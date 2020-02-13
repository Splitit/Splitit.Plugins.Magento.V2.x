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
		return json_encode(array('errors'=>false,'message' => 'Re-calculate successful.'));
	}

}
