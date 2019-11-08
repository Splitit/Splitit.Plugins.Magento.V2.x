<?php

namespace Splitit\Paymentmethod\Model\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Pay In Store payment method model
 */
class PaymentAction implements ArrayInterface {

	/**
	 * Get options for payment capture
	 *
	 * @return array
	 */
	public function toOptionArray() {
		return array(
			array(
				'value' => AbstractMethod::ACTION_AUTHORIZE_CAPTURE,
				'label' => __('Charge my consumer at the time of purchase'),
			),
			array(
				'value' => AbstractMethod::ACTION_AUTHORIZE,
				'label' => __('Charge my consumer when the shipment is ready'),
			),

		);
	}
}