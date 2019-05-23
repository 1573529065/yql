<?php

namespace App\Controller\Admin;

use App\Dal\HotSearch as HotSearchDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class HotSearch extends BaseController
{
    protected $whiteList = ['update_attr'];

    //列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['status !=' => -1];
        $list = HotSearchDal::fetchList($where, $offset, $pagesize,  'sort ASC, id DESC');
        $total = HotSearchDal::count($where);
        $page = new Pagination($total, $pagesize ,$curpage);

        $this->view->setVars([
            'page' => $page->generate(),
            'list' => $list
        ]);
    }

    //添加/编辑
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'keywords' => 'required|maxlen:30',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                HotSearchDal::insert($data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    /**
     * 硬删
     */
    public function delete()
    {
        $id = $this->request->get('ids');
        if (empty($id)) {
            Helper::json(false, 'id不能为空');
        }

        HotSearchDal::delete($id[0]);
        Helper::json(true);
    }

    /**
     * 修改状态
     * @throws \Exception
     */
    public function update_attr()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'field' => 'required',
            'val' => 'required'
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            HotSearchDal::update($data['id'], [$data['field'] => $data['val']]);
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }
}