<?php
namespace KS;
use Prefab,
    Base;

class Msg extends Prefab {

    //! Constants
    const TTL=60;//maximum lifetime of a message

    /** @var array */
    protected $list;

    /** @var int Current timestamp */
    protected $stamp;

    /**
     * Push message into the list
     * @param string $text
     * @param string $status
     * @param array $data
     */
    function say($text,$status='err',$data=[]) {
        $this->list[]=['text'=>$text,'status'=>$status,'stamp'=>$this->stamp]+$data;
    }

    /**
     * Deliver up-to-date messages
     * @return array
     */
    function deliver() {
        $list=[];
        $limit=$this->stamp-self::TTL;
        $delivered=[];// on vérifie qu'on n'affiche pas 2x le même texte
        foreach($this->list as $msg)
            if ($msg['stamp']>$limit && !isset($delivered[$hash=$this->hash($msg)])) {
                $list[]=$msg;
                $delivered[$hash]=TRUE;
            }
        $this->list=[];
        return $list;
    }

    /**
     * Return TRUE if the message list is empty
     * @return bool
     */
    function dry() {
        return count($this->list)==0;
    }

    /**
     * Hash message data
     * @param array $msg
     * @return string
     */
    protected function hash($msg) {
        unset($msg['stamp']);
        return md5(json_encode($msg));
    }

    //! Constructor
    function __construct($key='msg') {
        $f3=Base::instance();
        if (!is_array($this->list=&$f3->SESSION[$key]))
            $this->list=[];
        $this->stamp=time();
    }

}