import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import { randomUUID } from 'node:crypto'
import {
  llamar, entrarAdmin, crearTrabajador, crearInstalacion,
  jornada, material, sync, pendientes, csvLote,
} from './ayuda.mjs'

let admin, juan, marta

before(async () => {
  admin = await entrarAdmin()
  juan = await crearTrabajador(admin)
  marta = await crearTrabajador(admin)
})

test('reenviar la misma jornada no la duplica', async () => {
  const inst = await crearInstalacion(admin)
  const j = jornada(inst, '2026-11-03T08:00:00Z', '2026-11-03T09:00:00Z')
  assert.equal((await sync(juan, { jornadas: [j] })).status, 200)
  assert.equal((await sync(juan, { jornadas: [j] })).status, 200)
  const p = await pendientes(admin, inst)
  assert.equal(p.horas.length, 1)
  assert.equal(p.horas[0].horas, 1)
})

test('el trabajador puede actualizar su propia jornada pendiente', async () => {
  const inst = await crearInstalacion(admin)
  const j = jornada(inst, '2026-11-03T08:00:00Z', '2026-11-03T09:00:00Z')
  await sync(juan, { jornadas: [j] })
  await sync(juan, { jornadas: [{ ...j, fin: '2026-11-03T09:30:00Z' }] })
  assert.equal((await pendientes(admin, inst)).horas[0].horas, 1.5)
})

test('otro trabajador no puede cambiar una jornada ajena', async () => {
  const inst = await crearInstalacion(admin)
  const j = jornada(inst, '2026-11-03T08:00:00Z', '2026-11-03T09:00:00Z')
  await sync(juan, { jornadas: [j] })
  const r = await sync(marta, { jornadas: [{ ...j, inicio: '2026-11-03T06:00:00Z', fin: '2026-11-03T14:00:00Z' }] })
  assert.equal(r.status, 200)
  const p = await pendientes(admin, inst)
  assert.equal(p.horas.length, 1)
  assert.equal(p.horas[0].horas, 1)
  assert.equal(p.horas[0].trabajador, (await llamar('/auth/me', { token: juan.access })).json.nombre)
})

test('un reenvio no cambia horas ni materiales ya facturados', async () => {
  const inst = await crearInstalacion(admin)
  const j = jornada(inst, '2026-11-02T08:00:00Z', '2026-11-02T10:00:00Z')
  const m = material(inst, { cantidad: 2 })
  await sync(juan, { jornadas: [j], materiales: [m] })
  const p = await pendientes(admin, inst)
  const lote = await llamar('/facturacion', {
    method: 'POST', token: admin.access,
    body: { jornadas: [p.horas[0].id], materiales: [p.materiales[0].id] },
  })
  assert.equal(lote.status, 201)

  await sync(juan, { jornadas: [{ ...j, fin: '2026-11-02T12:00:00Z' }], materiales: [{ ...m, cantidad: 5 }] })

  const filas = await csvLote(admin, lote.json.lote_id)
  const horas = filas.find(f => f[0] === 'Horas')
  const mat = filas.find(f => f[0] === 'Material')
  assert.equal(horas[7], '2', 'siguen siendo 2 h')
  assert.equal(mat[7], '2', 'siguen siendo 2 unidades')
})

test('los registros incompletos se ignoran sin error', async () => {
  const inst = await crearInstalacion(admin)
  const r = await sync(juan, {
    jornadas: [{ instalacion_id: inst, inicio: '2026-11-03T08:00:00Z' }, { uuid: randomUUID(), inicio: '2026-11-03T08:00:00Z' }],
    materiales: [{ uuid: randomUUID(), instalacion_id: inst, descripcion: '  ' }],
  })
  assert.equal(r.status, 200)
  const p = await pendientes(admin, inst)
  assert.equal(p.horas.length + p.materiales.length, 0)
})

test('un fallo de la base da 500 sin detalles internos', async () => {
  const r = await sync(juan, { jornadas: [jornada(999999, '2026-11-04T08:00:00Z', null, 'en_curso')] })
  assert.equal(r.status, 500)
  assert.deepEqual(r.json, { error: 'Error de sincronizacion' })
})

test('el dashboard refleja el temporizador encendido y apagado', async () => {
  const inst = await crearInstalacion(admin)
  const j = jornada(inst, new Date().toISOString(), null, 'en_curso')
  await sync(marta, { jornadas: [j] })
  let fila = (await llamar('/dashboard/estado', { token: admin.access })).json.find(t => t.usuario_id === marta.id)
  assert.equal(fila.activo, true)
  assert.equal(fila.instalacion_id, inst)

  await sync(marta, { jornadas: [{ ...j, fin: new Date().toISOString(), estado: 'confirmada' }] })
  fila = (await llamar('/dashboard/estado', { token: admin.access })).json.find(t => t.usuario_id === marta.id)
  assert.equal(fila.activo, false)
})
