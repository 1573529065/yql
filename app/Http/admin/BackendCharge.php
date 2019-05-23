<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/8
 * Time: 9:54
 */

namespace App\Controller\Admin;

use App\Dal\CoinIncomeType;
use App\Logic\Income;
use App\Service\Helper;
use App\Service\Validator;

class BackendCharge extends BaseController
{
    /**
     * 后台直冲平台币
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'user_id' => 'required|intval|gt:0 `用户ID`',
                'coin' => 'required|floatval `充值金额`',
                'income_type' => 'required|intval|gt:0 `平台币获取类型`',
                'remarks' => '',
            ];
            !$v->setRules($rules)->validate($this->request->getPost()) && Helper::json(false, $v->getErrorString());

            $data = $v->getData();
            $time = time();
            try {
                CoinIncomeType::begin();
                Income::increase($data['income_type'], $data['user_id'], $data['coin'], '', $time, $this->auth['id'], $data['remarks']);
                CoinIncomeType::commit();
            } catch (\Exception $e) {
                CoinIncomeType::rollback();
                trigger_error($e->getMessage());
                Helper::json(false, '充值失败');
            }
            Helper::json(true);
        }
        $incomeType = CoinIncomeType::fetchAll(['status' => 1], '', 'id,name');
        $this->view->setVars([
            'incomeType' => $incomeType,
        ]);
    }

}