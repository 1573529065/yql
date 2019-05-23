<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/3
 * Time: 16:05
 */

namespace App\Controller\Admin;

use App\Dal\BlockDevice as BlockDeviceDal;
use App\Dal\Channel;
use App\Dal\SystemAdmin;
use App\Dal\UserLoginLog;
use App\Logic\System;
use App\Service\Helper;
use App\Dal\BlockDeviceLog;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\User;

class BlockDevice extends BaseController
{
    protected $whiteList = ['autocomplete', 'toggle'];

    /**
     * 设备黑名单列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $block_id = intval($this->request->get('block_id'));
        $block_name = $this->request->get('block_name');

        $where = [];
        $block_id > 0 && $where['id'] = $block_id;

        $list = BlockDeviceDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = BlockDeviceDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        foreach ($list as $key => $value) {
            $blockDeviceLog = BlockDeviceLog::fetchOne(['block_id' => $value['id']], '*', 'id DESC');
            $list[$key]['remarks'] = $blockDeviceLog['remarks'];
            $list[$key]['admin_id'] = $blockDeviceLog['admin_id'];
            $list[$key]['type'] = $blockDeviceLog['type'];
            $list[$key]['addtime'] = $blockDeviceLog['addtime'];
        }

        // 管理员信息获取
        $admin_ids = array_filter(array_unique(array_column($list, 'admin_id')));
        $adminInfos = empty($admin_ids) ? [] : SystemAdmin::fetchAll(['id IN' => $admin_ids], '', 'id,nickname');
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'adminInfos' => $adminInfos,
            'p' => $curpage,
            'block_id' => $block_id,
            'block_name' => $block_name
        ]);
    }

    /**
     * 添加设备黑名单
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'imei' => 'required `设备号`',
                'remarks' => 'required|minlen:10 `封停原因`',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();

            $adminInfo = System::getLoginInfo();
            $insert = [
                'remarks' => $data['remarks'],
                'admin_id' => $adminInfo['id'],
                'addtime' => time(),
            ];

            $blockDeviceInfo = BlockDeviceDal::fetchOne(['imei' => $data['imei']]);

            if (!empty($blockDeviceInfo)) {
                if ($blockDeviceInfo['status'] == 1) {
                    Helper::json(false, '该设备已处于封禁状态中');
                } else {
                    BlockDeviceDal::update(['imei' => $data['imei']], ['status' => 1]);
                    $insert['block_id'] = $blockDeviceInfo['id'];
                    $insert['type'] = 2;
                    BlockDeviceLogDal::insert($insert);
                }
            } else {
                $insert_id = BlockDeviceDal::insert(['imei' => $data['imei']], ['status' => 1]);
                $insert['block_id'] = $insert_id;
                $insert['type'] = 1;
                BlockDeviceLog::insert($insert);
            }
            Helper::json(true);
        }
    }

    /**
     * 修改设备黑名单状态
     */
    public function edit_status()
    {
        if ($this->request->isPost()) {
            $v = new Validator();

            $rules = [
                'block_id' => 'required `黑名单表主键ID`',
                'remarks' => 'required `封停原因`',
                'type' => 'required `类型`',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $adminInfo = System::getLoginInfo();
            $data['admin_id'] = $adminInfo['id'];
            $data['addtime'] = time();

            BlockDeviceDal::update(['id' => $data['block_id']], ['status' => $this->request->getPost('status')]);
            BlockDeviceLog::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 相关账号
     */
    public function related()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $imei = $this->request->get('imei');
        $registered_user_ids = User::fetchAll(['reg_imei' => $imei], '', 'id');
        $registered_user_ids = array_column($registered_user_ids, 'id');

        $login_user_ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $userLoginLog = UserLoginLog::__fetchAll($i, ['imei' => $imei]);
            $user_ids = array_column($userLoginLog, 'user_id');
            foreach ($user_ids as $item) {
                $login_user_ids[] = $item;
            }
        }
        $ids = array_unique(array_merge($registered_user_ids, $login_user_ids));
        if (empty($ids)) {
            $list = [];
            $total = 0;
        } else {
            $list = User::fetchList(['id IN' => $ids], $offset, $pagesize);
            $total = User::count(['id IN' => $ids]);
        }

        $page = new Pagination($total, $pagesize, $curpage);

        // 渠道信息
        $channel_id = array_filter(array_unique(array_column($list, 'reg_channel')));
        $channelInfos = empty($channel_id) ? [] : Channel::fetchAll(['id IN' => $channel_id], '', 'id,name');
        $channelInfos = Helper::arrayReindex($channelInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'channelInfos' => $channelInfos,
            'p' => $curpage
        ]);
    }


    /**
     * 修改状态
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $field = $this->request->getPost('field');
        $status = $this->request->getPost('status');

        if ($id > 0) {
            BlockDeviceDal::update(['id' => $id], [$field => $status]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    /**
     * 模糊搜索设备号
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = BlockDeviceDal::fetchList(['imei LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value, imei as title');
        Helper::json(true, '', $list);
    }
}