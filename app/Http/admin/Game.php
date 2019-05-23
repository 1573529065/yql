<?php

namespace App\Controller\Admin;

use App\Dal\Developer;
use App\Dal\Game as GameDal;
use App\Dal\GameCate;
use App\Dal\GameDetail;
use App\Dal\GameTopic;
use App\Dal\GameType;
use App\Dal\RecommendGame;
use App\Dal\RecommendType;
use App\Dal\Tags;
use App\Logic\Build;
use App\Logic\Waf;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use Phalcon\Di;

class Game extends BaseController
{
    protected $whiteList = ['toggle', 'autocomplete'];

    /**
     * 游戏列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max($this->request->get('p'), 1);
        $offset = ($curpage - 1) * $pagesize;

        $id = intval($this->request->get('id'));
        $name = trim($this->request->get('name'));
        $developer_id = intval($this->request->get('developer_id'));
        $developer = trim($this->request->get('developer'));
        $platform = trim($this->request->get('platform'));
        $status = trim($this->request->get('status'));

        $where = ['status !=' => -1];
        if ($id > 0) {
            $where['id'] = $id;
        }
        if ($developer_id > 0) {
            $where['developer_id'] = $developer_id;
        }
        if ($platform > 0) {
            $where['platform'] = $platform;
        }
        if (strlen($status) && is_numeric($status)) {
            $where['status'] = $status;
        }
        $total = GameDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);
        $list = GameDal::fetchList($where, $offset, $pagesize, 'id DESC');

        foreach ($list as $key => $item) {
            $gameDet = GameDetail::fetchOne(['id' => $item['id']]);
            $list[$key]['appkey'] = $gameDet['appkey'] ?? '';
            $list[$key]['appkey_server'] = $gameDet['appkey_server'] ?? '';
        }

        //研发商获取
        $developer_ids = array_filter(array_unique(array_column($list, 'developer_id')));
        $developers = empty($developer_ids) ? [] : Developer::fetchAll(['id IN' => $developer_ids]);
        $developers = Helper::arrayReindex($developers, 'id');
        $this->view->setVars([
            'id' => $id,
            'name' => $name,
            'curpage' => $curpage,
            'developer_id' => $developer_id,
            'developer' => $developer,
            'platform' => $platform,
            'status' => $status,
            'developers' => $developers,
            'list' => $list,
            'page' => $page->generate(),
        ]);
    }

    /**
     * 添加游戏-添加基本信息
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'name' => 'required|maxlen:20 `游戏名称`',
                'game_type' => 'required|intval|gt:0 `游戏主类`',
                'game_cates' => ' `游戏分类`',
                'platform' => 'intval|gte:0 `系统平台`',
                'package_type' => 'intval|gte:0 `包类型`',
                'screen_type' => 'intval|gte:0 `屏幕设置`',
                'icon' => 'is_url `游戏图标`',
                'video' => 'is_url `视频介绍`',
                'imgs' => ' `图片介绍`',
                'title' => ' `一句话介绍`',
                'tags' => ' `标签`',
                'des' => ' `游戏介绍`',
                'vip_des' => ' `vip介绍`',
                'rebate_des' => ' `返利介绍`',
                'welfare_des' => ' `福利介绍`',
                'tg_des' => 'maxlen:255 `推广介绍`',
                'support_all_coupon' => 'intval `代金券`',
                'is_promotion' => 'intval `是否推广`',
            ];

            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (empty($this->request->getPost('imgs'))) {
                    $data['imgs'] = json_encode([]);
                } else {
                    $data['imgs'] = json_encode($this->request->getPost('imgs'));
                }

                $data['addtime'] = time();
                // 32 位字符串
                $appkey = Helper::getRandStr(32, 'all');
                $appkey_server = Helper::getRandStr(32, 'all');

                $repeat = GameDal::fetchOne(['name' => $data['name']], 'id,name');
                if (!empty($repeat)) {
                    Helper::json(false, '游戏名不能重复');
                }

                $insertGameId = GameDal::insert($data);

                GameDetail::insert([
                    'id' => $insertGameId,
                    'appkey' => $appkey,
                    'appkey_server' => $appkey_server
                ]);

                $recommendType = $this->request->getPost('recommendType');
                if (!empty($recommendType)) {
                    foreach ($recommendType as $value) {
                        $recommendGameData = [
                            'type_id' => $value,
                            'game_id' => $insertGameId,
                            'platform' => $data['platform'],
                            'addtime' => time(),
                        ];
                        RecommendGame::insert($recommendGameData);
                    }
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
        $info = GameTopic::fetchAll(['status' => 1], '', 'id,name');
        foreach ($info as $k => $v) {
            $info[$k]['list'] = GameCate::fetchAll(['status =' => 1, 'topic_id' => $v['id']], '', 'id,name');
        }
        $gameType = GameType::fetchAll(['status' => 1], '', 'id,name');
        $recommendType = RecommendType::fetchAll(['status' => 1], '', 'id,name');
        $tags = Tags::fetchAll(['status' => 1], '', 'id,name');

        $this->view->setVars([
            'gameType' => $gameType,
            'recommendType' => $recommendType,
            'info' => $info,
            'tags' => $tags
        ]);
    }


    /**
     * 游戏列表-修改基本信息
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|gt:0|intval `游戏id`',
                'name' => 'required|maxlen:20 `游戏名称`',
                'game_type' => 'required|gt:0|intval `游戏主类`',
                'game_cates' => ' `游戏分类`',
                'platform' => 'intval|gte:0 `系统平台`',
                'package_type' => 'intval|gte:0 `包类型`',
                'screen_type' => 'intval|gte:0 `屏幕设置`',
                'icon' => 'is_url `游戏图标`',
                'video' => 'is_url `视频介绍`',
                'imgs' => '`图片介绍`',
                'title' => '`一句话介绍`',
                'tags' => '`标签`',
                'des' => '`游戏介绍`',
                'vip_des' => '`vip介绍`',
                'rebate_des' => '`返利介绍`',
                'welfare_des' => '`福利介绍`',
                'tg_des' => 'maxlen:255 `推广介绍`',
                'support_all_coupon' => 'intval `代金券`',
                'is_promotion' => 'intval `是否推广`',
            ];

            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (empty($this->request->getPost('imgs'))) {
                    $data['imgs'] = json_encode([]);
                } else {
                    $data['imgs'] = json_encode($this->request->getPost('imgs'));
                }
                GameDal::update(['id' => $data['id']], $data);

                $getrecommendType = $this->request->getPost('recommendType');
                if (!empty($getrecommendType)) {
                    $recommendGameTyepIds = RecommendGame::fetchAll(['game_id' => $data['id']], '', 'type_id');
                    $recommendGameTyepIds = !empty($recommendGameTyepIds) ? array_column($recommendGameTyepIds, 'type_id') : [];

                    // 删除 先删再更新,再新增
                    $diffDel = array_diff($recommendGameTyepIds, $getrecommendType);
                    if (!empty($diffDel)) {
                        RecommendGame::delete(['game_id' => $data['id'], 'type_id IN' => $diffDel]);
                    }
                    // 交集-更新
                    $intersect = array_intersect($getrecommendType, $recommendGameTyepIds);
                    if (!empty($intersect)) {
                        RecommendGame::update(['game_id' => $data['id'], 'type_id IN' => $intersect], ['platform' => $data['platform']]);
                    }
                    // 新增 新选的比数据库中多的
                    $diffAdd = array_diff($getrecommendType, $recommendGameTyepIds);
                    if (!empty($diffAdd)) {
                        foreach ($diffAdd as $value) {
                            $newInsert = [
                                'type_id' => $value,
                                'game_id' => $data['id'],
                                'platform' => $data['platform'],
                                'addtime' => time(),
                            ];
                            RecommendGame::insert($newInsert);
                        }
                    }
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $id = intval($this->request->get('id'));
        $p = max($this->request->get('p'), 1);
        $tab = $this->request->get('tab');
        $info = GameDal::fetchOne($id);
        if (empty($info)) {
            Helper::json(false, '游戏不存在');
        }
        // 基本信息
        $info['imgs'] = json_decode($info['imgs']);
        $game_cates = explode(',', $info['game_cates']);
        $info['game_cates_info'] = !empty($game_cates) ? GameCate::fetchAll(['id IN' => $game_cates], '', 'id,name') : [];

        $game_tags = explode(',', $info['tags']);
        $info['game_tags_info'] = !empty($game_tags) ? Tags::fetchAll(['id IN' => $game_tags], '', 'id,name') : [];

        $gameType = GameType::fetchAll(['status' => 1], '', 'id,name');
        $recommendType = RecommendType::fetchAll(['status' => 1], '', 'id,name');
        $recommendGameIds = RecommendGame::fetchAll(['game_id' => $id], '', 'type_id');
        $recommendGameIds = array_column($recommendGameIds, 'type_id');

        $gameTopic = GameTopic::fetchAll([], '', 'id,name');
        foreach ($gameTopic as $k => $v) {
            $gameTopic[$k]['list'] = GameCate::fetchAll(['status =' => 1, 'topic_id' => $v['id']], '', 'id,name');
        }

        $tags = Tags::fetchAll(['status' => 1], '', 'id,name'); // 标签
        $gameDetail = GameDetail::fetchOne(['id' => $id]); // 游戏包信息 appkey, appkey_server
        $developerInfo = Developer::fetchOne(['id' => $info['developer_id']], 'id,company_name'); // 研发商信息

        $this->view->setVars([
            'info' => $info,
            'tags' => $tags,
            'p' => $p,
            'gameType' => $gameType,
            'gameTopic' => $gameTopic,
            'recommendType' => $recommendType,
            'recommendGameIds' => $recommendGameIds,
            'gameDetail' => $gameDetail,
            'developerInfo' => $developerInfo,
            'id' => $id,
            'tab' => $tab
        ]);
    }

    /**
     * 游戏列表-修改游戏包信息
     */
    public function edit_package_info()
    {
        $v = new Validator();
        $rules = [
            'id' => 'required `required|gt:0|intval 游戏id`',
            'callback_url' => 'is_url `回调`',
            'package_name' => 'package_name|maxlen:30 `母包名`',
            'origin_package_name' => 'package_name|maxlen:30 `初始包名`',
            'version_name' => 'maxlen:30`版本名称`',
            'version' => '`版本号`',
            'package_size' => '`包大小`',
            'download_url' => 'is_url `下载`',
            'plist_url' => 'is_url `IOS描述文件`',
            'deploy_package' => 'intval `是否分包未选择`',
            'deploy_package_type' => 'intval `分包地址未选择`',
        ];
//        自定义验证规则
        $v->setTagMap('package_name', function ($var) {
            return preg_match('/^[\w\-\.]+$/', $var) ? true : false;
        });

        if ($v->setRules($rules)->validate($this->request->getPost())) {
            $data = $v->getData();
            $callback_url = $data['callback_url'];
            unset($data['callback_url']);
            GameDal::update(['id' => $data['id']], $data);

            if (!empty($callback_url)) {
                GameDetail::update(['id' => $data['id']], ['callback_url' => $callback_url]);
            }

            Helper::json(true);
        } else {
            Helper::json(false, $v->getErrorString());
        }
    }

    /**
     * 游戏列表-修改研发商与分成信息
     */
    public function edit_divided_info()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|gt:0|intval `游戏id`',
                'developer_id' => 'required|gt:0|intval `研发商`',
                'developer_fee_rate' => 'required|gte:0|lte:100 `研发商分成`',
                'channel_fee_rate' => 'required|gte:0|lte:100 `渠道费`',
                'cps_rate' => 'required|gte:0|lte:100 `CSP分成`',
                'discount' => 'required|gte:0|lte:100 `折扣返利`',
            ];

            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();

                GameDal::update(['id' => $data['id']], $data);

                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }
    }

    /**
     * 切换状态
     */
    public function toggle()
    {
        $id = intval($this->request->getPost('id'));
        $field = $this->request->getPost('field');
        $val = intval($this->request->getPost('val'));

        if ($id > 0) {
            GameDal::update(['id' => $id], [$field => $val]);
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
        $list = GameDal::fetchList(['name LIKE' => "%{$name}%"], 0, 10, 'id DESC', 'id as value,name as title');
        Helper::json(true, '', $list);
    }

    //一键分包
    public function build()
    {
        $id = $this->request->get('id');
        $game = GameDal::fetchOne($id, 'id,game_type,platform,package_name,origin_package_name');
        if (empty($game)) {
            Helper::json(false, '游戏不存在');
        }
        if (empty($game['package_name']) || empty($game['origin_package_name'])) {
            Helper::json(false, '请填写初始包名和母包名');
        }
        //暂时只对安卓分包
        if ($game['platform'] != 1) {
            Helper::json(true);
        }

        if (!Waf::checkDeployFrequency($id)) {
            Helper::json(false, '您的操作过于频繁，请稍后重试');
        }
        $config = Di::getDefault()->get('config')['upload'];
        $origin_package_path = $config['path'] . '/' . $game['package_name'];
        Build::run($origin_package_path, $game['origin_package_name']);
        Helper::json(true);
    }
}