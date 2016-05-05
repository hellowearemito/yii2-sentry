<?php
// This is global bootstrap for autoloading
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../vendor/yiisoft/yii2/Yii.php');
// autoload Dummy_Raven_Client
require_once dirname(__FILE__) . '/../vendor/sentry/sentry/test/Raven/Tests/ClientTest.php';
Raven_Autoloader::register();

$_SERVER['SCRIPT_FILENAME'] = '/' . dirname(__DIR__) .'/web';
$_SERVER['SCRIPT_NAME'] = __DIR__ . '/web';
Yii::setAlias('@mitosentry', dirname(__DIR__));
