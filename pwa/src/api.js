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

let renovacion
async function renovar(refresh) {
  const res = await fetch(`${API}/auth/refresh`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh }),
  })
  if (res.status === 401 || res.status === 403) return false
  if (!res.ok) throw new Error('No se pudo renovar la sesión. Se reintentará la subida.')
  const data = await res.json()
  if (auth.refresh === refresh) setSession(data)
  return true
}

function tryRefresh() {
  const refresh = auth.refresh
  if (!refresh) return false
  if (renovacion?.token !== refresh) {
    const promesa = renovar(refresh).finally(() => {
      if (renovacion?.token === refresh) renovacion = undefined
    })
    renovacion = { token: refresh, promesa }
  }
  return renovacion.promesa
}

export async function api(path, { method = 'GET', body, retry = true, userId = auth.user?.id } = {}) {
  if (userId !== auth.user?.id) throw new Error('Ha cambiado la sesión')
  const access = auth.access
  const headers = {}
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth.access) headers['Authorization'] = `Bearer ${auth.access}`

  const res = await fetch(`${API}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  if (res.status === 401 && userId && auth.user?.id === userId && !path.startsWith('/auth/')) {
    if (retry && (auth.access !== access || await tryRefresh())) {
      return api(path, { method, body, retry: false, userId })
    }
    if (auth.user?.id === userId && auth.access === access) logout()
    throw new Error('La sesión ha caducado. Vuelve a entrar.')
  }
  if (!res.ok) {
    let msg = 'Error'
    try { msg = (await res.json()).error || msg } catch {}
    throw new Error(msg)
  }
  return res.status === 204 ? null : res.json()
}
