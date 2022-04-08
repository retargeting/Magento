<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_StaticController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        header('Content-Disposition: attachment; filename=retargetig.csv');
        header('Content-type: text/csv; charset=utf-8');

        $result = Mage::getModel('retargeting_tracker/feed')->staticFeed();
    
        if ($result['status'] === 'success' && !$result['generated']) {
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($result['file']));

            readfile($result['file']);

        } else if ($result['status'] === 'readProblem') {
            echo json_encode($result);
        }
    }
}
