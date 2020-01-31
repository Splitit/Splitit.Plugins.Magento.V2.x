<?php

namespace Splitit\Paymentmethod\Block\Adminhtml\Sales\Order\Invoice;

class Totals extends \Magento\Framework\View\Element\Template {

	protected $config;
	protected $order;
	protected $source;

	/**
	 * Contructor
	 * @param \Magento\Framework\View\Element\Template\Context $context
	 * @param \Magento\Tax\Model\Config $taxConfig
	 * @param array $data
	 */
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Tax\Model\Config $taxConfig,
		array $data = []
	) {
		$this->config = $taxConfig;
		parent::__construct($context, $data);
	}

	/**
	 * @return boolean
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
		return $this->getParentBlock()->getSource();
	}

	/**
	 * Return Invoice block
	 * @return mixed
	 */
	public function getInvoice() {
		return $this->getParentBlock()->getInvoice();
	}

	/**
	 * Get store object
	 * @return object
	 */
	public function getStore() {
		return $this->order->getStore();
	}

	/**
	 * Get order object
	 * @return object
	 */
	public function getOrder() {
		return $this->order;
	}

	/**
	 * Get total label properties
	 * @return mixed
	 */
	public function getLabelProperties() {
		return $this->getParentBlock()->getLabelProperties();
	}

	/**
	 * Get label value properties
	 * @return mixed
	 */
	public function getValueProperties() {
		return $this->getParentBlock()->getValueProperties();
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

		$store = $this->getStore();
		
		$fee = new \Magento\Framework\DataObject(
			[
				'code' => 'fee',
				'strong' => false,
				'value' => $this->order->getFeeAmount(),
				'base_value' => $this->order->getFeeAmount(),
				'label' => __('Splitit Fee'),
			]
		);
		$parent->addTotal($fee, 'fee');
		return $this;
	}

}
