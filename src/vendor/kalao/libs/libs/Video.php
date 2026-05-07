<?php
namespace KS;
use Web,
    Cache;

class Video {

    //@{ Error messages
    const
        E_Detect='Video type could not be detected from URL %s',
        E_Type='Video type %s is not recognized!';
    //@}

    //! Types
    const
        TYPE_youtube='youtube',
        TYPE_vimeo='vimeo';

    //! API cache duration
    const
        API_ttl=86400;

    /** @var string */
    public $id;

    /** @var int */
    public $type;

    /** @var string */
    protected $embedUrl;

    /** @var string */
    protected $thumbnailUrl;

    /** @var array */
    protected $apiData;

    /**
     * Get embed URL
     * @return string
     */
    function getEmbedUrl() {
        if (!$this->embedUrl) {
            $embed=[
                self::TYPE_youtube=>'https://www.youtube.com/embed/%s?vq=hd720&rel=0',
                self::TYPE_vimeo=>'https://player.vimeo.com/video/%s?title=0&byline=0&portrait=0',
            ];
            $this->embedUrl=sprintf(@$embed[$this->type],$this->id);
        };
        return $this->embedUrl;
    }

    /**
     * Get thumbnail URL
     * @return string
     */
    function getThumbnailUrl() {
        if (!$this->thumbnailUrl) {
            switch($this->type) {
                case self::TYPE_youtube:
                    // cf. https://stackoverflow.com/a/2068371/2588746
                    $this->thumbnailUrl='https://img.youtube.com/vi/'.$this->id.'/hqdefault.jpg';// width=480
                    // the following would also work:
                    // $this->thumbnailUrl='https://i1.ytimg.com/vi/'.$this->id.'/hqdefault.jpg';

                    /* if we could get the API to work (requires authentication), we could get fetch thumbnail URL like this:
                    if ($data=$this->getApiData())
                        foreach($data['entry']['media$group']['media$thumbnail'] as $thumb)
                            if ($thumb['yt$name']=='hqdefault'||$thumb['width']=='480') {
                                $this->thumbnailUrl=@$thumb['url'];
                                break;
                            }
                    */
                    break;
                case self::TYPE_vimeo:
                    if ($data=$this->getApiData())
                        $this->thumbnailUrl=$data[0]['thumbnail_large'];// width=640
                    break;
            }
        };
        return $this->thumbnailUrl;
    }

    /**
     * Get API data
     * @return array
     */
    protected function getApiData() {
        if (!$this->apiData) {
            $cache=Cache::instance();
            if (!$cache->exists($key=__CLASS__.'.'.$this->type.'.'.$this->id,$data)) {
                $web=Web::instance();
                $api=[
                    //NB: l'API youtube v2 n'est plus supportée
                    self::TYPE_youtube=>'https://gdata.youtube.com/feeds/api/videos/%s?v=2&fields=media:group(media:thumbnail)&alt=json',
                    self::TYPE_vimeo=>'https://vimeo.com/api/v2/video/%s.json',
                ];
                if (array_key_exists($this->type,$api) &&
                    ($response=$web->request(sprintf($api[$this->type],$this->id))) &&
                    ($data=@json_decode($response['body'],TRUE))) {
                    $cache->set($key,$data,self::API_ttl);
                }
            }
            $this->apiData=$data;
        }
        return $this->apiData;
    }

    /**
     * @param string $url
     * @return array
     */
    protected function detect($url) {
        $types=[
            self::TYPE_youtube=>'/(?:youtube(?:-nocookie)?\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i',
            self::TYPE_vimeo=>'/^.*(?:vimeo\.com\/)(?:(?:channels\/[A-z]+\/)|(?:groups\/[A-z]+\/videos\/))?([0-9]+)/i',
        ];
        foreach($types as $t=>$pattern)
            if (preg_match($pattern,$url,$m))
                return [$t,$m[1]];
        user_error(sprintf(self::E_Detect,$url),E_USER_ERROR);
    }

    /**
     * @param array|string $input
     */
    function __construct($input) {
        list($type,$id)=is_array($input)?$input:$this->detect($input);
        $this->type=$type;
        $this->id=$id;
        if (!defined(__CLASS__.'::TYPE_'.$this->type))
            user_error(sprintf(self::E_Type,$this->type),E_USER_ERROR);
    }
}