// Cliente HTTP de la PWA. Igual que el del portal pero con login por PIN.
import { reactive } from 'vue'

const API = import.meta.env.VITE_API_URL || '/api'

export const auth = reactive({
  access: localStorage.getItem('access') || '',
  refresh: localStorage.getItem('refresh') || '',
  user: JSON.parse(localStorage.getItem('user') || 'null'),
})

export function setSession(data) {
  auth.access = data.access
  auth.refresh = data.refresh
  auth.user = data.user
  localStorage.setItem('access', data.access)
  localStorage.setItem('refresh', data.refresh)
  localStorage.setItem('user', JSON.stringify(data.user))
}

export function logout() {
  auth.access = auth.refresh = ''
  auth.user = null
  localStorage.removeItem('access')
  localStorage.removeItem('refresh')
  localStorage.removeItem('user')
}

async function tryRefresh() {
  if (!auth.refresh) return false
  try {
    const res = await fetch(`${API}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh: auth.refresh }),
    })
    if (!res.ok) return false
    setSession(await res.json())
    return true
  } catch {
    return false
  }
}

export async function api(path, { method = 'GET', body, retry = true } = {}) {
  const headers = {}
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth.access) headers['Authorization'] = `Bearer ${auth.access}`

  const res = await fetch(`${API}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  if (res.status === 401 && retry && (await tryRefresh())) {
    return api(path, { method, body, retry: false })
  }
  if (!res.ok) {
    let msg = 'Error'
    try { msg = (await res.json()).error || msg } catch {}
    throw new Error(msg)
  }
  return res.status === 204 ? null : res.json()
}
