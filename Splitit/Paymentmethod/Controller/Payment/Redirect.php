<?php

namespace Splitit\Paymentmethod\Controller\Payment;

class Redirect extends \Magento\Framework\App\Action\Action {

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
	protected $_helperData;

	/**
	 * @var \Magento\Sales\Api\Data\OrderInterface $order
	 */
	protected $order;
	protected $quoteFactory;
	protected $paymentForm;
	protected $api;
	protected $logger;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Sales\Api\Data\OrderInterface $order,
		\Magento\Quote\Model\QuoteFactory $quoteFactory,
		\Splitit\Paymentmethod\Helper\Data $helperData,
		\Psr\Log\LoggerInterface $logger
	) {
		$this->scopeConfig = $scopeConfig;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->_helperData = $helperData;
		$this->order = $order;
		$this->quoteFactory = $quoteFactory;
		$this->logger = $logger;
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$this->checkoutSession = $objectManager->get('\Magento\Checkout\Model\Session');
		$this->paymentForm = $objectManager->get('\Splitit\Paymentmethod\Model\PaymentForm');
		$this->api = $objectManager->get('\Splitit\Paymentmethod\Model\Api');
		parent::__construct($context);
	}

	public function execute() {

//        die("redirect controller");
		$data = $this->paymentForm->orderPlaceRedirectUrl();
		// echo '<pre>'; print_r($data); die;
		if ($data['error'] == true && $data["status"] == false) {
			$this->logger->addError("Split It processing error : " . $data["data"]);
			if (isset($data["errorMsg"]) && $data["errorMsg"]) {
				$this->messageManager->addErrorMessage($data["errorMsg"]);
				$this->checkoutSession->setErrorMessage($data["errorMsg"]);
			} else {
				$this->messageManager->addErrorMessage('Error in processing your order. Please try again later.');
				$this->checkoutSession->setErrorMessage('Error in processing your order. Please try again later.');
			}
			$resultRedirect = $this->resultRedirectFactory->create();
			$resultRedirect->setPath('checkout/cart');
			return $resultRedirect;

			//	$this->_redirect('checkout/cart')->sendResponse();
			// exit;
		}
		$order = $this->checkoutSession->getLastRealOrder();
		$orderId = $order->getEntityId();
		$payment = $order->getPayment();
//        var_dump($this->checkoutSession->getData());exit;
		$payment->setTransactionId($this->checkoutSession->getSplititInstallmentPlanNumber());
		$payment->save();
		$order->save();
		$curlRes = $this->paymentForm->updateRefOrderNumber($this->api, $order);
//        echo $orderId;
		//        print_r($curlRes);exit;
		if (isset($curlRes["status"]) && $curlRes["status"]) {
			$this->_redirect($data['checkoutUrl']);
		}
	}

}
