<?php
/**
 * Created by PhpStorm.
 * User: aborsos
 * Date: 16. 05. 03.
 * Time: 13:57
 */

namespace mito\sentry\tests\unit;


use mito\sentry\SentryTarget;
use mito\sentry\SentryComponent;
use yii\log\Logger;
use Yii;
use yii\helpers\ArrayHelper;
use Mockery;

class SentryTargetTest extends \yii\codeception\TestCase
{
    const EXCEPTION_TYPE_OBJECT = 'object';
    const EXCEPTION_TYPE_MSG = 'message';
    const EXCEPTION_TYPE_STRING = 'string';

    public $appConfig = '@mitosentry/tests/unit/config/main.php';

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
     * @param string $application
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
     * @param $exceptionCode
     * @param $expectLogged
     * @param $type
     * @param string $application
     */
    public function testExceptions($except, $exceptionCode, $expectLogged, $type)
    {
        $target = $this->mockSentryTarget($except);
        $logger = $this->mockLogger($target);

        if ($expectLogged) {
            if ($type === self::EXCEPTION_TYPE_OBJECT) {
                $target->sentry->shouldReceive('captureException')
                    ->with(Mockery::on(function($exception) {
                        return $exception instanceof \Exception && $exception->getMessage() === 'message';
                    }), Mockery::on(function($data) {
                        return true;
                    }))->once();
            } else {
                $target->sentry->shouldReceive('capture')
                    ->with(Mockery::on(function($data) {
                        return $data['message'] === 'message';
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

        try {
            throw new \Exception('message', $exceptionCode);
        } catch (\Exception $e) {

            $exception = $e;
            switch ($type) {
                case self::EXCEPTION_TYPE_MSG:
                    $exception = ['msg' => $e->getMessage()];
                    break;
                case self::EXCEPTION_TYPE_STRING:
                    $exception = $e->getMessage();
                    break;
            }

            $logger->log($exception, Logger::LEVEL_ERROR, 'yii\web\HttpException:' . $exceptionCode);
        }
        $logger->log('sentinel', Logger::LEVEL_INFO);

        $logger->flush(true);
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

    public function exceptions()
    {
        return [
            'skip code 404' => [['except' => ['yii\web\HttpException:404']], 404, false, self::EXCEPTION_TYPE_OBJECT],
            'skip http *' => [['except' => ['yii\web\HttpException:*']], 403, false, self::EXCEPTION_TYPE_MSG],
            'catch code 0' => [['except' => ['yii\web\HttpException:404']], 0, true, self::EXCEPTION_TYPE_STRING],
            'catch code 400' => [['except' => ['yii\web\HttpException:404']], 400, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 401' => [['except' => ['yii\web\HttpException:404']], 401, true, self::EXCEPTION_TYPE_MSG],
            'catch code 402' => [['except' => ['yii\web\HttpException:404']], 402, true, self::EXCEPTION_TYPE_STRING],
            'catch code 403' => [['except' => ['yii\web\HttpException:404']], 403, true, self::EXCEPTION_TYPE_OBJECT],
            'catch code 500' => [['except' => ['yii\web\HttpException:404']], 500, true, self::EXCEPTION_TYPE_MSG],
            'catch code 503' => [['except' => ['yii\web\HttpException:404']], 503, true, self::EXCEPTION_TYPE_STRING],
        ];
    }
}
