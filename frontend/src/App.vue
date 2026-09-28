<script setup>
import { onMounted } from 'vue'
import { state, loadConfig, refreshHiScore } from './game'
import AppHeader from './components/AppHeader.vue'
import IntroScreen from './components/IntroScreen.vue'
import CaseStage from './components/CaseStage.vue'
import ResultScreen from './components/ResultScreen.vue'
import BoardScreen from './components/BoardScreen.vue'

onMounted(() => { loadConfig(); refreshHiScore() })
</script>

<template>
  <AppHeader />
  <main>
    <IntroScreen v-if="state.screen === 'intro'" />
    <CaseStage v-else-if="state.screen === 'stage'" :key="state.caseIndex" />
    <ResultScreen v-else-if="state.screen === 'result'" />
    <BoardScreen v-else />
  </main>
  <footer>PERSAKA 26/27 internal training simulation</footer>
</template>
