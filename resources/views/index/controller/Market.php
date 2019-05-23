<?php

namespace app\index\controller;

use app\common\entity\Config;
use app\common\entity\MarketPrice;
use app\common\entity\Orders;
use app\common\entity\User;
use app\common\service\Market\Auth;
use app\index\model\SendCode;
use think\Request;

class Market extends Base
{
    public function initialize()
    {
        $authModel = new Auth();
        $authModel->identity();
        parent::initialize();
    }

    public function index()
    {
        $marketPrice = new MarketPrice();
        $prices = $marketPrice->getCurrentPrice();

        $magic = User::where('id', $this->userId)->value('magic');

        //获取24小时交易量和交易额
        $marketNumber = Config::getValue('market_day_total');
        $marketTotal = Config::getValue('market_day_magic');

        if (!$marketNumber) {
            //统计
            $marketNumber = Orders::where('create_time', '>=', time() - 24 * 3600)->count();
        }
        if (!$marketTotal) {
            //统计
            $marketTotal = Orders::where('create_time', '>=', time() - 24 * 3600)->sum('number');
        }
        return $this->fetch('index', [
            'prices' => $prices,
            'market_price_rate' => Config::getValue('market_rate'),
            'number_min' => Config::getValue('market_min'),
            'number_max' => Config::getValue('market_max'),
            'magic' => $magic,
            'market_number' => $marketNumber ?: 0,
            'market_total' => $marketTotal ?: 0,
        ]);
    }

    /**
     * 买入
     */
    public function buy(Request $request)
    {
        if ($request->isPost()) {
            $validate = $this->validate($request->post(), '\app\index\validate\BuyForm');
            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }
            //进行购买
            try {

                $model = new \app\index\model\Market();
                $model->buy($request->post('price'), $request->post('number'), $this->userId);

                return json(['code' => 0, 'message' => '买入成功', 'toUrl' => url('trade/buy')]);
            } catch (\Exception $e) {

                return json(['code' => 1, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * 买ta
     */
    public function buyTa(Request $request)
    {
        if ($request->isPost()) {
            $orderId = intval($request->post('order_id'));

            $model = new \app\index\model\Market();

            try {

                $model->buyTa($orderId, $this->userId);
                return json(['code' => 0, 'message' => '买入成功']);
            } catch (\Exception $e) {
                return json(['code' => 1, 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * 卖ta
     */
    public function saleTa(Request $request)
    {
        if ($request->isPost()) {

            $orderId = intval($request->post('order_id'));

//            //判断验证码
//            $code   = $request->post('code');
//            $smodel = new SendCode($this->userInfo->mobile, 'market_sale');

//            if (!$smodel->checkCode($code)) {
//                return json(['code' => -1, 'message' => '验证码输入不正确']);
//            }

            $model = new \app\index\model\Market();
            try {
                $model->checkSaleTa($orderId, $this->userId);
            } catch (\Exception $e) {
                return json(['code' => 1, 'message' => $e->getMessage()]);
            }

            //卖ta
            $result = $model->saleTa($orderId, $this->userId);

            if ($result) {
                return json(['code' => 0, 'message' => '出售成功']);
            }

            return json(['code' => 1, 'message' => '出售失败']);
        }
    }

    /**
     * 卖出
     */
    public function sale(Request $request)
    {
        if ($request->isPost()) {
            $market = new \app\index\model\Market();
            if ($market->checkOrder($this->userId)) {
                return json(['code' => 1, 'message' => '你还有交易未完成，请先去完成交易']);
            }

//            //判断验证码
//            $code  = $request->post('code');
//            $model = new SendCode($this->userInfo->mobile, 'market');
//
//            if (!$model->checkCode($code)) {
//                return json(['code' => -1, 'message' => '验证码输入不正确']);
//            }

            $validate = $this->validate($request->post(), '\app\index\validate\SaleForm');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            $model = new \app\index\model\Market();
            $result = $model->sale($request->post('price'), $request->post('number'), $this->userId);

            if ($result) {
                return json(['code' => 0, 'message' => '卖出成功', 'toUrl' => url('trade/sale')]);
            }

            return json(['code' => 1, 'message' => '卖出失败']);

        }
    }

    //买它 发送短信验证码
    public function sendSale(Request $request)
    {
        if ($request->isPost()) {

            $orderId = intval($request->post('order_id'));
            $model = new \app\index\model\Market();

            try {
                $model->checkSaleTa($orderId, $this->userId);
                //发送验证码

                $model = new SendCode($this->userInfo->mobile, 'market_sale');

                if ($model->send()) {
                    return json(['code' => 0, 'message' => 'success']);
                    //return json(['code' => 0, 'message' => $model->code]);
                }

                return json(['code' => 1, 'message' => '验证码发送失败']);
            } catch (\Exception $e) {

                return json(['code' => 1, 'message' => $e->getMessage()]);
            }

        }
    }

    //卖出 发送短信验证码
    public function send(Request $request)
    {
        if ($request->isPost()) {
            //验证用户是否有交易中
            $market = new \app\index\model\Market();
            if ($market->checkOrder($this->userId)) {
                return json(['code' => 1, 'message' => '你还有交易未完成，请先去完成交易']);
            }

            $validate = $this->validate($request->post(), '\app\index\validate\SaleForm');

            if ($validate !== true) {
                return json(['code' => 1, 'message' => $validate]);
            }

            //发送验证码

            $model = new SendCode($this->userInfo->mobile, 'market');

            if ($model->send()) {
                return json(['code' => 0, 'message' => 'success']);
                //return json(['code' => 0, 'message' => $model->code]);
            }

            return json(['code' => 1, 'message' => '验证码发送失败']);

        }
    }

    //求购列表
    public function buyList(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);
        $mobile = $request->get('mobile', '');

        $model = new \app\index\model\Market();
        $list = $model->getList(Orders::TYPE_BUY, $this->userId, $page, $limit, $mobile);

        return json([
            'code' => 0,
            'message' => 'success',
            'data' => $list,
        ]);
    }

    //出售列表

    public function saleList(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $model = new \app\index\model\Market();
        $mobile = $request->get('mobile', '');
        $list = $model->getList(Orders::TYPE_SALE, $this->userId, $page, $limit, $mobile);

        return json([
            'code' => 0,
            'message' => 'success',
            'data' => $list,
        ]);
    }
}
