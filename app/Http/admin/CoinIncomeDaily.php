<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/11
 * Time: 15:50
 */

namespace App\Controller\Admin;

use App\Dal\CoinIncomeDaily as CoinIncomeDailyDal;
use App\Dal\User;
use App\Service\Helper;
use App\Service\Pagination;

class CoinIncomeDaily extends BaseController
{
    protected $whiteList = ['index'];

    /**
     * 平台币获取排行榜
     */
    public function index()
    {
        $pagasize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage -1) * $pagasize;

        $where = [];
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');
        if (!empty($start_time)){
            $where['date >='] = $start_time;
        }
        if (!empty($end_time)){
            $where['date <='] = $end_time;
        }

        $list = CoinIncomeDailyDal::fetchList($where, $offset, $pagasize, 'id DESC');
        $total = CoinIncomeDailyDal::count($where);
        $page = new Pagination($total, $pagasize, $curpage);

        $userIds = array_unique(array_column($list, 'user_id'));
        $userInfos = !empty($userIds) ? User::fetchAll(['id IN' => $userIds], 'id', 'id,username') : [];
        $userInfos = Helper::arrayReindex($userInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'userInfos' => $userInfos,
            'start_time' => $start_time,
            'end_time' => $end_time,
        ]);
    }
}