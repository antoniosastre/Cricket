// Cola de sincronizacion: envia a /sync lo pendiente en IndexedDB.
// Idempotente (upsert por uuid en el servidor), asi que reintentar es seguro.
import { reactive } from 'vue'
import { api } from './api'
import { pending, clearQueued, pendingCount } from './db'

export const syncState = reactive({ pendientes: 0, online: navigator.onLine, sincronizando: false })

export async function refreshCount() {
  syncState.pendientes = await pendingCount()
}

export async function flush() {
  if (!navigator.onLine || syncState.sincronizando) return
  const { jornadas, materiales } = await pending()
  if (!jornadas.length && !materiales.length) return
  syncState.sincronizando = true
  try {
    await api('/sync', { method: 'POST', body: { jornadas, materiales } })
    await clearQueued(jornadas.map(j => j.uuid), materiales.map(m => m.uuid))
  } catch (e) {
    // Se reintentara en el proximo evento online / arranque.
  } finally {
    syncState.sincronizando = false
    await refreshCount()
  }
}

export function initSync() {
  window.addEventListener('online', () => { syncState.online = true; flush() })
  window.addEventListener('offline', () => { syncState.online = false })
  refreshCount()
  flush()
  // Reintento periodico por si la conexion es intermitente.
  setInterval(flush, 30000)
}
