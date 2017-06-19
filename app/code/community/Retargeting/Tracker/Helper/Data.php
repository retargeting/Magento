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
    *   Formats the price to have 2 decimals
    */
    protected function formatter($price)
    {
        return number_format($price, 2);
    }
    
    /*
    *   TBD
    */
    public function preparePrice(Mage_Catalog_Model_Product $product)
    {
        $price = 0;
        switch ($product->getTypeId()) {
            case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE:
                $bundlePrices = $product->getPriceModel();
                $specialPrice = $bundlePrices->getTotalPrices($product, 'min', true);
                $maxPrice = $bundlePrices->getTotalPrices($product, 'max', true);
                break;
            case Mage_Catalog_Model_Product_Type::TYPE_GROUPED:
                $typeInstance = $product->getTypeInstance();
                $associatedProducts = $typeInstance->setStoreFilter($product->getStore(),
                    $product)->getAssociatedProducts($product);
                $cheapestAssociatedProduct = null;
                $minimalPrice = 0;
                foreach ($associatedProducts as $associatedProduct) {
                    $temp = $associatedProduct->getSpecialPrice();
                    if ($minimalPrice === 0 || $minimalPrice > $temp) {
                        $minimalPrice = $temp;
                        $cheapestAssociatedProduct = $associatedProduct;
                    }
                }
                $specialPrice = $minimalPrice;
                if ($cheapestAssociatedProduct) {
                    $helper = Mage::helper('tax');
                    $specialPrice = $helper->getPrice($cheapestAssociatedProduct, $specialPrice, true);  
                }
                // Trebuie sa gasesc o metoda prin care sa iau cel mai mare base price al produsului
                // de pus intr-un branch separat
            default:
                $price = 'pretdefault';
                break;  
        }
        return $price;
    }

}