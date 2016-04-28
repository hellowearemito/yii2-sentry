<?php

namespace mito\sentry;

use Raven_Stacktrace;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\log\Logger;
use yii\log\Target;

class SentryTarget extends Target
{
    /**
     * @var \Raven_Client
     */
    protected $client;

    /**
     * @var string|SentryComponent
     */
    protected $sentry = 'sentry';

    public function init()
    {
        parent::init();

        $this->sentry = Instance::ensure($this->sentry, SentryComponent::className());

        if (!$this->sentry->enabled) {
            return;
        }

        $this->client = $this->sentry->getClient();
    }

    protected function getContextMessage()
    {
        return '';
    }

    /**
     * Filter all exceptions. They logged via ErrorHandler
     * @inheritdoc
     */
    public static function filterMessages($messages, $levels = 0, $categories = [], $except = [])
    {
        $messages = parent::filterMessages($messages, $levels, $categories, $except);
        foreach ($messages as $i => $message) {
            $type = explode(':', $message[2]);
            // shutdown function not working in yii2 yet: https://github.com/yiisoft/yii2/issues/6637
            // allow fatal errors exceptions in log messages
            if (is_array($type) && sizeof($type) == 2 && $type[0] == 'yii\base\ErrorException' && ErrorException::isFatalError(['type' => $type[1]])) {
                continue;
            }

            if (strpos($message[0], 'exception \'') === 0) {
                unset($messages[$i]);
            }
        }

        return $messages;
    }

    /**
     * Exports log [[messages]] to a specific destination.
     */
    public function export()
    {
        if (!$this->sentry->enabled) {
            return;
        }

        foreach ($this->messages as $message) {
            list($msg, $level, $category, $timestamp, $traces) = $message;
            $levelName = Logger::getLevelName($level);
            if (!in_array($levelName, ['error', 'warning', 'info'])) {
                $levelName = 'error';
            }
            $data = [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $timestamp),
                'level' => $levelName,
                'tags' => ['category' => $category],
                'message' => $msg,
            ];
            if (!empty($traces)) {
                $data['sentry.interfaces.Stacktrace'] = [
                    'frames' => Raven_Stacktrace::get_stack_info($traces),
                ];
            }
            $this->client->capture($data, false);
        }
    }
}
