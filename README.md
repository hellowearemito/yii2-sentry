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

Once the extension is installed, set your configuration in common config file:

```php
    'bootstrap' => ['log', 'sentry'],
    'components' => [
    
        // required
        'sentry' => [
            'class' => 'mito\sentry\SentryComponent',
            'dsn' => '', // private DSN for PHP errors
            'publicDsn' => '', // for JS errors
            'clientOptions' => [ // raven-js config parameter
                'whitelistUrls' => [ // collect JS errors from these urls
                    'http://staging.my-product.com',
                    'https://my-product.com',
                ],
            ],
        ],
        
        // optional
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

To skip collecting errors from development environment use this setup in your development config file:

```
    'components' => [
        'sentry' => [
            'enabled' => false,
        ],
    ],
```