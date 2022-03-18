<?php
/**
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      Retargeting <info@retargeting.biz>
 * @copyright   Copyright (c) Retargeting
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Retargeting_Tracker_Model_Defaultstock
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $pages = array(
            array(
                'value' => 1,
                'label' => 'In Stock'
            ),
            array(
                'value' => 0,
                'label' => 'Out Stock'
            )
        );
        
        return $pages;
    }
}
