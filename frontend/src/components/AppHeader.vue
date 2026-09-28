<script setup>
import { computed } from 'vue'
import { state, openBoard, toggleMute } from '../game'
import { LOGO } from '../format'

const showPips = computed(() => state.config && state.runId && ['stage', 'result'].includes(state.screen))
</script>

<template>
  <header class="bar">
    <div class="brand">
      <img :src="LOGO" alt="PERSAKA logo" width="56" height="44">
      <div>
        <b>PERSAKA 26/27</b>
        <span>Detective Arcade</span>
      </div>
    </div>
    <div class="bar-right">
      <ol v-if="showPips" class="pips" aria-label="Case progress">
        <li
          v-for="n in state.config.totalCases" :key="n" class="pip"
          :class="{ done: n - 1 < state.solved, now: state.screen === 'stage' && n - 1 === state.caseIndex && state.phase === 'playing' }"
        >{{ n }}</li>
      </ol>
      <button v-if="state.screen !== 'stage' && state.screen !== 'board'" class="btn ghost sm" @click="openBoard">Hi-scores</button>
      <button class="btn ghost sm" :aria-pressed="state.muted" @click="toggleMute">{{ state.muted ? 'Sound off' : 'Sound on' }}</button>
    </div>
  </header>
</template>
