<?php

namespace App\Vendor\Baidu;

use App\Unit\AipOcr;

class AI
{
    protected $aipocr;
    protected $appId = '11549946';
    protected $apiKey = 'wLGqBrgGpV3IRiG6j8FgvNPq';
    protected $secretKey = '0xYqmOm9wSpasYVL05zECthtr9ca2bCr';

    public function __construct()
    {
        $this->aipocr = new AipOcr($this->appId, $this->apiKey, $this->secretKey);
    }

    public function documentIdentification($filePath = '', $type = '')
    {
        $res = array();
        if (empty($filePath) || empty($type)) return;
        if (!function_exists('curl_file_create')) {
            function curl_file_create($fileName, $mimeType = '', $postName = '')
            {
                return "@$fileName;filename=" . ($postName ?: basename($fileName)) . ($mimeType ? ";type=$mimeType" : '');
            }
        }
        $cFile = curl_file_create($filePath, '', md5(date('Y-m-d H:i:s')) . '.jpg');

        if ($type == 2 || $type == 3) {
            $image = file_get_contents($cFile->name);
            if ($type == 2) {
                $idCardSide = "front";
            } else {
                $idCardSide = "back";
            }
            // 如果有可选参数
            $options = array();
            $options["detect_direction"] = true;
            $options["detect_risk"] = "false";
            $res = $this->aipocr->idcard($image, $idCardSide, $options);
        } elseif ($type == 5) {
            $image = file_get_contents($cFile->name);
            $options = array();
            $options["detect_direction"] = false;
            $res = $this->aipocr->drivingLicense($image, $options);
        } elseif ($type == 6) {
            $image = file_get_contents($cFile->name);
            $options = array();
            $options["detect_direction"] = false;
            $res = $this->aipocr->vehicleLicense($image, $options);
        }
        return $res;
    }

    public function arrayToObject($e)
    {

        if (gettype($e) != 'array') return;
        foreach ($e as $k => $v) {
            if (gettype($v) == 'array' || getType($v) == 'object')
                $e[$k] = (object)$this->arrayToObject($v);
        }
        return (object)$e;
    }

}