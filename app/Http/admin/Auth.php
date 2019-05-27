<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Logic\System;
use App\Service\Captcha;
use App\Service\Helper;
use App\Service\Validator;

class Auth extends Controller
{

    //登录
    public function login()
    {
        if (System::isLogin()) {
            $this->response->redirect('index/index');
        }

        if ($this->request->isPost()) {
            $valid = new Validator();
            $rules = [
                'username' => 'required|minlen:4|maxlen:16 `用户名`',
                'password' => 'required|minlen:6|maxlen:16 `密码`',
                'captcha' => 'trim|strtoupper `验证码`',
            ];
            if ($valid->setRules($rules)->validate($this->request->getPost())) {
                $username = $valid->getDataByField('username');
                $password = $valid->getDataByField('password');
                $captcha = $valid->getDataByField('captcha');

                $session = Di::getDefault()->get('session');
                $sess_captcha = $session->get('captcha');
                $session->remove('captcha');
                if (!$captcha || $captcha != $sess_captcha) {
                    Helper::json(false, "验证码错误");
                }

                $checkResult = System::checkLogin($username, $password, $this->request->getClientAddress(true));
                switch ($checkResult) {
                    case System::LOGIN_SUCCESS:
                        Helper::json(true);
                        break;
                    case System::LOGIN_GROUP_DISABLED:
                        Helper::json(false, "您所在的权限组不存在或已被禁用");
                        break;
                    case System::LOGIN_USER_DISABLED:
                        Helper::json(false, "您的账号已被停用");
                        break;
                    default:
                        Helper::json(false, "用户名或密码错误");
                        break;
                }
            } else {
                Helper::json(false, $valid->getErrorString());
            }
        }
    }

    //退出登录
    public function logout()
    {
        System::logout();
        Helper::json(true);
    }

    //显示验证码
    public function captcha()
    {
        $captcha = new Captcha();
        $session = Di::getDefault()->get('session');
        $session->set('captcha', strtoupper($captcha->getCode()));
        $captcha->show();
    }
}