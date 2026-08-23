<?php

namespace App\Http\Controllers;

use App\Support\WiringFacts;
use App\Support\WiringReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WiringController extends Controller
{
    public function index(Request $request): Response
    {
        $circuits = WiringReport::build(WiringFacts::for($request->user()));

        // Counted in subjects, not circuits: "two lists are not shown
        // anywhere" is actionable, "one area needs attention" is not.
        $looseEnds = array_sum(array_column($circuits, 'attention'));

        return Inertia::render('wiring/index', [
            'circuits' => $circuits,
            'looseEnds' => $looseEnds,
        ]);
    }
}
