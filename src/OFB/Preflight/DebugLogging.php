<?php

declare(strict_types=1);

namespace Dof\Framework\OFB\Preflight;

use Dof\Framework\Facade\Log;
use Dof\Framework\ConfigManager;

class DebugLogging
{
    public function preflight($request, $response)
    {
        $header = ConfigManager::getEnv('HTTP_DEBUG_HEADER', 'DOF_HTTP_DEBUG');
        if (! $request->hasHeader($header)) {
            return true;
        }
        $key = $request->getHeader($header);
        if (! $key) {
            return true;
        }

        if ($this->debugable($key)) {
            Log::setDebug(true)->setDebugKey($key);
        }

        return true;
    }

    protected function debugable(string $key) : bool
    {
        $config = ConfigManager::getEnv('HTTP_DEBUG_LOGGING', []);

        return (bool) ($config[$key] ?? false);
    }
}
