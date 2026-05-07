<?php

namespace KS\Seize;

interface ImageInterface {

    //! Image formats
    const
        FORMAT_gif='gif',
        FORMAT_jpeg='jpeg',
        FORMAT_png='png';

    /**
     * @param int $width
     * @param int $height
     */
    function resize($width,$height);

    /**
     * @param string $format
     */
    function render($format);

    /**
     * @param string $format
     * @param string $file
     */
    function write($format,$file);

    /**
     * @param array|string $config
     */
    function readConfig($config);

    /*
     * @param string $srcfile
     */
    function __construct($srcfile);

}