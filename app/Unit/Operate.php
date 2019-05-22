<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 11/21/2017
 * Time: 6:30 PM
 */

namespace App\Unit;

use App\Models\Permission;
use DB;
use Log as BaseLog;

class Operate
{

    public static $modules = [];

    /**
     * 1 查看 2 增加 3 修改 4 删除
     */
    CONST TYPE_VIEW = 1;
    CONST TYPE_ADD = 2;
    CONST TYPE_REPLY = 3;
    CONST TYPE_DEL = 4;

    const HANDLE_SUCCESS = 1;
    const HANDLE_ERROR = 2;

    /**
     * 添加操作日志
     * @param string $module
     * @param int $type
     * @param $message
     * @param $status
     * @param $content  /详细/描述
     * @return bool|void
     */
    public static function info($module, $type, $message=null, $status=1, $content = null)
    {
        $module_name = $module;
        $module = Permission::where("display_name", $module)->value("id");
        $id = auth()->user()->id;
        $username = auth()->user()->username;
        if (empty($message)) {
            switch ($type) {
                case self::TYPE_VIEW:
                    $message="查看了【{$module_name}】";
                    break;
                case self::TYPE_ADD:
                    $message="增加了【{$module_name}】";
                    break;
                case self::TYPE_REPLY:
                    $message="修改了【{$module_name}】";
                    break;
                case self::TYPE_DEL:
                    $message="删除了【{$module_name}】";
                    break;
            }
        }
        $message = "[{$username}]" . $message;
        //if (self::check($module, $message)) return;
        $context = [
            'user_id' => $id,
            'name' => $username,
            'module' => $module,
            'module_name' => $module_name,
            'type' => $type,
            'log' => $message,
            'status' => $status,
            'content' => $content,
            'created_at' => date("Y-m-d H:i:s")
        ];
        DB::table('operatinglog')->insert($context);
    }


    public static function check($module, $message)
    {
        $id = auth()->user()->id;
        $check = DB::table('operatinglog')
            ->where('user_id', $id)
            ->where('module', $module)
            ->where('log', $message)
            ->where('type', 1)
            ->where('created_at', '>=', date('Y-m-d H:i:s', (time() - 3600)))
            ->where('created_at', '<=', date('Y-m-d H:i:s', time()))->get(["id"]);
        if ($check) {
            return true;
        }
        return false;


    }

    /**
     * 获取模块信息
     * @auther ifehrim@gmail.com
     * @param $module
     * @param null $key
     * @return mixed|null
     */
    public static function module($module, $key = null)
    {
        $modules = self::$modules;
        if (empty($modules)) {
            $arr = Permission::all()->toArray();
            foreach ($arr as $item) {
                self::$modules[$item["id"]] = $item;
            }
            $modules = self::$modules;
        }
        if (is_array($modules)) {
            if (isset($modules[$module])) {
                if (!empty($key)) {
                    if (isset($modules[$module][$key])) {
                        return $modules[$module][$key];
                    } else {
                        return null;
                    }
                }
                return $modules[$module];
            }
        }
        return null;

    }


}