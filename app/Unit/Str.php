<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 11/21/2017
 * Time: 2:18 PM
 */

namespace App\Unit;

use App\Models\Air\Air;
use App\Models\Boundobd;
use App\Models\Rccobd;
use App\Models\Supplyer\SupplyObdItem;
use Illuminate\Support\Str as BaseStr;

class Str extends BaseStr
{

    /**
     * {sql}不能重复数据
     * @auther ifehrim@gmail.com
     * @param $str
     * @return array
     */
    public static function sql_duplicate($str)
    {
        $e = [];
        $isMatched = preg_match_all('/Duplicate entry \'(\w+)\' for key/', $str, $matches);
        if ($isMatched) {
            if (isset($matches[1]) && is_array($matches[1])) {
                foreach ($matches[1] as $match) {
                    $e[] = "不能重复已有数据库信息：{$match}，请先处理";
                }
            }
        }
        return $e;
    }

    /**
     * 验证手机号
     * @time 4/10/2018 09:34
     * @auther ifehrim@gmail.com
     * @param $str
     * @return bool
     */
    public static function isPhone($str)
    {
        if (preg_match("/(^0{0,1}1[3|4|5|6|7|8|9][0-9]{9}$)/", $str)) {
            return true;
        }
        return false;

    }


    /**
     * 是否中文
     * @auther ifehrim@gmail.com
     * @param $str
     * @return bool
     */
    public static function isChines($str)
    {
        if (preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $str)) {
            return true;
        }
        return false;
    }


    CONST CUT_BEGIN = 1;
    CONST CUT_END = 2;
    CONST CUT_CURRENT = 3;


    public static function cut($str, $find, $type = self::CUT_BEGIN)
    {

        $ret = "";
        $arr = explode($find, $str);
        if (!empty($arr)) {
            switch ($type) {
                case self::CUT_BEGIN:
                    $ret = $arr[0] . $find;
                    break;
                case self::CUT_END:
                    $ret = $find . $arr[count($arr) - 1];
                    break;
                case self::CUT_CURRENT:
                    $ret = $arr[floor(count($arr) / 2)] . $find;
                    break;
            }
        }
        return $ret;
    }

    /**
     * 字符串转数组
     * @author jerry
     * @date   2016-12-29
     * @update 2017-11-21 of ifehrim@gmail.com
     * @param $str
     * @param string $mark
     * @return array
     */
    public static function toArray($str, $mark = ',')
    {
        if (empty($str) || empty($mark)) {
            return [];
        }
        $result = explode($mark, $str);
        if (empty($result)) {
            return [];
        }
        return $result;
    }


    /**
     *
     * @auther ifehrim@gmail.com
     * @param $string
     * @param string $explode
     * @return array
     */
    public static function split($string, $explode = ",:")
    {
        $remark = explode($explode[0], $string);
        $arr = [];
        if (is_array($remark)) {
            foreach ($remark as $item) {
                $item_arr = explode($explode[1], $item);
                $arr[$item_arr[0]] = $item_arr[1];
            }
        }
        return $arr;
    }


    /**
     * 中文转拼音 (utf8版,gbk转utf8也可用)
     * @param string $str utf8字符串
     * @param string $ret_format 返回格式 [all:全拼音|first:首字母|one:仅第一字符首字母]
     * @param string $placeholder 无法识别的字符占位符
     * @param string $allow_chars 允许的非中文字符
     * @return string 拼音字符串
     */
    public static function pinyin($str, $ret_format = 'all', $placeholder = '_', $allow_chars = '/[a-zA-Z\d ]/')
    {
        static $pinyins = null;

        if (null === $pinyins) {
            $data = file_get_contents(storage_path('dat/pinyin.dat'));

            $rows = explode('|', $data);

            $pinyins = array();
            foreach ($rows as $v) {
                list ($py, $vals) = explode(':', $v);
                $chars = explode(',', $vals);

                foreach ($chars as $char) {
                    $pinyins[$char] = $py;
                }
            }
        }

        $str = trim($str);
        $len = mb_strlen($str, 'UTF-8');
        $rs = '';
        for ($i = 0; $i < $len; $i++) {
            $chr = mb_substr($str, $i, 1, 'UTF-8');
            $asc = ord($chr);
            if ($asc < 0x80) { // 0-127
                if (preg_match($allow_chars, $chr)) { // 用参数控制正则
                    $rs .= $chr; // 0-9 a-z A-Z 空格
                } else { // 其他字符用填充符代替
                    $rs .= $placeholder;
                }
            } else { // 128-255
                if (isset($pinyins[$chr])) {
                    $rs .= 'first' === $ret_format ? $pinyins[$chr][0] : ($pinyins[$chr] . ' ');
                } else {
                    $rs .= $placeholder;
                }
            }

            if ('one' === $ret_format && '' !== $rs) {
                return strtoupper($rs[0]);
            }
        }

        return rtrim($rs, ' ');
    }


    /**
     * 得到首字母大写
     * @param string
     * @return string
     * @author jerry
     */
    public static function first_pinyin($s0)
    {
        $firstchar_ord = ord(strtoupper($s0{0}));
        if (($firstchar_ord >= 65 and $firstchar_ord <= 91) or ($firstchar_ord >= 48 and $firstchar_ord <= 57)) {
            return strtoupper($s0{0});
        }
        $s = iconv("UTF-8", "gb2312", $s0);

        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
        if ($asc >= -20319 and $asc <= -20284) {
            return "A";
        }
        if ($asc >= -20283 and $asc <= -19776) {
            return "B";
        }
        if ($asc >= -19775 and $asc <= -19219) {
            return "C";
        }
        if ($asc >= -19218 and $asc <= -18711) {
            return "D";
        }
        if ($asc >= -18710 and $asc <= -18527) {
            return "E";
        }
        if ($asc >= -18526 and $asc <= -18240) {
            return "F";
        }
        if ($asc >= -18239 and $asc <= -17923) {
            return "G";
        }
        if ($asc >= -17922 and $asc <= -17418) {
            return "H";
        }
        if ($asc >= -17417 and $asc <= -16475) {
            return "J";
        }
        if ($asc >= -16474 and $asc <= -16213) {
            return "K";
        }
        if ($asc >= -16212 and $asc <= -15641) {
            return "L";
        }
        if ($asc >= -15640 and $asc <= -15166) {
            return "M";
        }
        if ($asc >= -15165 and $asc <= -14923) {
            return "N";
        }
        if ($asc >= -14922 and $asc <= -14915) {
            return "O";
        }
        if ($asc >= -14914 and $asc <= -14631) {
            return "P";
        }
        if ($asc >= -14630 and $asc <= -14150) {
            return "Q";
        }
        if ($asc >= -14149 and $asc <= -14091) {
            return "R";
        }
        if ($asc >= -14090 and $asc <= -13319) {
            return "S";
        }
        if ($asc >= -13318 and $asc <= -12839) {
            return "T";
        }
        if ($asc >= -12838 and $asc <= -12557) {
            return "W";
        }
        if ($asc >= -12556 and $asc <= -11848) {
            return "X";
        }
        if ($asc >= -11847 and $asc <= -11056) {
            return "Y";
        }
        if ($asc >= -11055 and $asc <= -10247) {
            return "Z";
        }
        return '#';
    }

    /**
     * 获取星座
     * @param $birth
     * @return bool
     */
    public static function getConstellation($birth)
    {
        list($year, $month, $day) = explode('-', $birth);
        // 检查参数有效性
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return '摩羯座';
        }
        // 星座名称以及开始日期
        $signs = array(
            array("20" => "水瓶座"),
            array("19" => "双鱼座"),
            array("21" => "白羊座"),
            array("20" => "金牛座"),
            array("21" => "双子座"),
            array("22" => "巨蟹座"),
            array("23" => "狮子座"),
            array("23" => "处女座"),
            array("23" => "天秤座"),
            array("24" => "天蝎座"),
            array("22" => "射手座"),
            array("22" => "摩羯座")
        );
        foreach ($signs[(int)$month - 1] as $k => $v) {
            $sign_start = $k;
            $sign_name = $v;
        }

        if ($day < $sign_start) {
            foreach ($signs[($month - 2 < 0) ? $month = 11 : $month -= 2] as $k => $v) {
                $sign_start = $k;
                $sign_name = $v;
            }
        }

        return $sign_name;
    }

    public static function unixDate($unixtime, $timezone = 'PRC')
    {
        $datetime = new \DateTime("@$unixtime");
        $datetime->setTimezone(new \DateTimeZone($timezone));
        return $datetime->format("Y-m-d H:i:s");
    }

    /**
     * @param $type
     * @param $device_no
     * @param $code
     * @return bool|string
     */
    public static function verifyDevice($type, $device_no, $code)
    {
        switch ($type) {
            case 1:
                $aowen = substr($device_no, 0, 2);
                if ($aowen == 'YL') {
                    $supply = SupplyObdItem::where(['serial_number' => $device_no, 'parity_check' => $code, 'status' => 0])->first();
                    if ($supply instanceof SupplyObdItem) {
                        //todo ve-box secondary type judgment
                        return Boundobd::device_status($device_no, $type) ? 'AoWen' : false;
                    }
                    return false;
                }
                $identifier = substr($device_no, 0, 1);
                $device = substr($device_no, 5, 1);
                if (($identifier == 6 && $device == 3) || ($identifier == 3 && $device == 5)) {
                    $rcc = Rccobd::where(['r_imei'=>$device_no,'r_code'=>$code])->first();
                    if ($rcc instanceof Rccobd) {
                        //todo Rcc verification
                        return Boundobd::device_status($device_no, $type) ? ($identifier == 3 ? 'Rcc_29B' : 'Rcc_30B') : false;
                    }

                }
                break;
            case 2:
                $air = Air::where(['air_imei' => $device_no, 'air_code' => $code])->first();
                if ($air instanceof Air) {
                    return Boundobd::device_status($device_no, $type) ? 'Air' : false;
                }
                return false;
                break;
        }
        return false;
    }


    public static function test()
    {
        return "aaaaaaaaaaaaaaaa";
    }

}