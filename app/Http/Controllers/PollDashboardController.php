<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PollDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $polls = $request->user()->polls()->with('options')->orderBy('created_at', 'desc')->get();

        return view('polls.dashboard', [
            'polls' => $polls,
        ]);
    }
}
