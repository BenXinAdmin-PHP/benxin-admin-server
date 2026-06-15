<?php
// 事件定义文件
use app\common\event\PaySuccessEvent;
use app\common\event\RefundSuccessEvent;
use app\common\listener\PayBizExampleListener;

return [
    'bind'      => [
    ],

    'listen'    => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],

        // 支付/退款业务解耦事件（M4-C，开源边界 §1）。
        // 底座仅注册「空示例 listener」演示链路；上层闭源业务应替换为自己的 listener，
        // 用 biz_type 路由、biz_id 定位单据（见 PayBizExampleListener 头注释）。
        PaySuccessEvent::class    => [PayBizExampleListener::class],
        RefundSuccessEvent::class => [PayBizExampleListener::class],
    ],

    'subscribe' => [
    ],
];
