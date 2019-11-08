<?php

namespace Splitit\Paymentmethod\Block\Sales;

class Totals extends \Magento\Framework\View\Element\Template {
	/**
	 * @var \Magento\Sales\Model\Order
	 */
	protected $order;

	/**
	 * @var \Magento\Framework\DataObject
	 */
	protected $source;

	/**
	 * Check if we nedd display full tax total info
	 *
	 * @return bool
	 */
	public function displayFullSummary() {
		return true;
	}

	/**
	 * Get data (totals) source model
	 *
	 * @return \Magento\Framework\DataObject
	 */
	public function getSource() {
		return $this->source;
	}

	/**
	 * Get store object
	 * @return object
	 */
	public function getStore() {
		return $this->order->getStore();
	}

	/**
	 * @return \Magento\Sales\Model\Order
	 */
	public function getOrder() {
		return $this->order;
	}
	/**
	 * Initialize fee totals
	 *
	 * @return $this
	 */
	public function initTotals() {
		$parent = $this->getParentBlock();
		$this->order = $parent->getOrder();
		$this->source = $parent->getSource();
		if (!$this->source->getFeeAmount()) {
			return $this;
		}
		$fee = new \Magento\Framework\DataObject(
			[
				'code' => 'fee',
				'strong' => false,
				'value' => $this->source->getFeeAmount(),
				'label' => __('Splitit Fee'),
			]
		);

		$parent->addTotal($fee, 'fee');
		return $this;
	}
}