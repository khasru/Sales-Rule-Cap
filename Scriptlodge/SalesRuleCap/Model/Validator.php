<?php
/**
 * Created by PhpStorm.
 * User: khasru
 * Date: 2/16/19
 * Time: 12:04 PM
 */

namespace Scriptlodge\SalesRuleCap\Model;

use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Scriptlodge\SalesRuleCap\Helper\RuleData;

/**
 * SalesRule Validator Model
 *
 * Allows dispatching before and after events for each controller action
 *
 * @method mixed getCouponCode()
 * @method Validator setCouponCode($code)
 * @method mixed getWebsiteId()
 * @method Validator setWebsiteId($id)
 * @method mixed getCustomerGroupId()
 * @method Validator setCustomerGroupId($id)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 */ ?>
<?php

class Validator extends \Magento\SalesRule\Model\Validator
{

    /**
     * Calculate quote totals for each rule and save results
     *
     * @param mixed $items
     * @param Address $address
     * @return $this
     */
    public function initTotals($items, Address $address)
    {
        $address->setCartFixedRules([]);

        if (!$items) {
            return $this;
        }

        /** @var \Magento\SalesRule\Model\Rule $rule */
        foreach ($this->_getRules($address) as $rule) {

            if ((\Magento\SalesRule\Model\Rule::CART_FIXED_ACTION == $rule->getSimpleAction() || RuleData::BY_PERCENT_MAX_ACTION == $rule->getSimpleAction())
                && $this->validatorUtility->canProcessRule($rule, $address)
            ) {
                $ruleTotalItemsPrice = 0;
                $ruleTotalBaseItemsPrice = 0;
                $validItemsCount = 0;

                foreach ($items as $item) {
                    //Skipping child items to avoid double calculations
                    if ($item->getParentItemId()) {
                        continue;
                    }
                    if (!$rule->getActions()->validate($item)) {
                        continue;
                    }
                    if (!$this->canApplyDiscount($item)) {
                        continue;
                    }
                    $qty = $this->validatorUtility->getItemQty($item, $rule);
                    $ruleTotalItemsPrice += $this->getItemPrice($item) * $qty;
                    $ruleTotalBaseItemsPrice += $this->getItemBasePrice($item) * $qty;
                    $validItemsCount++;
                }

                $this->_rulesItemTotals[$rule->getId()] = [
                    'items_price' => $ruleTotalItemsPrice,
                    'base_items_price' => $ruleTotalBaseItemsPrice,
                    'items_count' => $validItemsCount,
                ];
            }
        }

        return $this;
    }


    /**
     * Quote item discount calculation process
     *
     * @param AbstractItem $item
     * @return $this
     */
    public function processCap(AbstractItem $item,$ruleIds)
    {
        $item->setDiscountAmount(0);
        $item->setBaseDiscountAmount(0);
        $item->setDiscountPercent(0);

        $itemPrice = $this->getItemPrice($item);
        if ($itemPrice < 0) {
            return $this;
        }
        // Set max discount amount to the rule
        $rules= $this->_getRules($item->getAddress());

        foreach ($rules as $rule){
            if(in_array($rule->getId(),$ruleIds)){
                $max_discount_amount=$rule->getMaxDiscountAmount();
                $rule->setData('discount_amount_upto',true);
                $rule->setDiscountAmount($max_discount_amount);
            }
        }
        // end

        $appliedRuleIds = $this->rulesApplier->applyRules(
            $item,
            $rules,//$this->_getRules($item->getAddress()),
            $this->_skipActionsValidation,
            $this->getCouponCode()
        );

        $this->rulesApplier->setAppliedRuleIds($item, $appliedRuleIds);

        return $this;
    }

}