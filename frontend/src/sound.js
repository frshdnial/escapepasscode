// Tiny WebAudio arcade beeps. No audio files needed.
const KEY = 'persaka:muted'
let ctx = null

export function loadMuted() {
  try { return localStorage.getItem(KEY) === '1' } catch { return false }
}
let muted = loadMuted()

export function setMuted(v) {
  muted = v
  try { localStorage.setItem(KEY, v ? '1' : '0') } catch { /* private mode */ }
}

function note(freq, at, dur, type = 'square', vol = 0.04) {
  if (muted) return
  try {
    ctx ??= new (window.AudioContext || window.webkitAudioContext)()
    if (ctx.state === 'suspended') ctx.resume()
    const t = ctx.currentTime + at
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = type
    osc.frequency.setValueAtTime(freq, t)
    gain.gain.setValueAtTime(vol, t)
    gain.gain.exponentialRampToValueAtTime(0.0001, t + dur)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start(t)
    osc.stop(t + dur)
  } catch { /* audio unavailable */ }
}

export const sfx = {
  key: () => note(880, 0, 0.04),
  tick: () => note(1200, 0, 0.05, 'square', 0.03),
  wrong: () => { note(180, 0, 0.18, 'sawtooth', 0.06); note(120, 0.16, 0.25, 'sawtooth', 0.06) },
  correct: () => [523, 659, 784, 1047].forEach((f, i) => note(f, i * 0.08, 0.12)),
  win: () => [523, 659, 784, 1047, 784, 1047, 1319].forEach((f, i) => note(f, i * 0.11, 0.16)),
  lose: () => [392, 330, 262, 196].forEach((f, i) => note(f, i * 0.18, 0.22, 'triangle', 0.07)),
}
