<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/25
 * Time: 9:36
 */

namespace App\Controller\Admin;

use App\Dal\Channel;
use App\Dal\ChannelStat;
use App\Dal\Region;
use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\ChannelOrder as ChannelOrderDal;
use App\Dal\ChannelOrderLog;
use App\Dal\ChannelOrderFile;
use App\Dal\Invoice;


class ChannelOrder extends BaseController
{

    protected $whiteList = ['select_invoice', 'autocomplete'];

    /**
     * 对私结算
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $id = $this->request->get('id');
        $order_no = $this->request->get('order_no');
        $channel_id = $this->request->get('channel_id');
        $username = $this->request->get('username');
        $status = $this->request->get('status');
        $business_id = $this->request->get('business_id');

        $type = max(intval($this->request->get('type')), 1);
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');

        $where = ['finance_type' => 2];
        if (!empty($id)) {
            $where['id'] = $id;
        }
        if (!empty($channel_id)) {
            $where['channel_id'] = $channel_id;
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        if (!empty($business_id)) {
            $where['business_id'] = $business_id;
        }
        if (!empty($start_time)) {
            if ($type == 1) {
                $where['addtime >='] = strtotime($start_time);
            } else {
                $where['time >='] = strtotime($start_time);
            }
        }
        if (!empty($end_time)) {
            if ($type == 1) {
                $where['addtime <='] = strtotime($end_time);
            } else {
                $where['time  <='] = strtotime($end_time);
            }
        }

        $list = ChannelOrderDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = ChannelOrderDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        $channelIds = array_unique(array_column($list, 'channel_id'));
        $channelInfos = !empty($channelIds) ? Channel::fetchAll(['id in' => $channelIds], 'id asc', 'id,username') : [];
        $channelInfos = Helper::arrayReindex($channelInfos, 'id');

        $regionIds = array_unique(array_merge(array_column($list, 'bank_province'), array_column($list, 'bank_city')));
        $regionInfos = !empty($regionIds) ? Region::fetchAll(['id in' => $regionIds], 'id asc', 'id,name') : [];
        $regionInfos = Helper::arrayReindex($regionInfos, 'id');

        $adminIds = array_unique(array_column($list, 'admin_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $business = SystemAdmin::fetchAll(['is_business' => 1, 'status' => 1], 'id asc', 'id,nickname');

        $statusArr = isset($where['status']) ? [$where['status']] : [-1, 1, 2, 3, 4];
        $moneyArr = [];

        foreach ($statusArr as $item) {
            $where['status'] = $item;
            $moneyArr[$item] = ChannelOrderDal::sum('amount', $where);
        }

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'id' => $id,
            'order_no' => $order_no,
            'channel_id' => $channel_id,
            'username' => $username,
            'status' => $status,
            'business_id' => $business_id,
            'type' => $type,
            'start_time' => $start_time,
            'end_time' => $end_time,

            'adminInfos' => $adminInfos,
            'channelInfos' => $channelInfos,
            'regionInfos' => $regionInfos,
            'business' => $business,
            'moneyArr' => $moneyArr,
        ]);
    }

    /**
     * 对私-对公 通过订单操作
     */
    public function past()
    {
        if ($this->request->isPost()) {
            $ids = $this->request->getPost('ids');
            empty($ids) && Helper::json(false, '缺少状态为待审核的订单');

            foreach ($ids as $key => $item) {
                $orderInfo = ChannelOrderDal::fetchOne(['id' => $item], 'id,order_no,finance_type,status');
                if (isset($orderInfo['status']) && $orderInfo['status'] != 1) continue;
                ChannelOrderDal::update(['id' => $item], ['status' => 2]);

                ChannelOrderLog::insert([
                    'order_no' => $orderInfo['order_no'],
                    'finance_type' => $orderInfo['finance_type'],
                    'log_type' => 2,
                    'admin_id' => $this->auth['id'],
                    'addtime' => time(),
                ]);
            }

            Helper::json(true);
        }
    }

    /**
     * 对私-对公, 拒绝订单操作
     */
    public function denial()
    {
        if ($this->request->isPost()) {
            $id = $this->request->getPost('id');
            $remarks = $this->request->getPost('remarks');
            if (empty($id) || empty($remarks)) Helper::json(false, '缺少参数');

            try {
                Channel::begin();
                $channelOrderInfo = ChannelOrderDal::fetchOne(['id' => $id]);
//                对公操作需要清除
//                1.更改支付订单涉及的日收益表为“待支付”。
//                2.将支付订单的金额写入渠道的账户余额。 1
//                3.如果订单已经关联发票，清除发票关联。 1
                if ($channelOrderInfo['finance_type'] == 1) {
                    Invoice::inc(['number' => $channelOrderInfo['invoice_number']], ['order_amount' => -$channelOrderInfo['amount']]);
                }

                if (ChannelOrderDal::update(['id' => $id, 'status NOT IN' => [-1, 4]], ['status' => -1, 'remarks' => $remarks, 'invoice_number' => null])) {
                    $data = [
                        'order_no' => $channelOrderInfo['order_no'],
                        'finance_type' => $channelOrderInfo['finance_type'],
                        'log_type' => 5,
                        'admin_id' => $this->auth['id'],
                        'addtime' => time(),
                    ];
                    ChannelOrderLog::insert($data);
                    Channel::inc(['id' => $channelOrderInfo['channel_id']], ['money' => +$channelOrderInfo['amount']]);
                } else {
                    throw new \Exception('拒绝失败');
                }
                Channel::commit();
                Helper::json(true, '拒绝成功');
            } catch (\Exception $e) {
                Channel::rollback();
                trigger_error($e->getMessage());
                Helper::json(false, '拒绝失败');
            }
        }
    }

    /**
     * 对公-对私-导出待支付
     */
    public function export()
    {
        try {
            $type = max($this->request->get('type'), 1);
            $where = [
                'finance_type' => $type,
                'status' => 2,
            ];
            if ($type == 1) {
                $expTitle = '对公打款' . date('YmdHms');
                $where[] = 'invoice_number is not null';
                $orderList = ChannelOrderDal::fetchAll($where);
            } else if ($type == 2) {
                $expTitle = '对私打款' . date('YmdHms');
                $orderList = ChannelOrderDal::fetchAll($where);
            }
            if (empty($orderList)) {
                return "<script language=javascript>alert('暂无可导出数据!');history.back();</script>";
            }

            $expCellTitle = [
                'order_no' => '订单号',
                'username' => '渠道名称',
                'amount' => '提现金额',
                'payee' => '收款公司',
                'bank_account' => '银行账号',
                'bank' => '收款银行',
                'bank_branches' => '支行',
                'bank_province' => '省',
                'bank_city' => '市',
                'addtime' => '提现时间',
                'status' => '订单状态',
            ];

            $total_money = 0;
            $expTableData = [];
            $channel_num = count(array_unique(array_column($orderList, 'channel_id')));

            foreach ($orderList as $key => $item) {
                $total_money += $item['amount'];
                $expTableData[$key]['order_no'] = $item['order_no'];
                $channelInfo = Channel::fetchOne(['id' => $item['channel_id']], 'id,username');;
                $expTableData[$key]['username'] = $channelInfo['username'];

                $expTableData[$key]['amount'] = $item['amount'];
                $expTableData[$key]['payee'] = $item['payee'];
                $expTableData[$key]['bank_account'] = $item['bank_account'];
                $expTableData[$key]['bank'] = $item['bank'];
                $expTableData[$key]['bank_branches'] = $item['bank_branches'];

                $regionInfos = Region::fetchAll(['id in' => [$item['bank_province'], $item['bank_city']]], 'id asc', 'id,name');
                $expTableData[$key]['bank_province'] = $regionInfos[0]['name'];
                $expTableData[$key]['bank_city'] = $regionInfos[1]['name'];

                $expTableData[$key]['addtime'] = date('Y-m-d H:i:s', $item['addtime']);
                switch ($item['status']) {
                    case -1:
                        $expTableData[$key]['status'] = '支付失败';
                        break;
                    case 2:
                        $expTableData[$key]['status'] = '待支付';
                        break;
                    case 3:
                        $expTableData[$key]['status'] = '支付中';
                        break;
                    case 4:
                        $expTableData[$key]['status'] = '支付成功';
                        break;
                    default:
                        $expTableData[$key]['status'] = '待审核';
                        break;
                }
                ChannelOrderDal::update(['id' => $item['id']], [
                    'status' => 3,
                    'admin_id' => $this->auth['id'],
                    'time' => time()
                ]);
                ChannelOrderLog::insert([
                    'order_no' => $item['order_no'],
                    'finance_type' => $item['finance_type'],
                    'log_type' => 3,
                    'admin_id' => $this->auth['id'],
                    'addtime' => time(),
                ]);
            }
            $filePath = "./upload/export/" . date('Ymd') . '/';

            $data = [
                'name' => $expTitle,
                'url' => $filePath . $expTitle . '.xls',
                'type' => 2,
                'finance_type' => $type,
                'total_money' => $total_money,
                'order_num' => count($orderList),
                'channel_num' => $channel_num,
                'admin_id' => $this->auth['id'],
                'addtime' => time(),
            ];
            ChannelOrderFile::insert($data);

            Helper::exportExcel($expTitle, $expCellTitle, $expTableData, false, true, $filePath);

        } catch (\Exception $e) {
            trigger_error($e->getMessage());
        }
    }

    /**
     * 对公-对私-导入财务支付表
     */
    public function import()
    {
        if ($this->request->hasFiles()) {
            $type = max($this->request->get('type'), 1);
            $file = $this->request->getUploadedFiles();

            $name = $file[0]->getName();
            $ext = $file[0]->getExtension();
            $hash = sha1_file($file[0]->getTempName());
            $hashdir1 = './upload/import/' . date('Ymd') . '/';
            $hashdir = ROOT_PATH . '/upload/import/' . date('Ymd') . '/';
            if (!is_dir($hashdir)) mkdir($hashdir, 0777, true);
            $filePath1 = $hashdir1 . $hash . '.' . $ext;
            $filePath = $hashdir . $hash . '.' . $ext;
            $file[0]->moveTo($filePath);

            $data = Helper::importExcel($filePath, 0); // 订单号、渠道名称、提现金额、最后操作时间
            if (count($data) <= 1) {
                Helper::json(false, '文件不能为空');
            }

            $channelArr = [];
            $error_order_no = [];
            $total_money = 0;
            foreach ($data as $key => $item) {
                if ($key == 1) continue;
                $orderInfo = ChannelOrderDal::fetchOne(['order_no' => $item[0], 'amount' => $item[2], 'status' => 3]);
                if (empty($orderInfo)) {
                    $error_order_no[] = $item[0];
                    continue;
                }
                $channelArr[] = $item[1];
                $total_money += $item[2];
                $res = ChannelOrderDal::update(['order_no' => $item[0], 'amount' => $item[2], 'status' => 3], [
                    'status' => 4,
                    'admin_id' => $this->auth['id'],
                    'time' => time(),
                    'paytime' => strtotime($item[3]),
                ]);
                if ($res !== false){
                    ChannelOrderLog::insert([
                        'order_no' => $orderInfo['order_no'],
                        'finance_type' => $orderInfo['finance_type'],
                        'log_type' => 4,
                        'admin_id' => $this->auth['id'],
                        'addtime' => time(),
                    ]);
                }
            }
            $channel_num = count(array_unique($channelArr));
            $total_num = count($data) - 1;
            $error_num = count($error_order_no);
            $insert = [
                'name' => $name,
                'url' => $filePath1,
                'type' => 1,
                'finance_type' => $type,
                'total_money' => $total_money,
                'order_num' => $total_num - $error_num,
                'channel_num' => $channel_num,
                'admin_id' => $this->auth['id'],
                'addtime' => time(),
            ];
            ChannelOrderFile::insert($insert);

            if ($error_num == 0) {
                Helper::json(true, '全部导入成功，共计' . $total_num . '条');
            } else {
                Helper::json(false, '部分导入失败，共计' . $total_num . '条,失败' . $error_num . '条', $error_order_no);
            }
        }
    }

    /**
     * 对公结算
     */
    public function public_index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $order_no = $this->request->get('order_no');
        $channel_id = $this->request->get('channel_id');
        $username = $this->request->get('username');
        $invoice_num = $this->request->get('invoice_num');
        $status = $this->request->get('status');
        $business_id = $this->request->get('business_id');

        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');

        $where = ['finance_type' => 1];
        if (!empty($order_no)) {
            $where['order_no'] = $order_no;
        }
        if (!empty($channel_id)) {
            $where['channel_id'] = $channel_id;
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }
        if (!empty($business_id)) {
            $where['business_id'] = $business_id;
        }
        if (!empty($type)) {
            $where['type'] = $type;
        }
        if (!empty($start_time)) {
            $where['addtime >='] = strtotime($start_time);
        }
        if (!empty($end_time)) {
            $where['addtime <='] = strtotime($end_time);
        }

        $list = ChannelOrderDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = ChannelOrderDal::count($where);
        $page = new Pagination($total, $pagesize, $curpage);

        foreach ($list as $key => $item) {
            $invoiceInfo = Invoice::fetchOne(['number' => $item['invoice_number']]);
            $list[$key]['invoice'] = !empty($invoiceInfo) ? $invoiceInfo : [];
        }

        $channelIds = array_unique(array_column($list, 'channel_id'));
        $channelInfos = !empty($channelIds) ? Channel::fetchAll(['id in' => $channelIds], 'id asc', 'id,username,payee') : [];
        $channelInfos = Helper::arrayReindex($channelInfos, 'id');

        $adminIds = array_unique(array_column($list, 'admin_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $business = SystemAdmin::fetchAll(['is_business' => 1, 'status' => 1], 'id asc', 'id,nickname');

        $statusArr = isset($where['status']) ? [$where['status']] : [-1, 1, 2, 3, 4];
        $moneyArr = [];
        foreach ($statusArr as $item) {
            $where['status'] = $item;
            $moneyArr[$item] = ChannelOrderDal::sum('amount', $where);
        }

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'order_no' => $order_no,
            'channel_id' => $channel_id,
            'username' => $username,
            'status' => $status,
            'business_id' => $business_id,
            'invoice_num' => $invoice_num,
            'start_time' => $start_time,
            'end_time' => $end_time,

            'channelInfos' => $channelInfos,
            'adminInfos' => $adminInfos,
            'business' => $business,
            'moneyArr' => $moneyArr,
        ]);
    }

    /**
     * 对公-选择 编辑 发票前
     */
    public function select_invoice()
    {
        if ($this->request->isPost()) {
            $ids = $this->request->getPost('ids');
            $type = max($this->request->getPost('type'), 1);
            empty($ids) && Helper::json(false, '缺少参数');

            $orderInfos = ChannelOrderDal::fetchAll(['id IN' => $ids], '', 'id,payee,invoice_number');
            $payee_num = count(array_unique(array_column($orderInfos, 'payee')));
            ($payee_num > 1) && Helper::json(false, '所选订单不是同一“收款公司”');

            if ($type == 1) {
                foreach ($orderInfos as $item) {
                    if (empty($item['invoice_number'])) {
                        continue;
                    }
                    Helper::json(false, '存在已经选择发票的订单');
                }
            } else if ($type == 2) {
                $invoiceInfo = Invoice::fetchOne(['number' => $orderInfos[0]['invoice_number']], 'id');
                $data['invoice_id'] = $invoiceInfo['id'] ?? '';
            }

            $data['payee'] = $orderInfos[0]['payee'];
            $data['total_money'] = ChannelOrderDal::sum('amount', ['id IN' => $ids]);
            Helper::json(true, '', $data);
        }
    }

    /**
     * 对公-选择发票确认
     */
    public function confirm_invoice()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost('data');
            if (empty($data['id'])) Helper::json(false, '未选择发票');
            if (empty($data['order_ids'])) Helper::json(false, '订单为空');

            $order_ids = explode(',', $data['order_ids']);
            $invoiceInfo = Invoice::fetchOne(['id' => $data['id'], 'status' => 1]);
            empty($invoiceInfo) && Helper::json(false, '发票不存在');

            $orderInfos = ChannelOrderDal::fetchAll(['id IN' => $order_ids], '', 'id,amount');
            $amount = 0;
            foreach ($orderInfos as $key => $item) {
                $amount += $item['amount'];
            }
            Invoice::inc(['id' => $data['id']], ['order_amount' => +$amount]);
            ChannelOrderDal::update(['id IN' => $order_ids], ['invoice_number' => $invoiceInfo['number']]);

            Helper::json(true);
        }
    }

    /**
     * 编辑发票
     * Author: Admin
     * Date: 2019/5/22 10:03
     */
    public function edit_invoice()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost('data');
            if (empty($data['id'])) Helper::json(false, '未选择发票');
            if (empty($data['order_ids'])) Helper::json(false, '未选择订单');

            $invoiceInfo = Invoice::fetchOne(['id' => $data['id'], 'status' => 1]);
            empty($invoiceInfo) && Helper::json(false, '发票不存在');
            $orderInfos = ChannelOrderDal::fetchOne(['id' => $data['order_ids']]);

            // 1. 减去老发票订单金额 2. 增加新发票金额, 3. 更新发票号
            Invoice::inc(['number' => $orderInfos['invoice_number']], ['order_amount' => -$orderInfos['amount']]);
            Invoice::inc(['id' => $data['id']], ['order_amount' => $orderInfos['amount']]);
            ChannelOrderDal::update(['id' => $data['order_ids']], ['invoice_number' => $invoiceInfo['number']]);

            Helper::json(true);
        }
    }

    /**
     * 对公-一键生成订单
     */
    public function auto_order()
    {
        if ($this->request->isPost()) {
            $end_date = date('Y-m-t', strtotime('-1 month'));
            $monthFirstDay = strtotime(date("Y-m-1 00:00:00"));
            // 第三方, 对公, CPS 注册时间在本月以前
            $channelList = Channel::fetchAll(['type' => 2, 'finance_type' => 1, 'settlement_type' => 2, 'addtime <' => $monthFirstDay]);
            $num = 0;
            foreach ($channelList as $key => $item) {
                $lastWithdrawTime = ChannelOrderDal::fetchOne(['channel_id' => $item['id']], 'id, end_date', 'end_date DESC');
                if (isset($lastWithdrawTime['end_date'])) {
                    $start_date = date('Y-m-d', strtotime($lastWithdrawTime['end_date']) . "+ 1 day");
                } else {
                    $start_date = date('Y-m-d', $item['addtime']);
                }
                $where = [
                    'channel_id' => $item['id'],
                    'date >=' => $start_date,
                    'date <=' => $end_date
                ];
                $totalMoney = ChannelStat::sum('income_amount', $where);
                if ($totalMoney >= 500) {
                    try {
                        $num += 1;
                        Channel::begin();
                        Channel::inc($item['id'], ['money' => -$totalMoney]);
                        ChannelOrderDal::insert([
                            'finance_type' => $item['finance_type'],
                            'channel_id' => $item['id'],
                            'order_no' => Helper::generateOrderNo(),
                            'amount' => $totalMoney,
                            'payee' => $item['payee'],
                            'bank' => $item['bank'],
                            'bank_account' => $item['bank_account'],
                            'bank_province' => $item['bank_province'],
                            'bank_city' => $item['bank_city'],
                            'bank_branches' => $item['bank_branches'],
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'addtime' => time(),
                            'status' => 1,
                            'business_id' => $item['business_id'],
                            'admin_id' => $this->auth['id'],
                            'time' => time()
                        ]);
                        Channel::commit();
                    } catch (\Exception $e) {
                        Channel::rollback();
                        trigger_error($e->getMessage());
                    }
                }
            }
            if ($num == 0) {
                Helper::json(false, '暂无可生成订单');
            }
            Helper::json(true);
        }
    }

    /**
     * 对公-指定生成订单
     */
    public function auto_order_one()
    {
        if ($this->request->isPost()) {
            $channel_id = $this->request->getPost('channel_id');
            $end_date = $this->request->getPost('end_date');
            empty($end_date) && Helper::json(false, '结束时间不能为空');

            $channelInfo = Channel::fetchOne(['id' => $channel_id, 'finance_type' => 1, 'status' => 1]);
            empty($channelInfo) && Helper::json(false, '渠道不存在或财务类型为对私');
            if ($channelInfo['type'] == 1) {
                Helper::json(false, '官方渠道不允许生成订单');
            }
            if ($channelInfo['settlement_type'] != 2) {
                Helper::json(false, '结算方式为CPS的才可提现');
            }

            $lastWithdrawTime = ChannelOrderDal::fetchOne(['channel_id' => $channelInfo['id']], 'id, end_date', 'end_date DESC');
            if (isset($lastWithdrawTime['end_date'])) {
                $start_date = date('Y-m-d', strtotime($lastWithdrawTime['end_date'] . "+ 1 day"));
            } else {
                $start_date = date('Y-m-d', $channelInfo['addtime']);
            }
            $where = [
                'channel_id' => $channelInfo['id'],
                'date >=' => $start_date,
                'date <=' => $end_date
            ];
            $totalMoney = ChannelStat::sum('income_amount', $where);
            if ($totalMoney == 0) {
                Helper::json(false, '收益为0,不可生成订单');
            }
            try {
                Channel::begin();
                Channel::inc($channelInfo['id'], ['money' => -$totalMoney]);
                ChannelOrderDal::insert([
                    'finance_type' => $channelInfo['finance_type'],
                    'channel_id' => $channelInfo['id'],
                    'order_no' => Helper::generateOrderNo(),
                    'amount' => $totalMoney,
                    'payee' => $channelInfo['payee'],
                    'bank' => $channelInfo['bank'],
                    'bank_account' => $channelInfo['bank_account'],
                    'bank_province' => $channelInfo['bank_province'],
                    'bank_city' => $channelInfo['bank_city'],
                    'bank_branches' => $channelInfo['bank_branches'],
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'addtime' => time(),
                    'status' => 1,
                    'business_id' => $channelInfo['business_id'],
                    'admin_id' => $this->auth['id'],
                    'time' => time()
                ]);
                Channel::commit();
            } catch (\Exception $e) {
                trigger_error($e->getMessage());
                Channel::rollback();
            }
            Helper::json(true);
        }
    }

    /**
     * 模糊搜索 渠道订单
     */
    public function autocomplete()
    {
        $name = $this->request->getPost('name');
        $finance_type = intval($this->request->getPost('finance_type'));
        empty($name) && Helper::json(false, '缺少参数');

        $where = ['order_no LIKE' => "%{$name}%"];
        if (!empty($finance_type)) {
            $where['finance_type'] = $finance_type;
        }
        $list = ChannelOrderDal::fetchList($where, 0, 10, 'id DESC', 'id as value,order_no as title');
        Helper::json(true, '', $list);
    }

}