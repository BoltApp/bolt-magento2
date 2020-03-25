<?php

namespace Bolt\Boltpay\Helper;

class LogRetriever
{
    const LOGPATH = "var/log/exception.log";
    public function _construct (

    ) {

    }

    /**
     * @param string $logpath
     * @return array
     */
    public function getExceptionLog($logpath = self::LOGPATH, $lines = 100)
    {
        //open file
        //get last 100 lines
        $logString = $this->customTail($logpath, $lines, true);
        return explode("\n", $logString);
    }

    private function customTail($logpath = self::LOGPATH, $lines = 100, $adaptive = true)
    {
        //Open file, return false if doesn't exist
        $file = @fopen($logpath, "rb");
        if ($file === false)
        {
            return "No file found at " . $logpath;
        }

        //set buffer according to lines we want
        if (!$adaptive)
        {
            $buffer = 4096;
        }
        else
        {
            $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        }

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
