<?php

namespace Splitit\Paymentmethod\Controller\Payment;

class Success extends \Magento\Framework\App\Action\Action {

	/**
	 * @var \Magento\Framework\App\Config\ScopeConfigInterface
	 */
	protected $scopeConfig;
	/**
	 * @var \Magento\Framework\Controller\Result\JsonFactory
	 */
	protected $resultJsonFactory;
	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $checkoutSession;
	/**
	 * @var \Splitit\Paymentmethod\Helper\Data
	 */
	protected $helperData;
	/**
	 * @var \Magento\Sales\Api\Data\OrderInterface $order
	 */
	protected $order;
	protected $quoteFactory;
	protected $paymentForm;
	protected $api;
	protected $logger;
	protected $orderSender;

	/**
     * Contructor
     * @param \Magento\Framework\App\Action\Context $context
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
	 * @param \Magento\Sales\Api\Data\OrderInterface $order
	 * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param \Splitit\Paymentmethod\Helper\Data $helperData
	 * @param \Splitit\Paymentmethod\Model\Helper\OrderPlace $orderPlace
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 * @param \Splitit\Paymentmethod\Model\PaymentForm $paymentForm
	 * @param \Splitit\Paymentmethod\Model\Api $api
	 * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     */
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Sales\Api\Data\OrderInterface $order,
		\Magento\Quote\Model\QuoteFactory $quoteFactory,
		\Psr\Log\LoggerInterface $logger,
		\Splitit\Paymentmethod\Helper\Data $helperData,
		\Splitit\Paymentmethod\Model\Helper\OrderPlace $orderPlace,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Splitit\Paymentmethod\Model\PaymentForm $paymentForm,
		\Splitit\Paymentmethod\Model\Api $api,
		\Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
	) {
		$this->scopeConfig = $scopeConfig;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helperData = $helperData;
		$this->order = $order;
		$this->orderPlace = $orderPlace;
		$this->quoteFactory = $quoteFactory;
		$this->logger = $logger;
		$this->checkoutSession = $checkoutSession;
		$this->paymentForm = $paymentForm;
		$this->api = $api;
		$this->orderSender = $orderSender;
		parent::__construct($context);
	}

	/**
	 * Success the order handle
	 * @return void
	 **/
	public function execute() {
		$quote = $this->checkoutSession->getQuote();
		$params = $this->getRequest()->getParams();
		if (!$this->checkoutSession->getSplititInstallmentPlanNumber()) {
			$this->checkoutSession->setSplititInstallmentPlanNumber($params['InstallmentPlanNumber']);
		}
		$planDetails = $this->paymentForm->getInstallmentPlanDetails($this->api);

		$this->logger->addDebug("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
		$this->logger->addDebug('======= get installmentplan details :  ======= ');
		$this->logger->addDebug(print_r($planDetails, TRUE));

		$grandTotal = number_format((float) $quote->getGrandTotal(), 2, '.', '');
		$planDetails["grandTotal"] = number_format((float) $planDetails["grandTotal"], 2, '.', '');
		$this->logger->addDebug("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
		$this->logger->addDebug('======= grandTotal(quote):' . $grandTotal . ', grandTotal(planDetails):' . $planDetails["grandTotal"] . '   ======= ');

        $verifyResult = $this->api->verifyPayment($this->checkoutSession->getSplititInstallmentPlanNumber());
        $this->logger->addDebug('======= verify details :  ======= ');
        $this->logger->addDebug(print_r($verifyResult, TRUE));

		if ($verifyResult['isPaid'] && $verifyResult['OriginalAmountPaid'] == $grandTotal && $grandTotal == $planDetails["grandTotal"] && ($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "InProgress")) {
			$this->orderPlace->execute($quote, array());
			$order = $this->checkoutSession->getLastRealOrder();

			$orderId = $order->getEntityId();
			$orderIncrementId = $order->getIncrementId();
			
			$payment = $order->getPayment();
			$paymentAction = $this->helperData->getRedirectPaymentAction();

			$payment->setTransactionId($this->checkoutSession->getSplititInstallmentPlanNumber());
			$payment->setParentTransactionId($this->checkoutSession->getSplititInstallmentPlanNumber());
			$payment->setInstallmentsNo($planDetails["numberOfInstallments"]);
			$payment->setIsTransactionClosed(0);
			$payment->setCurrencyCode($planDetails["currencyCode"]);
			$payment->setCcType($planDetails["cardBrand"]["Code"]);
			$payment->setIsTransactionApproved(true);

			$payment->registerAuthorizationNotification($grandTotal);
			$order->addStatusToHistory(
				$order->getStatus(), 'Payment InstallmentPlan was created with number ID: '
				. $this->checkoutSession->getSplititInstallmentPlanNumber(), false
			);
			if ($paymentAction == "authorize_capture") {
				$payment->setShouldCloseParentTransaction(true);
				$payment->setIsTransactionClosed(1);
				$payment->registerCaptureNotification($grandTotal);
				$order->addStatusToHistory(
					false, 'Payment NotifyOrderShipped was sent with number ID: ' . $this->checkoutSession->getSplititInstallmentPlanNumber(), false
				);
			}
			$this->orderSender->send($order);
			$order->save();
			$this->paymentForm->updateRefOrderNumber($this->api, $order);

			$this->logger->addDebug("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
			$this->logger->addDebug('====== Order Id =====:' . $orderId . '==== Order Increment Id ======:' . $orderIncrementId);

			$this->_redirect("checkout/onepage/success")->sendResponse();
		} else {

			$this->logger->addDebug("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
			$this->logger->addDebug('====== Order cancel due to Grand total and Payment detail total coming from Api is not same. =====');
			$cancelResponse = $this->paymentForm->cancelInstallmentPlan($this->api, $params["InstallmentPlanNumber"]);
			if ($cancelResponse["status"]) {
				$this->_redirect("checkout/cart")->sendResponse();
			}
		}
	}

}
