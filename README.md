Yii 2 - Sentry Error Logger
==================

[Sentry](https://getsentry.com/) provides real-time crash reporting for web apps, both server and client side. This is a Yii 2 extension which lets you integrate your projects to Sentry and log PHP and JavaScript errors.

Brought to you by [Mito](http://mito.hu). 

[![Latest Stable Version](https://poser.pugx.org/mito/yii2-sentry/v/stable)](https://packagist.org/packages/mito/yii2-sentry) [![Total Downloads](https://poser.pugx.org/mito/yii2-sentry/downloads)](https://packagist.org/packages/mito/yii2-sentry) [![License](https://poser.pugx.org/mito/yii2-sentry/license)](https://packagist.org/packages/mito/yii2-sentry)

[![Build Status](https://travis-ci.org/hellowearemito/yii2-sentry.svg?branch=master)](https://travis-ci.org/hellowearemito/yii2-sentry) [![Coverage Status](https://coveralls.io/repos/github/hellowearemito/yii2-sentry/badge.svg?branch=master)](https://coveralls.io/github/hellowearemito/yii2-sentry?branch=master)

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mito/yii2-sentry "~1.0.0"
```

or add the following line to the require section of your `composer.json` file:

```
"mito/yii2-sentry": "~1.0.0"
```

## Requirements

Yii 2 and above.
Sentry 8 and above.

You can use this extension with both the hosted and on-premise version of Sentry. 


## Usage

Once the extension is installed, set your configuration in common config file:

```php
    'components' => [

        'sentry' => [
            'class' => 'mito\sentry\Component',
            'dsn' => 'YOUR-PRIVATE-DSN', // private DSN
            'environment' => 'staging', // if not set, the default is `production`
            'jsNotifier' => true, // to collect JS errors. Default value is `false`
            'jsOptions' => [ // raven-js config parameter
                'whitelistUrls' => [ // collect JS errors from these urls
                    'http://staging.my-product.com',
                    'https://my-product.com',
                ],
            ],
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'mito\sentry\Target',
                    'levels' => ['error', 'warning'],
                    'except' => [
                        'yii\web\HttpException:404',
                    ],
                ],
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
