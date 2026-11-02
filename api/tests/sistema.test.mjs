import { test } from 'node:test'
import assert from 'node:assert/strict'
import { llamar } from './ayuda.mjs'

test('salud, estado del administrador y protección de las operaciones de despliegue', async () => {
  const salud = await llamar('/healthz')
  assert.equal(salud.status, 200)
  assert.equal(salud.json.ok, true)
  assert.ok(salud.json.version)
  assert.deepEqual((await llamar('/sistema/estado')).json, { necesita_admin: false })
  for (const ruta of ['/sistema/migrar', '/sistema/admin-inicial']) {
    assert.equal((await llamar(ruta, { method: 'POST' })).status, 403)
  }
})
