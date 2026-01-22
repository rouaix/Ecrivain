<?php
namespace KS;
use Base,
    Web;

class Folder {

    //@{ Error messages
    const
        E_Path='Folder %s does not exist!';
    //@}

    //! Filesystem modes
    const
        MODE_Dir=0775;

    /** @var string Folder path */
    protected $path;

    /**
     * Return the folder contents
     * @param string $filter
     * @return array
     */
    function getContents($filter='*') {
        $children=[];
        foreach(glob($this->path.'/'.$filter) as $path)
            $children[]=[
                'path'=>$path,
                'name'=>basename($path),
                'dir'=>is_dir($path),
                'mtime'=>filemtime($path),
                'size'=>filesize($path),
            ];
        usort($children,function($a,$b){
            if ($a['mtime']==$b['mtime'])
                return $a['name']>$b['name']?1:-1;
            return $a['mtime']<$b['mtime']?1:-1;
        });
        return $children;
    }

    /**
     * @return string
     */
    function getPath() {
        return $this->path;
    }

    /**
     * Get filepath
     * @param string $filename
     * @return string|FALSE
     */
    function getFilepath($filename) {
        $path=$this->path.'/'.(preg_replace('/\.+/','.',$filename));// make sure we are removing dots only INSIDE the folder
        return is_file($path)?$path:FALSE;
    }

    /**
     * Save uploaded files to folder
     * @return array|bool
     */
    function saveUploads() {
        $f3=Base::instance();
        $f3->UPLOADS=$this->path.'/';
        $uploads=[];
        $web=Web::instance();
        foreach($web->receive(NULL,TRUE) as $filename=>$status)
            $uploads[$this->path.'/'.$filename]=$status;
        return $uploads;
    }

    /**
     * Remove a file from the folder
     * @param string $filename
     * @return bool
     */
    function removeFile($filename) {
        if ($path=$this->getFilepath($filename))
            return unlink($path);
        return FALSE;
    }

    /**
     * Constructor
     * @param string $path
     * @param bool $force
     */
    function __construct($path,$force=FALSE) {
        $this->path=rtrim($path,'/');
        if (!is_dir($path))
            $force?mkdir($path,self::MODE_Dir,TRUE):user_error(sprintf(self::E_Path,$path),E_USER_ERROR);
    }

}
