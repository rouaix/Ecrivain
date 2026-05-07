<?php
namespace KS;
use Base,
    Web;

class Media {

    /** @var string */
    protected $src;

    /** @var array */
    protected $noindex;

    /**
     * @param Base $f3
     * @param $params
     */
    function get($f3,$params) {
        $path=str_replace('..','',$params['*']);//necessary??
        if (!is_file($srcfile=$this->src.$path))
            $f3->error(404);
        if (PHP_SAPI!='cli' && in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),$this->noindex))
            header('X-Robots-Tag: noindex, nofollow');
        $web=Web::instance();
        $web->send($srcfile,NULL,0,FALSE);
    }

    //! Constructor
    function __construct() {
        $f3=Base::instance();
        if (is_array($config=$f3->MEDIA))
            foreach($config as $key=>$val)
                if (property_exists($this,$key))
                    $this->$key=$val;
        if ($this->noindex) {
            if (!is_array($this->noindex))
                $this->noindex=[$this->noindex];
            $this->noindex=array_map('strtolower',$this->noindex);
        } else
            $this->noindex=[];
    }

}
