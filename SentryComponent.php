<?php

namespace mito\sentry;

use mito\sentry\assets\RavenAsset;
use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\web\View;

class SentryComponent extends Component
{

    /**
     * Set to `false` in development environment to skip collecting errors
     *
     * @var bool
     */
    public $enabled = true;

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
        if (!$this->enabled) {
            return;
        }

        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private or public DSN must be set!');
        }

        $this->client = new \Raven_Client($this->dsn, $this->options);

        $this->errorHandler = new \Raven_ErrorHandler($this->client);
        $this->errorHandler->registerErrorHandler();

        $this->exceptionHandler = set_exception_handler(array($this, 'handleExceptions'));

        $this->registerAssets();
    }

    /**
     * Registers RavenJS if publicDsn exists
     */
    private function registerAssets()
    {
        if (!empty($this->publicDsn)) {
            RavenAsset::register(Yii::$app->getView());
            Yii::$app->getView()->registerJs('Raven.config(' . Json::encode($this->publicDsn) . ').install();', View::POS_HEAD);
        }
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
