<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 4/25/2018
 * Time: 15:34
 */

namespace App\Unit;


use Illuminate\Support\Facades\Redis as BaseRedis;

class Redis
{

    public function connection($connection = "default")
    {
        return BaseRedis::connection($connection);
    }


    /**
     * @auther ifehrim@gmail.com
     * @param $key
     * @param $field
     * @param null $item
     * @return null|array
     */
    public static function hget($key, $field, $item = null)
    {
        $redis = (new static())->connection();
        $res = $redis->hget($key, $field);
        if (!empty($res)) {
            if (!empty($item)) {
                $res = Json::get($res, $item);
                return empty($res) ? null : Json::decode($res);
            }
            return Json::decode($res);
        }
        return null;
    }


}