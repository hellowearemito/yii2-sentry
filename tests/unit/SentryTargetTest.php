<?php
/**
 * Created by PhpStorm.
 * User: aborsos
 * Date: 16. 05. 03.
 * Time: 13:57
 */

namespace mito\sentry\tests\unit;


use mito\sentry\SentryTarget;
use yii\log\Dispatcher;
use yii\log\Logger;

class SentryTargetTest extends TestCase
{

    const EXCEPTION_TYPE_OBJECT = 'object';
    const EXCEPTION_TYPE_MSG = 'message';
    const EXCEPTION_TYPE_STRING = 'string';

    protected function getLogger()
    {
        $logger = new Logger();
        $dispatcher = new Dispatcher([
            'logger' => $logger,
            'targets' => [
                'sentry' => [
                    'class' => '\mito\sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    'except' => [
                        'yii\web\HttpException:404',
                    ],
                ],
            ],
        ]);

        return $logger;
    }

    /** smoke test */
    public function testTargetExists()
    {
        $this->setSentryTarget();
    }

    public function testComponentIsDisabledAndTargetIsSetThenTheApplicationDoesNotCrashIfErrorOccurs()
    {
        $this->setSentryComponent(['enabled' => false]);

        $logger = $this->getLogger();
        $logger->log(str_repeat('x', 1024), Logger::LEVEL_ERROR);
        $logger->flush(true);
    }

    public function testRavenClientExists()
    {
        $this->setSentryTarget();

        $this->assertInstanceOf('\Raven_Client', \Yii::$app->sentry->client);
    }

    public function filters()
    {
        return [
            // filters from: https://github.com/yiisoft/yii2/blob/master/tests/framework/log/TargetTest.php
            [[], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']],
            [['levels' => 0], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']],
            [
                ['levels' => Logger::LEVEL_INFO | Logger::LEVEL_WARNING | Logger::LEVEL_ERROR | Logger::LEVEL_TRACE],
                ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']
            ],
            [['levels' => ['error']], ['B', 'G', 'H']],
            [['levels' => Logger::LEVEL_ERROR], ['B', 'G', 'H']],
            [['levels' => ['error', 'warning']], ['B', 'C', 'G', 'H']],
            [['levels' => Logger::LEVEL_ERROR | Logger::LEVEL_WARNING], ['B', 'C', 'G', 'H']],
            [['categories' => ['application']], ['A', 'B', 'C', 'D', 'E']],
            [['categories' => ['application*']], ['A', 'B', 'C', 'D', 'E', 'F']],
            [['categories' => ['application.*']], ['F']],
            [['categories' => ['application.components']], []],
            [['categories' => ['application.components.Test']], ['F']],
            [['categories' => ['application.components.*']], ['F']],
            [['categories' => ['application.*', 'yii.db.*']], ['F', 'G', 'H']],
            [['categories' => ['application.*', 'yii.db.*'], 'except' => ['yii.db.Command.*']], ['F', 'G']],
            [['categories' => ['application', 'yii.db.*'], 'levels' => Logger::LEVEL_ERROR], ['B', 'G', 'H']],
            [['categories' => ['application'], 'levels' => Logger::LEVEL_ERROR], ['B']],
            [['categories' => ['application'], 'levels' => Logger::LEVEL_ERROR | Logger::LEVEL_WARNING], ['B', 'C']],
        ];
    }

    /**
     * @dataProvider filters
     */
    public function testFilter($filter, $expected)
    {
        $this->setSentryTarget();

        $logger = new Logger();
        $dispatcher = new Dispatcher([
            'logger' => $logger,
            'targets' => [
                'sentry' => new SentryTarget(array_merge($filter, ['logVars' => []])),
            ],
        ]);

        $logger->log('testA', Logger::LEVEL_INFO);
        $logger->log('testB', Logger::LEVEL_ERROR);
        $logger->log('testC', Logger::LEVEL_WARNING);
        $logger->log('testD', Logger::LEVEL_TRACE);
        $logger->log('testE', Logger::LEVEL_INFO, 'application');
        $logger->log('testF', Logger::LEVEL_INFO, 'application.components.Test');
        $logger->log('testG', Logger::LEVEL_ERROR, 'yii.db.Command');
        $logger->log('testH', Logger::LEVEL_ERROR, 'yii.db.Command.whatever');

        $logger->flush(true);

        /** @var \mito\sentry\SentryTarget $sentryTarget */
        $sentryTarget = $logger->dispatcher->targets['sentry'];
        $messages = $sentryTarget->getCapturedMessages();

        $this->assertEquals(count($expected), count($messages));
    }

    public function exceptions()
    {
        return [
            'skip code 404' => [['except' => ['yii\web\HttpException:404']], 404, 0, self::EXCEPTION_TYPE_OBJECT],
            'skip http *' => [['except' => ['yii\web\HttpException:*']], 403, 0, self::EXCEPTION_TYPE_MSG],
            'catch code 0' => [['except' => ['yii\web\HttpException:404']], 0, 1, self::EXCEPTION_TYPE_STRING],
            'catch code 400' => [['except' => ['yii\web\HttpException:404']], 400, 1, self::EXCEPTION_TYPE_OBJECT],
            'catch code 401' => [['except' => ['yii\web\HttpException:404']], 401, 1, self::EXCEPTION_TYPE_MSG],
            'catch code 402' => [['except' => ['yii\web\HttpException:404']], 402, 1, self::EXCEPTION_TYPE_STRING],
            'catch code 403' => [['except' => ['yii\web\HttpException:404']], 403, 1, self::EXCEPTION_TYPE_OBJECT],
            'catch code 500' => [['except' => ['yii\web\HttpException:404']], 500, 1, self::EXCEPTION_TYPE_MSG],
            'catch code 503' => [['except' => ['yii\web\HttpException:404']], 503, 1, self::EXCEPTION_TYPE_STRING],
        ];
    }

    /**
     * @dataProvider exceptions
     */
    public function testExceptions($except, $exceptionCode, $expectedNumberOfLogs, $type)
    {
        $this->setSentryTarget();

        $logger = new Logger();
        $dispatcher = new Dispatcher([
            'logger' => $logger,
            'targets' => [
                'sentry' => new SentryTarget(array_merge($except, ['logVars' => []])),
            ],
        ]);

        try {
            throw new \Exception('message', $exceptionCode);
        } catch (\Exception $e) {

            $exception = $e;
            switch($type){
                case self::EXCEPTION_TYPE_MSG:
                    $exception = ['msg' => $e->getMessage()];
                    break;
                case self::EXCEPTION_TYPE_STRING:
                    $exception = $e->getMessage();
                    break;
            }

            $logger->log($exception, Logger::LEVEL_ERROR, 'yii\web\HttpException:' . $exceptionCode);
        }

        $logger->flush(true);

        /** @var \mito\sentry\SentryTarget $sentryTarget */
        $sentryTarget = $logger->dispatcher->targets['sentry'];
        $messages = $sentryTarget->getCapturedMessages();

        $this->assertEquals($expectedNumberOfLogs, count($messages));
    }
}
