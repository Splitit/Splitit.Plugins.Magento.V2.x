<?php

namespace Splitit\Paymentmethod\Model\Source;
use Magento\Framework\Option\ArrayInterface;

class Firstpaymentfp implements ArrayInterface {
	/**
	 * @return string[]
	 */
	public function toOptionArray() {
		return array(
			array('value' => 'equal', 'label' => __('Equal to Monthly Payment')),
			array('value' => 'shipping', 'label' => __('Only Shipping')),
			array('value' => 'shipping_taxes', 'label' => __('Only Shipping + Taxes')),
			array('value' => 'percentage', 'label' => __('Equal to percentage of the order [X]')),
		);
	}
}
