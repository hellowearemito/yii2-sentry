<?php

namespace mito\sentry\tests\unit;

use mito\sentry\SentryComponent;
use yii\helpers\ArrayHelper;
use Yii;
use Mockery;

class SentryComponentTest extends \yii\codeception\TestCase
{
    const PRIVATE_DSN = 'https://65b4cf757v9kx53ja583f038bb1a07d6:cda7d637fb7kd85nch39c4445cf47126@getsentry.com/1';
    const PUBLIC_DSN = 'https://65b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1';

    public $appConfig = '@mitosentry/tests/unit/config/main.php';

    private function mockSentryComponent($options = [])
    {
        return Yii::createObject(ArrayHelper::merge([
            'class' => SentryComponent::className(),
            'enabled' => true,
            'dsn' => self::PRIVATE_DSN,
        ], $options));
    }

    public function testDontCrashIfNotEnabledAndNullDSN()
    {
        $this->mockSentryComponent([
            'enabled' => false,
            'dsn' => null,
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);
    }

    public function testInvalidConfigExceptionIfDsnIsNotSet()
    {
        $this->expectException(\yii\base\InvalidConfigException::class);
        $this->mockSentryComponent([
            'dsn' => null,
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);
    }

    public function testConvertPrivateDsnToPublicDsn()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);

        $this->assertEquals(self::PUBLIC_DSN, $component->publicDsn);
    }

    public function environments()
    {
        return [
            'empty' => [null, null],
            'development' => ['development', 'development'],
            'staging' => ['staging', 'staging'],
            'production' => ['production', 'production'],
        ];
    }

    /**
     * @dataProvider environments
     */
    public function testSetEnvironment($environment, $expected)
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => $environment,
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);
        $this->assertEquals($expected, $component->environment);
        $this->assertInstanceOf('mito\sentry\tests\unit\DummyRavenClient', $component->client);
        if (!empty($environment)) {
            $this->assertArrayHasKey('tags', $component->options);
            $this->assertArrayHasKey('tags', $component->jsOptions);
            $this->assertArrayHasKey('environment', $component->client->tags);
            $this->assertEquals($expected, $component->options['tags']['environment']);
            $this->assertEquals($expected, $component->jsOptions['tags']['environment']);
            $this->assertEquals($expected, $component->client->tags['environment']);
        }
    }

    public function testClientConfig()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => 'development',
            'client' => [
                'class' => 'mito\sentry\tests\unit\DummyRavenClient',
                'tags' => [
                    'test' => 'value',
                ],
            ],
        ]);
        $this->assertInstanceOf('mito\sentry\tests\unit\DummyRavenClient', $component->client);
        $this->assertEquals(self::PRIVATE_DSN, $component->client->dsn);
        $this->assertArrayHasKey('test', $component->client->tags);
        $this->assertEquals('value', $component->client->tags['test']);
        $this->assertArrayHasKey('environment', $component->client->tags);
        $this->assertEquals('development', $component->client->tags['environment']);
        $this->assertEquals($component->client, $component->getClient());
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
        $this->assertEquals($component->client, $component->getClient());
    }

    public function testCapture()
    {
        $raven = Mockery::mock('\Raven_Client');

        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => 'development',
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
        private function assertAssetNotRegistered($asset) {
            if (Yii::$app->view instanceof \yii\web\View) {
                $this->assertArrayNotHasKey($asset, Yii::$app->view->assetBundles);
            }
        }

    public function testJsNotifierEnabledIfPublicDsnSet()
    {
        $publicDsn = 'https://45b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1';
        $component = $this->mockSentryComponent([
            'publicDsn' => $publicDsn,
            'jsNotifier' => false,
            'environment' => 'development',
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);
        $this->assertTrue($component->jsNotifier);
        $this->assertEquals($publicDsn, $component->publicDsn);
        $this->assertEquals($component->publicDsn, $component->getPublicDsn());
        $this->assertAssetRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAssetNotRegisteredIfJsNotifierIsFalseAndPublicDsnIsEmpty()
    {
        $component = $this->mockSentryComponent([
            'publicDsn' => '',
            'jsNotifier' => false,
            'environment' => 'development',
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAssetNotRegisteredIfJsNotifierIsFalse()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => false,
            'environment' => 'development',
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
        ]);
        $this->assertAssetNotRegistered('mito\sentry\assets\RavenAsset');
    }

    public function testAutogeneratedPublicDsn()
    {
        $component = $this->mockSentryComponent([
            'jsNotifier' => true,
            'environment' => 'development',
            'ravenClass' => 'mito\sentry\tests\unit\DummyRavenClient',
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
