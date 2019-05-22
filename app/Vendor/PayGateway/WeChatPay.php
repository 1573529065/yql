<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/3/30
 * Time: 16:53
 */

namespace App\Vendor\PayGateway;


use App\Models\Pay\MemberOrder;
use App\Models\PointsMall\Order\ShopOrders;
use InvalidArgumentException;
use Omnipay\Omnipay;
use Omnipay\WechatPay\AppGateway;
use Omnipay\WechatPay\Message\CreateOrderRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WeChatPay implements GatewayInterface
{
    protected $gateway;

    public function getGateway($genre = 1)
    {
        switch ($genre) {
            case 1;
                $this->getWeChatPayApp();
                break;
        }

        return $this->gateway;
    }

    public function getWeChatPayApp()
    {
        $this->gateway = Omnipay::create('WechatPay_App');

        if ($this->gateway instanceof AppGateway) {
            $this->gateway->setAppId(env('APPID'));
            $this->gateway->setMchId(env('MCHID'));
            $this->gateway->setApiKey(env('API_KEY'));
        }
    }

    public function setNotifyUrl(string $notifyUrl)
    {
        if ($this->gateway instanceof AppGateway)
            $this->gateway->setNotifyUrl(env('APP_URL') . '/api/wechat/' . $notifyUrl);
    }

    public function setGatewayOrder($order)
    {
        if ($order instanceof ShopOrders) {
            $content = [
                'body' => $order->good->name,
                'out_trade_no' => $order->order_no,
                'total_fee' => (int)($order->pay_price * 100), //=0.01
                'spbill_create_ip' => request()->getClientIp(),
                'fee_type' => 'CNY'
            ];
        }

        if ($order instanceof MemberOrder) {
            $content = [
                'body' => $order->product->mp_name,
                'out_trade_no' => $order->mo_order_num,
                'total_fee' => (int)($order->mo_order_amount * 100),//0.01
                'spbill_create_ip' => request()->getClientIp(),
                'fee_type' => 'CNY',
            ];
        }

        if ($this->gateway instanceof AppGateway)
            $this->gateway = $this->gateway->purchase($content);
    }

    public function response(): array
    {
        if ($this->gateway instanceof CreateOrderRequest) {
            $response = $this->gateway->send();


            if (!$response->isSuccessful()) throw new HttpException(500);

            return [
                'pay_info' => $response->getAppOrderData()
            ];
        }
    }

    public function toXml($data): string
    {
        if (!is_array($data) || count($data) <= 0) {
            throw new InvalidArgumentException('Convert To Xml Error! Invalid Array!');
        }

        $xml = '<xml>';
        foreach ($data as $key => $val) {
            $xml .= is_numeric($val) ? '<' . $key . '>' . $val . '</' . $key . '>' :
                '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
        }
        $xml .= '</xml>';

        return $xml;
    }

    public function success(): Response
    {
        return Response::create(
            $this->toXml(['return_code' => 'SUCCESS', 'return_msg' => 'OK']),
            200,
            ['Content-Type' => 'application/xml']
        );
    }
}