import { openDB } from 'idb'

const stores = ['outbox_jornadas', 'outbox_materiales']
const dbp = openDB('partes', 2, {
  upgrade(db, oldVersion, _, tx) {
    if (oldVersion < 1) {
      for (const name of stores) db.createObjectStore(name, { keyPath: 'uuid' })
      db.createObjectStore('cache')
    }
    for (const name of stores) tx.objectStore(name).createIndex('usuario', 'usuario_id')
  },
})

// Confirmar una instalación escribe todos sus registros en una sola transacción.
export async function encolar(usuarioId, { jornadas = [], materiales = [] }) {
  if (!usuarioId) throw new Error('Falta el trabajador de la instalación')
  const db = await dbp
  const tx = db.transaction(stores, 'readwrite')
  try {
    for (const [store, filas] of [[stores[0], jornadas], [stores[1], materiales]]) {
      for (const fila of filas) {
        await tx.objectStore(store).put({ ...fila, usuario_id: usuarioId, revision: crypto.randomUUID() })
      }
    }
    await tx.done
  } catch (error) {
    try { tx.abort() } catch { /* la transacción ya se ha cerrado */ }
    await tx.done.catch(() => {})
    throw error
  }
}

export async function pending(usuarioId) {
  if (!usuarioId) return { jornadas: [], materiales: [] }
  const db = await dbp
  const [jornadas, materiales] = await Promise.all(stores.map(s => db.getAllFromIndex(s, 'usuario', usuarioId)))
  return { jornadas, materiales }
}

export async function clearQueued({ jornadas, materiales }) {
  const db = await dbp
  const tx = db.transaction(stores, 'readwrite')
  for (const [nombre, filas] of [[stores[0], jornadas], [stores[1], materiales]]) {
    const store = tx.objectStore(nombre)
    for (const fila of filas) {
      const actual = await store.get(fila.uuid)
      // Un heartbeat antiguo puede terminar después de confirmar ese mismo tramo.
      if (actual?.usuario_id === fila.usuario_id && actual.revision === fila.revision) await store.delete(fila.uuid)
    }
  }
  await tx.done
}

export async function pendingCount(usuarioId) {
  if (!usuarioId) return 0
  const db = await dbp
  const [j, m] = await Promise.all(stores.map(s => db.countFromIndex(s, 'usuario', usuarioId)))
  return j + m
}

export async function cachePut(key, value) {
  await (await dbp).put('cache', JSON.parse(JSON.stringify(value)), key)
}
export async function cacheGet(key) {
  return (await dbp).get('cache', key)
}
