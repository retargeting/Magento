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
    
    /*
    *   TBD
    */
    public function preparePrice(Mage_Catalog_Model_Product $product)
    {
        $price = 0;
        $specialPrice = 0;
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
                ->setPage(1,1)
                ->addFinalPrice()
                ->addTaxPercents()
                ->load()
                ->getFirstItem();

                $price = $helper->getPrice($tmp, $tmp->getMaxPrice(), true);
                $specialPrice = $helper->getPrice($tmp, $tmp->getMinimalPrice(), true);
                if( $price == $specialPrice) {
                  $specialPrice = 0;
                }
                break;
            default:
                $price = $helper->getPrice($product, $product->getPrice());
                $specialPrice = $helper->getPrice($product, $product->getFinalPrice());
                if( $price - $specialPrice > 0 ){
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
