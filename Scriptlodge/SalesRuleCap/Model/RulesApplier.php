<?php
/**
 * Created by PhpStorm.
 * User: khasru
 * Date: 2/16/19
 * Time: 3:32 PM
 */

namespace Scriptlodge\SalesRuleCap\Model;

use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Quote\ChildrenValidationLocator;
use Magento\Framework\App\ObjectManager;


class RulesApplier extends \Magento\SalesRule\Model\RulesApplier
{
    /**
     * Application Event Dispatcher
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager;

    /**
     * @var \Magento\SalesRule\Model\Utility
     */
    protected $validatorUtility;

    /**
     * @var ChildrenValidationLocator
     */
    private $childrenValidationLocator;

    /**
     * @var \Magento\SalesRule\Model\Rule\Action\Discount\CalculatorFactory
     */
    private $calculatorFactory;

    /**
     * @param \Scriptlodge\SalesRuleCap\Model\Rule\Action\Discount\CalculatorFactory $calculatorFactory
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\SalesRule\Model\Utility $utility
     * @param ChildrenValidationLocator $childrenValidationLocator
     */
    public function __construct(
        \Scriptlodge\SalesRuleCap\Model\Rule\Action\Discount\CalculatorFactory $calculatorFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\SalesRule\Model\Utility $utility,
        ChildrenValidationLocator $childrenValidationLocator = null
    ) {
        $this->calculatorFactory = $calculatorFactory;
        $this->validatorUtility = $utility;
        $this->_eventManager = $eventManager;
        $this->childrenValidationLocator = $childrenValidationLocator
            ?: ObjectManager::getInstance()->get(ChildrenValidationLocator::class);

        parent::__construct(
            $calculatorFactory,
            $eventManager,
            $utility,
            $childrenValidationLocator
        );
    }


    /**
     * @param \Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @param \Magento\SalesRule\Model\Rule $rule
     * @return \Magento\SalesRule\Model\Rule\Action\Discount\Data
     */
    public function getDiscountDataCap($item, $rule, $address, $couponCode, $skipValidation = false)
    {
        if (!$this->validatorUtility->canProcessRule($rule, $address)) {
            return;
        }

        if (!$skipValidation && !$rule->getActions()->validate($item)) {

            if (!$this->childrenValidationLocator->isChildrenValidationRequired($item)) {
                return;
            }
            $childItems = $item->getChildren();
            $isContinue = true;
            if (!empty($childItems)) {
                foreach ($childItems as $childItem) {
                    if ($rule->getActions()->validate($childItem)) {
                        $isContinue = false;
                    }
                }
            }
            if ($isContinue) {
                return;
            }
        }


        $qty = $this->validatorUtility->getItemQty($item, $rule);

        $discountCalculator = $this->calculatorFactory->create($rule->getSimpleAction());

        $qty = $discountCalculator->fixQuantity($qty, $rule);

        return $discountData = $discountCalculator->calculate($rule, $item, $qty, true);

    }

}