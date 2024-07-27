<?php
$params = require __DIR__ . '/params.php';

$config = [
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'runtimePath' => dirname(dirname(__DIR__)) . '/runtime',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@root' => ROOT_APP_PATH,
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\DummyCache',
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'dbSource' => [
            'class' => 'yii\db\Connection',
            'dsn' => env('SOURCE_DSN'),
            'username' => env('SOURCE_USERNAME'),
            'password' => env('SOURCE_PASSWORD'),
            'charset' => 'utf8',
        ],
        'dbDest' => [
            'class' => 'yii\db\Connection',
            'dsn' => env('DEST_DSN'),
            'username' => env('DEST_USERNAME'),
            'password' => env('DEST_PASSWORD'),
            'charset' => 'utf8',
        ],
        'mutex' => [
            'class' => \yii\mutex\FileMutex::class,
        ],
    ],
    'params' => $params,
];

if (YII_IS_LOCAL) {
    // configuration adjustments for 'dev' environment
    
}

return $config;
