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
        /*$session->setQuoteId($session->getSplititQuoteId());
        
        if ($session->getLastRealOrderId()) {
            $order = $this->order->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            $this->checkoutSession->restoreQuote();
            $order = $session->getLastRealOrder();
            $quote = $this->quoteFactory->create()->load($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)
                    ->setReservedOrderId(null)
                    ->save();
                $session
                    ->replaceQuote($quote)
                    ->unsLastRealOrderId();
                
            }
        }*/    
        $this->_redirect("checkout/cart")->sendResponse();
    }

}
