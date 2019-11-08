<?php

namespace Splitit\Paymentmethod\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;

class Totals extends \Magento\Framework\App\Action\Action {

	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $checkoutSession;

	/**
	 * @var \Magento\Framework\Controller\Result\JsonFactory
	 */
	protected $resultJson;

	/**
	 * @var \Magento\Framework\Json\Helper\Data
	 */
	protected $helper;
	/**
	 * @var \Splitit\Paymentmethod\Helper\Data
	 */
	protected $helperData;

	/**
	 * @var \Magento\Quote\Api\CartRepositoryInterface
	 */
	protected $quoteRepository;

	/**
	 * @param Context $context
	 * @param Session $checkoutSession
	 * @param \Magento\Framework\Json\Helper\Data $helper
	 * @param \Magento\Framework\Controller\Result\JsonFactory $resultJson
	 * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
	 * @param \Splitit\Paymentmethod\Helper\Data $helperData
	 */
	public function _construct(
		Context $context,
		\Magento\Checkout\Model\Session $checkoutSession,
		\Magento\Framework\Json\Helper\Data $helper,
		\Magento\Framework\Controller\Result\JsonFactory $resultJson,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		\Splitit\Paymentmethod\Helper\Data $helperData
	) {
		parent::__construct($context);
		$this->checkoutSession = $checkoutSession;
		$this->helper = $helper;
		$this->helperData = $helperData;
		$this->resultJson = $resultJson;
		$this->quoteRepository = $quoteRepository;
	}

	/**
	 * Trigger to re-calculate the collect Totals
	 * @return JSON
	 */
	public function execute() {
		$response = [
			'errors' => false,
			'message' => 'Re-calculate successful.',
		];
		try {
			$this->quoteRepository->get($this->checkoutSession->getQuoteId());
			$quote = $this->checkoutSession->getQuote();
			/* Trigger to re-calculate totals */
			$payment = $this->helper->jsonDecode($this->getRequest()->getContent());
			if ($payment['pageReloaded']) {
				$this->checkoutSession->setSelectedIns(false);
			}
			$this->checkoutSession->getQuote()->getPayment()->setMethod($payment['payment']);

			if (version_compare($this->helperData->getMagentoVersion(), '2.3.0', '<')) {
				$this->quoteRepository->save($quote->collectTotals());
			} else {
				$this->checkoutSession->getQuote()->collectTotals();
				$this->quoteRepository->save($quote);
			}
		} catch (\Exception $e) {
			$response = [
				'errors' => true,
				'message' => $e->getMessage(),
			];
		}

		/**
		 * @var \Magento\Framework\Controller\Result\Raw $resultRaw
		 */
		$resultJson = $this->resultJson->create();
		return $resultJson->setData($response);
	}

}
