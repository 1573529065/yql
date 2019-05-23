<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/25
 * Time: 9:36
 */

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\ChannelOrderLog as ChannelOrderLogDal;


class ChannelOrderLog extends BaseController
{

    /**
     * 订单日志
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $order_no = $this->request->get('order_no');
        $admin_id = $this->request->get('admin_id');
        $nickname = $this->request->get('nickname');
        $finance_type = $this->request->get('finance_type');
        $log_type = $this->request->get('log_type');

        $where = [];
        if (!empty($order_no)) {
            $where['order_no'] = $order_no;
        }
        if (!empty($admin_id)) {
            $where['admin_id'] = $admin_id;
        }
        if (!empty($finance_type)) {
            $where['finance_type'] = $finance_type;
        }
        if (!empty($log_type)) {
            $where['log_type'] = $log_type;
        }

        $list = ChannelOrderLogDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = ChannelOrderLogDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $adminIds = array_unique(array_column($list, 'admin_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'order_no' => $order_no,
            'admin_id' => $admin_id,
            'nickname' => $nickname,
            'finance_type' => $finance_type,
            'log_type' => $log_type,
            'adminInfos' => $adminInfos,
        ]);
    }

}