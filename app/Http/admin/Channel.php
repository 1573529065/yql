<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/25
 * Time: 9:36
 */

namespace App\Controller\Admin;

use App\Dal\Channel as ChannelDal;
use App\Dal\Region;
use App\Dal\SystemAdmin;
use App\Dal\ChannelOrder;
use App\Service\Bank;
use App\Service\Helper;
use App\Service\Pagination;
use App\Service\Validator;
use App\Logic\Build;
use App\Dal\ChannelTg;
use App\Dal\Game;
use Phalcon\Di;

class Channel extends BaseController
{

    protected $whiteList = ['auto_bank_info', 'toggle', 'autocomplete'];

    /**
     * 渠道列表
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $channel_id = $this->request->get('channel_id');
        $username = $this->request->get('username');
        $type = $this->request->get('type');
        $settlement_type = $this->request->get('settlement_type');
        $finance_type = $this->request->get('finance_type');
        $status = $this->request->get('status');
        $business_id = $this->request->get('business_id');

        $where['pid'] = 0;
        if (!empty($channel_id)) {
            $where['id'] = $channel_id;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        if (!empty($settlement_type)) {
            $where['settlement_type'] = $settlement_type;
        }
        if (!empty($finance_type)) {
            $where['finance_type'] = $finance_type;
        }

        if (strlen($status) == 0) {
            $where['status !='] = -1;
        } else {
            $where['status'] = $status;
        }
        if (!empty($business_id)) {
            $where['business_id'] = $business_id;
        }

        $list = ChannelDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = ChannelDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        foreach ($list as $key => $item) {
            $list[$key]['sub_channel_num'] = ChannelDal::count(['pid' => $item['id'], 'status !=' => -1]);
            $list[$key]['withdraw_money'] = ChannelOrder::sum('amount', ['channel_id' => $item['id'], 'status' => 4]); // 提现金额 : 渠道成功提现金额总和
        }

        $adminIds = array_unique(array_column($list, 'business_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $business = SystemAdmin::fetchAll(['is_business' => 1, 'status' => 1], 'id asc', 'id,nickname');
        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'adminInfos' => $adminInfos,
            'business' => $business,
            'channel_id' => $channel_id,
            'username' => $username,
            'type' => $type,
            'settlement_type' => $settlement_type,
            'finance_type' => $finance_type,
            'status' => $status,
            'business_id' => $business_id,
        ]);
    }

    /**
     * 添加渠道
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'username' => 'required|minlen:4|maxlen:30 `渠道账号`',
                'password' => 'required|minlen:6|maxlen:16 `渠道密码`',
                'password1' => 'required|same:password `两次输入的密码不一致`',
                'type' => 'required|intval|gt:0 `渠道类型`',
                'settlement_type' => 'required|intval|gt:0 `结算方式`',
                'contact' => 'maxlen:15 `联系人`',
                'qq' => 'is_numeric|maxlen:15 `QQ号`',
                'wechat' => 'maxlen:32 `微信号`',
                'mobile' => 'is_mobile `手机号`',
                'remarks' => '',
                'status' => 'required|intval `状态`',
                'business_id' => 'required|intval|gt:0 `商务`',
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }
            $data = $v->getData();
            unset($data['password1']);

            $info = ChannelDal::fetchOne(['username' => $data['username']]);
            if (!empty($info)) {
                Helper::json(false, '用户名已存在,请修改');
            }

            $data['addtime'] = time();
            $data['salt'] = Helper::getRandStr(8, 'all'); // 8 位字符串
            $data['password'] = md5($data['password'] . $data['salt']);

            ChannelDal::insert($data);
            Helper::json(true);
        }

        $business = SystemAdmin::fetchAll(['is_business' => 1, 'status' => 1], 'id ASC', 'id,nickname');
        $this->view->setVars([
            'business' => $business
        ]);
    }

    /**
     * 渠道列表-导出渠道
     */
    public function export_channel()
    {
        $channel_id = $this->request->get('channel_id');
        $type = $this->request->get('type');
        $settlement_type = $this->request->get('settlement_type');
        $finance_type = $this->request->get('finance_type');
        $status = $this->request->get('status');
        $business_id = $this->request->get('business_id');

        $where['pid'] = 0;
        if (!empty($channel_id)) {
            $where['id'] = $channel_id;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        if (!empty($settlement_type)) {
            $where['settlement_type'] = $settlement_type;
        }
        if (!empty($finance_type)) {
            $where['finance_type'] = $finance_type;
        }
        if (strlen($status) == 0) {
            $where['status !='] = -1;
        } else {
            $where['status'] = $status;
        }
        if (!empty($business_id)) {
            $where['business_id'] = $business_id;
        }

//        渠道ID、渠道账号、渠道类型、结算方式、财务类型、创建时间、QQ、微信、手机号、备注、账户余额、提现金额、状态、所属商务
        $expTitle = '渠道表' . date('YmdHms');
        $expCellTitle = [
            'id' => '渠道ID',
            'username' => '渠道账号',
            'type' => '渠道类型',
            'settlement_type' => '结算方式',
            'finance_type' => '财务类型',
            'addtime' => '创建时间',
            'qq' => 'QQ',
            'wechat' => '微信',
            'mobile' => '手机号',
            'remarks' => '备注',
            'money' => '账户余额',
            'amount' => '提现金额',
            'status' => '状态',
            'business_id' => '所属商务',
        ];
        $expTableData = [];
        $orderList = ChannelDal::fetchAll($where);

        foreach ($orderList as $key => $item) {
            $expTableData[$key]['id'] = $item['id'];
            $expTableData[$key]['username'] = $item['username'];
            $expTableData[$key]['type'] = $item['type'] == 1 ? '官方' : ($item['type'] == 2 ? '第三方' : '');
            $expTableData[$key]['settlement_type'] = $item['settlement_type'] == 1 ? 'CPC' : ($item['settlement_type'] == 2 ? 'CPS' : ($item['settlement_type'] == 3 ? '工会' : ''));

            $expTableData[$key]['finance_type'] = $item['finance_type'] == 1 ? '对公' : ($item['finance_type'] == 2 ? '对私' : '');
            $expTableData[$key]['addtime'] = date('Y-m-d H:i:s', $item['addtime']);
            $expTableData[$key]['qq'] = $item['qq'];
            $expTableData[$key]['wechat'] = $item['wechat'];
            $expTableData[$key]['mobile'] = $item['mobile'];
            $expTableData[$key]['remarks'] = $item['remarks'];
            $expTableData[$key]['money'] = $item['money'];

            $sum = ChannelOrder::sum('amount', ['status' => 4, 'channel_id' => $item['id']]);
            $expTableData[$key]['amount'] = $sum;
            $adminInfo = SystemAdmin::fetchOne(['id' => $item['business_id']]);
            $expTableData[$key]['business_id'] = $adminInfo['nickname'] ?? '';

            switch ($item['status']) {
                case 0:
                    $expTableData[$key]['status'] = '关闭';
                    break;
                case -1:
                    $expTableData[$key]['status'] = '删除';
                    break;
                default:
                    $expTableData[$key]['status'] = '正常';
                    break;
            }
        }

        Helper::exportExcel($expTitle, $expCellTitle, $expTableData, false);
    }

    /**
     * 编辑渠道(QQ,微信,备注)
     */
    public function edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'contact' => 'maxlen:15 `联系人`',
                'qq' => 'is_numeric|maxlen:15 `QQ号`',
                'wechat' => 'maxlen:32 `微信号`',
                'remarks' => '',
            ];
            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }
            $data = $v->getData();

            ChannelDal::update($data['id'], $data);
            Helper::json(true);
        }
    }

    /**
     * 高级编辑-基本信息修改
     */
    public function info_edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();

            //  手机号必填: 手机号不必填的话,推广后台填写财务信息发送验证码没发进行,要增加很多操作判断
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'password' => 'minlen:6|maxlen:16 `渠道密码`',
                'password1' => '',
                'type' => 'required|intval|gt:0 `渠道类型`',
                'settlement_type' => 'required|intval|gt:0 `结算方式`',
                'contact' => 'maxlen:15 `联系人`',
                'qq' => 'is_numeric|maxlen:15 `qq`',
                'wechat' => 'maxlen:32 `微信号`',
                'mobile' => 'required|is_mobile `手机号`',
                'remarks' => '',
                'status' => 'required|intval `状态`',
                'business_id' => 'required|intval|gt:0 `商务`',
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }
            $data = $v->getData();

            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                if ($data['password'] !== $data['password1']) {
                    Helper::json(false, '两次输入的密码不一致');
                }
                $data['salt'] = Helper::getRandStr(8, 'all'); // 8 位字符串
                $data['password'] = md5($data['password'] . $data['salt']);
            }
            unset($data['password1']);

            ChannelDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }

        $id = intval($this->request->get('id'));
        $p = max($this->request->get('p'), 1);
        $tab = max($this->request->get('tab'), 1);

        $info = ChannelDal::fetchOne(['id' => $id]);
        $business = SystemAdmin::fetchAll(['is_business' => 1, 'status' => 1], 'id asc', 'id,nickname');

        $pro = Region::fetchAll(['pid' => 0], 'id asc', 'id,name');
        $pro_ids = array_unique(array_column($pro, 'id'));
        $city = !empty($pro_ids) ? Region::fetchAll(['pid in' => $pro_ids]) : [];

        $this->view->setVars([
            'business' => $business,
            'id' => $id,
            'p' => $p,
            'tab' => $tab,
            'info' => $info,
            'pro' => $pro,
            'city' => $city,
        ]);
    }

    /**
     * 高级编辑-财务信息修改
     */
    public function finance_edit()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'id' => 'required|intval|gt:0 `ID`',
                'finance_type' => 'required|intval|gt:0`财务类型`',
                'payee' => 'required|maxlen:30 `收款人`',
                'bank_account' => 'required|minlen:16|maxlen:19|is_numeric `银行卡号`',
                'bank' => 'required|maxlen:30 `银行`',
                'bank_province' => 'required|intval|gt:0`省份`',
                'bank_city' => 'required|intval|gt:0`城市`',
                'bank_branches' => 'required|maxlen:30`分行`',
            ];

            if (!$v->setRules($rules)->validate($this->request->getPost())) {
                Helper::json(false, $v->getErrorString());
            }
            $data = $v->getData();

            ChannelDal::update(['id' => $data['id']], $data);
            Helper::json(true);
        }
    }

    /**
     * 自动获取银行卡信息
     */
    public function auto_bank_info()
    {
        $bank_account = $this->request->get('bank_account');
        if (empty($bank_account)) {
            Helper::json(false, '银行卡号不能为空');
        }
        $info = Bank::get_bank_info($bank_account);
//        $info = '{
//            "showapi_res_error": "",
//            "showapi_res_id": "86597ff9c83d4541a676047c74c6e073",
//            "showapi_res_code": 0,
//            "showapi_res_body": {
//                "tel":"95559",
//                "bankName":"交通银行",
//                "cardType":"借记卡",
//                "url":"www.bankcomm.com",
//                "ret_code":0,
//                "area":"内蒙古自治区 - 芜湖市",
//                "brand":"太平洋借记卡",
//                "cardNum":"6222600260001072444",
//                "simpleCode":"BOCOM"
//            }
//        }';
//        $info = json_decode($info, true);

        if (isset($info['showapi_res_code']) && $info['showapi_res_code'] == 0 && !empty($info['showapi_res_body'])) {
            $data['bankName'] = $info['showapi_res_body']['bankName'] ?? '';
            $data['bank_province'] = 0;
            $data['bank_province_name'] = '';
            $data['bank_city'] = 0;
            $data['bank_city_name'] = '';

            $area = explode('-', preg_replace('# #', '', $info['showapi_res_body']['area']));
            if (isset($area[0])) {
                if (mb_substr($area[0], -1, 1) == '省') {
                    $province = substr($area[0], 0, strlen($area[0]) - 3);
                } else {
                    $province = $area[0];
                }
                $bank_province_info = Region::fetchOne(['name' => $province], 'id,name');
                $data['bank_province'] = $bank_province_info['id'] ?? 0;
                $data['bank_province_name'] = $bank_province_info['name'] ?? 0;
            }
            if (isset($area[1])) {
                if (mb_substr($area[1], -1, 1) == '市') {
                    $bank_city = substr($area[1], 0, strlen($area[1]) - 3);
                } else {
                    $bank_city = $area[1];
                }
                $bank_city_info = Region::fetchOne(['name' => $bank_city], 'id,name');
                $data['bank_city'] = $bank_city_info['id'] ?? 0;
                $data['bank_city_name'] = $bank_city_info['name'] ?? 0;
            }
            Helper::json(true, '', $data);
        } else {
            Helper::json(false, '银行卡识别错误');
        }
    }

    /**
     * 子渠道列表
     */
    public function sub_channel()
    {
        $pageszie = 15;
        $current = max(intval($this->request->get('p')), 1);
        $offset = ($current - 1) * ($pageszie);

        $id = intval($this->request->get('id'));
        if (empty($id)) {
            Helper::json(false, '缺少渠道ID');
        }
        $where = [
            'status !=' => -1,
            'pid' => $id
        ];

        $list = ChannelDal::fetchList($where, $offset, $pageszie, 'id DESC');
        $total = ChannelDal::count($where);
        $page = new Pagination($total, $pageszie, $current);

        $this->view->setVars([
            'list' => $list,
            'p' => $current,
            'page' => $page->generate()
        ]);
    }


    /**
     * 修改渠道状态
     */
    public function toggle()
    {
        $id = $this->request->get('id');
        $field = $this->request->get('field');
        $val = $this->request->get('val');

        if (empty($id)) {
            Helper::json(false, 'ID不能为空');
        }

        ChannelDal::update(['id' => $id], [$field => $val]);
        Helper::json(true);
    }

    /**
     * 模糊搜索 会员名
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $type = $this->request->getPost('type'); // type 1: 管理后台渠道列表

        $where = ['username LIKE' => "%{$name}%"];
        if ($type == 1) {
            $where['pid'] = 0;
        }

        $list = ChannelDal::fetchList($where, 0, 10, 'id DESC', 'id as value,username as title');
        Helper::json(true, '', $list);
    }

    //针对单个渠道一键分包(只分包已经推广过的包)
    public function build()
    {
        $channel_id = intval($this->request->get('id'));
        $tg_games = ChannelTg::fetchAll(['channel_id' => $channel_id]);
        if (empty($tg_games)) {
            Helper::json(true);
        }

        $config = Di::getDefault()->get('config')['upload'];
        $game_ids = array_column($tg_games, 'game_id');
        $games = Game::fetchAll(['id IN' => $game_ids], '', 'id,game_type,platform,package_name,origin_package_name');
        foreach ($games as $game) {
            if ($game['platform'] != 1 || empty($game['package_name']) || empty($game['origin_package_name'])) {
                continue;
            }
            $origin_package_path = $config['path'] . '/' . $game['package_name'];
            Build::run($origin_package_path, $game['origin_package_name'], $channel_id);
        }
        Helper::json(true);
    }
}