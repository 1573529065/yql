<?php

namespace app\index\controller;

use app\common\entity\ShopList;
use app\common\entity\ShopDetail;
use think\facade\Session;
use think\Request;
use app\common\entity\Config;
use app\common\entity\User;
use app\common\entity\UserJifenLog;
use app\common\entity\UserYueLog;
use app\common\entity\Address;
use app\common\entity\ShopOrder;
use app\common\entity\YueOrders;
use app\common\service\Market\Auth;
use Think\Db;

class Shop extends Base
{

    public function initialize()
    {
        parent::initialize();
        if (in_array(request()->url(), ['/index/shop/shop_market', '/index/shop/add_buy_market', '/index/shop/add_sale', '/index/shop/sale', '/index/shop/buy'])) {
            $start = explode('|', Config::getValue('yue_start_time'));
            $startTime = strtotime(date('Y-m-d') . ' ' . $start[0]);
            $endTime = strtotime(date('Y-m-d') . ' ' . $start[1]);
            //开市时间
            if ((time() < $startTime) || (time() > $endTime)) {
                return alert('交易市场已关闭！', url('/index'));
            }
            //检测是否实名
            if ($this->userInfo->is_certification != User::AUTH_SUCCESS) {
                return alert('未实名！', url('/index'));
            }

        }

    }

    //商城首页
    public function index()
    {
        $click = input('id') ?? ShopList::where('is_show', 1)->field('id')->find()['id'];
//        $pudg = Config::getValue('shop_pudg');
        $shop = new ShopList();
//        $yue = User::field('magic')->where('id', $this->userId)->find();
        $list = $shop->field('id,name')->where(['is_show' => 1, 'pid' => 0])->select()->toArray();
        $pid = input('pid') ? input('pid') : $click;
        $list_two = $shop->field('id,name')->where('is_show = 1 and pid = :pid', ['pid' => $click])
            ->select()
            ->toArray();

        //相等说明是全部
        if ($pid == $click) {
            $type = $pid . ',';
            foreach ($list_two as $v) {
                $type .= $v['id'] . ',';
            }
            $type = trim($type, ',');
        } else {
            $type = $pid;
        }

        $detail_list = ShopDetail::field('*')
            ->where("is_del=1 and type in ({$type})")
            ->paginate(20, false, [
                'query' => ['id' => $click, 'pid' => $pid]
            ]);
        return $this->fetch('index', compact(array('list', 'click', 'detail_list', 'list_two', 'pid')));
    }

    //详情页
    public function detail()
    {
        $id = input('get.id');
        if (empty($id))
            return $this->redirect('index');
        $pudg = Config::getValue('shop_pudg');
        $list = ShopDetail::where('id = :id', ['id' => [$id, \PDO::PARAM_INT]])->where('is_del', 1)->find();
        if (empty($list))
            return $this->redirect('index');
        $info = User::field('yue,magic,shop_magic')->where('id', $this->userId)->find();
//        $list_address = Address::where('user_id', $this->userId)->select()->toArray();
        return $this->fetch('detail', ['list' => $list, 'pudg' => $pudg, 'info' => $info]);
    }

    //购买商品
    public function add_detail()
    {
        if (!\request()->isPost())
            return $this->redirect('index');
        $data = input('post.');
        if (empty($data))
            return json(['code' => 400, 'msg' => '数据错误！']);
        //检测是否添加发货地址
        $address = Address::where('user_id', $this->userId)->order('add_time desc')->find();
        if (!$address) {
            return json(['code' => 400, 'msg' => '未添加地址']);
        }
        $auth = new Auth();
        if (!$auth->check($data['psw'])) {
            return json(['code' => 400, 'msg' => '密码错误']);
        }
        $pudg = Config::getValue('shop_pudg');
        $list = ShopDetail::where('id = :id', ['id' => [$data['id'], \PDO::PARAM_INT]])->find();
        $info = User::field('yue,magic,shop_magic')->where('id', $this->userId)->find();
        if ($data['num'] > $list['stock'])
            return json(['code' => 400, 'msg' => '库存不足！']);
        if ($data['num'] <= 0)
            return json(['code' => 400, 'msg' => '数量过小！']);
//        //判断是否达到级别
//        if($info['yu_level'] < $list['level']){
//            return json(['code' => 400, 'msg' => '等级不足,无法购买！']);
//        }

//        $start = date('Y-m-d').' 00:00:00';
//        $end = date('Y-m-d', strtotime('+1 day')).' 00:00:00';
//        $count = ShopOrder::where('shop_id',$list['id'])
//                    ->where('user_id',$this->userId)
//                    ->where('add_time','>=',$start)
//                    ->where('add_time','<=',$end)
//                    ->sum('num');
//        $count = $count+$data['num'];
//        if($count > $list['limit']){
//            return json(['code' => 400, 'msg' => '超过每日限购数量！']);
//        }
//        $month_start = date('Y-m').'-00 00:00:00';
//        $month_end = date('Y-m', strtotime('+1 month')).'-00 00:00:00';
//        $month_count = ShopOrder::where('shop_id',$list['id'])
//                    ->where('user_id',$this->userId)
//                    ->where('add_time','>=',$month_start)
//                    ->where('add_time','<',$month_end)
//                    ->sum('num');
//        $month_count = $month_count+$data['num'];
//        if($month_count > $list['month_limit']){
//            return json(['code' => 400, 'msg' => '超过每月限购数量！']);
//        }

        if ($data['mode'] == 3) {//付款方式
            $price = $list['price_xuni'] * $data['num'];
            $pudg_price = round($pudg * $list['price_xuni'] * $data['num'], 2);
            $field = 'shop_magic';
            $type = \app\common\entity\UserMagicLog::TYPE_SHOP_M;
        } else {
            $price = $list['price_xuni'] * $data['num'];
            $pudg_price = round($pudg * $list['price_xuni'] * $data['num'], 2);
            $field = 'magic';
            $type = \app\common\entity\UserMagicLog::TYPE_SHOP;
        }

        $total = $price + $pudg_price;
        if ($info[$field] < $total)
            return json(['code' => 400, 'msg' => 'RCRC不足！']);
        $date = date('Y-m-d H:i:s');
        Db::startTrans();
        try {
            //更新用户币
            User::where('id', $this->userId)->dec($field, $total)->update();
            //更新库存
            ShopDetail::where('id = :id', ['id' => [$data['id'], \PDO::PARAM_INT]])->dec('stock', $data['num'])->update();
            //订单
            ShopOrder::insert([
                'shop_id' => $data['id'],
                'shop_name' => $list['name'],
                'shop_price' => $total,
                'num' => $data['num'],
                'payment_type' => $data['mode'],
                'status' => 1,
                'name' => $address['name'],
                'mobile' => $address['mobile'],
                'address' => $address['address'],
                'add_time' => $date,
                'user_id' => $this->userId
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollBack();
            return json(['code' => 400, 'msg' => '系统错误,请重试！']);
        }


        $userMagicLog = new \app\common\entity\UserMagicLog();
        //插入MBO余额日志
        $userMagicLog->save([
            'user_id' => $this->userId,
            'magic' => '-' . $total,
            'old' => $info[$field],
            'new' => ($info[$field] - $total),
            'remark' => $userMagicLog->getType(8),
            'type' => $type,
            'create_time' => time()
        ]);


        return json(['code' => 200, 'msg' => '购买成功！']);
    }

    public function order_list()
    {
        $list = ShopOrder::field('o.*,s.id as sid,s.img')->alias('o')
            ->where(['o.user_id' => $this->userId])
            ->Leftjoin('shop_detail s', 'o.shop_id = s.id')
            ->order('o.id desc')
            ->paginate(10);

        return $this->fetch('order_list', compact(['list']));
    }

    //余额交易市场
//    public function shop_market() {
//        $start = explode('|',Config::getValue('yue_start_time'));
//        $yue = User::where('id', $this->userId)->field('yue')->find();
//        $show_yue_all = Config::getValues(['yue_sell', 'yue_buy']);
//        $yue_sell = explode('|', $show_yue_all['yue_sell']);
//        $yue_buy = explode('|', $show_yue_all['yue_buy']);
//        $conf = Config::getValues(['yue_rmb_ratio','yue_sell_pudg','yue_bond']);
//        return $this->fetch('', ['yue' => $yue, 'yue_sell' => $yue_sell, 'yue_buy' => $yue_buy,'start'=>$start,'conf'=>$conf]);
//    }

    //添加买入余额交易订单
    public function add_buy_market()
    {
        if (!request()->isPost())
            return $this->redirect('shop_market');
        $data = input('post.');
        $yue_all = explode('|', Config::getValue('yue_buy'));
        if (!in_array($data['yue'], $yue_all))
            return json(['code' => 401, 'msg' => "交易金额未在范围内!"]);
        if (YueOrders::isExist($this->userId))
            return json(['code' => 403, 'msg' => '请完成交易后再发布！']);
        $re = YueOrders::insert([
            'user_id' => $this->userId,
            'number' => $data['yue'],
            'create_time' => time(),
            'status' => 1,
            'types' => 1,
            'total_price_china' => round(abs($data['rmb']), 2)
        ]);
        if ($re)
            return json(['code' => 200, 'msg' => "买入成功!", 'toUrl' => url('buy_list')]);

        return json(['code' => 402, 'msg' => "买入失败!"]);
    }

    //添加卖出余额交易订单
    public function add_sell_market()
    {
        if (!request()->isPost())
            return $this->redirect('shop_market');
    }

    //余额买入订单列表
    public function buy_list()
    {
        $list = YueOrders::field('*')->where([
            'user_id' => $this->userId,
            'types' => YueOrders::TYPE_BUY,
            'status' => YueOrders::STATUS_DEFAULT,
            'is_del' => 0
        ])->find();
        return $this->fetch('', ['list' => $list]);
    }

    //余额卖出订单列表
    public function sale_list()
    {
        $list = YueOrders::field('*')->where([
            'user_id' => $this->userId,
            'types' => YueOrders::TYPE_SALE,
            'status' => YueOrders::STATUS_DEFAULT,
            'is_del' => 0
        ])->find();
        return $this->fetch('', ['list' => $list]);
    }

    //订单软删除
    public function yue_order_del()
    {
        if (!request()->isPost())
            return $this->redirect('yue_order_index');

        $data = input('post.');
        $order = YueOrders::where("id=:id and is_del = 0", ['id' => [$data['id'], \PDO::PARAM_INT]])->field('id,user_id,status,bond,charge_number,number')->find();

        if (!$order)
            return json(['code' => 401, 'msg' => '订单不存在']);
        if ($order->user_id != $this->userId)
            return json(['code' => 402, 'msg' => '非法提交']);
        if ($order->status != YueOrders::STATUS_DEFAULT)
            return json(['code' => 403, 'msg' => '订单已在交易中，请在交易中去继续操作']);
        switch ($data['type']) {
            case 'buy':
                $result = YueOrders::where('id', $order->id)->update(['is_del' => 1]);
                break;
            case 'sale':
                Db::startTrans();
                try {
                    $result = true;
                    YueOrders::where('id', $order->id)->update(['is_del' => 1]);
                    User::where('id', $this->userId)->inc('yue', ($order['bond'] + $order['charge_number'] + $order['number']))->update();
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    $result = false;
                }
                break;

            default:
                return json(['code' => 405, 'msg' => '请求错误']);
                break;
        }
        if ($result)
            return json(['code' => 200, 'msg' => '取消成功']);
        return json(['code' => 400, 'msg' => '取消失败']);
    }

    //取消交易
    public function order_del()
    {
        if (!request()->isPost())
            return $this->redirect('trade_list');
        $data = input('post.');
        $order = YueOrders::where("id=:id and is_del = 0", ['id' => [$data['id'], \PDO::PARAM_INT]])->field('id,user_id,status,bond,charge_number,number,types,target_user_id')->find();
        if (!$order)
            return json(['code' => 401, 'msg' => '订单不存在']);
        if (!in_array($this->userId, [$order['user_id'], $order['target_user_id']]))
            return json(['code' => 402, 'msg' => '非法提交']);
        if (in_array($order['status'], [3, 4]))
            return json(['code' => 403, 'msg' => '订单状态发生变化！']);
        if ($order['types'] == 1) {
            $user_id = $order['target_user_id'];
        } else {
            $user_id = $order['user_id'];
        }
        //退还余额
        $yue = $order['charge_number'] + $order['number'] + $order['bond'];
        // 启动事务
        Db::startTrans();
        try {
            User::where('id', $user_id)->inc('yue', $yue)->update();
            YueOrders::where('id', $order['id'])->update(['is_del' => 1]);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['code' => 405, 'msg' => '取消失败！']);
        }
        return json(['code' => 200, 'msg' => '取消成功！']);
    }

    //余额求购列表
    public function yue_buy_list()
    {

        $list = YueOrders::where("y.user_id <> {$this->userId} and y.status = 1 and y.types=1 and is_del = 0 and u.status=1")
            ->alias('y')
            ->field("u.avatar,u.nick_name,y.id,y.number,y.total_price_china,(select count(id) from yue_orders where status = 4 and (user_id = $this->userId or target_user_id=$this->userId)) as amount")
            ->join('user u', 'u.id = y.user_id')
            ->select();
        if ($list)
            return json(['code' => 200, 'data' => $list]);
        return json(['code' => 400]);
    }

    //余额出售列表
    public function yue_sale_list()
    {
        $list = YueOrders::where("y.user_id <> {$this->userId} and y.status = 1 and y.types=2 and is_del = 0 and u.status=1")
            ->alias('y')
            ->field("u.avatar,u.nick_name,y.id,y.number,y.total_price_china,(select count(id) from yue_orders where status = 4 and (user_id = $this->userId or target_user_id=$this->userId)) as amount")
            ->join('user u', 'u.id = y.user_id')
            ->select();
        if ($list)
            return json(['code' => 200, 'data' => $list]);
        return json(['code' => 400]);
    }

    //余额出售
    public function buy()
    {
        if (!request()->isPost())
            return $this->redirect('shop_market');
        $data = input('post.');
        $order = YueOrders::where("id=:id and is_del = 0 and types=1", ['id' => [$data['id'], \PDO::PARAM_INT]])->field('id,user_id,status,number')->find();
        if (YueOrders::isExist($this->userId))
            return json(['code' => 408, 'msg' => '请完成交易后再发布！']);
        if (!$order)
            return json(['code' => 401, 'msg' => '该订单已被取消']);
        if ($order->user_id == $this->userId)
            return json(['code' => 402, 'msg' => '不能和自己交易']);
        if ($order->status != YueOrders::STATUS_DEFAULT)
            return json(['code' => 403, 'msg' => '订单已在交易中']);
        $user_info = User::where(['id' => $this->userId, 'status' => 1])->field('yue')->find();
        if (!$user_info) {
            session(null);
            return json(['code' => 405, 'msg' => '账号错误']);
        }

        $conf = Config::getValues(['yue_bond', 'yue_sell_pudg']);
        //计算手续费+保证金
        $pudg = round(($order['number'] * $conf['yue_sell_pudg']), 2);
        $pudgAll = $pudg + $conf['yue_bond'] + $order['number'];
        if ($user_info['yue'] < $pudgAll)
            return json(['code' => 406, 'msg' => '余额不足,请及时充值']);
        // 启动事务
        Db::startTrans();
        try {
            User::where('id', $this->userId)->dec('yue', $pudgAll)->update();
            YueOrders::where('id', $order['id'])->update([
                'target_user_id' => $this->userId,
                'status' => YueOrders::STATUS_PAY,
                'bond' => $conf['yue_bond'],
                'charge_number' => $pudg,
                'match_time' => time()
            ]);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['code' => 407, 'msg' => '出售失败！']);
        }
        return json(['code' => 200, 'msg' => '出售成功！']);
    }

    //买入
    public function sale()
    {
        if (!request()->isPost())
            return $this->redirect('shop_market');
        $data = input('post.');
        $auth = new Auth();
        if (!$auth->check($data['psw'])) {
            return json(['code' => 400, 'msg' => '密码错误']);
        }
        $order = YueOrders::where("id=:id and is_del = 0 and types=2", ['id' => [$data['id'], \PDO::PARAM_INT]])->field('id,user_id,status,number')->find();
        if (YueOrders::isExist($this->userId))
            return json(['code' => 408, 'msg' => '请完成交易后再发布！']);
        if (!$order)
            return json(['code' => 401, 'msg' => '该订单已被取消']);
        if ($order->user_id == $this->userId)
            return json(['code' => 402, 'msg' => '不能和自己交易']);
        if ($order->status != YueOrders::STATUS_DEFAULT)
            return json(['code' => 403, 'msg' => '订单已在交易中']);
        $re = YueOrders::where('id', $order['id'])->update([
            'target_user_id' => $this->userId,
            'status' => YueOrders::STATUS_PAY,
            'match_time' => time()
        ]);
        if ($re)
            return json(['code' => 200, 'msg' => '买入成功！']);
        return json(['code' => 405, 'msg' => '买入失败！']);
    }

    //卖出
    public function add_sale()
    {
        if (!request()->isPost())
            return $this->redirect('shop_market');
        $data = input('post.');
        if (YueOrders::isExist($this->userId))
            return json(['code' => 401, 'msg' => '请完成交易后再发布！']);
        $user_info = User::where(['id' => $this->userId, 'status' => 1])->field('yue')->find();
        if (!$user_info) {
            session(null);
            return json(['code' => 402, 'msg' => '账号错误']);
        }
        $conf = Config::getValues(['yue_bond', 'yue_sell_pudg']);
        //计算手续费+保证金
        $pudg = round(($data['yue'] * $conf['yue_sell_pudg']), 2);
        $pudgAll = $pudg + $conf['yue_bond'] + $data['yue'];
        if ($user_info['yue'] < $pudgAll)
            return json(['code' => 403, 'msg' => '余额不足,请及时充值']);
        // 启动事务
        Db::startTrans();
        try {
            User::where('id', $this->userId)->dec('yue', $pudgAll)->update();
            YueOrders::insert([
                'user_id' => $this->userId,
                'number' => $data['yue'],
                'create_time' => time(),
                'status' => 1,
                'types' => 2,
                'bond' => $conf['yue_bond'],
                'charge_number' => $pudg,
                'total_price_china' => round(abs($data['rmb']), 2)
            ]);
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['code' => 405, 'msg' => '卖出失败！']);
        }
        return json(['code' => 200, 'msg' => '卖出成功！', 'toUrl' => url('sale_list')]);
    }

    //余额交易中订单列表
    public function trade_list()
    {
        $list = YueOrders::field('*')->where([
            'user_id' => $this->userId,
            'is_del' => 0
        ])->where('status', 'in', '2,3')->find();
        $type_id = 'target_user_id';
        if (empty($list)) {
            $type_id = 'user_id';
            $list = YueOrders::field('*')->where([
                'target_user_id' => $this->userId,
                'is_del' => 0
            ])->where('status', 'in', '2,3')->find();
        }
        return $this->fetch('', ['list' => $list, 'user_id' => $this->userId, 'type_id' => $type_id]);
    }

    //交易中详情页
    public function trade_detail()
    {
        $data = input('get.');
        $list = YueOrders::where('id=:id and is_del = 0', ['id' => [$data['id'], \PDO::PARAM_INT]])->find();

        if (empty($list))
            return $this->redirect('trade_list');

        if (!in_array($this->userId, [$list['user_id'], $list['target_user_id']]))
            return $this->redirect('trade_list');
        //判断订单状态是否在交易中
        if (!in_array($list['status'], [YueOrders::STATUS_PAY, YueOrders::STATUS_CONFIRM]))
            return $this->redirect('trade_list');

        if ($data['type'] == 'user_id') {
            $user_id = $list['user_id'];
        } else {
            $user_id = $list['target_user_id'];
        }
        $userInfo = User::where('id', $user_id)->find();
        $img = false;
        //是否显示上传图片
        if ($list['types'] == 1) {
            //买入
            if ($list['user_id'] == $this->userId) {
                $img = true;
            }
        } else {
            //卖出
            if ($list['target_user_id'] == $this->userId) {
                $img = true;
            }
        }
        return $this->fetch('', ['userInfo' => $userInfo, 'list' => $list, 'img' => $img]);
    }

    //上传、修改凭证
    public function upImg()
    {
        $file = request()->file('image');
        // 移动到框架应用根目录/uploads/ 目录下
        $info = $file->validate(['size' => 1048576, 'ext' => 'jpg,png,gif'])->move('../public/uploads/yueimg/');
        if (!$info) {
            return json(['code' => 400, 'msg' => $file->getError()]);
        }
        $id = input('post.id');
        $vali = YueOrders::where('id=:id and is_del = 0 and status = 2', ['id' => [$id, \PDO::PARAM_INT]])->field('id,user_id,target_user_id')->find();
        if (!in_array($this->userId, [$vali['user_id'], $vali['target_user_id']]))
            return json(['code' => 401, 'msg' => '订单状态发生变化！']);
        $re = YueOrders::where('id', $vali['id'])->update(['img' => '/uploads/yueimg/' . $info->getSaveName()]);
        if ($re)
            return json(['code' => 200, 'url' => '/uploads/yueimg/' . $info->getSaveName()]);
        return json(['code' => 402, 'mg' => '上传失败！']);
    }

    /**
     * 确认付款
     * 确认收款
     */
    public function payment()
    {
        $id = input('post.id');
        $type = input('post.type');
        $info = YueOrders::where('id=:id and is_del = 0', ['id' => [$id, \PDO::PARAM_INT]])->field('number,user_id,status,target_user_id,charge_number,bond')->find();
        if (!$info)
            return json(['code' => 501, 'msg' => '订单不存在']);

        if (!in_array($this->userId, [$info['user_id'], $info['target_user_id']]))
            return json(['code' => 502, 'msg' => '非法提交']);

        switch ($type) {
            case "pay":
                if ($info['status'] != YueOrders::STATUS_PAY)
                    return json(['code' => 503, 'msg' => '订单状态发生变化!']);
                $re = YueOrders::where('id', $id)->update([
                    'status' => YueOrders::STATUS_CONFIRM,
                    'pay_time' => time()
                ]);
                if (!$re)
                    return json(['code' => 505, 'msg' => '付款失败!']);
                break;
            case "collect":
                if ($info['status'] != YueOrders::STATUS_CONFIRM)
                    return json(['code' => 503, 'msg' => '订单状态发生变化!']);
                $user_id = ($info['user_id'] == $this->userId) ? $info['target_user_id'] : $info['user_id'];
                // 启动事务
                Db::startTrans();
                try {
                    //退回保证金
                    User::where('id', $this->userId)->inc('yue', $info['bond'])->update();
                    //更新用户余额
                    User::where('id', $user_id)->inc('yue', $info['number'])->update();
                    YueOrders::where('id', $id)->update([
                        'status' => YueOrders::STATUS_FINISH,
                        'finish_time' => time()
                    ]);
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return json(['code' => 505, 'msg' => '付款失败!']);
                }
                $yue = $info['number'] + $info['charge_number'];
                $userYueLog = new UserYueLog();
                $data = [[
                    'user_id' => $this->userId,
                    'yue' => '-' . $yue,
                    'types' => 8,
                    'remark' => '余额卖出',
                    'create_time' => date('Y-m-d H:i:s'),
                    'just_yue' => $yue
                ],
                    [
                        'user_id' => $user_id,
                        'yue' => $info['number'],
                        'types' => 9,
                        'remark' => '余额买入',
                        'create_time' => date('Y-m-d H:i:s'),
                        'just_yue' => $info['number']
                    ]
                ];
                //插入余额日志
                $userYueLog->saveAll($data);
                break;
            default:
                return json(['code' => '506', 'msg' => '请求错误!']);
                break;
        }
        return json(['code' => 200, 'msg' => '付款成功!']);
    }

    /**
     * 已完成列表
     */
    public function complete_list()
    {
        $list = YueOrders::where("is_del = 0 and status =" . YueOrders::STATUS_FINISH . " and (user_id = {$this->userId} or target_user_id = {$this->userId})")->order('id desc')->paginate(20);
        return $this->fetch('', ['list' => $list, 'user_id' => $this->userId]);
    }

    //完成列表详情
    public function complete_details()
    {
        $id = input('get.id');
        $info = YueOrders::where('id = :id and is_del = 0', ['id' => [$id, \PDO::PARAM_INT]])->find();
        //判断存在
        if (!$info)
            return $this->redirect('complete_list');
        //判断用户
        if (!in_array($this->userId, [$info['user_id'], $info['target_user_id']]))
            return $this->redirect('complete_list');
        //判断状态
        if (!in_array($info['status'], [YueOrders::STATUS_FINISH]))
            return $this->redirect('complete_list');

        //得到对方ID
        $user_id = ($info['user_id'] == $this->userId) ? $info['target_user_id'] : $info['user_id'];

        $userInfo = User::where('id', $user_id)->find();


        return $this->fetch('', ['order' => $info, 'userInfo' => $userInfo]);
    }

    /**
     * 确认收货
     */
    public function collect_goods()
    {
        if (!request()->isPost())
            return $this->redirect('order_list');
        $id = input('post.id');
        if (empty($id)) return json(['code' => 500, 'msg' => 'error！']);
        $shopOrder = new ShopOrder();
        $shopData = $shopOrder->where("id=:id and user_id = :user_id and status=:status and is_del = 0",
            ['id' => [$id, \PDO::PARAM_INT], 'user_id' => $this->userId, 'status' => $shopOrder::STATUS_TWO])
            ->find();
        if (!$shopData) {
            return json(['code' => 500, 'msg' => 'error！']);
        }
        $shopDetail = ShopDetail::where("id ={$shopData['shop_id']} and is_del = 1")->find();
        if (!$shopDetail || ($shopDetail['is_back'] == 0)) {
            $suanli = 0;
        } else {
            $suanli = $shopDetail['power_num'];
        }
        $data['collect_time'] = date('Y-m-d H:i:s');
        $data['status'] = $shopOrder::STATUS_THREE;
        Db::startTrans();
        try {
            $shopOrder->where("id = {$shopData['id']}")->update($data);
            //更新用户算力
            User::where('id', $this->userId)->inc('product_rate', $suanli)->update();
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['code' => 501, 'msg' => "收货失败！"]);
        }
        return json(['code' => 200, 'msg' => '收货成功！']);

    }

}
