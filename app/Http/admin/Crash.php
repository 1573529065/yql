<?php

namespace App\Controller\Admin;

use App\Dal\Crash as CrashDal;
use App\Dal\User;
use App\Service\Pagination;
use App\Service\Helper;

//崩溃日志管理
class Crash extends BaseController
{
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = [];
        $platform = trim($this->request->get('platform'));//平台
        $type = trim($this->request->get('type'));//错误类型
        $version = trim($this->request->get('version'));//版本号
        $imei = trim($this->request->get('imei'));//imei
        $agent = trim($this->request->get('agent'));//机型
        $startdate = trim($this->request->get('startdate'));//开始日期
        $enddate = trim($this->request->get('enddate'));//载止日期
        $md5 = trim($this->request->get('md5'));


        //时间筛选
        if ($startdate && $enddate) {
            $where['addtime BETWEEN'] = [strtotime($startdate), strtotime($enddate) + (24 * 3600)];
        }
        if ($platform) {
            $where['platform'] = $platform;
        }
        if ($type) {
            $where['type'] = $type;
        }
        if ($md5) {
            $where['md5'] = $md5;
        }
        if ($version) {
            $where['version'] = $version;
        }
        if ($imei) {
            $where['imei'] = $imei;
        }
        if ($agent) {
            $where['agent LIKE'] = "%{$agent}%";
        }
        $list = CrashDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = CrashDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        //拼凑数据
        $uids = $list ? array_unique(array_column($list, 'user_id')) : [];
        $users = $uids ? User::fetchAll(['id IN' => $uids]) : [];
        $users = $users ? array_combine(array_column($users, 'id'), $users) : [];

        foreach ($list as $k=>$v){
            $list[$k]['nickname'] = $users[$v['user_id']]['nickname'] ?? '';
        }

        $this->view->setVars([
            'platform' => $platform,
            'type' => $type,
            'version' => $version,
            'imei' => $imei,
            'agent' => $agent,
            'md5' => $md5,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'list' => $list,//当前列表数据
            'page' => $page->generate(),
            'total' => $total
        ]);
    }

    //删除
    public function delete()
    {
        //清空
        $act = $this->request->getPost('act');
        if ($act == 'truncate') {
            try {
                CrashDal::truncate();
                Helper::json(true);
            } catch (\Exception $e) {
                Helper::json(false, $e->getMessage());
            }
        }

        //批量删除
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            CrashDal::delete(['id IN' => $ids]);
        }
        Helper::json(true);
    }
}