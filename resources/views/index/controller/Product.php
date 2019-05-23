<?php

namespace app\index\controller;

use app\admin\exception\AdminException;
use think\Request;
use app\common\service\Users\Service;
use app\common\entity\User;
use app\common\entity\Config;
use app\common\entity\UserProduct;
use app\common\entity\UserMagicLog;
use app\common\entity\UserJewelLog;
use app\common\entity\Product as productModel;
use think\Db;
use app\common\entity\Address;
use think\cache\driver\Redis;
use app\common\entity\UserYueLog;
use app\common\entity\ShopOrder;
use app\common\entity\UserTotalLog;

class Product extends Base
{
    public function index(Request $request)
    {
        return $this->fetch('index', [
            'list' => productModel::order('sort')->where('status', 1)->select()
        ]);
    }


    //购买云矿机
    public function buy(Request $request)
    {

        //检测是否添加发货地址
        $address = Address::where('user_id', $this->userId)->order('add_time desc')->find();
        if (!$address) {
            return json(['code' => 1, 'message' => '未添加地址']);
        }
        $product_id = $request->post("product_id");
        //得到能量详细信息
        $productModel = new productModel();
        $product = $productModel->getInfoById($product_id);
        if (!$product) {
            return json(['code' => 1, 'message' => '该矿机类型不存在']);
        }
        if ($product['status'] == 0) {
            return json(['code' => 1, 'message' => '该矿机以下架']);
        }
        $user = new User();
        //获取用户详细信息
        $userInfo = $user->where('id', $this->userId)->field('*')->find();
        //检测是否实名
        if ($userInfo['is_certification'] != User::AUTH_SUCCESS) {
            return json(['code' => 1, 'message' => '请实名认证']);
        }
        if ($userInfo['yue'] < $product['price']) {
            return json(['code' => 1, 'message' => '余额不足,请充值']);
        }
        //计算上级可获得数量
        $user_parent = $user->get_parent($userInfo['pid'], $product['price']);
        $doubles = Config::getValues(['day_double', 'sum_double', 'three_double', 'kg_double']);
        //矿机记录
        $user_product = new UserProduct();
        Db::startTrans();
        try {
            $user->where('id', $this->userId)
                ->dec('yue', $product['price'])
                ->inc('product_rate', ($product->getRate()))
                ->inc('three_conf', ($product->price * $doubles['three_double']))
                ->inc('kg_conf', ($product->price * $doubles['kg_double']))
                ->inc('everyday_conf', ($product->price * $doubles['day_double']))
                ->inc('sum_lucre_conf', ($product->price * $doubles['sum_double']))
                ->update();

            $user->isUpdate(true)->saveAll($user_parent);

            $user_product->createInfo($product, $this->userId, 2);
            //插入订单记录
            ShopOrder::insert([
                'shop_name' => $product['product_name'],
                'shop_price' => $product['price'],
                'num' => 1,
                'payment_type' => 2,
                'name' => $address['name'],
                'address' => $address['address'],
                'mobile' => $address['mobile'],
                'add_time' => date('Y-m-d H:i:s'),
                'user_id' => $this->userId,
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'message' => "购买失败"]);
        }
        UserYueLog::insert([
            'user_id' => $this->userId,
            'yue' => '-' . $product['price'],
            'remark' => "购买" . $product['product_name'],
            'types' => UserYueLog::TYPE_KG,
            'create_time' => date('Y-m-d H:i:s'),
            'just_yue' => $product['price']
        ]);
        $total_log = $magic_log = [];
        foreach ($user_parent as $k => $v) {
            //总业绩 日志
            $total_log[$k] = [
                'user_id' => $v['id'],
                'num' => $product['price'],
                'remark' => "下级{$this->userInfo->mobile}购买矿机",
                'types' => UserTotalLog::TYPE_SYSTEM,
                'create_time' => time()
            ];
            if ($v['diff'] > 0) {
                $magic_log[$k] = [
                    'user_id' => $v['id'],
                    'magic' => $v['diff'],
                    'old' => 0,
                    'new' => 0,
                    'remark' => '推荐奖励',
                    'types' => UserMagicLog::TYPE_REWARD,
                    'create_time' => time()
                ];
            }
        }
        UserMagicLog::insertAll($magic_log);
        UserTotalLog::insertAll($total_log);
        return json(['code' => 0, 'message' => '购买成功']);
    }


    /**
     * 购买能量
     */
    public function recharge(Request $request)
    {
        $product_id = $request->post("product_id");
        //得到能量详细信息
        $productModel = new productModel();
        $product = $productModel->getInfoById($product_id);
        if (!$product) {
            return json(['code' => 1, 'message' => '该矿机类型不存在']);
        }

        //获取用户详细信息
        $user = new \app\index\model\User();
        $userInfo = $user->getInfo($this->userId);

        //查看该用户等级最多拥有能量数量 高级 中级 初级 迷你
        $config = new Config();
        $max_box = $config->getMaxBox($userInfo["level"]);
        $should_max_box = 0;
        //这是为了将该有的能量数量以及用户拥有的能量数量与产品id进行对应 高级4 中级3 初级2 迷你1
        if ($product_id == 4) {
            $should_max_box = $max_box[0];
        }
        if ($product_id == 3) {
            $should_max_box = $max_box[1];
        }
        if ($product_id == 2) {
            $should_max_box = $max_box[2];
        }
        if ($product_id == 1) {
            $should_max_box = $max_box[3];
        }

        //查询出该用户所拥有的能量数量
        $user_product = new UserProduct();
        $boxList = $user_product->getBox($userInfo['id'], $product_id);

        //判断用户是否可以买这个规格的能量
        if (count($boxList) >= $should_max_box) {
            return json(['code' => 1, 'message' => '您拥有的该类型能源发生器已经达到上限了']);
        }
        //得到用户购买能量方式 1能量 2宝石
        $type = $request->post("type");

        //判断用户账户能量数量是否足够支付购买该能量
        if (($userInfo['magic'] < $product['price']) && $type == 1) {
            return json(['code' => 1, 'message' => '您账户能量数量不够！！']);
        }

        //判断用户账户宝石数量是否足够支付购买该能量
        if (($userInfo['jewel'] < $product['jewel_price']) && $type == 2) {
            return json(['code' => 1, 'message' => '您账户宝石数量不够！！']);
        }

        //扣除账户能量 该用户开采率增加 增加user_product记录
        Db::startTrans();
        try {
            $user_magic_log = new UserMagicLog();
            $user_jewel_log = new UserJewelLog();
            if ($type == 1) {
                //增加能量流水记录
                $res3 = $user_magic_log->addInfo($this->userId, "购买能源发生器", $product['price'] * (-1), $userInfo['magic'], $userInfo['magic'] - $product['price'], 2);
                $userInfo->magic = $userInfo->magic - $product->price;
            } elseif ($type == 2) {
                //增加宝石流水记录
                $res3 = $user_jewel_log->addInfo($this->userId, "购买能源发生器", $product['jewel_price'] * (-1), $userInfo['jewel'], $userInfo['jewel'] - $product['jewel_price']);
                $userInfo->jewel = $userInfo->jewel - $product->jewel_price;
            }

            if (!$res3) {
                throw new \Exception('能量流失记录增加失败');
            }

            $userInfo->product_rate = $userInfo->product_rate + $product->getRate();

            $res = $userInfo->save();

            if (!$res) {
                throw new \Exception('用户资料修改失败');
            }

            //增加user_product记录
            $res2 = $user_product->createInfo($product, $this->userId, 2);

            if ($userInfo['pid'] && $type == 1) {
                //得到后台配置的直推奖励
                $val = Config::getValue("rules_spread_rate");
                //增加上级的能量收益
                //烧伤制度：上级得到的能量收益 = 用户购买的能量等级 > 父级拥有最高级能量 ? 父级拥有最高能量价格*后台设置的直推奖励百分比 : 用户购买能量价格*后台设置的直推奖励百分比
                //1得到父级的详细信息
                $userInfo_p = $user->getInfo($userInfo['pid']);

                //2.查看父级是否拥有比用户购买能量更高级的能量
                $moreBox = UserProduct::where("product_id", ">=", $product_id)->where("user_id", $userInfo['pid'])->find();

                $old_magic = $userInfo_p->magic;
                //父级存在比用户更高等级的能量
                if ($moreBox) {
                    $userInfo_p->magic = $userInfo_p->magic + ($val / 100) * $product['price'];
                    $price = $product['price'];
                    $userInfo_p->save();
                } else {
                    //查询出父级最小等级的能量
                    $moreBox = UserProduct::where("product_id", "ELT", $product_id)->where("user_id", $userInfo['pid'])->order("product_id desc")->select();
                    $minbox = $moreBox[0] ?? 0;
                    if ($minbox) {
                        $product_min = $productModel->getInfoById($minbox->product_id);
                        //父级最小能量价格*后台设置的直推奖励百分比的能量
                        $userInfo_p->magic = $userInfo_p->magic + ($val / 100) * $product_min['price'];
                        $price = $product_min['price'];
                    }

                }
                if ($userInfo_p->save() === false) {
                    throw new \Exception('增加父级能量数量失败');
                }
                if (isset($price) && $price > 0) {
                    //增加父级能量流水记录
                    $resp = $user_magic_log->addInfo($userInfo_p->id, UserMagicLog::TYPE_REWARD, $price * ($val / 100), $old_magic, $userInfo_p->magic, 4);
                    if (!$resp) {
                        throw new \Exception('增加父级能量流水记录失败');
                    }
                }
            }


            if (!$res2) {
                throw new \Exception('增加user_product记录失败');
            }


            Db::commit();
            return json(['code' => 0, 'message' => '购买成功']);

        } catch (\Exception $e) {
            Db::rollback();
            return json(['code' => 1, 'message' => $e->getMessage()]);
        }
    }

}