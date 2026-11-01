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

test('un error no controlado -> 500 generico, sin detalles', async () => {
  // /trabajos/{id} espera un entero: un texto provoca un TypeError en PHP.
  const r = await llamar('/trabajos/abc', { token: admin.access })
  assert.equal(r.status, 500)
  assert.deepEqual(r.json, { error: 'Error interno' })
})
