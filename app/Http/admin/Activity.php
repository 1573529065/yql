<?php

namespace App\Controller\Admin;

use App\Dal\Activity as ActivityDal;
use App\Dal\SystemAdmin;
use App\Logic\System;
use App\Dal\Game;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class Activity extends BaseController
{
    protected $whiteList = ['toggle', 'autocomplete'];

    /**
     * 活动列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = [];
        $activity_id = $this->request->get('activity_id');
        $activity_name = $this->request->get('activity_name');
        $game_id = $this->request->get('game_id');
        $game_name1 = $this->request->get('game_name');
        $type = $this->request->get('type');
        $admin_id = $this->request->get('admin_id');
        $status = $this->request->get('status');

        !empty($activity_id) && $where['id'] = $activity_id;
        !empty($type) && $where['type'] = $type;
        !empty($admin_id) && $where['admin_id'] = $admin_id;
        $status != "" && $where['status'] = $status;

        if (!empty($game_id)) {
            $find_in_set = ActivityDal::formatRawFindInSetSql($game_id, 'game_ids');
            if (!empty($find_in_set)) {
                $where['__raw'] = $find_in_set;
            }
        }
        $list = ActivityDal::fetchList($where, $offset, $pagesize, 'id DESC');

        $adminIds = array_column($list, 'admin_id');
        $adminInfo = !empty($adminIds) ? SystemAdmin::fetchAll(['id IN' => $adminIds], '', 'id,username') : [];
        $adminInfo = Helper::arrayReindex($adminInfo, 'id');

        foreach ($list as $key => $value) {
            $ids = explode(',', $value['game_ids']);
            $game_name = Game::fetchAll(['id IN' => $ids], '', 'name');
            $list[$key]['game_name'] = implode(',', array_column($game_name, 'name'));
        }

        $adminList = SystemAdmin::fetchAll(['status !=' => -1], '', 'id,username');

        $total = ActivityDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'adminInfo' => $adminInfo,
            'adminList' => $adminList,
            'activity_id' => $activity_id,
            'activity_name' => $activity_name,
            'p' => $curpage,
            'game_id' => $game_id,
            'game_name' => $game_name1,
            'type' => $type,
            'admin_id' => $admin_id,
            'status' => $status,
        ]);
    }

    /**
     * 活动添加
     */
    public function add()
    {
        if ($this->request->getPost()) {
            $v = new Validator();
            $rules = [
                'game_list' => 'required `游戏名称`',
                'type' => 'required|intval `活动类型`',
                'title' => 'required `活动标题`',
                'start_time' => 'required `活动开始时间`',
                'end_time' => 'required `活动结束时间`',
                'des' => 'required `活动简介`',
                'status' => 'required `活动状态`'
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
                $gameInfo = Game::fetchOne(['name' => $value], 'id,name');
                if (empty($gameInfo)) {
                    Helper::json(false, $value . '不存在');
                }
                $game_ids[] = $gameInfo['id'];
            }
            $data['game_ids'] = implode(',', $game_ids);

            ActivityDal::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 活动编辑
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required `活动ID`',
                'game_list' => 'required `游戏名称`',
                'type' => 'required|intval `活动类型`',
                'title' => 'required `活动标题`',
                'start_time' => 'required `活动开始时间`',
                'end_time' => 'required `活动结束时间`',
                'des' => 'required `活动简介`',
                'status' => 'required `活动状态`'
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

            ActivityDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }

        $id = $this->request->get('id');
        $p = max($this->request->get('p'), 1);
        $info = ActivityDal::fetchOne(['id' => $id]);
        empty($info) && Helper::json(false, '活动不存在');
        $game_name = Game::fetchAll(['id IN' => explode(',', $info['game_ids'])], '', 'name');

        $info['game_name'] = implode(',', array_column($game_name, 'name'));
        $this->view->setVars([
            'info' => $info,
            'p' => $p,
        ]);
    }

    /**
     * 切换状态-删除活动
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $field = $this->request->getPost('field');
        $val = intval($this->request->getPost('val'));

        empty($id) && Helper::json(false, '缺少参数');

        ActivityDal::update(['id' => $id], [$field => $val]);
        Helper::json(true);
    }

    /**
     * 模糊搜索活动
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = ActivityDal::fetchList(['title LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,title');
        Helper::json(true, '', $list);
    }
}