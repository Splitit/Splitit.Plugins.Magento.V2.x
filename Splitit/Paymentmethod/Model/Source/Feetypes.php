<?php

namespace Splitit\Paymentmethod\Model\Source;

class Feetypes {
	const PERCENTAGE = 1;
	const FIXED = 2;

	/**
	 * To get option for fix type
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array(
			array('value' => self::PERCENTAGE, 'label' => __('Percentage from Total Amount')),
			array('value' => self::FIXED, 'label' => __('FIXED')),
		);

	}
}