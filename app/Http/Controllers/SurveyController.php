<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Survey;

class SurveyController extends Controller
{
    /**
     * Display the surveys page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $surveys = Survey::with(['creator', 'questions.options', 'responses.respondent'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Surveys', [
            'surveys' => $surveys,
        ]);
    }
}