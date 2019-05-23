<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/8
 * Time: 9:54
 */

namespace App\Controller\Admin;


use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Dal\CoinIncomeType as CoinIncomeTypeDal;

class CoinIncomeType extends BaseController
{
    protected $whiteList = ['toggle'];

    /**
     * 平台币获取类型列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = ['status !=' => -1];
        $list = CoinIncomeTypeDal::fetchList($where, $offset, $pagesize, 'sort ASC, id DESC');
        $total = CoinIncomeTypeDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate()
        ]);
    }

    /**
     * 添加平台币获取类型
     */
    public function add()
    {
        if ($this->request->isPost()){
            $v = new Validator();

            $rules = [
                'name' => 'required `平台币类型名称`',
                'currency' => 'required `平台币性质`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false);
            $data = $v->getData();

            CoinIncomeTypeDal::insert($data);
            Helper::json(true);
        }
    }

    /**
     * 编辑平台币获取类型
     */
    public function edit()
    {
        if ($this->request->isPost()){
            $v = new Validator();

            $rules = [
                'id' => 'required `类型ID`',
                'name' => 'required `平台币类型名称`',
                'currency' => 'required `平台币性质`',
                'sort' => 'required `排序`',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false);
            $data = $v->getData();

            CoinIncomeTypeDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }
    }

    /**
     * 删除平台币获取类型
     */
    public function delete()
    {
        if ($this->request->isPost()){
            $id = intval($this->request->get('id'));
            CoinIncomeTypeDal::update(['id' => $id], ['status' => -1]);
            Helper::json(true);
        }
    }

    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $status = intval($this->request->getPost('status'));
        $status = $status ? 1 : 0;
        if ($id > 0) {
            CoinIncomeTypeDal::update(['id' => $id], ['status' => $status]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

}