import { reactive } from 'vue'
import { api } from './api'
import { sfx, loadMuted, setMuted } from './sound'

export const state = reactive({
  screen: 'intro',        // intro | stage | result | board
  prev: 'intro',          // where the board screen returns to
  config: null,           // { totalCases, timeLimitSeconds, maxAttempts }
  configError: '',
  player: '',
  runId: null,
  caseIndex: 0,
  caseData: null,
  phase: 'idle',          // idle | playing | granted | failed
  failReason: '',         // timeout | locked
  score: 0,
  solved: 0,
  lastCaseScore: 0,
  attemptsLeft: 0,
  timeLeftMs: 0,
  final: null,            // { status, score, timeMs, casesSolved, totalCases, rank }
  hiScore: 0,
  busy: false,
  error: '',
  muted: loadMuted(),
})

// ---------------------------------------------------------------- countdown
let timer = null
let deadline = 0
let lastTick = -1

function stopTimer() {
  if (timer) { clearInterval(timer); timer = null }
}

function runTimer(ms) {
  stopTimer()
  deadline = performance.now() + ms
  state.timeLeftMs = ms
  lastTick = -1
  timer = setInterval(() => {
    const left = Math.max(0, deadline - performance.now())
    state.timeLeftMs = left
    const s = Math.ceil(left / 1000)
    if (left > 0 && s <= 10 && s !== lastTick) { lastTick = s; sfx.tick() }
    if (left <= 0) { stopTimer(); expire() }
  }, 100)
}

/** The server confirms the timeout against its own clock, so retry briefly if we are a hair early. */
async function expire() {
  for (let i = 0; i < 4 && state.phase === 'playing'; i++) {
    try {
      finishFailed(await api.timeout(state.runId))
      return
    } catch (e) {
      if (e.status !== 409) { state.error = e.message; return }
      await new Promise((r) => setTimeout(r, 800))
    }
  }
}

function finishFailed(r) {
  stopTimer()
  state.phase = 'failed'
  state.failReason = r.result
  state.final = r.final
  state.score = r.final.score
  state.solved = r.final.casesSolved
  sfx.lose()
}

// ---------------------------------------------------------------- actions
export async function loadConfig() {
  state.configError = ''
  try { state.config = await api.config() } catch (e) { state.configError = e.message }
}

export async function refreshHiScore() {
  try { state.hiScore = (await api.leaderboard(1)).entries[0]?.score ?? 0 } catch { /* offline: keep old value */ }
}

async function openCase() {
  const r = await api.startCase(state.runId)
  state.caseData = r.case
  state.caseIndex = r.caseIndex
  state.attemptsLeft = r.attemptsLeft
  state.lastCaseScore = 0
  state.phase = 'playing'
  state.screen = 'stage'
  runTimer(r.remainingMs)
}

export async function begin(name) {
  state.busy = true
  state.error = ''
  try {
    const r = await api.createRun(name.trim())
    Object.assign(state, {
      runId: r.runId, player: r.player, score: 0, solved: 0, caseIndex: 0, final: null,
      config: { totalCases: r.totalCases, timeLimitSeconds: r.timeLimitSeconds, maxAttempts: r.maxAttempts },
    })
    await openCase()
  } catch (e) {
    state.error = e.message
  } finally {
    state.busy = false
  }
}

/** Returns 'wrong' | 'correct' | 'locked' | 'timeout' | null (request failed). */
export async function submit(code) {
  if (state.phase !== 'playing' || state.busy) return null
  state.busy = true
  state.error = ''
  try {
    const r = await api.guess(state.runId, code)
    if (r.result === 'wrong') {
      state.attemptsLeft = r.attemptsLeft
      sfx.wrong()
      return 'wrong'
    }
    if (r.result === 'correct') {
      stopTimer()
      Object.assign(state, { score: r.score, solved: r.casesSolved, lastCaseScore: r.caseScore, final: r.final ?? null, phase: 'granted' })
      r.finished ? sfx.win() : sfx.correct()
      return 'correct'
    }
    finishFailed(r)
    return r.result
  } catch (e) {
    state.error = e.message
    return null
  } finally {
    state.busy = false
  }
}

/** After a stamp: open the next case, or go to the results when the run is over. */
export async function advance() {
  if (state.phase === 'failed' || state.final) {
    state.screen = 'result'
    refreshHiScore()
    return
  }
  state.busy = true
  state.error = ''
  try { await openCase() } catch (e) { state.error = e.message } finally { state.busy = false }
}

export function playAgain() {
  stopTimer()
  Object.assign(state, { runId: null, caseData: null, phase: 'idle', final: null, score: 0, solved: 0, caseIndex: 0, error: '', screen: 'intro' })
  refreshHiScore()
}

export function openBoard() { state.prev = state.screen; state.screen = 'board' }
export function closeBoard() { state.screen = state.prev }
export function toggleMute() { state.muted = !state.muted; setMuted(state.muted) }
