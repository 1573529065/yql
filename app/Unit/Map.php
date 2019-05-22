<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 12/25/2017
 * Time: 9:56 AM
 */

namespace App\Unit;

use App\Vendor\Baidu\MapApi;
use Log;

define('EARTH_RADIUS', 6378.137);//地球半径
define('EARTH_RADIUS_T', 6378245.0);//地球半径
define('PI', 3.1415926535897932384626);
define('x_PI', 3.14159265358979324 * 3000.0 / 180.0);
define('EE', 0.00669342162296594323);


class Map
{

    /**
     * 获取距离四个坐标
     * @auther ifehrim@gmail.com
     * @param $lat
     * @param $lng
     * @param int $distance
     * @return array
     */
    public static function radius($lat, $lng, $distance = 1)
    {
        $range = 180 / pi() * $distance / EARTH_RADIUS;
        $lngR = $range / cos($lat * pi() / 180);
        $data = [];
        $data[0][] = $lat - $range;
        $data[0][] = $lat + $range;
        $data[1][] = $lng + $lngR;//最大经度
        $data[1][] = $lng - $lngR;//最小经度
        return $data;
    }

    /**
     * 获取两点之间的距离
     * @auther ifehrim@gmail.com
     * @param $lat
     * @param $lng
     * @param $lat2
     * @param $lng2
     * @return float|int
     */
    public static function distance($lat, $lng, $lat2, $lng2)
    {
        $theta = $lng - $lng2;
        $miles = (sin(deg2rad($lat)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
        $miles = rad2deg(acos($miles)) * 60 * 1.1515;
        $meters = $miles * 1.609344 * 1000;
        return $meters;
    }

    /**
     * 需转换的源坐标，多组坐标以“；”分隔 经度，纬度）
     * @auther ifehrim@gmail.com
     * @param $bo_datas
     * @param bool $type
     * @return mixed
     */
    public static function geoconv($bo_datas,$type=false)
    {
        Arr::chunk($bo_datas, 100, function ($items) use ($bo_datas,$type) {
            if($type){
                $position = [];
                foreach ($items as $item) {
                    $position[] = $item["lat"] . "," . $item["lng"];
                }
                $pos = MapApi::geoconv(implode(";", $position));
                Log::info("json-car-item",[$pos]);
                $i=0;
                foreach ($items as $k => $item) {
                    if (isset($pos[$i])) {
                        if (isset($pos[$i])) $item["lat"] = $pos[$i]["y"];
                        if (isset($pos[$i])) $item["lng"] = $pos[$i]["x"];
                    }
                    $bo_datas[$k] = $item;
                    $i++;
                }
            }else{
                foreach ($items as $k => $item) {
                    list($lng,$lat)=self::wgs84_bd09($item["lng"],$item["lat"]);
                    $item["lat"]=$lat;
                    $item["lng"]=$lng;
                    $bo_datas[$k] = $item;
                }
            }

        });
        return $bo_datas;
    }





    /**
     * 百度坐标系 (BD-09) 与 火星坐标系 (GCJ-02)的转换
     * 即 百度 转 谷歌、高德
     * @param bd_lon
     * @param bd_lat
     * @return array
     */
    public static function bd09_gcj02($bd_lon, $bd_lat)
    {
        $x = $bd_lon - 0.0065;
        $y = $bd_lat - 0.006;
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * x_PI);
        $theta = atan2($y, $x) - 0.000003 * cos($x * x_PI);
        $gg_lng = $z * cos($theta);
        $gg_lat = $z * sin($theta);
        return [$gg_lng, $gg_lat];
    }

    /**
     * 火星坐标系 (GCJ-02) 与百度坐标系 (BD-09) 的转换
     * 即谷歌、高德 转 百度
     * @param lng
     * @param lat
     * @return array
     */
    public static function gcj02_bd09($lng, $lat)
    {
        $z = sqrt($lng * $lng + $lat * $lat) + 0.00002 * sin($lat * x_PI);
        $theta = atan2($lat, $lng) + 0.000003 * cos($lng * x_PI);
        $bd_lng = $z * cos($theta) + 0.0065;
        $bd_lat = $z * sin($theta) + 0.006;
        return [$bd_lng, $bd_lat];
    }

    /**
     * WGS84转GCj02
     * @param lng
     * @param lat
     * @return array
     */
    public static function wgs84_gcj02($lng, $lat)
    {
        if (self::outChina($lng, $lat)) {
            return [$lng, $lat];
        } else {
            $dlat = self::transformLat($lng - 105.0, $lat - 35.0);
            $dlng = self::transformLng($lng - 105.0, $lat - 35.0);
            $radlat = $lat / 180.0 * PI;
            $magic = sin($radlat);
            $magic = 1 - EE * $magic * $magic;
            $sqrtmagic = sqrt($magic);
            $dlat = ($dlat * 180.0) / ((EARTH_RADIUS_T * (1 - EE)) / ($magic * $sqrtmagic) * PI);
            $dlng = ($dlng * 180.0) / (EARTH_RADIUS_T / $sqrtmagic * cos($radlat) * PI);
            $mglat = $lat + $dlat;
            $mglng = $lng + $dlng;
            return [$mglng, $mglat];
        }
    }

    public static function wgs84_bd09($lng,$lat,$isOnline=false){
        if($isOnline) return MapApi::geoconv([[$lng,$lat]])[0];
        list($lng,$lat)=self::wgs84_gcj02($lng,$lat);
        return self::gcj02_bd09($lng,$lat);
    }


    public static function bd09_wgs84($lng,$lat,$isOnline=false){
        list($lng,$lat)=self::bd09_gcj02($lng,$lat);
        return self::gcj02_wgs84($lng,$lat);
    }


    /**
     * GCJ02 转换为 WGS84
     * @param lng
     * @param lat
     * @return array
     */
    public static function gcj02_wgs84($lng, $lat)
    {
        if (self::outChina($lng, $lat)) {
            return [$lng, $lat];
        } else {
            $dlat = self::transformLat($lng - 105.0, $lat - 35.0);
            $dlng = self::transformLng($lng - 105.0, $lat - 35.0);
            $radlat = $lat / 180.0 * PI;
            $magic = sin($radlat);
            $magic = 1 - EE * $magic * $magic;
            $sqrtmagic = sqrt($magic);
            $dlat = ($dlat * 180.0) / ((EARTH_RADIUS_T * (1 - EE)) / ($magic * $sqrtmagic) * PI);
            $dlng = ($dlng * 180.0) / (EARTH_RADIUS_T / $sqrtmagic * cos($radlat) * PI);
            $mglat = $lat + $dlat;
            $mglng = $lng + $dlng;
            return [$lng * 2 - $mglng, $lat * 2 - $mglat];
        }
    }




    private static function transformLat($lng, $lat)
    {
        $ret = -100.0 + 2.0 * $lng + 3.0 * $lat + 0.2 * $lat * $lat + 0.1 * $lng * $lat + 0.2 * sqrt(abs($lng));
        $ret += (20.0 * sin(6.0 * $lng * PI) + 20.0 * sin(2.0 * $lng * PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($lat * PI) + 40.0 * sin($lat / 3.0 * PI)) * 2.0 / 3.0;
        $ret += (160.0 * sin($lat / 12.0 * PI) + 320 * sin($lat * PI / 30.0)) * 2.0 / 3.0;
        return $ret;
    }

    private static function transformLng($lng, $lat)
    {
        $ret = 300.0 + $lng + 2.0 * $lat + 0.1 * $lng * $lng + 0.1 * $lng * $lat + 0.1 * sqrt(abs($lng));
        $ret += (20.0 * sin(6.0 * $lng * PI) + 20.0 * sin(2.0 * $lng * PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($lng * PI) + 40.0 * sin($lng / 3.0 * PI)) * 2.0 / 3.0;
        $ret += (150.0 * sin($lng / 12.0 * PI) + 300.0 * sin($lng / 30.0 * PI)) * 2.0 / 3.0;
        return $ret;
    }

    /**
     * 判断是否在国内，不在国内则不做偏移
     * @param lng
     * @param lat
     * @return boolean
     */
    public static function outChina($lng, $lat)
    {
        return ($lng < 72.004 || $lng > 137.8347) || (($lat < 0.8293 || $lat > 55.8271) || false);
    }


}