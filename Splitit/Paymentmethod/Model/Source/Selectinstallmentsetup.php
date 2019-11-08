<?php

namespace Splitit\Paymentmethod\Model\Source;

class Selectinstallmentsetup {
	/**
	 * Get options for installments
	 *
	 * @return array
	 */
	public function toOptionArray() {

		return array(
			array('value' => 'fixed', 'label' => __('Fixed')),
			array('value' => 'depending_on_cart_total', 'label' => __('Depending on cart total')),
		);
	}
}