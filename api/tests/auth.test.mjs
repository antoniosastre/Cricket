import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import { llamar, entrarAdmin, crearTrabajador } from './ayuda.mjs'

let admin, trabajador

before(async () => {
  admin = await entrarAdmin()
  trabajador = await crearTrabajador(admin)
})

test('login de admin: contrasena incorrecta -> 401', async () => {
  const r = await llamar('/auth/login', { method: 'POST', body: { email: 'admin@empresa.com', password: 'otra' } })
  assert.equal(r.status, 401)
})

test('login por PIN con id y con email; PIN incorrecto -> 401', async () => {
  const porId = await llamar('/auth/login-pin', { method: 'POST', body: { usuario: trabajador.id, pin: trabajador.pin } })
  const porEmail = await llamar('/auth/login-pin', { method: 'POST', body: { usuario: trabajador.email, pin: trabajador.pin } })
  const malo = await llamar('/auth/login-pin', { method: 'POST', body: { usuario: trabajador.id, pin: '000000' } })
  assert.equal(porId.status, 200)
  assert.equal(porEmail.status, 200)
  assert.equal(malo.status, 401)
})

test('el access token abre las rutas; el refresh token no', async () => {
  assert.equal((await llamar('/auth/me', { token: admin.access })).status, 200)
  assert.equal((await llamar('/auth/me', { token: admin.refresh })).status, 401)
  assert.equal((await llamar('/auth/me')).status, 401)
})

test('/auth/refresh acepta el refresh token y rechaza el access token', async () => {
  const ok = await llamar('/auth/refresh', { method: 'POST', body: { refresh: admin.refresh } })
  assert.equal(ok.status, 200)
  assert.ok(ok.json.access && ok.json.refresh)
  const malo = await llamar('/auth/refresh', { method: 'POST', body: { refresh: admin.access } })
  assert.equal(malo.status, 401)
})

test('un trabajador no entra en las rutas del portal -> 403', async () => {
  assert.equal((await llamar('/trabajos', { token: trabajador.access })).status, 403)
  assert.equal((await llamar('/sync', { method: 'POST', token: admin.access, body: {} })).status, 403)
})
