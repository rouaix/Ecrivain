<?php

namespace KS\Fields;

abstract class Field {

    /** @var array */
    protected $attrs=[];

    /**
     * Render HTML attributes
     * @param array $attrs
     * @return string
     */
    protected function renderAttributes($attrs) {
        return implode(' ',array_map(function($k,$v){
            return $k.'="'.$v.'"';
        },array_keys($attrs),$attrs));
    }

    /**
     * @param array $attrs
     */
    function __construct($attrs) {
        $this->attrs=$attrs;
    }

}