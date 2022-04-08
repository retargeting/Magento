<?php
 
//Load Magento API
$dir = dirname(__FILE__);

require_once $dir.'/app/Mage.php';

if (isset($_GET['CronRTG'])) {
    var_dump(Mage::app()->getConfig()->getNode('crontab/jobs'));
    die();
}

Mage::app();
 
//First we load the model
$model = Mage::getModel('retargeting_tracker/observer');
 
//Then execute the task
$model->feedgen();
?>