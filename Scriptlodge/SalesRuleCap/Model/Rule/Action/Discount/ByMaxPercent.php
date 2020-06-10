<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Scriptlodge\SalesRuleCap\Model\Rule\Action\Discount;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\SalesRule\Model\DeltaPriceRound;
use Magento\SalesRule\Model\Validator;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;

class ByMaxPercent extends AbstractDiscount
{

    /**
     * @var string
     */
    private static $discountType = 'CartMaxPercent';


    /**
     * @var DeltaPriceRound
     */
    private $deltaPriceRound;


    /**
     * @param Validator $validator
     * @param DataFactory $discountDataFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param DeltaPriceRound $deltaPriceRound
     */
    public function __construct(
        Validator $validator,
        \Magento\SalesRule\Model\Rule\Action\Discount\DataFactory $discountDataFactory,
        PriceCurrencyInterface $priceCurrency,
        DeltaPriceRound $deltaPriceRound = null
    ) {
        $this->deltaPriceRound = $deltaPriceRound ?: ObjectManager::getInstance()->get(DeltaPriceRound::class);

        parent::__construct($validator, $discountDataFactory, $priceCurrency);
    }

    /**
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param float $qty
     * @return Data
     */
    public function calculate($rule, $item, $qty,$checkcap=false)
    {

        $discount_amount_upto=$rule->getDiscountAmountUpto();
        $rulePercent = min(100, $rule->getDiscountAmount());
        if($checkcap==true){
            $discountData = $this->_calculatecap($rule, $item, $qty, $rulePercent,$discount_amount_upto);
        }else{
            $discountData = $this->_calculate($rule, $item, $qty, $rulePercent,$discount_amount_upto);
        }
        return $discountData;
    }

    protected function _calculatecap($rule, $item, $qty, $rulePercent,$maxdiscount){
        /** @var \Magento\SalesRule\Model\Rule\Action\Discount\Data $discountData */
        $discountData = $this->discountFactory->create();

        $itemPrice = $this->validator->getItemPrice($item);
        $baseItemPrice = $this->validator->getItemBasePrice($item);
        $itemOriginalPrice = $this->validator->getItemOriginalPrice($item);
        $baseItemOriginalPrice = $this->validator->getItemBaseOriginalPrice($item);

        $_rulePct = $rulePercent / 100;

        $discountData->setAmount(($qty * $itemPrice) * $_rulePct);
        $discountData->setAmount(($qty * $itemPrice) * $_rulePct);
        $discountData->setBaseAmount(($qty * $baseItemPrice) * $_rulePct);
        $discountData->setOriginalAmount(($qty * $itemOriginalPrice) * $_rulePct);
        $discountData->setBaseOriginalAmount(
            ($qty * $baseItemOriginalPrice) * $_rulePct
        );

        /*$discountData->setAmount(($qty * $itemPrice - $item->getDiscountAmount()) * $_rulePct);
        $discountData->setBaseAmount(($qty * $baseItemPrice - $item->getBaseDiscountAmount()) * $_rulePct);
        $discountData->setOriginalAmount(($qty * $itemOriginalPrice - $item->getDiscountAmount()) * $_rulePct);
        $discountData->setBaseOriginalAmount(
            ($qty * $baseItemOriginalPrice - $item->getBaseDiscountAmount()) * $_rulePct
        );*/

        if (!$rule->getDiscountQty() || $rule->getDiscountQty() > $qty) {
            $discountPercent = min(100, $item->getDiscountPercent() + $rulePercent);
            $item->setDiscountPercent($discountPercent);
        }


        return $discountData;
    }

    /**
     * @param \Magento\SalesRule\Model\Rule $rule
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param float $qty
     * @param float $rulePercent
     * @return Data
     */
    protected function _calculate($rule, $item, $qty, $rulePercent,$maxdiscount)
    {
        if($maxdiscount > 0) {
            /** @var \Magento\SalesRule\Model\Rule\Action\Discount\Data $discountData */
            $discountData = $this->discountFactory->create();

            $ruleTotals = $this->validator->getRuleItemTotalsInfo($rule->getId());

            $quote = $item->getQuote();
            $address = $item->getAddress();

            $itemPrice = $this->validator->getItemPrice($item);
            $baseItemPrice = $this->validator->getItemBasePrice($item);
            $itemOriginalPrice = $this->validator->getItemOriginalPrice($item);
            $baseItemOriginalPrice = $this->validator->getItemBaseOriginalPrice($item);

            /**
             * prevent applying whole cart discount for every shipping order, but only for first order
             */
            if ($quote->getIsMultiShipping()) {
                $usedForAddressId = $this->getCartFixedRuleUsedForAddress($rule->getId());
                if ($usedForAddressId && $usedForAddressId != $address->getId()) {
                    return $discountData;
                } else {
                    $this->setCartFixedRuleUsedForAddress($rule->getId(), $address->getId());
                }
            }
            $cartRules = $address->getCartFixedRules();

            if (!isset($cartRules[$rule->getId()])) {
                $cartRules[$rule->getId()] = $rule->getDiscountAmount();

            }


            $availableDiscountAmount = (float)$cartRules[$rule->getId()];
            //$availableDiscountAmount = (float)$rule->getDiscountAmount();
            $discountType = self::$discountType . $rule->getId();



            if ($availableDiscountAmount > 0) {
                $store = $quote->getStore();
                if ($ruleTotals['items_count'] <= 1) {
                    $quoteAmount = $this->priceCurrency->convert($availableDiscountAmount, $store);
                    $baseDiscountAmount = min($baseItemPrice * $qty, $availableDiscountAmount);
                    $this->deltaPriceRound->reset($discountType);
                } else {
                    $ratio = $baseItemPrice * $qty / $ruleTotals['base_items_price'];
                    $maximumItemDiscount = $this->deltaPriceRound->round(
                        $rule->getDiscountAmount() * $ratio,
                        $discountType
                    );

                    $quoteAmount = $this->priceCurrency->convert($maximumItemDiscount, $store);

                    $baseDiscountAmount = min($baseItemPrice * $qty, $maximumItemDiscount);
                    $this->validator->decrementRuleItemTotalsCount($rule->getId());
                }

                $baseDiscountAmount = $this->priceCurrency->round($baseDiscountAmount);

                $availableDiscountAmount -= $baseDiscountAmount;
                $cartRules[$rule->getId()] = $availableDiscountAmount;
                if ($availableDiscountAmount <= 0) {
                    $this->deltaPriceRound->reset($discountType);
                }

                $discountData->setAmount($this->priceCurrency->round(min($itemPrice * $qty, $quoteAmount)));
                $discountData->setBaseAmount($baseDiscountAmount);
                $discountData->setOriginalAmount(min($itemOriginalPrice * $qty, $quoteAmount));
                $discountData->setBaseOriginalAmount($this->priceCurrency->round($baseItemOriginalPrice));
            }
            $address->setCartFixedRules($cartRules);

            return $discountData;
        }else {
            /** @var \Magento\SalesRule\Model\Rule\Action\Discount\Data $discountData */
            $discountData = $this->discountFactory->create();
            $itemPrice = $this->validator->getItemPrice($item);
            $baseItemPrice = $this->validator->getItemBasePrice($item);
            $itemOriginalPrice = $this->validator->getItemOriginalPrice($item);
            $baseItemOriginalPrice = $this->validator->getItemBaseOriginalPrice($item);

            $_rulePct = $rulePercent / 100;
            $discountData->setAmount(($qty * $itemPrice - $item->getDiscountAmount()) * $_rulePct);
            $discountData->setBaseAmount(($qty * $baseItemPrice - $item->getBaseDiscountAmount()) * $_rulePct);
            $discountData->setOriginalAmount(($qty * $itemOriginalPrice - $item->getDiscountAmount()) * $_rulePct);
            $discountData->setBaseOriginalAmount(
                ($qty * $baseItemOriginalPrice - $item->getBaseDiscountAmount()) * $_rulePct
            );

            if (!$rule->getDiscountQty() || $rule->getDiscountQty() > $qty) {
                $discountPercent = min(100, $item->getDiscountPercent() + $rulePercent);
                $item->setDiscountPercent($discountPercent);
            }

            return $discountData;
        }
    }

    /**
     * @param float $qty
     * @param \Magento\SalesRule\Model\Rule $rule
     * @return float
     */
    public function fixQuantity($qty, $rule)
    {
        $step = $rule->getDiscountStep();
        if ($step) {
            $qty = floor($qty / $step) * $step;
        }

        return $qty;
    }
}
