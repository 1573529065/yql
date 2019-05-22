<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/3/29
 * Time: 17:01
 */

namespace App\Vendor\PayGateway;


use App\Models\Pay\MemberOrder;
use App\Models\PointsMall\Order\ShopOrders;
use Omnipay\Alipay\AopAppGateway;
use Omnipay\Alipay\Requests\AopTradeAppPayRequest;
use Omnipay\Alipay\Responses\AopTradeAppPayResponse;
use Omnipay\Omnipay;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AliPay implements GatewayInterface
{
    protected $gateway;

    public function getGateway($genre = 1)
    {
        switch ($genre) {
            case 1;
                $this->getAliPayAopApp();
                break;
        }

        return $this->gateway;
    }

    public function getAliPayAopApp()
    {
        $this->gateway = Omnipay::create('Alipay_AopApp');

        if ($this->gateway instanceof AopAppGateway) {
            $this->gateway->setSignType('RSA'); //RSA/RSA2

            $this->gateway->setAppId(env("ALIAPPID"));

            $this->gateway->setPrivateKey(env('PRIVATEKEY'));

            $this->gateway->setAlipayPublicKey(env('ALIPAYPUBLICKEY'));
        }
    }

    public function setNotifyUrl(string $notifyUrl)
    {
        if ($this->gateway instanceof AopAppGateway)
            $this->gateway->setNotifyUrl(env('APP_URL') . '/api/alipay/'. $notifyUrl);
    }

    public function setGatewayOrder($order)
    {
        if ($order instanceof ShopOrders) {
            $content = [
                'subject' => $order->good->name,
                'out_trade_no' => $order->order_no,
                'total_amount' => $order->pay_price,
                'body' => $order->name,
                'product_code' => 'QUICK_MSECURITY_PAY',
            ];
        }

        if ($order instanceof MemberOrder) {
            $content = [
                'subject' => $order->product->mp_name,
                'out_trade_no' => $order->mo_order_num,
                'total_amount' => $order->mo_order_amount,//0.01
                'body' => $order->product->mp_name,
                'product_code' => 'QUICK_MSECURITY_PAY',
            ];
        }

        if ($this->gateway instanceof AopAppGateway)
            $this->gateway = $this->gateway->purchase()->setBizContent($content);
    }

    public function response()
    {
        if ($this->gateway instanceof AopTradeAppPayRequest) {
            $response = $this->gateway->send();
            if ($response instanceof AopTradeAppPayResponse)
                return [
                    'pay_info' => [
                        'alipay_url' => $response->getOrderString()
                    ]
                ];
        }
        throw new HttpException(500);
    }
}