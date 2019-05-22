<?php
/**
 * Created by PhpStorm.
 * User: DELL
 * Date: 2018/12/11
 * Time: 16:30
 */

namespace App\Vendor\ExpressDelivery;


class Express
{

    /**
     * 快递订阅推送
     * @param $code
     * @param $num
     * @param array $attr_param
     * @return mixed
     */
    public static function expressSubscription($code, $num, $attr_param = [])
    {
//        $url = 'https://poll.kuaidi100.com/poll'; // 正式环境请求地址
        $url = env('EXPRESS_REQUEST') ?? 'http://www.kuaidi100.com/poll';
        $parameters = ["callbackurl" => env('EXPRESS_CALLBACK'), 'resultv2' => 1];
        $attr_param && $parameters = array_merge($parameters, $attr_param);
        $post_data["schema"] = 'json';
        $param = [
            "company" => $code,
            "number" => $num,
            //"from" => "广东深圳",
            //"to" => "北京朝阳",
            "key" => 'WyAmdHaH6172',
            "parameters" => $parameters
        ];
        $post_data["param"] = json_encode($param);
        $o = "";
        foreach ($post_data as $k => $v) {
            $o .= "$k=" . urlencode($v) . "&";        //默认UTF-8编码格式
        }
        $post_data = substr($o, 0, -1);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        //返回提交结果，格式与指定的格式一致（result=true代表成功）
        $result = curl_exec($ch);
        \Log::debug('物流订阅日志:', [
            'url' => $url,
            'param' => json_encode($param),
            'result' => json_encode($result)
        ]);

        return $result;
    }
}