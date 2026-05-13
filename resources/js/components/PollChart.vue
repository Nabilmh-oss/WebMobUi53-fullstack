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
