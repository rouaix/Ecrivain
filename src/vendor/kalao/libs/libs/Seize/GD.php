<?php

namespace KS\Seize;
use Base,
    Image;

class GD implements ImageInterface {

    /** @var Image */
    protected $img;

    /** @var bool */
    protected $crop=FALSE;

    /** @var int */
    protected $jpeg_quality=75;

    /** @var int */
    protected $png_quality=6;

    /** @var int */
    protected $png_filters=FALSE;

    /**
     * @param int $width
     * @param int $height
     */
    function resize($width, $height) {
        $this->img->resize($width ?: 10000,$height ?: 10000,!$width || !$height ? FALSE : $this->crop);
    }

    /**
     * @param string $format
     */
    function render($format) {
        if ($format==self::FORMAT_jpeg)
            $this->img->render($format,$this->jpeg_quality);
        elseif ($format==self::FORMAT_png)
            $this->img->render($format,$this->png_quality,$this->png_filters?PNG_ALL_FILTERS:PNG_NO_FILTER);
        else
            $this->img->render($format);
    }

    /**
     * @param string $format
     * @param string $file
     */
    function write($format,$file) {
        $f3=Base::instance();
        if ($format==self::FORMAT_jpeg)
            $str=$this->img->dump($format,$this->jpeg_quality);
        elseif ($format==self::FORMAT_png)
            $str=$this->img->dump($format,$this->png_quality,$this->png_filters?PNG_ALL_FILTERS:PNG_NO_FILTER);
        else
            $str=$this->img->dump($format);
        $f3->write($file,$str);
    }

    /**
     * @param array|string $config
     */
    function readConfig($config) {
        if (!is_array($config))
            $config=[$config];
        foreach ($config as $cmd) {
            if (preg_match('/^(no|)crop$/i',$cmd,$m))
                $this->crop=!$m[1];
            elseif (preg_match('/^jpe?g=(\d+)$/i',$cmd,$m))
                $this->jpeg_quality=(int)$m[1];
            elseif (preg_match('/^png=(\d+)(f?)$/i',$cmd,$m)) {
                $this->png_quality=(int)$m[1];
                $this->png_filters=(bool)$m[2];
            }
        }
    }

    /**
     * @param string $srcfile
     */
    function __construct($srcfile) {
        $this->img=new Image($srcfile);
    }

}