<?php

namespace KS\Fields;
use Base,
    Template;

class Select extends Field {

    /**
     * @return string
     */
    function render() {
        $attrs=$this->attrs;
        $value=$attrs['value'];
        $options=$attrs['options'];
        unset($attrs['value'],$attrs['options'],$attrs['type']);
        return '<select '.$this->renderAttributes($attrs).'>'.$this->renderOptions(
                preg_match('/\{\{(.+?)\}\}/',$options)?
                    Template::instance()->token($options):$options,
                preg_match('/\{\{(.+?)\}\}/',$value)?
                    Template::instance()->token($value):Base::instance()->stringify($value)).
            '</select>';
    }

    /**
     * Render options and groups
     * @param array $options
     * @param string $value
     * @return string
     */
    protected function renderOptions($options,$value) {
        $code='<?php $renderOptions=function($o,$d)use(&$renderOptions){'.
            '$html=\'\';'.
            'foreach($o as $k=>$v) '.
            '$html.=is_array($v)?'.
                '\'<optgroup label="\'.$k.\'">\'.$renderOptions($v,$d).\'</optgroup>\':'.
                '\'<option value="\'.$k.\'"\'.(($k===0?$k===$d:$k==$d)?\' selected="selected"\':\'\').\'>\'.$v.\'</option>\''.
            ';return $html;'.
            '};';
        $code.='echo $renderOptions('.$options.','.$value.');unset($renderOptions);?>';
        return $code;
    }

}
