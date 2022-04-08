<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_IndexController extends Mage_Core_Controller_Front_Action
{
    /**
     * @return Zend_Controller_Response_Abstract
     */
    public function indexAction()
    {
        $response = array(
            'status' => true,
            'data' => 'Retargeting Tracker Version: ' . \Retargeting_Tracker_Helper_Data::getVersion()
        );

        return $this->getResponse()
            ->setHeader('Content-Type', 'application/json', 1)
            ->setBody(json_encode($response));
    }
}
