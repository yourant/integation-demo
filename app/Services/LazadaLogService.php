<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;

class LazadaLogService
{
    protected $channel;

    public function __construct($channel)
    {
        $this->channel = $channel;
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function writeSapLog(ClientException $exception, $msg){
        $response = $exception->getResponse();
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $fullErrorMsg = $statusCode."(".$reasonPhrase.")"." - ".$response->getBody(true);
        
        Log::channel($this->channel)->emergency($msg."\n".$fullErrorMsg);
    }


}