<?php

namespace mito\sentry\tests\unit;

use mito\sentry\Component;
use yii\helpers\ArrayHelper;
use Yii;
use Mockery;

class ComponentTest extends \yii\codeception\TestCase
{
    const PRIVATE_DSN = 'https://65b4cf757v9kx53ja583f038bb1a07d6:cda7d637fb7kd85nch39c4445cf47126@getsentry.com/1';
    const PUBLIC_DSN = 'https://65b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1';

    public $appConfig = '@mitosentry/tests/unit/config/main.php';

    const CLIENT_CONFIG_TYPE_ARRAY = 'array';
    const CLIENT_CONFIG_TYPE_OBJECT = 'object';
    const CLIENT_CONFIG_TYPE_CALLABLE = 'callable';

    const ENV_DEVELOPMENT = 'development';
    const ENV_STAGING = 'staging';
    const ENV_PRODUCTION = 'production';

    protected function tearDown()
    {
        parent::tearDown();
        Mockery::close();
    }

    private function mockSentryComponent($options = [])
    {
        return Yii::createObject(ArrayHelper::merge([
            'class' => Component::className(),
            'enabled' => true,
            'dsn' => self::PRIVATE_DSN,
        ], $options));
    }

    public function testInvalidConfigExceptionIfDsnIsNotSet()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->mockSentryComponent([
            'dsn' => null,
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);
    }

    public function testInvalidConfigExceptionIfDsnIsInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mockSentryComponent([
            'dsn' => 'https://getsentry.io/50',
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);
    }

    public function testConvertPrivateDsnToPublicDsn()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);

        $this->assertEquals(self::PUBLIC_DSN, $component->publicDsn);
    }

    public function environments()
    {
        return [
            'empty' => [null, null, self::CLIENT_CONFIG_TYPE_ARRAY, 'mito\sentry\tests\unit\DummyRavenClient'],
            'development' => [self::ENV_DEVELOPMENT, self::ENV_DEVELOPMENT, self::CLIENT_CONFIG_TYPE_ARRAY, 'mito\sentry\tests\unit\DummyRavenClient'],
            'staging' => [self::ENV_STAGING, self::ENV_STAGING, self::CLIENT_CONFIG_TYPE_ARRAY, 'mito\sentry\tests\unit\DummyRavenClient'],
            'production' => [self::ENV_PRODUCTION, self::ENV_PRODUCTION, self::CLIENT_CONFIG_TYPE_ARRAY, 'mito\sentry\tests\unit\DummyRavenClient'],
            'client config is Dummy object' => [self::ENV_DEVELOPMENT, self::ENV_DEVELOPMENT, self::CLIENT_CONFIG_TYPE_OBJECT, DummyRavenClient::class],
            'client config is object' => [self::ENV_DEVELOPMENT, self::ENV_DEVELOPMENT, self::CLIENT_CONFIG_TYPE_OBJECT, \Raven_Client::class],
            'client config is callable' => [self::ENV_DEVELOPMENT, self::ENV_DEVELOPMENT, self::CLIENT_CONFIG_TYPE_CALLABLE, function () {
                return new \Raven_Client(self::PRIVATE_DSN, [
                    'tags' => ['test' => 'value'],
                ]);
            }],
        ];
    }

    /**
     * @dataProvider environments
     */
    public function testSetEnvironment($environment, $expected, $configType, $clientClass)
    {
        switch ($configType) {
            case self::CLIENT_CONFIG_TYPE_ARRAY:
                $clientConfig = ['class' => $clientClass];
                break;
            case self::CLIENT_CONFIG_TYPE_OBJECT:
                $clientConfig = new $clientClass(self::PRIVATE_DSN, []);
                break;
            case self::CLIENT_CONFIG_TYPE_CALLABLE:
                $clientConfig = $clientClass;
                $clientClass = get_class($clientClass());
                break;
        }

        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => $environment,
            'client' => $clientConfig,
        ]);
        $this->assertEquals($expected, $component->environment);
        $this->assertInstanceOf($clientClass, $component->client);
        if (!empty($environment)) {
            $this->assertArrayHasKey('environment', $component->jsOptions);
            $this->assertObjectHasAttribute('environment', $component->client);
            $this->assertEquals($expected, $component->client->environment);
            $this->assertEquals($expected, $component->jsOptions['environment']);
        }
    }

    public function clientConfigs()
    {
        return [
            'array' => [self::CLIENT_CONFIG_TYPE_ARRAY, 'mito\sentry\tests\unit\DummyRavenClient'],
            'object' => [self::CLIENT_CONFIG_TYPE_OBJECT, DummyRavenClient::class],
            'callable' => [self::CLIENT_CONFIG_TYPE_CALLABLE, function () {
                return new DummyRavenClient(self::PRIVATE_DSN, [
                    'tags' => ['test' => 'value'],
                ]);
            }],
        ];
    }

    /**
     * @dataProvider clientConfigs
     */
    public function testClientConfig($configType, $clientClass)
    {
        switch ($configType) {
            case self::CLIENT_CONFIG_TYPE_ARRAY:
                $clientConfig = [
                    'class' => $clientClass,
                    'tags' => [
                        'test' => 'value',
                    ],
                ];
                break;
            case self::CLIENT_CONFIG_TYPE_OBJECT:
                $clientConfig = new $clientClass(self::PRIVATE_DSN, ['tags' => ['test' => 'value']]);
                break;
            default:
                $clientConfig = $clientClass;
                break;
        }
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => self::ENV_DEVELOPMENT,
            'client' => $clientConfig,
        ]);
        $this->assertInstanceOf('mito\sentry\tests\unit\DummyRavenClient', $component->client);
        $this->assertEquals(self::PRIVATE_DSN, $component->client->dsn);
        $this->assertArrayHasKey('test', $component->client->tags);
        $this->assertEquals('value', $component->client->tags['test']);
        $this->assertObjectHasAttribute('environment', $component->client);
        $this->assertEquals(self::ENV_DEVELOPMENT, $component->client->environment);
    }

    public function invalidConfigs()
    {
        return [
            'class name - missing dsn' => ['mito\sentry\tests\unit\DummyRavenClient', \yii\base\InvalidConfigException::class],
            'string or class name - missing class' => ['RavenClient', \ReflectionException::class],
            'callable - invalid return value' => [function () {
                return 'string';
            }, \yii\base\InvalidConfigException::class],
        ];
    }

    /**
     * @dataProvider invalidConfigs
     *
     * @param $config
     * @param $exceptionClass
     */
    public function testInvalidClientConfig($config, $exceptionClass)
    {
        $this->expectException($exceptionClass);
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'client' => $config,
        ]);
    }

    public function testClientConfigDefaultClass()
    {
        $component = $this->mockSentryComponent([
            'client' => [
                'curl_method' => 'async',
            ],
        ]);
        $this->assertInstanceOf(\Raven_Client::class, $component->client);
        $this->assertEquals('async', $component->client->curl_method);
    }

    public function testCapture()
    {
        $raven = Mockery::mock('\Raven_Client');
        $raven->shouldReceive('close_curl_resource')->atMost()->once();

        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => self::ENV_DEVELOPMENT,
            'client' => $raven,
        ]);

        $message = 'message';
        $params = ['foo' => 'bar'];
        $level = 'info';
        $stack = ['stack1', 'stack2'];
        $vars = ['var1' => 'value 1'];

        $data = [
            'message' => $message,
        ];
        $logger = 'test';
        $exception = new \Exception('exception message');

        $raven->shouldReceive('captureMessage')->with($message, $params, $level, $stack, $vars)->atLeast()->once();
        $raven->shouldReceive('captureException')->with($exception, $data, $logger, $vars)->atLeast()->once();
        $raven->shouldReceive('capture')->with($data, $stack, $vars)->atLeast()->once();

        $component->captureMessage($message, $params, $level, $stack, $vars);
        $component->captureException($exception, $data, $logger, $vars);
        $component->capture($data, $stack, $vars);
    }

    private function assertAssetRegistered($asset)
    {
        if (Yii::$app->view instanceof \yii\web\View) {
            $this->assertArrayHasKey($asset, Yii::$app->view->assetBundles);
        }
    }

    private function assertAssetNotRegistered($asset)
    {
        if (Yii::$app->view instanceof \yii\web\View) {
            $this->assertArrayNotHasKey($asset, Yii::$app->view->assetBundles);
        }
    }

    public function testJsNotifierIsNotEnabledIfPublicDsnSet()
    {
        $publicDsn = 'https://45b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1';
        $component = $this->mockSentryComponent([
            'publicDsn' => $publicDsn,
            'jsNotifier' => false,
            'environment' => self::ENV_DEVELOPMENT,
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);
        $this->assertFalse($component->jsNotifier);
        $this->assertEquals($publicDsn, $component->publicDsn);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAssetNotRegisteredIfJsNotifierIsFalseAndPublicDsnIsEmpty()
    {
        $component = $this->mockSentryComponent([
            'publicDsn' => '',
            'jsNotifier' => false,
            'environment' => self::ENV_DEVELOPMENT,
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAssetNotRegisteredIfJsNotifierIsFalse()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => false,
            'environment' => self::ENV_DEVELOPMENT,
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAutogeneratedPublicDsn()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => self::ENV_DEVELOPMENT,
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
            ],
        ]);
        $this->assertTrue($component->jsNotifier);
        $this->assertEquals(self::PUBLIC_DSN, $component->publicDsn);
        $this->assertAssetRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAssetNotRegisteredIfComponentIsNotEnabled()
    {
        $component = $this->mockSentryComponent([
            'enabled' => false,
            'jsNotifier' => true,
        ]);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testDoNotRegisterAssetsIfApplicationIsConsoleApplication()
    {
        $this->destroyApplication();
        $this->mockApplication([
            'id' => 'testapp-console',
            'class' => '\yii\console\Application',
            'basePath' => '@mitosentry/tests/unit/runtime/web',
            'vendorPath' => '@mitosentry/vendor',
        ]);
        $component = $this->mockSentryComponent([
            'enabled' => true,
            'jsNotifier' => true,
        ]);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }
}
