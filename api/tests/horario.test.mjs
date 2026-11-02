import { test } from 'node:test'
import assert from 'node:assert/strict'
import { entrarAdmin, crearTrabajador, crearInstalacion, jornada, sync, pendientes, llamar, csvLote } from './ayuda.mjs'

test('una jornada UTC se ve y se exporta en hora peninsular tanto en invierno como en verano', async () => {
  const admin = await entrarAdmin()
  const trabajador = await crearTrabajador(admin)
  for (const [dia, hora] of [['2026-01-12', '09'], ['2026-07-12', '10']]) {
    const inst = await crearInstalacion(admin)
    await sync(trabajador, { jornadas: [jornada(inst, dia + 'T08:00:00Z', dia + 'T09:00:00Z')] })
    const [h] = (await pendientes(admin, inst)).horas
    assert.equal(h.fecha, dia + ' ' + hora + ':00:00')
    assert.equal(h.horas, 1)
    const lote = await llamar('/facturacion', { method: 'POST', token: admin.access, body: { jornadas: [h.id] } })
    assert.equal((await csvLote(admin, lote.json.lote_id))[0][3], h.fecha)
  }
})

test('el cambio de hora de otoño conserva la duración real de un tramo UTC', async () => {
  const admin = await entrarAdmin()
  const t = await crearTrabajador(admin)
  const inst = await crearInstalacion(admin)
  await sync(t, { jornadas: [jornada(inst, '2026-10-25T00:30:00Z', '2026-10-25T01:30:00Z')] })
  assert.equal((await pendientes(admin, inst)).horas[0].horas, 1)
})
