<?php
namespace KS;
use Base;

//! Hookable controller
class Hookable {

    /** @var array */
    protected $hooks;

    /**
     * @param Base $f3
     * @param array $params
     * @return bool
     */
    function beforeRoute($f3,$params) {
        foreach($this->hooks[__FUNCTION__] as $hook)
            if (FALSE===$f3->call($hook,[$f3,$params]))
                return FALSE;
        return TRUE;
    }

    /**
     * @param Base $f3
     * @param array $params
     * @return bool
     */
    function afterRoute($f3,$params) {
        foreach($this->hooks[__FUNCTION__] as $hook)
            if (FALSE===$f3->call($hook,[$f3,$params]))
                return FALSE;
        return TRUE;
    }

    //! Constructor
    function __construct() {
        $f3=Base::instance();
        $config=(array)$f3->HOOKABLE;
        foreach(['beforeRoute','afterRoute'] as $k) {
            $this->hooks[$k]=[];
            if (isset($config[$k]))
                $this->hooks[$k]=is_array($config[$k])?$config[$k]:[$config[$k]];
        }
    }

}