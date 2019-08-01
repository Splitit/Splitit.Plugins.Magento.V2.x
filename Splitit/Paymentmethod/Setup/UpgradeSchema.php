<?php

namespace Splitit\Paymentmethod\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '0.2.0', '<')) {
            $quoteAddressTable = 'quote_address';
            $quoteTable = 'quote';
            $orderTable = 'sales_order';
            $invoiceTable = 'sales_invoice';
            $creditmemoTable = 'sales_creditmemo';

            //Setup two columns for quote, quote_address and order
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($quoteTable),
                    'fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount'
                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($quoteTable),
                    'base_fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount'
                    ]
                );
            //Quote address tables
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($quoteAddressTable),
                    'fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount'
                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($quoteAddressTable),
                    'base_fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount'
                    ]
                );
            //Order tables
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($orderTable),
                    'fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount'

                    ]
                );

            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($orderTable),
                    'base_fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount'

                    ]
                );

            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($orderTable),
                    'fee_amount_refunded',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount Refunded'
                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($orderTable),
                    'base_fee_amount_refunded',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount Refunded'
                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($orderTable),
                    'fee_amount_invoiced',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount Invoiced'
                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($orderTable),
                    'base_fee_amount_invoiced',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount Invoiced'
                    ]
                );
            //Invoice tables
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($invoiceTable),
                    'fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount'

                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($invoiceTable),
                    'base_fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount'

                    ]
                );
            //Credit memo tables
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($creditmemoTable),
                    'fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Splitit Fee Amount'

                    ]
                );
            $setup->getConnection()
                ->addColumn(
                    $setup->getTable($creditmemoTable),
                    'base_fee_amount',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                        'length' => '10,2',
                        'default' => 0.00,
                        'nullable' => true,
                        'comment' =>'Base Fee Amount'

                    ]
                );
        }
        $setup->endSetup();
    }
}