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
