<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;

class LazadaLogService
{
    public function __construct($channel)
    {
        $this->channel = $channel;
    }

    public function writeSapLog(ClientException $exception, $msg){
        $response = $e->getResponse();
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $fullErrorMsg = $statusCode."(".$reasonPhrase.")"." - ".$response->getBody(true);

        Log::channel($this->channel)->emergency($msg."\n".$fullErrorMsg);
    }


}