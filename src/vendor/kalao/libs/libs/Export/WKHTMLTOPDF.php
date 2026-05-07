<?php

namespace KS\Export;
use Base;

class WKHTMLTOPDF {

    //@{ Error messages
    const
        E_Bin='Cannot access executable (%s)',
        E_Exec='An error occurred during the export.';
    //@}

    /** @var string Output filename */
    public $filename='output.pdf';

    /** @var string */
    public $opts;

    /** @var string HTML data or URL */
    protected $html;

    /** @var string */
    protected $bin;

    /**
     * Output PDF file
     * @param bool $die
     */
    function output($die=TRUE) {
        $f3=Base::instance();
        // Let's store the PDF file to a temporary folder
        $temp=$f3->get('TEMP').'wkhtmltopdf/';
        if (!is_dir($temp))
            mkdir($temp);
        $output=$temp.$this->filename;
        $this->convert($output);
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$this->filename.'"');
        echo $f3->read($output);
        @unlink($output);
        if ($die)
            die();
    }

    /**
     * Convert HTML to a PDF file located in the specified output path
     * @param string $output
     * @return bool
     */
    function convert($output) {
        $f3=Base::instance();
        $tempfile=NULL;
        // Vérifions si on a une URL ou bien une chaîne HTML
        if (preg_match('/^https?:\/\//',$this->html)) {
            $input=$this->html;
        } else {
            // S'il s'agit d'une chaîne, on la sauve dans un fichier temporaire
            $tempfile=$input=preg_replace('/pdf$/','html',$output);
            $f3->write($input,$this->html);
        }
        // Puis on transforme le HTML en PDF
        $cmd=strtr('[bin] [opts] [in] [out] 2>&1',[
            '[bin]'=>$this->bin,
            '[opts]'=>$this->opts,
            '[in]'=>$input,
            '[out]'=>$output,
        ]);
        exec($cmd,$out,$ret);
        if (isset($tempfile))
            @unlink($tempfile);
        if ($ret) {
            // Erreur
            foreach ($out as $lines)
                foreach (preg_split('/\R+/',$lines) as $line)
                    error_log($line);
            user_error(self::E_Exec,E_USER_ERROR);
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Constructor
     * @param string $html
     * @param string $filename
     * @param string $opts
     */
    function __construct($html,$filename=NULL,$opts=NULL) {
        $f3=Base::instance();
        $this->bin=$f3->get('EXPORT.wkhtmltopdf.bin');
        if (!is_executable($this->bin))
            user_error(sprintf(self::E_Bin,$this->bin),E_USER_ERROR);
        $this->html=$html;
        if ($filename)
            $this->filename=$filename;
        $this->opts=trim($f3->get('EXPORT.wkhtmltopdf.opts').' '.$opts);
    }

}