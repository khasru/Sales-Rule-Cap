<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Scriptlodge\SalesRuleCap\Model\Rule\Action\Discount;

class CalculatorFactory extends \Magento\SalesRule\Model\Rule\Action\Discount\CalculatorFactory
{
    /**
     * @var array
     */
    protected $classByType = [
        \Magento\SalesRule\Model\Rule::TO_PERCENT_ACTION =>
            \Magento\SalesRule\Model\Rule\Action\Discount\ToPercent::class,
        \Magento\SalesRule\Model\Rule::BY_PERCENT_ACTION =>
            \Magento\SalesRule\Model\Rule\Action\Discount\ByPercent::class,
        \Scriptlodge\SalesRuleCap\Helper\RuleData::BY_PERCENT_MAX_ACTION =>
            \Scriptlodge\SalesRuleCap\Model\Rule\Action\Discount\ByMaxPercent::class,
        \Magento\SalesRule\Model\Rule::TO_FIXED_ACTION => \Magento\SalesRule\Model\Rule\Action\Discount\ToFixed::class,
        \Magento\SalesRule\Model\Rule::BY_FIXED_ACTION => \Magento\SalesRule\Model\Rule\Action\Discount\ByFixed::class,
        \Magento\SalesRule\Model\Rule::CART_FIXED_ACTION =>
            \Magento\SalesRule\Model\Rule\Action\Discount\CartFixed::class,
        \Magento\SalesRule\Model\Rule::BUY_X_GET_Y_ACTION =>
            \Magento\SalesRule\Model\Rule\Action\Discount\BuyXGetY::class,
    ];

}
