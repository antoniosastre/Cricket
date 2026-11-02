import { test } from 'node:test'
import assert from 'node:assert/strict'
import { entrarAdmin, crearTrabajador, crearInstalacion, jornada, material, sync, pendientes, llamar, csvLote } from './ayuda.mjs'

test('facturar congela la tarifa y la reversión permite tomar la nueva', async () => {
  const admin = await entrarAdmin()
  const trabajador = await crearTrabajador(admin, { tarifa: 25 })
  const inst = await crearInstalacion(admin)
  await sync(trabajador, { jornadas: [jornada(inst, '2026-01-12T10:00:00Z', '2026-01-12T12:00:00Z')] })
  const [h] = (await pendientes(admin, inst)).horas
  assert.equal(h.importe, 50)
  const facturar = () => llamar('/facturacion', { method: 'POST', token: admin.access, body: { jornadas: [h.id] } })
  const lote = (await facturar()).json.lote_id
  await llamar('/trabajadores/' + trabajador.id, { method: 'PATCH', token: admin.access, body: {
    nombre: trabajador.user.nombre, email: trabajador.email, rol: 'trabajador', tarifa_hora: 33,
  } })
  assert.equal((await csvLote(admin, lote))[0][10], '50')
  assert.equal((await llamar('/archivado', { token: admin.access })).json.find(l => l.id === lote).total_euros, 50)
  assert.equal((await facturar()).status, 409)
  await llamar('/facturacion/' + lote + '/revertir', { method: 'POST', token: admin.access })
  const nuevo = (await facturar()).json.lote_id
  assert.equal((await csvLote(admin, nuevo))[0][10], '66')
})

test('los textos de las exportaciones se neutralizan sin cambiar los números negativos', async () => {
  const admin = await entrarAdmin()
  const t = await crearTrabajador(admin)
  const inst = await crearInstalacion(admin)
  for (const prefijo of ['=', '+', '-', '@']) {
    await sync(t, { materiales: [material(inst, { descripcion: prefijo + 'texto', unidad: '@ud', cantidad: -2, precio_unit: 3, origen: 'cliente' })] })
  }
  const lineas = (await pendientes(admin, inst)).materiales
  const lote = (await llamar('/facturacion', { method: 'POST', token: admin.access, body: { materiales: lineas.map(m => m.id) } })).json.lote_id
  for (const f of await csvLote(admin, lote)) {
    assert.equal(f[4][0], "'")
    assert.deepEqual(f.slice(6), ['cliente', '-2', "'@ud", '3', '-6'])
  }
  assert.equal((await llamar('/archivado', { token: admin.access })).json.find(l => l.id === lote).total_euros, -24)
})

test('un lote inexistente se distingue de una selección vacía o ya facturada', async () => {
  const { access: token } = await entrarAdmin()
  assert.equal((await llamar('/facturacion/2147483647/revertir', { token, method: 'POST' })).status, 404)
  assert.equal((await llamar('/facturacion/2147483647/export.csv', { token })).status, 404)
  assert.equal((await llamar('/facturacion', { token, method: 'POST', body: {} })).status, 409)
})
