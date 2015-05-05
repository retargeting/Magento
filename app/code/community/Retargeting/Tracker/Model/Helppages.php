<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_Model_Helppages
{
    public function toOptionArray()
    {
        $pageCollection = Mage::getModel('cms/page')->getCollection();
        $pages = array();
        foreach ($pageCollection as $page) {
            $pages[] = array('value'=>$page->getId(), 'label'=>Mage::helper('retargeting_tracker')->__($page->getTitle()));
        }
        return $pages;
    }

}