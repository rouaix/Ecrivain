<?php

namespace KS\Fields;

class Textarea extends Field {

    /**
     * @return string
     */
    function render() {
        $attrs=$this->attrs;
        $value=$attrs['value'];
        unset($attrs['value'],$attrs['type']);
        return '<textarea '.$this->renderAttributes($attrs).'>'.$value.'</textarea>';
    }

}