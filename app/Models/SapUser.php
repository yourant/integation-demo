<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SaintSystems\OData\ODataClient;

class SapUser extends Model
{
    use HasFactory;

    public static function getCurrentUser(ODataClient $odataClient, $userCode)
    {
        $sapUser = $odataClient->from('Users')
            ->where('UserCode', '=', $userCode)
            ->first();
            
        return $sapUser['properties'];
    }
}
