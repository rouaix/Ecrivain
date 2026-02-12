<?php

namespace KS\Fields;

class File extends Field {

    /**
     * @return string
     */
    function render() {
        return '<input '.$this->renderAttributes(['value'=>'']+$this->attrs).'/>';
    }

}