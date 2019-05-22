<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/3/29
 * Time: 16:36
 */

namespace App\Vendor\PayGateway;

interface GatewayInterface
{
    /**
     * 构造支付网关
     *
     * @return mixed
     */
    public function getGateway();

    public function setNotifyUrl(string $notifyUrl);

    public function setGatewayOrder($order);

    public function response();


}