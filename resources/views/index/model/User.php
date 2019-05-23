<?php
namespace app\index\model;

use app\common\entity\Config;
use app\common\entity\Log;
use app\common\entity\Orders;
use app\common\entity\UserInviteCode;
use app\common\entity\UserProduct;
use app\common\service\Users\Cache;
use app\common\service\Users\Identity;
use app\common\service\Users\Service;
use think\Db;
use think\facade\Request;
use think\cache\driver\Redis;
use think\facade\Session;
class User
{

    public function checkRegisterOpen()
    {
        $registerOpen = Config::getValue('register_open');
        if ($registerOpen) {
            return true;
        }
        return false;
    }

    public function checkIp()
    {
        $ipTotal = Config::getValue('register_ip');
        $ip = Request::ip();
        $total = \app\common\entity\User::where('register_ip', $ip)->count();
        if ($ipTotal > $total) {
            return true;
        }
        return false;
    }

    public function doRegister($data)
    {
        $entity = new \app\common\entity\User();
        $service = new Service();
        $parentId = (new UserInviteCode())->getUserIdByCode($data['invite_code']);
        $entity->mobile = $data['mobile'];
        $entity->nick_name = $data['nick_name'];
        $entity->password = $service->getPassword($data['password']);
        $entity->trad_password = $service->getPassword($data['safe_password']);
        $entity->register_time = time();
        $entity->register_ip = Request::ip();
        $entity->status = \app\common\entity\User::STATUS_DEFAULT;
        $entity->is_certification = \app\common\entity\User::AUTH_ERROR;
        $entity->pid = $parentId;

        if ($entity->save()) {
            $inviteCode = new UserInviteCode();
            $inviteCode->saveCode($entity->id);
            $this->sendRegisterReward($entity);

            //清除全部会员的缓存
            $cache = new Cache();
            $cache->delCache();
            $redis = new Redis();
            if($redis->hexists(PID,$parentId)){
               $val = $redis->hget(PID,$parentId);
               $redis->hset(PID,$parentId,$val.','.$entity->id);
            }else{
              $redis->hset(PID,$parentId,$entity->id);
            }
            $user = \app\common\entity\User::where('id', $entity->id)->find();
            //保存session
            Session::set('flow_box_member', [
            'id' => $entity->id,
            'mobile' => $data['mobile'],
        ]);
            return true;
        }

        return false;

    }

    //注册赠送
    public function sendRegisterReward($user)
    {
        $registerReward = Config::getValue('register_send_produc');
        if (!$registerReward) {
            return true;
        }
        $number = Config::getValue('register_send_product_num');
        if ($number < 1) {
            return true;
        }
        $level = Config::getValue('product_id');

        //送矿机
        $model = new UserProduct();
        for ($i = 0; $i < $number; $i++) {
            $result = $model->addInfo($user->id, $level, UserProduct::TYPE_REGISTER);

            if (!$result) {
                Log::addLog(Log::TYPE_PRODUCT, '注册送矿机失败', [
                    'user_id' => $user->id,
                    'mobile' => $user->mobile
                ]);
            }
        }
    }

    /**
     * 得到用户的详细信息
     */
    public function getInfo($id)
    {
        return \app\common\entity\User::where('id', $id)->find();
    }

    /**
     * 银行卡号 微信号 支付宝账号 唯一
     */
    public function checkMsg($type, $account, $id = '')
    {
        return \app\common\entity\User::where("$type", $account)->where('id', '<>', $id)->find();
    }

    public function doLogin($account, $password)
    {
        $user = \app\common\entity\User::where('nick_name', $account)->find();
        if (!$user) {
            return '账号或者密码错误';
        }
        $model = new \app\common\service\Users\Service();
        if (!$model->checkPassword($password, $user)) {
            return '账号或者密码错误';
        }

        if ($user->status == \app\common\entity\User::STATUS_FORBIDDED) {
            return '账号已被禁用';
        }

        //保存session
        $identity = new Identity();
        $identity->saveSession($user);

        return true;
    }


}