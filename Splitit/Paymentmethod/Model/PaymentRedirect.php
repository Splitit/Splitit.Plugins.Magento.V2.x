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

class PaymentRedirect extends \Magento\Payment\Model\Method\AbstractMethod {

	const CODE = 'splitit_paymentredirect';

	protected $_code = self::CODE;
	protected $_isInitializeNeeded = true;
	protected $_canUseInternal = true;
	protected $_canUseForMultishipping = false;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canCaptureOnce = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = false;
	protected $_canUseCheckout = true;
	protected $_canCancel = false;
	protected $api;
	protected $helper;
	protected $checkoutSession;
	protected $customerSession;
	protected $quote;
	protected $store;
	protected $paymentForm;
	private $requestData = null;

	public function __construct(
		\Magento\Framework\Model\Context $context,
		\Magento\Framework\Registry $registry,
		\Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
		\Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
		\Magento\Payment\Helper\Data $paymentData,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Payment\Model\Method\Logger $logger,
		Api $api,
		PaymentForm $paymentForm,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Store\Api\Data\StoreInterface $store,
		\Magento\Framework\UrlInterface $urlBuilder,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Splitit\Paymentmethod\Helper\Data $helper,
		\Magento\Framework\App\RequestInterface $request,
		\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
		\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
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
			$resource,
			$resourceCollection,
			$data
		);
		$this->api = $api;
		$this->checkoutSession = $checkoutSession;
		$this->customerSession = $customerSession;
		$this->store = $store;
		$this->urlBuilder = $urlBuilder;
		$this->paymentForm = $paymentForm;
		$this->quote = $this->checkoutSession->getQuote();
		$this->helper = $helper;
		$this->requestData = $request->getParams();
	}

	/**
	 * Return checkout URL for payment gateway redirect
	 * @return string
	 */
	public function getCheckoutRedirectUrl() {
		$data = $this->paymentForm->orderPlaceRedirectUrl();
		return $data['checkoutUrl'];
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
		if ($this->paymentForm->checkAvailableInstallments($quote) && $this->paymentForm->checkProductBasedAvailability()) {
			return parent::isAvailable($quote);
		} else {
			return false;
		}
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
			$paymentAction = $this->helper->getRedirectPaymentAction();
			$params = array('InstallmentPlanNumber' => $authNumber);
			if ($paymentAction == "authorize_capture") {
				$api = $this->api->getApiUrl();
				$sessionId = $this->api->getorCreateSplititSessionid();
			} else {
				$api = $this->api->apiLogin();
				$sessionId = $this->api->getorCreateSplititSessionid();
			}
			$params = array_merge($params, array("RequestHeader" => array('SessionId' => $sessionId)));
			$this->_logger->error(print_r($params, true));
			$api = $this->api->getApiUrl();
			$result = $this->api->makePhpCurlRequest($api, "InstallmentPlan/StartInstallments", $params);
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
			$apiLogin = $this->api->apiLogin();
			$api = $this->api->getApiUrl();
			$installmentPlanNumber = $payment->getAuthorizationTransaction()->getTxnId();
			$ipn = substr($installmentPlanNumber, 0, strpos($installmentPlanNumber, '-'));
			if ($ipn != "") {
				$installmentPlanNumber = $ipn;
			}
			$params = array(
				"RequestHeader" => array(
					"SessionId" => $this->api->getorCreateSplititSessionid(),
				),
				"InstallmentPlanNumber" => $installmentPlanNumber,
				"RefundUnderCancelation" => "OnlyIfAFullRefundIsPossible",
			);

			$result = $this->api->makePhpCurlRequest($api, "InstallmentPlan/Cancel", $params);
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
			$apiLogin = $this->api->apiLogin();
			$api = $this->api->getApiUrl();
			$installmentPlanNumber = $payment->getAuthorizationTransaction()->getTxnId();
			$ipn = substr($installmentPlanNumber, 0, strpos($installmentPlanNumber, '-'));
			if ($ipn != "") {
				$installmentPlanNumber = $ipn;
			}
			$params = array(
				"RequestHeader" => array(
					"SessionId" => $this->api->getorCreateSplititSessionid(),
				),
				"InstallmentPlanNumber" => $installmentPlanNumber,
				"Amount" => array("Value" => $amount),
				"_RefundStrategy" => "FutureInstallmentsFirst",

			);

			$result = $this->api->makePhpCurlRequest($api, "InstallmentPlan/Refund", $params);
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
}
