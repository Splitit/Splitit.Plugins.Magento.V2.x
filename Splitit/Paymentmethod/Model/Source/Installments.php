<?php

namespace Splitit\Paymentmethod\Model\Source;

class Installments {

    public function toOptionArray() {
        $installments = array();
        for ($i = 1; $i <= 24; $i++) {
            array_push($installments, array('value' => "$i", 'label' => __("$i Installments")));
        }
        return $installments;
    }

}
