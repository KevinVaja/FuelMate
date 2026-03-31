<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function contact()
    {
        return view('pages.contact');
    }

    public function petrol()
    {
        return view('pages.petrol');
    }

    public function diesel()
    {
        return view('pages.diesel');
    }

    public function emergency()
    {
        return view('pages.emergency');
    }

    public function availability()
    {
        return view('pages.availability');
    }
}