<?php

namespace Splitit\Paymentmethod\Controller\Payment;

class Cancel extends \Magento\Framework\App\Action\Action {

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /** 
     * @var \Magento\Sales\Api\Data\OrderInterface $order 
     */
    protected $order;
    protected $quoteFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->order = $order;
        $this->quoteFactory = $quoteFactory;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }
    
    /**
     * Cancel the order handle
     * @return void
     **/
    public function execute() {
        $session = $this->checkoutSession;
        $this->_redirect("checkout/cart")->sendResponse();
    }

}
