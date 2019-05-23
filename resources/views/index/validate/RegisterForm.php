<?php
namespace app\index\validate;

use app\common\entity\UserInviteCode;
use app\index\model\SendCode;
use think\Validate;

class RegisterForm extends Validate
{
    protected $rule = [
        'invite_code' => 'require|checkInvite',
        'nick_name' => 'require|checkName',
        'mobile' => 'require|regex:^1[3,4,5,7,8,9][0-9]{9}$',
        'code' => 'require',
        'password' => 'require|min:6',
        'safe_password' => 'require|min:6'
    ];

    protected $message = [
        'invite_code.require' => '邀请码不能为空',
        'nick_name.require' => '昵称不能为空',
        'mobile.require' => '账号不能为空',
        'mobile.regex' => '账号格式不正确',
        'code.require' => '验证码不能为空',
        'password.require' => '登录密码不能为空',
        'password.min' => '登录密码至少为6位',
        'safe_password.require' => '交易密码不能为空',
        'safe_password.min' => '交易密码至少为6位'
    ];

    public function checkInvite($value, $rule, $data = [])
    {
        //判断邀请码是否存在
        $userInviteCode = new UserInviteCode();
        if (!$userInviteCode->getUserIdByCode($value)) {
            return '邀请码不存在';
        }
        return true;
    }

    public function checkMobile($value, $rule, $data = [])
    {
        if (\app\common\entity\User::checkMobile($value)) {
            return '此昵称已被注册，请重新填写';
        }
        return true;
    }
    
    public function checkName($value, $rule, $data = [])
    {
        if (\app\common\entity\User::checkName($value)) {
            return '此昵称已被注册，请重新填写';
        }
        return true;
    }

    public function checkCode($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'register');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }

    public function checkChange($value, $mobile)
    {
        $sendCode = new SendCode($mobile, 'change-password');
        if (!$sendCode->checkCode($value)) {
            return false;
        }
        return true;
    }


}