<?php
/**
 * Application configuration for test console.
 */
return yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../../config/console.php'),
    require(__DIR__ . '/config.php'),
    []
);
