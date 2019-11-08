<?php

namespace Splitit\Paymentmethod\Observer;

// use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class AddFeeToOrderObserver implements ObserverInterface {
	/**
	 * @var \Magento\Checkout\Model\Session
	 */
	protected $checkoutSession;
	protected $logger;

	/**
	 * AddFeeToOrderObserver constructor.
	 * @param \Magento\Checkout\Model\Session $checkoutSession
	 */
	public function __construct(
		\Magento\Checkout\Model\Session $checkoutSession,
		\Psr\Log\LoggerInterface $logger
	) {
		$this->checkoutSession = $checkoutSession;
		$this->logger = $logger;
	}

	/**
	 * Set fee to order
	 *
	 * @param EventObserver $observer
	 * @return $this
	 */
	public function execute(\Magento\Framework\Event\Observer $observer) {
		$this->logger->debug('fee_amount=0 , base_fee_amount=0');

		$quote = $observer->getEvent()->getQuote();
		$feeAmount = $quote->getFeeAmount();

		$this->logger->debug($quote->getId());
		$this->logger->debug('feeAmount=' . $feeAmount);

		$baseFeeAmount = $quote->getBaseFeeAmount();
		if (!$feeAmount || !$baseFeeAmount) {
			return $this;
		}
		//Set fee data to order
		$order = $observer->getEvent()->getOrder();
		$order->setData('fee_amount', $feeAmount);
		$order->setData('base_fee_amount', $baseFeeAmount);

		$this->logger->debug('fee_amount=' . $feeAmount . ', base_fee_amount=' . $baseFeeAmount);

		return $this;
	}
}