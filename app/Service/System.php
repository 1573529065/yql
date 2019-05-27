<?php

namespace App\Service;

use App\Http\Models\SystemAdmin;
use App\Http\Models\SystemGroup;

class System
{
    const LOGIN_SUCCESS = 1;
    const LOGIN_USER_DISABLED = -1;
    const LOGIN_GROUP_DISABLED = -2;
    const LOGIN_FAILED = -3;

    private static $_session;

    /**
     * 校验登录
     * @param $username
     * @param $password
     * @param $ip
     * @return int
     */
    public static function checkLogin($username, $password, $ip)
    {
        if ($username && $password) {
            $admin_info = SystemAdmin::fetchOne(['username' => $username]);
            if (is_array($admin_info) && !empty($admin_info)) {
                if ($admin_info['password'] == md5($password . $admin_info['salt'])) {
                    if($admin_info['status'] != 1) {
                        return self::LOGIN_USER_DISABLED;
                    }

                    $group = SystemGroup::fetchOne(['id' => $admin_info['group_id'], 'status' => 1]);
                    if (!$group) {
                        return self::LOGIN_GROUP_DISABLED;
                    }

                    SystemAdmin::update(['id' => $admin_info['id']], ['last_login_time' => time(), 'last_login_ip' => ip2long($ip)]);
                    $admin_info['group_info'] = $group;
//                    $session = Di::getDefault()->get('session');
                    session('auth', $admin_info);
//                    $session = Di::getDefault()->get('session');
//                    $session->set('auth', $admin_info);
                    return self::LOGIN_SUCCESS;
                }
            }
        }
        return self::LOGIN_FAILED;
    }

    /**
     * 检测权限成功返回相关菜单信息，失败返回false
     * @param $controllerName
     * @param $actionName
     * @return array|bool
     */
    public static function checkPermission($controllerName, $actionName)
    {
        $url = sprintf('%s/%s', $controllerName, $actionName);
        $permissions = json_decode(self::getLoginInfo()['group_info']['permissions'], true);
        $permissions = array_flip($permissions);
        $menus = self::getSortedMenuList();

        $ctl = $act = [];
        foreach ($menus as $menu) {
            if (isset($menu['child'])) {
                foreach ($menu['child'] as $child) {
                    if ($child['url'] == $url) {
                        $ctl = $menu;
                        $act = $child;
                    }
                    if (!isset($permissions[$child['id']])) {
                        unset($menus[$menu['id']]['child'][$child['id']]);
                    }
                }
            }
            if (!isset($permissions[$menu['id']])) {
                unset($menus[$menu['id']]);
            }
        }

        //ignore index
        if ($controllerName != 'index') {
            if (!isset($act['id']) || !isset($permissions[$act['id']])) {
                return false;
            }
        }
        return ['ctl' => $ctl, 'act' => $act, 'menus' => $menus];

    }

    /**
     * 更新用户的session信息
     * @param $id
     * @return bool
     */
    public static function updateSession($id)
    {
        $info = Admin::fetchOne($id);
        if ($info) {
            $info['group_info'] = SystemGroup::fetchOne(['id' => $info['group_id'], 'status' => 1]);
            $session = Di::getDefault()->get('session');
            $session->set('auth', $info);
            return true;
        }
        return false;
    }

    /**
     * 是否已登陆
     */
    public static function isLogin()
    {
        $auth = self::getLoginInfo();
        if (!$auth) {
            return false;
        }

        $info = Admin::fetchOne($auth['id']);
        if ($info['status'] != 1) {
            self::logout();
            return false;
        }
        return true;
    }

    /**
     * 退出登陆
     * @return bool
     */
    public static function logout()
    {
        $session = Di::getDefault()->get('session');
        $session->destroy();
        self::$_session = null;
        return true;
    }

    /**
     * 获取登陆信息
     * @return mixed
     */
    public static function getLoginInfo()
    {
        if (!isset(self::$_session)) {
            $session = Di::getDefault()->get('session');
            self::$_session = $session->get('auth');
        }
        return self::$_session;
    }

    /**
     * 检查指定用户的密码是否正确
     * @param $id
     * @param $password
     * @return bool
     */
    public static function checkPwd($id, $password)
    {
        if ($id && $password) {
            $info = Admin::fetchOne($id);
            if (is_array($info) && !empty($info)) {
                if ($info['password'] == md5($password . $info['salt'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 更新用户资料
     * @param $id
     * @param array $data
     * @return bool
     */
    public static function updateUser($id, array $data)
    {
        if ($id && !empty($data)) {
            if (isset($data['password'])) {
                if (!empty($data['password'])) {
                    $data['salt'] = Helper::getRandStr(6);
                    $data['password'] = md5($data['password'] . $data['salt']);
                } else {
                    unset($data['password']);
                }
            }
            return Admin::update(['id' => $id], $data);
        }
        return false;
    }

    /**
     * 获取所有菜单，按照上下级关系排好
     * @return array
     */
    public static function getSortedMenuList()
    {
        $sort_list = [];
        $list = SystemMenu::fetchAll(['status' => 1], 'sort ASC, id ASC');

        foreach ($list as $row) {
            if ($row['pid'] == 0) {
                $sort_list[$row['id']] = $row;
            }
        }

        foreach ($list as $row) {
            if ($row['pid'] > 0 && isset($sort_list[$row['pid']])) {
                $sort_list[$row['pid']]['child'][$row['id']] = $row;
            }
        }
        return $sort_list;
    }

    /**
     * 获取顶级菜单
     * @return mixed
     */
    public static function getTopMenuList()
    {
        return SystemMenu::fetchAll(['status' => 1, 'pid' => 0], 'sort ASC, id ASC');
    }
}