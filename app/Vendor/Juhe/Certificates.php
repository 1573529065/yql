<?php
/**
 * Created by PhpStorm.
 * User: ifehrim@gmail.com
 * Date: 12/19/2017
 * Time: 4:39 PM
 */

namespace App\Vendor\Juhe;

/**
 * 证件识别
 * document url https://www.juhe.cn/docs/api/id/153
 * @auther ifehrim@gmail.com
 * Class Certificates
 * @package App\Jobs\Juhe
 */
use App\Models\File;
use App\Unit\Json;
use Exception;
use Storage;

class Certificates
{
    /**
     * @var File
     */
    public $file;

    public $error = null;

    public $option = [];

    //2:二代身份证正面,3:二代身份证证背面,5:驾照,6:行驶证,19:车牌,
    CONST TYPE_IDCARD_2 = 2;
    CONST TYPE_IDCARD_3 = 3;
    CONST TYPE_DRIVING_LICENSE_PIC = 5;
    CONST TYPE_DRIVING_LICENSE = 6;


    private $url='http://v.juhe.cn/certificates/query.php';
    private $key='f53b6f81bbc6dfacc31d43591f5f2eca';
    private $type;
    private $pic;

    /**
     * Create a new job instance.
     * @param File $file
     * @param array $option
     */
    public function __construct(File $file, $option = null)
    {
        $this->type=$option['type'];
        $this->file=$file;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try{
            if($this->file instanceof File){
                $fi=config('setting.UPLOAD_URL').$this->file->f_file;
                if (Storage::exists($this->file->f_file)) {
                    $this->pic=$fi;
                    $json=$this->request();
                    return Json::decode($json);
                }
            }
            return ["error_code"=>-1,"message"=>"请输入图片资源"];
        }catch (Exception $e){
            return ["error_code"=>-1,"message"=>$e->getMessage()];
        }
    }


    public static function send(File $file, $type = self::TYPE_IDCARD_2)
    {
        $o = new static($file, ["type"=>$type]);
        return $o->handle();
    }


    /**
     * @auther ifehrim@gmail.com
     * @return mixed
     * @throws Exception
     */
    protected function request()
    {
        $url = $this->url;
        if(strtoupper(substr(PHP_OS,0,3))==='WIN'){
            $this->pic=str_replace('/','\\',$this->pic);
        }
        $pic=curl_file_create($this->pic, '', md5(date('Y-m-d H:i:s')) . '.jpg');
        $data = array(
            'key' => $this->key,
            'cardType' => $this->type,
            'pic' => $pic
        );
        $httpInfo = array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);         //一般因为路径引起的错误都会提示:couldn't open file "cc.jpg"
        if ($response === FALSE) {
            //如果提示你上传图片太小等,可以打印出$cFile这个变量看看具体图片信息
            //一般因为路径引起的错误都会提示:couldn't open file "cc.jpg"
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            throw new Exception('服务器状态码: ' . $httpCode);
        }
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }

    /**
     * 2:二代身份证正面,3:二代身份证证背面,5:驾照,6:行驶证,19:车牌,
     * @auther ifehrim@gmail.com
     * @param $type
     * @return bool
     */
    public static function check($type)
    {
        if(in_array($type,[2,3,5,6,19])){
            return true;
        }
        return false;
    }


}