<?php

namespace App\Controller\Admin;

use App\Dal\Adzone as AdzoneDal;
use App\Dal\Ad;
use App\Service\Validator;
use App\Service\Helper;
use App\Service\Pagination;

class Adzone extends BaseController
{
    protected $whiteList = ['update_sort'];

    //广告位列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;
        $where = [];

        $list = AdzoneDal::fetchList($where, $offset, $pagesize, 'sort ASC,id DESC');
        $total = AdzoneDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        foreach ($list as $k => $v) {
            $list[$k]['show_ad'] = Ad::count(['zoneid' => $v['id'], 'status' => 1]);
        }
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage
        ]);
    }

    //添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'title' => 'required|minlen:2|maxlen:50 `标题`',
                'icon_width' => 'required|is_numeric|lt:1920 `图标宽度`',
                'icon_height' => 'required|is_numeric|lt:1920 `图标高度`',
                'width' => 'required|is_numeric|lt:1920 `素材宽度`',
                'height' => 'required|is_numeric|lt:1920 `素材高度`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (AdzoneDal::insert($data)) {
                    Helper::json(true);
                } else {
                    Helper::json(false, '创建失败');
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    //修改
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'title' => 'required|minlen:2|maxlen:50 `标题`',
                'icon_width' => 'required|is_numeric|lt:1920 `图标宽度`',
                'icon_height' => 'required|is_numeric|lt:1920 `图标高度`',
                'width' => 'required|is_numeric|lt:1920 `素材宽度`',
                'height' => 'required|is_numeric|lt:1920 `素材高度`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (AdzoneDal::update($data['id'], $data) !== false) {
                    Helper::json(true);
                } else {
                    Helper::json(false, '编辑失败');
                }
            } else {
                Helper::json(false, $v->getErrorString());
            }
        } else {
            $id = intval($this->request->get('id'));
            $p = intval($this->request->get('p'));
            $info = AdzoneDal::fetchOne($id);
            if (empty($info)) {
                return $this->showError('您编辑的信息不存在');
            }
            $this->view->setVars([
                'info' => $info,
                'p' => $p,
            ]);
        }
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
            if (AdzoneDal::update(['id' => $data['id']], ['sort' => $data['sort']]) === false) {
                Helper::json(false, '操作失败');
            }
            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

}