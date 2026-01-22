<?php

namespace KS\Seize;
use Base,
    Imagick;

class Magick implements ImageInterface {

    /** @var Imagick */
    protected $img;

    /** @var bool */
    protected $crop=FALSE;

    /** @var int */
    protected $filter=Imagick::FILTER_LANCZOS;

    /** @var float */
    protected $blur=1.0;

    /** @var int */
    protected $jpeg_compression;

    /** @var int */
    protected $png_compression;

    /**
     * @param int $width
     * @param int $height
     */
    function resize($width, $height) {
        $crop=NULL;
        if ($width && $height && $this->crop) {
            $ratio=$this->img->getImageWidth()/$this->img->getImageHeight()*$height/$width;
            if ($ratio>1) {// image too large
                $newwidth=(int)ceil($width*$ratio);
                if ($newwidth>$width) {
                    $crop=[$width,$height,(int)(0.5*($newwidth-$width)),0];
                    $width=$newwidth;
                }
            } elseif ($ratio<1) {// image too high
                $newheight=(int)ceil($height/$ratio);
                if ($newheight>$height) {
                    $crop=[$width,$height,0,(int)(0.5*($newheight-$height))];
                    $height=$newheight;
                }
            }
        }
        $this->img->resizeImage($width ?: 10000,$height ?: 10000,$this->filter,$this->blur,TRUE);
        if ($crop)
            call_user_func_array([$this->img,'cropImage'],$crop);
    }

    /**
     * @param string $format
     */
    function render($format) {
        //$this->img->setFormat($format==self::FORMAT_png?'png24':$format);
        $compression=property_exists($this,$k=$format.'_compression')?$this->$k:NULL;
        if (isset($compression))
            $this->img->setImageCompressionQuality($compression);
        header('Content-Type: image/'.$format);
        echo $this->img->getImageBlob();
    }

    /**
     * @param string $format
     * @param string $file
     */
    function write($format,$file) {
        $f3=Base::instance();
        //$this->img->setFormat($format==self::FORMAT_png?'png24':$format);
        $compression=property_exists($this,$k=$format.'_compression')?$this->$k:NULL;
        if (isset($compression))
            $this->img->setImageCompressionQuality($compression);
        $f3->write($file,$this->img->getImageBlob());
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
            elseif (preg_match('/^filter=(\w+)$/i',$cmd,$m)) {
                if (defined($constant='Imagick::FILTER_'.strtoupper($m[1])))
                    $this->filter=constant($constant);
            } elseif (preg_match('/^blur=([0-9\.]+)$/i',$cmd,$m))
                $this->blur=(float)$m[1];
            elseif (preg_match('/^jpe?g=(\d+)$/i',$cmd,$m))
                $this->jpeg_compression=(int)$m[1];
            elseif (preg_match('/^png=(\d+)(f?)$/i',$cmd,$m)) {
                $this->png_compression=(int)$m[1];
            }
        }
    }

    /**
     * @param string $srcfile
     */
    function __construct($srcfile) {
        $this->img=new Imagick($srcfile);
    }

}