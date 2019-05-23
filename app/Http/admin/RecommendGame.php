<?php

namespace App\Controller\Admin;

use App\Dal\RecommendGame as RecommendGameDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\Game;
use App\Dal\RecommendType;


class RecommendGame extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 推荐位游戏列表
     */
    public function index()
    {
        $pagesize = 15;
        $lastPage = max($this->request->get('last_page'), 1);
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;
        $type_id = $this->request->get('type_id');
        $platform = $this->request->get('platform');

        $where = [
            'type_id' => $type_id
        ];
        if ($platform > 0) {
            $where['platform'] = $platform;
        }

        $list = RecommendGameDal::fetchList($where, $offset, $pagesize, 'sort ASC, id DESC');

        // 取出游戏名
        $game_ids = array_column($list, 'game_id');
        $games = $game_ids ? Game::fetchAll(['id IN' => $game_ids], '', 'id,name') : [];
        $games = Helper::arrayReindex($games, 'id');

        // 取出类型名
        $recommend_type_name = RecommendType::fetchOne($type_id, 'name');

        $total = RecommendGameDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'games' => $games,
            'platform' => $platform,
            'recommend_type_name' => $recommend_type_name['name'] ?? '',
            'type_id' => $type_id,
            'lastPage' => $lastPage,
            'p' => $curpage,
        ]);
    }

    /**
     * 修改推荐位游戏
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();

            $rules = [
                'id' => 'required|intval',
                'tag' => 'required',
                'des' => 'required',
                'img' => 'is_url'
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }

            $data = $v->getData();
            RecommendGameDal::update($data['id'], $data);
            Helper::json(true);
        }

        $id = $this->request->get('id');
        $type_id = $this->request->get('type_id');
        $p = max(intval($this->request->get('p')), 1);
        $last_page = $this->request->get('last_page');

        $info = RecommendGameDal::fetchOne($id);
        empty($info) && Helper::json(false, '数据不存在');

        $game_name = Game::fetchOne($info['game_id'], 'name'); //

        $this->view->setVars([
            'info' => $info,
            'game_name' => $game_name['name'] ?? '',
            'p' => $p,
            'type_id' => $type_id,
            'last_page' => $last_page,
        ]);
    }

    /**
     * 移除推荐位游戏(物理删除)
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            Helper::json(false, '参数错误');
        }

        RecommendGameDal::delete(['id IN' => $ids]);
        Helper::json(true);
    }

    /**
     * 修改单个字段的值
     */
    public function toggle()
    {
        $v = new Validator();

        $rules = [
            'id' => 'required|intval|gt:0',
            'field' => 'required',
            'val' => 'required'
        ];

        if (!$v->setRules($rules)->validate($this->request->getPost())) {
            Helper::json(false, $v->getErrorString());
        }

        $data = $v->getData();
        RecommendGameDal::update($data['id'], [$data['field'] => $data['val']]);

        Helper::json(true);
    }


}