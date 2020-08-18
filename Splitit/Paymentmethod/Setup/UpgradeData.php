<?php

namespace Splitit\Paymentmethod\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Config\Model\ResourceModel\Config;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var Config
     */
    private $configResource;

    public function __construct(
        Config $configResource
    ) {
        $this->configResource = $configResource;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '0.2.1', '<')) {
            $logoUrl = 'https://splitit-logos-prod-new.s3.amazonaws.com/Official_Splitit_Logo_V2.png';
            $helpUrl = 'https://s3.amazonaws.com/splitit-images-prod-new/learnmore/en-us/V1-USD.png';
            $this->configResource->saveConfig('payment/splitit_paymentmethod/splitit_logo_src', $logoUrl);
            $this->configResource->saveConfig('payment/splitit_paymentmethod/faq_link_title_url', $helpUrl);
            $this->configResource->saveConfig('payment/splitit_paymentmethod/splitit_logo__bakcground_href', $helpUrl);
            $this->configResource->saveConfig('payment/splitit_paymentredirect/splitit_logo_src', $logoUrl);
            $this->configResource->saveConfig('payment/splitit_paymentredirect/faq_link_title_url', $helpUrl);
            $this->configResource->saveConfig('payment/splitit_paymentredirect/splitit_logo__bakcground_href', $helpUrl);
        }

        $setup->endSetup();
    }
}
