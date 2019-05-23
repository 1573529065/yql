<?php
namespace App\Controller\Admin;

use Phalcon\Mvc\Controller;

class Error extends Controller
{
    function show404()
    {
        exit('404 page not found');
    }

}
