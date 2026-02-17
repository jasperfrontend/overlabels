<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function notfound()
    {
        return view('errors.404');
    }

    public function notAuthorized()
    {
        return route('login');
    }
}
