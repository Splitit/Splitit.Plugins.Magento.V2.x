<?php

namespace Splitit\Paymentmethod\Model\Source;

class Frontendpages {
	/**
	 * Get option to show splitit label
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array(
			array('value' => 'product', 'label' => __('Product pages')),
			array('value' => 'cart', 'label' => __('Shopping cart page')),
			array('value' => 'checkout', 'label' => __('Checkout page')),
		);

	}
}