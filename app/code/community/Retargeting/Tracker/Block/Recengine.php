<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      EAX LEX SRL <info@eax.ro>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_Block_Recengine extends Mage_Core_Block_Template
{
    private static $rec_engine = array(
        "cms_index_index" => "home_page",
        "checkout_onepage_success" => "thank_you_page", /* Importanta Ordinea */
        "checkout_onepage_index" => "shopping_cart",
        "checkout_cart_index" => "shopping_cart",
        "catalog_category_view" => "category_page",
        "catalog_product_view" => "product_page",
        "catalogsearch_result_index" => "search_page",
        "cms_index_noRoute" => "page_404",
        // "catalog_category_view" => "category_page"
    );
    private static $store = null;

    public function __construct($attributes=array())
    {
        self::$store = Mage::app()->getStore();
        parent::__construct($attributes);
    }

    public static function status() {
        return (bool) self::cfg();
    }

    public static function apistatus() {
        return (bool) Mage::getStoreConfig('retargetingtracker_options/domain/status', self::$store);
    }

    public static function cfg($key = 'rec_status') {
        if ($key === 'rec_status') {
            return Mage::getStoreConfig('retargetingtracker_options/more/'.$key, self::$store);
        }
        //retargetingtracker_options_more_rec_status
        try {
            $value = Mage::getStoreConfig('retargetingtracker_options/rec_data/'.$key, self::$store);
            return Mage::helper('core/unserializeArray')->unserialize($value);
        } catch (Exception $e) {
            Mage::throwException(Mage::helper('adminhtml')->__('Serialized data is incorrect'));
        }
        return null;
    }

    public static function rec_engine_load() {
        if (self::apistatus() && self::status()) {
            $ActionName = Mage::app()->getFrontController()->getAction()->getFullActionName();
            if (isset(self::$rec_engine[$ActionName])) {
                return '
                var _ra_rec_engine = {};
    
                _ra_rec_engine.init = function () {
                    let list = this.list;
                    for (let key in list) {
                        _ra_rec_engine.insert(list[key].value, list[key].selector, list[key].place);
                    }
                };
    
                _ra_rec_engine.insert = function (code = "", selector = null, place = "before") {
                    if (code !== "" && selector !== null) {
                        let newTag = document.createRange().createContextualFragment(code);
                        let content = document.querySelector(selector);
    
                        content.parentNode.insertBefore(newTag, place === "before" ? content : content.nextSibling);
                    }
                };
                _ra_rec_engine.list = '.json_encode(self::cfg(self::$rec_engine[$ActionName])).';
                _ra_rec_engine.init();';
            }
        }

        return "";
    }
    /** @noinspection PhpUnused */
    protected function _toHtml()
    {
        /*
        console.log("'.Mage::app()->getFrontController()->getAction()->getFullActionName().'","RTG")
        */
        return '<script type="text/javascript">'.self::rec_engine_load().'</script>';
    }
}
