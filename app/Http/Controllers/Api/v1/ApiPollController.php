<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    /**
     * Display a listing of the authenticated user's polls.
     */
    public function index(Request $request)
    {
        $polls = $request->user()->polls()->with('options')->orderBy('created_at', 'desc')->get();

        return response()->json($polls);
    }

    /**
     * Display the specified poll by its secret token.
     */
public function show(Request $request, string $token)
    {
        $poll = Poll::with(['options' => fn($q) => $q->withCount('votes')])
            ->where('secret_token', $token)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user          = auth('sanctum')->user();
        $isOwner       = $user && $user->id === $poll->user_id;
        $hasVoted      = $user
            ? \App\Models\PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->exists()
            : false;
        $userVoteIds   = $user
            ? \App\Models\PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->pluck('poll_option_id')
            : [];
        $canSeeResults = $isOwner || $poll->results_public;

        $data                         = $poll->toArray();
        $data['is_owner']             = $isOwner;
        $data['has_voted']            = $hasVoted;
        $data['user_vote_option_ids'] = $userVoteIds;
        $data['can_see_results']      = $canSeeResults;
        $data['is_authenticated']     = (bool) $user;

        if (!$canSeeResults) {
            $data['options'] = array_map(function ($opt) {
                unset($opt['votes_count']);
                return $opt;
            }, $data['options']);
        }

        return response()->json($data);
    }

    /**
     * Remove the specified poll.
     */
    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'success'], 200);
    }
    public function store(Request $request)
    {
        $v = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'required|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
            'options'                => 'nullable|array',
            'options.*.label'        => 'required_with:options|string|max:255',
            'launch'                 => 'boolean',
        ]);

        $launching = $v['launch'] ?? false;

        $poll = $request->user()->polls()->create([
            'title'                  => $v['title'] ?? null,
            'question'               => $v['question'],
            'secret_token'           => Str::random(32),
            'is_draft'               => !$launching,
            'allow_multiple_choices' => $v['allow_multiple_choices'] ?? false,
            'allow_vote_change'      => $v['allow_vote_change'] ?? false,
            'results_public'         => $v['results_public'] ?? false,
            'duration'               => $v['duration'] ?? null,
            'started_at'             => $launching ? now() : null,
            'ends_at'                => $launching && isset($v['duration'])
                                            ? now()->addSeconds($v['duration'])
                                            : null,
        ]);

        foreach ($v['options'] ?? [] as $opt) {
            $poll->options()->create(['label' => $opt['label']]);
        }

        return response()->json($poll->load('options'), 201);
    }

    public function update(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $v = $request->validate([
            'title'                  => 'nullable|string|max:255',
            'question'               => 'sometimes|required|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:1',
        ]);

        $poll->update($v);

        return response()->json($poll->fresh()->load('options'));
    }

    public function launch(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        if (!$poll->is_draft) {
            return response()->json(['message' => 'Ce sondage est déjà lancé.'], 409);
        }

        $poll->update([
            'is_draft'   => false,
            'started_at' => now(),
            'ends_at'    => $poll->duration ? now()->addSeconds($poll->duration) : null,
        ]);

        return response()->json($poll->fresh()->load('options'));
    }
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }
        if ($poll->is_draft) {
            return response()->json(['message' => 'Ce sondage n\'est pas encore actif.'], 403);
        }
        if ($poll->ends_at && $poll->ends_at < now()) {
            return response()->json(['message' => 'Ce sondage est terminé.'], 403);
        }

        $v        = $request->validate(['option_ids' => 'required|array|min:1', 'option_ids.*' => 'integer']);
        $validIds = $poll->options->pluck('id')->toArray();

        foreach ($v['option_ids'] as $oid) {
            if (!in_array($oid, $validIds)) {
                return response()->json(['message' => 'Option invalide.'], 422);
            }
        }

        if (!$poll->allow_multiple_choices && count($v['option_ids']) > 1) {
            return response()->json(['message' => 'Un seul choix autorisé.'], 422);
        }

        $userId   = $request->user()->id;
        $existing = \App\Models\PollVote::where('poll_id', $poll->id)->where('user_id', $userId)->get();

        if ($existing->count() > 0) {
            if (!$poll->allow_vote_change) {
                return response()->json(['message' => 'Vous avez déjà voté.'], 403);
            }
            $existing->each->delete();
        }

        foreach ($v['option_ids'] as $oid) {
            \App\Models\PollVote::create([
                'poll_id'        => $poll->id,
                'user_id'        => $userId,
                'poll_option_id' => $oid,
            ]);
        }

        return response()->json(['message' => 'Vote enregistré.'], 201);
    }
}
