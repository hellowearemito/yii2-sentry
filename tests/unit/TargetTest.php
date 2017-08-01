<?php

namespace mito\sentry\tests\unit;

use mito\sentry\Target;
use mito\sentry\Component;
use yii\log\Logger;
use yii\helpers\ArrayHelper;
use Yii;
use Mockery;
use yii\web\HttpException;

class TargetTest extends \yii\codeception\TestCase
{
    const EXCEPTION_TYPE_OBJECT = 'object';
    const EXCEPTION_TYPE_MSG = 'message';
    const EXCEPTION_TYPE_STRING = 'string';
    const EXCEPTION_TYPE_ARRAY = 'array';

    const DEFAULT_ERROR_MESSAGE = 'message';

    public $appConfig = '@mitosentry/tests/unit/config/main.php';

    protected function tearDown()
    {
        parent::tearDown();
        Mockery::close();
    }

    protected function mockSentryTarget($options = [])
    {
        $component = Mockery::mock(Component::className());

        return Yii::createObject(ArrayHelper::merge([
            'class' => Target::className(),
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
        $component = Mockery::mock(Component::className());
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
                ->with(Mockery::on(function ($data) use ($message) {
                    return $data['message'] === $message;
                }), Mockery::on(function ($traces) {
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
     * @dataProvider exceptFilters
     *
     * @param $except
     * @param $exceptionClass
     * @param $exceptionCode
     * @param $expectLogged
     * @param $type
     */
    public function testFilterExceptions($except, $exceptionClass, $exceptionCode, $expectLogged, $type)
    {
        $target = $this->mockSentryTarget($except);
        $logger = $this->mockLogger($target);

        if ($expectLogged) {
            if ($type === self::EXCEPTION_TYPE_OBJECT) {
                $target->sentry->shouldReceive('captureException')
                    ->with(Mockery::on(function ($exception) {
                        return ($exception instanceof \Throwable || $exception instanceof \Exception) && $exception->getMessage() === self::DEFAULT_ERROR_MESSAGE;
                    }), Mockery::on(function ($data) {
                        return true;
                    }))->once();
            } else {
                $target->sentry->shouldReceive('capture')
                    ->with(Mockery::on(function ($data) {
                        return !empty($data['message']);
                    }), Mockery::on(function ($traces) {
                        return true;
                    }))->once();
            }
        } else {
            $target->sentry->shouldNotReceive('capture');
            $target->sentry->shouldNotReceive('captureException');
            $target->sentry->shouldNotReceive('captureMessage');
        }
        $target->sentry->shouldReceive('capture')
            ->with(Mockery::on(function ($data) {
                return $data['message'] === 'sentinel';
            }), Mockery::on(function ($traces) {
                return true;
            }))->once();

        $exception = $this->createException($type, $exceptionClass, $exceptionCode);
        $category = $this->getLogCategory($exception);

        if ($exception !== false) {
            $logger->log($exception, Logger::LEVEL_ERROR, $category);
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
                ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
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
                ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
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
    public function exceptFilters()
    {

        $results = [
            'skip code 404' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 404, false, self::EXCEPTION_TYPE_OBJECT],
            'skip http *' => [['except' => ['yii\web\HttpException:*']], HttpException::class, 403, false, self::EXCEPTION_TYPE_OBJECT],
            'catch code 0' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 0, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 400' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 400, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 401' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 401, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 402' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 402, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 403' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 403, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 500' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 500, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 503' => [['except' => ['yii\web\HttpException:404']], HttpException::class, 503, true, self::EXCEPTION_TYPE_OBJECT],
            'catch string' => [['except' => ['yii\web\HttpException:404']], null, null, true, self::EXCEPTION_TYPE_STRING],
            'catch message' => [['except' => ['yii\web\HttpException:404']], null, null, true, self::EXCEPTION_TYPE_MSG],
            'catch array' => [['except' => ['yii\web\HttpException:404']], null, null, true, self::EXCEPTION_TYPE_ARRAY],
        ];

        // php7+
        if (class_exists('\Error')) {
            $results = array_merge($results, [
                'skip \Error' => [['except' => ['Error']], \Error::class, null, false, self::EXCEPTION_TYPE_OBJECT],
                'catch \Error' => [['except' => ['yii\web\HttpException:404']], \Error::class, null, true, self::EXCEPTION_TYPE_OBJECT],
            ]);
        }

        return $results;
    }

    private function getLogCategory($exception)
    {
        if (!is_object($exception)) {
            return 'application';
        }

        $category = get_class($exception);
        if ($exception instanceof HttpException) {
            $category = 'yii\\web\\HttpException:' . $exception->statusCode;
        } elseif ($exception instanceof \ErrorException) {
            $category .= ':' . $exception->getSeverity();
        }

        return $category;
    }

    private function createException($type, $exceptionClass, $exceptionCode)
    {
        switch ($type) {
            case self::EXCEPTION_TYPE_OBJECT:
                if ($exceptionClass === HttpException::class) {
                    $args = [$exceptionCode, self::DEFAULT_ERROR_MESSAGE];
                } else {
                    $args = [self::DEFAULT_ERROR_MESSAGE, $exceptionCode];
                }
                $exception = (new \ReflectionClass($exceptionClass))->newInstanceArgs($args);
                break;
            case self::EXCEPTION_TYPE_MSG:
                $exception = ['msg' => self::DEFAULT_ERROR_MESSAGE];
                break;
            case self::EXCEPTION_TYPE_STRING:
                $exception = self::DEFAULT_ERROR_MESSAGE;
                break;
            case self::EXCEPTION_TYPE_ARRAY:
                $exception = [
                    'message' => self::DEFAULT_ERROR_MESSAGE,
                    'other' => 'extra message',
                ];
                break;
            default:
                $exception = false;
        }

        return $exception;
    }
}
