<?php

namespace mito\sentry;

use mito\sentry\assets\RavenAsset;
use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
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
    public $environment = 'development';

    /**
     * @var array Options of the Raven client.
     * @see \Raven_Client::__construct for more details
     * @deprecated use [[client]] instead
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
    public $jsOptions;

    /**
     * Raven-JS configuration array
     *
     * @var array
     * @see https://docs.getsentry.com/hosted/clients/javascript/config/
     * @deprecated use [[jsOptions]] instead
     */
    public $clientOptions = [];

    /**
     * @var string Raven client class
     * @deprecated use [[client]] instead
     */
    public $ravenClass = '\Raven_Client';

    /**
     * @var \Raven_Client|array Raven client or configuration array used to instantiate one
     */
    public $client;

    public function init()
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->jsOptions === null) {
            $this->jsOptions = $this->clientOptions;
        }

        $this->setEnvironmentOptions();

        // for backwards compatibility
        $this->clientOptions = $this->jsOptions;

        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private or public DSN must be set!');
        }

        if ($this->publicDsn === null && $this->jsNotifier === true) {
            $this->publicDsn = preg_replace('/^(https:\/\/|http:\/\/)([a-z0-9]*):([a-z0-9]*)@(.*)/', '$1$2@$4', $this->dsn);
        }
        if (!empty($this->publicDsn)) {
            $this->jsNotifier = true;
        }

        if (is_array($this->client)) {
            $ravenClass = ArrayHelper::remove($this->client, 'class', '\Raven_Client');
            $options = $this->client;
            $this->client = new $ravenClass($this->dsn, $options);
        } elseif (empty($this->client)) {
            // deprecated codepath
            $this->client = new $this->ravenClass($this->dsn, $this->options);
        }

        $this->registerAssets();
    }

    private function setEnvironmentOptions()
    {
        if (empty($this->environment)) {
            return;
        }

        if (is_array($this->client)) {
            $this->client['tags']['environment'] = $this->environment;
        }
        $this->options['tags']['environment'] = $this->environment;
        $this->jsOptions['tags']['environment'] = $this->environment;
    }

    /**
     * Registers RavenJS if publicDsn exists
     */
    private function registerAssets()
    {
        if ($this->jsNotifier && Yii::$app instanceof \yii\web\Application) {
            RavenAsset::register(Yii::$app->getView());
            Yii::$app->getView()->registerJs('Raven.config(' . Json::encode($this->publicDsn) . ', ' . Json::encode($this->jsOptions) . ').install();', View::POS_HEAD);
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

    /**
     * @return \Raven_Client
     * @deprecated use [[$client]]
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return string public dsn
     * @deprecated use [[$publicDsn]]
     */
    public function getPublicDsn()
    {
        return $this->publicDsn;
    }
}
