<?php

namespace Splitit\Paymentmethod\Model\Source;


class Frontendpages
{
    public function toOptionArray()
    {
        return array(
            // array('value' => 'category', 'label' => __('Category pages')),
            array('value' => 'product', 'label' => __('Product pages')),
            array('value' => 'cart', 'label' => __('Shopping cart page')),
            array('value' => 'checkout', 'label' => __('Checkout page')),
        );

    }
}