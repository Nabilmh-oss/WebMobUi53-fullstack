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
        {{ launching ? 'Lancement…' : 'Lancer le sondage' }}
      </button>
      <button v-else @click="handleSave" :disabled="saving || launching"
        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
        {{ saving ? 'Enregistrement…' : 'Enregistrer les modifications' }}
      </button>
    </div>
  </div>
</template>
