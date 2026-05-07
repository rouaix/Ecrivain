<?php
namespace KS;
use Prefab,
    Base;

class Layout extends Prefab {

    /** @var string */
    public $wrapper;

    /** @var array */
    protected $data=[];

    /** @var array */
    protected $filters=[];

    /** @var array Before render hooks */
    protected $beforeRender=[];

    /** @var bool Rendering flag */
    protected $flag=FALSE;

    /**
     * Retrieve a stored value
     * @param string $key
     * @return mixed
     */
    function get($key) {
        return isset($this->data[$key]) ? $this->data[$key] : NULL;
    }

    /**
     * Store a value to be rendered
     * @param string $key
     * @param mixed $val
     * @return $this
     */
    function set($key,$val) {
        $this->data[$key]=$val;
        return $this;
    }

    /**
     * @param string $file
     * @param array $hive
     * @return string
     */
    function render($file,$hive=[]) {
        $hive+=$this->data;
        if ($this->wrapper)
            $hive['include']=$file;
        $f3=Base::instance();
        foreach($this->beforeRender as $hook)
            $f3->call($hook,[&$hive,$this]);
        $this->flag=TRUE;
        $out=$f3->TEMPLATE()->render($this->wrapper?:$file,'text/html',$hive);
        $this->flag=FALSE;
        return $out;
    }

    /**
     * Return TRUE if layout is being rendered
     * @return bool
     */
    function isRendering() {
        return $this->flag;
    }

    /**
     * Add a hook to be triggered before rendering
     * @param string|callable $hook
     * @return $this
     */
    function beforeRender($hook) {
        $this->beforeRender[]=$hook;
        return $this;
    }

    /**
     * @param array $config
     */
    function __construct(array $config=NULL) {
        if (!isset($config)) {
            $f3=Base::instance();
            $config=(array)$f3->LAYOUT;
        }
        foreach($config as $key=>$val)
            if (property_exists($this,$key))
                $this->$key=$val;
        if (!is_array($this->beforeRender))
            $this->beforeRender=[$this->beforeRender];
    }

}
