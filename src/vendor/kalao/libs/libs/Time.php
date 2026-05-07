<?php
namespace KS;
use Base;

class Time {

    //@{ Interval units
    const
        SECOND='s',
        MINUTE='m',
        HOUR='h',
        DAY='d';
    //@}

    /** @var array Interval durations */
    private static $durations=[
        self::SECOND => 1,
        self::MINUTE => 60,
        self::HOUR => 3600,
        self::DAY => 86400,
    ];

    /**
     * Compute the difference between two timestamps, including DST
     * @param int $t2
     * @param int $t1
     * @param string $unit
     * @param bool $int Output as int
     * @return int|float
     */
    static function diff($t2,$t1,$unit=self::SECOND,$int=TRUE) {
        $diff=($t2 - $t1 + self::offsetDiff($t2,$t1)) / self::$durations[$unit];
        return $int ? (int)$diff : $diff;
    }

    /**
     * Add an interval to a timestamp, substracting DST
     * @param int $t1
     * @param int $interval
     * @param string $unit
     * @return int
     */
    static function add($t1,$interval,$unit=self::SECOND) {
        $t2 = $t1 + $interval * self::$durations[$unit];
        return $t2 - self::offsetDiff($t2,$t1);
    }

    /**
     * Compute the intersection range of two time ranges (i.e arrays of timestamps)
     * Return FALSE if no intersection range has been found or if it is zero-length (e.g [10,10])
     * @param array $rangeA
     * @param array $rangeB
     * @return array|FALSE
     */
    static function intersect($rangeA,$rangeB) {
        sort($rangeA);
        sort($rangeB);
        $intersect=[];
        if (self::diff($rangeA[1],$rangeB[0])>0)
            $intersect[0]=max($rangeA[0],$rangeB[0]);
        if (self::diff($rangeB[1],$rangeA[0])>0)
            $intersect[1]=min($rangeA[1],$rangeB[1]);
        return count($intersect)==2?$intersect:FALSE;
    }

    /**
     * Compute age at $time, given the birth date
     * @param int $birthdate
     * @param int $time
     * @return int
     */
    static function age($birthdate,$time=NULL) {
        return (new DateTime(isset($time)?'@'.$time:'now'))->diff(new DateTime('@'.$birthdate))->y;
    }

    /**
     * Compute the DST offset difference between two timestamps
     * @param int $t2
     * @param int $t1
     * @return int
     */
    static function offsetDiff($t2,$t1) {
        $tz=new DateTimeZone(Base::instance()->TZ);// current timezone
        $offset2=(int)@$tz->getTransitions($t2,$t2)[0]['offset'];// t2 offset with UTC
        $offset1=(int)@$tz->getTransitions($t1,$t1)[0]['offset'];// t1 offset with UTC
        return $offset2-$offset1;
    }

}