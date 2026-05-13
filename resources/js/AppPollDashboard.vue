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
