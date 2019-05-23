<?php

namespace App\Controller\Admin;

use App\Service\Validator;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\Faq as FaqDal;
use App\Dal\FaqCate;

class Faq extends BaseController
{
    protected $whiteList = ['update_sort', 'update_sort_cate'];

    //常见问题
    public function index()
    {
        $pagesize = 10;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $cate_id = intval($this->request->get('cate_id'));
        $where = ['status !=' => -1];
        if ($cate_id > 0) {
            $where['cate_id'] = $cate_id;
        }

        $list = FaqDal::fetchList($where, $offset, $pagesize, 'sort ASC,id DESC');
        $total = FaqDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $cates = FaqCate::fetchAll([]);
        $cates = Helper::arrayReindex($cates, 'id');
        $this->view->setVars([
            'cates' => $cates,
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'cate_id' => $cate_id
        ]);
    }

    //添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'cate_id' => 'required|intval|gt:0 `问题分类`',
                'title' => 'required|maxlen:100 `标题`',
                'content' => 'required `内容`',
                'sort' => 'required|intval|gte:0|lte:127 `排序`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                $id = intval($this->request->getPost('id'));
                if ($id > 0) {
                    FaqDal::update($id, $data);
                } else {
                    FaqDal::insert($data);
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $cates = FaqCate::fetchAll([]);
        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);
        $info = $id ? FaqDal::fetchOne($id) : [];
        $this->view->setVars([
            'info' => $info,
            'cates' => $cates,
            'p' => $p
        ]);
    }

    //排序
    public function update_sort()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'sort' => 'required|intval',
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            if (FaqDal::update(['id' => $data['id']], ['sort' => $data['sort']]) === false) {
                Helper::json(false, '操作失败');
            }
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                FaqDal::update(['id' => $id], ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }


    //问题分类
    public function cate()
    {
        $pagesize = 5;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;
        $where = ['status' => 1];

        $list = FaqCate::fetchList($where, $offset, $pagesize,  'sort ASC,id DESC');
        $total = FaqCate::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage
        ]);
    }

    //问题分类添加
    public function add_cate()
    {
        if ($this->request->isPost()) {
            $id = intval($this->request->getPost('id'));
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:30 `分类名称`',
                'sort' => 'required|intval|gte:0|lte:127 `排序`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if ($id > 0) {
                    FaqCate::update($id, $data);
                } else {
                    FaqCate::insert($data);
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);
        $info = $id ? FaqCate::fetchOne($id) : [];
        $this->view->setVars([
            'info' => $info,
            'p' => $p
        ]);
    }

    //删除
    public function delete_cate()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                FaqCate::update(['id' => $id], ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    //更新分类排序
    public function update_sort_cate()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'sort' => 'required|intval',
        ];
        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            if (FaqCate::update(['id' => $data['id']], ['sort' => $data['sort']]) === false) {
                Helper::json(false, '操作失败');
            }
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }
}