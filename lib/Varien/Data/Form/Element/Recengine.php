<?php
/**
 * Form Rec Engine RTG
 * @category    Retargeting
 * @package     Retargeting_Tracker
 * @author      EAX LEX SRL <info@eax.ro>
 */
class Varien_Data_Form_Element_Recengine extends Varien_Data_Form_Element_Abstract
{
    /* TODO: RecEngine */
    private static $RecDef = array(
        "value" => "",
        "selector" => ".main",
        "place" => "after"
    );

    private static $blocks = array(
        'block_1' => array(
            'title' => 'Block 1',
            'def_rtg' => array(
                "value"=>"",
                "selector"=>".main",
                "place"=>"before"
            )
        ),
        'block_2' => array(
            'title' => 'Block 2',
        ),
        'block_3' => array(
            'title' => 'Block 3'
        ),
        'block_4' => array(
            'title' => 'Block 4'
        )
    );

    private static $fields = [
        'home_page' => array(
            'title' => 'Home Page',
        ),
        'category_page' => array(
            'title' => 'Category Page',
        ),
        'product_page' => array(
            'title' => 'Product Page',
        ),
        'shopping_cart' => array(
            'title' => 'Shopping Cart',
        ),
        'thank_you_page' => array(
            'title' => 'Thank you Page',
        ),
        'search_page' => array(
            'title' => 'Search Page',
        ),
        'page_404' => array(
            'title' => 'Page 404',
        )
    ];

    public function __construct($attributes=array())
    {
        parent::__construct($attributes);
    }

    public function getElementHtml()
    {
        $value = $this->getValue();

        preg_match_all('/\[([^\]]+)\]/', $this->getName(), $elm);

        $selected = self::$fields[$elm[1][2]];

        $html = '';

        foreach (self::$blocks as $k=>$v) {
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
                    $value[$k]['value'].'</textarea>'."\n";

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
