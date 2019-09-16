<?php

namespace Splitit\Paymentmethod\Model\Source;

//use Magento\Payment\Model\Method\AbstractMethod;
//use Magento\Framework\Option\ArrayInterface; 

class Selectinstallmentsetup// implements ArrayInterface
{
	public function toOptionArray() {
      
        return array(
			array('value' => 'fixed', 'label' => __('Set Number of Installments')),
            array('value' => 'depending_on_cart_total', 'label' => __('Depending on cart total')),
        );
    }
}