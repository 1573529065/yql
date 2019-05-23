<?php

namespace App\Controller\Admin;

use App\Dal\SystemSetting;
use App\Service\Helper;
use App\Service\Validator;


class Setting extends BaseController
{
    /**
     * 全局设置
     * @throws \Exception
     */
    public function index()
    {
        if ($this->request->isPost()) {
            $id = $this->request->getPost('id');
            $v = new Validator();
            $rules = [
                'title' => 'required|maxlen:30 `标题`',
                'keywords' => 'required `关键字`',
                'des' => 'required `描述`',
                'icp' => 'required `备案号`',
                'android_ad_ex_version' => 'maxlen:10 `安卓广告`',
                'ios_ad_ex_version' => 'maxlen:10 `IOS广告`',
                'android_full_ex_version' => '',
                'ios_full_ex_version' => '',
                'service_qq' => 'maxlen:15 `客服QQ`',
                'player_qq' => 'maxlen:115 `QQ群`',
                'player_qq_key_ios' => 'maxlen:64 `IOS-QQ群KEY`',
                'player_qq_key_android' => 'maxlen:64 `安卓-QQ群KEY`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                if (false === SystemSetting::update($id, $data)) {
                    Helper::json(false, '更新失败');
                }
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        //编辑
        if (SystemSetting::count([]) == 0) {
            SystemSetting::insert([
                'title' => '',
                'des' => '',
                'keywords' => '',
                'icp' => '',
                'android_ad_ex_version' => '',
                'ios_ad_ex_version' => '',
                'android_full_ex_version' => '',
                'ios_full_ex_version' => ''
            ]);
        }
        $setting = SystemSetting::fetchAll([]);
        $this->view->setVar('info', array_pop($setting));
    }

}