<?php
namespace KS;
use Prefab,
    IntlDateFormatter,
    NumberFormatter;

class Format extends Prefab {

    //! Non-breaking space
    const NBSP="\xc2\xa0";

    /** @var array */
    protected $formatters=[];

    /** @var bool International date/time format */
    protected $intl;

    /**
     * Formate un prix en tenant compte de la locale
     * @param float $amount
     * @param string $symbol
     * @param int|NULL $decimals (NULL = auto-detect)
     * @param bool $nbsp Non-breaking space
     * @return string
     */
    static function price($amount,$symbol,$decimals=2,$nbsp=TRUE) {
        if ($decimals===NULL && preg_match('/^\-?\d+[\.,](\d+)$/',$amount,$m))
            $decimals=strlen(rtrim($m[1],'0'));
        $conv=localeconv();
        $fmt=[
            0=>'(nc)',1=>'(n c)',
            2=>'(nc)',10=>'+nc',
            11=>'+n c',12=>'+ nc',
            20=>'nc+',21=>'n c+',
            22=>'nc +',30=>'n+c',
            31=>'n +c',32=>'n+ c',
            40=>'nc+',41=>'n c+',
            42=>'nc +',100=>'(cn)',
            101=>'(c n)',102=>'(cn)',
            110=>'+cn',111=>'+c n',
            112=>'+ cn',120=>'cn+',
            121=>'c n+',122=>'cn +',
            130=>'+cn',131=>'+c n',
            132=>'+ cn',140=>'c+n',
            141=>'c+ n',142=>'c +n'
        ];
        if ($amount<0) {
            $sgn=$conv['negative_sign'];
            $pre='n';
        }
        else {
            $sgn=$conv['positive_sign'];
            $pre='p';
        }
        return str_replace(
            ['+','n','c',' '],
            [$sgn,
                number_format(abs($amount),$decimals,$conv['decimal_point'],$conv['thousands_sep']),
                $symbol,
                $nbsp?self::NBSP:' '],
            $fmt[(int)(
                ($conv[$pre.'_cs_precedes']%2).
                ($conv[$pre.'_sign_posn']%5).
                ($conv[$pre.'_sep_by_space']%3)
            )]
        );
    }

    /**
     * Formate un flottant avec un nombre fixe de décimales
     * @param float $value
     * @param int $decimals
     * @param string $decimal_point
     * @param string $thousands_sep
     * @return string
     */
    static function decimal($value,$decimals,$decimal_point=NULL,$thousands_sep=NULL) {
        $conv=localeconv();
        if (!isset($decimal_point))
            $decimal_point=$conv['decimal_point'];
        if (!isset($thousands_sep))
            $thousands_sep=$conv['thousands_sep'];
        return number_format((float)$value,$decimals,$decimal_point,$thousands_sep);
    }

    /**
     * Affiche une fraction sous forme de pourcentage
     * @param float $val
     * @param int $min Min fraction digits
     * @param int $max Max fraction digits
     * @return string
     */
    static function percent($val,$min=0,$max=2) {
        if (class_exists('NumberFormatter')) {
            $loc=setlocale(LC_NUMERIC,0);
            setlocale(LC_NUMERIC,['en_US.UTF-8','en_US.utf8','en_US.utf','en_US']);//workaround for PHP bug #54538 and #53735
            $formatter=new NumberFormatter($loc,NumberFormatter::PERCENT);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS,$min);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS,$max);
            $format=$formatter->format($val);
            setlocale(LC_NUMERIC,$loc);
        } else
            $format=round($val*100,$max).' %';
        return $format;
    }

    /**
     * Round up a positive number to the closest multiple of 5
     * @param float $num
     * @return float
     */
    static function round5($num) {
        return $num>=0?ceil($num/5)*5:ceil($num/-5)*-5;
    }

    /**
     * String excerpt (remove html tags + truncate between words)
     * @param string $str
     * @param int $chars
     * @param string $suffix
     * @return string
     */
    static function excerpt($str,$chars=140,$suffix='...') {
        $str=trim(preg_replace('/\s+/u',' ',strip_tags(str_replace('><','> <',$str))));//hack to replace tags by spaces
        if (mb_strlen($str)>$chars)
            $str=mb_substr($str,0,mb_strrpos(mb_substr($str,0,$chars),' ')).$suffix;
        return $str;
    }

    /**
     * Parse and output filesize
     * @param int|string $bytes
     * @param bool $nbsp Non-breaking space
     * @return string
     */
    static function filesize($bytes,$human=TRUE,$nbsp=TRUE) {
        if (is_string($bytes))
            $bytes=(int)preg_replace_callback('/^(\d+)([a-z])$/i',function($m){
                return $m[1]*pow(1024,strpos('BKMGT',strtoupper($m[2])));
            },$bytes);
        if (!$human)
            return $bytes;
        $s='BKMGT';
        if (preg_match('/fr/',setlocale(LC_NUMERIC,0))) // tentative d'auto-traduction (a priori seul le français traduit B en o)
            $s='okMGT';
        if ($bytes>0) {
            $base=$bytes>0?log($bytes)/log(1024):0;
            return round(pow(1024,$base-floor($base)),2).($nbsp?self::NBSP:' ').
                $s[$i=(int)floor($base)].($i>0?$s[0]:'');
        }
        return '0'.($nbsp?self::NBSP:' ').$s[0];
    }

    /**
     * Affiche une date en tenant compte de la locale
     * @param int $time
     * @param string $pattern
     * @param string $timezone
     * @return string
     */
    function formatDate($time,$pattern=NULL,$timezone=NULL) {
        $fmt=$this->getFormatter('date');
        $backup=[
            'pattern'=>NULL,
            'timezone'=>NULL,
        ];
        if ($pattern) {
            $backup['pattern']=$fmt->getPattern();
            $fmt->setPattern($pattern);
        }
        if ($timezone) {
            $backup['timezone']=$fmt->getTimeZone();
            $fmt->setTimeZone($timezone);
        }
        $out=$fmt->format((int)$time);
        if (isset($backup['timezone']))
            $fmt->setTimeZone($backup['timezone']);
        if (isset($backup['pattern']))
            $fmt->setPattern($backup['pattern']);
        return $out;
    }

    /**
     * Convertit une date au format chaînes de caractères en timestamp UNIX
     * @param string $str
     * @param bool $strict
     * @return bool|int
     */
    function parseDate($str,$strict=FALSE) {
        $fmt=$this->getFormatter('date');
        $t=$fmt->parse($str);
        if ($t!==FALSE && $strict && $fmt->format($t)!==$str)
            return FALSE;
        return $t;
    }

    /**
     * Affiche un horodatage (date + HH:MM) en tenant compte de la locale
     * @param int $time
     * @param string $pattern
     * @param string $timezone
     * @return string
     */
    function formatDateTime($time,$pattern=NULL,$timezone=NULL) {
        $fmt=$this->getFormatter('datetime');
        $backup=[
            'pattern'=>NULL,
            'timezone'=>NULL,
        ];
        if ($pattern) {
            $backup['pattern']=$fmt->getPattern();
            $fmt->setPattern($pattern);
        }
        if ($timezone) {
            $backup['timezone']=$fmt->getTimeZone();
            $fmt->setTimeZone($timezone);
        }
        $out=$fmt->format((int)$time);
        if (isset($backup['timezone']))
            $fmt->setTimeZone($backup['timezone']);
        if (isset($backup['pattern']))
            $fmt->setPattern($backup['pattern']);
        return $out;
    }

    /**
     * Convertit un horodatage (date + HH:MM) au format chaînes de caractères en timestamp UNIX
     * @param string $str
     * @param bool $strict
     * @return bool|int
     */
    function parseDateTime($str,$strict=FALSE) {
        $fmt=$this->getFormatter('datetime');
        $t=$fmt->parse($str);
        if ($t!==FALSE && $strict && $fmt->format($t)!==$str)
            return FALSE;
        return $t;
    }

    /**
     * Affiche un mois en tenant compte de la locale (format court ou long)
     * @param int $m
     * @param bool $full
     * @return string
     */
    function formatMonth($m,$full=FALSE) {
        return $this->formatDate(mktime(0,0,0,$m,1,1970),$full?'MMMM':'MMM');
    }

    /**
     * Returns the IntlDateFormatter instance
     * @param $name (date|datetime)
     * @return IntlDateFormatter
     */
    protected function getFormatter($name) {
        if (!isset($this->formatters[$name])) {
            //$loc=preg_replace('/\..*$/','',setlocale(LC_ALL,0));
            $loc=explode('.',setlocale(LC_ALL,0))[0];
            $this->formatters[$name]=new IntlDateFormatter($loc,IntlDateFormatter::SHORT,IntlDateFormatter::NONE);
            $this->formatters[$name]->setLenient(FALSE);
            if ($this->intl) {
                $this->formatters[$name]->setPattern('dd/MM/yyyy');
                if ($name=='datetime')
                    $this->formatters[$name]->setPattern($this->formatters[$name]->getPattern().' HH:mm');
            }
        }
        return $this->formatters[$name];
    }

    /**
     * Constructor
     * @param bool $intl
     */
    function __construct($intl=TRUE) {
        $this->intl=$intl;
    }

}