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

}