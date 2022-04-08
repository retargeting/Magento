<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_DiscountsController extends Mage_Core_Controller_Front_Action
{
    /**
     * Index action
     */
    public function indexAction()
    {
        return $this->getResponse()
            ->setHeader('Content-Type', 'application/json', 1)
            ->setBody('Discounts!');
    }

    /**
     * Add Discount
     */
    public function addDiscountCodeAction()
    {
        $params = $this->getRequest()->getParams();

        if (isset($params['key']) && isset($params['value']) && isset($params['type']) && isset($params['count'])) {
            $userApiKey = Mage::getStoreConfig('retargetingtracker_options/token/token');

            if ($userApiKey != '' && $params['key'] == $userApiKey && $params['value'] != "" && $params['type'] != "" && $params['count'] != "") {
                $name = 'RA-' . htmlspecialchars($params['type']) . '-' . htmlspecialchars($params['value']);
                $discount = htmlspecialchars($params['value']);
                $raDiscountType = htmlspecialchars($params['type']);
                $count = htmlspecialchars($params['count']);

                return $this->generateRule($name, $discount, $raDiscountType, $count);
            } else {
                $response = json_encode(array(
                    "status" => false,
                    "error" => "0002: Invalid Parameters!"
                ));
                return $this->getResponse()
                    ->setHeader('Content-Type', 'application/json', 1)
                    ->setBody($response);
            }
        } else {
            $response = json_encode(array(
                "status" => false,
                "error" => "0001: Missing Parameters!"
            ));
            return $this->getResponse()
                ->setHeader('Content-Type', 'application/json', 1)
                ->setBody($response);
        }
    }

    /**
     * @param null $name
     * @param int $discount
     * @param int $raDiscountType
     * @param $count
     * @return string
     */
    private function generateRule($name = null, $discount = 0, $raDiscountType = 0, $count)
    {
        if ($raDiscountType == 0) {
            $raDiscountType = "fixed value";
        }
        if ($raDiscountType == 1) {
            $raDiscountType = "percentage";
        }
        if ($raDiscountType == 2) {
            $raDiscountType = "free shipping";
        }

        if ($name != null && ($raDiscountType == "fixed value" || $raDiscountType == "free shipping" || $raDiscountType == "percentage")) {
            $rule = Mage::getModel('salesrule/rule');

            $customerGroupColl = Mage::getModel('customer/group')->getCollection();
            $customer_groups = array();
            foreach ($customerGroupColl as $group) {
                $customer_groups[] = $group->getCustomerGroupId();
            }

            //Get all Store Ids
            $storeIds = array();
            $allWebsites = Mage::app()->getWebsites();
            foreach ($allWebsites as $websiteId => $website) {
                $storeIds[] = $websiteId;
            }

            // discount name and init
            $rule->setName($name)
                ->setDescription("Auto generated discount through Retargeting Discount API")
                ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_AUTO)
                ->setUsesPerCustomer(1)
                ->setUsesPerCoupon(1)
                ->setCustomerGroupIds($customer_groups)
                ->setIsActive(1)
                ->setConditionsSerialized('')
                ->setActionsSerialized('')
                ->setStopRulesProcessing(0)
                ->setIsAdvanced(1)
                ->setProductIds('')
                ->setSortOrder(0)
                ->setSimpleFreeShipping('0')
                ->setApplyToShipping('0')
                ->setIsRss(0)
                ->setWebsiteIds($storeIds)
                ->setUseAutoGeneration(1);

            // discount amount
            $rule->setDiscountAmount($discount)
                ->setDiscountQty(null)
                ->setDiscountStep(0);

            // discount type
            switch ($raDiscountType) {
                case 'percentage':
                    $rule->setSimpleAction('by_percent');
                    break;
                case 'free shipping':
                    $rule->setSimpleAction('by_fixed')
                        ->setSimpleFreeShipping('1')
                        ->setDiscountAmount(0);
                    break;
                case 'fixed value':
                    $rule->setSimpleAction('cart_fixed');
                    break;
            }

            // discount availability
            $rule->setFromDate(date('Y-m-d'));

            $generator = Mage::getModel('salesrule/coupon_massgenerator');

            $parameters = array(
                'count' => 5,
                'format' => 'alphanumeric',
                'dash_every_x_characters' => 4,
                'prefix' => 'ABCD-EFGH-',
                'suffix' => '-WXYZ',
                'length' => 8
            );

            $generator->setFormat(Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC);
            $generator->setDash(0);
            $generator->setLength(8);
            $generator->setPrefix('');
            $generator->setSuffix('');

            // Set the generator, and coupon type so it's able to generate
            $rule->setCouponCodeGenerator($generator);

            // save discount
            $rule->save();

            $codes = array();
            for ($i = 0; $i < $count; $i++) {
                $coupon = $rule->acquireCoupon(true);
                $coupon
                    ->setType(Mage_SalesRule_Helper_Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED)
                    ->save();
                $code = $coupon->getCode();
                $codes[] = $code;
            }

            $rule->setCouponType(2);
            $rule->save();

            $data = json_encode($codes);
            return $this->getResponse()
                ->setHeader('Content-Type', 'application/json', 1)
                ->setBody($data);
        }

        $invalidParametersResponse = json_encode(array(
            "status" => false,
            "error" => "0003: Invalid Parameters!"
        ));
        return $this->getResponse()
            ->setHeader('Content-Type', 'application/json', 1)
            ->setBody($invalidParametersResponse);
    }
}
