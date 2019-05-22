<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 1/30/2018
 * Time: 5:56 PM
 */

namespace App\Vendor\AliCloud;

use App\Unit\Json;

/**
 * 阿里第三方天气
 * @auther ifehrim@gmail.com
 * Class WeatherApi
 * @package App\Vendor\AliCloud
 * @url https://market.aliyun.com/products/56928004/cmapi014123.html
 */
class WeatherApi
{
    public function __construct()
    {
        //todo other relation for ali cloud weather edit this area
    }

    public static function city($cityName)
    {
        $curl = curl_init();

        $cityName = urlencode($cityName);

        $host = "https://ali-weather.showapi.com";
        $path = "/spot-to-weather";
        $method = "GET";
        $appCode = '662ef4cb2b114aad82ce62771799e45f';

        $headers = [
            "Authorization:APPCODE " . $appCode,
        ];

        $queryParameter = "area=".$cityName."&areaid=&need3HourForcast=0&needAlarm=0&needHourData=1&needIndex=1&needMoreDay=0";

        $url = $host . $path . "?" . $queryParameter;
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST,$method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER,$headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response=curl_exec($curl);

        return Json::decode($response);
    }



}