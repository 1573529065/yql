<?php
/**
 * Created by PhpStorm.
 * User: HWT51
 * Date: 2018/8/7
 * Time: 17:15
 */

namespace App\Vendor\QiNiu;


use App\Models\File;
use Qiniu\Auth;
use function Qiniu\base64_urlSafeDecode;
use function Qiniu\base64_urlSafeEncode;

class QiNiu
{
    /*
     * 存库空间名
     */
    protected $bucket;

    /*
     * 访问密匙
     */
    protected $accessKey;

    /*
     * 加密密匙
     */
    protected $secretKey;

    /*
     * 过期时间
     */
    protected $expire;

    protected $accessToken;

    protected $userId;

    protected $callbackUrl;

    protected $callbackBody = [
        'desc' => '$(x:desc)',
        'file_key' => '$(key)',
        'file_size' => '$(fsize)',
        'file_name' => '$(fname)',
        'mimeType' => '$(mimeType)',
        'image_width' => '$(imageInfo.width)',
        'image_height' => '$(imageInfo.height)',
        'image_orientation' => '$(imageInfo.orientation)',
    ];
    protected $files;

    /**
     * QiNiu constructor.
     * @param null $userId
     */
    public function __construct($userId = null)
    {
        $this->userId = $userId;
        $this->bucket = config('qiniu.bucket');
        $this->accessKey = config('qiniu.accessKey');
        $this->secretKey = config('qiniu.secretKey');
        $this->expire = config('qiniu.expire');
        $this->contentType = config('qiniu.contentType');
        $this->callbackUrl = config('qiniu.dpaUrl') . config('qiniu.callbackUrl');
    }

    /**
     * @param null $policy
     * @param bool $strictPolicy
     * @return string
     */
    public function generateToken($policy = null, $strictPolicy = true)
    {
        $auth = new Auth($this->accessKey, $this->secretKey);

        $result = $auth->uploadToken($this->bucket, null, $this->expire, $policy, $strictPolicy);

        return $result;
    }

    /**
     * set bucket
     *
     * @param $bucket
     * @return $this
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        return $this;
    }

    /**
     * set expire
     *
     * @param $expire
     * @return $this
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
        return $this;
    }

    /**
     * decode qiniuToken
     *
     * @param $token
     * @return mixed
     */
    public static function decodeToken($token)
    {
        $tmpArr = explode(':', $token);

        $decodeData = base64_urlSafeDecode($tmpArr[2]);

        $data = json_decode($decodeData, true);

        return $data;
    }

    public function setFiles(array $files)
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function generateTokensForFiles()
    {
        $files = $this->preProcessFiles()
            ->processFiles()
            ->generateTokenForFile();

        return $files;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function generateOneTokenForFiles()
    {
        $this->preProcessFiles()
             ->processFiles();

        return $this->files;
    }


    /**
     * @return $this
     * @throws \Exception
     */
    private function processFiles()
    {
        $userId = $this->userId;

        $files = $this->files;

        $dataWithThumbData = [];

        $dataWithoutThumbData = [];

        foreach ($files as $file) {
            if ($file['is_exist']) {
                $dataWithThumbData[] = $file;
            } else {
                $dataWithoutThumbData[] = $file;
            }
        }

        $dataClassification = [
            'dataWithThumbData' => $dataWithThumbData,
            'dataWithoutThumbData' => $dataWithoutThumbData,
        ];

        $dataWithThumbData = $dataClassification['dataWithThumbData'];

        $dataWithoutThumbData = $dataClassification['dataWithoutThumbData'];

        if (!empty($dataWithoutThumbData)) {
            $dataWithoutThumbData = File::bulkInsert($dataWithoutThumbData, $userId);
        }

        $this->files = array_merge($dataWithThumbData, $dataWithoutThumbData);

        return $this;
    }


    private function generateTokenForFile()
    {
        $files = $this->files;

        $files = array_map(function ($file) {

            if ($file['is_success']) return $file;

            $callbackUrl = $this->callbackUrl;

            $fileMaxSize = $file['max_size'];

            $callbackBody = $file['callback_body'];

            //todo 七牛SDK bug须将sdk更新至最新版, 否则生成的token无法用来上传。
            $callbackBody['thumb_data'] = base64_urlSafeEncode($file['thumb_data']);

            $callbackBodyJson = json_encode($callbackBody);

            $policy = [
                'fsizeLimit' => $fileMaxSize,
                'callbackUrl' => $callbackUrl,
                'callbackBody' => $callbackBodyJson,
                'callbackBodyType' => $this->contentType,
            ];

            $token = $this->generateToken($policy);

            $file['upload_token'] = $token;

            $file['thumb_url'] = $file['thumb_url'] ?? config('setting.IMG_QINIU') . $file['upload_name'];

            return $file;
        }, $files);

        $this->files = $files;

        return $files;
    }

    public function getToken()
    {
        $callbackUrl = $this->callbackUrl;

        $callbackBody = $this->callbackBody;

        $callbackBodyJson = json_encode($callbackBody);

        $policy = [
            'fsizeLimit' => config('setting.FILE_MAX_SIZE'),
            'callbackUrl' => $callbackUrl,
            'callbackBody' => $callbackBodyJson,
            'callbackBodyType' => $this->contentType,
        ];

        $token = $this->generateToken($policy);

        return $token;
    }

    /**
     * @return $this
     */
    private function preProcessFiles()
    {
        $userId = $this->userId;

        $callbackBody = $this->callbackBody;

        $files = $this->files;

        $hashes = array_pluck($files, 'hash');

        $filesInfo = File::getFilesByHashes($hashes);

        $tmpArr = [];

        foreach ($filesInfo as $value) {
            $tmpArr[$value['f_hash']] = $value;
        }

        $filesInfo = $tmpArr;

        $this->files = array_map(function ($file) use ($callbackBody, $userId, $filesInfo) {

            $fileSize = $file['size'] ?? false;
            $filePath = $file['path'] ?? false;
            $fileHash = $file['hash'] ?? false;

            if (empty($fileSize) && empty($filePath) && empty($fileHash)) {
                throw new \Exception('The parameter is invalid.');
            }

            $file['is_exist'] = $file['is_success'] = false;

            //文件是否已经存在
            if (!empty($filesInfo[$fileHash])) {

                $fileInfo = $filesInfo[$fileHash];

                $file['thumb_data'] = $fileInfo['f_thumbdata'];

                $file['is_exist'] = true;

                $file['thumb_url'] = ($fileInfo['f_resource'] == 1 ? config('setting.IMG_QINIU') : config('setting.FILE_DOMAIN')) . $fileInfo['f_file'];

                //文件是否上传成功
                if (($fileInfo['f_is_callback'] == File::IS_CALLBACK_YES &&
                        $fileInfo['f_resource'] != File::RESOURCE_LOCAL) ||
                    $fileInfo['f_resource'] == File::RESOURCE_LOCAL
                ) {
                    $file['is_success'] = true;
                    return $file;
                }
            }

            $fileName = $file['name'] = getFileNameByPath($filePath);

            $fileExtension = fileExtension($fileName);

            $fileType = determineTypeByExtension($fileExtension);

            $fileMaxSize = getMaxUploadSizeByType($fileType);

            $fileUploadName = $fileInfo['f_file'] ?? generateFileUploadName($fileName, $fileHash);

            $callbackBody['origin_hash'] = $fileHash;

            $file['name'] = $fileName;
            $file['max_size'] = $fileMaxSize;
            $file['extension'] = $fileExtension;
            $file['callback_body'] = $callbackBody;
            $file['upload_name'] = $fileUploadName;
            $file['resource'] = File::RESOURCE_QINIU;

            return $file;

        }, $files);

        return $this;
    }

    /**
     * @param $callbackBody
     * @param $authorization
     * @return bool
     */
    public function verifyCallback($callbackBody, $authorization)
    {
        $auth = new Auth($this->accessKey, $this->secretKey);

        $result = $auth->verifyCallback(
            $this->contentType,
            $authorization,
            $this->callbackUrl,
            $callbackBody
        );

        return $result;
    }

}