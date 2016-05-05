<?php

namespace mito\sentry\assets;

use yii\web\AssetBundle;
use yii\web\View;

class RavenAsset extends AssetBundle
{
    public $sourcePath = '@bower/raven-js/dist';

    public $js = [
        'raven.min.js',
    ];

    public $jsOptions = [
        'position' => View::POS_HEAD,
    ];
}
