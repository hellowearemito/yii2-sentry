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
    public function testComponentIsNotEnabledAndDsnIsNotSetThenTheApplicationDoesNotCrash()
    {
        $this->setSentryComponent([
            'enabled' => false,
            'dsn' => null,
        ]);

        $this->assertNotInstanceOf('\Raven_Client', Yii::$app->sentry->client);
    }

    public function testComponentIsEnabledThenRavenClientExists()
    {
        $this->setSentryComponent();

        $this->assertInstanceOf('\Raven_Client', Yii::$app->sentry->client);
    }

    /**
     * @expectedException \yii\base\InvalidConfigException
     */
    public function testComponentIsEnabledAndDsnIsNotSetThenTheApplicationCrash()
    {
        $this->setSentryComponent([
            'dsn' => null,
        ]);
    }

    public function testConvertPrivateDsnToPublicDsn()
    {
        $this->setSentryComponent();

        $this->assertEquals('https://65b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1', Yii::$app->sentry->getPublicDsn());
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
        $this->setSentryComponent([
            'environment' => $environment
        ]);

        $this->assertEquals($expected, Yii::$app->sentry->environment);
        if (!empty($environment)) {
            $this->assertEquals($expected, Yii::$app->sentry->options['tags']['environment']);
            $this->assertEquals($expected, Yii::$app->sentry->clientOptions['tags']['environment']);
            $this->assertEquals($expected, Yii::$app->sentry->client->tags['environment']);
        }
    }

    public function testIfPublicDsnSetThenJsNotifierIsEnabled()
    {
        $this->setSentryComponent([
            'publicDsn' => 'https://65b4cf757v9kx53ja583f038bb1a07d6@getsentry.com/1',
            'jsNotifier' => false,
        ]);

        $this->assertTrue(Yii::$app->sentry->jsNotifier);
    }

    public function testIfPublicDsnEmptyAndJsNotifierFalse()
    {
        $this->setSentryComponent([
            'publicDsn' => '',
            'jsNotifier' => false,
        ]);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetManager->bundles);
    }

    public function testIfPublicDsnIsNotSetAndJsNotifierIsFalseThenDoNotRegisterAssets()
    {
        $this->setSentryComponent([
            'jsNotifier' => false,
        ]);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetManager->bundles);
    }

    public function testComponentIsNotEnabledThenDoNotRegisterAssets()
    {
        $this->setSentryComponent([
            'enabled' => false,
        ]);

        $this->assertArrayNotHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetManager->bundles);
    }

    public function testComponentIsEnabledThenRegisterAssets()
    {
        $this->setSentryComponent();

        $this->assertArrayHasKey('mito\sentry\assets\RavenAsset', Yii::$app->view->assetManager->bundles);
    }
}