<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Splitit\Paymentmethod\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Class ExpressConfigProvider
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ExpressConfigProvider implements ConfigProviderInterface
{
    const IN_CONTEXT_BUTTON_ID = 'splitit-express-in-context-button';

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var string[]
     */
    protected $methodCodes = [
        PaymentRedirect::CODE
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    protected $helpBlock;

    /**
     * Constructor
     *
     * @param ConfigFactory $configFactory
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param PaymentHelper $paymentHelper
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        PaymentHelper $paymentHelper,
        ScopeConfigInterface $scopeConfig,
        \Splitit\Paymentmethod\Block\Help $helpBlock,
        UrlInterface $urlBuilder
    ) {
        $this->localeResolver = $localeResolver;
        $this->currentCustomer = $currentCustomer;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->helpBlock = $helpBlock;

        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'splititExpress' => [
                    'paymentAcceptanceMarkHref' => $this->scopeConfig->getValue('payment/splitit_paymentmethod/faq_link_title_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'paymentAcceptanceMarkSrc' => $this->scopeConfig->getValue('payment/splitit_paymentmethod/splitit_logo_src', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'questionMark' => $this->helpBlock->getViewFileUrl('Splitit_Paymentmethod::images/learn_more.svg'),
                    'isContextCheckout' => false,
                    'inContextConfig' => []
                ]
            ]
        ];

//        $isInContext = $this->isInContextCheckout();
//        if ($isInContext) {
//            $config['payment']['splititExpress']['isContextCheckout'] = $isInContext;
//            $config['payment']['splititExpress']['inContextConfig'] = [
//                'inContextId' => self::IN_CONTEXT_BUTTON_ID,
//                'merchantId' => 123456789,
//                'path' => $this->urlBuilder->getUrl('splitit/express/gettoken', ['_secure' => true]),
//                'clientConfig' => [
//                    'environment' => 'sandbox',
//                    'locale' => $locale,
//                    'button' => [
//                        self::IN_CONTEXT_BUTTON_ID
//                    ]
//                ],
//            ];
//        }

//        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
//        $api = $objectManager->get('Splitit\Paymentmethod\Model\Api');
//        $response=$api->apiLogin();
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                if($code==PaymentRedirect::CODE){
                    $config['payment']['splititExpress']['paymentAcceptanceMarkHref'] = $this->scopeConfig->getValue('payment/splitit_paymentredirect/faq_link_title_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                    $config['payment']['splititExpress']['paymentAcceptanceMarkSrc'] = $this->scopeConfig->getValue('payment/splitit_paymentredirect/splitit_logo_src', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                }
//                $config['payment']['splititExpress']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
                $config['payment']['splititExpress']['redirectUrl'][$code] = $this->urlBuilder->getUrl("splititpaymentmethod/payment/redirect");
                $config['payment']['splititExpress']['billingAgreementCode'][$code] = "";
            }
        }
        return $config;
    }
    
    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }
}
