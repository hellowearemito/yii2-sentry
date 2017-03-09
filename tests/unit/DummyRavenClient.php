<?php

namespace mito\sentry\tests\unit;

class DummyRavenClient
{
    public $tags = [];
    public $environment;
    public $dsn;

    public function __construct($dsn, $options)
    {
        if (isset($options['tags'])) {
            $this->tags = $options['tags'];
        }
        if (isset($options['environment'])) {
            $this->environment = $options['environment'];
        }
        $this->dsn = $dsn;
    }
}
