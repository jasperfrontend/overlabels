<?php

namespace App\Http\Controllers;

use App\Support\SkillFacts;
use App\Support\SkillReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function index(Request $request): Response
    {
        $sets = SkillReport::build(SkillFacts::for($request->user()));

        // Counted in subjects, not skillsets: "two lists are not shown
        // anywhere" is actionable, "one area needs attention" is not.
        $looseEnds = array_sum(array_column($sets, 'attention'));

        return Inertia::render('skills/index', [
            'skillsets' => $sets,
            'looseEnds' => $looseEnds,
        ]);
    }
}
