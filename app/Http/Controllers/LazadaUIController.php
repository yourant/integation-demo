<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LazadaUIController extends Controller
{
    public function index()
    {
        return view('lazada.dashboard');
    }
}
