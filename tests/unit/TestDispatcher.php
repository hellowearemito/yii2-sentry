<?php

namespace mito\sentry\tests\unit;

class TestDispatcher extends \yii\log\Dispatcher
{
    /**
     * Dispatches the logged messages to [[targets]].
     * Modified to not swallow exceptions.
     * @param array $messages the logged messages
     * @param boolean $final whether this method is called at the end of the current application
     */
    public function dispatch($messages, $final)
    {
        foreach ($this->targets as $target) {
            if ($target->enabled) {
                $target->collect($messages, $final);
            }
        }
    }
}
