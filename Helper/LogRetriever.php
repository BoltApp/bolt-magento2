<?php

namespace Bolt\Boltpay\Helper;

class LogRetriever
{
    const DEFAULT_LOG_PATH = "var/log/exception.log";

    /**
     * @param string $logPath
     * @param int $lines
     * @return array
     */
    public function getLog($logPath = self::DEFAULT_LOG_PATH, $lines = 100)
    {
        return explode("\n", $this->customTail($logPath, $lines));
    }

    private function customTail($logPath, $lines)
    {
        return trim (shell_exec("tail -n $lines $logPath")) ?: "No file found at " . $logPath;
    }
}
