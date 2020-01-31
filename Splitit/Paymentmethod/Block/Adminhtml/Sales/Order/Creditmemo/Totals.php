<?php

namespace Splitit\Paymentmethod\Block\Adminhtml\Sales\Order\Creditmemo;

class Totals extends \Magento\Framework\View\Element\Template {

    /**
     * Get data (totals) source model
     *
     * @return \Magento\Framework\DataObject
     */
    public function getSource() {
        return $this->getParentBlock()->getSource();
    }

    /**
     * Get credit memo block
     * @return \Magento\Framework\DataObject
     */
    public function getCreditmemo() {
        return $this->getParentBlock()->getCreditmemo();
    }

    /**
     * Initialize fee totals
     *
     * @return $this
     */
    public function initTotals() {
        $parent = $this->getParentBlock();
        $this->_order = $parent->getOrder();
        $this->_source = $parent->getSource();
        $orderItems = $this->_order->getAllItems();
        $r = $c = $o = 0;
        foreach ($orderItems as $oitem) {
            $o+=$oitem->getQtyOrdered();
            $r+=$oitem->getQtyRefunded();
        }
        $creditmemoItems = $this->_source->getAllItems();
        foreach ($creditmemoItems as $citems) {
            $c+=$citems->getQty();
        }
        $feeAmount = 0;
        if (($c == ($o - $r)) || $this->_source->getFeeAmount()) {
            $feeAmount = $this->_order->getFeeAmount();
        }
        $store = $this->getStore();
        $fee = new \Magento\Framework\DataObject(
                [
            'code' => 'fee',
            'strong' => false,
            'value' => $feeAmount,
            'base_value' => $feeAmount,
            'label' => __('Splitit Fee'),
                ]
        );
        $parent->addTotal($fee, 'fee');
        return $this;
    }

}
