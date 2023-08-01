<?php

return [
    // 是否开启报错写入
    'enabled' => env('DINGDING_ENABLED', false),

    // 钉钉日志只记录request id
    'only_request_id' => env('DINGDING_ONLY_REQUEST_ID', true),
    'request_id_key' => env('DINGDING_ONLY_REQUEST_KEY', 'mn-request-id'),

    // curl证书验证, 线下环境不用开启
    'curl_verify' => env('DINGDING_SSL_VERIFY', true),

    // logging
    'logging' => [
        'channel' => env('DINGDING_LOGGING_CHANNEL', 'dingding'),
        'level' => env('DINGDING_LOGGING_LEVEL', 'error'),
        'webhook' => env('LOG_DINGDING_WEBHOOK_URL', ''),
        'secret' => env('DINGDING_SECRET', ''),
    ],

    'at_mobiles' => array_filter(env('DINGDING_ERROR_MOBILES') ? (explode(',', env('DINGDING_ERROR_MOBILES')) ?: []) : []), //定义艾特的人
];
