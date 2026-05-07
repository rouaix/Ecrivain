<?php
namespace KS;
use Base,
    Audit;

class Validator {

    const PATH_SEPARATOR=':';

    //@{ Error messages
    const
        E_Rule='Rule `%s` cannot be found';
    //@}

    /** @var array */
    public $input;

    /** @var array */
    public $ouput;

    /** @var array */
    public $errors;

    /** @var array Custom rules */
    protected $rules=[
        'plain'=>[self::class,'plain'],
        'rich'=>[self::class,'rich'],
        'int'=>[self::class,'int'],
        'decimal'=>[self::class,'decimal'],
        'reqd'=>[self::class,'reqd'],
        'isFile'=>[self::class,'isFile'],
        'isEmail'=>[self::class,'isEmail'],
        'isUrl'=>[self::class,'isUrl'],
        'inArray'=>[self::class,'inArray'],
    ];

    /**
     * @param array $input
     */
    function __construct($input=NULL) {
        $f3=Base::instance();
        if (isset($input))
            $this->input=$input;
        $this->output=$this->errors=[];
        $config=(array)$f3->get('VALIDATOR');
        if (isset($config['rules']))
            foreach((array)$config['rules'] as $rule=>$callback)
                $this->addRule($rule,$callback);
    }

    /**
     * Validate the whole input array against a set of rules
     * @param array $rules
     * @return bool
     */
    function validate($rules) {
        $res=TRUE;
        foreach($rules as $field=>$frules) {
            $field=strtr($field,['['=>self::PATH_SEPARATOR,']'=>'']);// convert foo[bar] into foo:bar notation
            $out=&$this->ref('output',$field);
            $out=$this->ref('input',$field,FALSE);
            if (!$this->validateField($field,$frules))
                $res=FALSE;
        }
        return $res;
    }

    /**
     * Get a reference to the variable matching the given path
     * e.g: ref('output','foo:bar') should point to $this->output['foo']['bar']
     * @param $var
     * @param $path
     * @param bool|TRUE $add
     * @return null
     */
    protected function &ref($var,$path,$add=TRUE) {
        $null=NULL;
        if ($add)
            $var=&$this->$var;
        else
            $var=$this->$var;
        foreach(explode(self::PATH_SEPARATOR,$path) as $i=>$k)
            if ($add || is_array($var) && array_key_exists($k,$var))
                $var=&$var[$k];
            else {
                $var=&$null;
                break;
            }
        return $var;
    }

    /**
     * Validate a field value against a set of rules
     * @param string $field
     * @param array $frules
     * @return bool
     */
    protected function validateField($field,$frules) {
        if (!is_array($frules))
            $frules=[$frules];
        $out=&$this->ref('output',$field);
        foreach($frules as $k=>$v) {
            $hasArg=is_string($k);
            list($rule,$arg)=$hasArg?[$k,$v]:[$v,NULL];
            if ($rule=='each') {
                $nlevel=1;// nesting level
                if ($nrules=(array)$arg) {
                    list($v1,$k1)=[reset($nrules),key($nrules)];
                    if (is_array($v1) && !array_key_exists($k1,$this->rules))
                        $nlevel=2;
                }
                $out=(array)$out;
                $res=TRUE;
                foreach($out as $i=>$data) {
                    $v=clone($this);
                    $v->input=$nlevel==1?['dummy'=>$data]:$data;
                    $v->output=$v->errors=[];
                    if (!$v->validate($nlevel==1?['dummy'=>$nrules]:$nrules)) {
                        foreach($v->errors as $key=>$e) {
                            $path=$field.PATH_SEPARATOR.$i;
                            if ($nlevel>1)
                                $path.=PATH_SEPARATOR.$key;
                            $this->setError($path,$e['rule']);
                        }
                        $res=FALSE;
                    }
                    $out[$i]=$nlevel>1?$v->output:array_shift($v->output);
                }
                if (!$res)
                    return FALSE;
            } elseif ($rule) {
                if (!isset($this->rules[$rule]) || !is_callable($this->rules[$rule]))
                    user_error(sprintf(self::E_Rule,$rule),E_USER_ERROR);
                if (FALSE===call_user_func_array($this->rules[$rule],
                        array_merge([&$out],$hasArg?[$arg]:[]))) {
                    $this->setError($field,$rule);
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    /**
     * Define a custom rule
     * @param string $rule
     * @param callable $callback
     */
    function addRule($rule,$callback) {
        $this->rules[$rule]=$callback;
    }

    /**
     * Get error for a given field path
     * @param string $path
     * @return array|NULL
     */
    function getError($path) {
        return @$this->errors[$path];
    }

    /**
     * Set error for a given field path
     * @param string $path
     * @param string $rule
     */
    function setError($path,$rule) {
        $this->errors[$path]=['path'=>$path,'rule'=>$rule];
    }

    /**
     * Clear error for a given field path
     * @param string $path
     */
    function clearError($path) {
        unset($this->errors[$path]);
    }

    /**
     * Plain text
     * @param mixed &$val
     * @param bool $nullable
     */
    function plain(&$val,$nullable=TRUE) {
        $val=Base::instance()->clean($val);
        if ($nullable && strlen($val)===0)
            $val=NULL;
    }

    /**
     * Rich text
     * @param mixed &$val
     * @param string $tags
     */
    function rich(&$val,$tags='*') {
        $regex='\s*<(p|div)>\s*<br\/?>\s*<\/\1>\s*';// trim surrounding empty paragraphs or divs
        $val=preg_replace('/^'.$regex.'/','',preg_replace('/'.$regex.'$/','',Base::instance()->clean($val,$tags)));
    }

    /**
     * Integer
     * @param mixed $val
     * @param string $range
     * @return NULL|FALSE
     */
    function int(&$val,$range=NULL) {
        $val=Base::instance()->clean($val);
        if (preg_match('/^(\-?\d+)[,\.]?\d*$/',$val,$m))
            $val=(int)$m[1];
        else
            $val=NULL;
        if (isset($val,$range) && preg_match_all('/\b(min|max)=([0-9\-]+)/',$range,$matches,PREG_SET_ORDER)) {
            foreach($matches as $m)
                if ($m[1]=='min' && $val<(int)$m[2] || $m[1]=='max' && $val>(int)$m[2])
                    return FALSE;
        }
    }

    /**
     * Decimal
     * @param mixed $val
     * @param string $range
     * @return NULL|FALSE
     */
    function decimal(&$val,$range=NULL) {
        $val=Base::instance()->clean($val);
        if (preg_match('/^\-?\d+[\.,]?\d*$/',$val))
            $val=(float)str_replace(',','.',$val);
        else
            $val=NULL;
        if (isset($val,$range) && preg_match_all('/\b(precision|min|max)=([0-9\.\-]+)/',$range,$matches,PREG_SET_ORDER)) {
            foreach($matches as $m) {
                if ($m[1]=='precision')
                    $val=round($val,(int)$m[2]);
                if ($m[1]=='min' && $val<(float)$m[2] || $m[1]=='max' && $val>(float)$m[2])
                    return FALSE;
            }
        }
    }

    /**
     * Required input
     * @param mixed $val
     * @param bool|callable $cond
     * @return bool|NULL
     */
    function reqd($val,$cond=TRUE) {
        if (is_callable($cond))
            $cond=call_user_func($cond,$val);
        if ($cond)
            return is_numeric($val) || (bool)$val;
    }

    /**
     * Valid file path
     * @param mixed $val
     * @param string $src Source folder
     * @return bool
     */
    function isFile($val,$src='./') {
        return !$val || is_file($src.$val);
    }

    /**
     * Valid e-mail address
     * @param mixed $val
     * @param bool $mx (check MX record)
     * @return bool
     */
    function isEmail($val,$mx=FALSE) {
        return !$val || Audit::instance()->email($val,$mx);
    }

    /**
     * Valid URL
     * @param mixed $val
     * @return bool
     */
    function isUrl($val) {
        return !$val || Audit::instance()->url($val);
    }

    /**
     * Exists in given array
     * @param mixed $val
     * @param array $arr
     * @return bool
     */
    function inArray($val,$arr) {
        return in_array($val,$arr);
    }

}