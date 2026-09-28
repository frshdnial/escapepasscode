const BASE = import.meta.env.VITE_API_BASE || '/api'

export class ApiError extends Error {
  constructor(status, message) {
    super(message)
    this.status = status
  }
}

async function request(path, options = {}) {
  let res
  try {
    res = await fetch(BASE + path, {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      ...options,
    })
  } catch {
    throw new ApiError(0, 'Cannot reach the case server. Check that the backend is running.')
  }
  const data = await res.json().catch(() => ({}))
  if (!res.ok) throw new ApiError(res.status, data.error || 'Something went wrong. Try again.')
  return data
}

const post = (path, body) => request(path, { method: 'POST', body: body ? JSON.stringify(body) : undefined })

export const api = {
  config: () => request('/config'),
  leaderboard: (limit = 10, run) => request(`/leaderboard?limit=${limit}` + (run ? `&run=${run}` : '')),
  createRun: (name) => post('/runs', { name }),
  startCase: (id) => post(`/runs/${id}/start`),
  guess: (id, code) => post(`/runs/${id}/guess`, { code }),
  timeout: (id) => post(`/runs/${id}/timeout`),
}
