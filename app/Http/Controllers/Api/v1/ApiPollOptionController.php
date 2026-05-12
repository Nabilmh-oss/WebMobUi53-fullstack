<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Http\Request;

class ApiPollOptionController extends Controller
{
    public function store(Request $request, int $pollId)
    {
        $poll = Poll::where('id', $pollId)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $v = $request->validate(['label' => 'required|string|max:255']);

        return response()->json($poll->options()->create($v), 201);
    }

    public function update(Request $request, int $pollId, int $optionId)
    {
        $poll = Poll::where('id', $pollId)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $option = PollOption::where('id', $optionId)->where('poll_id', $pollId)->first();

        if (!$option) {
            return response()->json(['message' => 'Option introuvable.'], 404);
        }

        $option->update($request->validate(['label' => 'required|string|max:255']));

        return response()->json($option);
    }

    public function destroy(Request $request, int $pollId, int $optionId)
    {
        $poll = Poll::where('id', $pollId)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $option = PollOption::where('id', $optionId)->where('poll_id', $pollId)->first();

        if (!$option) {
            return response()->json(['message' => 'Option introuvable.'], 404);
        }

        $option->delete();

        return response()->json(['message' => 'success']);
    }
}
