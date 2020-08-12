<?php

namespace Splitit\Paymentmethod\Controller\Payment;

class Successasync extends \Magento\Framework\App\Action\Action {

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
	protected $quoteRepository;
	protected $paymentForm;
	protected $api;
	protected $logger;
	protected $orderSender;
	protected $orderPlace;

	/**
     * Contructor
     * @param \Magento\Framework\App\Action\Context $context
	 * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	 * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
	 * @param \Magento\Sales\Api\Data\OrderInterface $order
	 * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
	 * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
	 * @param \Splitit\Paymentmethod\Model\Helper\OrderPlace $orderPlace
	 * @param \Psr\Log\LoggerInterface $logger
	 * @param \Splitit\Paymentmethod\Helper\Data $helperData
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
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		\Splitit\Paymentmethod\Model\Helper\OrderPlace $orderPlace,
		\Psr\Log\LoggerInterface $logger,
		\Splitit\Paymentmethod\Helper\Data $helperData,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Splitit\Paymentmethod\Model\PaymentForm $paymentForm,
		\Splitit\Paymentmethod\Model\Api $api,
		\Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
	) {
		$this->scopeConfig = $scopeConfig;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helperData = $helperData;
		$this->order = $order;
		$this->quoteFactory = $quoteFactory;
		$this->orderPlace = $orderPlace;
		$this->quoteRepository = $quoteRepository;
		$this->logger = $logger;
		$this->checkoutSession = $checkoutSession;
		$this->paymentForm = $paymentForm;
		$this->api = $api;
		$this->orderSender = $orderSender;
		parent::__construct($context);
	}

	/**
	 * Async Success for the order handle
	 * @return void
	 **/
	public function execute() {
		$params = $this->getRequest()->getParams();

		$this->checkoutSession->setSplititInstallmentPlanNumber($params['InstallmentPlanNumber']);
		$this->logger->addDebug("FILE: ".__FILE__."\n LINE: ". __LINE__."\n Method: ". __METHOD__);
		$this->logger->addDebug('======= successAsyncAction :  =======InstallmentPlanNumber coming from splitit in url: ' . $params["InstallmentPlanNumber"]);
		$this->logger->addDebug('======= quote Id from Splitit :  ======= '.$params['RefOrderNumber']);
		$quote = $this->quoteRepository->get($params['RefOrderNumber']);
		$api = $this->paymentForm->_initApi();
		$planDetails = $this->paymentForm->getInstallmentPlanDetails($this->api);

		$this->logger->addDebug('======= get installmentplan details :  ======= ');
		$this->logger->addDebug(print_r($planDetails, TRUE));

		$orderId = 0;
		$orderIncrementId = 0;

		$grandTotal = number_format((float) $quote->getGrandTotal(), 2, '.', '');
		$planDetails["grandTotal"] = number_format((float) $planDetails["grandTotal"], 2, '.', '');
		$this->logger->addDebug('======= grandTotal(orderObj):' . $grandTotal . ', grandTotal(planDetails):' . $planDetails["grandTotal"] . '   ======= ');

		if ($grandTotal == $planDetails["grandTotal"] && ($planDetails["planStatus"] == "PendingMerchantShipmentNotice" || $planDetails["planStatus"] == "InProgress")) {
			$orderId = $this->orderPlace->execute($quote, array());
			$this->logger->addDebug('======= order Id :  ======= '.$orderId);

			$orderObj = $this->order->load($orderId);
			$orderIncrementId = $orderObj->getIncrementId();

			$payment = $orderObj->getPayment();
			$paymentAction = $this->helperData->getRedirectPaymentAction();

			$payment->setTransactionId($this->checkoutSession->getSplititInstallmentPlanNumber());
			$payment->setParentTransactionId($this->checkoutSession->getSplititInstallmentPlanNumber());
			$payment->setInstallmentsNo($planDetails["numberOfInstallments"]);
			$payment->setIsTransactionClosed(0);
			$payment->setCurrencyCode($planDetails["currencyCode"]);
			$payment->setCcType($planDetails["cardBrand"]["Code"]);
			$payment->setIsTransactionApproved(true);

			$payment->registerAuthorizationNotification($grandTotal);
			$this->logger->addDebug('======= add order status to history  ======= ');
			$orderObj->addStatusToHistory(
				$orderObj->getStatus(), 'Payment InstallmentPlan was created with number ID: '
				. $this->checkoutSession->getSplititInstallmentPlanNumber(), false
			);
			if ($paymentAction == "authorize_capture") {
				$this->logger->addDebug('======= order authorize_capture  ======= ');
				$payment->setShouldCloseParentTransaction(true);
				$payment->setIsTransactionClosed(1);
				$payment->registerCaptureNotification($grandTotal);
				$orderObj->addStatusToHistory(
					false, 'Payment NotifyOrderShipped was sent with number ID: ' . $this->checkoutSession->getSplititInstallmentPlanNumber(), false
				);
			}
			$this->logger->addDebug('======= order send email  ======= ');
			$this->orderSender->send($orderObj);
			$orderObj->save();
			$curlRes = $this->paymentForm->updateRefOrderNumber($this->api, $orderObj);

			$this->logger->addDebug('====== Order Id =====:' . $orderId . '==== Order Increment Id ======:' . $orderIncrementId);
		} else {
			$this->logger->addDebug('====== Order Grand total and Payment detail total coming from Api is not same. =====');
			$this->logger->addDebug('Grand Total : ' . $grandTotal);
			$this->logger->addDebug('Plan Details Total : ' . $planDetails["grandTotal"]);
			$cancelResponse = $this->paymentForm->cancelInstallmentPlan($this->api, $params["InstallmentPlanNumber"]);
		}
	}

}
