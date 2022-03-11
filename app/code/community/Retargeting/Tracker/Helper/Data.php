<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_Helper_Data extends Mage_Core_Helper_Abstract
{
    /*
     * Config paths for using throughout the code
     * */

    const XML_PATH_FACEBOOK = 'retargetingtracker_options/more/facebook';
    const XML_PATH_IMAGECLASS = 'retargetingtracker_options/more/css';

    /**
     * @return string
     * */
    public static function getVersion()
    {
        return Mage::getConfig()->getNode('modules/Retargeting_Tracker/version');
    }
    
    /*
     * Check if FB is ready to use
     *
     * @param mixed $store
     * @return bool
     * */

    public function isFacebookAvailable($store = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_FACEBOOK, $store);
    }

    /*
    * Custom CSS CLASS
    *
    * @param mixed $store
    * @return bool
    * */
    public function getCustomClass($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_IMAGECLASS, $store);
    }

    public function checkStock(Mage_Catalog_Model_Product $product)
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

        return $qty;
    }


    protected function getMinQty(array $productCollection)
    {
        $quantities = array();
        $minQty = 0;
        /* @var Mage_Catalog_Model_Product $product */
        foreach ($productCollection as $product) {
            $quantities[] = $this->checkStock($product);
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
            $qty += $this->checkStock($product);
        }

        return $qty;
    }
    
    private $delete = null;
    public function getFromCache($imgUrl = null)
    {
        if ($this->delete === null) {
            $start = false;
            $count = 0;
            $this->delete = '';
            foreach (explode("/",$imgUrl) as $k => $v) {
                if ($v === "cache") {
                    $start = true;
                }
                if ($start) {
                    $count++;
                    if ($count <= 5) {
                        $this->delete .= '/'.$v;
                    }
                }
            }
        }
        return str_replace($this->delete, "", $imgUrl);
    }

    private $delete = null;
    public function getFromCache($imgUrl = null)
    {
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

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function preparePrice(Mage_Catalog_Model_Product $product)
    {
        $helper = Mage::helper('tax');
        switch ($product->getTypeId()) {
            case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                $bundlePrices = $product->getPriceModel();
                $price = $bundlePrices->getTotalPrices($product, 'max', true);
                $specialPrice = $bundlePrices->getTotalPrices($product, 'min', true);
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                $conf = Mage::getSingleton('catalog/config');
                $tmp = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect(
                        $conf->getProductAttributes()
                    )
                    ->addAttributeToFilter('entity_id', $product->getId())
                    ->setPage(1, 1)
                    ->addFinalPrice()
                    ->addTaxPercents()
                    ->load()
                    ->getFirstItem();

                $price = $helper->getPrice($tmp, $tmp->getMaxPrice(), true);
                $specialPrice = $helper->getPrice($tmp, $tmp->getMinimalPrice(), true);
                if ($price == $specialPrice) {
                    $specialPrice = 0;
                }
                break;
            default:
                $price = $helper->getPrice($product, $product->getPrice());
                $specialPrice = $helper->getPrice($product, $product->getFinalPrice());
                if ($price - $specialPrice > 0) {
                    $specialPrice = $helper->getPrice($product, $product->getFinalPrice());
                } else {
                    $specialPrice = 0;
                }
                break;
        }

        return array(
            'price' => $price,
            'promo' => $specialPrice
        );
    }
}
