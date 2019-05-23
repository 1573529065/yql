<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/8
 * Time: 9:54
 */

namespace App\Controller\Admin;

use App\Dal\Order as OrderDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\User;
use App\Dal\Game;

class Order extends BaseController
{
    protected $whiteList = ['autocomplete'];

    /**
     * 游戏充值明细
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = [];
        $order_no = $this->request->get('order_no');
        $user_id = $this->request->get('user_id');
        $user_name = $this->request->get('user_name');
        $game_id = $this->request->get('game_id');
        $game_name = $this->request->get('game_name');
        $status = $this->request->get('status');
        $pay_type = $this->request->get('pay_type');
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');

        !empty($order_no) && $where['order_no'] = $order_no;
        !empty($user_id) && $where['user_id'] = $user_id;
        !empty($game_id) && $where['game_id'] = $game_id;
        $status != '' && $where['status'] = $status;
        !empty($pay_type) && $where['pay_type'] = $pay_type;

        if (!empty($start_time)) {
            $where['addtime >='] = strtotime($start_time);
        }
        if (!empty($end_time)) {
            $where['addtime <='] = strtotime($end_time);
        }
        $list = OrderDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = OrderDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        // 用户信息
        $userIds = array_column($list, 'user_id');
        $userInfos = !empty($userIds) ? User::fetchAll(['id IN' => $userIds], '', 'id,username') : [];
        $userInfos = Helper::arrayReindex($userInfos, 'id');

        // 游戏信息
        $gameIds = array_column($list, 'game_id');
        $gameInfos = !empty($gameIds) ? Game::fetchAll(['id IN' => $gameIds], '', 'id,name') : [];
        $gameInfos = Helper::arrayReindex($gameInfos, 'id');
        // 商品总金额
        $total_amount = OrderDal::sum('amount', $where);
        // 充值币抵消
        $deduction_charge = OrderDal::sum('deduction_charge', $where);
        // 赠送币抵消
        $deduction_giving = OrderDal::sum('deduction_giving', $where);
        // 实际支付
        $actual_pay = OrderDal::sum('online_pay', $where);

        // 未支付概率
        $totalNum = OrderDal::count($where);
        $no_pay = 0;
        $err_pay = 0;
        if ($totalNum != 0 ){
            $where['status'] = 0;
            $no_pay = round(OrderDal::count($where) / $totalNum * 100, 2);
            // 异常概率
            $where['status'] = -1;
            $err_pay = round(OrderDal::count($where) / $totalNum * 100, 2);
        }

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'userInfos' => $userInfos,
            'gameInfos' => $gameInfos,
            'total_amount' => $total_amount,
            'deduction_charge' => $deduction_charge,
            'deduction_giving' => $deduction_giving,
            'online_pay' => $actual_pay,
            'no_pay' => $no_pay,
            'err_pay' => $err_pay,

            'order_no' => $order_no,
            'user_id' => $user_id,
            'user_name' => $user_name,
            'game_id' => $game_id,
            'game_name' => $game_name,
            'status' => $status,
            'pay_type' => $pay_type,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
    }

    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = OrderDal::fetchList(['order_no LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,order_no as title');
        Helper::json(true, '', $list);
    }

}