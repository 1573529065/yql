<?php
/**
 * 常用方法
 */

use App\Jobs\File\Upload;
use App\Model\Credit;
use App\Model\Userdetails;
use App\Unit\Messager;
use App\Models\File as mFile;
use Carbon\Carbon;


if (!function_exists("success")) {
    /**
     * 处理信息
     * @auther ifehrim@gmail.com
     * @param null $msg
     * @return array
     */
    function success($msg = null)
    {
        return ["success" => true, "msg" => $msg];
    }
}
if (!function_exists("is_success")) {
    /**
     * 判断是否出错
     * @auther ifehrim@gmail.com
     * @param $msg
     * @return bool
     */
    function is_success($msg)
    {
        if (is_array($msg) && isset($msg["success"]) && $msg["success"] === true) {
            return true;
        }
        return false;
    }
}


if (!function_exists("error")) {
    /**
     * 出错处理
     * @auther ifehrim@gmail.com
     * @param $msg
     * @return array
     */
    function error($msg = null)
    {
        return ["error" => true, "msg" => $msg];
    }
}
if (!function_exists("is_error")) {
    /**
     * 判断是否出错
     * @auther ifehrim@gmail.com
     * @param $msg
     * @return bool
     */
    function is_error($msg)
    {
        if (is_array($msg) && isset($msg["error"]) && $msg["error"] === true) {
            return true;
        }
        return false;
    }
}


/**
 * 消息通知
 * @param int $code 推送的类型
 * @param array|int $fub_id 接收推送信息的用户Ids
 * @param null|string $content 推送的内容
 * @param int $authtype 审核的类型，自定义推送不需要这个参数
 * @return void
 * @author jerry Frank.K.Yuan  & ifehrim@gmail.com
 * @update_at 2017-11-21 12:00:00 of ifehrim@gmail.com
 *
 * 1001 加好友
 * 1002 成为好友
 * 1003 删除好友
 * 2001 周报
 * 3001 任务完成
 * 3002 账号被顶
 * 4001 每日推送
 * 6001 安卓更新
 * 8001 微信推送 **
 * 5001 头像审核
 * 5002 背景审核
 * 5003 身份认证
 * 5004 车辆认证
 * 6001 安卓更新
 * 7000 天气推送
 * 8001 自定义服务
 * 9010 服务推送
 * 9002 营销活动
 *
 */
if (!function_exists("messager")) {
    function messager($code, $fub_id, $content = null, $authtype = 1)
    {
        static $messager = null;
        if (empty($messager)) $messager = new Messager();
        $messager->handel($code, $fub_id, $content, $authtype);
    }
}


/**
 * @return float
 */
if (!function_exists("ftime")) {
    function ftime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}


/**
 * 获取时间 0000-00-00 00:00:00
 * @param $time
 * @return false|string
 * @author ifehrim@gmail.com
 */
if (!function_exists("timestamp")) {
    function timestamp($time = null, $isDayFormat = false, $format = "Y-m-d H:i:s")
    {
        if ($isDayFormat === true) $format = "Y-m-d 00:00:00";
        if ($time === null) $time = time();
        if (is_numeric($time) && strlen($time) > 8) {
            return date($format, $time);
        }
        if (is_string($isDayFormat)) $format = $isDayFormat;
        return date($format, strtotime($time));
    }
}

/**
 * 获取时间 1511744065
 * @param $time
 * @param bool $toDay
 * @return int|string
 * @author ifehrim@gmail.com
 */
if (!function_exists("timesint")) {
    function timesint($time = null, $toDay = false)
    {
        if ($time === null) $time = time();
        if ($toDay) return strtotime(date('Y-m-d', strtotime($time)));
        return strtotime(timestamp($time));
    }
}
/**
 * 获取时间 20180312454574
 * @param $time
 * @param bool $toDay
 * @return int|string
 * @author ifehrim@gmail.com
 * @time 3/7/2018 5:08 PM
 */
if (!function_exists("timesrim")) {
    function timesrim($time = null, $toDay = false)
    {
        if ($time === null) $time = time();
        if ($toDay) return strtotime(date('Y-m-d', strtotime($time)));
        return str_replace([' ', '-', ':'], "", timestamp($time));
    }
}


/**
 * 生成随机字符串
 * @param int $length 生成随机字符串的长度
 * @param string $string 前缀
 * @param string $char 组成随机字符串的字符串
 * @return string $string 生成的随机字符串
 * @author ifehrim@gmail.com
 */
if (!function_exists("ticket")) {
    function ticket($length = 32, $string = '', $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if (!is_int($length) || $length < 0) {
            return false;
        }
        for ($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }
        return $string;
    }
}


/**
 * 哈希目录结构
 *
 * @param string $hash
 * @param string $prefix
 * @param string $levels
 * @return string
 * @author jerry
 */

if (!function_exists("getHashDir")) {
    function getHashDir($hash, $prefix = '', $levels = '1:1:2')
    {
        $levelarr = explode(':', $levels);
        $cnt = 0;
        foreach ($levelarr as $level) {
            $cnt -= $level;
            $prefix .= substr($hash, $cnt, $level) . '/';
        }
        return $prefix;
    }
}


/**
 *哈希文件
 *
 * @param string $file
 * @return string
 * @author jerry
 */
if (!function_exists("setHashFile")) {
    function setHashFile($file)
    {
        $hash = sha1_file($file);
        /*
         $cmd = "shasum $file | cut -d ' ' -f 1";
         $hash = exec($cmd);
         */
        return $hash;
    }
}

/**
 * 得到图片地址
 * @param $thumbdata
 * @param string $type
 * @param string $url
 * @return bool|string
 * @author ifehrim@gmail.com
 */
if (!function_exists("media_info")) {
    function media_info($thumbdata, $isHash = false)
    {
        if ($isHash) {
            $hash = $thumbdata;
        } else {
            if (empty($thumbdata) || !is_string($thumbdata)) {
                return false;
            }
            $arr = explode('?', $thumbdata);
            if (is_array($arr) && count($arr) != 4) return [];
            list($id, $hash, $size, $ext) = $arr;
        }
        return \App\Models\File::where("f_hash", $hash)->get()->toArray();
    }
}


/**
 * 得到图片地址
 * old PhotoController::_getImgUrl
 * 15?53092b71237daf1e7712760d0c8ef12c2057853f?234990?jpg
 * id ? hash ? size ? ext
 * @param $thumbdata
 * @param string $type
 * @param string $url
 * @param bool $geometry
 * @return bool|string
 * @author ifehrim@gmail.com
 */
if (!function_exists("media_url")) {
    function media_url($thumbdata, $type = '', $url = null, $geometry = null)
    {
        if (strpos($thumbdata, "://")) {
            return $thumbdata;
        }
        if (empty($thumbdata) || !is_string($thumbdata)) {
            return false;
        }
        $arr = explode('?', $thumbdata);
        if (is_array($arr) && count($arr) != 4) return false;
        list($id, $hash, $size, $ext) = $arr;
        $resource = mFile::find($id, ['f_resource', 'f_file', 'f_geometry', 'f_2geometry', 'f_4geometry']);
        if (isset($resource->f_resource) && $resource->f_resource == 1) {
            $qn_img = Config::get('setting.IMG_QINIU') . '/' . $resource->f_file;
            if ($geometry) {//防止识别不出来长宽数据  返回，0
                return $resource->f_geometry ? $qn_img . ",{$resource->f_geometry}" : $qn_img . ',0';
            }
            return $qn_img;
        }
        if ($url === null) $url = config('setting.FILE_DOMAIN');
        $path = getHashDir($hash) . $hash;
        switch ($type) {
            case "task":
                $url .= '/task/' . $path . '.' . $ext;
                break;
            case "logo":
                $url .= '/logo/' . $path . '.' . $ext;
                break;
            case "s":
                $url .= '/files/' . $path . '_2.' . $ext;
                break;
            default:
                $url .= '/files/' . $path . '.' . $ext;
                break;
        }
        if ($geometry) {
            $file = mFile::find($id);
            if ($file instanceof mFile) {
                $url = $file->f_geometry ? $url . ",{$file->f_geometry}" : $url . ",0";
            }
        }

        return $url;
    }
}


/**
 * 通用的文件上传
 * @param $file
 * @param string $type
 * @param array $option
 * @return array
 * @author ifehrim@gmail.com
 */
if (!function_exists("media_upload")) {
    function media_upload($file, $type = 'img', $option = null, $fileType = null)
    {
        $result = [];
        switch ($type) {
            case "task":
                $path = 'task/';
                break;
            case "logo":
                $path = 'logo/';
                break;
            default:
                $path = 'files/';
                break;
        }
        $job = new Upload($file, $path, $option, $fileType);
        $ret = $job->handle();
        if (is_int($ret)) {
            $result['error'] = $ret;
            return $result;
        }
        if ($job->failed()) {
            $result['error'] = $job->error;
        } else {
            $result['file'] = $job->mFile->toArray();
        }
        return $result;
    }
}


/**
 *
 * 返回数据封装
 *
 * @param int $code
 * @param string $message
 * @param mixed $data
 * @param bool $other
 * @return Illuminate\Http\JsonResponse
 * @author jreey and @ifehrim
 */
if (!function_exists("response_json")) {
    function response_json($first = 200, $message = "success", $data = null, $arr = [])
    {
        $param = func_get_args();
        /**
         * @author ifehrim @date 2018-2-27
         * first value is string run the condition
         * just not insert $first like this response_json($message,$data,$arr);
         */
        if (is_string($first)) {
            $message = $first;
            $first = 200;
            if (isset($param[1])) {
                $data = $param[1];
                $arr = [];
            }
            if (isset($param[2])) {
                $arr = $param[2];
            }
        }
        /**
         * @author ifehrim @date 2018-2-27
         * first value is array run the condition
         * just not insert $first and $message like this response_json($data,$arr);
         */

        if (is_array($first) || is_object($first)) {
            $data = $first;
            $message = "success";
            $first = 200;
            $arr = [];
            if (isset($param[1])) {
                $arr = $param[1];
            }
        }

        /**
         * because $first is not 200 ; so its not success;
         */
        if ($message == "success" && $first != 200) {
            $message = "failed";
        }


        $array = array(
            'status_code' => $first,
            'status' => $message == "success" ? "操作成功" : ($message == "failed" ? "操作失败" : $message),
        );
        if (!empty($data) && !is_null($data)) {
            $array['data'] = $data;
        }
        if (is_array($arr)) {
            $array = array_merge($array, $arr);
        }
        return response()->json($array);
    }

}


if (!function_exists("combine")) {
    function combine($array, $key)
    {
        if (empty($array) || empty($key) || (count($array) !== count($key))) return false;

        foreach ($array as $k => $val) {
            $array[$key[$k]] = $val;
        }
        return $array;
    }
}

if (!function_exists('shoplogout')) {
    function shoplogout()
    {
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', env('SHOP_SERVER_URL'), [
            'm' => 'Home',
            'c' => 'Users',
            'a' => 'logout',
            'f' => 'api'
        ]);
        return $res->getBody()->getContents();
    }
}


if (!function_exists('curl_file_create')) {
    function curl_file_create($fileName, $mimeType = '', $postName = '')
    {
        return "@$fileName;filename=" . ($postName ?: basename($fileName)) . ($mimeType ? ";type=$mimeType" : '');
    }
}

if (!function_exists('part_of_date')) {
    //获取日期的一部分   $stopLetter  Y  m   d    H  i s 返回Y-stopLetter时间
    function part_of_date($stopLetter = 'd', $date)
    {
        if ($date) {
            switch ($stopLetter) {
                case 'Y':
                    $ret = date('Y', strtotime($date));;
                    break;
                case 'm':
                    $ret = date('Y-m', strtotime($date));;
                    break;
                case 'd':
                    $ret = date('Y-m-d', strtotime($date));;
                    break;
                case 'H':
                    $ret = date('Y-m-d H', strtotime($date));;
                    break;
                case 'i':
                    $ret = date('Y-m-d H:i', strtotime($date));;
                    break;
                case 's':
                    $ret = date('Y-m-d H:i:s', strtotime($date));;
                    break;
                default:
                    $ret = $date;
                    break;
            }
            return $ret;
        }
        return '';
    }
}

/**
 * Determine file type by file suffix
 *
 * @param string $extension
 * @return string
 */
if (!function_exists('determineTypeByExtension')) {
    function determineTypeByExtension(string $extension): string
    {
        $types = config('setting.mime');

        $result = 'file';

        foreach ($types as $type => $value) {
            if (isset($value[$extension])) {
                $result = $type;
                break;
            }
        }

        return $result;
    }
}

/**
 * @param string $filename
 * @return string
 */
if (!function_exists('fileExtension')) {
    function fileExtension(string $filename): string
    {
        $fileExtension = pathinfo($filename)['extension'] ?? '';

        return $fileExtension;
    }
}

if (!function_exists('getMimeSetting')) {
    /**
     * @param string $type
     * @return array
     */
    function getMimeSetting(string $type): array
    {
        $mimeTypesSetting = config('setting.mime.' . $type);

        $result = is_array($mimeTypesSetting) ? array_values($mimeTypesSetting) : [];

        return $result;
    }
}

if (!function_exists('generateFileUploadName')) {
    /**
     * @param string $fileName
     * @param string $hash
     * @return string
     */
    function generateFileUploadName(string $fileName, string $hash): string
    {
        $date = date('Ymd');

        $fileExtension = fileExtension($fileName);

        $type = determineTypeByExtension($fileExtension);

        $directoryInfoArray = [
            $type,
            $date,
            $hash
        ];

        $directory = implode('/', $directoryInfoArray);

        $fileUploadName = $directory . '.' . $fileExtension;

        return $fileUploadName;
    }
}

if (!function_exists('getMaxUploadSizeByType')) {
    function getMaxUploadSizeByType(string $type)
    {
        $fileMaxSize = $type == 'img' ?
            config('setting.IMAGE_MAX_SIZE') : config('setting.FILE_MAX_SIZE');

        return $fileMaxSize;
    }
}

if (!function_exists('getFileNameByPath')) {
    function getFileNameByPath(string $path)
    {
        return pathinfo($path)['basename'];
    }
}

if (!function_exists('isCarLicense')) {
    /**
     * 车牌正则
     * @param $license
     * @return bool
     */
    function isCarLicense($license)
    {
        if (empty($license)) {
            return false;
        }

        //匹配民用车牌和使馆车牌

        $regular = "/[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新使]{1}[A-Z]{1}[0-9a-zA-Z]{5}$/u";
        preg_match($regular, $license, $match);
        if (isset($match[0])) {
            return true;
        }

        //匹配特种车牌(挂,警,学,领,港,澳)

        $regular = '/[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新]{1}[A-Z]{1}[0-9a-zA-Z]{4}[挂警学领港澳]{1}$/u';
        preg_match($regular, $license, $match);
        if (isset($match[0])) {
            return true;
        }

        //匹配军牌

        $regular = '/^WJ[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新]?[0-9a-zA-Z]{5}$/ui';
        preg_match($regular, $license, $match);
        if (isset($match[0])) {
            return true;
        }


        $regular = "/[A-Z]{2}[0-9]{5}$/";
        preg_match($regular, $license, $match);
        if (isset($match[0])) {
            return true;
        }

        //匹配新能源车辆6位车牌

        //小型新能源车

        $regular = "/[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新]{1}[A-Z]{1}[DF]{1}[0-9a-zA-Z]{5}$/u";
        preg_match($regular, $license, $match);
        if (isset($match[0])) {
            return true;
        }

        //大型新能源车

        $regular = "/[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼川贵云渝藏陕甘青宁新]{1}[A-Z]{1}[0-9a-zA-Z]{5}[DF]{1}$/u";
        preg_match($regular, $license, $match);
        if (isset($match[0])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('getRankingCacheKey')) {
    function getRankingCacheKey($type)
    {

        $key = 'laravel:daily:rank:' . $type;
        $now = Carbon::now();

        $updatedAt = Carbon::parse(date('Y-m-d 02:00:00'));

        $isGT = $now->gt($updatedAt);

        if ($isGT) {
            $dateSuffix = $now->format('Ymd');
        } else {
            $dateSuffix = Carbon::yesterday()->format('Ymd');
        }

        $key .= ':' . $dateSuffix;

        return $key;
    }
}

if (!function_exists('pointReward')) {
    function pointReward($ub_id, $type, $credit, $c_modified = 1, $item = '', $c_content = '', $gsid = 0, $gt_id = 0)
    {
        $userDetails = new Userdetails();
        $creditModel = new Credit();
        if ($c_modified == 1) {
            $userDetails->upCredit($ub_id, $credit);
        } else {
            $userDetails->reduceCredit($ub_id, $credit);
        }
        $creditModel->addCreditlog($ub_id, $type, $credit, $c_modified, $item, $c_content, $gsid, $gt_id);
    }
}

if (!function_exists('processPhoneBrand')) {

    /**
     * @param string $brand
     * @return string
     */
    function processPhoneBrand(string $brand)
    {
        return strtolower(str_replace(' ', '', $brand));
    }
}

if (!function_exists('subFloat')) {
    /**
     * 截取浮点数
     * @param float $float
     * @param int $precision
     * @return float
     */
    function subFloat(float $float, int $precision)
    {
        $precision = $precision < 0 ? 0 : $precision;
        $dotPos = strpos($float, '.');
        if ($dotPos == 0) return $float;
        $precision += $dotPos + 1;
        $float = sprintf('%' . $precision . '.f', $float);
        return (float)substr($float, 0, $precision);
    }
}

if (!function_exists('getAbnormalLogInInfo')) {
    function getAbnormalLogInInfo()
    {
        $request = app('Illuminate\Http\Request');

        $model = $request->get('device_model', '其他设备');

        $format = '您的账号于 %s 在 %s 上登录';

        return sprintf($format, date('Y/m/d H:i'), $model);
    }
}