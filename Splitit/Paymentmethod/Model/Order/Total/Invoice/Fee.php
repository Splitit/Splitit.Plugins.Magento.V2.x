<?php

namespace Splitit\Paymentmethod\Model\Order\Total\Invoice;

use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Fee extends AbstractTotal {
/**
 * @param \Magento\Sales\Model\Order\Invoice $invoice
 * @return $this
 */
	public function collect(\Magento\Sales\Model\Order\Invoice $invoice) {
		$order = $invoice->getOrder();
		$invoice->setFeeAmount(0);
		$invoice->setBaseFeeAmount(0);
		$order->setFeeAmountInvoiced(0);
		$order->setBaseFeeAmountInvoiced(0);
		$amount = $order->getFeeAmount();
		$invoice->setFeeAmount($amount);
		$order->setFeeAmountInvoiced($amount);
		$amount = $invoice->getOrder()->getBaseFeeAmount();
		$invoice->setBaseFeeAmount($amount);
		$order->setBaseFeeAmountInvoiced($amount);
		$invoice->setGrandTotal($invoice->getGrandTotal() + $invoice->getFeeAmount());
		$invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $invoice->getFeeAmount());

		return $this;
	}
}