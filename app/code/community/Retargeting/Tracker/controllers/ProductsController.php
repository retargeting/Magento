<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_ProductsController extends Mage_Core_Controller_Front_Action
{
    public function indexAction() {
    
    			$productCollection = Mage::getModel('catalog/product')->getCollection();
          $productCollection->addAttributeToSelect(array('name', 'price', 'special_price'));
          $retargetingFeed = array();
          
    			foreach($productCollection as $product) {
            
            $retargetingFeed[] = array(
                'id' => $product->getId(),
                'price' => (double)$product->getPrice(),
                'promo' =>( $product->getPrice() - $product->getFinalPrice() > 0 ) ?  (double)$product->getFinalPrice() : 0,
                'promo_price_end_date' => $product->getSpecialToDate(),
                'inventory' => array(
                    'variations' => false, // nu am gasit o solutie finala
                    'stock' => (bool)$product->getStockItem()->getIsInStock()
                ),
                'user_groups' => false,
                'product_availability' => null
            );
    			}
          
    $data = json_encode($retargetingFeed, JSON_PRETTY_PRINT);
    
    return $this->getResponse()
    ->setHeader('Content-Type', 'application/json', 1)
    ->setBody($data);
    
    }
}
