<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/3
 * Time: 16:05
 */

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\BlockDeviceLog as BlockDeviceLogDal;

class BlockDeviceLog extends BaseController
{
    protected $whiteList = ['log_list'];


    /**
     * 操作日志
     */
    public function log_list()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $block_id = intval($this->request->get('block_id'));
        $list = BlockDeviceLogDal::fetchList(['block_id' => $block_id], $offset, $pagesize, 'id DESC');

        $total = BlockDeviceLogDal::count(['block_id' => $block_id]);
        $page = new Pagination($total, $pagesize, $curpage);

        // 管理员信息获取
        $admin_ids = array_filter(array_unique(array_column($list, 'admin_id')));
        $adminInfos = empty($admin_ids) ? [] : SystemAdmin::fetchAll(['id IN' => $admin_ids], '', 'id,nickname');
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'adminInfos' => $adminInfos,
            'p' => $curpage
        ]);
    }



}