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
