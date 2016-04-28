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
     * @deprecated since version 0.4.0
     */
    public $publicDsn;

    /**
     * @var string environment name
     */
    public $environment = 'development';

    /**
     * @var array Options of the \Raven_Client.
     * @see \Raven_Client::__construct for more details
     */
    public $options = [];

    /**
     * collect JavaScript errors
     *
     * @var bool
     */
    public $jsNotifier = false;

    /**
     * Raven-JS configuration array
     *
     * @var array
     * @see https://docs.getsentry.com/hosted/clients/javascript/config/
     */
    public $clientOptions = [];

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

        $this->setEnvironmentOptions();

        $this->client = new \Raven_Client($this->dsn, $this->options);

        $this->errorHandler = new \Raven_ErrorHandler($this->client);
        $this->errorHandler->registerErrorHandler();

        $this->exceptionHandler = set_exception_handler(array($this, 'handleExceptions'));

        $this->registerAssets();
    }

    private function setEnvironmentOptions()
    {
        if (empty($this->environment)) {
            return;
        }

        $this->options['tags']['environment'] = $this->environment;
        $this->clientOptions['tags']['environment'] = $this->environment;
    }

    /**
     * Registers RavenJS if publicDsn exists
     */
    private function registerAssets()
    {
        /** to keep BC */
        if (!empty($this->publicDsn)) {
            $this->jsNotifier = true;
        }

        if ($this->jsNotifier && Yii::$app instanceof \yii\web\Application) {
            RavenAsset::register(Yii::$app->getView());
            Yii::$app->getView()->registerJs('Raven.config(' . Json::encode($this->getPublicDsn()) . ', ' . Json::encode($this->clientOptions) . ').install();', View::POS_HEAD);
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

    private function getPublicDsn()
    {
        return preg_replace('/^(https:\/\/|http:\/\/)([a-z0-9]*):([a-z0-9]*)@(.*)/', '$1$2@$4', $this->dsn);
    }
}
