<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/8
 * Time: 9:54
 */

namespace App\Controller\Admin;

use App\Dal\CoinExpenseType as CoinExpenseTypeDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;

class CoinExpenseType extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 平台币消耗类型列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['status !=' => -1];
        $list = CoinExpenseTypeDal::fetchList($where, $offset, $pagesize, 'sort ASC, id DESC');

        $total = CoinExpenseTypeDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate()
        ]);
    }

    /**
     * 添加平台币消耗类型
     */
    public function add()
    {
        if ($this->request->isPost()){
            $v = new Validator();

            $rules = [
                'name' => 'required `平台币类型名称`'
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false);
            $data = $v->getData();

            CoinExpenseTypeDal::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 编辑平台币消耗类型
     */
    public function edit()
    {
        if ($this->request->isPost()){
            $v = new Validator();

            $rules = [
                'id' => 'required `类型ID`',
                'name' => 'required `平台币消耗类型名称`',
                'sort' => 'required `排序`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false);
            $data = $v->getData();

            CoinExpenseTypeDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }
    }

    /**
     * 删除平台币消耗类型
     */
    public function delete()
    {
        if ($this->request->isPost()){
            $id = intval($this->request->getPost('id'));
            CoinExpenseTypeDal::update(['id' => $id], ['status' => -1]);
            Helper::json(true);
        }
    }

    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $status = intval($this->request->getPost('status'));
        $status = $status ? 1 : 0;
        if ($id > 0) {
            CoinExpenseTypeDal::update(['id' => $id], ['status' => $status]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

}