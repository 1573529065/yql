<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 11/21/2017
 * Time: 6:03 PM
 */

namespace App\Unit;


class Json
{
    /**
     * json 序列化
     * @author jerry
     * @date   2016-12-29
     * @update 2017-11-21 of ifehrim@gmail.com
     * @param $arr
     * @param int $options
     * @param int $depth
     * @return string
     */
    public static function encode($arr, $options = 0, $depth = 512)
    {
        if (empty($arr)) {
            return '';
        }
        $result = json_encode($arr, $options, $depth);
        if (empty($result)) {
            return '';
        }
        return $result;

    }

    /**
     * json解析
     * @author jerry
     * @date   2016-12-29
     * @update 2017-11-21 of ifehrim@gmail.com
     * @param $json
     * @param bool $assoc
     * @param int $depth
     * @param int $options
     * @return mixed|string|array
     */
    public static function decode($json, $assoc = true, $depth = 512, $options = 0)
    {
        if (empty($json)) {
            return ''; //todo real is [] fix this bug
        }
        $result = json_decode($json, $assoc, $depth, $options);
        if (empty($result)) {
            return ''; //todo real is [] fix this bug
        }
        return $result;
    }





    public static function merge($json,...$jsons)
    {


    }





    /**
     * @auther ifehrim@gmail.com
     * @param $json
     * @param array ...$jsons
     * @return string
     */
    public static function concat($json, ...$jsons)
    {
        $arr=self::decode($json);
        if(empty($arr)) $arr=[];
        foreach ($jsons as $_json) {
            $arr[]=self::decode($_json);
        }
        return self::encode($arr);
    }

    /**
     *
     * @auther ifehrim@gmail.com
     * @param $json
     * @param $key
     * @return string
     */
    public static function get($json, $key)
    {
        $keys=explode(".",$key);
        if(count($keys)>1){
            $json=self::get($json,$keys[0]);
            unset($keys[0]);
            return self::get($json,implode(".",$keys));
        }
        $arr=self::decode($json);
        if(isset($arr[$key])){
            return self::encode($arr[$key]);
        }
        return self::encode([]);
    }

    /**
     * @auther ifehrim@gmail.com
     * @param $json
     * @param $items
     * @return string
     */
    public static function set($json, $items)
    {
        $arr=self::decode($json);
        if(self::is($items)) $items=self::decode($items);
        if(is_array($items))
        foreach ($items as $k=>$v) {
            $arr[$k]=$v;
        }
        return self::encode($arr);
    }

    /**
     * @auther ifehrim@gmail.com
     * @param $json
     * @param $items
     * @return string
     */
    public static function del($json, $items)
    {
        $arr=self::decode($json);
        if(self::is($items)) $items=self::decode($items);
        if(is_array($items))
            foreach ($items as $k) {
                unset($arr[$k]);
            }
        return self::encode($arr);
    }

    /**
     * @auther ifehrim@gmail.com
     * @param $string
     * @return bool
     */
    public static function is($string) {
        if(is_string($string)){
            json_decode($string);
            return (json_last_error() == JSON_ERROR_NONE);
        }
        return false;
    }


}