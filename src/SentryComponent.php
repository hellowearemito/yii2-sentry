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
     * @var array Options of the Raven client.
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
     * @var string Raven client class
     */
    public $ravenClass = '\Raven_Client';

    /**
     * Raven-JS configuration array
     *
     * @var array
     * @see https://docs.getsentry.com/hosted/clients/javascript/config/
     */
    public $clientOptions = [];

    /**
     * @var \Raven_Client Raven client
     */
    protected $client;

    public function init()
    {
        if (!$this->enabled) {
            return;
        }

        $this->setEnvironmentOptions();

        if (empty($this->dsn)) {
            throw new InvalidConfigException('Private or public DSN must be set!');
        }

        if ($this->publicDsn === null && $this->jsNotifier === true) {
            $this->publicDsn = preg_replace('/^(https:\/\/|http:\/\/)([a-z0-9]*):([a-z0-9]*)@(.*)/', '$1$2@$4', $this->dsn);
        }
        if (!empty($this->publicDsn)) {
            $this->jsNotifier = true;
        }

        $this->client = new $this->ravenClass($this->dsn, $this->options);

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
        if ($this->jsNotifier && Yii::$app instanceof \yii\web\Application) {
            RavenAsset::register(Yii::$app->getView());
            Yii::$app->getView()->registerJs('Raven.config(' . Json::encode($this->publicDsn) . ', ' . Json::encode($this->clientOptions) . ').install();', View::POS_HEAD);
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
