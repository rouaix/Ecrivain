<?php

namespace KS;
use Base,
    ReflectionClass;

class Seize {

    /** @var string */
    protected $src;

    /** @var string */
    protected $dest;

    /** @var array */
    protected $default;

    /** @var array */
    protected $formats=[];

    /**
     * Return TRUE if the plugin supports the given file path
     * @param string $path
     * @return bool
     */
    static function supports($path) {
        return (bool)preg_match('/(gif|jfif?|jpe?g|png)$/i',$path);
    }

    /**
     * @param Base $f3
     * @param $params
     */
    function get($f3,$params) {
        $path=str_replace('..','',$params['*']);//necessary??
        if (!is_file($srcfile=$this->src.$path))
            $f3->error(404);
        $ext=preg_replace('/^(jpg|jfif?)$/','jpeg',strtolower(pathinfo($srcfile,PATHINFO_EXTENSION)));
        if (!in_array($ext,$f3->constants(Seize\ImageInterface::class,'FORMAT_')))
            $f3->error(404);
        $img=class_exists('Imagick') ?
            new Seize\Magick($srcfile) :
            new Seize\GD($srcfile);
        if (isset($this->default[$k=strtolower((new ReflectionClass($img))->getShortName())]))
            $img->readConfig($this->default[$k]);
        $width=$height=0;
        if ($dims=$this->parseDimensions($format=$params['format']))
            list($width,$height)=$dims;
        elseif (array_key_exists($format,$this->formats)) {
            if ($dims=$this->parseDimensions($this->formats[$format]))
                list($width,$height)=$dims;
            $img->readConfig($this->formats[$format]);
        } else
            $f3->error(404);
        if ($width || $height) {
            $img->resize($width,$height);
            if (isset($this->dest)) {
                if (!is_dir($dir=dirname($destfile=$this->dest.$format.'/'.$path)))
                    @mkdir($dir,0775,TRUE);
                $img->write($ext,$destfile);
            }
        }
        $img->render($ext);
    }

    /**
     * Remove all the thumbnails of a given file
     * @param $file
     */
    function rmfile($file) {
        if (isset($this->dest) && is_dir($this->dest))
            foreach (array_diff(scandir($this->dest),['.','..']) as $dir)
                @unlink($this->dest.$dir.'/'.$file);
    }

    /**
     * Parse config dimensions (12x34 style)
     * @param array|string $input
     * @return array|FALSE
     */
    protected function parseDimensions($input) {
        if (!is_array($input))
            $input=[$input];
        foreach ($input as $cmd)
            if (preg_match('/^(\d+)x(\d+)$/i',$cmd,$m))
                return [(int)$m[1],(int)$m[2]];
        return FALSE;
    }

    //! Constructor
    function __construct() {
        $f3=Base::instance();
        if (is_array($config=$f3->SEIZE))
            foreach ($config as $key=>$val)
                if (property_exists($this,$key))
                    $this->$key=$val;
    }

}