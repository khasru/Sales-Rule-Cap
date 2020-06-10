<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Scriptlodge\SalesRuleCap\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

/**
 * Upgrade the SalesRule module DB scheme
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '2.2.3', '<')) {
            $this->addColumnMaxDiscountAmount($setup);
        }

        $setup->endSetup();
    }

    /**
     * Add New column for the max discount amount (Upto/cap)
     * @param SchemaSetupInterface $setup
     * @return void
     */
    private function addColumnMaxDiscountAmount(SchemaSetupInterface $setup)
    {
        $connection = $setup->getConnection();
        $connection->addColumn(
            $setup->getTable('salesrule'),
            'max_discount_amount',
            [
                'type' => Table::TYPE_DECIMAL,
                [12, 4],
                'unsigned' => true,
                'nullable' => true,
                'default' => '0.0000',
                'comment' => 'Max discount amount',
            ]
        );
    }
}