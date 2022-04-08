<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_Model_Stores
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $pages = array();
        
        foreach (Mage::app()->getWebsites() as $website) {
            $pages[] = array(
                'value' => $website->getId(),
                'label' => $website->getName()
            );
        }

        return $pages;
    }
}
