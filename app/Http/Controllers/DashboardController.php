<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // $request->user() grabs the currently authenticated user
        $user = $request->user();
        return Inertia::render('dashboard/index', [
            'userName' => $user->name,
        ]);
    }
}
