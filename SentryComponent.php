<?php

namespace mito\sentry;

use yii\base\Component;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;

class SentryComponent extends Component
{

    /**
     * @var string Sentry DSN
     */
    public $dsn;

    /**
     * @var string public Sentry DSN for raven-js
     */
    public $publicDsn;

    /**
     * @var array Options of the \Raven_Client.
     * @see \Raven_Client::__construct for more details
     */
    public $options = [];

    /**
     * @var \Raven_Client
     */
    protected $client;

    protected $errorHandler;

    protected $exceptionHandler;

    public function init()
    {
        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private or public DSN must be set!');
        }

        $this->client = new \Raven_Client($this->dsn, $this->options);

        $this->errorHandler = new \Raven_ErrorHandler($this->client);
        $this->errorHandler->registerErrorHandler();

        $this->exceptionHandler = set_exception_handler(array($this, 'handleExceptions'));
    }

    /**
     * @param \Exception $e
     */
    public function handleExceptions($e)
    {
        restore_exception_handler();
        if ($this->canLogException($e)) {
            $e->event_id = $this->client->getIdent($this->client->captureException($e));
        }
        if ($this->exceptionHandler) {
            call_user_func($this->exceptionHandler, $e);
        }
    }

    /**
     * Filter exception and its previous exceptions for yii\base\ErrorException
     * Raven expects normal stacktrace, but yii\base\ErrorException may have xdebug_get_function_stack
     *
     * @param \Exception $e
     * @return bool
     */
    public function canLogException(&$e)
    {
        if (function_exists('xdebug_get_function_stack')) {
            if ($e instanceof ErrorException) {
                return false;
            }
            $selectedException = $e;
            while ($nestedException = $selectedException->getPrevious()) {
                if ($nestedException instanceof ErrorException) {
                    $ref = new \ReflectionProperty('Exception', 'previous');
                    $ref->setAccessible(true);
                    $ref->setValue($selectedException, null);

                    return true;
                }
                $selectedException = $selectedException->getPrevious();
            }
        }

        return true;
    }

    /**
     * @return \Raven_Client
     */
    public function getClient()
    {
        return $this->client;
    }
}
