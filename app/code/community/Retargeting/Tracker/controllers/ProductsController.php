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
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '
		<products>';

		$params = $this->getRequest()->getParams();

        if ( isset($params['key']) && $params['key'] != '' && $params['key'] == Mage::getStoreConfig('retargetingtracker_options/token/token') ) {

			$collection = Mage::getResourceModel('catalog/product_collection');
			foreach($collection as $product) {
				$product = Mage::getModel('catalog/product')->load($product->getId());
				$product_price = Mage::helper('tax')->getPrice($product, $product->getPrice());
				$product_promo = ( Mage::helper('tax')->getPrice($product, $product->getPrice()) - Mage::helper('tax')->getPrice($product, $product->getFinalPrice()) > 0 ? Mage::helper('tax')->getPrice($product, $product->getFinalPrice()) : 0 );
				$product_image = ( $product->getThumbnail() != 'no_selection' ? htmlspecialchars(Mage::helper('catalog/image')->init($product, 'thumbnail')) : htmlspecialchars(Mage::helper('catalog/image')->init($product, 'image')->resize(500)) );
				$product_url = $product->getProductUrl();
				echo '
			<product>
				<id>'.$product->getId().'</id>
				<stock>'.$product->getIsInStock().'</stock>
				<price>'.$product_price.'</price>
				<promo>'.$product_promo.'</promo>
				<url>'.$product_url.'</url>
				<image>'.$product_image.'</image>
			</product>';
			}
		
		}

		echo '
		</products>';
    }
}