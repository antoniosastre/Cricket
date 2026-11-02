import 'fake-indexeddb/auto'
import { beforeAll, beforeEach, expect, test, vi } from 'vitest'

let db, sync, cliente
const sesion = id => ({ access: 'acceso-' + id, refresh: 'renovar-' + id, user: { id } })

beforeAll(async () => {
  const valores = new Map()
  vi.stubGlobal('localStorage', {
    getItem: k => valores.get(k) ?? null, setItem: (k, v) => valores.set(k, v), removeItem: k => valores.delete(k),
  })
  vi.stubGlobal('navigator', { onLine: true })
  db = await import('../src/db')
  cliente = await import('../src/api')
  sync = await import('../src/sync')
})
beforeEach(() => { vi.stubGlobal('fetch', vi.fn()); cliente.logout() })

test('un dispositivo compartido solo sube los registros de quien ha entrado', async () => {
  await db.encolar(1, { jornadas: [{ uuid: 'j1' }] })
  await db.encolar(2, { materiales: [{ uuid: 'm2' }] })
  cliente.setSession(sesion(1))
  fetch.mockImplementation(async () => Response.json({ ok: true }))
  await sync.flush()
  const [url, peticion] = fetch.mock.calls[0]
  expect(url).toMatch(/\/sync$/)
  expect(peticion.headers.Authorization).toBe('Bearer acceso-1')
  expect(JSON.parse(peticion.body).materiales).toHaveLength(0)
  expect(await db.pendingCount(1)).toBe(0)
  expect(await db.pendingCount(2)).toBe(1)
  cliente.setSession(sesion(2))
  await sync.flush()
  expect(await db.pendingCount(2)).toBe(0)
})

test('una confirmación posterior al envío de un heartbeat sobrevive al acuse antiguo', async () => {
  await db.encolar(3, { jornadas: [{ uuid: 'mismo', estado: 'en_curso' }] })
  const antiguo = await db.pending(3)
  await db.encolar(3, { jornadas: [{ uuid: 'mismo', estado: 'confirmada' }] })
  await db.clearQueued(antiguo)
  expect((await db.pending(3)).jornadas[0].estado).toBe('confirmada')
})

test('un refresh rechazado cierra el acceso y conserva el trabajo hasta identificarse', async () => {
  cliente.setSession(sesion(4))
  await db.encolar(4, { materiales: [{ uuid: 'm4' }] })
  fetch.mockImplementation(() => Promise.resolve(Response.json({}, { status: 401 })))
  await sync.flush()
  expect(cliente.auth.user).toBeNull()
  expect(await db.pendingCount(4)).toBe(1)
})

test('un fallo transitorio de red permite reintentar sin cerrar sesión', async () => {
  cliente.setSession(sesion(5))
  await db.encolar(5, { jornadas: [{ uuid: 'j5' }] })
  fetch.mockResolvedValueOnce(Response.json({}, { status: 401 })).mockRejectedValueOnce(new Error('Sin red'))
  await sync.flush()
  expect(sync.syncState.error).toBe('Sin red')
  expect(cliente.auth.user.id).toBe(5)
  expect(await db.pendingCount(5)).toBe(1)
})

test('un cambio de cuenta durante el refresh no reenvía el lote con el token del siguiente trabajador', async () => {
  cliente.setSession(sesion(6))
  await db.encolar(6, { jornadas: [{ uuid: 'j6' }] })
  fetch.mockResolvedValueOnce(Response.json({}, { status: 401 })).mockImplementationOnce(async () => {
    cliente.setSession(sesion(7))
    return Response.json(sesion(6))
  })
  await sync.flush()
  expect(cliente.auth.user.id).toBe(7)
  expect(fetch).toHaveBeenCalledTimes(2)
  expect(await db.pendingCount(6)).toBe(1)
})
