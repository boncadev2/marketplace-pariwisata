<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class ConfigureLogging
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(new RedactSensitiveData);
    }
}
