<?php

namespace mito\sentry\tests\unit;

use mito\sentry\SentryTarget;
use mito\sentry\SentryComponent;
use yii\log\Logger;
use yii\helpers\ArrayHelper;
use Yii;
use Mockery;
use yii\web\HttpException;

class SentryTargetTest extends \yii\codeception\TestCase
{
    const EXCEPTION_TYPE_OBJECT = 'object';
    const EXCEPTION_TYPE_MSG = 'message';
    const EXCEPTION_TYPE_STRING = 'string';

    const DEFAULT_ERROR_MESSAGE = 'message';

    public $appConfig = '@mitosentry/tests/unit/config/main.php';

    private $_exceptionTypes = [
        self::EXCEPTION_TYPE_OBJECT,
        self::EXCEPTION_TYPE_MSG,
        self::EXCEPTION_TYPE_STRING,
    ];

    protected function mockSentryTarget($options = [])
    {
        $component = Mockery::mock(SentryComponent::className());

        return Yii::createObject(ArrayHelper::merge([
            'class' => SentryTarget::className(),
            'sentry' => $component,
        ], $options));
    }

    protected function mockLogger($target)
    {
        $dispatcher = new TestDispatcher([
            'targets' => [$target],
        ]);
        $logger = new Logger([
            'dispatcher' => $dispatcher,
        ]);

        return $logger;
    }

    public function testDontCrashIfComponentIsDisabled()
    {
        // Attempting to call any method on sentryComponent will throw an exception.
        // If the component is disabled, SentryTarget should not call any methods on
        // SentryComponent.
        $component = Mockery::mock(SentryComponent::className());
        $component->enabled = false;
        $target = $this->mockSentryTarget([
            'sentry' => $component,
        ]);
        $logger = $this->mockLogger($target);
        $logger->log(str_repeat('x', 1024), Logger::LEVEL_ERROR);
        $logger->flush(true);
    }

    /**
     * @dataProvider filters
     *
     * @param $filter
     * @param $expected
     */
    public function testFilter($filter, $expected)
    {
        $target = $this->mockSentryTarget($filter);
        $logger = $this->mockLogger($target);
        foreach ($expected as $message) {
            $target->sentry->shouldReceive('capture')
                ->with(Mockery::on(function($data) use ($message) {
                    return $data['message'] === $message;
                }), Mockery::on(function($traces) {
                    return true;
                }))->once();
        }

        $logger->log('A', Logger::LEVEL_INFO);
        $logger->log('B', Logger::LEVEL_ERROR);
        $logger->log('C', Logger::LEVEL_WARNING);
        $logger->log('D', Logger::LEVEL_TRACE);
        $logger->log('E', Logger::LEVEL_INFO, 'application');
        $logger->log('F', Logger::LEVEL_INFO, 'application.components.Test');
        $logger->log('G', Logger::LEVEL_ERROR, 'yii.db.Command');
        $logger->log('H', Logger::LEVEL_ERROR, 'yii.db.Command.whatever');

        $logger->flush(true);
    }

    /**
     * @dataProvider exceptions
     *
     * @param $except
     * @param $exceptionClass
     * @param $exceptionCode
     * @param $expectLogged
     * @param $type
     */
    public function testExceptions($except, $exceptionClass, $exceptionCode, $expectLogged, $type)
    {
        $target = $this->mockSentryTarget($except);
        $logger = $this->mockLogger($target);

        if ($expectLogged) {
            if ($type === self::EXCEPTION_TYPE_OBJECT) {
                $target->sentry->shouldReceive('captureException')
                    ->with(Mockery::on(function($exception) {
                        return ($exception instanceof \Throwable || $exception instanceof \Exception) && $exception->getMessage() === self::DEFAULT_ERROR_MESSAGE;
                    }), Mockery::on(function($data) {
                        return true;
                    }))->once();
            } else {
                $target->sentry->shouldReceive('capture')
                    ->with(Mockery::on(function($data) {
                        return $data['message'] === self::DEFAULT_ERROR_MESSAGE;
                    }), Mockery::on(function($traces) {
                        return true;
                    }))->once();
            }
        } else {
            $target->sentry->shouldNotReceive('capture');
            $target->sentry->shouldNotReceive('captureException');
            $target->sentry->shouldNotReceive('captureMessage');
        }
        $target->sentry->shouldReceive('capture')
            ->with(Mockery::on(function($data) {
                return $data['message'] === 'sentinel';
            }), Mockery::on(function($traces) {
                return true;
            }))->once();

        switch ($type) {
            case self::EXCEPTION_TYPE_OBJECT:
                $exception = new $exceptionClass(self::DEFAULT_ERROR_MESSAGE, $exceptionCode);
                break;
            case self::EXCEPTION_TYPE_MSG:
                $exception = ['msg' => self::DEFAULT_ERROR_MESSAGE];
                break;
            case self::EXCEPTION_TYPE_STRING:
                $exception = self::DEFAULT_ERROR_MESSAGE;
                break;
            default:
                $exception = false;
        }

        if ($exception !== false) {
            $logger->log($exception, Logger::LEVEL_ERROR, $exceptionClass . ":" . $exceptionCode);
        }

        $logger->log('sentinel', Logger::LEVEL_INFO);

        $logger->flush(true);
    }

    /**
     * @return array
     */
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
            // console
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
     * @return array
     */
    public function exceptions()
    {
        $results = [];
        foreach ($this->_exceptionTypes as $exceptionType) {
            $results = array_merge($results, [
                'skip code 404 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], HttpException::class, 404, false, $exceptionType],
                'skip http * - ' . $exceptionType => [['except' => ['yii\web\HttpException:*']], HttpException::class, 403, false, $exceptionType],
                'catch code 0 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 0, true, $exceptionType],
                'catch code 400 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 400, true, $exceptionType],
                'catch code 401 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 401, true, $exceptionType],
                'catch code 402 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 402, true, $exceptionType],
                'catch code 403 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 403, true, $exceptionType],
                'catch code 500 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 500, true, $exceptionType],
                'catch code 503 - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Exception::class, 503, true, $exceptionType],
            ]);

            // php7+
            if (class_exists('\Error')) {
                $results = array_merge($results, [
                    'skip \Error - ' . $exceptionType => [['except' => ['Error:*']], \Error::class, 0, false, $exceptionType],
                    'catch \Error - ' . $exceptionType => [['except' => ['yii\web\HttpException:404']], \Error::class, 0, true, $exceptionType],
                ]);
            }
        }

        return $results;
    }
}
