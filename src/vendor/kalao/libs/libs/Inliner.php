<?php
namespace KS;
use Base,
    Log;

class Inliner {

    /** @var array|string */
    public $defaults=[];

    /** @var bool Drop class attribute */
    public $dropClass=TRUE;

    /** @var bool Debug log */
    public $debug=FALSE;

    /** @var Log */
    private $log;

    /** @var array */
    protected static $self_closing=['br','wbr','hr','img','input','link','meta'];

    /**
     * Convert HTML code
     * @param string $html
     * @return string
     */
    function convert($html) {
        $defaults=$this->defaults;
        if (is_string($defaults))
            $defaults=Base::instance()->get($defaults);
        $tags=[];
        foreach($defaults as $selector=>$default)
            if ($parse=$this->parseSelector($selector))
                $tags[$parse['tag']][$parse['selector']]=$parse+['default'=>$default];
        foreach($tags as $name=>$selectors) {// stable sort by specificity (original order preserved when possible)
            $tmp=[];$i=0;
            foreach($selectors as $sel=>$data)
                $tmp[]=[$i++,$sel,$data];
            uasort($tmp,function($a,$b){
                return $a[2]['specificity']-$b[2]['specificity']?:$a[0]-$b[0];
            });
            $tags[$name]=[];
            foreach($tmp as $a)
                $tags[$name][$a[1]]=$a[2];
        }
        $tree=[];
        return preg_replace_callback('/<(\/?)([a-z0-9]+)(.*?)(\/?)(?<![?-])>/i',function($m)use(&$tree,$tags){
            if ($m[1]) {// closing tag
                array_pop($tree);
                return $m[0];
            } else {// opening tag
                $str='<'.$m[2];
                $attrs=$this->parseAttrs($m[3]);
                $classes=array_filter(explode(' ',@$attrs['class']));
                if (isset($tags[$m[2]]))
                    foreach(array_reverse($tags[$m[2]]) as $sel=>$data) {
                        if ($data['classes'] && !$this->allInArray($data['classes'],$classes))
                            continue;
                        $treeCopy=$tree;
                        while ($data['ancestor']) {
                            if (!$this->inTree($data['ancestor'],$treeCopy,$data['immediate']))
                                continue 2;
                            $data['ancestor']=$data['ancestor']['ancestor'];
                            $data['immediate']=$data['ancestor']['immediate'];
                        }
                        $default=$data['default'];
                        if (isset($attrs['style'],$default['style']))
                            $attrs['style']=rtrim($default['style'],';').';'.$attrs['style'];// default style is prepended
                        $attrs+=$default;
                    }
                if ($this->dropClass)
                    unset($attrs['class']);
                foreach($attrs as $k=>$v)
                    $str.=' '.$k.(isset($v)?'="'.$v.'"':'');
                $str.=$m[4].'>';
                if (!$m[4] && !in_array($m[2],self::$self_closing)) // non self-closing tag
                    $tree[]=[$m[2],$classes];
                if ($this->debug)
                    $this->logTree($tree);
                return $str;
            }
        },$html);
    }

    /**
     * Check if all the given values exist in an array
     * @param array $needles
     * @param array $haystack
     * @return bool
     */
    protected function allInArray($needles,$haystack) {
        return !array_diff($needles,$haystack);
    }

    /**
     * Return TRUE if the given distant/immediate ancestor is found in the tree
     * @param array $ancestor
     * @param array &$tree
     * @param bool $immediate
     * @return bool
     */
    protected function inTree($ancestor,&$tree,$immediate) {
        while ($item=array_pop($tree)) {
            list($tag,$classes)=$item;
            if ($ancestor['tag']==$tag && (!$ancestor['classes'] || $this->allInArray($ancestor['classes'],$classes)))
                return TRUE;
            if ($immediate)
                break;
        }
        return FALSE;
    }

    /**
     * Parse a simple CSS selector (only descendants and classes are supported)
     * Example: `h1 a.btn > span`
     * @param string $str
     * @return array|FALSE
     */
    protected function parseSelector($str) {
        if (preg_match('/^(.*?)(>\h*)?(\w+)((?:\.\w+)*)\h*$/',$str,$m))
            return [
                'tag'=>$m[3],
                'selector'=>$m[0],
                'specificity'=>$this->getSpecificity($m[0]),
                'classes'=>$m[4]?array_filter(explode('.',$m[4])):[],
                'ancestor'=>$this->parseSelector($m[1]),
                'immediate'=>(bool)$m[2],
            ];
        return FALSE;
    }

    /**
     * Compute specificity of a simple CSS selector
     * @param string $str
     * @return int
     */
    protected function getSpecificity($str) {
        $score=0;
        if (preg_match_all('/(\b|\.)\w/',$str,$matches,PREG_SET_ORDER))
            foreach($matches as $m)
                $score+=$m[1]=='.'?10:1;
        return $score;
    }

    /**
     * Parse attributes string
     * @param string $str
     * @return array
     */
    protected function parseAttrs($str) {
        $attrs=[];
        if (preg_match_all('/([a-z\-]+)(?:\h*=\h*"([^"]*)")?/i',$str,$matches,PREG_SET_ORDER))
            foreach($matches as $m)
                $attrs[$m[1]]=@$m[2];
        return $attrs;
    }

    /**
     * Log tree to debug file
     * @param array $tree
     */
    private function logTree($tree) {
        $this->getLog()->write(implode(' > ',array_map(function($item){return $item[0].($item[1]?'.'.implode('.',$item[1]):'');},$tree)));
    }

    /**
     * Get Log instance
     * @return Log
     */
    private function getLog() {
        if (!isset($this->log))
            $this->log=new Log('inliner.debug');
        return $this->log;
    }

}