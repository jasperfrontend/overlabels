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

        return Inertia::render('skills/index', [
            'skillsets' => $sets,
            'looseEnds' => count(array_filter($sets, fn ($s) => $s['status'] === SkillReport::LOOSE_END)),
        ]);
    }
}
