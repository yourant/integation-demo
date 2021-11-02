<?php

namespace App\Traits;

trait ResponseUtilTrait {

    public function getActionMsg(int $itemCount, string $itemType, string $action)
    {
        $verb = $itemCount > 1? 'were' : 'was';

        return "A total of {$itemCount} {$itemType} {$verb} {$action}. ";
    }

    public function getErrorMsg()
    {
        return "There is an issue with the request. Please check the log for the complete details.";
    }

    public function getJsonResponse(int $successCount, int $errorCount, string $itemType, string $action)
    {
        $alertType = 'alert-';
        $responseMsg = '';

        if ($errorCount) {
            $level = 'ERROR: ';
            $alertType .= 'danger';

            if ($successCount) {
                $level = 'WARNING: ';
                $alertType .= 'warning';
                $responseMsg .= $this->getActionMsg($successCount, $itemType, $action);
            }

            $responseMsg .= $this->getErrorMsg();
        } else {
            $level = 'SUCCESS: ';
            $alertType .= 'success';
            $responseMsg .= $this->getActionMsg($successCount, $itemType, $action);
        }

        return [
            'level' => $level,
            'alertType' => $alertType,
            'message' => $responseMsg
        ];
    }
}