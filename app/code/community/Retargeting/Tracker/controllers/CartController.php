<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once 'Mage/Checkout/controllers/CartController.php';

class Retargeting_Tracker_CartController extends Mage_Checkout_CartController
{
    /**
     * @return Mage_Core_Controller_Varien_Action|void
     */
    public function indexAction()
    {
        return $this->_redirectReferer();
    }

    /**
     * @return Mage_Core_Controller_Varien_Action|void
     */
    public function addAction()
    {
        $params = $this->getRequest()->getParams();
        $productId = $params['productId'];

        if (!isset($productId)) {
            return $this->_redirectReferer();
        }

        try {
            $cart = $this->_getCart();
            $product = Mage::getModel('catalog/product')->load($productId);

            if (!$product->getId()) {
                $this->_getSession()->addError('The product does not exist.');
                $this->_redirect('/');
                return;
            }

            if (!$product->getTypeInstance() instanceof Mage_Catalog_Model_Product_Type_Simple) {
                $this->_getSession()->addNotice('Please select an option.');
                $this->_redirectUrl($product->getProductUrl());
            } else {
                $cart->addProduct($productId);
                $cart->save();
                $this->_getSession()->setCartWasUpdated(true);

                Mage::dispatchEvent('checkout_cart_add_product_complete', [
                    'product' => $product,
                    'request' => $this->getRequest(),
                    'response' => $this->getResponse()
                ]);

                if (!$this->_getSession()->getNoCartRedirect(true)) {
                    if (!$cart->getQuote()->getHasError()) {
                        $message = $this->__(
                            '%s was added to your shopping cart.',
                            Mage::helper('core')->escapeHtml($product->getName())
                        );
                        $this->_getSession()->addSuccess($message);
                    }
                    $this->_goBack();
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
