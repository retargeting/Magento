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

    public function feedgen($schedule = null)
    {
        if (!empty(Mage::getStoreConfig('retargetingtracker_options/more/cronfeed'))) {
            echo json_encode(Mage::getModel('retargeting_tracker/feed')->cronFeed());
        }
    }

    private $isSubscribed = false;
    public function subscriberStatus($observer)
    {
        $info = null;

        $model = $observer->getObject();

        if (!$model instanceof Mage_Newsletter_Model_Subscriber) {
            return;
        }

        $customer = $model;

        if ($customer->getEmail() === null) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
        }

        $customer = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());

        $cus = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getWebsite()->getId())
        ->loadByEmail($customer->getEmail());

        $customerPhone = '';

        $customerAddressId = $cus->getDefaultShipping();
        if ($customerAddressId) {
            $address = Mage::getModel('customer/address')->load($customerAddressId);

            $customerData = $address->getData();
            $customerPhone = $customerData['telephone'];
        }
        
        $info = array(
            "email" => $customer->getEmail(),
            "name" => '',
            "phone" => $customerPhone
        );

        if ($cus->getName() !== null && $cus->getName() !== ' ') {
            $info["name"] = $cus->getName();
        } else if ($cus->getFirstname() === null && $cus->getLastname() === null) {
            $info["name"] = explode("@",$customer->getEmail())[0];
        } else if ($cus->getFirstname() !== null && $cus->getLastname() !== null) {
            $info["name"] = $cus->getFirstname().' '.$cus->getLastname();
        } else if ($cus->getFirstname() !== null) {
            $info["name"] = $cus->getFirstname();
        } else  if ($cus->getLastname() !== null) {
            $info["name"] = $cus->getLastname();
        } else {
            $info["name"] = explode("@",$customer->getEmail())[0];
        }
        //var_dump($cus->getFirstname(),empty($cus->getName()));

        $this->isSubscribed = $customer->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
        //var_dump($info);
        //die();
        $this->sendSubCustomer($customer, $info);
    }

    public function TrackRegister($observer) {

        $customer = $observer->getCustomer();
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customer->getEmail());

        $this->isSubscribed = $subscriber->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
        if ($this->isSubscribed) {
            $this->sendSubCustomer($customer);
        }
    }

    public function sendSubCustomer($customer, $info = null) {
        if ($info === null){
            $customerPhone = '';

            $customerAddressId = $customer->getDefaultShipping();
            if ($customerAddressId) {
                $address = Mage::getModel('customer/address')->load($customerAddressId);
                $customerData = $address->getData();
                $customerPhone = $customerData['telephone'];
            }

            $info = array(
                "email" => $customer->getEmail(),
                "name" => $customer->getName(),
                "phone" => $customerPhone
            );
        } else {
            $info = array(
                "email" => $info["email"],
                "name" => $info["name"],
                "phone" => $info["phone"]
            );
        }

        $this->Subscriber($this->isSubscribed, $info);
    }

    private function Subscriber($isSubscribed, $info) {
        try {
            $apiURL = "https://api.retargeting.app/v1/" . ( $isSubscribed ? "subscriber-register" : "subscriber-unsubscribe" );

            $key = Mage::getStoreConfig('retargetingtracker_options/token/token');

            if (empty($key)) {
                return false;
            }

            $info['k'] = $key;

            if (empty($info['phone'])){
                unset($info['phone']);
            }

            $apiURL = $apiURL .'?'. http_build_query($info);

            $curl_request = curl_init();
            curl_setopt($curl_request, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($curl_request, CURLOPT_TIMEOUT, 1);
            curl_setopt($curl_request, CURLOPT_URL, $apiURL);
            curl_setopt($curl_request, CURLOPT_POST, false);
            // curl_setopt($curl_request, CURLOPT_POSTFIELDS, $api_parameters);
            curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, false);
            $out = curl_exec($curl_request);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function TrackSetEmail($observer)
    {
        $event = $observer->getEvent();  //Fetches the current event
        $customer = $observer->getCustomer();

        $this->isSubscribed = $customer->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;

        if ($this->isSubscribed) {

            $customerPhone = '';
            $customerCity = '';

            $customerAddressId = $customer->getDefaultShipping();

            if ($customerAddressId) {
                $address = Mage::getModel('customer/address')->load($customerAddressId);
                $customerData = $address->getData();
                $customerPhone = $customerData['telephone'];
                $customerCity = $customerData['city'];
            }

            $info = array (
                "email" => $customer->getEmail(),
                "name" => $customer->getName(),
                "phone" => $customerPhone,
                "city" => $customerCity,
                "sex" => $customer->getGender()
            );

            // $this->sendSubCustomer($customer, $info);

            Mage::getSingleton('core/session')->setTriggerSetEmail( $info );
        }
    }

    public function removeFromCart($observer)
    {
        $item = $observer->getQuoteItem();

        $info = array(
            'product_id' => $item->getProductId(),
            'quantity' => $item->getQty(),
            'variation' => false
        );
        Mage::getSingleton('core/session')->setTriggerRemoveFromCart(json_encode($info));
    }

    public function TrackAddToCart($observer)
    {
        $magentoVersion = Mage::getVersion();

        if ($magentoVersion > "1.4.2.0") {
            $helper = Mage::helper('catalog/product_configuration');

            $event = $observer->getEvent();  //Fetches the current event
            $product = $event->getProduct();
            $quoteItem = $event->getQuoteItem();

            $optionsCode = array();
            $optionsDetails = array();
            $options = "false";

            if (!$product->isConfigurable() && !$product->isGrouped()) {
                $itemOptions = $helper->getOptions($quoteItem);
                if (!empty($itemOptions)) {
                    foreach ($itemOptions as $itemOption) {
                        $_optCode = str_replace(' ', '', $itemOption['value']);
                        $_optCode = str_replace('-', '', $_optCode);
                        $_optCode = strip_tags($_optCode);
                        $optionsCode[] = $_optCode;
                        $optionsDetails[] = '"' . $_optCode . '": {
                        "category_name": "' . htmlspecialchars($itemOption['label']) . '",
                        "category": "' . htmlspecialchars($itemOption['label']) . '",
                        "value": "' . htmlspecialchars($itemOption['value']) . '"
                    }';
                    }
                }
            } else {
                $itemOptions = $helper->getOptions($quoteItem);
                if (!empty($itemOptions)) {
                    foreach ($itemOptions as $itemOption) {
                        $_optCode = str_replace(' ', '', $itemOption['value']);
                        $_optCode = str_replace('-', '', $_optCode);
                        $_optCode = strip_tags($_optCode);
                        $optionsCode[] = $_optCode;
                        $optionsDetails[] = '"' . $_optCode . '": {
                        "category_name": "' . htmlspecialchars($itemOption['label']) . '",
                        "category": "' . htmlspecialchars($itemOption['label']) . '",
                        "value": "' . htmlspecialchars($itemOption['value']) . '"
                    }';
                    }
                }
            }

            if (!empty($optionsCode)) {
                $options = '{ "code": "' . implode('-', $optionsCode) . '", 
                "stock":1, "details": {' . implode(', ', $optionsDetails) . '} }';
            } else {
                $options = "false";
            }

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
        $magentoVersion = Mage::getVersion();
        $order = $observer->getOrder();

        if ($magentoVersion > "1.4.2.0") {
            // $helper = Mage::helper('catalog/product_configuration');
            $billingAddress = $order->getbillingAddress();
        } else {
            // $helper = Mage::helper('catalog/product');
            $billingAddress = $order->getBillingAddress();
        }

        // $quote = $observer->getEvent()->getQuote();

        $products = array();
        foreach ($order->getAllVisibleItems() as $item) {
            $itemOptions = $item->getProductOptions();
            $product = Mage::getModel('catalog/product')->load($item->getProductId());
            if ($magentoVersion > "1.4.2.0") {
                $variationCode = false;
                $optionsCode = array();
                if ($product->isConfigurable()) {
                    $_optCode = str_replace(' ', '', $item->getSku());
                    $_optCode = str_replace('-', '', $_optCode);
                    $_optCode = strip_tags($_optCode);
                    $optionsCode[] = $_optCode;
                }

                if (isset($itemOptions['options']) && !empty($itemOptions['options'])) {
                    foreach ($itemOptions['options'] as $itemOption) {
                        $_optCode = str_replace(' ', '', $itemOption['value']);
                        $_optCode = str_replace('-', '', $_optCode);
                        $_optCode = strip_tags($_optCode);
                        $optionsCode[] = $_optCode;
                    }
                }

                $variationCode = !empty($optionsCode) ? implode('-', $optionsCode): false;
            } else {
                $variationCode = false;
            }
            $products[] = array(
                'id' => $item->getProductId(),
                'quantity' => $item->getQtyOrdered(),
                'price' => number_format(Mage::helper('tax')->getPrice($item, $item->getPrice()), 2, '.', ''),
                'variation_code' => $variationCode
            );
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
            "products" => json_encode($products)
        );

        Mage::getSingleton('core/session')->setTriggerSaveOrder($info);

        $customer = Mage::getModel('newsletter/subscriber')->loadByEmail($billingAddress->getEmail());

        

        $this->isSubscribed = $customer->getStatus() == Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED;
        
        $customerPhone = $billingAddress->getTelephone();

        $info = array (
            "email" => $customer->getEmail(),
            "name" => $billingAddress->getFirstname().' '.$billingAddress->getLastname(),
            "phone" => $customerPhone,
        );

        $this->sendSubCustomer($customer, $info);
        //die();
    }
}
