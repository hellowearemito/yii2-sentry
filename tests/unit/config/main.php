<?php
return [
    'id' => 'testapp',
    'basePath' => __DIR__ . '/../runtime/web/',
    'vendorPath' => __DIR__ . '/../../../vendor',
    'components' => [
        'assetManager' => [
            'basePath' => '@mitosentry/tests/unit/runtime/web/assets',
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
];
