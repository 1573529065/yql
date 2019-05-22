<?php

namespace App\Vendor\Baidu;

use App\Unit\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 12/25/2017
 * Time: 2:34 PM
 * document url http://lbsyun.baidu.com/index.php?title=webapi
 * 百度地图Web服务API为开发者提供http/https接口，即开发者通过http/https形式发起检索请求，获取返回json或xml格式的检索数据。
 */
class MapApi
{
    private $url = "http://api.map.baidu.com/";
    private $ak = "7UTepZk33vGSNfqVMwQiX4rDW4Ym7d70";


    /**
     * http://lbsyun.baidu.com/index.php?title=webapi/guide/changeposition
     * @auther ifehrim@gmail.com
     * @param array|null $lat_lng 需转换的源坐标，多组坐标以“；”分隔 经度，纬度）
     * @param int $from 源坐标类型
     * @param int $to 目标坐标类型
     * @return array|null
     */
    public static function geoconv($lat_lng, $from = 1, $to = 5)
    {
        if (is_array($lat_lng)) {
            $cor = [];
            foreach ($lat_lng as $item) {
                $cor[] = implode(",", $item);
            }
            $lat_lng = implode(";", $cor);
        }
        $data = [
            "coords" => $lat_lng,
            "from" => $from,
            "to" => $to,
        ];
        $res = (new static())->request("geoconv/v1/", $data);

        if (isset($res["result"])) {
            $arr = [];
            if (is_array($res["result"])) {
                foreach ($res["result"] as $re) {
                    $arr[] = [$re["x"], $re["y"]];
                }
            }
            return $arr;
        };
        return null;
    }


    public static function place($address, $region, callable $callable = null)
    {
        $data = [
            "query" => $address,
            "region" => $region,
            "output" => "json",
        ];
        $res = (new static())->request("place/v2/search", $data);
        if (is_callable($callable)) {
            return call_user_func($callable, $res);
        }
        return $res;
    }

    public static function geocoder($address,callable $callable = null)
    {
        $data = [
            "address" =>$address,
            "output" =>"json",
            "ak" =>"S35rmEiLHeRUP24ye6LcDDWQFdadnnNO"
        ];
        $res = (new static())->request("geocoder/v2/", $data);
        if (is_callable($callable)) {
            return call_user_func($callable, $res);
        }
        return $res;
    }


    private function request($uri = null, $body = null, $method = 'GET', $headers = [], array $options = [])
    {
        $uri = $this->url . $this->make_url($uri);
        $method = strtoupper($method);
        $client = new Client();
        $response = null;
        if ($method === "GET") {
            $uri .= http_build_query($body);
            $response = $client->get($uri);
        }
        if ($method === "POST") {
            $response = $client->post($uri, $body);
        }
        if (!empty($response)) {
            if ($response->getStatusCode() != 200) {
                return $response->getStatusCode();
            }
            $data = Json::decode($response->getBody());
            return $data;
        }
        return null;

    }


    private function make_url($string)
    {
        return "{$string}?ak=" . $this->ak . "&";
    }


}