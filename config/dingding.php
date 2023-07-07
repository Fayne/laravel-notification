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
    ],

    // 默认keyword
    'default_keyword' => env('DINGDING_CHANNEL_KEYWORD', 'error'),  // 此处必须要填在钉钉机器人中配置的keyword，并且在dingding.php的keywords数组中配置

    //机器人支持的关键词
    'keywords' => [
        'error' => [//定义关键词
            'value' => env('DINGDING_ERROR_KEYWORD', 'error'),
            'at_mobiles' => array_filter(explode(',', env('DINGDING_ERROR_MOBILES')) ?: []), //定义对应关键词艾特的人
        ],
        'alarm' => [
            'value' => env('DINGDING_ALARM_KEYWORD', 'alarm'),
            'at_mobiles' => array_filter(explode(',', env('DINGDING_ALARM_MOBILES')) ?: []), //定义对应关键词艾特的人
        ],

        // 在钉钉机器人中定义的关键词，需要配置在这里。array key和value 即是关键词并必须一致。
    ],
];
