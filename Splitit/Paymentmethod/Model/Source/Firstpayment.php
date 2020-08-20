<?php

namespace Splitit\Paymentmethod\Model\Source;
use Magento\Framework\Option\ArrayInterface;

class Firstpayment implements ArrayInterface {
	/**
	 * @return string[]
	 */
	public function toOptionArray() {
		return array(
			array('value' => 'equal', 'label' => __('Equal to Monthly Payment')),
			array('value' => 'shipping_taxes', 'label' => __('Add Shipping & Taxes')),
			array('value' => 'shipping', 'label' => __('Add Shipping')),
			array('value' => 'tax', 'label' => __('Add Taxes')),
			array('value' => 'percentage', 'label' => __('Equal to percentage of the order [X]')),
		);
	}
}
