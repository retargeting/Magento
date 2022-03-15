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
    private $delete = null;
    
    public function prepareImg($product)
    {
        return Mage::getModel('catalog/product_media_config')->getMediaUrl($product->getImage());
        
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

    private $defStock = 0;

    public function indexAction() {

        $this->defStock = Mage::getStoreConfig('retargetingtracker_options/more/defaultstock') ?? 0;

        ini_set('max_execution_time', 3600);
        ini_set('memory_limit', '8G');
        set_time_limit(0);
        

        header("Content-Disposition: attachment; filename=retargeting.csv");
        header("Content-type: text/csv; charset=utf-8");

        $_productCollection = Mage::getModel('catalog/product')->getCollection();
        $_productCollection->addAttributeToSelect(array('id', 'name', 'url_path', 'image', 'price', 'specialprice','stock','visibility','status'));
        $_productCollection->addFieldToFilter( 'visibility', Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH );
        $_productCollection->addAttributeToFilter( 'status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED );

        $_productCollection->setPageSize(100);

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

            foreach ($_productCollection as $_product) {
                
                $extra_data = [
                    'categories' => [],
                    'media_gallery' => [],
                    'variations' => [],
                    'margin' => null
                ];

                $product = Mage::getModel('catalog/product')->load($_product->getId());

                $imgUrl = $this->prepareImg($product);

                $productURL = $this->buildProductUrl($product->geturlpath());

                $price = $product->getPrice();

                $productQty = $this->getQty($product);
                
                if( "no_selection" === $imgUrl ||
                    empty($productQty) ||
                    empty($imgUrl) ||
                    empty((float) $price) || !filter_var($productURL, FILTER_VALIDATE_URL)){
                    continue;
                }

                if($product->getTypeId() == 'configurable') {
                    $productType = Mage::getModel('catalog/product_type_configurable');
                    $products = $productType->getUsedProducts(null, $product);

                    foreach ($products as $p) {
                        $vPrice = $product->getPrice();
                        if (!empty((float) $vPrice)) {
                            $vFinalPrice = $product->getFinalPrice();
                            $vSalePrice = empty((float) $vFinalPrice) ? $vPrice : $vFinalPrice;

                            $qty = $this->getQty($p);

                            $attr = [
                                'color' => $this->getAttributeText('color', $p),
                                'size'=>$this->getAttributeText('size', $p)
                            ];

                            $attr['code'] = sprintf("%s-%s", $attr['color'], $attr['size']);

                            $extra_data['variations'][] = [
                                'code' => $attr['code'] !== '-' ? $attr['code'] : $p->getId(),
                                'price' => number_format((float) $vPrice, 2, '.', ''),
                                'sale_price' => number_format((float) $vSalePrice, 2, '.', ''),
                                'stock' => $qty,
                                'size' => $attr['size'],
                                'color' => $attr['color']
                            ];
                        }
                    }
                }

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
                    $category = Mage::getModel('catalog/category')->load($categoryId);
                    if (!empty($category->getName())) {
                        $extra_data['categories'][$categoryId] = $category->getName();
                    }
                }

                if (empty($extra_data['categories'])) {
                    $extra_data['categories']['root'] = 'Root';
                }
                
                $finalPrice = $product->getFinalPrice();

                $salePrice = empty((float) $finalPrice) ? $price : $finalPrice;
                
                $brand = '';
                
                fputcsv($outstream, array(
                    'product id' => $product->getId(),
                    'product name' => $product->getName(),
                    'product url' => $productURL,
                    'image url' => $imgUrl,
                    'stock' => $productQty,
                    'price' => number_format($price, 2, '.', ''),
                    'sale price' => number_format($salePrice, 2, '.', ''),
                    'brand' => $brand,
                    'category' => end($extra_data['categories']),
                    'extra data' => json_encode($extra_data, JSON_UNESCAPED_SLASHES)
                ), ',', '"');
            }
            
            $currentPage++;
            $_productCollection->clear();
        } while ($currentPage <= $pages);

    }

    public function getAttributeText($attributeCode, $p)
    {
        if (!$p->getResource()->getAttribute($attributeCode)) { 
            return '';
        }
        return $p->getResource()
            ->getAttribute($attributeCode)
                ->getSource()
                    ->getOptionText($p->getData($attributeCode));
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
            return $this->defStock;
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
