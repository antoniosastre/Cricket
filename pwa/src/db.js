// Almacenamiento local (IndexedDB) para la PWA offline-first.
// - cache: copias de instalaciones y catalogo para usar sin conexion.
// - outbox: cola de jornadas y materiales pendientes de sincronizar.
import { openDB } from 'idb'

const dbp = openDB('partes', 1, {
  upgrade(db) {
    db.createObjectStore('outbox_jornadas', { keyPath: 'uuid' })
    db.createObjectStore('outbox_materiales', { keyPath: 'uuid' })
    db.createObjectStore('cache') // clave libre: 'instalaciones', 'catalogo'
  },
})

export async function queueJornada(j) {
  ;(await dbp).put('outbox_jornadas', j)
}
export async function queueMaterial(m) {
  ;(await dbp).put('outbox_materiales', m)
}
export async function pending() {
  const db = await dbp
  return {
    jornadas: await db.getAll('outbox_jornadas'),
    materiales: await db.getAll('outbox_materiales'),
  }
}
export async function clearQueued(jornadaUuids, materialUuids) {
  const db = await dbp
  const tx = db.transaction(['outbox_jornadas', 'outbox_materiales'], 'readwrite')
  for (const u of jornadaUuids) tx.objectStore('outbox_jornadas').delete(u)
  for (const u of materialUuids) tx.objectStore('outbox_materiales').delete(u)
  await tx.done
}
export async function pendingCount() {
  const db = await dbp
  return (await db.count('outbox_jornadas')) + (await db.count('outbox_materiales'))
}

export async function cachePut(key, value) {
  // Clon plano: IndexedDB no puede serializar los proxies reactivos de Vue.
  const plain = JSON.parse(JSON.stringify(value))
  ;(await dbp).put('cache', plain, key)
}
export async function cacheGet(key) {
  return (await dbp).get('cache', key)
}
