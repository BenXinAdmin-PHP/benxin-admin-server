<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   容器 Provider — 绑定统一异常处理器
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-07 21:00:00
// +----------------------------------------------------------------------

use app\common\exception\Handle;
use app\Request;

// 容器Provider定义文件
return [
    'think\Request'          => Request::class,
    'think\exception\Handle' => Handle::class,
];
