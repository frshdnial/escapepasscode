<script setup>
import { state, closeBoard } from '../game'
import LeaderboardTable from './LeaderboardTable.vue'
</script>

<template>
  <section class="panel">
    <h1 class="title small">HI-SCORES</h1>
    <p class="tagline">Ranked by score. Ties go to the faster total time.</p>
    <LeaderboardTable :limit="20" :run-id="state.runId" />
    <p><button class="btn" @click="closeBoard">Back</button></p>
  </section>
</template>
