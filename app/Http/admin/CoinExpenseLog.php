<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/8
 * Time: 9:54
 */

namespace App\Controller\Admin;

use App\Dal\CoinExpenseLog as CoinExpenseLogDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\User;
use App\Dal\CoinExpenseType;


class CoinExpenseLog extends BaseController
{
    protected $whiteList = ['autocomplete'];

    /**
     * 平台币消耗订单列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $order_no = $this->request->get('order_no');
        $user_id = $this->request->get('user_id');
        $user_name = $this->request->get('user_name');
        $expense_type = $this->request->get('expense_type');
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');


        $where = [];
        !empty($order_no) && $where['order_no'] = $order_no;
        !empty($user_id) && $where['user_id'] = $user_id;
        !empty($expense_type) && $where['expense_type'] = $expense_type;
        if (!empty($start_time)) {
            $where['addtime >='] = strtotime($start_time);
        }
        if (!empty($end_time)) {
            $where['addtime <='] = strtotime($end_time);
        }
        $list = CoinExpenseLogDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = CoinExpenseLogDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        // 用户信息
        $userIds = array_column($list, 'user_id');
        $userInfos = !empty($userIds) ? User::fetchAll(['id IN' => $userIds], '', 'id,username') : [];
        $userInfos = Helper::arrayReindex($userInfos, 'id');

        // 消耗类型
        $expenseTypeList = CoinExpenseType::fetchAll([], '', 'id,name');
        $expenseType = Helper::arrayReindex($expenseTypeList, 'id');

        // 交易总金额
        $total_amount = CoinExpenseLogDal::sum('amount', $where);
        // 成功金额
        $where['status'] = 1;
        $success_amount = CoinExpenseLogDal::sum('amount', $where);
        // 失败金额
        $error_amount = $total_amount - $success_amount;

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'expenseTypeList' => $expenseTypeList,
            'expenseType' => $expenseType,
            'userInfos' => $userInfos,
            'total_amount' => $total_amount,
            'success_amount' => $success_amount,
            'error_amount' => $error_amount,
            'order_no' => $order_no,
            'user_id' => $user_id,
            'user_name' => $user_name,
            'expense_type' => $expense_type,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
    }

    // 搜索订单号
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = CoinExpenseLogDal::fetchList(['order_no LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,order_no as title');
        Helper::json(true, '', $list);
    }

}