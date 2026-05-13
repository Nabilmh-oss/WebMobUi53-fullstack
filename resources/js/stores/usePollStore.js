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
