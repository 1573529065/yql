<?php

namespace App\Controller\Admin;

use App\Dal\SystemError;
use App\Service\Pagination;
use App\Service\Helper;

/**
 * 错误日志
 * Class Logger
 * @package App\Controller\Admin
 */
class Logger extends BaseController
{
    public static $modules = ['h5' => 'H5', 'admin' => '后台', 'api' => 'API', 'cli' => 'CLI', 'sdk' => 'SDK', 'up' => '文件存储', 'tg' => '推广后台'];

    //错误日志列表
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $module = trim($this->request->get('module'));
        $startdate = trim($this->request->get('startdate'));//开始日期
        $enddate = trim($this->request->get('enddate'));//载止日期

        $where = [];
        if (array_key_exists($module, self::$modules)) {
            $where['module'] = $module;
        }
        //时间筛选
        if ($startdate && $enddate) {
            $where['addtime BETWEEN'] = [strtotime($startdate), strtotime($enddate) + 86399];
        }

        $list = SystemError::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = SystemError::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $this->view->setVars([
            'modules' => self::$modules,
            'module' => $module,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'list' => $list,//当前列表数据
            'page' => $page->generate(),
            'total' => $total
        ]);
    }

    //删除
    public function delete()
    {
        //清空
        $act = $this->request->getPost('act');
        if ($act == 'truncate') {
            try {
                SystemError::truncate();
                Helper::json(true);
            } catch (\Exception $e) {
                Helper::json(false, $e->getMessage());
            }
        }

        //批量删除
        $ids = $this->request->getPost('ids');
        if (!empty($ids)) {
            SystemError::delete(['id IN' => $ids]);
        }
        Helper::json(true);
    }
}