<?php
/**
 * Payment payment method model
 *
 * @category    Splitit
 * @package     Splitit_Paymentmethod
 * @author      Ivan Weiler & Stjepan Udovičić
 * @copyright   Splitit (http://Splitit.net)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Splitit\Paymentmethod\Model;

use Magento\Framework\Exception\LocalizedException;

class Payment extends \Magento\Payment\Model\Method\Cc {
	const CODE = 'splitit_paymentmethod';

	protected $_code = self::CODE;

	protected $_isGateway = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = true;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;

	protected $_paymentApi = false;

	protected $countryFactory;

	protected $_supportedCurrencyCodes = array('USD');

	protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];

	protected $apiModel = null;
	protected $currency;
	protected $storeManager;
	protected $cart;
	protected $sourceInstallments;
	private $billingAddress;
	private $customerSession;
	private $helper;
	private $grandTotal = null;
	private $requestData = null;

	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		\Magento\Framework\Module\ModuleListInterface $moduleList,
		\Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
		\Magento\Directory\Model\CountryFactory $countryFactory,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Checkout\Model\Cart $cart,
		\Magento\Framework\App\RequestInterface $request,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Directory\Model\Currency $currency,
		\Splitit\Paymentmethod\Model\Api $apiModel,
		\Splitit\Paymentmethod\Helper\Data $helper,
		\Splitit\Paymentmethod\Model\Source\Installments $sourceInstallments,
		array $data = array()
	) {
		parent::__construct(
			$context,
			$registry,
			$extensionFactory,
			$customAttributeFactory,
			$paymentData,
			$scopeConfig,
			$logger,
			$moduleList,
			$localeDate,
			null,
			null,
			$data
		);

		$this->countryFactory = $countryFactory;

		$this->apiModel = $apiModel;
		$this->customerSession = $customerSession;
		$this->storeManager = $storeManager;
		$this->currency = $currency;
		$this->cart = $cart;

		$this->billingAddress = $cart->getQuote()->getBillingAddress();

		$this->sourceInstallments = $sourceInstallments;
		$this->helper = $helper;
		$this->grandTotal = round($cart->getQuote()->getGrandTotal(), 2);
		$this->requestData = $request->getParams();
	}

	/**
	 * Authorize payment abstract method
	 *
	 * @param \Magento\Framework\DataObject|InfoInterface $payment
	 * @param float $amount
	 * @return $this
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @api
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount) {

		/*if (!$this->canAuthorize()) {
			            throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available.'));
		*/

		try {

			$api = $this->apiModel->getApiUrl();
			$result = $this->createInstallmentPlan($api, $payment, $amount);
			$result = $this->helper->jsonDecode($result);

			/*show error if there is any error from spliti it when click on place order*/
			if (!$result["ResponseHeader"]["Succeeded"]) {
				$errorMsg = "";
				if (isset($result["serverError"])) {
					$errorMsg = $result["serverError"];
					$this->_logger->error(__($errorMsg));
					throw new \Magento\Framework\Validator\Exception(__($errorMsg));

				} else {
					foreach ($result["ResponseHeader"]["Errors"] as $key => $value) {
						$errorMsg .= $value["ErrorCode"] . " : " . $value["Message"];
					}
					$this->_logger->error(__($errorMsg));
					throw new \Magento\Framework\Validator\Exception(__($errorMsg));
				}

			}
			$this->customerSession->setInstallmentPlanNumber($result['InstallmentPlan']['InstallmentPlanNumber']);
			$payment->setTransactionId($result['InstallmentPlan']['InstallmentPlanNumber']);
			$payment->setIsTransactionClosed(0);
			$payment->setIsTransactionApproved(true);
			foreach (
				array(
					'ConsumerFullName',
					'Email',
					'Amount',
					'InstallmentNumber',
				) as $param) {

				unset($result[$param]);

			}
			$payment->setAdditionalInformation(
				[\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $result]
			);

			$order = $payment->getOrder();

			$order->addStatusToHistory(" ",
				'Payment InstallmentPlan was created with number ID: '
				. $result['InstallmentPlan']['InstallmentPlanNumber'] . ' with No# of Installments: ' . $this->customerSession->getSelectedInstallment(),
				false
			);

			/*call InstallmentPlan-UpdatePlan-Params for update "RefOrderNumber" after order creation*/
			$updateStatus = $this->updateRefOrderNumber($api, $order);
			if ($updateStatus["status"] == false) {

				$this->_logger->error(__($updateStatus["errorMsg"]));
				throw new \Magento\Framework\Validator\Exception(__($updateStatus["errorMsg"]));

			}
		} catch (\Exception $e) {
			$this->debugData(['request' => $this->requestData, 'exception' => $e->getMessage()]);
			//$this->_logger->error(__($e->getMessage()));
			$this->_logger->error(__('Payment Authorize error.'));
			throw new \Magento\Framework\Validator\Exception(__('Payment Authorize error.'));
		}

		return $this;
	}

	/**
	 * Payment capturing
	 *
	 * @param \Magento\Payment\Model\InfoInterface $payment
	 * @param float $amount
	 * @return $this
	 * @throws \Magento\Framework\Validator\Exception
	 */
	public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
		try {
			if (!$payment->getAuthorizationTransaction()) {
				$this->authorize($payment, $amount);
				$authNumber = $payment->getTransactionId();
			} else {
				$authNumber = $payment->getAuthorizationTransaction()->getTxnId();
			}
			$paymentAction = $this->helper->getPaymentAction();
			$params = array('InstallmentPlanNumber' => $authNumber);
			if ($paymentAction == "authorize_capture") {
				$api = $this->apiModel->getApiUrl();
				$sessionId = $this->apiModel->getorCreateSplititSessionid();
			} else {
				$api = $this->apiModel->apiLogin();
				$sessionId = $this->apiModel->getorCreateSplititSessionid();
			}
			$params = array_merge($params, array("RequestHeader" => array('SessionId' => $sessionId)));
			$api = $this->apiModel->getApiUrl();
			$result = $this->apiModel->makePhpCurlRequest($api, "InstallmentPlan/StartInstallments", $params);
			$result = $this->helper->jsonDecode($result);
			if (isset($result["ResponseHeader"]) && isset($result["ResponseHeader"]["Errors"]) && !empty($result["ResponseHeader"]["Errors"])) {
				$errorMsg = "";

				$errorCode = 503;
				$isErrorCode503Found = 0;
				foreach ($result["ResponseHeader"]["Errors"] as $key => $value) {
					$errorMsg .= $value["ErrorCode"] . " : " . $value["Message"];
					if ($value["ErrorCode"] == $errorCode) {
						$isErrorCode503Found = 1;
						break;
					}
				}

				if ($isErrorCode503Found == 0) {
					$this->_logger->error(__($errorMsg));
					throw new \Magento\Framework\Validator\Exception(__($errorMsg));
				}

			} elseif (isset($result["serverError"])) {
				$errorMsg = $result["serverError"];
				$this->_logger->error(__($errorMsg));
				throw new \Magento\Framework\Validator\Exception(__($errorMsg));
			}
			$payment->setIsTransactionClosed(1);
			$order = $payment->getOrder();

			$order->addStatusToHistory(
				false,
				'Payment NotifyOrderShipped was sent with number ID: ' . $authNumber, false
			);
			$order->save();

		} catch (\Exception $e) {
			$this->debugData(['request' => $this->requestData, 'exception' => $e->getMessage()]);
			$this->_logger->error(__('Payment capturing error.'));
			throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
		}

		return $this;
	}

	/**
	 * Cancel payment
	 *
	 * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface|Payment $payment
	 * @return $this
	 */
	public function cancel(\Magento\Payment\Model\InfoInterface $payment) {
		$transactionId = $payment->getParentTransactionId();
		try {
			$apiLogin = $this->apiModel->apiLogin();
			$api = $this->apiModel->getApiUrl();
			$installmentPlanNumber = $payment->getAuthorizationTransaction()->getTxnId();
			$ipn = substr($installmentPlanNumber, 0, strpos($installmentPlanNumber, '-'));
			if ($ipn != "") {
				$installmentPlanNumber = $ipn;
			}
			$params = array(
				"RequestHeader" => array(
					"SessionId" => $this->apiModel->getorCreateSplititSessionid(),
				),
				"InstallmentPlanNumber" => $installmentPlanNumber,
				"RefundUnderCancelation" => "OnlyIfAFullRefundIsPossible",
			);

			$result = $this->apiModel->makePhpCurlRequest($api, "InstallmentPlan/Cancel", $params);
			$result = $this->helper->jsonDecode($result);
			if (isset($result["ResponseHeader"]) && isset($result["ResponseHeader"]["Errors"]) && !empty($result["ResponseHeader"]["Errors"])) {
				$errorMsg = "";

				$errorCode = 503;
				$isErrorCode503Found = 0;
				foreach ($result["ResponseHeader"]["Errors"] as $key => $value) {
					$errorMsg .= $value["ErrorCode"] . " : " . $value["Message"];
					if ($value["ErrorCode"] == $errorCode) {
						$isErrorCode503Found = 1;
						break;
					}
				}

				if ($isErrorCode503Found == 0) {
					$this->_logger->error(__($errorMsg));
					throw new \Magento\Framework\Validator\Exception(__($errorMsg));
				}

			} elseif (isset($result["serverError"])) {
				$errorMsg = $result["serverError"];
				$this->_logger->error(__($errorMsg));
				throw new \Magento\Framework\Validator\Exception(__($errorMsg));
			}
		} catch (\Exception $e) {
			$this->debugData(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
			$this->_logger->error(__('Payment cancel error.'));
			throw new \Magento\Framework\Validator\Exception(__('Payment cancel error.'));
		}

		return $this;
	}

	/**
	 * Payment refund
	 *
	 * @param \Magento\Payment\Model\InfoInterface $payment
	 * @param float $amount
	 * @return $this
	 * @throws \Magento\Framework\Validator\Exception
	 */
	public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount) {
		$transactionId = $payment->getParentTransactionId();
		try {
			$apiLogin = $this->apiModel->apiLogin();
			$api = $this->apiModel->getApiUrl();
			$installmentPlanNumber = $payment->getAuthorizationTransaction()->getTxnId();
			$ipn = substr($installmentPlanNumber, 0, strpos($installmentPlanNumber, '-'));
			if ($ipn != "") {
				$installmentPlanNumber = $ipn;
			}
			$params = array(
				"RequestHeader" => array(
					"SessionId" => $this->apiModel->getorCreateSplititSessionid(),
				),
				"InstallmentPlanNumber" => $installmentPlanNumber,
				"Amount" => array("Value" => $amount),
				"_RefundStrategy" => "FutureInstallmentsFirst",

			);

			$result = $this->apiModel->makePhpCurlRequest($api, "InstallmentPlan/Refund", $params);
			$result = $this->helper->jsonDecode($result);

			if (isset($result["ResponseHeader"]) && isset($result["ResponseHeader"]["Errors"]) && !empty($result["ResponseHeader"]["Errors"])) {
				$errorMsg = "";

				$errorCode = 503;
				$isErrorCode503Found = 0;
				foreach ($result["ResponseHeader"]["Errors"] as $key => $value) {
					$errorMsg .= $value["ErrorCode"] . " : " . $value["Message"];
					if ($value["ErrorCode"] == $errorCode) {
						$isErrorCode503Found = 1;
						break;
					}
				}

				if ($isErrorCode503Found == 0) {
					$this->_logger->error(__($errorMsg));
					throw new \Magento\Framework\Validator\Exception(__($errorMsg));
				}

			} elseif (isset($result["serverError"])) {
				$errorMsg = $result["serverError"];
				$this->_logger->error(__($errorMsg));
				throw new \Magento\Framework\Validator\Exception(__($errorMsg));
			}
		} catch (\Exception $e) {
			$this->debugData(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
			$this->_logger->error(__('Payment refunding error.'));
			throw new \Magento\Framework\Validator\Exception(__('Payment refunding error.'));
		}

		$payment
			->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
			->setParentTransactionId($transactionId)
			->setIsTransactionClosed(1)
			->setShouldCloseParentTransaction(1);

		return $this;
	}

	/**
	 * Determine method availability based on quote amount and config data
	 *
	 * @param \Magento\Quote\Api\Data\CartInterface|null $quote
	 * @return bool
	 */
	public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {

		if ($this->checkAvailableInstallments($quote) && $this->checkProductBasedAvailability()) {
			return parent::isAvailable($quote);
		} else {
			return false;
		}
	}

	/**
	 * Availability for currency
	 *
	 * @param string $currencyCode
	 * @return bool
	 */
	public function canUseForCurrency($currencyCode) {
		return true;
	}

	/**
	 * Check if single installment is applicable
	 * @return boolean
	 */
	private function isOneInstallment() {
		$selectInstallmentSetup = $this->helper->getSelectInstallmentSetup();
		$options = $this->sourceInstallments->toOptionArray();
		$currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
		$currencySymbol = $this->currency->load($currentCurrencyCode)->getCurrencySymbol();

		$countInstallments = $installmentValue = 0;
		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			$installments = $this->helper->getFixedInstallment();

			if ($installments) {
				foreach (explode(',', $installments) as $value) {
					$installmentValue = $value;
					$countInstallments++;
				}

			}
		} else {
			$totalAmount = $this->grandTotal;
			$depandingOnCartInstallments = $this->helper->getDepandingOnCartTotalValues();
			$depandingOnCartInstallmentsArr = $this->helper->jsonDecode($depandingOnCartInstallments);
			$dataAsPerCurrency = [];
			foreach ($depandingOnCartInstallmentsArr as $data) {
				$dataAsPerCurrency[$data['doctv']['currency']][] = $data['doctv'];
			}

			if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

				foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
					if ($totalAmount >= $data['from'] && !empty($data['to']) && $totalAmount <= $data['to']) {
						foreach (explode(',', $data['installments']) as $n) {
							$installmentValue = $n;
							$countInstallments++;
						}
						break;
					} else if ($totalAmount >= $data['from'] && empty($data['to'])) {
						foreach (explode(',', $data['installments']) as $n) {
							$installmentValue = $n;
							$countInstallments++;
						}
						break;
					}
				}
			}

		}
		if ($countInstallments == 1 && $installmentValue == 1) {
			return true;
		}
		return false;
	}

	/**
	 * Create installment plan to Splitit
	 * @param approvalUrl string
	 * @param payment object
	 * @param amount float
	 * @return json
	 */
	protected function createInstallmentPlan($api, $payment, $amount) {
		$cultureName = $this->helper->getCultureName();
		$this->_logger->error(__('creating installment plan-----'));

		$guestEmail = "";
        if (isset($this->requestData["guestEmail"])) {
            $guestEmail = $this->requestData["guestEmail"];
        }

		$customerInfo = $this->customerSession->getCustomer()->getData();
		if (!isset($customerInfo["firstname"])) {
			$customerInfo["firstname"] = $this->billingAddress->getFirstname();
			$customerInfo["lastname"] = $this->billingAddress->getLastname();
			$customerInfo["email"] = $this->billingAddress->getEmail();
		}
		if ($customerInfo["email"] == "") {
			$customerInfo["email"] = $guestEmail;
		}
		$billingStreet1 = "";
		$billingStreet2 = "";
		if (isset($this->billingAddress->getStreet()[0])) {
			$billingStreet1 = $this->billingAddress->getStreet()[0];
		}
		if (isset($this->billingAddress->getStreet()[1])) {
			$billingStreet2 = $this->billingAddress->getStreet()[1];
		}

		/*check if cunsumer dont filled data in billing form in case of onepage checkout.*/
		$billingFieldsEmpty = $this->apiModel->checkForBillingFieldsEmpty();
		if (!$billingFieldsEmpty["status"]) {
			$response["errorMsg"] = $billingFieldsEmpty["errorMsg"];
			return $response;
		}

		if ($this->isOneInstallment()) {
			$this->_logger->error(__('is one installment-----'));
			$apiLogin = $this->apiModel->apiLogin();
			$params = $this->apiModel->createDataForInstallmentPlanInit(1);
			$params["CreditCardDetails"] = [
				"CardCvv" => $payment->getCcCid(),
				"CardNumber" => $payment->getCcNumber(),
				"CardExpYear" => $payment->getCcExpYear(),
				"CardExpMonth" => $payment->getCcExpMonth(),
			];
			$params["PlanApprovalEvidence"] = [
				"AreTermsAndConditionsApproved" => "True",
			];
			$this->_logger->error("====1 installment ====");
			$this->_logger->error($this->helper->jsonEncode($params));
			$this->_logger->error("==== END ====");
		} else {
			$this->_logger->error(__('normal installment-----'));
			$params = [
				"RequestHeader" => [
					"SessionId" => $this->apiModel->getorCreateSplititSessionid(),
					"ApiKey" => $this->helper->getApiTerminalKey("splitit_payment"),
					"CultureName" => $cultureName,
				],
				"InstallmentPlanNumber" => $this->customerSession->getInstallmentPlanNumber(),
				"CreditCardDetails" => [
					"CardCvv" => $payment->getCcCid(),
					"CardNumber" => $payment->getCcNumber(),
					"CardExpYear" => $payment->getCcExpYear(),
					"CardExpMonth" => $payment->getCcExpMonth(),
				],
				"PlanApprovalEvidence" => [
					"AreTermsAndConditionsApproved" => "True",
				],
			];
		}
		$params["BillingAddress"] = [
				"AddressLine" => $billingStreet1,
				"AddressLine2" => $billingStreet2,
				"City" => $this->billingAddress->getCity(),
				"State" => $this->billingAddress->getRegion(),
				"Country" => $this->countryFactory->create()->loadByCode($this->billingAddress->getCountry())->getName('en_US'),
				"Zip" => $this->billingAddress->getPostcode(),
			];
		$params["ConsumerData"] = [
				"FullName" => $customerInfo["firstname"] . " " . $customerInfo["lastname"],
				"Email" => $customerInfo["email"],
				"PhoneNumber" => $this->billingAddress->getTelephone(),
				"CultureName" => $cultureName,
			];
		$this->_logger->error(print_r($params, true));
		$result = $this->apiModel->makePhpCurlRequest($api, "InstallmentPlan/Create", $params);
		$this->_logger->error(print_r($result, true));
		return $result;
	}

	/**
	 * Update order in magento
	 * @param api object
	 * @param order int
	 * @return array
	 */
	public function updateRefOrderNumber($api, $order) {
		$params = [
			"RequestHeader" => [
				"SessionId" => $this->apiModel->getorCreateSplititSessionid(),
			],
			"InstallmentPlanNumber" => $this->customerSession->getInstallmentPlanNumber(),
			"PlanData" => [
				"ExtendedParams" => [
					"CreateAck" => "Received",
				],
				"RefOrderNumber" => $order->getIncrementId(),
			],
		];
		$response = ["status" => false, "errorMsg" => ""];
		$result = $this->apiModel->makePhpCurlRequest($api, "InstallmentPlan/Update", $params);
		$decodedResult = $this->helper->jsonDecode($result);
		if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
			$response["status"] = true;
		} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
			$errorMsg = "";
			$errorCount = count($decodedResult["ResponseHeader"]["Errors"]);
			$i = 1;
			foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < $errorCount) {
					$errorMsg .= ", ";
				}
				$i++;
			}

			$response["errorMsg"] = $errorMsg;
		}
		return $response;

	}

	/**
	 * Check available installments
	 * @param quote object
	 * @return bool
	 */
	public function checkAvailableInstallments($quote) {
		$installments = array();
		$totalAmount = $this->grandTotal;
		$selectInstallmentSetup = $this->helper->getSelectInstallmentSetup();

		$options = $this->sourceInstallments->toOptionArray();

		$depandOnCart = 0;

		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			/*Select Fixed installment setup*/
			$fixedInstallments = $this->helper->getFixedInstallment();
			$installments = explode(',', $fixedInstallments);
			if (count($installments) > 0) {
				return true;
			}

		} else {
			/*Select Depanding on cart installment setup*/
			$depandOnCart = 1;
			$depandingOnCartInstallments = $this->helper->getDepandingOnCartTotalValues();
			$depandingOnCartInstallmentsArr = $this->helper->jsonDecode($depandingOnCartInstallments);
			$dataAsPerCurrency = [];
			foreach ($depandingOnCartInstallmentsArr as $data) {
				$dataAsPerCurrency[$data['doctv']['currency']][] = $data['doctv'];
			}
			$currentCurrencyCode = $this->storeManager->getStore()->getCurrentCurrencyCode();
			if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

				foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
					if ($totalAmount >= $data['from'] && !empty($data['to']) && $totalAmount <= $data['to']) {
						foreach (explode(',', $data['installments']) as $n) {
							if ((array_key_exists($n, $options))) {
								$installments[$n] = $n;
							}
						}
						break;
					} else if ($totalAmount >= $data['from'] && empty($data['to'])) {
						foreach (explode(',', $data['installments']) as $n) {

							if ((array_key_exists($n, $options))) {
								$installments[$n] = $n;
							}
						}
						break;
					}
				}
			}
			if (count($installments) > 0) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Check product based availability of module
	 * @return bool
	 */
	public function checkProductBasedAvailability() {
		$check = TRUE;
		if ($this->helper->getSplititPerProduct()) {

			/*get array of all items what can be display directly*/
			$itemsVisible = $this->cart->getQuote()->getAllVisibleItems();
			$allowedProducts = $this->helper->getSplititProductSkus();
			$allowedProducts = explode(',', $allowedProducts);
			if ($this->helper->getSplititPerProduct() == 1) {
				$check = TRUE;
				foreach ($itemsVisible as $item) {
					if (!in_array($item->getProductId(), $allowedProducts)) {
						$check = FALSE;
						break;
					}
				}
			}
			if ($this->helper->getSplititPerProduct() == 2) {
				$check = FALSE;
				foreach ($itemsVisible as $item) {
					if (in_array($item->getProductId(), $allowedProducts)) {
						$check = TRUE;
						break;
					}
				}
			}
		}
		return $check;
	}

}
