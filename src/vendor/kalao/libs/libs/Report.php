<?php
namespace KS;
use Prefab,
    Base,
    Exception;

class Report extends Prefab {

    /** @var string|array */
    protected $to;

    /**
     * Send error report by e-mail
     * @param string $body
     */
    function send($body=NULL) {
        if ($this->to) {
            $f3=Base::instance();
            $mail=$f3->MAIL();
            $mail->to=$this->to;
            $subject=sprintf('Error %s on %s [%s]',$f3->get('ERROR.code'),$_SERVER['SERVER_NAME'],date('d/m/Y H:i:s'));
            if (!isset($body)) {
                $req=PHP_SAPI=='cli'?
                    implode(' ',(array)@$_SERVER['argv']):
                    $f3->get('VERB').' '.$f3->get('SERVER.REQUEST_URI');
                $body=$f3->get('ERROR.status').PHP_EOL.
                    $f3->get('ERROR.code').PHP_EOL.
                    $f3->get('ERROR.text').PHP_EOL.
                    'IP='.$f3->get('IP').PHP_EOL.
                    'REQ='.$req.PHP_EOL.
                    PHP_EOL.
                    $this->generateCallTrace();
            }
            $mail->send($subject,$body);
        }
    }

    /**
     * @return string
     */
    protected function generateCallTrace() {//cf. http://www.php.net/manual/en/function.debug-backtrace.php#112238
        $e = new Exception();
        $trace = explode(PHP_EOL, $e->getTraceAsString());
        // reverse array to make steps line up chronologically
        $trace = array_reverse($trace);
        array_shift($trace); // remove {main}
        array_pop($trace); // remove call to this method
        $length = count($trace);
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
        }
        return implode(PHP_EOL, $result);
        /*return implode(PHP_EOL,array_map(function($trace){
            return (isset($trace['file'])?$trace['file']:'-').
            (isset($trace['line'])?' ('.$trace['line'].')':'');
        },debug_backtrace(FALSE)));*/
    }

    //! Constructor
    function __construct() {
        $this->to=Base::instance()->get('REPORT.to');
    }

}