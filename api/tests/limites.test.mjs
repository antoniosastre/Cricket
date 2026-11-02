import { test } from 'node:test'
import assert from 'node:assert/strict'
import { randomUUID } from 'node:crypto'
import { llamar, entrarAdmin, crearTrabajador } from './ayuda.mjs'

test('los intentos se comparten entre id y email y una clave correcta limpia los fallos', async () => {
  const t = await crearTrabajador(await entrarAdmin())
  const entrar = (ident, pin) => llamar('/auth/login-pin', { method: 'POST', body: { usuario: ident, pin },
    headers: { 'X-Forwarded-For': randomUUID() } })
  for (let i = 0; i < 4; i++) assert.equal((await entrar(t.id, '000000')).status, 401)
  assert.equal((await entrar(t.email, t.pin)).status, 200)
  for (let i = 0; i < 5; i++) assert.equal((await entrar(i % 2 ? t.id : t.email, '000000')).status, 401)
  const limite = await entrar(t.email, t.pin)
  assert.equal(limite.status, 429)
  assert.ok(Number(limite.headers.get('retry-after')) > 0)
})

test('el login administrativo limita cuentas inexistentes y el listado de trabajadores es privado', async () => {
  const email = randomUUID() + '@prueba.test'
  for (let i = 0; i < 6; i++) {
    const r = await llamar('/auth/login', { method: 'POST', body: { email, password: 'incorrecta' } })
    assert.equal(r.status, i === 5 ? 429 : 401)
  }
  assert.equal((await llamar('/trabajadores?rol=trabajador&login=1')).status, 401)
})

test('alta y cambio rechazan credenciales demasiado cortas o PIN no numérico', async () => {
  const admin = await entrarAdmin()
  const t = await crearTrabajador(admin)
  for (const datos of [{ pin: '12345' }, { pin: '123ab6' }, { password: 'corta' }]) {
    assert.equal((await llamar('/trabajadores/' + t.id, { method: 'PATCH', token: admin.access, body: datos })).status, 400)
    const body = { rol: datos.password ? 'admin' : 'trabajador', nombre: 'Prueba', email: randomUUID() + '@prueba.test', ...datos }
    assert.equal((await llamar('/trabajadores', { method: 'POST', token: admin.access, body })).status, 400)
  }
})
