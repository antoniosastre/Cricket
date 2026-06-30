// Cliente HTTP para la API. Gestiona el token de acceso y el refresco
// automatico cuando expira (401).
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
  auth.access = ''
  auth.refresh = ''
  auth.user = null
  localStorage.removeItem('access')
  localStorage.removeItem('refresh')
  localStorage.removeItem('user')
}

async function tryRefresh() {
  if (!auth.refresh) return false
  const res = await fetch(`${API}/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh: auth.refresh }),
  })
  if (!res.ok) return false
  setSession(await res.json())
  return true
}

export async function api(path, { method = 'GET', body, raw = false, retry = true } = {}) {
  const headers = {}
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth.access) headers['Authorization'] = `Bearer ${auth.access}`

  const res = await fetch(`${API}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (res.status === 401 && retry && (await tryRefresh())) {
    return api(path, { method, body, raw, retry: false })
  }
  if (res.status === 401) {
    logout()
    throw new Error('Sesion expirada')
  }
  if (!res.ok) {
    let msg = 'Error'
    try { msg = (await res.json()).error || msg } catch {}
    throw new Error(msg)
  }
  if (raw) return res
  if (res.status === 204) return null
  return res.json()
}

// Descarga un fichero (CSV) respetando la autenticacion.
export async function download(path, filename) {
  const res = await api(path, { raw: true })
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
