<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;

class LogService
{
    protected $channel;
    protected $errorCount;

    public function __construct($channel)
    {
        $this->channel = 'shopee.' . $channel;
        $this->errorCount = 0;
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function getErrorCount()
    {
        return $this->errorCount;
    }
    
    public function writeLog(string $msg, bool $hasError = false)
    {      
        if ($hasError) {
            Log::channel($this->channel)->error($msg);
        } else {
            Log::channel($this->channel)->info($msg);
        }
    }

    public function writeSapLog(ClientException $exception) 
    {
        $response = $exception->getResponse();
        $codeMsg = $response->getStatusCode();
        $reasonMsg = $response->getReasonPhrase();

        $this->errorCount++;
        
        Log::channel($this->channel)->error("[" . $codeMsg . "] - " . $reasonMsg);
    }

    public function validateResponse(array $response, string $identifier = null)
    {
        if ($error = $response['error']) {

            $errorMsg = $response['message'];
            $logStr = $identifier ? "[{$identifier}] " : "";
            $response = null;

            $this->errorCount++;
            
            $this->writeLog($logStr . "[{$error}] - {$errorMsg}", true);  
        }       

        return $response;
    }
}