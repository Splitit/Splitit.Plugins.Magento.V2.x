<?php

namespace Splitit\Paymentmethod\Observer;

use Magento\Framework\Event\ObserverInterface;

class PaymentCancel implements ObserverInterface {

	protected $paymentModel;
	protected $apiModel;
	protected $customerSession;
	protected $logger;
	protected $jsonHelper;
	/**
	 * PaymentCancel constructor.
	 * @param \Splitit\Paymentmethod\Model\Payment $paymentModel
	 * @param \Magento\Customer\Model\Session $customerSession
	 * @param \Splitit\Paymentmethod\Model\Api $apiModel
	 * @param \Magento\Framework\Json\Helper\Data $jsonHelper
	 * @param \Psr\Log\LoggerInterface $logger
	 */
	public function __construct(
		\Splitit\Paymentmethod\Model\Payment $paymentModel,
		\Magento\Customer\Model\Session $customerSession,
		\Splitit\Paymentmethod\Model\Api $apiModel,
		\Magento\Framework\Json\Helper\Data $jsonHelper,
		\Psr\Log\LoggerInterface $logger
	) {
		$this->logger = $logger;
		$this->customerSession = $customerSession;
		$this->paymentModel = $paymentModel;
		$this->apiModel = $apiModel;
		$this->jsonHelper = $jsonHelper;
	}

	/**
	 * Cancel order on Splitit
	 *
	 * @param EventObserver $observer
	 * @return $this
	 */
	public function execute(\Magento\Framework\Event\Observer $observer) {
		$this->logger->debug("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
		$this->logger->error("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
		$order = $observer->getEvent()->getOrder();
		$payment = $order->getPayment();
		$this->logger->debug(get_class($payment));
		$this->logger->debug('$payment->getMethod()===');
		$this->logger->debug($payment->getMethod());
		$this->logger->debug('$payment->getCode()===');
		$this->logger->debug($payment->getCode());
		$transactionId = $payment->getParentTransactionId();
		$this->logger->debug('transactionId=' . $transactionId);
		try {
			$dataForLogin = array(
				'UserName' => $this->helper->getApiUsername($payment->getMethod()),
				'Password' => $this->helper->getApiPassword($payment->getMethod()),
				'TouchPoint' => $this->helper->getApiTouchPointVersion(),
			);
			$apiLogin = $this->apiModel->apiLogin($dataForLogin);
			$api = $this->apiModel->getApiUrl();
			if ($payment->getAuthorizationTransaction()) {
				$installmentPlanNumber = $payment->getAuthorizationTransaction()->getTxnId();
				$this->logger->debug('IPN=' . $installmentPlanNumber);
				$ipn = substr($installmentPlanNumber, 0, strpos($installmentPlanNumber, '-'));
				if ($ipn != "") {
					$installmentPlanNumber = $ipn;
				}
				$params = array(
					"RequestHeader" => array(
						"SessionId" => $this->customerSession->getSplititSessionid(),
					),
					"InstallmentPlanNumber" => $installmentPlanNumber,
					"RefundUnderCancelation" => "OnlyIfAFullRefundIsPossible",
				);

				$result = $this->apiModel->makePhpCurlRequest($api, "InstallmentPlan/Cancel", $params);
				$result = $this->jsonHelper->jsonDecode($result);
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
						$this->logger->error(__($errorMsg));
						throw new \Magento\Framework\Validator\Exception(__($errorMsg));
					}

				} elseif (isset($result["serverError"])) {
					$errorMsg = $result["serverError"];
					$this->logger->error(__($errorMsg));
					throw new \Magento\Framework\Validator\Exception(__($errorMsg));
				}
			}
		} catch (\Exception $e) {
			$this->logger->debug(['transaction_id' => $transactionId, 'exception' => $e->getMessage()]);
			$this->logger->error(__('Payment cancel error.'));
		}
		return $this;
	}
}