<?php

namespace App\Http\Controllers\Tchub;

use App\Services\SapService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('tchub.dashboard');
    }
}