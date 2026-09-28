<script setup>
import { computed } from 'vue'
import { state, playAgain } from '../game'
import { pad, formatTime } from '../format'
import LeaderboardTable from './LeaderboardTable.vue'

const f = computed(() => state.final)
const won = computed(() => f.value?.status === 'completed')
</script>

<template>
  <section v-if="f" class="result">
    <div class="panel center">
      <h1 class="title" :class="won ? '' : 'dead'">{{ won ? 'CASE CLOSED' : 'GAME OVER' }}</h1>
      <p class="tagline">
        {{ won
          ? `All ${f.totalCases} files unsealed, Detective ${state.player}.`
          : `Detective ${state.player} solved ${f.casesSolved} of ${f.totalCases} cases.` }}
      </p>

      <div class="hud stats">
        <div><small>Score</small><strong>{{ pad(f.score) }}</strong></div>
        <div><small>Time</small><strong>{{ formatTime(f.timeMs) }}</strong></div>
        <div><small>Cases</small><strong>{{ f.casesSolved }}/{{ f.totalCases }}</strong></div>
        <div><small>Rank</small><strong>{{ f.rank ? '#' + f.rank : 'None' }}</strong></div>
      </div>
      <p v-if="!f.rank" class="msg">Solve at least one case to reach the board.</p>
      <button class="btn" @click="playAgain">Play again</button>
    </div>

    <div class="panel">
      <h2 class="h">Hi-scores</h2>
      <LeaderboardTable :limit="10" :run-id="state.runId" />
    </div>
  </section>
</template>
