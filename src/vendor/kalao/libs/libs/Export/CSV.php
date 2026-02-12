<?php

namespace KS\Export;
use Base;

class CSV {

    /** @var string Field separator */
    public $sep=',';

    /** @var string End of line character */
    public $eol="\r\n";

    /** @var array Data rows */
    protected $rows=[];

    /**
     * Output CSV file
     * @param string $filename
     * @param bool $die
     */
    function output($filename='output.csv',$die=TRUE) {
        header('Content-type: application/csv; charset='.Base::instance()->get('ENCODING'));
        header('Content-Disposition: attachment; filename='.$filename);
        foreach($this->rows as $row)
            echo implode($this->sep,array_map(function($cell){return '"'.str_replace('"','""',$cell).'"';},$row)).$this->eol;
        if ($die)
            die();
    }

    /**
     * Constructor
     * @param array $rows
     * @param array $headers
     */
    function __construct($rows,$headers=[]) {
        if ($headers)
            $rows=array_merge([$headers],$rows);
        $this->rows=$rows;
    }

}