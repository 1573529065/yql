<?php

namespace App\Controller\Admin;

use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\RecommendType as RecommendTypeDal;
use App\Dal\RecommendGame;


class RecommendType extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 推荐位列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;
        $where['status'] = 1;

        $list = RecommendTypeDal::fetchList($where, $offset, $pagesize, 'sort ASC, id DESC');

        foreach ($list as $key => $v){
            $list[$key]['true_num'] = RecommendGame::count(['type_id' => $v['id'], 'status' => 1]);
            $list[$key]['false_num'] = RecommendGame::count(['type_id' => $v['id'], 'status !=' => 1]);
        }

        $total = RecommendTypeDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage
        ]);
    }

    /**
     * 添加推荐位
     */
    public function add()
    {
        if (!$this->request->isPost()) return;

        $v = new Validator();

        $rules = [
            'name' => 'required',
            'width' => 'required|intval',
            'height' => 'required|intval'
        ];

        if (!$v->setRules($rules)->validate($this->request->getPost())) {
            Helper::json(false, $v->getErrorString());
        }

        $success = RecommendTypeDal::insert($v->getData());

        $success ? Helper::json(true) : Helper::json(false, '添加失败');
    }

    /**
     * 修改推荐位
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();

            $rules = [
                'id' => 'required|intval',
                'name' => 'required',
                'width' => 'required|intval',
                'height' => 'required|intval'
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())){
                Helper::json(false, $v->getErrorString());
            }

            $data = $v->getData();
            RecommendTypeDal::update($data['id'], $data);
            Helper::json(true);
        }

        $id = $this->request->get('id');
        $p = max($this->request->get('p'), 1);
        $info = RecommendTypeDal::fetchOne($id);
        empty($info) && Helper::json(false, '数据不存在');

        $this->view->setVars([
            'p' => $p,
            'info' => $info
        ]);
    }

    /**
     * 删除推荐位(单个)
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            Helper::json(false, '参数错误');
        }

        RecommendTypeDal::update(['id IN' => $ids], ['status' => -1]);
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
        RecommendTypeDal::update($data['id'], [$data['field'] => $data['val']]);

        Helper::json(true);
    }


}