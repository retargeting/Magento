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

    protected function buildImageUrl($path)
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $path;
    }

    protected function buildProductUrl($path)
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . $path;
    }

    public function indexAction()
    {
        // return json_encode(array('products'));
        $storeId = Mage::app()->getStore()->getId();
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();

        $_productCollection = Mage::getModel('catalog/product')->getCollection();
        $_productCollection->addAttributeToSelect(array('id', 'name', 'url_path', 'image', 'price', 'specialprice','stock','image','visibility','status'));
        $_productCollection->addFieldToFilter( 'visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH );
        $_productCollection->addAttributeToFilter( 'status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED );

        $_productCollection->setPageSize(250);

        $pages = $_productCollection->getLastPageNumber();
        $currentPage = 1;

        $outstream = fopen('php://output', 'w');
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
            $extra_data = [
                'categories' => '',
                'media gallery' => [],
                'variations' => [],
                'margin' => null
            ];

            foreach ($_productCollection as $_product) {
                $product = Mage::getModel('catalog/product')->load($_product->getId());

                if($product->getTypeId() == 'configurable') {
                    $productType = Mage::getModel('catalog/product_type_configurable');
                    $products = $productType->getUsedProducts(null, $product);

                    foreach ($products as $p) {
                        $extra_data['variations'][] = [
                            'id' => sprintf("%s-%s", $p->getAttributeText('color'), $p->getAttributeText('size') ),
                            'price' => number_format($product->getPrice(), 2),
                            'sale price' => number_format($product->getFinalPrice(), 2),
                            'stock' => $this->getQty($p),
                            'margin' => null
                        ];
                    }
                }

                if(isset($product->media_gallery['images'])) {
                    foreach ($product->media_gallery['images'] as $img) {
                        if($img['disabled'] != '0') {
                            continue;
                        }
                        $extra_data['media gallery'][] = $this->buildImageUrl($img['file']);
                    }
                }

                $categories = $_product->getCategoryIds();

                foreach($categories as $categoryId) {
                    $category = Mage::getModel('catalog/category')->load($categoryId);
                    $extra_data['categories'] = $category->getName();
                    break;
                }

                fputcsv($outstream, array(
                    'product id' => $_product->getId(),
                    'product name' => $_product->getName(),
                    'product url' => $this->buildProductUrl($_product->geturlpath()),
                    'image url' => $this->buildImageUrl($_product->getImage()),
                    'stock' => $this->getQty($product),
                    'price' => number_format($_product->getPrice(), 2),
                    'sale price' => number_format($_product->getFinalPrice(), 2),
                    'brand' => '',
                    'category' => $category->getName(),
                    'extra data' => json_encode($extra_data, JSON_UNESCAPED_SLASHES)
                ), ',', '"');
            }
            
            $currentPage++;
            $_productCollection->clear();
        } while ($currentPage <= $pages);

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

}

