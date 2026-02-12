<?php

namespace KS\Fields;

class Password extends Field {

    /**
     * @return string
     */
    function render() {
        $this->attrs['value']='';
        return '<input '.$this->renderAttributes($this->attrs).'/>';
    }

}