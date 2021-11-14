<?php

namespace App\Http\Controllers\Tchub;

use App\Services\SapService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TchubController extends Controller
{
    public function index(Request $request)
    {
        return view('tchub.dashboard');
    }
}