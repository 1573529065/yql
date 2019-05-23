<?php

namespace App\Controller\Admin;

use App\Dal\Gift as GiftDal;
use App\Dal\GiftCode as GiftCodeDal;
use App\Dal\GiftType;
use App\Dal\Game;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class GiftCode extends BaseController
{
    protected $whiteList = ['toggle', 'autocomplete'];

    /**
     * 兑换码列表
     */
    public function index()
    {
        $pagesize = 15;
        $last_page = max(intval($this->request->get('last_page')), 1);
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $giftId = $this->request->get('id');
        $code = trim($this->request->get('code'));
        $user_id = trim($this->request->get('user_id'));
        $imei = trim($this->request->get('imei'));
        $start_time = trim($this->request->get('start_time'));
        $end_time = trim($this->request->get('end_time'));

        $where = [
            'status !=' => -1,
            'gift_id' => $giftId,
        ];
        if (!empty($code)) {
            $where['code'] = $code;
        }
        if (!empty($user_id)) {
            $where['user_id'] = $user_id;
        }
        if (!empty($imei)) {
            $where['imei'] = $imei;
        }
        if (!empty($start_time)) {
            $where['receive_time >='] = $start_time;
        }
        if (!empty($end_time)) {
            $where['receive_time <='] = $end_time;
        }
        $list = GiftCodeDal::fetchList($where, $offset, $pagesize, 'receive_time DESC, id DESC');

        $total = GiftCodeDal::count(['gift_id' => $giftId, 'status !=' => -1]);
        $giftInfo = $giftId ? GiftDal::fetchOne(['id' => $giftId], 'id,name,game_id') : [];
        $gameInfo = isset($giftInfo['game_id']) ? Game::fetchOne(['id' => $giftInfo['game_id']], 'id,name') : [];
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'giftInfo' => $giftInfo,
            'last_page' => $last_page,
            'p' => $curpage,
            'gameName' => $gameInfo['name'] ?? '',
            'giftId' => $giftId,
            'code' => $code,
            'user_id' => $user_id,
            'imei' => $imei,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
    }

    /**
     * 添加兑换码
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'gift_id' => 'required|intval|gte:0 `礼包ID`',
                'code' => 'required `兑换码`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());
            $data = $v->getData();
            $data['addtime'] = time();

            GiftCodeDal::insert($data);
            GiftDal::inc($data['gift_id'], ['total' => +1]);
            Helper::json(true, '', ['id' => $data['gift_id']]);
        }
        $gift_id = $this->request->get('id');
        empty($gift_id) && Helper::json(false, '礼包ID不能为空');
        $this->view->setVar('gift_id', $gift_id);
    }


    /**
     * 删除兑换码
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        empty($ids) && Helper::json(false, '参数错误');

        GiftCodeDal::update(['id IN' => $ids], ['status' => -1]);
        $receive_num = 0;
        foreach ($ids as $key => $value) {
            $giftCodeInfo = GiftCodeDal::fetchOne(['id' => $value], 'id,gift_id,user_id');
            if ($giftCodeInfo['user_id'] > 0) {
                $receive_num++;
            }
        }
        $num = count($ids);
        GiftDal::inc($giftCodeInfo['gift_id'], ['total' => -$num, 'receive_count' => -$receive_num]); // 修改礼包兑换码总数量,已领取数量
        Helper::json(true);
    }

    /**
     * 设备码搜索
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = GiftCodeDal::fetchList(['code LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,code as title');
        Helper::json(true, '', $list);
    }

}