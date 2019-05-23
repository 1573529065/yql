<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/3
 * Time: 17:18
 */

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Dal\UserBanned as UserBannedDal;
use App\Logic\System;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\User;
use App\Service\Validator;

class UserBanned extends BaseController
{
    protected $whiteList = ['add', 'user_log'];

    /**
     * 会员处理日志列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $id = intval($this->request->get('id'));
        $type = intval($this->request->get('type'));
        $admin_id = intval($this->request->get('admin_id'));
        $addtime = trim($this->request->get('addtime'));
        $endtime = trim($this->request->get('endtime'));

        $where = [];
        !empty($type) && $where['type'] = $type;
        $id > 0 && $where['user_id'] = $id;
        $admin_id > 0 && $where['admin_id'] = $admin_id;
        !empty($addtime) && $where['addtime >='] = strtotime($addtime);
        !empty($endtime) && $where['addtime <='] = strtotime($endtime);

        $list = UserBannedDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = UserBannedDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        // 用户信息获取
        $user_ids = array_filter(array_unique(array_column($list, 'user_id')));
        $userInfos = empty($user_ids) ? [] : User::fetchAll(['id IN' => $user_ids], '', 'id,nickname');
        $userInfos = Helper::arrayReindex($userInfos, 'id');

        // 管理员信息获取
        $admin_ids = array_filter(array_unique(array_column($list, 'admin_id')));
        $adminInfos = empty($admin_ids) ? [] : SystemAdmin::fetchAll(['id IN' => $admin_ids], '', 'id,nickname');
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        // 管理员列表
        $adminList = SystemAdmin::fetchAll(['status !=' => -1], '', 'id,nickname');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'userInfos' => $userInfos,
            'adminInfos' => $adminInfos,
            'adminList' => $adminList,
            'id' => $id,
            'type' => $type,
            'admin_id' => $admin_id,
            'addtime' => $addtime,
            'endtime' => $endtime
        ]);
    }

    /**
     * 单用户日志
     */
    public function user_log()
    {
        $user_id = $this->request->get('id');
        $pagesize = 10;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['user_id' => $user_id];
        $list = UserBannedDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = UserBannedDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        // 管理员信息获取
        $admin_ids = array_filter(array_unique(array_column($list, 'admin_id')));
        $adminInfos = empty($admin_ids) ? [] : SystemAdmin::fetchAll(['id IN' => $admin_ids], '', 'id,nickname');
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

//        1禁言、2锁定用户、3解锁用户、4冻结余额、5解锁余额
        foreach ($list as $key => $item) {
            $list[$key]['addtime'] = date('Y-m-d H:i:s', $item['addtime']);
            switch ($item['type']) {
                case 1:
                    $list[$key]['type'] = '禁言';
                    break;
                case 2:
                    $list[$key]['type'] = '锁定用户';
                    break;
                case 3:
                    $list[$key]['type'] = '解锁用户';
                    break;
                case 4:
                    $list[$key]['type'] = '冻结余额';
                    break;
                default:
                    $list[$key]['type'] = '解锁余额';
                    break;
            }
            $list[$key]['admin_name'] = $adminInfos[$item['admin_id']]['nickname'];
        }
        $data = [
            'list' => $list,
            'page' => $page,
            'adminInfos' => $adminInfos
        ];
        Helper::json(true, '', $data);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();

            $rules = [
                'user_id' => 'required `用户ID`',
                'type' => 'required `类型`',
                'remarks' => 'required `封停原因`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();

            $adminInfo = System::getLoginInfo();
            $data['admin_id'] = $adminInfo['id'];
            $data['addtime'] = time();

            UserBannedDal::insert($data);
            Helper::json(true);
        }
    }
}