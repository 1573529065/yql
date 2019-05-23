<?php

namespace App\Controller\Admin;

use App\Dal\StopWords as StopWordsDal;
use App\Service\Helper;

class StopWords extends BaseController
{
    function index()
    {
        if ($this->request->isPost()) {
            $words = $this->request->getPost('words');
            StopWordsDal::update(1, ['words' => $words]);
            Helper::json(true);
        }

        $row = StopWordsDal::fetchOne(1);
        if (empty($row)) {
            StopWordsDal::insert(['id' => 1, 'words' => '']);
        }
        $this->view->setVar('words', $row['words'] ?? '');
    }
}