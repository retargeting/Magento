<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once(Mage::getBaseDir('lib') . '/Retargeting/Retargeting_REST_API_Client.php');

class Retargeting_Tracker_Model_Observer
{
    public function TrackSetEmail($observer)
    {
        $event = $observer->getEvent();  //Fetches the current event
        $customer = $observer->getCustomer();
        $customerPhone = '';
        $customerCity = '';

        $customerAddressId = $customer->getDefaultShipping();
        if ($customerAddressId)
        {
            $address = Mage::getModel('customer/address')->load($customerAddressId);
            $cust_data = $address->getData();
            $customerPhone = $cust_data['telephone'];
            $customerCity = $cust_data['city'];
        }

        $info = array(
            "email" => $customer->getEmail(),
            "name" => $customer->getName(),
            "phone" => $customerPhone,
            "city" => $customerCity,
            "sex" => $customer->getGender()
        );

        Mage::getSingleton('core/session')->setTriggerSetEmail($info);
    }

    public function TrackAddToCart($observer)
    {
    
        $magentoVersion = Mage::getVersion();
    
        if($magentoVersion > "1.4.2.0"){

        $helper = Mage::helper('catalog/product_configuration');

        $event = $observer->getEvent();  //Fetches the current event
        $product = $event->getProduct();
        $quoteItem = $event->getQuoteItem();

        $optionsCode = array();
        $optionsDetails = array();
        $options = "false";

        if ( ! $product->isConfigurable() && ! $product->isGrouped() ) {
            $itemOptions = $helper->getOptions($quoteItem);
            if ( count($itemOptions) > 0 ) {
                foreach ($itemOptions as $itemOption) {
                    $_optCode = str_replace(' ', '', $itemOption['value']);
                    $_optCode = str_replace('-', '', $_optCode);
                    $_optCode = strip_tags($_optCode);
                    $optionsCode[] = $_optCode;
                    $optionsDetails[] = '"'.$_optCode.'": {
                        "category_name": "'.htmlspecialchars($itemOption['label']).'",
                        "category": "'.htmlspecialchars($itemOption['label']).'",
                        "value": "'.htmlspecialchars($itemOption['value']).'"
                    }';
                }
            }
        } else {
            $itemOptions = $helper->getOptions($event);
            if ( count($itemOptions) > 0 ) {
                foreach ($itemOptions as $itemOption) {
                    $_optCode = str_replace(' ', '', $itemOption['value']);
                    $_optCode = str_replace('-', '', $_optCode);
                    $_optCode = strip_tags($_optCode);
                    $optionsCode[] = $_optCode;
                    $optionsDetails[] = '"'.$_optCode.'": {
                        "category_name": "'.htmlspecialchars($itemOption['label']).'",
                        "category": "'.htmlspecialchars($itemOption['label']).'",
                        "value": "'.htmlspecialchars($itemOption['value']).'"
                    }';
                }
            }
        }

        if(count($optionsCode) > 0) $options = '{ "code": "'.implode('-', $optionsCode).'", "details": {'.implode(', ', $optionsDetails).'} }';
        else $options = "false";

        $info = array(
            "product_id" => $product->getId(),
            "variation" => $options
        );

        Mage::getSingleton('core/session')->setTriggerAddToCart($info);
        
        } else {
            //Magento 1.4 compatibility
            $helper = Mage::helper('catalog/product');

            $event = $observer->getEvent();  //Fetches the current event
            $product = $event->getProduct();
            $quoteItem = $event->getQuoteItem();

            $info = array(
                "product_id" => $product->getId(),
                "variation" => "false"
            );

            Mage::getSingleton('core/session')->setTriggerAddToCart($info);
        }
    }

    public function TrackAddToWishlist($observer)
    {
        $event = $observer->getEvent();  //Fetches the current event
        $product = $event->getProduct();

        $info = array(
            "product_id" => $product->getId()
        );

        Mage::getSingleton('core/session')->setTriggerAddToWishlist($info);
    }

    public function TrackCommentOnProduct($observer)
    {

        $object = $observer->getEvent()->getObject();
        $productId = $object->getEntityPkValue();

        $info = array(
            "product_id" => $productId
        );

        Mage::getSingleton('core/session')->setTriggerCommentOnProduct($info);
    }

    public function TrackSaveOrder($observer)
    {
        $apiKey = Mage::getStoreConfig('retargetingtracker_options/domain/domain_api_key');
        $token = Mage::getStoreConfig('retargetingtracker_options/token/token');

        $magentoVersion = Mage::getVersion();
        if($magentoVersion > "1.4.2.0") {

            $helper = Mage::helper('catalog/product_configuration');

            $event = $observer->getEvent();  //Fetches the current event
            $order = $observer->getOrder();
            $billingAddress = $order->getbillingAddress();
            $quote = $observer->getEvent()->getQuote();

            $products = array();
            foreach ($order->getAllVisibleItems() as $item) {
                $itemOptions = $item->getProductOptions();
                $variationCode = "false";
                $optionsCode = array();

                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                if ( $product->isConfigurable() ) {
                    $_optCode = str_replace(' ', '', $item->getSku());
                    $_optCode = str_replace('-', '', $_optCode);
                    $_optCode = strip_tags($_optCode);
                    $optionsCode[] = $_optCode;
                }

                if ( count($itemOptions['options']) > 0 ) {
                    foreach ($itemOptions['options'] as $itemOption) {
                        $_optCode = str_replace(' ', '', $itemOption['value']);
                        $_optCode = str_replace('-', '', $_optCode);
                        $_optCode = strip_tags($_optCode);
                        $optionsCode[] = $_optCode;
                    }
                }

                $variationCode = count($optionsCode) > 0 ? '"'.implode('-', $optionsCode).'"' : "false";

                $products[] = '{
                    "id": "'. $item->getProductId() .'",
                    "quantity": '. $item->getQtyOrdered() .',
                    "price": ' . Mage::helper('tax')->getPrice($item, $item->getPrice()).',
                    "variation_code": ' . $variationCode . '}';
            }

            $info = array(
                "order_no" => $order->getIncrementId(),
                "firstname" => $billingAddress->getFirstname(),
                "lastname" => $billingAddress->getLastname(),
                "email" => $billingAddress->getEmail(),
                "phone" => $billingAddress->getTelephone(),
                "state" => $billingAddress->getRegion(),
                "city" => $billingAddress->getCity(),
                "address" => implode(" ", $billingAddress->getStreet()),
                "data" => $billingAddress->getData(),
                "discount" => $order->getDiscountAmount(),
                "discount_code" => $order->getCouponCode(),
                "shipping" => $order->getShippingInclTax(),
                "total" => $order->getGrandTotal(),
                "products" => "[".implode(",", $products)."]",
            );
        
            if($apiKey && $apiKey != "" && $token && $token != "") {
                $retargetingClient = new Retargeting_REST_API_Client($apiKey,$token);
                $retargetingClient->setResponseFormat("json");
                $retargetingClient->setDecoding(false);
                $response = $retargetingClient->order->save($info, $products);
            }
            
            Mage::getSingleton('core/session')->setTriggerSaveOrder($info);
        } else {
           
           // Magento 1.4 compatibility
            $helper = Mage::helper('catalog/product');

            $event = $observer->getEvent();  //Fetches the current event
            $order = $observer->getOrder();
            $billingAddress = $order->getBillingAddress();
            $quote = $observer->getEvent()->getQuote();

            $products = array();
            foreach ($order->getAllVisibleItems() as $item) {
                $itemOptions = $item->getProductOptions();

                $product = Mage::getModel('catalog/product')->load($item->getProductId());

                $variationCode = "";

                $products[] = '{
                    "id": "'. $item->getProductId() .'",
                    "quantity": '. $item->getQtyOrdered() .',
                    "price": ' . Mage::helper('tax')->getPrice($item, $item->getPrice()).',
                    "variation_code": false}';
            }

            $info = array(
                "order_no" => $order->getIncrementId(),
                "firstname" => $billingAddress->getFirstname(),
                "lastname" => $billingAddress->getLastname(),
                "email" => $billingAddress->getEmail(),
                "phone" => $billingAddress->getTelephone(),
                "state" => $billingAddress->getRegion(),
                "city" => $billingAddress->getCity(),
                "address" => implode(" ", $billingAddress->getStreet()),
                "data" => $billingAddress->getData(),
                "discount" => $order->getDiscountAmount(),
                "discount_code" => $order->getCouponCode(),
                "shipping" => $order->getShippingInclTax(),
                "total" => $order->getGrandTotal(),
                "products" => "[".implode(",", $products)."]",
            );
            
            if($apiKey && $apiKey != "" && $token && $token != "") {
                $retargetingClient = new Retargeting_REST_API_Client($apiKey,$token);
                $retargetingClient->setResponseFormat("json");
                $retargetingClient->setDecoding(false);
                $response = $retargetingClient->order->save($info, $products);
            }

            Mage::getSingleton('core/session')->setTriggerSaveOrder($info);
        }
    }
}