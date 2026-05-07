<?php

use KS\Mapper;

class ProjectFile extends Mapper
{
    const TABLE = 'project_files';

    /**
     * Get formatted file size
     */
    public function getSizeFormatted()
    {
        $size = $this->filesize;
        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1048576) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / 1048576, 2) . ' MB';
        }
    }
}
