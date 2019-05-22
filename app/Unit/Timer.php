<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 12/18/2017
 * Time: 10:56 AM
 */

namespace App\Unit;
use Log;

class Timer
{


    public static $timers=[];
    public static $option=[
        "timer"=>0,
        "print"=>true,
    ];




    public static function start($message,$option=[]){
        $key=md5($message);
        $option=array_merge(self::$option,$option);
        self::$timers[$key]=$option;
        self::$timers[$key]['timer']=ftime();
        $message="----.---- s [Timer->_".__FUNCTION__."_] {$message}";
        if(self::$timers[$key]['print']){
            print(timestamp()." ".$message."\n");
        }
        Log::info($message);
    }


    public static function end($message){
        $key=md5($message);
        $b=ftime();
        $time=round($b - self::$timers[$key]['timer'],4);

        $time=preg_replace_callback("/(\d+)\.(\d+)/",function ($arr){
            $l=str_pad($arr[1],4,"0",STR_PAD_LEFT);
            $r=str_pad($arr[2],4,"0",STR_PAD_RIGHT);
            return $l.".".$r;
        },$time);

        $message=$time." s [Timer->__".__FUNCTION__."__] {$message}";
        if(self::$timers[$key]['print']){
            print(timestamp()." ".$message."\n");
        }
        Log::info($message);
    }

    public static function log($message,$isPrint=true){
        $message="----.---- s [Timer->__".__FUNCTION__."__] {$message}";
        if($isPrint){
            print(timestamp()." ".$message."\n");
        }
        Log::info($message);
    }








}