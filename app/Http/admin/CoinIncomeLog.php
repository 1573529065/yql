<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/8
 * Time: 9:54
 */

namespace App\Controller\Admin;

use App\Dal\CoinIncomeLog as CoinIncomeLogDal;
use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\CoinIncomeType;
use App\Dal\User;

class CoinIncomeLog extends BaseController
{
    protected $whiteList = ['autocomplete'];
    /**
     * 平台币获取订单列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $order_no = $this->request->get('order_no');
        $user_id = $this->request->get('user_id');
        $user_name = $this->request->get('user_name');
        $income_type = $this->request->get('income_type');
        $charge = $this->request->get('charge');
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');


        $where = [];
        !empty($order_no) && $where['order_no'] = $order_no;
        !empty($user_id) && $where['user_id'] = $user_id;
        !empty($income_type) && $where['income_type'] = $income_type;
        if ($charge == 1) {
            $where['admin_id !='] = 0;
        }elseif($charge == 2){
            $where['admin_id ='] = 0;
        }

        if (!empty($start_time)) {
            $where['addtime >='] = strtotime($start_time);
        }
        if (!empty($end_time)) {
            $where['addtime <='] = strtotime($end_time);
        }
        $list = CoinIncomeLogDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = CoinIncomeLogDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        // 用户信息
        $userIds = array_column($list, 'user_id');
        $userInfos = !empty($userIds) ? User::fetchAll(['id IN' => $userIds], '', 'id,username') : [];
        $userInfos = Helper::arrayReindex($userInfos, 'id');
        // 操作人
        foreach ($list as $key => $item) {
            if (!empty($item['admin_id']) && $item['admin_id'] != ''){
                $adminInfo = SystemAdmin::fetchOne(['id' => $item['admin_id']], 'id,username');
                $list[$key]['admin_name'] = $adminInfo['username'];
            }
        }
        // 获取类型
        $incomeTypeList = CoinIncomeType::fetchAll([], '', 'id,name,currency');
        $incomeType = Helper::arrayReindex($incomeTypeList, 'id');

        // 交易总金额
        $total_amount = CoinIncomeLogDal::sum('amount', $where);
        // 成功金额
        $where['status'] = 1;
        $success_amount = CoinIncomeLogDal::sum('amount', $where);
        // 失败金额
        $error_amount = $total_amount - $success_amount;

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'incomeTypeList' => $incomeTypeList,
            'incomeType' => $incomeType,
            'userInfos' => $userInfos,
            'total_amount' => $total_amount,
            'success_amount' => $success_amount,
            'error_amount' => $error_amount,
            'order_no' => $order_no,
            'user_id' => $user_id,
            'user_name' => $user_name,
            'income_type' => $income_type,
            'charge' => $charge,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
    }

    // 搜索订单号
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = CoinIncomeLogDal::fetchList(['order_no LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,order_no as title');
        Helper::json(true, '', $list);
    }
}