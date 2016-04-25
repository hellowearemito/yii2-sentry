Sentry Integration
==================
Yii 2 extension for Sentry

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mito/yii2-sentry "*"
```

or add

```
"mito/yii2-sentry": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, set your configuration in config file:

```php

    'components' => [
        'sentry' => [
            'class' => 'mito\sentry\SentryComponent',
            'dsn' => '', // private DSN for PHP errors
            'publicDsn' => '', // for JS errors 
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'mito\sentry\SentryTarget',
                    'levels' => ['error', 'warning'],
                ]
            ],
        ],
    ],
```
