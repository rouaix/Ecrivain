<?php
namespace KS;
use Base,
    SMTP,
    Log;

class Mail {

    //@{ Error messages
    const
        E_MissingFields='E-mails can\'t be sent without proper From and To fields',
        E_Attachment='Attachment %s could not be found';
    //@}

    public
        $from,//sender
        $to,//recipient
        $cc,//carbon copy
        $bcc,//blind carbon copy
        $reply,//reply address
        $logging=FALSE,
        $SMTP=NULL,//SMTP settings
        $renderer;//HTML renderer

    /**
     * Send e-mail
     * @param string $subject
     * @param string $body
     * @param bool|string $html (Flag or HTML template)
     * @param array $attachments
     * @return bool
     */
    function send($subject,$body,$html=FALSE,array $attachments=[]) {
        foreach (['to','cc','bcc'] as $k)
            if (is_array($this->$k))
                $this->$k=implode(',',$this->$k);
        if (!($this->from && $this->to)) {
            user_error(self::E_MissingFields,E_USER_ERROR);
            return FALSE;
        }
        foreach ($attachments as $k=>$path) {
            if (!is_file($path))
                user_error(sprintf(self::E_Attachment,$path),E_USER_ERROR);
            if (is_int($k)) {
                unset($attachments[$k]);
                $name=basename($path);
            } else
                $name=$k;
            $attachments[$name]=$path;
        }
        $f3=Base::instance();
        $headers=[
            'MIME-Type'=>'1.0',
            'Content-Type'=>'text/'.($html?'html':'plain').'; charset='.$f3->get('ENCODING'),
            'Content-Transfer-Encoding'=>'8bit',
            'From'=>$this->from,
            'Reply-To'=>$this->reply?:$this->from,
            //"Return-Path: ".MAIL_BOUNCE.PHP_EOL . //Ne marche pas chez OVH en mutualise
            'X-Mailer'=>'PHP/'.phpversion(),
        ];
        if ($this->cc)
            $headers['Cc']=$this->cc;
        if ($this->bcc)
            $headers['Bcc']=$this->bcc;
        if ($html && isset($this->renderer))
            $body=$f3->call($this->renderer,[$subject,$body]);
        if (is_array($this->SMTP) && isset($this->SMTP['host'])) {
            $headers['Message-Id']='<'.time().'-'.md5($this->from.$this->to).'@'.parse_url($f3->MAIL_REALM(),PHP_URL_HOST).'>';
            $smtp=$this->SMTP+[
                    'port'=>25,
                    'scheme'=>NULL,
                    'user'=>NULL,
                    'pw'=>NULL,
                ];
            $smtp=new SMTP($smtp['host'],$smtp['port'],$smtp['scheme'],$smtp['user'],$smtp['pw']);
            foreach($headers as $key=>$val)
                $smtp->set($key,$val);
            $smtp->set('To',$this->to);
            $smtp->set('Subject',$this->encodeSubject($subject));
            foreach ($attachments as $name=>$path)
                $smtp->attach($path,$name);
            $res=$smtp->send($body);
        } else {
            if ($attachments) {
                //references :
                //http://webcheatsheet.com/php/send_email_text_html_attachment.php
                //https://github.com/stlewis/Mail/blob/master/mail.php
                //https://github.com/bcosca/fatfree/blob/master/lib/smtp.php
                $boundary_hash=md5(date('r',time()));
                $body='--PHP-mixed-'.$boundary_hash.PHP_EOL.
                    'Content-Type: multipart/alternative; boundary="PHP-alt-'.$boundary_hash.'"'.PHP_EOL.
                    PHP_EOL.
                    '--PHP-alt-'.$boundary_hash.PHP_EOL.
                    'Content-Type: '.$headers['Content-Type'].PHP_EOL.
                    PHP_EOL.
                    $body.PHP_EOL.
                    PHP_EOL.
                    '--PHP-alt-'.$boundary_hash.'--'.PHP_EOL.
                    PHP_EOL;
                $headers['Content-Type']='multipart/mixed; boundary="PHP-mixed-'.$boundary_hash.'"';
                foreach($attachments as $name=>$path)
                    $body.='--PHP-mixed-'.$boundary_hash.PHP_EOL.
                        'Content-Type: application/octet-stream; name="'.$name.'"'.PHP_EOL.
                        'Content-Transfer-Encoding: base64'.PHP_EOL.
                        'Content-Disposition: attachment; filename="'.$name.'"'.PHP_EOL.
                        PHP_EOL.
                        chunk_split(base64_encode(file_get_contents($path))).PHP_EOL;
                $body.='--PHP-mixed-'.$boundary_hash.'--'.PHP_EOL;
            }
            @ini_set('sendmail_from',$this->from);
            mb_internal_encoding($f3->get('ENCODING'));
            $res=mb_send_mail($this->to,$subject,$body,
                implode(PHP_EOL,array_map(function($k,$v){return $k.': '.$v;},array_keys($headers),array_values($headers))));
        }
        if ($this->logging) {
            $data=array_filter([
                'Result'=>(int)$res,
                'From'=>$this->from,
                'To'=>$this->to,
                'Cc'=>$this->cc,
                'Bcc'=>$this->bcc,
                'Subject'=>$subject,
                'Body'=>$body,
            ]);
            $log=new Log('mail.log');
            $log->write(implode(PHP_EOL,array_map(function($k,$v){return $k.': '.$v;},array_keys($data),array_values($data))));
        }
        return $res;
    }

    /**
     * @param string $subject
     * @return string
     */
    protected function encodeSubject($subject) {
        $f3=Base::instance();
        $charset=$f3->get('ENCODING');
        // cf. https://stackoverflow.com/questions/4389676/email-from-php-has-broken-subject-header-encoding/27648245#27648245
        if (extension_loaded('iconv'))
            return substr(iconv_mime_encode('Subject',$subject,['input-charset'=>$charset,'output-charset'=>$charset]),strlen('Subject: '));
        if (extension_loaded('mbstring'))
            return mb_encode_mimeheader($subject,$charset,'B',"\r\n",strlen('Subject: '));
        return wordwrap($subject,65,"\r\n");
    }

    /**
     * Constructor
     * @param array $config
     */
    function __construct($config=NULL) {
        if (!isset($config)) {
            $f3=Base::instance();
            $config=$f3->MAIL;
        }
        if (is_array($config))
            foreach($config as $key=>$val)
                if (property_exists($this,$key))
                    $this->$key=$val;
    }

}