export const LOGO = import.meta.env.BASE_URL + 'persaka-logo.png'

export const pad = (n) => String(n).padStart(6, '0')

/** 83400 -> "01:23.4" (total run time). */
export function formatTime(ms) {
  const t = Math.max(0, Math.round(ms / 100))
  const m = String(Math.floor(t / 600)).padStart(2, '0')
  const s = String(Math.floor((t % 600) / 10)).padStart(2, '0')
  return `${m}:${s}.${t % 10}`
}

/** Countdown clock: rounds up so it reads 00:00 only when time is really up. */
export function clock(ms) {
  const s = Math.max(0, Math.ceil(ms / 1000))
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}
