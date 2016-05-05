<?php

namespace mito\sentry\tests\unit;

use Codeception\Configuration;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Yii;
use yii\base\InvalidConfigException;
use yii\di\Container;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\AssetManager;
use yii\web\View;

/**
 * This is the base class for all yii framework unit tests.
 */
abstract class TestCase extends \yii\codeception\TestCase
{
    const APP_CONSOLE = '\yii\console\Application';
    const APP_WEB = '\yii\web\Application';

    public $appConfig = '@mitosentry/tests/unit/config/main.php';

    protected $sentryComponentConfig = [
        'class' => '\mito\sentry\SentryComponent',
        'ravenClass' => '\Dummy_Raven_Client',
        'enabled' => true,
        'dsn' => 'https://65b4cf757v9kx53ja583f038bb1a07d6:cda7d637fb7kd85nch39c4445cf47126@getsentry.com/1',
        'jsNotifier' => true,
    ];

    protected $sentryTargetConfig = [
        'class' => '\mito\sentry\SentryTarget',
        'levels' => ['error', 'warning'],
        'except' => [
            'yii\web\HttpException:404',
        ],
    ];

    /**
     * application data provider
     * @return array
     */
    public function applications()
    {
        return [
            [self::APP_WEB],
            [self::APP_CONSOLE],
        ];
    }

    protected function setUp()
    {
        $actor = $this->actor;
        if ($actor) {
            $property = lcfirst(Configuration::config()['actor']);
            $this->$property = new $actor($this->scenario);

            // BC compatibility hook
            $actorProperty = lcfirst($actor);
            $this->$actorProperty = $this->$property;
        }
        $this->getScenario()->run();
        $this->fire(Events::TEST_BEFORE, new TestEvent($this));
        $this->_before();

        $this->unloadFixtures();
        $this->loadFixtures();

        \Yii::setAlias('@webroot', __DIR__ . '/runtime/web');
        \Yii::setAlias('@web', '/runtime/web');
    }

    protected function tearDown()
    {
        FileHelper::removeDirectory(Yii::getAlias('@mitosentry/tests/unit/runtime/web/assets'));
        parent::tearDown();
    }


    protected function mockApplication($config = null)
    {
        if (isset(Yii::$app)) {
            return;
        }
        Yii::$container = new Container();

        $configFile = Yii::getAlias($this->appConfig);
        if (!is_file($configFile)) {
            throw new InvalidConfigException("The application configuration file does not exist: $config");
        }

        if (is_array($config)) {
            $configMain = require($configFile);
            $config = ArrayHelper::merge($configMain, $config);
        } else {
            $config = require($configFile);
        }

        if (!isset($config['class'])) {
            $config['class'] = self::APP_WEB;
        }

        return Yii::createObject($config);
    }

    protected function setSentryComponent($config = [], $applicationClass = self::APP_WEB)
    {
        $config = ArrayHelper::merge($this->sentryComponentConfig, $config);

        $this->mockApplication([
            'class' => $applicationClass,
            'bootstrap' => ['sentry'],
            'components' => [
                'sentry' => $config,
            ],
        ]);

        if (ArrayHelper::getValue($config, 'jsNotifier', false)) {
            $this->mockView();
        }
    }

    protected function setSentryTarget($configTarget = [], $configComponent = [], $applicationClass = self::APP_WEB)
    {
        $configComponent = ArrayHelper::merge($this->sentryComponentConfig, $configComponent);
        $configTarget = ArrayHelper::merge($this->sentryTargetConfig, $configTarget);

        $this->mockApplication([
            'class' => $applicationClass,
            'bootstrap' => ['sentry'],
            'components' => [
                'sentry' => $configComponent,
                'log' => [
                    'targets' => [
                        $configTarget,
                    ],
                ],
            ],
        ]);

        if (ArrayHelper::getValue($configComponent, 'jsNotifier', false)) {
            $this->mockView();
        }
    }

    protected function mockView()
    {
        return new View([
            'assetManager' => $this->mockAssetManager(),
        ]);
    }

    protected function mockAssetManager()
    {
        $assetDir = Yii::getAlias('@mitosentry/tests/unit/runtime/web/assets');
        if (!is_dir($assetDir)) {
            mkdir($assetDir, 0777, true);
        }

        return new AssetManager([
            'basePath' => $assetDir,
            'baseUrl' => '/tests/unit/runtime/web',
        ]);
    }

    protected function debug($data)
    {
        return fwrite(STDERR, print_r($data, true));
    }

}