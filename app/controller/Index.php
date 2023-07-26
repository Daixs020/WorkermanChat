<?php
namespace app\controller;

use app\BaseController;

use think\facade\View;
class Index extends BaseController
{
    public function index()
    {
        // 模板输出
        return View::fetch('index');
    }

    public function hello($name = 'ThinkPHP6')
    {
        return 'hello,' . $name;
    }
}
