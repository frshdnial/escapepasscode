<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { state, submit, advance } from '../game'
import { pad, clock } from '../format'
import { sfx } from '../sound'

const digits = ref(['', '', ''])
const boxes = []
const shake = ref(false)
const msg = ref('')

const c = computed(() => state.caseData)
const locked = computed(() => state.phase !== 'playing')
const pct = computed(() => Math.min(100, (state.timeLeftMs / (state.config.timeLimitSeconds * 1000)) * 100))
const secs = computed(() => Math.ceil(state.timeLeftMs / 1000))
const meterClass = computed(() => (secs.value <= 10 ? 'low' : pct.value < 40 ? 'mid' : ''))
const failText = computed(() => {
  const why = state.failReason === 'timeout' ? 'Time ran out.' : 'No tries left.'
  return state.solved > 0 ? `${why} Your score is saved to the board.` : `${why} Solve at least one case to reach the board.`
})

onMounted(() => boxes[0]?.focus())

function onInput(i, e) {
  const v = e.target.value.replace(/\D/g, '').slice(-1)
  digits.value[i] = v
  e.target.value = v
  if (v) { sfx.key(); boxes[i + 1]?.focus() }
}

function onKey(i, e) {
  if (e.key === 'Backspace' && !digits.value[i] && i > 0) boxes[i - 1]?.focus()
  if (e.key === 'Enter') send()
}

function onPaste(e) {
  const t = (e.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 3)
  if (!t) return
  e.preventDefault()
  digits.value = [t[0] ?? '', t[1] ?? '', t[2] ?? '']
  boxes[Math.min(t.length, 3) - 1]?.focus()
}

async function send() {
  if (locked.value || state.busy) return
  const code = digits.value.join('')
  if (code.length < 3) { msg.value = 'Enter all three digits.'; return }
  const result = await submit(code)
  if (result === 'wrong') {
    msg.value = 'Access denied. Recheck your working.'
    digits.value = ['', '', '']
    shake.value = true
    await nextTick()
    boxes[0]?.focus()
  } else {
    msg.value = ''
  }
}
</script>

<template>
  <section v-if="c">
    <div class="hud">
      <div><small>Player</small><strong class="nm">{{ state.player }}</strong></div>
      <div><small>Score</small><strong>{{ pad(state.score) }}</strong></div>
      <div><small>Hi-score</small><strong>{{ pad(Math.max(state.hiScore, state.score)) }}</strong></div>
      <div><small>Time</small><strong :class="{ low: meterClass === 'low' && state.phase === 'playing' }">{{ clock(state.timeLeftMs) }}</strong></div>
    </div>
    <div class="meter" role="presentation"><i :class="meterClass" :style="{ width: pct + '%' }"></i></div>

    <article class="panel case" :class="{ shake }" @animationend.self="shake = false">
      <div class="case-head">
        <div>
          <h2>{{ c.title }}</h2>
          <span class="rank">Rank: {{ c.rank }}</span>
        </div>
        <div class="stamp-tag">CASE {{ String(state.caseIndex + 1).padStart(2, '0') }}</div>
      </div>

      <p class="note">{{ c.note }}</p>

      <div class="clues">
        <div v-for="(clue, i) in c.clues" :key="i" class="clue">
          <b aria-hidden="true">{{ 'ABC'[i] }}</b>
          <p><span class="sr">{{ clue.tag }}: </span>{{ clue.text }}</p>
        </div>
      </div>

      <div class="lock">
        <div id="lock-label" class="lock-label">Enter the 3-digit combination</div>
        <div class="digits" role="group" aria-labelledby="lock-label">
          <input
            v-for="(d, i) in digits" :key="i" :ref="(el) => (boxes[i] = el)"
            class="digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="off"
            :value="d" :disabled="locked" :aria-label="`Digit ${i + 1}`"
            @input="onInput(i, $event)" @keydown="onKey(i, $event)" @paste="onPaste"
          >
        </div>

        <button v-if="state.phase === 'playing'" class="btn" :disabled="state.busy" @click="send">Unlock file</button>
        <p class="msg" :class="{ bad: msg || state.error }" aria-live="polite">{{ msg || state.error }}</p>

        <div v-if="state.phase === 'playing'" class="tries">
          Tries left
          <i v-for="n in state.config.maxAttempts" :key="n" :class="{ spent: n > state.attemptsLeft }"></i>
        </div>

        <div v-if="state.phase === 'granted'" class="ending">
          <div class="result-stamp ok">ACCESS GRANTED +{{ state.lastCaseScore }}</div>
          <p><button class="btn" :disabled="state.busy" @click="advance">{{ state.final ? 'See final report' : 'Next case' }}</button></p>
        </div>
        <div v-else-if="state.phase === 'failed'" class="ending">
          <div class="result-stamp no">{{ state.failReason === 'timeout' ? "TIME'S UP" : 'VAULT LOCKED' }}</div>
          <p class="msg">{{ failText }}</p>
          <p><button class="btn" @click="advance">See results</button></p>
        </div>
      </div>
    </article>
  </section>
</template>
