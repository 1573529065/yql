<?php

namespace App\Controller\Admin;

use App\Dal\Gift as GiftDal;
use App\Dal\GiftCode;
use App\Dal\GiftType;
use App\Dal\Game;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class Gift extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 礼包列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $game_id = $this->request->get('game_id');
        $game_name = $this->request->get('game_name');

        $where['status !='] = -1;
        if (!empty($game_id)) {
            $where['game_id'] = $game_id;
        }
        $list = GiftDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = GiftDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $weekStartTime = strtotime(date('Y-m-d H:i:s', strtotime('-7 day')));
        $monthStartTime = strtotime(date('Y-m-d H:i:s', strtotime('-1 month')));
        foreach ($list as $key => $value) { // 周 月的定义还需要问产品
            $list[$key]['weekNum'] = GiftCode::count(['gift_id' => $value['id'], 'user_id >' => 0, 'receive_time >=' => $weekStartTime, 'receive_time <=' => time()]);
            $list[$key]['monthNum'] = GiftCode::count(['gift_id' => $value['id'], 'user_id >' => 0, 'receive_time >=' => $monthStartTime, 'receive_time <=' => time()]);
        }

        $giftTypeIds = array_filter(array_unique(array_column($list, 'type_id')));
        $giftType = !empty($giftTypeIds) ? GiftType::fetchAll(['id IN' => $giftTypeIds], '', 'id,name') : [];
        $giftType = Helper::arrayReindex($giftType, 'id');

        $gameIds = array_filter(array_unique(array_column($list, 'game_id')));
        $gameInfos = !empty($gameIds) ? Game::fetchAll(['id IN' => $gameIds], '', 'id,name') : [];
        $gameInfos = Helper::arrayReindex($gameInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'giftType' => $giftType,
            'gameInfos' => $gameInfos,
            'p' => $curpage,
            'game_id' => $game_id,
            'game_name' => $game_name,
        ]);
    }

    /**
     * 添加礼包
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:20 `礼包名称`',
                'type_id' => 'required|intval `礼包类型`',
                'game_id' => 'required|intval `游戏不存在`',
                'platform' => 'required|intval|gte:0 `适用系统`',
                'start_time' => 'required `开始时间`',
                'end_time' => 'required `结束时间`',
                'device_only' => 'required `设备限制`',
                'is_common' => 'required `通用卡号`',
                'code_list' => 'required `兑换码`',
                'des' => 'required `礼包内容`',
                'usage' => 'required `使用方法`',
                'status' => 'required `礼包状态`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());

            $data = $v->getData();
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['addtime'] = time();
            $codeList = explode("\n", $data['code_list']);
            unset($data['code_list']);

            if ($data['is_common'] != 0) {
                $data['total'] = $data['is_common'];
            } else {
                $data['total'] = count($codeList);
            }
            $insertGameId = GiftDal::insert($data);

            for ($i = 0; $i < $data['total']; $i++) {
                $giftCodeData = [
                    'gift_id' => $insertGameId,
                    'code' => ($data['is_common'] != 0) ? trim($codeList[0]) : $codeList[$i],
                    'addtime' => time(),
                ];
                GiftCode::insert($giftCodeData);
            }
            Helper::json(true);
        }
        $giftType = GiftType::fetchAll(['status' => 1], '', 'id,name');
        $this->view->setVars([
            'giftType' => $giftType
        ]);
    }

    /**
     * 编辑礼包
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required `ID不能为空`',
                'name' => 'required|maxlen:20 `礼包名称`',
                'type_id' => 'required|intval `礼包类型`',
                'game_id' => 'required|intval `游戏不存在`',
                'platform' => 'required|intval|gte:0 `适用系统`',
                'start_time' => 'required `开始时间`',
                'end_time' => 'required `结束时间`',
                'device_only' => 'required `设备限制`',
                'is_common' => 'required `通用卡号`',
                'des' => 'required `礼包内容`',
                'usage' => 'required `使用方法`',
                'status' => 'required `礼包状态`',
            ];

            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());

            $data = $v->getData();
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);

            GiftDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }

        $id = $this->request->get('id');
        $p = max($this->request->get('p'), 1);
        $info = GiftDal::fetchOne($id);
        empty($info) && Helper::json(false, '游戏不存在');

        $gameInfo = Game::fetchOne(['id' => $info['game_id']], 'id,name');
        $giftType = GiftType::fetchAll(['status' => 1], '', 'id,name');

        $this->view->setVars([
            'info' => $info,
            'giftType' => $giftType,
            'game_name' => $gameInfo['name'],
            'p' => $p,
        ]);
    }

    /**
     * 删除礼包
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        empty($ids) && Helper::json(false, '参数错误');

        GiftDal::update(['id IN' => $ids], ['status' => -1]);
        Helper::json(true);
    }

    /**
     * 切换状态
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $status = intval($this->request->getPost('status')) ? 1 : 0;

        ($id <= 0) && Helper::json(false, '参数错误');

        GiftDal::update(['id' => $id], ['status' => $status]);
        Helper::json(true);
    }
}