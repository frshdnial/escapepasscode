<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '../api'
import { state } from '../game'
import { pad, formatTime } from '../format'

const props = defineProps({
  limit: { type: Number, default: 10 },
  runId: { type: String, default: null },
  compact: Boolean,
})

const rows = ref([])
const you = ref(null)
const loading = ref(true)
const error = ref('')
const total = computed(() => state.config?.totalCases ?? 5)
const outside = computed(() => you.value && !rows.value.some((r) => r.you))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const r = await api.leaderboard(props.limit, props.runId)
    rows.value = r.entries
    you.value = r.you
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => [props.limit, props.runId], load)
</script>

<template>
  <p v-if="loading" class="msg">Loading scores…</p>
  <p v-else-if="error" class="msg bad">{{ error }} <button class="btn ghost sm" @click="load">Retry</button></p>
  <p v-else-if="!rows.length" class="msg">No scores yet. Solve a case to take the first spot.</p>
  <div v-else class="scroll">
    <table class="board" :class="{ compact }">
      <thead>
        <tr>
          <th>#</th><th>Detective</th><th class="num">Score</th><th class="num">Time</th><th v-if="!compact" class="num">Cases</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in rows" :key="r.rank" :class="{ you: r.you }">
          <td class="rk" :class="'r' + r.rank">{{ r.rank }}</td>
          <td class="nm">{{ r.name }}<span v-if="r.you" class="tag"> you</span></td>
          <td class="num">{{ pad(r.score) }}</td>
          <td class="num">{{ formatTime(r.timeMs) }}</td>
          <td v-if="!compact" class="num">{{ r.casesSolved }}/{{ total }}<span v-if="r.completed" class="ok"> ✓</span></td>
        </tr>
        <template v-if="outside">
          <tr class="gap"><td colspan="5">…</td></tr>
          <tr class="you">
            <td class="rk">{{ you.rank }}</td>
            <td class="nm">{{ you.name }}<span class="tag"> you</span></td>
            <td class="num">{{ pad(you.score) }}</td>
            <td class="num">{{ formatTime(you.timeMs) }}</td>
            <td v-if="!compact" class="num">{{ you.casesSolved }}/{{ total }}</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
