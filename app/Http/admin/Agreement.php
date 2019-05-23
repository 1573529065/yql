<?php

namespace App\Controller\Admin;

use App\Dal\About;
use App\Service\Helper;
use App\Service\Validator;

class Agreement extends BaseController
{
    function index()
    {
        if ($this->request->isPost()) {
            $v = new Validator();
            $rules = [
                'const' => 'required `类型`',
                'des' => 'required `描述`',
            ];
            if ($v->setRules($rules)->validate($this->request->getPost())) {
                $data = $v->getData();
                About::update(['const' => $data['const']], $data);
                Helper::json(true);
            } else {
                Helper::json(false, $v->getErrorString());
            }
        }

        $agreement = About::fetchAll([]);
        $agreement = array_combine(array_column($agreement, 'const'), $agreement);
        $this->view->setVar('agreement', $agreement);
    }
}