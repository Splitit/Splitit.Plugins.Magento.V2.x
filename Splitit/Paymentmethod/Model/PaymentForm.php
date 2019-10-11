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

class PaymentForm {

	const CODE = 'splitit_paymentredirect';

	protected $_code = self::CODE;
	protected $_isInitializeNeeded = true;
	protected $_canUseInternal = true;
	protected $_canUseForMultishipping = false;
//    protected $_formBlockType = 'pis_payment/form_pisPaymentForm';
	//    protected $_infoBlockType = 'pis_payment/info_pis';
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canCaptureOnce = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = false;
	//protected $_canUseInternal              = false;
	protected $_canUseCheckout = true;
	//protected $_infoBlockType = 'pis_payment/info_pis';
	protected $_canCancel = false;
	protected $api;
	protected $helper;
	protected $_checkoutSession;
	protected $customerSession;
	protected $quote;
	protected $quoteValidator;
	protected $jsonHelper;
	protected $_store;
	protected $objectManager;
	protected $logger;
	protected $orderPlace;
	protected $productModel;

	public function __construct(
		\Psr\Log\LoggerInterface $logger,
		Api $api,
		\Magento\Quote\Model\QuoteValidator $quoteValidator,
		Helper\OrderPlace $orderPlace,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Store\Api\Data\StoreInterface $store,
		\Magento\Framework\UrlInterface $urlBuilder,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Magento\Checkout\Model\Session $_checkoutSession
	) {
		$this->api = $api;
		$this->quoteValidator = $quoteValidator;
		$this->orderPlace = $orderPlace;
		$this->_checkoutSession = $_checkoutSession;
		$this->customerSession = $customerSession;
		$this->_store = $store;
		$this->urlBuilder = $urlBuilder;
		$this->jsonHelper = $jsonHelper;
		$this->logger = $logger;
		$this->quote = $this->_checkoutSession->getQuote();
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$this->objectManager = $objectManager;
		$this->helper = $objectManager->get('Splitit\Paymentmethod\Helper\Data');
		$this->productModel = $objectManager->get('Magento\Catalog\Model\Product');
	}

	public function orderPlaceRedirectUrl() {

		$api = $this->_initApi();

//        $this->_checkoutSession->setInstallmentsInDropdownForPaymentForm(explode(',',$this->helper->getConfig('payment/splitit_paymentredirect/fixed_installment')));
		$response = array(
			"status" => false,
			"error" => "",
			"success" => "",
			"data" => "",
			"checkoutUrl" => "",
			"installmentNum" => "1",
		);

		//check for address
		$quote = $this->quote;
		$billAddress = $quote->getBillingAddress();
		$customerInfo = $this->customerSession->getCustomer()->getData();
		if (!isset($customerInfo["firstname"])) {
			$customerInfo["firstname"] = $billAddress->getFirstname();
			$customerInfo["lastname"] = $billAddress->getLastname();
			$customerInfo["email"] = $billAddress->getEmail();
		}
		$bags = $billAddress->getStreet();

		$validateAddress = $this->checkForBillingFieldsEmpty($billAddress, $customerInfo);
		if (!$validateAddress['status']) {
			$response["status"] = false;
			$response["error"] = true;
			$response["errorMsg"] = $validateAddress['errorMsg'];

			return $response;
		}
		$initResponse = $this->installmentplaninitForHostedSolution();
//        print_r($initResponse);
		//        echo 5/0;
		//        return $initResponse;
		$response["data"] = $initResponse["data"];
		if ($initResponse["status"]) {
			$response["status"] = true;
		}

		if (isset($initResponse["checkoutUrl"]) && $initResponse["checkoutUrl"] != "") {
			$response["checkoutUrl"] = $initResponse["checkoutUrl"];
//            print_r($initResponse);
			//            print_r($response);
			//            die("--eeeeee");
			$quote = $this->quote;
			$billAddress = $quote->getBillingAddress();
			$customerInfo = $this->customerSession->getCustomer()->getData();
			if (!isset($customerInfo["firstname"])) {
				$customerInfo["firstname"] = $billAddress->getFirstname();
				$customerInfo["lastname"] = $billAddress->getLastname();
				$customerInfo["email"] = $billAddress->getEmail();
			}
			$bags = $billAddress->getStreet();

			if (!($bags[0] == "" || $billAddress->getCity() == "" || $billAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $billAddress->getTelephone() == "")) {
				if ($this->quoteValidator->validateBeforeSubmit($quote)) {
//                    echo "validated quote";exit;
					$this->orderPlace->execute($quote, array());
				}
			}
			$this->_checkoutSession->setSplititQuoteId($quote->getId());
			$this->_checkoutSession->setSplititCheckoutUrl($response["checkoutUrl"]);
			$this->_checkoutSession->setSplititInstallmentPlanNumber($initResponse["installmentPlanNumber"]);

			return $response;
		} else {
			$this->logger->error(__($response['data']));
			$response["status"] = false;
			$response["error"] = true;
			return $response;
//            throw new \Magento\Framework\Validator\Exception(__($response['data']));
		}
	}

	public function checkForBillingFieldsEmpty($billingAddress, $customerInfo) {

		$response = ["errorMsg" => "", "successMsg" => "", "status" => false];
		if ($billingAddress->getStreet()[0] == "" || $billingAddress->getCity() == "" || $billingAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $billingAddress->getTelephone() == "") {
			$response["errorMsg"] = "Please fill required fields.";
		} else if (strlen($billingAddress->getTelephone()) < 5 || strlen($billingAddress->getTelephone()) > 14) {

			$response["errorMsg"] = __("Splitit does not accept phone number less than 5 digits or greater than 14 digits.");
		} elseif (!$billingAddress->getCity()) {
			$response["errorMsg"] = __("Splitit does not accept empty city field.");
		} elseif (!$billingAddress->getCountry()) {
			$response["errorMsg"] = ("Splitit does not accept empty country field.");
		} elseif (!$billingAddress->getPostcode()) {
			$response["errorMsg"] = ("Splitit does not accept empty postcode field.");
		} elseif (!$customerInfo["firstname"]) {
			$response["errorMsg"] = ("Splitit does not accept empty customer name field.");
		} elseif (strlen($customerInfo["firstname"] . ' ' . $customerInfo['lastname']) < 3) {
			$response["errorMsg"] = ("Splitit does not accept less than 3 characters customer name field.");
		} elseif (!filter_var($customerInfo['email'], FILTER_VALIDATE_EMAIL)) {
			$response["errorMsg"] = ("Splitit does not accept invalid customer email field.");
		} else {
			$response["status"] = true;
		}
		return $response;
	}

	public function getCheckoutRedirectUrl() {
		$data = $this->orderPlaceRedirectUrl();
		return $data['checkoutUrl'];
	}

	/**
	 * Validate payment method information object
	 *
	 * @return $this
	 */
	public function validate() {
		$info = $this->getInfoInstance();
		$no = $info->getInstallmentsNo();
		$terms = $info->getAdditionalInformation('terms');
		$errorMsg = '';

		/* if (empty($no)) {
			          $errorMsg = $this->_getHelper()->__('Installments are required fields');
		*/
		/* if (empty($terms)) {
			          $errorMsg = $this->_getHelper()->__('You should accept terms and conditions');
		*/
		if ($errorMsg) {
			throw new \Magento\Framework\Validator\Exception($errorMsg);
		}

		return $this;
	}

	public function splititCapture($payment, $sessionId, $transactionId) {
		$api = $this->getApi();
		//$authNumber = $payment->getAuthorizationTransaction()->getTxnId();
		$params = array(
			"RequestHeader" => array("SessionId" => $sessionId),
			"InstallmentPlanNumber" => $transactionId,
		);
		$result = $api->startInstallment($this->api->getApiUrl(), $params);
		if (isset($result["ResponseHeader"]) && isset($result["ResponseHeader"]["Errors"]) && !empty($result["ResponseHeader"]["Errors"])) {
			$e = $api->getError();
			throw new \Magento\Framework\Validator\Exception($e['code'] . ' ' . $e['message']);
		}
		$payment->setIsTransactionClosed(1);
		$order = $payment->getOrder();

		$order->addStatusToHistory(
			false, 'Payment NotifyOrderShipped was sent with number ID: ' . $authNumber, false
		);
		$order->save();
		return $result;
	}

	protected function createInstallmentPlan($api, $payment, $amount) {
		$cultureName = $this->helper->getCultureName(true);
		$params = array(
			"RequestHeader" => array(
				"SessionId" => $this->api->getorCreateSplititSessionid(),
				"ApiKey" => $this->getConfigData('api_terminal_key', $storeId),
				"CultureName" => $cultureName,
			),
			"InstallmentPlanNumber" => $this->customerSession->getInstallmentPlanNumber(),
			"CreditCardDetails" => array(
				"CardCvv" => $payment->getCcCid(),
				"CardNumber" => $payment->getCcNumber(),
				"CardExpYear" => $payment->getCcExpYear(),
				"CardExpMonth" => $payment->getCcExpMonth(),
			),
			"PlanApprovalEvidence" => array(
				"AreTermsAndConditionsApproved" => "True",
			),
		);
		$result = $api->createInstallmentPlan($this->api->getApiUrl(), $params);
		if (isset($result["ResponseHeader"]) && isset($result["ResponseHeader"]["Errors"]) && !empty($result["ResponseHeader"]["Errors"])) {
			$e = $api->getError();
			throw new \Magento\Framework\Validator\Exception($e['code'] . ' ' . $e['message']);
		}
		return $result;
	}

	/**
	 * @param $storeId int
	 *
	 * @return PayItSimple_Payment_Model_Api
	 * @throws Mage_Payment_Exception
	 */
	public function _initApi() {
		$dataForLogin = array(
			'UserName' => $this->helper->getConfig("payment/splitit_paymentredirect/api_username"),
			'Password' => $this->helper->getConfig("payment/splitit_paymentredirect/api_password"),
			'TouchPoint' => array("Code" => "MagentoPlugin", "Version" => "v2.1"),
		);
		$result = $this->api->apiLogin($dataForLogin);
		if (isset($result["serverError"])) {
			throw new \Magento\Framework\Validator\Exception(__($result["serverError"]));
		}
		return $result;
	}

	public function getValidNumberOfInstallments($api) {
		return $result = $api->getValidNumberOfInstallments();
	}

	public function updateRefOrderNumber($api, $order) {

		$params = array(
			"RequestHeader" => array(
				"SessionId" => $this->api->getorCreateSplititSessionid(),
			),
			"InstallmentPlanNumber" => $this->_checkoutSession->getSplititInstallmentPlanNumber(),
			"PlanData" => array(
				"ExtendedParams" => array(
					"CreateAck" => "Received",
				),
				"RefOrderNumber" => $order->getIncrementId(),
			),
		);
		$this->logger->addDebug('========== splitit update ref order number params ==============');
		$this->logger->addDebug(print_r($params, TRUE));
		$response = array("status" => false, "data" => "");
		$result = $api->updateRefOrderNumber($this->api->getApiUrl(), $params);
		$decodedResult = $this->jsonHelper->jsonDecode($result);
		if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
			$response["status"] = true;
		} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
			$errorMsg = "";
			$i = 1;
			foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < count($decodedResult["ResponseHeader"]["Errors"])) {
					$errorMsg .= ", ";
				}
				$i++;
			}

			$response["data"] = $errorMsg;
		}
		return $response;
	}

	public function installmentplaninit($api, $selectedInstallment) {
		$session = $this->_checkoutSession;
		$quote_id = $session->getQuoteId();
		$firstInstallmentAmount = $this->getFirstInstallmentAmount($selectedInstallment);
		$checkout = $this->_checkoutSession->getQuote();
		$billAddress = $checkout->getBillingAddress();
		$BillingAddressArr = $billAddress->getData();
		$customerInfo = $this->customerSession->getCustomer()->getData();
		if (!isset($customerInfo["firstname"])) {
			$customerInfo["firstname"] = $billAddress->getFirstname();
			$customerInfo["lastname"] = $billAddress->getLastname();
			$customerInfo["email"] = $billAddress->getEmail();
		}
		$cultureName = $this->helper->getCultureName(true);

		try {
			$params = $this->installmentplaninitParams($firstInstallmentAmount, $billAddress, $customerInfo, $cultureName, null, $selectedInstallment);

			$response = array("status" => false, "data" => "");
			// check if cunsumer dont filled data
			$bags = $billAddress->getStreet();
			if ($bags[0] == "" || $billAddress->getCity() == "" || $billAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $billAddress->getTelephone() == "") {
				$response["emptyFields"] = true;
				$response["data"] = "Please fill required fields.";
				return $response;
			}

//            $result = Mage::getSingleton("pis_payment/api")->installmentplaninit($this->getApiUrl(), $params);
			$result = $this->api->installmentplaninit($this->api->getApiUrl(), $params);
			// check for approval URL from response
			$decodedResult = $this->jsonHelper->jsonDecode($result);

			if (isset($decodedResult) && isset($decodedResult["ApprovalUrl"]) && $decodedResult["ApprovalUrl"] != "") {
				$intallmentPlan = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
				// set Installment plan number into session
				$this->customerSession->setInstallmentPlanNumber($intallmentPlan);
//                $approvalUrlResponse = Mage::getSingleton("pis_payment/api")->getApprovalUrlResponse($decodedResult["ApprovalUrl"]);
				$approvalUrlResponse = $this->api->getApprovalUrlResponse($decodedResult["ApprovalUrl"]);
				$approvalUrlRes = $this->jsonHelper->jsonDecode($approvalUrlResponse);
				if (isset($approvalUrlRes["Global"]["ResponseResult"]["Errors"]) && count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])) {
					$i = 1;
					$errorMsg = "";
					foreach ($approvalUrlRes["Global"]["ResponseResult"]["Errors"] as $key => $value) {
						$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
						if ($i < count($approvalUrlRes["Global"]["ResponseResult"]["Errors"])) {
							$errorMsg .= ", ";
						}
						$i++;
					}
					$response["data"] = $errorMsg;
				} else if (isset($approvalUrlRes["serverError"])) {
					$response["data"] = $decodedResult["serverError"];
				} else {
					$popupHtml = $this->createPopupHtml($approvalUrlResponse);
					$response["status"] = true;
					$response["data"] = $popupHtml;
				}

				//print_r($approvalUrlResponse);die("---approvalUrlResponse");
			} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
				$errorMsg = "";
				$i = 1;
				foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
					$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
					if ($i < count($decodedResult["ResponseHeader"]["Errors"])) {
						$errorMsg .= ", ";
					}
					$i++;
				}

				$response["data"] = $errorMsg;
			} else if (isset($decodedResult["serverError"])) {
				$response["data"] = $decodedResult["serverError"];
			}
		} catch (\Magento\Framework\Validator\Exception $e) {
			$response["data"] = $e->getMessage();
		}
		return $response;
		//return $result;
	}

	public function installmentplaninitForHostedSolution() {
		$session = $this->_checkoutSession;
		$quote_id = $session->getQuoteId();
		$firstInstallmentAmount = $this->getFirstInstallmentAmountHosted();
		$checkout = $this->_checkoutSession->getQuote();
		$billAddress = $checkout->getBillingAddress();
		$BillingAddressArr = $billAddress->getData();
		$customerInfo = $this->customerSession->getCustomer()->getData();
		$numOfInstallments = $this->_checkoutSession->getInstallmentsInDropdownForPaymentForm();

		if (!isset($customerInfo["firstname"])) {
			$customerInfo["firstname"] = $billAddress->getFirstname();
			$customerInfo["lastname"] = $billAddress->getLastname();
			$customerInfo["email"] = $billAddress->getEmail();
		}
		$cultureName = $this->helper->getCultureName(true);
		$params = $this->installmentplaninitParams($firstInstallmentAmount, $billAddress, $customerInfo, $cultureName, $numOfInstallments, null);
		$this->logger->error('======= installmentplaninitForHostedSolution : params passed to Initit Api ======= : ');
		$this->logger->error(print_r($params, TRUE));
		$this->logger->error(json_encode($params));
		$this->logger->error('======= END ======= : ');

		try {
			$response = array("status" => false, "data" => "", "checkoutUrl" => "");
			// check if cunsumer dont filled data
			$bags = $billAddress->getStreet();
			if ($bags[0] == "" || $billAddress->getCity() == "" || $billAddress->getPostcode() == "" || $customerInfo["firstname"] == "" || $customerInfo["lastname"] == "" || $customerInfo["email"] == "" || $billAddress->getTelephone() == "") {
				$response["emptyFields"] = true;
				$response["data"] = "Please fill required fields.";
				return $response;
			}

			$result = $this->api->installmentplaninitforhostedsolution($params);
			// check for checkout URL from response
			$decodedResult = $this->jsonHelper->jsonDecode($result);

			if (isset($decodedResult) && isset($decodedResult["CheckoutUrl"]) && $decodedResult["CheckoutUrl"] != "") {

				$response["status"] = true;
				$response["checkoutUrl"] = $decodedResult["CheckoutUrl"];
				$installmentPlan = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
				$response["installmentPlanNumber"] = $decodedResult["InstallmentPlan"]["InstallmentPlanNumber"];
//                return $response;
				// store installment plan number in session, so that will not call init again & again if customer clicks on radio button
				//$this->customerSession->setSplititInstallmentPlanNumber($installmentPlan);
				$this->logger->addDebug('======= installmentplaninit : response from splitit =======InstallmentPlanNumber : ' . $installmentPlan);
				$this->logger->addDebug(print_r($decodedResult, TRUE));
				// store information in splitit_hosted_solution for successExit and Async
				$customerId = 0;
				if ($this->customerSession->isLoggedIn()) {
					$customerData = $this->customerSession->getCustomer();
					$customerId = $customerData->getId();
				}
//                $db_write = Mage::getSingleton('core/resource')->getConnection('core_write');
				//                $tablePrefix = (string) Mage::getConfig()->getTablePrefix();
				//                $cartItemCount = Mage::helper('checkout/cart')->getSummaryCount();
				//                $grandTotal = $this->_checkoutSession->getQuote()->getGrandTotal();
				//                $passedData = json_encode($params);
				//
				//                $sql = 'INSERT INTO `' . $tablePrefix . 'splitit_hosted_solution` (`installment_plan_number`, `quote_id`, `quote_item_count`, `customer_id`, `base_grand_total`, `additional_data`) VALUES ("' . $installmentPlan . '", ' . $quote_id . ', ' . $cartItemCount . ', ' . $customerId . ', ' . $grandTotal . ',\'' . $passedData . '\')';
				//                $db_write->query($sql);
			} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
				$errorMsg = "";
				$i = 1;
				foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
					$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
					if ($i < count($decodedResult["ResponseHeader"]["Errors"])) {
						$errorMsg .= ", ";
					}
					$i++;
				}

				$response["data"] = $errorMsg;
			} else if (isset($decodedResult["serverError"])) {
				$response["data"] = $decodedResult["serverError"];
			}
		} catch (\Magento\Framework\Validator\Exception $e) {
			$response["data"] = $e->getMessage();
		}
		return $response;
		//return $result;
	}

	public function installmentplaninitParams($firstInstallmentAmount, $billAddress, $customerInfo, $cultureName, $numOfInstallments = null, $selectedInstallment) {
		$paymentAction = $this->helper->getConfig("payment/splitit_paymentredirect/payment_action");
		$autoCapture = false;
		if ($paymentAction == "authorize_capture") {
			$autoCapture = true;
		}
		$getStreet = $billAddress->getStreet();

		$params = array(
			"RequestHeader" => array(
				"SessionId" => $this->api->getorCreateSplititSessionid(),
				"ApiKey" => $this->helper->getConfig("payment/splitit_paymentredirect/api_terminal_key"),
			),
			"PlanData" => array(
				"Amount" => array(
					"Value" => round($this->_checkoutSession->getQuote()->getGrandTotal(), 2),
					"CurrencyCode" => $this->_store->getCurrentCurrency()->getCode(),
				),
				//"NumberOfInstallments" => $selectedInstallment,
				"PurchaseMethod" => "ECommerce",
				"RefOrderNumber" => $this->_checkoutSession->getLastOrderId(),
				"AutoCapture" => $autoCapture,
				"ExtendedParams" => array(
					"CreateAck" => "NotReceived",
				),
			),
			"BillingAddress" => array(
				"AddressLine" => isset($getStreet[0]) ? $getStreet[0] : "",
				"AddressLine2" => isset($getStreet[1]) ? $getStreet[1] : "",
				"City" => $billAddress->getCity(),
				"State" => $billAddress->getRegion(),
				"Country" => $billAddress->getCountry(),
				"Zip" => $billAddress->getPostcode(),
			),
			"ConsumerData" => array(
				"FullName" => $customerInfo["firstname"] . " " . $customerInfo["lastname"],
				"Email" => $customerInfo["email"],
				"PhoneNumber" => $billAddress->getTelephone(),
				"CultureName" => $cultureName,
			),
		);

		if ($firstInstallmentAmount) {
			$params['PlanData']["FirstInstallmentAmount"] = array(
				"Value" => $firstInstallmentAmount,
				"CurrencyCode" => $this->_store->getCurrentCurrency()->getCode(),
			);
		}

		$cart = $this->quote;
		$itemsArr = array();
		$i = 0;
		$currencyCode = $this->_store->getCurrentCurrency()->getCode();
		foreach ($cart->getAllItems() as $item) {
			$description = $this->productModel->load($item->getProductId())->getShortDescription();
			$itemsArr[$i]["Name"] = $item->getName();
			$itemsArr[$i]["SKU"] = $item->getSku();
			$itemsArr[$i]["Price"] = array("Value" => round($item->getPrice(), 2), "CurrencyCode" => $currencyCode);
			$itemsArr[$i]["Quantity"] = $item->getQty();
			$itemsArr[$i]["Description"] = strip_tags($description);
//            $itemsArr[$i]["Description"] = $product->getShortDescription();
			$i++;
		}
		$params['CartData'] = array(
			"Items" => $itemsArr,
			"AmountDetails" => array(
				"Subtotal" => round($this->_checkoutSession->getQuote()->getSubtotal(), 2),
				"Tax" => round($this->_checkoutSession->getQuote()->getShippingAddress()->getData('tax_amount'), 2),
				"Shipping" => round($this->_checkoutSession->getQuote()->getShippingAddress()->getShippingAmount(), 2),
			),
		);

		$paymentWizardData = array(
			"PaymentWizardData" => array(
				"RequestedNumberOfInstallments" => implode(',', array_keys($numOfInstallments)),
				"SuccessAsyncURL" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/successasync'),
				"SuccessExitURL" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/success'),
				"CancelExitURL" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/cancel'),
			),
		);
		// check for 3d secure yes or no
		$_3d_secure = $this->helper->getConfig("payment/splitit_paymentredirect/splitit_3d_secure");
		$_3d_minimal_amount = $this->helper->getConfig("payment/splitit_paymentredirect/splitit_3d_minimal_amount");
		if (!$_3d_minimal_amount) {
			$_3d_minimal_amount = 0;
		}

		$grandTotal = round($this->_checkoutSession->getQuote()->getGrandTotal(), 2);
		if ($_3d_secure != "" && $_3d_secure == 1 && $grandTotal >= $_3d_minimal_amount) {
			$params['PlanData']["Attempt3DSecure"] = true;
			$params["RedirectUrls"] = array(
				"Succeeded" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/success'),
				"Failed" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/cancel'),
				"Canceled" => $this->urlBuilder->getUrl('splititpaymentmethod/payment/cancel'),
			);
		}
		$params = array_merge($params, $paymentWizardData);
		//print_r($params);die;
		return $params;
	}

	public function getFirstInstallmentAmountHosted() {
		$firstPayment = $this->helper->getConfig('payment/splitit_paymentredirect/first_payment');
		$percentageOfOrder = $this->helper->getConfig('payment/splitit_paymentredirect/percentage_of_order');

		$firstInstallmentAmount = 0;
		if ($firstPayment == "shipping") {
			$firstInstallmentAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();
		} else if ($firstPayment == "shipping_taxes") {
			$shippingAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();
			$taxAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getData('tax_amount');
			$firstInstallmentAmount = $shippingAmount + $taxAmount;
		} else if ($firstPayment == "percentage") {
			if ($percentageOfOrder > 50) {
				$percentageOfOrder = 50;
			}
			$firstInstallmentAmount = (($this->_checkoutSession->getQuote()->getGrandTotal() * $percentageOfOrder) / 100);
		}

		return round($firstInstallmentAmount, 2);
	}

	public function getFirstInstallmentAmount($selectedInstallment) {
		$firstPayment = $this->helper->getConfig('payment/pis_cc/first_payment');
		$percentageOfOrder = $this->helper->getConfig('payment/pis_cc/percentage_of_order');
		$installmentsInDropdownArr = $this->customerSession->getInstallmentsInDropdown();

		$firstInstallmentAmount = 0;
		if ($firstPayment == "equal") {
			$firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment];
		} else if ($firstPayment == "shipping_taxes") {
			$shippingAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();
			$taxAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getData('tax_amount');
			$firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment] + $shippingAmount + $taxAmount;
		} else if ($firstPayment == "shipping") {
			$shippingAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getShippingAmount();
			$firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment] + $shippingAmount;
		} else if ($firstPayment == "tax") {
			$taxAmount = $this->_checkoutSession->getQuote()->getShippingAddress()->getData('tax_amount');
			$firstInstallmentAmount = $installmentsInDropdownArr[$selectedInstallment] + $taxAmount;
		} else if ($firstPayment == "percentage") {
			if ($percentageOfOrder > 50) {
				$percentageOfOrder = 50;
			}
			$firstInstallmentAmount = (($this->_checkoutSession->getQuote()->getGrandTotal() * $percentageOfOrder) / 100);
		}

		return round($firstInstallmentAmount, 2);
	}

	public function getInstallmentPlanDetails($api) {
		$params = array(
			"RequestHeader" => array(
				"SessionId" => $this->api->getorCreateSplititSessionid(),
			),
			"QueryCriteria" => array(
				"InstallmentPlanNumber" => $this->_checkoutSession->getSplititInstallmentPlanNumber(),
			),
		);
		$response = array("status" => false, "data" => "", "numberOfInstallments" => "", "cardBrand" => "", "cardNumber" => "", "cardExpMonth" => "", "cardExpYear" => "");
		$result = $api->getInstallmentPlanDetails($this->api->getApiUrl(), $params);
		$decodedResult = $this->jsonHelper->jsonDecode($result);

		if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
			$response["status"] = true;
			$response["numberOfInstallments"] = $decodedResult["PlansList"][0]["NumberOfInstallments"];
			$response["cardBrand"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardBrand"];
			$response["cardNumber"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardNumber"];
			$response["cardExpMonth"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardExpMonth"];
			$response["cardExpYear"] = $decodedResult["PlansList"][0]["ActiveCard"]["CardExpYear"];
			$response["grandTotal"] = $decodedResult["PlansList"][0]["OriginalAmount"]["Value"];
			$response["currencyCode"] = $decodedResult["PlansList"][0]["OriginalAmount"]["Currency"]["Code"];
			$response["planStatus"] = $decodedResult["PlansList"][0]["InstallmentPlanStatus"]["Code"];
		} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
			$errorMsg = "";
			$i = 1;
			foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < count($decodedResult["ResponseHeader"]["Errors"])) {
					$errorMsg .= ", ";
				}
				$i++;
			}

			$response["data"] = $errorMsg;
		}
		return $response;
	}

	public function cancelInstallmentPlan($api, $installmentPlanNumber) {
		$params = array(
			"RequestHeader" => array(
				"SessionId" => $this->api->getorCreateSplititSessionid(),
			),
			"InstallmentPlanNumber" => $installmentPlanNumber,
			"RefundUnderCancelation" => "OnlyIfAFullRefundIsPossible",
		);
		$response = array("status" => false, "data" => "");
		$result = $this->api->cancelInstallmentPlan($this->api->getApiUrl(), $params);
		$decodedResult = $this->jsonHelper->jsonDecode($result);

		if (isset($decodedResult["ResponseHeader"]["Succeeded"]) && $decodedResult["ResponseHeader"]["Succeeded"] == 1) {
			$response["status"] = true;
		} else if (isset($decodedResult["ResponseHeader"]) && count($decodedResult["ResponseHeader"]["Errors"])) {
			$errorMsg = "";
			$i = 1;
			foreach ($decodedResult["ResponseHeader"]["Errors"] as $key => $value) {
				$errorMsg .= "Code : " . $value["ErrorCode"] . " - " . $value["Message"];
				if ($i < count($decodedResult["ResponseHeader"]["Errors"])) {
					$errorMsg .= ", ";
				}
				$i++;
			}

			$response["data"] = $errorMsg;
		}
		return $response;
	}

	/**
	 * Determine method availability based on quote amount and config data
	 *
	 * @param \Magento\Quote\Api\Data\CartInterface|null $quote
	 * @return bool
	 */
	public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
		if (!$quote) {
			$quote = $this->quote;
		}

		if ($this->checkAvailableInstallments($quote) && $this->checkProductBasedAvailability()) {
			return parent::isAvailable($quote);
		} else {
			return false;
		}
	}

	public function checkAvailableInstallments($quote) {
		$installments = array();
		$installmentsInDropdown = array();
		$totalAmount = $quote->getGrandTotal();
		$selectInstallmentSetup = $this->helper->getConfig("payment/splitit_paymentredirect/select_installment_setup");

		$options = $this->objectManager->get('Splitit\Paymentmethod\Model\Source\Installments')->toOptionArray();

		$depandOnCart = 0;

		if ($selectInstallmentSetup == "" || $selectInstallmentSetup == "fixed") {
			// Select Fixed installment setup
			$fixedInstallments = $this->helper->getConfig("payment/splitit_paymentredirect/fixed_installment");
			$installments = explode(',', $fixedInstallments);
			foreach ($installments as $n) {
				if ((array_key_exists($n, $options))) {
					$installmentsInDropdown[$n] = round($totalAmount / $n, 2);
				}
			}
		} else {
			// Select Depanding on cart installment setup
			$depandOnCart = 1;
			$depandingOnCartInstallments = $this->helper->getConfig("payment/splitit_paymentredirect/depanding_on_cart_total_values");
			$depandingOnCartInstallmentsArr = json_decode($depandingOnCartInstallments);
			$dataAsPerCurrency = [];
			foreach ($depandingOnCartInstallmentsArr as $data) {
				$dataAsPerCurrency[$data->doctv->currency][] = $data->doctv;
			}
			$storeManager = $this->objectManager->get('\Magento\Store\Model\StoreManagerInterface');
			$currentCurrencyCode = $storeManager->getStore()->getCurrentCurrencyCode();
			if (count($dataAsPerCurrency) && isset($dataAsPerCurrency[$currentCurrencyCode])) {

				foreach ($dataAsPerCurrency[$currentCurrencyCode] as $data) {
					if ($totalAmount >= $data->from && !empty($data->to) && $totalAmount <= $data->to) {
						foreach (explode(',', $data->installments) as $n) {
							if ((array_key_exists($n, $options))) {
								$installments[$n] = $n;
								$installmentsInDropdown[$n] = round($totalAmount / $n, 2);
							}
						}
						break;
					} else if ($totalAmount >= $data->from && empty($data->to)) {
						foreach (explode(',', $data->installments) as $n) {

							if ((array_key_exists($n, $options))) {
								$installments[$n] = $n;
								$installmentsInDropdown[$n] = round($totalAmount / $n, 2);
							}
						}
						break;
					}
				}
			}
		}
		$this->_checkoutSession->setInstallmentsInDropdownForPaymentForm($installmentsInDropdown);
		if (count($installments) > 0) {
			return true;
		}

		return false;
	}

	public function checkProductBasedAvailability() {
		$check = TRUE;
		if ($this->helper->getConfig("payment/splitit_paymentredirect/splitit_per_product")) {
			$cart = $this->objectManager->get('\Magento\Checkout\Model\Cart');
// retrieve quote items collection
			//        $itemsCollection = $cart->getQuote()->getItemsCollection();
			// retrieve quote items array
			//        $items = $cart->getQuote()->getAllItems();
			// get array of all items what can be display directly
			$itemsVisible = $cart->getQuote()->getAllVisibleItems();
			$allowedProducts = $this->helper->getConfig("payment/splitit_paymentredirect/splitit_product_skus");
			$allowedProducts = explode(',', $allowedProducts);
			if ($this->helper->getConfig("payment/splitit_paymentredirect/splitit_per_product") == 1) {
				$check = TRUE;
				foreach ($itemsVisible as $item) {
					if (!in_array($item->getProductId(), $allowedProducts)) {
						$check = FALSE;
						break;
					}
				}
			}
			if ($this->helper->getConfig("payment/splitit_paymentredirect/splitit_per_product") == 2) {
				$check = FALSE;
				foreach ($itemsVisible as $item) {
					if (in_array($item->getProductId(), $allowedProducts)) {
						$check = TRUE;
						break;
					}
				}
			}
		}
//        var_dump($check);
		return $check;
	}

}
