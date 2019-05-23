<?php

namespace App\Controller\Admin;

use App\Dal\GameCate as GameCateDal;
use App\Dal\GameTopic;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class GameCate extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 游戏分类列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $is_recommend = trim($this->request->get('is_recommend'));
        $topic_id = trim($this->request->get('topic_id'));

        $where = ['status !=' => -1];
        if ($topic_id) {
            $where['topic_id'] = intval($topic_id);
        }
        if (strlen($is_recommend)) {
            $where['is_recommend'] = intval($is_recommend);
        }

        $total = GameCateDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $list = GameCateDal::fetchList($where, $offset, $pagesize, 'sort ASC, id DESC');

        $topics = GameTopic::fetchAll(['status' => 1]);
        $topics = Helper::arrayReindex($topics, 'id');

        $this->view->setVars([
            'topic_id' => $topic_id,
            'is_recommend' => $is_recommend,
            'topics' => $topics,
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
        ]);
    }

    /**
     * 添加 游戏分类
     * @throws \Exception
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:20 `分类名称`',
                'topic_id' => 'required|gt:0 `主题ID`',
                'status' => 'required|in:0,1 `状态`'
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                GameCateDal::insert($data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $topics = GameTopic::fetchAll(['status' => 1]);
        $topics = Helper::arrayReindex($topics, 'id');
        $this->view->setVar('topics', $topics);
    }

    /**
     * 编辑 游戏分类
     * @throws \Exception
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'name' => 'required|maxlen:20 `分类名称`',
                'topic_id' => 'required|gt:0 `主题ID`',
                'status' => 'required|in:0,1 `状态`'
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                GameCateDal::update($data['id'], $data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);

        $info = GameCateDal::fetchOne($id);
        if (empty($info)) {
            return $this->showError('您编辑的信息不存在');
        }
        $topics = GameTopic::fetchAll(['status' => 1]);
        $topics = Helper::arrayReindex($topics, 'id');
        $this->view->setVars([
            'p' => $p,
            'topics' => $topics,
            'info' => $info,
        ]);
    }

    /**
     * 删除 游戏分类
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                GameCateDal::update(['id' => $id], ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    //切换状态
    public function toggle()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'field' => 'required',
            'val' => 'required'
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            GameCateDal::update($data['id'], [$data['field'] => $data['val']]);
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }
}