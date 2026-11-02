import { test, before } from 'node:test'
import assert from 'node:assert/strict'
import {
  llamar, entrarAdmin, crearTrabajador, crearInstalacion,
  jornada, material, sync, pendientes, csvLote,
} from './ayuda.mjs'

let admin, trabajador

before(async () => {
  admin = await entrarAdmin()
  trabajador = await crearTrabajador(admin, { tarifa: 25 })
})

// Prepara una instalacion con 2 h de trabajo y 2 unidades a 8,50 EUR.
async function preparar() {
  const inst = await crearInstalacion(admin)
  await sync(trabajador, {
    jornadas: [jornada(inst, '2026-11-02T08:00:00Z', '2026-11-02T10:00:00Z')],
    materiales: [material(inst, { cantidad: 2, precio_unit: 8.5 })],
  })
  const p = await pendientes(admin, inst)
  return { inst, idHora: p.horas[0].id, idMat: p.materiales[0].id }
}

async function facturar(jornadas, materiales) {
  return llamar('/facturacion', { method: 'POST', token: admin.access, body: { jornadas, materiales } })
}

test('facturar saca las lineas de pendientes y el CSV lleva los importes', async () => {
  const { inst, idHora, idMat } = await preparar()
  const lote = await facturar([idHora], [idMat])
  assert.equal(lote.status, 201)

  const p = await pendientes(admin, inst)
  assert.equal(p.horas.length + p.materiales.length, 0)

  const filas = await csvLote(admin, lote.json.lote_id)
  const horas = filas.find(f => f[0] === 'Horas')
  const mat = filas.find(f => f[0] === 'Material')
  assert.deepEqual([horas[7], horas[9], horas[10]], ['2', '25', '50'])
  assert.deepEqual([mat[7], mat[9], mat[10]], ['2', '8,5', '17'])
})

test('el lote aparece en el archivado y revertirlo devuelve las lineas a pendientes', async () => {
  const { inst, idHora, idMat } = await preparar()
  const lote = (await facturar([idHora], [idMat])).json.lote_id

  const archivado = (await llamar('/archivado', { token: admin.access })).json
  const fila = archivado.find(l => l.id === lote)
  assert.equal(fila.total_horas, 2)
  assert.equal(fila.total_mat, 1)

  assert.equal((await llamar(`/facturacion/${lote}/revertir`, { method: 'POST', token: admin.access })).status, 200)
  const p = await pendientes(admin, inst)
  assert.equal(p.horas.length, 1)
  assert.equal(p.materiales.length, 1)
})

test('una linea facturada no se edita (409) ni se borra', async () => {
  const { idHora, idMat } = await preparar()
  const lote = (await facturar([idHora], [idMat])).json.lote_id

  assert.equal((await llamar(`/jornadas/${idHora}`, { method: 'PATCH', token: admin.access, body: { fin: '2026-11-02T11:00:00Z' } })).status, 409)
  assert.equal((await llamar(`/materiales/${idMat}`, { method: 'PATCH', token: admin.access, body: { cantidad: 9 } })).status, 409)

  await llamar(`/jornadas/${idHora}`, { method: 'DELETE', token: admin.access })
  await llamar(`/materiales/${idMat}`, { method: 'DELETE', token: admin.access })
  const filas = await csvLote(admin, lote)
  assert.equal(filas.length, 2, 'las dos lineas siguen en el lote')
})

test('editar una jornada pendiente recalcula su duracion', async () => {
  const { inst, idHora } = await preparar()
  const r = await llamar(`/jornadas/${idHora}`, {
    method: 'PATCH', token: admin.access,
    body: { inicio: '2026-11-02 08:00', fin: '2026-11-02 11:30' },
  })
  assert.equal(r.status, 200)
  assert.equal(r.json.duracion_min, 210)
  assert.equal((await pendientes(admin, inst)).horas[0].horas, 3.5)
})
