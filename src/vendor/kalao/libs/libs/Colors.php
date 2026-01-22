<?php
namespace KS;

class Colors {

    /**
     * cf. https://24ways.org/2010/calculating-color-contrast/
     * @param string $hexcolor
     * @return bool
     */
    static function getContrast50($hexcolor){
        return hexdec($hexcolor) > 0xffffff/2;
    }

    /**
     * cf. https://24ways.org/2010/calculating-color-contrast/
     * @param string $hexcolor
     * @return bool
     */
    static function getContrastYIQ($hexcolor){
        $r = hexdec(substr($hexcolor,0,2));
        $g = hexdec(substr($hexcolor,2,2));
        $b = hexdec(substr($hexcolor,4,2));
        $yiq = (($r*299)+($g*587)+($b*114))/1000;
        return $yiq >= 128;
    }

}