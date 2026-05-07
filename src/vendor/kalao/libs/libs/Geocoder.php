<?php
namespace KS;
use Web;

class Geocoder {

    //! Geocoder API URL
    const API_Url='https://maps.googleapis.com/maps/api/geocode/json';

    //! Errors
    const
        E_Unknown='Unknown error while contacting %s. See log',
        E_Error='The error `%s` was received when contacting %s';

    /** @var string Searched address */
    public $address;

    /** @var string Language used for the query (optional) */
    public $language;

    /** @var string API key */
    protected $key;

    /**
     * Find coordinates of the submitted address
     * @return array|FALSE
     */
    function findCoordinates() {
        if ($this->key && $this->address) {
            $query=[
                'key'=>$this->key,
                'address'=>$this->address,
            ];
            if ($this->language)
                $query['language']=$this->language;
            $req=@Web::instance()->request($url=self::API_Url.'?'.http_build_query($query));
            if ($req && isset($req['body']) && $json=@json_decode($req['body'],TRUE)) {
                if ($firstLocation=(array)@$json['results'][0]['geometry']['location']) {
                    return $firstLocation;
                } elseif ($msg=@$json['error_message']) {
                    user_error(sprintf(self::E_Error,$msg,$url),E_USER_ERROR);
                }
            } else {
                error_log(print_r($req,true));
                user_error(sprintf(self::E_Unknown,$url),E_USER_ERROR);
            }
        }
        return FALSE;
    }

    /**
     * Constructor
     * @param string $key
     */
    function __construct($key) {
        $this->key=$key;
    }

}