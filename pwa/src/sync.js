import { reactive, watch } from 'vue'
import { api, auth } from './api'
import { pending, clearQueued, pendingCount } from './db'

export const syncState = reactive({ pendientes: 0, online: navigator.onLine, sincronizando: false, error: '' })

export async function refreshCount() {
  const id = auth.user?.id
  const count = await pendingCount(id)
  if (auth.user?.id === id) syncState.pendientes = count
}

export async function flush() {
  const id = auth.user?.id
  if (!id || !navigator.onLine || syncState.sincronizando) return
  syncState.sincronizando = true
  try {
    const filas = await pending(id)
    if (auth.user?.id !== id || !(filas.jornadas.length || filas.materiales.length)) return
    await api('/sync', { method: 'POST', body: filas, userId: id })
    await clearQueued(filas)
    if (auth.user?.id === id) syncState.error = ''
  } catch (e) {
    if (auth.user?.id === id) syncState.error = e.message || 'No se pudo sincronizar'
  } finally {
    syncState.sincronizando = false
    await refreshCount()
    if (auth.user?.id && auth.user.id !== id) void flush()
  }
}

export function initSync() {
  window.addEventListener('online', () => { syncState.online = true; flush() })
  window.addEventListener('offline', () => { syncState.online = false })
  watch(() => auth.user?.id, () => {
    syncState.error = ''
    syncState.pendientes = 0
    refreshCount()
    flush()
  }, { immediate: true })
  setInterval(flush, 30000)
}
