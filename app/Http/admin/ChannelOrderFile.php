<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2019/4/25
 * Time: 9:36
 */

namespace App\Controller\Admin;

use App\Dal\SystemAdmin;
use App\Service\Helper;
use App\Service\Pagination;
use App\Dal\ChannelOrderFile as ChannelOrderFileDal;


class ChannelOrderFile extends BaseController
{

    /**
     * 文件日志
     */
    public function index()
    {
        $pagesize = 15;
        $curpage = max(intval($this->request->get('p')), 1);
        $offset = ($curpage - 1) * 15;

        $admin_id = $this->request->get('admin_id');
        $nickname = $this->request->get('nickname');
        $finance_type = $this->request->get('finance_type');
        $type = $this->request->get('type');
        $start_time = $this->request->get('start_time');
        $end_time = $this->request->get('end_time');

        $where = [];
        if (!empty($admin_id)) {
            $where['admin_id'] = $admin_id;
        }
        if (!empty($finance_type)) {
            $where['finance_type'] = $finance_type;
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

        $list = ChannelOrderFileDal::fetchList($where, $offset, $pagesize, 'id DESC');
        $total = ChannelOrderFileDal::count($where);

        $page = new Pagination($total, $pagesize, $curpage);

        $adminIds = array_unique(array_column($list, 'admin_id'));
        $adminInfos = !empty($adminIds) ? SystemAdmin::fetchAll(['id in' => $adminIds], 'id asc', 'id,nickname') : [];
        $adminInfos = Helper::arrayReindex($adminInfos, 'id');

        $this->view->setVars([
            'list' => $list,
            'page' => $page->generate(),
            'p' => $curpage,
            'admin_id' => $admin_id,
            'nickname' => $nickname,
            'type' => $type,
            'finance_type' => $finance_type,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'adminInfos' => $adminInfos,
        ]);
    }


    /**
     * 文件下载
     */
    public function download_file()
    {
        $id = intval($this->request->get('id'));
        empty($id) && Helper::json(false, '缺少参数');

        $fileInfo = ChannelOrderFileDal::fetchOne(['id' => $id], 'id,url,name');

        if (!isset($fileInfo['url'])) {
            return '文件不存在';
        }
        try {
            $filePath = $fileInfo['url'];
            ob_end_clean(); // 清空缓冲区并关闭输出缓冲

            //r: 以只读方式打开，b: 强制使用二进制模式
            $fileHandle = fopen($filePath, "rb");
            if ($fileHandle === false) {
                throw new \Exception("找不到文件: $filePath\n");
            }
            $ext = pathinfo( parse_url( $filePath, PHP_URL_PATH ), PATHINFO_EXTENSION ) ?? '';

            $arr = explode('.', $fileInfo['name']);
            if (!isset($arr[1])){
                $fileInfo['name'] = $fileInfo['name'] . '.' . $ext;
            }

            Header("Content-type: application/octet-stream");
            Header("Content-Transfer-Encoding: binary");
            Header("Accept-Ranges: bytes");
            if (file_exists($filePath)){
                Header("Content-Length: " . filesize($filePath));
            }
            Header("Content-Disposition: attachment; filename=" . $fileInfo['name']);

            while (!feof($fileHandle)) {
                echo fread($fileHandle, 20971520); //从文件指针 handle 读取最多 length 个字节 20M
            }
            fclose($fileHandle);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }


}