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

    private $delete = null;
    
    public function prepareImg($product)
    {
        $imgUrl = Mage::helper('catalog/image')->init($product, 'image')->resize(500);

        if ($this->delete === null) {
            $exp = explode("/",$imgUrl);
            $start = false;
            $count = 0;
            $this->delete = '';
            foreach ($exp as $k => $v) {
                if ($v === "cache") {
                    $start = true;
                }
                if ($start) {
                    $count++;
                    if ($count <= 5){
                        $this->delete .= '/'.$v;
                    }
                }
            }
        }
        return str_replace($this->delete, "", $imgUrl);
    }

    protected function buildImageUrl($path)
    {
        if (substr($path,0,1) !== "/") {  $path = "/".$path; }
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $path;
    }

    protected function buildProductUrl($path)
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . $path;
    }

    public static $cronFeed = false;
    
    public function feedgen($schedule = null)
    {
        if (self::$cronFeed) {
        /*
        <Files *retargeting.csv>
            order allow,deny
            allow from all
        </Files>
        */
            ini_set('display_errors', '1');
            error_reporting(E_ALL);

            ini_set('max_execution_time', 12600);//3600);
            ini_set('memory_limit', '8G');
            set_time_limit(12600);
            
            //header("Content-Disposition: attachment; filename=retargeting.csv");
            //header("Content-type: text/csv");
            //$link = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            //echo $dir;
            //die();
            //$websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
            //$mgV = (float) Mage::getVersion();

            //copy($files['tmp'][0], $files['static'][0]);
            //chmod( $files['static'][0], 777);

            //chmod($files['static'][0], 777);

            //throw new Exception(Mage::helper('cron')->__('Unable to save This Cron Job'));

            $dir = Mage::getBaseDir('base').'/app/code/community/Retargeting/Tracker/Feed';
            
            $name = date('m_d_Y_H_i_s');
            
            $files = [
                'tmp' => [
                    $dir . '/retargeting.'.$name.'.csv',
                    'w+'
                ],
                'static' => [
                    $dir . '/retargeting.csv',
                    'w'
                ]
            ];

            $storeId = Mage::app()->getStore()->getId();
            
            try{
                $_productCollection = Mage::getModel('catalog/product')->getCollection();
                $_productCollection->addAttributeToSelect(array('id', 'name', 'url_path', 'image', 'price', 'specialprice','stock','visibility','status'));
                $_productCollection->addFieldToFilter( 'visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH );
                $_productCollection->addAttributeToFilter( 'status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED );

                $_productCollection->setPageSize(100);

                $pages = $_productCollection->getLastPageNumber();
                $currentPage = 1;
                // chmod( $files['tmp'][0], 777);
                $outstream = fopen($files['tmp'][0], $files['tmp'][1]) or die('fail to create file ' .$files['tmp'][0].' - '. $files['tmp'][1] );

                fputcsv($outstream, array(
                    'product id',
                    'product name',
                    'product url',
                    'image url',
                    'stock',
                    'price',
                    'sale price',
                    'brand',
                    'category',
                    'extra data'
                ), ',', '"');

                do {
                    $_productCollection->setCurPage($currentPage);
                    $_productCollection->load();

                    foreach ($_productCollection as $_product) {
                        
                        $extra_data = [
                            'categories' => [],
                            'media_gallery' => [],
                            'variations' => [],
                            'margin' => null
                        ];

                        $product = Mage::getModel('catalog/product')->load($_product->getId());

                        if(isset($product->media_gallery['images'])) {
                            foreach ($product->media_gallery['images'] as $img) {
                                if($img['disabled'] != '0') {
                                    continue;
                                }
                                $extra_data['media_gallery'][] = $this->buildImageUrl($img['file']);
                            }
                        }

                        $categories = $product->getCategoryIds();

                        foreach($categories as $categoryId) {
                            $category = Mage::getModel('catalog/category')
                                ->setStoreId($storeId)
                                ->load($categoryId);
                            if (!empty($category->getName())) {
                                $extra_data['categories'][$categoryId] = $category->getName();
                            }
                        }

                        if (empty($extra_data['categories'])) {
                            $extra_data['categories']['root'] = 'Root';
                        }

                        $imgUrl = $this->prepareImg($product);
                        $productURL = $this->buildProductUrl($product->geturlpath());
                        $price = $product->getPrice();

                        if( "no_selection" === $imgUrl ||
                            empty($imgUrl) ||
                            empty((float) $price) || !filter_var($productURL, FILTER_VALIDATE_URL)){
                            continue;
                        }
                        $finalPrice = $product->getFinalPrice();

                        $salePrice = empty((float) $finalPrice) ? $price : $finalPrice;

                        if($product->getTypeId() == 'configurable') {
                            $productType = Mage::getModel('catalog/product_type_configurable');
                            $products = $productType->getUsedProducts(null, $product);

                            foreach ($products as $p) {
                                $extra_data['variations'][] = [
                                    'code' => sprintf("%s-%s", $p->getAttributeText('color'), $p->getAttributeText('size') ),
                                    'price' => number_format($price, 2),
                                    'sale_price' => number_format($salePrice, 2),
                                    'stock' => $this->getQty($p),
                                    'size' => $p->getAttributeText('size'),
                                    'color' => $p->getAttributeText('color')
                                ];
                            }
                        }

                        $brand = '';
                        
                        fputcsv($outstream, array(
                            'product id' => $product->getId(),
                            'product name' => $product->getName(),
                            'product url' => $productURL,
                            'image url' => $imgUrl,
                            'stock' => $this->getQty($product),
                            'price' => number_format($price, 2, '.', ''),
                            'sale price' => number_format($salePrice, 2, '.', ''),
                            'brand' => $brand,
                            'category' => end($extra_data['categories']),
                            'extra data' => json_encode($extra_data, JSON_UNESCAPED_SLASHES)
                        ), ',', '"');
                    }
                    /*
                    if ($currentPage >== 10) {
                        $pages = $currentPage;
                    }

                    if ($prod >= 25000) {
                        $pages = $currentPage;
                    }
                    */

                    $currentPage++;
                    $_productCollection->clear();
                } while ($currentPage <= $pages);

                fclose($outstream);

                if(!copy($files['tmp'][0], $files['static'][0]))
                {
                    $errors = error_get_last();
                    $myfile = fopen($dir . '/errorRTG.log', "w+") or die("Unable to open file!");
                    fwrite($myfile, "COPY ERROR: ".$errors['type']);
                    fwrite($myfile, "<br />\n".$errors['message']);
                    fwrite($myfile, "<br />\n".json_encode($errors));
                    fclose($myfile);
                }
                unlink($files['tmp'][0]);
            } catch (Exception $e) {
                $myfile = fopen($dir . '/errorRTG.log', "w+") or die("Unable to open file!");
                fwrite($myfile, $e->getMessage());
                fwrite($myfile, $e->getTraceAsString());
                fclose($myfile);
            }
        }
    }

    protected function getQty(Mage_Catalog_Model_Product $product)
    {
        $qty = 0;

        switch ($product->getTypeId()) {
            case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                $bundledItemIds = Mage::getResourceSingleton('bundle/selection')
                    ->getChildrenIds($product->getId(), $required = true);
                $products = array();
                foreach ($bundledItemIds as $variants) {
                    if (is_array($variants) && count($variants) > 0) { // @codingStandardsIgnoreLine
                        foreach ($variants as $variantId) {
                            /* @var Mage_Catalog_Model_Product $productModel */
                            $productModel = Mage::getModel('catalog/product')->load($variantId); // @codingStandardsIgnoreLine
                            $products[] = $productModel;
                        }
                    }
                }
                $qty = $this->getMinQty($products);
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                /** @var Mage_Catalog_Model_Product_Type_Grouped $productType */
                $productType = $product->getTypeInstance(true);
                $products = $productType->getAssociatedProducts($product);
                $qty = $this->getMinQty($products);
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE:
                /** @var Mage_Catalog_Model_Product_Type_Configurable $productType */
                $productType = Mage::getModel('catalog/product_type_configurable');
                $products = $productType->getUsedProducts(null, $product);
                $qty = $this->getQtySum($products);
                break;
            default:
                /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
                $stockItem = Mage::getModel('cataloginventory/stock_item');
                /** @noinspection PhpUndefinedMethodInspection */
                $qty += $stockItem->loadByProduct($product)->getQty();
                break;
        }
        if($qty < 0) {
            return 0;
        }
        return $qty;
    }

    protected function getMinQty(array $productCollection)
    {
        $quantities = array();
        $minQty = 0;
        /* @var Mage_Catalog_Model_Product $product */
        foreach ($productCollection as $product) {
            $quantities[] = $this->getQty($product);
        }
        if (!empty($quantities)) {
            rsort($quantities, SORT_NUMERIC);
            $minQty = array_pop($quantities);
        }

        return $minQty;
    }

    protected function getQtySum(array $productCollection)
    {
        $qty = 0;
        /* @var Mage_Catalog_Model_Product $product */
        foreach ($productCollection as $product) {
            $qty += $this->getQty($product);
        }

        return $qty;
    }

    public function TrackSetEmail($observer)
    {
        $event = $observer->getEvent();  //Fetches the current event
        $customer = $observer->getCustomer();
        $customerPhone = '';
        $customerCity = '';

        $customerAddressId = $customer->getDefaultShipping();
        if ($customerAddressId) {
            $address = Mage::getModel('customer/address')->load($customerAddressId);
            $customerData = $address->getData();
            $customerPhone = $customerData['telephone'];
            $customerCity = $customerData['city'];
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
        $apiKey = Mage::getStoreConfig('retargetingtracker_options/domain/domain_api_key');
        $token = Mage::getStoreConfig('retargetingtracker_options/token/token');

        $magentoVersion = Mage::getVersion();
        if ($magentoVersion > "1.4.2.0") {
            $helper = Mage::helper('catalog/product_configuration');

            $event = $observer->getEvent();  //Fetches the current event
            $order = $observer->getOrder();
            $billingAddress = $order->getbillingAddress();
            $quote = $observer->getEvent()->getQuote();

            $products = array();
            foreach ($order->getAllVisibleItems() as $item) {
                $itemOptions = $item->getProductOptions();
                $variationCode = false;
                $optionsCode = array();

                $product = Mage::getModel('catalog/product')->load($item->getProductId());
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
                "products" => json_encode($products),
            );

            if ($token && $token != "") {
                $retargetingClient = new Retargeting_REST_API_Client($token);
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

                $products[] = array(
                    'id' => $item->getProductId(),
                    'quantity' => $item->getQtyOrdered(),
                    'price' => number_format(Mage::helper('tax')->getPrice($item, $item->getPrice()), 2, '.', ''),
                    'variation_code' => false
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

            if ($token && $token != "") {
                $retargetingClient = new Retargeting_REST_API_Client($token);
                $retargetingClient->setResponseFormat("json");
                $retargetingClient->setDecoding(false);
                $response = $retargetingClient->order->save($info, $products);
            }

            Mage::getSingleton('core/session')->setTriggerSaveOrder($info);
        }
    }
}
