<?php

namespace KS\Fields;

class Text extends Field {//todo: décider si on garde ou on remplace par une classe générique "input"

    /**
     * @return string
     */
    function render() {
        return '<input '.$this->renderAttributes($this->attrs).'/>';
    }

}