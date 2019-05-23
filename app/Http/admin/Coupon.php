<?php

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Logic\System;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\Coupon as CouponDal;
use App\Dal\Game;
use App\Dal\CouponReceive;

class Coupon extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 优惠券列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $game_id = $this->request->get('game_id');
        $game_name = $this->request->get('game_name');
        $admin_id = $this->request->get('admin_id');
        $status = $this->request->get('status');

        $where = ['status != ' => -1];
        !empty($admin_id) && $where['admin_id'] = $admin_id;
        $status != "" && $where['status'] = $status;
        if (!empty($game_id)) {
            $find_in_set = CouponDal::formatRawFindInSetSql($game_id, 'game_ids');
            if (!empty($find_in_set)) {
                $where['__raw'] = $find_in_set;
            }
        }

        $list = CouponDal::fetchList($where, $offset, $pagesize, 'id DESC');

        $adminIds = array_column($list, 'admin_id');
        $adminInfo = !empty($adminIds) ? SystemAdmin::fetchAll(['id IN' => $adminIds], '', 'id,username') : [];
        $adminInfo = Helper::arrayReindex($adminInfo, 'id');

        foreach ($list as $key => $value) {
            $ids = explode(',', $value['game_ids']);
            $gameInfo = Game::fetchAll(['id IN' => $ids], '', 'name');
            $list[$key]['game_name'] = implode(',', array_column($gameInfo, 'name'));
            $list[$key]['used_num'] = CouponReceive::count(['coupon_id' => $value['id'], 'used' => 1]);
        }

        $adminList = SystemAdmin::fetchAll(['status !=' => -1], '', 'id,username');

        $total = CouponDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'adminInfo' => $adminInfo,
            'adminList' => $adminList,
            'game_id' => $game_id,
            'game_name' => $game_name,
            'admin_id' => $admin_id,
            'status' => $status,
            'p' => $curpage,
        ]);
    }

    /**
     * 优惠券添加
     */
    public function add()
    {
        if ($this->request->getPost()) {
            $v = new Validator();
            $rules = [
                'game_list' => 'required `游戏名称`',
                'value' => 'required `抵扣金额`',
                'condition' => 'required `满减点`',
                'start_time' => 'required `开始时间`',
                'end_time' => 'required `结束时间`',
                'device_only' => 'required `设备限制`',
                'source' => 'required `费用承担`',
                'status' => 'required `状态`'
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $adminInfo = System::getLoginInfo();

            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['addtime'] = time();
            $data['admin_id'] = $adminInfo['id'] ?? 0;
            $game_list = array_unique(explode(',', $data['game_list']));
            unset($data['game_list']);

            $game_ids = [];
            foreach ($game_list as $key => $value) {
                $gameInfo = Game::fetchOne(['name' => $value], 'id,name,game_type');
                if (empty($gameInfo)) {
                    Helper::json(false, $value . '不存在');
                }
                if ($gameInfo['game_type'] == 1) {
                    Helper::json(false, 'BT手游: ' . $value . ' 不可添加优惠券');
                }
                $game_ids[] = $gameInfo['id'];
            }
            $data['game_ids'] = implode(',', $game_ids);

            CouponDal::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 代金券编辑
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required `代金券ID`',
                'game_list' => 'required `游戏名称`',
                'value' => 'required `抵扣金额`',
                'condition' => 'required `满减点`',
                'start_time' => 'required `开始时间`',
                'end_time' => 'required `结束时间`',
                'device_only' => 'required `设备限制`',
                'source' => 'required `费用承担`',
                'status' => 'required `状态`'
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);

            $game_list = array_unique(explode(',', $data['game_list']));
            unset($data['game_list']);

            $game_ids = [];
            foreach ($game_list as $key => $value) {
                $gameInfo = Game::fetchOne(['name' => $value], 'id,name');
                if (empty($gameInfo)) {
                    Helper::json(false, $value . '不存在');
                }
                $game_ids[] = $gameInfo['id'];
            }
            $data['game_ids'] = implode(',', $game_ids);
            CouponDal::update(['id' => $data['id'], 'state' => 0], $data);
            Helper::json(true);
        }

        $id = $this->request->get('id');
        $p = max(intval($this->request->get('p')), 1);
        $where = [
            'id' => $id,
            'status != ' => -1
        ];

        $info = CouponDal::fetchOne($where);
        empty($info) && Helper::json(false, '优惠券不存在');
        if ($info['state'] == 1) {
            $this->showError('已通过审核,不可编辑');
        }
        $game_name = Game::fetchAll(['id IN' => explode(',', $info['game_ids'])], '', 'name');

        $info['game_list'] = implode(',', array_column($game_name, 'name'));
        $this->view->setVars([
            'info' => $info,
            'p' => $p
        ]);
    }

    /**
     * 修改状态-删除
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $field = $this->request->getPost('field');
        $val = intval($this->request->getPost('val'));

        empty($id) && Helper::json(false, '缺少参数');

        CouponDal::update(['id' => $id], [$field => $val]);
        Helper::json(true);
    }

}