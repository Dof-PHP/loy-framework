<?php

declare(strict_types=1);

namespace Dof\Framework\Log;

use Dof\Framework\Log\Logger\File;

class LoggerAware
{
    private $logger;

    public function setLogger($logger)
    {
        $psr3 = 'Psr\Log\LoggerInterface';
        if ((! ($logger instanceof LoggerInterface)) && (! ($logger instanceof $psr3))) {
            exception('UnacceptableLogger', ['logger' => string_literal($logger)]);
        }

        $this->logger = $logger;

        return $this;
    }

    public function getLogger()
    {
        return $this->logger ?: ($this->logger = (new File));
    }

    public function __call(string $method, array $params)
    {
        return $this->getLogger()->{$method}(...$params);
    }
}
