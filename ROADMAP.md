# Roadmap — Application de sondage (Laravel + Vue.js)

## Ordre des commits

| # | Message de commit |
|---|-------------------|
| 1 | `feat: add fillable and casts to poll models` |
| 2 | `feat: add poll CRUD API endpoints (store, update, launch)` |
| 3 | `feat: add poll option management API endpoints` |
| 4 | `feat: enrich poll show endpoint with user context` |
| 5 | `feat: add vote endpoint and poll vote page` |
| 6 | `feat: update poll store with full CRUD actions` |
| 7 | `feat: add poll editor component` |
| 8 | `feat: update dashboard with navigation and improved table` |
| 9 | `feat: add poll chart component` |
| 10 | `feat: add poll vote and results page` |
| 11 | `chore: error handling, responsive polish and README` |

---

## Commit 1 — `feat: add fillable and casts to poll models`

### `app/Models/Poll.php`
✅ Déjà fait — `$fillable` et `$casts` sont présents aux lignes 35–48.

---

### `app/Models/PollOption.php`
Le fichier fait 26 lignes. Ajouter la ligne suivante **juste avant le `}` final (ligne 26)** :

```php
    protected $fillable = ['poll_id', 'label'];
```

Résultat attendu à la fin du fichier :
```php
    protected $fillable = ['poll_id', 'label'];
}
```

---

### `app/Models/PollVote.php`
Le fichier fait 33 lignes. Ajouter la ligne suivante **juste avant le `}` final (ligne 33)** :

```php
    protected $fillable = ['poll_id', 'user_id', 'poll_option_id'];
```

Résultat attendu à la fin du fichier :
```php
    protected $fillable = ['poll_id', 'user_id', 'poll_option_id'];
}
```

---

## Commit 2 — `feat: add poll CRUD API endpoints (store, update, launch)`

### `app/Http/Controllers/Api/v1/ApiPollController.php`

**Étape 1 — Ajouter l'import `Str`** à la ligne 7, juste après `use Illuminate\Http\Request;` :
```php
use Illuminate\Support\Str;
```

**Étape 2 — Mettre à jour la méthode `index`** (lignes 14–19).
Remplacer le contenu de la méthode par :
```php
    public function index(Request $request)
    {
        $polls = $request->user()->polls()->with('options')->orderBy('created_at', 'desc')->get();

        return response()->json($polls);
    }
```

**Étape 3 — Ajouter `store`, `update`, `launch`** juste avant le `}` final du fichier (ligne 52), après la méthode `remove` :
```php
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
```

---

### `routes/api.php`

Dans le groupe `auth:sanctum` (lignes 21–26), ajouter après la ligne `Route::delete('/v1/polls/{id}', ...)` :
```php
    Route::post('/v1/polls',             [ApiPollController::class, 'store']);
    Route::put('/v1/polls/{id}',         [ApiPollController::class, 'update']);
    Route::post('/v1/polls/{id}/launch', [ApiPollController::class, 'launch']);
```

Le groupe `auth:sanctum` doit ressembler à :
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v1/foo', [ApiFooController::class, 'show']);
    Route::post('/v1/foo', [ApiFooController::class, 'store']);
    Route::get('/v1/polls', [ApiPollController::class, 'index']);
    Route::delete('/v1/polls/{id}', [ApiPollController::class, 'remove']);
    Route::post('/v1/polls',             [ApiPollController::class, 'store']);
    Route::put('/v1/polls/{id}',         [ApiPollController::class, 'update']);
    Route::post('/v1/polls/{id}/launch', [ApiPollController::class, 'launch']);
});
```

---

## Commit 3 — `feat: add poll option management API endpoints`

### Créer le fichier `app/Http/Controllers/Api/v1/ApiPollOptionController.php`
C'est un nouveau fichier. Le créer avec ce contenu :
```php
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
```

---

### `routes/api.php`

**Étape 1 — Ajouter l'import** à la ligne 5, après la ligne `use App\Http\Controllers\Api\v1\ApiPollController;` :
```php
use App\Http\Controllers\Api\v1\ApiPollOptionController;
```

**Étape 2 — Ajouter les routes** dans le groupe `auth:sanctum`, après les routes du commit 2 :
```php
    Route::post('/v1/polls/{id}/options',              [ApiPollOptionController::class, 'store']);
    Route::put('/v1/polls/{id}/options/{optionId}',    [ApiPollOptionController::class, 'update']);
    Route::delete('/v1/polls/{id}/options/{optionId}', [ApiPollOptionController::class, 'destroy']);
```

---

## Commit 4 — `feat: enrich poll show endpoint with user context`

### `app/Http/Controllers/Api/v1/ApiPollController.php`

**Remplacer entièrement la méthode `show`** (lignes 21–35) par :
```php
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
```

> ⚠️ La signature change : `show(string $token)` devient `show(Request $request, string $token)`.

---

### `app/Http/Controllers/PollDashboardController.php`

**Remplacer la ligne 11** :
```php
        $polls = $request->user()->polls()->orderBy('created_at', 'desc')->get();
```
par :
```php
        $polls = $request->user()->polls()->with('options')->orderBy('created_at', 'desc')->get();
```

---

## Commit 5 — `feat: add vote endpoint and poll vote page`

### `app/Http/Controllers/Api/v1/ApiPollController.php`

Ajouter la méthode `vote` **juste avant le `}` final du fichier**, après la méthode `launch` :
```php
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
```

---

### `routes/api.php`

Ajouter dans le groupe `auth:sanctum`, après les routes d'options :
```php
    Route::post('/v1/polls/{token}/vote', [ApiPollController::class, 'vote']);
```

---

### `routes/web.php`

Ajouter **tout en bas du fichier** (après la ligne 41, hors de tout groupe) :
```php
Route::get('/polls/{token}', function ($token) {
    return view('polls.vote', ['token' => $token]);
})->name('polls.vote');
```

---

### Créer `resources/views/polls/vote.blade.php`
Nouveau fichier dans le dossier `resources/views/polls/` (qui existe déjà) :
```blade
<x-vue-app-layout>
    <x-slot:title>
        Sondage
    </x-slot>

    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <div
        id="app-poll-vote"
        data-props='@json(["token" => $token])'
    ></div>
</x-vue-app-layout>
```

---

## Commit 6 — `feat: update poll store with full CRUD actions`

### `resources/js/stores/usePollStore.js`

**Remplacer tout le contenu du fichier** par :
```js
import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

const polls       = ref([]);
const currentPoll = ref(null);

export function usePollStore() {
  const { fetchApi } = useFetchApi();

  function setPolls(data) { polls.value = data; }

  function selectPoll(poll) {
    currentPoll.value = poll ? { ...poll, options: [...(poll.options ?? [])] } : null;
  }

  async function createPoll(data) {
    const result = await fetchApi({ url: 'polls', method: 'POST', data });
    polls.value.unshift(result);
    return result;
  }

  async function updatePoll(id, data) {
    const result = await fetchApi({ url: `polls/${id}`, method: 'PUT', data });
    const idx = polls.value.findIndex(p => p.id === id);
    if (idx !== -1) polls.value[idx] = result;
    if (currentPoll.value?.id === id) currentPoll.value = result;
    return result;
  }

  async function launchPoll(id) {
    const result = await fetchApi({ url: `polls/${id}/launch`, method: 'POST' });
    const idx = polls.value.findIndex(p => p.id === id);
    if (idx !== -1) polls.value[idx] = result;
    if (currentPoll.value?.id === id) currentPoll.value = result;
    return result;
  }

  async function deletePoll(id) {
    await fetchApi({ url: `polls/${id}`, method: 'DELETE' });
    polls.value = polls.value.filter(p => p.id !== id);
  }

  async function addOption(pollId, label) {
    const option = await fetchApi({ url: `polls/${pollId}/options`, method: 'POST', data: { label } });
    if (currentPoll.value?.id === pollId) currentPoll.value.options.push(option);
    return option;
  }

  async function updateOption(pollId, optionId, label) {
    const option = await fetchApi({ url: `polls/${pollId}/options/${optionId}`, method: 'PUT', data: { label } });
    if (currentPoll.value?.id === pollId) {
      const idx = currentPoll.value.options.findIndex(o => o.id === optionId);
      if (idx !== -1) currentPoll.value.options[idx] = option;
    }
    return option;
  }

  async function deleteOption(pollId, optionId) {
    await fetchApi({ url: `polls/${pollId}/options/${optionId}`, method: 'DELETE' });
    if (currentPoll.value?.id === pollId) {
      currentPoll.value.options = currentPoll.value.options.filter(o => o.id !== optionId);
    }
  }

  return {
    polls, currentPoll,
    setPolls, selectPoll,
    createPoll, updatePoll, launchPoll, deletePoll,
    addOption, updateOption, deleteOption,
  };
}
```

---

## Commit 7 — `feat: add poll editor component`

### Créer `resources/js/components/PollEditor.vue`
Nouveau fichier dans `resources/js/components/` (qui existe déjà) :
```vue
<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  mode: { type: String, default: 'create' },
});
const emit = defineEmits(['saved', 'launched']);

const { currentPoll, createPoll, updatePoll, launchPoll, addOption, updateOption, deleteOption } = usePollStore();

const question             = ref('');
const title                = ref('');
const allowMultipleChoices = ref(false);
const allowVoteChange      = ref(false);
const resultsPublic        = ref(false);
const hasDuration          = ref(false);
const durationHours        = ref(0);
const durationMinutes      = ref(0);
const newOptions           = ref([{ label: '' }, { label: '' }]);
const newOptionInput       = ref('');
const editingOptionId      = ref(null);
const editingLabel         = ref('');
const error                = ref(null);
const optionError          = ref(null);
const saving               = ref(false);
const launching            = ref(false);

const isEdit = computed(() => props.mode === 'edit');
const poll   = computed(() => currentPoll.value);

onMounted(() => {
  if (isEdit.value && poll.value) {
    question.value             = poll.value.question;
    title.value                = poll.value.title ?? '';
    allowMultipleChoices.value = poll.value.allow_multiple_choices;
    allowVoteChange.value      = poll.value.allow_vote_change;
    resultsPublic.value        = poll.value.results_public;
    if (poll.value.duration) {
      hasDuration.value     = true;
      durationHours.value   = Math.floor(poll.value.duration / 3600);
      durationMinutes.value = Math.floor((poll.value.duration % 3600) / 60);
    }
  }
});

function durationInSeconds() {
  if (!hasDuration.value) return null;
  const s = durationHours.value * 3600 + durationMinutes.value * 60;
  return s > 0 ? s : null;
}

function buildPayload() {
  return {
    question:               question.value.trim(),
    title:                  title.value.trim() || null,
    allow_multiple_choices: allowMultipleChoices.value,
    allow_vote_change:      allowVoteChange.value,
    results_public:         resultsPublic.value,
    duration:               durationInSeconds(),
  };
}

async function handleSave() {
  if (!question.value.trim()) { error.value = 'La question est obligatoire.'; return; }
  error.value = null; saving.value = true;
  try {
    if (isEdit.value) { await updatePoll(poll.value.id, buildPayload()); }
    else { await createPoll({ ...buildPayload(), options: newOptions.value.filter(o => o.label.trim()) }); }
    emit('saved');
  } catch (err) {
    error.value = err?.data?.message ?? 'Une erreur est survenue.';
  } finally { saving.value = false; }
}

async function handleSaveAndLaunch() {
  if (!question.value.trim()) { error.value = 'La question est obligatoire.'; return; }
  error.value = null; launching.value = true;
  try {
    if (isEdit.value) {
      await updatePoll(poll.value.id, buildPayload());
      await launchPoll(poll.value.id);
    } else {
      await createPoll({ ...buildPayload(), options: newOptions.value.filter(o => o.label.trim()), launch: true });
    }
    emit('launched');
  } catch (err) {
    error.value = err?.data?.message ?? 'Une erreur est survenue.';
  } finally { launching.value = false; }
}

function addLocalOption()     { newOptions.value.push({ label: '' }); }
function removeLocalOption(i) { if (newOptions.value.length > 1) newOptions.value.splice(i, 1); }
function startEdit(opt)       { editingOptionId.value = opt.id; editingLabel.value = opt.label; }

async function saveEdit(optionId) {
  if (!editingLabel.value.trim()) return;
  try { await updateOption(poll.value.id, optionId, editingLabel.value.trim()); editingOptionId.value = null; }
  catch { optionError.value = 'Erreur lors de la mise à jour.'; }
}

async function handleDeleteOption(optionId) {
  try   { await deleteOption(poll.value.id, optionId); }
  catch { optionError.value = 'Erreur lors de la suppression.'; }
}

async function handleAddOption() {
  if (!newOptionInput.value.trim()) return;
  try { await addOption(poll.value.id, newOptionInput.value.trim()); newOptionInput.value = ''; }
  catch { optionError.value = 'Erreur lors de l\'ajout.'; }
}
</script>

<template>
  <div class="space-y-6 max-w-2xl">
    <h2 class="text-xl font-semibold">{{ isEdit ? 'Modifier le sondage' : 'Nouveau sondage' }}</h2>

    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
      {{ error }}
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Titre <span class="text-slate-400">(optionnel)</span></label>
      <input v-model="title" type="text" placeholder="Ex: Sondage d'équipe"
        class="w-full border rounded-lg px-3 py-2 text-sm" />
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Question <span class="text-red-500">*</span></label>
      <input v-model="question" type="text" placeholder="Quelle est votre question ?"
        class="w-full border rounded-lg px-3 py-2 text-sm" />
    </div>

    <!-- Options mode création -->
    <div v-if="!isEdit">
      <label class="block text-sm font-medium mb-2">Options de réponse</label>
      <div v-for="(opt, i) in newOptions" :key="i" class="flex gap-2 mb-2">
        <input v-model="newOptions[i].label" type="text" :placeholder="'Option ' + (i + 1)"
          class="flex-1 border rounded-lg px-3 py-2 text-sm" />
        <button @click="removeLocalOption(i)" :disabled="newOptions.length <= 1"
          class="px-2 text-red-400 disabled:opacity-30">✕</button>
      </div>
      <button @click="addLocalOption" class="text-sm text-blue-600 underline">+ Ajouter une option</button>
    </div>

    <!-- Options mode édition -->
    <div v-else-if="poll">
      <label class="block text-sm font-medium mb-2">Options de réponse</label>
      <p v-if="optionError" class="text-red-600 text-sm mb-2">{{ optionError }}</p>
      <div v-for="opt in poll.options" :key="opt.id" class="flex gap-2 mb-2 items-center">
        <template v-if="editingOptionId === opt.id">
          <input v-model="editingLabel" type="text" class="flex-1 border rounded-lg px-3 py-1 text-sm"
            @keyup.enter="saveEdit(opt.id)" />
          <button @click="saveEdit(opt.id)" class="text-green-700 px-2 text-sm">✓</button>
          <button @click="editingOptionId = null" class="text-slate-400 px-2 text-sm">✕</button>
        </template>
        <template v-else>
          <span class="flex-1 text-sm border rounded-lg px-3 py-1 bg-slate-50">{{ opt.label }}</span>
          <button @click="startEdit(opt)" class="text-blue-600 text-sm px-2">Modifier</button>
          <button @click="handleDeleteOption(opt.id)" class="text-red-500 text-sm px-2">✕</button>
        </template>
      </div>
      <div class="flex gap-2 mt-2">
        <input v-model="newOptionInput" type="text" placeholder="Nouvelle option..."
          class="flex-1 border rounded-lg px-3 py-1 text-sm" @keyup.enter="handleAddOption" />
        <button @click="handleAddOption" class="text-sm bg-slate-100 px-3 py-1 rounded-lg hover:bg-slate-200">
          Ajouter
        </button>
      </div>
    </div>

    <!-- Paramètres -->
    <div class="border-t pt-4 space-y-3">
      <p class="text-sm font-medium">Paramètres</p>
      <label class="flex items-center gap-2 cursor-pointer text-sm">
        <input type="checkbox" v-model="allowMultipleChoices" class="rounded" />
        Choix multiples autorisés
      </label>
      <label class="flex items-center gap-2 cursor-pointer text-sm">
        <input type="checkbox" v-model="resultsPublic" class="rounded" />
        Résultats publics (visibles sans connexion)
      </label>
      <label class="flex items-center gap-2 cursor-pointer text-sm">
        <input type="checkbox" v-model="allowVoteChange" class="rounded" />
        Permettre la modification du vote (bonus)
      </label>
      <label class="flex items-center gap-2 cursor-pointer text-sm">
        <input type="checkbox" v-model="hasDuration" class="rounded" />
        Durée limitée
      </label>
      <div v-if="hasDuration" class="flex gap-2 items-center pl-6">
        <input v-model.number="durationHours"   type="number" min="0"
          class="w-20 border rounded px-2 py-1 text-sm" />
        <span class="text-sm">h</span>
        <input v-model.number="durationMinutes" type="number" min="0" max="59"
          class="w-20 border rounded px-2 py-1 text-sm" />
        <span class="text-sm">min</span>
      </div>
    </div>

    <!-- Boutons -->
    <div class="border-t pt-4 flex gap-3 flex-wrap">
      <button @click="handleSave" :disabled="saving || launching"
        class="px-4 py-2 bg-slate-200 rounded-lg text-sm hover:bg-slate-300 disabled:opacity-50">
        {{ saving ? 'Enregistrement…' : 'Enregistrer (brouillon)' }}
      </button>
      <button v-if="!isEdit || poll?.is_draft" @click="handleSaveAndLaunch"
        :disabled="saving || launching"
        class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">
        {{ launching ? 'Lancement…' : '🚀 Lancer le sondage' }}
      </button>
      <button v-else @click="handleSave" :disabled="saving || launching"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
        {{ saving ? 'Enregistrement…' : 'Enregistrer les modifications' }}
      </button>
    </div>
  </div>
</template>
```

---

## Commit 8 — `feat: update dashboard with navigation and improved table`

### `resources/js/AppPollDashboard.vue`
**Remplacer tout le contenu du fichier** par :
```vue
<script setup>
import { ref } from 'vue';
import PollTable  from './components/PollTable.vue';
import PollEditor from './components/PollEditor.vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  polls:    { type: Array,  default: () => [] },
  loginUrl: { type: String, default: null },
  username: { type: String, default: null },
});

const { setPolls, selectPoll } = usePollStore();
setPolls(props.polls);

const view = ref('list'); // 'list' | 'create' | 'edit'

function goToCreate() { selectPoll(null); view.value = 'create'; }
function goToEdit(poll) { selectPoll(poll); view.value = 'edit'; }
function goToList() { view.value = 'list'; }
</script>

<template>
  <div class="p-4 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Mes sondages</h1>
      <button v-if="view === 'list'" @click="goToCreate"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
        + Nouveau sondage
      </button>
      <button v-else @click="goToList" class="text-blue-600 text-sm underline">
        ← Retour à la liste
      </button>
    </div>
    <PollTable  v-if="view === 'list'" @edit="goToEdit" />
    <PollEditor v-else :mode="view" @saved="goToList" @launched="goToList" />
  </div>
</template>
```

---

### `resources/js/components/PollTable.vue`
**Remplacer tout le contenu du fichier** par :
```vue
<script setup>
import { ref } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const emit = defineEmits(['edit']);
const { polls, deletePoll } = usePollStore();
const copiedId = ref(null);

async function handleDelete(id) {
  if (!confirm('Supprimer ce sondage ?')) return;
  await deletePoll(id);
}

async function copyLink(poll) {
  await navigator.clipboard.writeText(`${window.location.origin}/polls/${poll.secret_token}`);
  copiedId.value = poll.id;
  setTimeout(() => (copiedId.value = null), 2000);
}
</script>

<template>
  <p v-if="polls.length === 0" class="text-slate-500 text-center py-8">
    Aucun sondage. Créez-en un !
  </p>
  <div v-else class="space-y-3">
    <div v-for="poll in polls" :key="poll.id" class="border rounded-xl p-4 bg-white shadow-sm">
      <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
          <p class="font-semibold truncate">{{ poll.title || poll.question }}</p>
          <p v-if="poll.title" class="text-sm text-slate-500 truncate">{{ poll.question }}</p>
          <div class="mt-1 flex flex-wrap gap-2 text-xs">
            <span class="px-2 py-0.5 rounded-full"
              :class="poll.is_draft ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'">
              {{ poll.is_draft ? 'Brouillon' : 'Actif' }}
            </span>
            <span v-if="poll.ends_at" class="text-slate-400">
              Fin : {{ new Date(poll.ends_at).toLocaleString() }}
            </span>
          </div>
        </div>
        <div class="flex gap-2 shrink-0">
          <button @click="$emit('edit', poll)"
            class="px-3 py-1 text-sm bg-slate-100 rounded-lg hover:bg-slate-200">
            Modifier
          </button>
          <button @click="copyLink(poll)"
            class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200">
            {{ copiedId === poll.id ? '✓ Copié' : '🔗 Lien' }}
          </button>
          <button @click="handleDelete(poll.id)"
            class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200">
            Suppr.
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## Commit 9 — `feat: add poll chart component`

### Créer `resources/js/components/PollChart.vue`
Nouveau fichier dans `resources/js/components/` :
```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
  options: { type: Array, default: () => [] },
});

const total = computed(() =>
  props.options.reduce((sum, o) => sum + (o.votes_count ?? 0), 0)
);

const bars = computed(() =>
  props.options.map(o => ({
    label:   o.label,
    count:   o.votes_count ?? 0,
    percent: total.value > 0 ? Math.round(((o.votes_count ?? 0) / total.value) * 100) : 0,
  }))
);
</script>

<template>
  <div>
    <p v-if="total === 0" class="text-slate-400 text-sm text-center py-4">
      Aucun vote pour le moment.
    </p>
    <div v-else class="space-y-3">
      <div v-for="bar in bars" :key="bar.label">
        <div class="flex justify-between text-sm mb-1">
          <span class="text-slate-700">{{ bar.label }}</span>
          <span class="text-slate-500">
            {{ bar.count }} vote{{ bar.count !== 1 ? 's' : '' }} · {{ bar.percent }}%
          </span>
        </div>
        <div class="h-5 bg-slate-100 rounded-full overflow-hidden">
          <div class="h-full bg-blue-500 rounded-full transition-all duration-500"
            :style="{ width: bar.percent + '%' }" />
        </div>
      </div>
      <p class="text-xs text-slate-400 text-right">
        Total : {{ total }} vote{{ total !== 1 ? 's' : '' }}
      </p>
    </div>
  </div>
</template>
```

---

## Commit 10 — `feat: add poll vote and results page`

### Créer `resources/js/AppPollVote.vue`
Nouveau fichier à la racine de `resources/js/` (au même niveau que `AppPollDashboard.vue`) :
```vue
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';
import { usePolling }  from '@/composables/usePolling';
import PollChart from './components/PollChart.vue';

const props = defineProps({
  token: { type: String, required: true },
});

const { fetchApi } = useFetchApi();

const poll        = ref(null);
const loading     = ref(true);
const fetchError  = ref(null);
const voteError   = ref(null);
const voteSuccess = ref(false);
const voting      = ref(false);
const selectedOptionIds = ref([]);

const isExpired = computed(() => {
  if (!poll.value?.ends_at) return false;
  return new Date(poll.value.ends_at) < new Date();
});

const canVote = computed(() => {
  if (!poll.value)                                           return false;
  if (poll.value.is_draft)                                   return false;
  if (isExpired.value)                                       return false;
  if (!poll.value.is_authenticated)                          return false;
  if (poll.value.has_voted && !poll.value.allow_vote_change) return false;
  return true;
});

async function fetchPoll() {
  try {
    const data = await fetchApi({ url: `polls/${props.token}` });
    poll.value = data;
    if (data.has_voted && data.user_vote_option_ids?.length) {
      selectedOptionIds.value = [...data.user_vote_option_ids];
    }
    fetchError.value = null;
  } catch (err) {
    if (!poll.value) fetchError.value = err?.data?.message ?? 'Sondage introuvable.';
  }
}

async function submitVote() {
  if (selectedOptionIds.value.length === 0) {
    voteError.value = 'Veuillez sélectionner au moins une option.'; return;
  }
  voteError.value = null; voteSuccess.value = false; voting.value = true;
  try {
    await fetchApi({
      url: `polls/${props.token}/vote`,
      method: 'POST',
      data: { option_ids: selectedOptionIds.value },
    });
    voteSuccess.value = true;
    await fetchPoll();
  } catch (err) {
    voteError.value = err?.data?.message ?? 'Erreur lors du vote.';
  } finally { voting.value = false; }
}

function toggleOption(id) {
  if (poll.value?.allow_multiple_choices) {
    const idx = selectedOptionIds.value.indexOf(id);
    idx === -1 ? selectedOptionIds.value.push(id) : selectedOptionIds.value.splice(idx, 1);
  } else {
    selectedOptionIds.value = [id];
  }
}

async function copyLink() {
  await navigator.clipboard.writeText(window.location.href);
}

onMounted(async () => { await fetchPoll(); loading.value = false; });
usePolling(fetchPoll, 5000);
</script>

<template>
  <div class="min-h-screen bg-slate-50 p-4">
    <div class="max-w-lg mx-auto space-y-4">

      <div v-if="loading" class="text-center py-16 text-slate-400">Chargement…</div>
      <div v-else-if="fetchError"
        class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
        {{ fetchError }}
      </div>

      <template v-else-if="poll">
        <!-- En-tête -->
        <div class="bg-white rounded-xl shadow-sm p-6">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h1 class="text-xl font-bold">{{ poll.title || poll.question }}</h1>
              <p v-if="poll.title" class="text-slate-600 mt-1 text-sm">{{ poll.question }}</p>
            </div>
            <span class="text-xs px-2 py-1 rounded-full shrink-0"
              :class="poll.is_draft ? 'bg-yellow-100 text-yellow-700'
                : isExpired ? 'bg-slate-100 text-slate-600'
                : 'bg-green-100 text-green-700'">
              {{ poll.is_draft ? 'Brouillon' : isExpired ? 'Terminé' : 'En cours' }}
            </span>
          </div>
          <p v-if="poll.ends_at" class="mt-3 text-sm text-slate-500">
            {{ isExpired ? '⏱ Terminé le' : '⏱ Se termine le' }}
            {{ new Date(poll.ends_at).toLocaleString() }}
          </p>
          <!-- Lien de partage visible uniquement par le créateur -->
          <div v-if="poll.is_owner" class="mt-3 flex gap-2 items-center">
            <input :value="window.location.href" readonly
              class="flex-1 text-xs border rounded-lg px-2 py-1 bg-slate-50 text-slate-600" />
            <button @click="copyLink"
              class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-lg hover:bg-blue-200">
              Copier le lien
            </button>
          </div>
        </div>

        <!-- Formulaire de vote -->
        <div v-if="!poll.is_draft" class="bg-white rounded-xl shadow-sm p-6">
          <h2 class="font-semibold mb-4">
            {{ canVote ? (poll.has_voted ? 'Modifier votre vote' : 'Voter') : 'Options' }}
          </h2>

          <div v-if="isExpired" class="mb-4 p-3 bg-slate-100 text-slate-600 rounded-lg text-sm">
            ⛔ Ce sondage est terminé. Il n'est plus possible de voter.
          </div>
          <div v-else-if="!poll.is_authenticated"
            class="mb-4 p-3 bg-blue-50 text-blue-700 rounded-lg text-sm">
            <a href="/auth/login" class="underline font-medium">Connectez-vous</a> pour voter.
          </div>
          <div v-else-if="poll.has_voted && !poll.allow_vote_change"
            class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">
            ✓ Vous avez déjà voté.
          </div>

          <div class="space-y-2">
            <label v-for="option in poll.options" :key="option.id"
              class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer hover:bg-slate-50 transition-colors"
              :class="{ 'border-blue-400 bg-blue-50': selectedOptionIds.includes(option.id) }">
              <input v-if="poll.allow_multiple_choices" type="checkbox"
                :checked="selectedOptionIds.includes(option.id)"
                @change="toggleOption(option.id)" :disabled="!canVote" class="rounded" />
              <input v-else type="radio"
                :checked="selectedOptionIds.includes(option.id)"
                @change="toggleOption(option.id)" :disabled="!canVote" />
              <span class="text-sm flex-1">{{ option.label }}</span>
            </label>
          </div>

          <p v-if="voteError"   class="mt-3 text-red-600 text-sm">{{ voteError }}</p>
          <p v-if="voteSuccess" class="mt-3 text-green-600 text-sm">✓ Vote enregistré !</p>

          <button v-if="canVote" @click="submitVote"
            :disabled="voting || selectedOptionIds.length === 0"
            class="mt-4 w-full bg-blue-600 text-white py-2 rounded-xl text-sm font-medium
                   hover:bg-blue-700 disabled:opacity-50">
            {{ voting ? 'Envoi…' : poll.has_voted ? 'Modifier mon vote' : 'Soumettre mon vote' }}
          </button>
        </div>

        <!-- Résultats -->
        <div v-if="poll.can_see_results" class="bg-white rounded-xl shadow-sm p-6">
          <div class="flex justify-between items-center mb-4">
            <h2 class="font-semibold">Résultats</h2>
            <span class="text-xs text-slate-400">↻ toutes les 5 s</span>
          </div>
          <PollChart :options="poll.options" />
        </div>
        <div v-else-if="!poll.is_draft && !poll.can_see_results"
          class="bg-white rounded-xl shadow-sm p-4 text-center text-slate-500 text-sm">
          Les résultats ne sont pas publics.
        </div>
      </template>

    </div>
  </div>
</template>
```

---

### Créer `resources/js/poll-vote.js`
Nouveau fichier à la racine de `resources/js/` (au même niveau que `poll-dashboard.js`) :
```js
import './bootstrap';
import { createApp } from 'vue';
import App from './AppPollVote.vue';

const el    = document.getElementById('app-poll-vote');
const props = JSON.parse(el.dataset.props ?? '{}');

createApp(App, props).mount(el);
```

---

### `vite.config.js`

**Ligne 9** — dans le tableau `input`, ajouter `'resources/js/poll-vote.js'` :
```js
input: [
    'resources/css/app.css',
    'resources/js/poll-dashboard.js',
    'resources/js/poll-vote.js',   // ← ajouter cette ligne
],
```

---

## Commit 11 — `chore: error handling, responsive polish and README`

- Vérifier que tous les messages d'erreur API s'affichent dans l'UI
- Tester l'affichage sur mobile (375px) via les DevTools du navigateur
- Compléter `README.md` avec les étapes d'installation et les choix techniques
