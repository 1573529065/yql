<?php

namespace App\Controller\Admin;

use App\Dal\GameTopic as GameTopicDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class GameTopic extends BaseController
{
    //列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['status' => 1];
        $list = GameTopicDal::fetchList($where, $offset, $pagesize, 'sort ASC, id DESC');
        $total = GameTopicDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage
        ]);
    }

    //添加/编辑
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:20',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                GameTopicDal::insert($data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    //编辑
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'name' => 'required|maxlen:20 `类型名称`',
                'sort' => 'required|intval|gte:0|lte:127 `排序`'
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                GameTopicDal::update($data['id'], $data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                GameTopicDal::update(['id' => $id], ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }
}