<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShopeeController extends Controller
{
    public function index()
    {
        return view('shopee.dashboard');
    }

    public function index2()
    {
        return view('lazada.dashboard');
    }  
    
    public function syncProduct()
    {

    }

    public function updatePrice()
    {

    }

    public function updateStock()
    {

    }

    public function generateSalesorder()
    {

    }

    public function generateInvoice()
    {

    }

}
