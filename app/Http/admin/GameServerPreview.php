<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/3/28
 * Time: 9:24
 */

namespace App\Controller\Admin;

use App\Dal\GameServerPreview as GameServerPreviewDal;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\Game;
use App\Service\Validator;

class GameServerPreview extends BaseController
{
    /**
     * 游戏服管理
     */
    public function index()
    {
        $pagesize = 10;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * $pagesize;

        $where = [
            'status !=' => -1
        ];

        $list = GameServerPreviewDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = GameServerPreviewDal::count($where);

        //取出游戏名
        $game_ids = array_column($list, 'game_id');
        $games = $game_ids ? Game::fetchAll(['id IN' => $game_ids], '', 'id,name') : [];
        $games = Helper::arrayReindex($games, 'id');
        $page = new Pagination($total, $pagesize, $curpage);
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'game' => $games
        ]);
    }

    /**
     * 添加游戏服
     */
    public function add()
    {
        if (!$this->request->isPost()) return;
        $v = new Validator();
        $rules = [
            'game_id' => 'required|intval|gt:0 `游戏ID`',
            'name' => 'required `区服`',
            'start_time' => 'required `开服时间`'
        ];
        if (!$v->setRules($rules)->validate($this->request->getPost())) {
            Helper::json(false, $v->getErrorString());
        }
        $data = $v->getData();
        $data['start_time'] = strtotime($data['start_time']);

        $game = Game::fetchOne($data['game_id']);
        if(empty($game)) {
            Helper::json('游戏不存在');
        }
        $data['game_type'] = $game['game_type'];
        $data['platform'] = $game['platform'];
        $success = GameServerPreviewDal::insert($data);
        $success ? Helper::json(true) : Helper::json(false, '添加失败');
    }

    /**
     * excle 时间转换
     * Author: Admin
     * Date: 2019/5/23 18:04
     */
    public function change_time($time)
    {
//        时 = 小数 *24 取整（不要四舍五入）
//        分 = （小数 * 24 * 60 ）% 60
        $hour = intval($time * 24);
        $min = ($time * 24 * 60) % 60;

        return $hour . ':' . $min;
    }

    /**
     * 导入游戏服
     */
    public function import()
    {
        if ($this->request->hasFiles()) {
            $game_id = intval($this->request->getPost('game_id'));
            $file = $this->request->getUploadedFiles();
            if (!$game_id){
                Helper::json(false, '请先选择游戏');
            }

            $ext = $file[0]->getExtension();
            $hash = sha1_file($file[0]->getTempName());
            $hashdir = ROOT_PATH . '/upload/gameServer/' . date('Ymd') . '/';
            if (!is_dir($hashdir)) mkdir($hashdir, 0777, true);
            $filePath = $hashdir . $hash . '.' . $ext;
            $file[0]->moveTo($filePath);

            $data = Helper::importExcel($filePath, 0); // 游戏服名 年月日 时间
            if (count($data) <= 1) {
                Helper::json(false, '文件不能为空');
            }

            $gameInfo = Game::fetchOne(['id' => 1]);
            if (empty($gameInfo)) {
                Helper::json(false, '游戏不存在');
            }

            $error_order_no = [];
            foreach ($data as $key => $item) {
                $year = explode('.', $item[1]);
                $hour = $this->change_time($item[2]);
                $minute = explode(':', $hour);

                $res = GameServerPreviewDal::insert([
                    'game_id' => $gameInfo['id'],
                    'game_type' => $gameInfo['game_type'],
                    'platform' => $gameInfo['platform'],
                    'name' => $item[0] ?? '',
                    'start_time' => mktime($minute[0], $minute[1], '00', $year[1], $year[2], $year[0]),
                ]);
                if ($res === false){
                    $error_order_no[] = $key;
                }
            }
            $total_num = count($data);
            $error_num = count($error_order_no);
            unlink($filePath);
            if ($error_num == 0) {
                Helper::json(true, '全部导入成功，共计' . $total_num . '条');
            } else {
                Helper::json(false, '部分导入失败，共计' . $total_num . '条,失败' . $error_num . '条', $error_order_no);
            }
        }
    }

    /**
     * 修改游戏服
     */
    public function edit()
    {
        if (!$this->request->isPost()) return;
        $v = new Validator();
        $rules = [
            'id' => 'required|intval|gt:0',
            'name' => 'required `游戏服名称`',
        ];
        if (!$v->setRules($rules)->validate($this->request->getPost())) {
            Helper::json(false, $v->getErrorString());
        }
        $data = $v->getData();
        GameServerPreviewDal::update($data['id'], $data);
        Helper::json(true);
    }

    /**
     * 删除游戏服(单个)
     */
    public function delete()
    {
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            Helper::json(false, '参数错误');
        }
        GameServerPreviewDal::update(['id IN' => $ids], ['status' => -1]);
        Helper::json(true);
    }
}