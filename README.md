Yii 2 - Sentry Error Logger
==================

[Sentry](https://getsentry.com/) provides real-time crash reporting for web apps, both server and client side. This is a Yii 2 extension which lets you integrate your projects to Sentry and log PHP and JavaScript errors.

Brought to you by [Mito](http://mito.hu). 

[![Latest Stable Version](https://poser.pugx.org/mito/yii2-sentry/v/stable)](https://packagist.org/packages/mito/yii2-sentry) [![Total Downloads](https://poser.pugx.org/mito/yii2-sentry/downloads)](https://packagist.org/packages/mito/yii2-sentry) [![License](https://poser.pugx.org/mito/yii2-sentry/license)](https://packagist.org/packages/mito/yii2-sentry)

[![Build Status](https://travis-ci.org/hellowearemito/yii2-sentry.svg?branch=master)](https://travis-ci.org/hellowearemito/yii2-sentry)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mito/yii2-sentry "*"
```

or add the following line to the require section of your `composer.json` file:

```
"mito/yii2-sentry": "*"
```

## Requirements

Yii 2 and above.
Sentry 8 and above.

You can use this extension with both the hosted and on-premise version of Sentry. 


## Usage

Once the extension is installed, set your configuration in common config file:

```php
    'bootstrap' => ['log', 'sentry'],
    'components' => [
    
        'sentry' => [
            'class' => 'mito\sentry\SentryComponent',
            'dsn' => '', // private DSN
            'environment' => YII_CONFIG_ENVIRONMENT, // if not set, the default is `development`
            'jsNotifier' => true, // to collect JS errors
            'clientOptions' => [ // raven-js config parameter
                'whitelistUrls' => [ // collect JS errors from these urls
                    'http://staging.my-product.com',
                    'https://my-product.com',
                ],
            ],
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'mito\sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                    'except' => [
                        'yii\web\HttpException:404',
                    ],
                ]
            ],
        ],
    ],
```

To skip collecting errors in the development environment, disable the component with this parameter:

```php
    'components' => [
        'sentry' => [
            'enabled' => false,
        ],
    ],
```

## License

Code released under [MIT License](LICENSE).

## Contact

Should you have any comments or questions, please contact us at [info@mito.hu](mailto:info@mito.hu).