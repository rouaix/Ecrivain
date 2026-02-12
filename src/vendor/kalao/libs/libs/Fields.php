<?php

namespace KS;
use Prefab,
    Base,
    Template;

class Fields extends Prefab {

    //@{ Error messages
    const
        E_Type='Type %s doesn\'t exist',
        E_Name='Field name is mandatory';
    //@}

    /** @var array */
    protected $fields=[];

    /** @var array */
    protected $config=[
        'layout' => ':label: :input:',
        'prefix' => 'field-',
        'keywords' => [],
        'dict' => NULL,
        'data' => NULL,
        'types' => [
            'text' => Fields\Text::class,
            'file' => Fields\File::class,
            'password' => Fields\Password::class,
            'textarea' => Fields\Textarea::class,
            'select' => Fields\Select::class,
        ],
    ];

    /**
     * Render <field> tag
     * @param array $node
     * @return string
     */
    function renderField($node) {
        $attrs=$node['@attrib']+[
            'name'=>'',
            'type'=>'',
            'id'=>NULL,
            'value'=>NULL,
            'label'=>NULL,
            'layout'=>$this->config['layout'],
            ];
        if (!array_key_exists($attrs['type'],$this->config['types']))
            user_error(sprintf(self::E_Type,$attrs['type']),E_USER_ERROR);
        if (!$attrs['name'])
            user_error(self::E_Name,E_USER_ERROR);
        $tpl=Template::instance();
        if (!isset($attrs['label'])) {
            $attrs['label']=strtr(preg_replace('/\{\{(.+?)\}\}/','',$attrs['name']),['[]'=>'',']'=>'','['=>'_']);// foo[bar][] => foo_bar
            if (isset($this->config['dict']))
                $attrs['label']='{{'.$this->config['dict'].'.'.$attrs['label'].'}}';
        }
        if (!isset($attrs['id']))
            $attrs['id']=strtr($this->config['prefix'].$attrs['name'],['[]'=>'',']'=>'','['=>'_']);// foo[bar][] => foo_bar
        if (!isset($attrs['value']) && isset($this->config['data']))
            $attrs['value']='{{@ '.$this->config['data'].($this->config['data']=='@'?'':'.').
                preg_replace('/\{\{(.+?)\}\}/s',trim('\1'),preg_replace('/\[\s*(\w+)\s*\]/',"['$1']",$attrs['name'])).'}}';
        $label=$attrs['label'];
        $layout=$attrs['layout'];
        $labelClasses=[];
        foreach($this->config['keywords'] as $key) {
            if (array_key_exists($key,$attrs)) {
                $labelClasses[]=preg_match('/\{\{(.+?)\}\}/',$attrs[$key])?
                    '<?php if('.$tpl->token($attrs[$key]).') echo \''.$key.'\'; ?>':
                    $key;
                unset($attrs[$key]);
            }
        }
        unset($attrs['label'],$attrs['layout']);
        $field=new $this->config['types'][$attrs['type']]($attrs);
        return strtr($layout,[
            ':label:'=>$tpl->build('<label for="'.$attrs['id'].'"'.
                ($labelClasses?' class="'.implode(' ',$labelClasses).'"':'').'>'.$label.'</label>'),
            ':input:'=>$tpl->build($field->render($attrs)),
        ]);
    }

    /**
     * Render <fields> tag
     * @param array $node
     * @return string
     */
    function renderFields($node) {
        $attrib=$node['@attrib'];
        unset($node['@attrib']);
        $backup=$this->config;
        foreach($attrib as $k=>$v)
            $this->set($k,$v);
        $code=Template::instance()->build($node);
        $this->config=$backup;
        return $code;
    }

    /**
     * Declare a new field type
     * @param string $type
     * @param string $class
     * @return $this
     */
    function addType($type,$class) {
        $this->config['types'][$type]=$class;
        return $this;
    }

    /**
     * HTML escape a value
     * @param string|array $val
     * @return string|array
     * @todo décider si on garde ou supprime $this->encode
     */
    protected function encode($val) {
        $f3=Base::instance();
        return is_array($val)?
            array_map(function($str)use($f3){return $f3->encode($str);},$val):
            $f3->encode($val);
    }

    /**
     * Set a config item
     * @param string $key
     * @param mixed $val
     */
    protected function set($key,$val) {
        if (in_array($key,['data','dict']))
            $val=trim(preg_replace('/\{\{|\}\}/','',$val));// we don't need braces
        if (array_key_exists($key,$this->config))
            $this->config[$key]=is_array($this->config[$key])?
                array_merge($this->config[$key],is_array($val)?$val:Base::instance()->split($val)):$val;
    }

    //! Constructor
    function __construct() {
        $f3=Base::instance();
        if (is_array($config=$f3->FIELDS))
            foreach($config as $key=>$val)
                $this->set($key,$val);
        $tpl=Template::instance();
        $tpl->extend('field',[$this,'renderField']);
        $tpl->extend('fields',[$this,'renderFields']);
    }

}