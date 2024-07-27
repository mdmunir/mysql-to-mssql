<?php

$config = [
    'id' => 'basic',
    'name' => 'main',
    'controllerNamespace' => 'app\controllers',
    'bootstrap' => [ext\inertia\Bootstrap::class],
    'components' => [
        'request' => [
            'cookieValidationKey' => env('COOKIE_VALIDATION_KEY'),
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
                'multipart/form-data' => 'yii\web\MultipartFormDataParser'
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            //'enableStrictParsing' => true,
            'rules' => [],
        ],
    ],
];

if (YII_IS_LOCAL) {
    // configuration adjustments for 'dev' environment
     
}

return $config;
