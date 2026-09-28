<script setup>
import { ref, computed } from 'vue'
import { state, begin, openBoard, loadConfig } from '../game'
import { LOGO } from '../format'
import LeaderboardTable from './LeaderboardTable.vue'

const name = ref(state.player)
const valid = computed(() => /^[\p{L}\p{N} _.\-]{2,16}$/u.test(name.value.trim()))
const ready = computed(() => valid.value && state.config && !state.busy)

function go() { if (ready.value) begin(name.value) }
</script>

<template>
  <section class="intro">
    <div v-if="state.config" class="panel rules-panel">
      <h2 class="h">How to play</h2>
      <ul class="rules">
        <li>Every case hides a 3-digit combination. Each of its 3 clues resolves to one digit.</li>
        <li>You get {{ state.config.timeLimitSeconds }} seconds and {{ state.config.maxAttempts }} tries per case.</li>
        <li>Faster solves with fewer wrong tries score more. Later cases are worth more.</li>
        <li>Run out of time or tries and the run ends. Your score so far still goes on the board.</li>
      </ul>
    </div>
    <div v-else class="panel rules-panel"></div>

    <div class="panel hero">
      <img class="hero-logo" :src="LOGO" alt="PERSAKA logo">
      <h1 class="title">ESCAPE THE<br>PASSCODE</h1>
      <p class="tagline">Break into sealed case files before the clock runs out.</p>

      <div class="field">
        <label for="name">Detective name</label>
        <input
          id="name" v-model="name" class="input" maxlength="16" autocomplete="off" spellcheck="false"
          placeholder="2 to 16 characters" @keydown.enter="go"
        >
      </div>

      <p v-if="state.error || state.configError" class="msg bad" role="alert">
        {{ state.error || state.configError }}
        <button v-if="state.configError" class="btn ghost sm" @click="loadConfig">Retry</button>
      </p>
      <button class="btn" :disabled="!ready" @click="go">{{ state.busy ? 'Opening file…' : 'Start case 01' }}</button>
    </div>

    <div class="panel top-panel">
      <h2 class="h">Top detectives</h2>
      <LeaderboardTable :limit="5" compact />
      <p><button class="btn ghost sm" @click="openBoard">Full board</button></p>
    </div>
  </section>
</template>
