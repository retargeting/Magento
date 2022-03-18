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

        header("Content-Disposition: attachment; filename=retargeting.csv");
        header("Content-type: text/csv; charset=utf-8");

        $result = Mage::getModel('retargeting_tracker/feed')->generateFeed();

        if ($result['status'] !== 'success') {
            echo json_encode($result);
        }
    }
}
