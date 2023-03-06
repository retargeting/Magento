<?php
/**
 * Form Rec Engine RTG
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      EAX LEX SRL <info@eax.ro>
 */
class Retargeting_Tracker_Block_Adminhtml_Form_Element_Recengine extends Varien_Data_Form_Element_Abstract
{
    /* TODO: RecEngine */
    private static $RecDef = array(
        "value" => "",
        "selector" => ".main",
        "place" => "after"
    );

    private static $fields = [
        'home_page' => array(
            'title' => 'Home Page',
            'child' => array(
                'recommended_for_you' => array(
                    'title' => 'Recommended for you',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'bestseller' => array(
                    'title' => 'Bestseller'
                ),
                'live_feed' => array(
                    'title' => 'Live Feed'
                )
            )
        ),
        'category_page' => array(
            'title' => 'Category Page',
            'child' => array(
                'recommended_for_you' => array(
                    'title' => 'Recommended for you',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'recently_viewed' => array(
                    'title' => 'Recently viewed'
                )
            )
        ),
        'product_page' => array(
            'title' => 'Product Page',
            'child' => array(
                'frequently_bought_together' => array(
                    'title' => 'Frequently bought together',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'others_you_may_like' => array(
                    'title' => 'Others you may like'
                ),
                'recently_viewed' => array(
                    'title' => 'Recently viewed'
                )
            )
        ),
        'shopping_cart' => array(
            'title' => 'Shopping Cart',
            'child' => array(
                'frequently_bought_together' => array(
                    'title' => 'Frequently bought together',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'recommended_for_you' => array(
                    'title' => 'Recommended for you'
                )
            )
        ),
        'thank_you_page' => array(
            'title' => 'Thank you Page',
            'child' => array(
                'frequently_bought_together' => array(
                    'title' => 'Frequently bought together',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'bestseller' => array(
                    'title' => 'Bestseller'
                )
            )
        ),
        'search_page' => array(
            'title' => 'Search Page',
            'child' => array(
                'recommended_for_you' => array(
                    'title' => 'Recommended for you',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'recently_viewed' => array(
                    'title' => 'Recently viewed'
                )
            )
        ),
        'page_404' => array(
            'title' => 'Page 404',
            'child' => array(
                'recommended_for_you' => array(
                    'title' => 'Recommended for you',
                    'def_rtg' => array(
                        "value"=>"",
                        "selector"=>".main",
                        "place"=>"before"
                    )
                ),
                'bestseller' => array(
                    'title' => 'Bestseller'
                ),
                'new_arrivals' => array(
                    'title' => 'New Arrivals'
                ),
                'recently_viewed' => array(
                    'title' => 'Recently viewed'
                )
            )
        )
    ];

    public function __construct($attributes=array())
    {
        parent::__construct($attributes);
        $this->setType('Recengine');
    }

    public function getElementHtml()
    {
        $value = $this->getValue();

        preg_match_all('/\[([^\]]+)\]/', $this->getName(), $elm);

        $selected = self::$fields[$elm[1][2]];

        $html = '';

        foreach ($selected['child'] as $k=>$v) {
            if (empty($value[$k]['value']) && empty($value[$k]['selector'])) {
                $def = isset($v['def_rtg']) ?
                    $v['def_rtg'] : (isset($selected['def_rtg']) ? $selected['def_rtg'] : null);

                $value[$k] = $def !== null ? $def : self::$RecDef;
            }

            $html .= '<label for="'.$this->getHtmlId().'_'.$k.'">
            <strong>'.$v['title'].'</strong>
        </label>';
            $html .= '<textarea style="min-width: 50%; height: 75px;"'.
                    ' id="'.$this->getHtmlId().'_'.$k.'" name="'.$this->getName().'['.$k.'][value]" spellcheck="false">'.
                    $value[$k]['value'].'676</textarea>'."\n";

            $html .= '<p><span><strong>'.
            '<a href="javascript:void(0);" onclick="document.querySelectorAll(\'#'.$this->getHtmlId().
            '_advace\').forEach((e)=>{e.style.display=e.style.display===\'none\'?\'block\':\'none\';});">'.
            'Show/Hide Advance</a></strong></span></p>';

            $html .= '<span id="'.$this->getHtmlId().'_advace" style="display:none" >'.
                    '<input style="max-width: 200px;" class="input-text"'.
                    ' id="'.$this->getHtmlId().'" type="text" name="'.$this->getName().'['.$k.'][selector]" '.
                    'value="'.$value[$k]['selector'].'" />'."\n";

            $html .= '<select style="max-width: 74px;min-height: 20px" id="'.$this->getHtmlId().'" name="'.$this->getName().'['.$k.'][place]">'."\n";

            foreach (['before', 'after'] as $v)
            {
                $html .= '<option value="'.$v.'"'.($value[$k]['place'] === $v ? ' selected="selected"' : '' );
                $html .= '>'.$v.'</option>'."\n";  
            }

            $html .= '</select></span><br />'."\n";
        }

        
        $html .= $this->getAfterElementHtml();
        return $html;
    }

    public function getHtmlAttributes()
    {
        return array('title', 'class', 'style', 'onclick', 'onchange', 'disabled', 'readonly', 'tabindex');
    }
}
