import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import { llamar, entrarAdmin } from './ayuda.mjs'

let admin

before(async () => {
  admin = await entrarAdmin()
})

test('una ruta desconocida -> 404', async () => {
  const r = await llamar('/no-existe', { token: admin.access })
  assert.equal(r.status, 404)
})

test('un identificador de ruta no numérico devuelve 404', async () => {
  assert.equal((await llamar('/trabajos/abc', { token: admin.access })).status, 404)
})
