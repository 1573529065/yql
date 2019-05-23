<?php

namespace App\Controller\Admin;

use Phalcon\Mvc\Controller;
use App\Service\Helper;
use Phalcon\Mvc\View;

class Oss extends Controller
{
    public function get_token()
    {
        $config = [];
        $dir = $this->request->get('dir');
        if (!empty($dir)) {
            $config['upload_dir'] = $dir;
        }
        echo json_encode(Helper::getOssWebUploadToken($config));
        exit;
    }

    public function upload_demo()
    {
        $this->view->setRenderLevel(View::LEVEL_LAYOUT);
    }

    public function video()
    {
        $this->view->setRenderLevel(View::LEVEL_LAYOUT);
    }
}