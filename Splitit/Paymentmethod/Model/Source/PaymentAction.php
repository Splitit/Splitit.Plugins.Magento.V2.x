<?php
 
namespace Splitit\Paymentmethod\Model\Source;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Option\ArrayInterface; 
/**
 * Pay In Store payment method model
 */
class PaymentAction implements ArrayInterface 
{
 
    public function toOptionArray()
    {
        return array(
            array(
                'value' => AbstractMethod::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Charge my consumer at the time of purchase')
            ),
            array(
                'value' => AbstractMethod::ACTION_AUTHORIZE,
                'label' => __('Charge my consumer when the shipment is ready')
            ),

        );
    }
}