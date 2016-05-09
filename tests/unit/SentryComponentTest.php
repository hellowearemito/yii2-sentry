<?php

namespace mito\sentry\tests\unit;


use Mockery;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\AssetManager;
use yii\web\View;

class SentryComponentTest extends TestCase
{
    /**
     * @dataProvider applications
     */
    public function testDontCrashIfNotEnabledAndNullDSN($application)
    {
        $this->setSentryComponent([
            'enabled' => false,
            'dsn' => null,
        ], $application);

        $this->assertNotInstanceOf('\Raven_Client', Yii::$app->sentry->client);
    }

    /**
     * @dataProvider applications
     */
    public function testRavenClientExistsWhenComponentIsEnabled($application)
    {
        $this->setSentryComponent([], $application);

        $this->assertInstanceOf('\Raven_Client', Yii::$app->sentry->client);
    }

    /**
     * @dataProvider applications
     * @expectedException \yii\base\InvalidConfigException
     */
    public function testInvalidConfigExceptionIfDsnIsNotSet($application)
    {
        $this->setSentryComponent([
            'dsn' => null,
        ], $application);
    }

    /**
     * @dataProvider applications
     */

    public function testConvertPrivateDsnToPublicDsn($application)
    {
        $this->setSentryComponent([], $application);

        $this->assertEquals('https://65b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1', Yii::$app->sentry->getPublicDsn());
    }

    public function environments()
    {
        return [
            'empty' => [null, null],
            'development' => ['development', 'development'],
            'staging' => ['staging', 'staging'],
            'production' => ['production', 'production'],
            'empty @console' => [null, null, self::APP_CONSOLE],
            'development @console' => ['development', 'development', self::APP_CONSOLE],
            'staging @console' => ['staging', 'staging', self::APP_CONSOLE],
            'production @console' => ['production', 'production', self::APP_CONSOLE],
        ];
    }

    /**
     * @dataProvider environments
     */
    public function testSetEnvironment($environment, $expected, $application = self::APP_WEB)
    {
        $this->setSentryComponent([
            'environment' => $environment
        ], $application);

        $this->assertEquals($expected, Yii::$app->sentry->environment);
        if (!empty($environment)) {
            $this->assertEquals($expected, Yii::$app->sentry->options['tags']['environment']);
            $this->assertEquals($expected, Yii::$app->sentry->clientOptions['tags']['environment']);
            $this->assertEquals($expected, Yii::$app->sentry->client->tags['environment']);
        }
    }

    /**
     * @dataProvider applications
     */
    public function testJsNotifierEnabledIfPublicDsnSet($application)
    {
        $this->setSentryComponent([
            'publicDsn' => 'https://65b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1',
            'jsNotifier' => false,
        ], $application);

        $this->assertTrue(Yii::$app->sentry->jsNotifier);
    }

    /**
     * @dataProvider applications
     */
    public function testAssetNotRegisteredIfJsNotifierIsFalseAndPublicDsnIsEmpty($application)
    {
        $this->setSentryComponent([
            'publicDsn' => '',
            'jsNotifier' => false,
        ], $application);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetBundles);
    }

    /**
     * @dataProvider applications
     */
    public function testAssetNotRegisteredIfJsNotifierIsFalse($application)
    {
        $this->setSentryComponent([
            'jsNotifier' => false,
        ], $application);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetBundles);
    }

    /**
     * @dataProvider applications
     */
    public function testAssetNotRegisteredIfComponentIsNotEnabled($application)
    {
        $this->setSentryComponent([
            'enabled' => false,
        ], $application);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetBundles);
    }

    public function testRegisterAssetIfComponentIsEnabled()
    {
        $this->setSentryComponent();

        $this->assertArrayHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetBundles);
    }

    public function testDoNotRegisterAssetsIfApplicationIsConsoleApplication()
    {
        $this->setSentryComponent([], self::APP_CONSOLE);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetBundles);
    }
}
