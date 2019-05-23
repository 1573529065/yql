<?php
namespace app\index\model;

use think\facade\Session;

class SendCode
{
    public $mobile;
    public $code;
    public $type;

    public function __construct($mobile, $type)
    {
        $this->mobile = $mobile;
        $this->type   = $type;
    }

    public function send()
    {
        $this->setCode();
        //调用发生接口
        if ($this->sendCode()) {
            $this->saveCode(); //保存code值到session值
            return true;
        }

        return false;
    }

    public function getSessionName()
    {
        $sessionNames = [
            'register'        => 'register_code_',
            'change-password' => 'change-password_',
            'market'          => 'market_sale_',
            'market_sale'     => 'market_sale_ta_',
        ];

        return $sessionNames[$this->type] . $this->mobile;
    }

    private function getCode()
    {
        return Session::get($this->getSessionName());
    }

    private function setCode()
    {
        $this->code = mt_rand(100000, 999999);
    }

    private function saveCode()
    {
        Session::set($this->getSessionName(), $this->code);
    }

    private function sendCode()
    {
        // 阿里云发送
        $phone = $this->mobile;
        $code  = $this->code;
        $url   = 'http://' . $_SERVER['HTTP_HOST'] . '/aliyun/demo/sendSms.php?mobile=' . $phone . '&code=' . $code;
        @file_get_contents($url);
        return true;
    }

    public function checkCode($code)
    {
        $trueCode = $this->getCode();

        if ($trueCode == $code) {
            Session::delete($this->getSessionName());
            return true;
        }

        return false;
    }

}
