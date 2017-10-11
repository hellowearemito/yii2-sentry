<?php

namespace mito\sentry;

use Closure;
use mito\sentry\assets\RavenAsset;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\View;

class Component extends \yii\base\Component
{

    /**
     * Set to `false` in development environment to skip collecting errors
     *
     * @var bool
     */
    public $enabled = true;

    /**
     * @var string Sentry DSN
     * @note this is ignored if [[client]] is a Raven client instance.
     */
    public $dsn;

    /**
     * @var string public Sentry DSN for raven-js
     * If not set, this is generated from the private dsn.
     */
    public $publicDsn;

    /**
     * @var string environment name
     * @note this is ignored if [[client]] is a Raven client instance.
     */
    public $environment = 'production';

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
    public $jsOptions;

    /**
     * @var \Raven_Client|array Raven client or configuration array used to instantiate one
     */
    public $client = [];

    public function init()
    {
        if (!$this->enabled) {
            return;
        }

        $this->validateDsn();
        $this->setRavenClient();
        $this->setEnvironmentOptions();
        $this->generatePublicDsn();
        $this->registerAssets();
    }

    private function validateDsn()
    {
        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private DSN must be set!');
        }

        // throws \InvalidArgumentException if dsn is invalid
        \Raven_Client::parseDSN($this->dsn);
    }

    /**
     * Adds a tag to filter events by environment
     */
    private function setEnvironmentOptions()
    {
        if (empty($this->environment)) {
            return;
        }

        if (is_object($this->client) && property_exists($this->client, 'environment')) {
            $this->client->environment = $this->environment;
        }
        $this->jsOptions['environment'] = $this->environment;
    }

    private function setRavenClient()
    {
        if (is_array($this->client)) {
            $ravenClass = ArrayHelper::remove($this->client, 'class', '\Raven_Client');
            $options = $this->client;
            $this->client = new $ravenClass($this->dsn, $options);
        } elseif (!is_object($this->client) || $this->client instanceof Closure) {
            $this->client = Yii::createObject($this->client);
        }

        if (!is_object($this->client)) {
            throw new InvalidConfigException(get_class($this) . '::' . 'client must be an object');
        }
    }

    /**
     * Registers RavenJS if publicDsn exists
     */
    private function registerAssets()
    {
        if ($this->jsNotifier === false) {
            return;
        }

        if (!Yii::$app instanceof \yii\web\Application) {
            return;
        }

        try {
            $view = Yii::$app->getView();
            RavenAsset::register($view);
            $view->registerJs('Raven.config(' . Json::encode($this->publicDsn) . ', ' . Json::encode($this->jsOptions) . ').install();', View::POS_HEAD);
        } catch (Exception $e) {
            // initialize Sentry component even if unable to register the assets
            Yii::error($e->getMessage());
        }
    }

    public function captureMessage($message, $params, $levelOrOptions = [], $stack = false, $vars = null)
    {
        return $this->client->captureMessage($message, $params, $levelOrOptions, $stack, $vars);
    }

    public function captureException($exception, $culpritOrOptions = null, $logger = null, $vars = null)
    {
        return $this->client->captureException($exception, $culpritOrOptions, $logger, $vars);
    }

    public function capture($data, $stack = null, $vars = null)
    {
        return $this->client->capture($data, $stack, $vars);
    }

    private function generatePublicDsn()
    {
        if ($this->publicDsn === null && $this->jsNotifier === true) {
            $this->publicDsn = preg_replace('/^(https:\/\/|http:\/\/)([a-z0-9]*):([a-z0-9]*)@(.*)/', '$1$2@$4', $this->dsn);
        }
    }
}
