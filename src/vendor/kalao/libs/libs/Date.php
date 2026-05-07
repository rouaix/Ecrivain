<?php
namespace KS;

class Date {

    /**
     * Format MySQL date to dd/mm/yyyy format
     * @param string $date
     * @return string
     */
    static function format($date) {
        if ($date) {
            list($y,$m,$d)=explode('-',$date);
            return "$d/$m/$y";
        }
        return '';
    }

    /**
     * Compute the difference in days between two dates
     * @param string $date2
     * @param string $date1
     * @return int
     */
    static function diff($date2,$date1) {
        return (int)round((strtotime($date2)-strtotime($date1))/86400);
    }

    /**
     * Add an interval in days to a given date
     * @param string $date1
     * @param int $interval
     * @return string
     */
    static function add($date1,$interval) {
        return date('Y-m-d',strtotime($date1)+86400*$interval+3600);// DST-safe
    }

    /**
     * Compute age at a $refdate, given the birth date
     * @param string $birthdate
     * @param string $refdate
     * @return int
     */
    static function age($birthdate,$refdate=NULL) {
        if (!$refdate)
            $refdate=date('Y-m-d');
        list($y1,$m1,$d1)=explode('-',$birthdate);
        list($y2,$m2,$d2)=explode('-',$refdate);
        $age=$y2-$y1;
        if ($m2<$m1 || $m2==$m1 && $d2<$d1)
            $age--;
        return $age;
    }

}
