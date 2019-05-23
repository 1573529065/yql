<?php

namespace App\Controller\Admin;

use App\Dal\Developer as DeveloperDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class Developer extends BaseController
{
    protected $whiteList = ['autocomplete'];

    // 列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;


        $list = DeveloperDal::fetchList([], $offset, $pagesize, 'id DESC');
        $total = DeveloperDal::count([]);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage
        ]);
    }

    // 添加
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:30 `简介`',
                'company_name' => 'required|maxlen:30 `公司名称`',
                'contacts' => 'required|maxlen:30 `联系人`',
                'contacts_des' => 'required|maxlen:255 `联系方式`',
                'account_type' => 'required|in:1,2 `对公对私`',
                'payee' => 'required|maxlen:30 `收款人`',
                'bank' => 'required|maxlen:30 `开户行`',
                'card_no' => 'required|is_numeric|minlen:16|maxlen:30 `账号`',
                'des' => 'required|maxlen:255 `其他备注`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                DeveloperDal::insert($data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    // 编辑
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0',
                'name' => 'required|maxlen:30 `简介`',
                'company_name' => 'required|maxlen:30 `公司名称`',
                'contacts' => 'required|maxlen:30 `联系人`',
                'contacts_des' => 'required|maxlen:255 `联系方式`',
                'account_type' => 'required|in:1,2 `对公对私`',
                'payee' => 'required|maxlen:30 `收款人`',
                'bank' => 'required|maxlen:30 `开户行`',
                'card_no' => 'required|maxlen:30 `账号`',
                'des' => 'required|maxlen:255 `其他备注`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                DeveloperDal::update(['id' => $data['id']], $data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $id = intval($this->request->get('id'));
        $p = max(intval($this->request->get('p')), 1);
        $info = ($id > 0) ? DeveloperDal::fetchOne($id) : [];
        if (empty($info)) {
            return $this->showError('您编辑的信息不存在');
        }
        $this->view->setVars([
            'info' => $info,
            'p' => $p
        ]);
    }

    //删除
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                DeveloperDal::update(['id' => $id], ['status' => -1]);
            }
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    /**
     * 模糊搜索研发商
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = DeveloperDal::fetchList(['name LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,company_name as title');
        Helper::json(true, '', $list);
    }
}