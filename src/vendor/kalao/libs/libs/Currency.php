<?php
namespace KS;

class Currency {

    //@{ Error messages
    const
        E_Code='%s is not a supported currency code',
        E_Undefined='Undefined property: %s::$%s';
    //@}

    /** @var string Currency ISO 4217 alphabetic code */
    protected $code;

    /** @var string Currency ISO 4217 numeric code */
    protected $numcode;

    /** @var string Currency symbol */
    protected $symbol;

    //! Supported currencies data (symbol + numeric code)
    const CURRENCIES=[
        'CAD'=>[124,'C$'],
        'CHF'=>[756,'CHF'],
        'CNY'=>[156,'¥'],
        'CUC'=>[931,'CUC'],
        'CVE'=>[132,'CVE'],
        'EUR'=>[978,'€'],
        'GBP'=>[826,'£'],
        'IDR'=>[360,'Rp'],
        'USD'=>[840,'$'],
    ];

    /**
     * Find currency by numeric code
     * @param string $numcode
     * @return Currency|NULL
     */
    static function findByNumcode($numcode) {
        foreach (self::CURRENCIES as $code=>$data)
            if ($data[0]==$numcode)
                return new self($code);
        return NULL;
    }

    /**
     * @return array
     */
    function cast() {
        return [
            'code'=>$this->code,
            'numcode'=>$this->numcode,
            'symbol'=>$this->symbol,
        ];
    }

    //! Read-only public properties
    function __get($name) {
        if (in_array($name,['code','numcode','symbol']))
            return $this->$name;
        user_error(sprintf(self::E_Undefined,__CLASS__,$name),E_USER_ERROR);
    }

    /**
     * Constructor
     * @param string $code
     */
    function __construct($code) {
        if (!array_key_exists($code=strtoupper($code),self::CURRENCIES))
            user_error(sprintf(self::E_Code,$code),E_USER_ERROR);
        $this->code=$code;
        $this->numcode=self::CURRENCIES[$code][0];
        $this->symbol=self::CURRENCIES[$code][1];
    }

}