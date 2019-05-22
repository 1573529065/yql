<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 2/22/2018
 * Time: 9:47 AM
 */

namespace App\Unit;

use Closure;
use Exception;
use PFinal\Excel\Excel as vExcel;
use Storage;
use XLSXWriter;


class Excel
{


    /**
     * 导入和预处理
     * @auther ifehrim@gmail.com
     * @param $path
     * @param array $map
     * @param array $option
     * @param callable|null $callable
     * @return array
     */
    public static function parse($path, $map = [], $option = [], callable $callable = null)
    {
        $_path = $path;
        $path = config('setting.UPLOAD_URL') . $path;

        $option = array_merge(["print" => false], $option);

        $key = md5($path);
        $datas = [];
        $error = [];
        try {
            if (!file_exists($path) || !is_file($path)) {
                throw new Exception("文件导入错误" . $_path);
            }
            Timer::start("EXCEL解析", $option);
            $datas = vExcel::readExcelFile($path, $map);
            Timer::end("EXCEL解析");

            if (empty($datas)) {
                throw new Exception("没有数据");
            }
            if (isset($option['max_row']) && count($datas) > intval($option['max_row'])) {
                throw new Exception("数据超过{$option['max_row']}条，EXCEL表中:" . count($datas) . "条");
            }
            if (isset($option['min_row']) && count($datas) < intval($option['min_row'])) {
                throw new Exception("数据小于{$option['min_row']}条，EXCEL表中:" . count($datas) . "条");
            }


            if (isset($option['max_col']) && count($datas[0]) != intval($option['max_col'])) {
                $error[] = "Excel是" . count($datas[0]) . "列,超过{$option['max_col']}列 ";
            }

            if (isset($option['regular']) && is_array($option['regular'])) {
                foreach ($datas as $line => $data) {
                    $line = $line + 2;
                    $__e = [];

                    foreach ($option['regular'] as $k => $_callable) {
                        if (isset($data[$k]) && is_callable($_callable)) {
                            $___e = call_user_func($_callable, $data[$k]);

                            if (!empty($___e)) $__e[] = implode(",", $___e);
                        } else {
                            $__e[] = "({$map[$k]}) 不存在";
                        }
                    }
                    if (!empty($__e) && empty($option['delete'])) $error[$line] = "【第{$line}行】" . implode(";", $__e);

                    if (!empty($__e) && !empty($option['delete'])) unset($datas[$line - 2]);
                }
            }

            if (is_callable($callable)) {
                Timer::start("EXCEL处理", $option);
                list($key, $datas, $error) = call_user_func($callable, $key, $datas, $error);
                Timer::end("EXCEL处理");
            }

            return [$key, $datas, $error];
        } catch (Exception $e) {
            return [$key, $datas, ["EXCEL处理异常:" . $e->getMessage()]];
        }
    }

    /**
     * 默认处理规则方法
     * @auther ifehrim@gmail.com
     * @param null $callable
     * @param bool $isSelf
     * @return Closure
     */
    public static function regular($callable = null, $isSelf = false)
    {

        if (is_callable($callable) && $isSelf) {
            return $callable;
        }

        return function ($val) use ($callable) {
            $er = [];
            if (empty($val) || is_null($val)) {
                $er[] = "不能空";
            } else if (preg_match("/[\x7f-\xff]/", $val)) {
                $er[] = "不能汉字";
            } else if (!preg_match("/^[A-Za-z0-9]+$/", $val)) {
                $er[] = "不能包含符号和空格";
            }
            if (is_callable($callable)) {
                $e = call_user_func($callable, $val);
                if (!empty($e) && is_array($e)) {
                    $er = array_merge($er, $e);
                }
            }

            return $er;
        };
    }


    /**
     * 导出
     * @auther ifehrim@gmail.com
     * @param $data
     * @param $map
     * @param $file
     * $data = array(
     * array('id' => 1, 'name' => 'Jack', 'age' => 18, 'date'=>'2017-07-18'),
     * array('id' => 2, 'name' => 'Mary', 'age' => 20, 'date'=>'2017-07-18'),
     * array('id' => 3, 'name' => 'Ethan', 'age' => 34, 'date'=>'2017-07-18'),
     * );
     *
     * $map = array(
     * 'title'=>[
     * 'id' => '编号',
     * 'name' => '姓名',
     * 'age' => '年龄',
     * ],
     * );
     * @param string $workSheetName
     * @return string
     */
    public static function export($data, $map, $file = null, $workSheetName = "WorkSheet")
    {
        if (empty($file)) $file = timesrim();

        $file_path = storage_path("excel") . '/' . $file . ".xlsx";
        if (!isset($map['title'])) {
            if (count($data) > 0 && isset($data[0])) {
                $map['title'] = array_combine(array_keys($data[0]), array_keys($data[0]));
            } else {
                $map['title'] = array();
            }
        }
        $header = array();
        foreach ($map['title'] as $key => $val) {
            if (isset($map['simpleFormat'][$key])) {
                $header[$val] = $map['simpleFormat'][$key];
            } else {
                $header[$val] = 'GENERAL';
            }
        }
        $writer = new XLSXWriter();
        $writer->writeSheetHeader($workSheetName, $header);
        foreach ($data as $row) {
            $temp = array();
            foreach ($map['title'] as $key => $value) {
                if (isset($row[$key])) {
                    if (isset($map['regular']) && isset($map['regular'][$key]) && is_callable($map['regular'][$key])) {
                        $row[$key] = call_user_func($map['regular'][$key], $row[$key]);
                    }
                    $temp[] = $row[$key];
                } else {
                    $temp[] = '';
                }
            }
            $writer->writeSheetRow($workSheetName, $temp);
        }
        $writer->writeToFile($file_path);
        return $file_path;
    }


}