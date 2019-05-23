<?php

namespace App\Controller\Admin;

use App\Logic\System;
use App\Service\Helper;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\View;

abstract class BaseController extends Controller
{
    protected $auth;
    protected $whiteList = [];

    function beforeExecuteRoute(Dispatcher $dispatcher)
    {
        if (!System::isLogin()) {
            $this->response->redirect('auth/login');
            return false;
        }
        $controllerName = strtolower($dispatcher->getControllerName());
        $actionName = strtolower($dispatcher->getActionName());
        $ret = System::checkPermission($controllerName, $actionName);

        //白名单可直接访问
        if (false === $ret) {
            if (!in_array($actionName, $this->whiteList)) {
                if ($this->request->isAjax()) {
                    Helper::json(false, "您没有访问权限");
                }
                return $this->showError('您没有访问权限');
            }
        } else {
            $this->view->setVar('ctl', $ret['ctl']);
            $this->view->setVar('act', $ret['act']);
            $this->view->setVar('menus', $ret['menus']);
        }

        $this->auth = System::getLoginInfo();
        $this->view->setVar('auth', $this->auth);
    }

    public function initialize()
    {
        $this->view->disableLevel(View::LEVEL_MAIN_LAYOUT);
    }

    //显示错误页
    public function showError($msg)
    {
        $this->flash->error($msg);
        return $this->dispatcher->forward(['controller' => 'index', 'action' => 'error']);
    }
}