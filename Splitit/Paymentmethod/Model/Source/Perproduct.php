<?php

namespace Splitit\Paymentmethod\Model\Source;


class Perproduct
{
    public function toOptionArray()
    {
        return array(
            array('value' => '0', 'label' => __('Disabled')),
            array('value' => '1', 'label' => __('Enable Splitit only if the products in the list below are present in the cart')),
            array('value' => '2', 'label' => __('Enable Splitit if one or more of the below products are present in the cart'))
        );

    }
}