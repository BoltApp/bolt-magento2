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
        //Open file, return informative error string if doesn't exist
        $file = @fopen($logPath, "rb");
        if ($file === false)
        {
            return "No file found at " . $logPath;
        }

        $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));

        fseek($file, -1, SEEK_END);

        //Correct for blank line at end of file
        if (fread($file, 1) != "\n")
        {
            $lines -= 1;
        }

        $output = '';

        while (ftell($file) > 0 && $lines >= 0)
        {
            $seek = min(ftell($file), $buffer);
            fseek($file, -$seek, SEEK_CUR);
            $chunk = fread($file, $seek);
            $output = $chunk . $output;
            fseek($file, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            $lines -= substr_count($chunk, "\n");
        }

        //possible that with the buffer we read too many lines.
        //find first newline char and remove all text before that
        while ($lines++ < 0)
        {
            $output = substr($output, strpos($output, "\n") + 1);
        }

        fclose($file);
        return trim($output);
    }
}
