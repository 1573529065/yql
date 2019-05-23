<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/3
 * Time: 16:05
 */

namespace App\Controller\Admin;

use App\Dal\UserAccount as UserAccountDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\User;
use App\Dal\Game;

class UserAccount extends BaseController
{
    protected $whiteList = ['toggle', 'autocomplete'];

    /**
     * 小号管理列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $id = intval($this->request->get('id'));
        $name = $this->request->get('name');
        $user_id = intval($this->request->get('user_id'));
        $user_name = $this->request->get('user_name');

        $where = [];
        $user_id > 0 && $where['user_id'] = $user_id;
        $id > 0 && $where['id'] = $id;

        $list = UserAccountDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = UserAccountDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        // 用户信息获取
        $user_ids = array_filter(array_unique(array_column($list, 'user_id')));
        $userInfos = empty($user_ids) ? [] : User::fetchAll(['id IN' => $user_ids], '', 'id,username');
        $userInfos = Helper::arrayReindex($userInfos, 'id');

        // 游戏信息获取
        $game_ids = array_filter(array_unique(array_column($list, 'game_id')));
        $gameInfos = empty($game_ids) ? [] : Game::fetchAll(['id IN' => $game_ids], '', 'id,name');
        $gameInfos = Helper::arrayReindex($gameInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'userInfos' => $userInfos,
            'gameInfos' => $gameInfos,
            'id' => $id,
            'name' => $name,
            'user_id' => $user_id,
            'user_name' => $user_name,
        ]);
    }

    /**
     * 锁定-解锁
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $field = $this->request->getPost('field');
        $val = $this->request->getPost('val');

        if ($id > 0) {
            UserAccountDal::update(['id' => $id], [$field => $val]);
            Helper::json(true);
        }
        Helper::json(false, '参数错误');
    }

    /**
     * 模糊搜索游戏名称
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $list = UserAccountDal::fetchList(['name LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,name as title');
        Helper::json(true, '', $list);
    }

}