<?php

namespace mito\sentry\tests\unit;

class DummyRavenClient
{
    public $tags = [];
    public $dsn;

    public function __construct($dsn, $options)
    {
        if (isset($options['tags'])) {
            $this->tags = $options['tags'];
        }
        $this->dsn = $dsn;
    }
}
