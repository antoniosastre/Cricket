import { test } from 'node:test'
import assert from 'node:assert/strict'
import { randomUUID } from 'node:crypto'
import { llamar, entrarAdmin, crearTrabajador, crearInstalacion, jornada, sync, pendientes } from './ayuda.mjs'

test('cerrar o descartar una jornada sin confirmar prevalece ante un reenvío del móvil', async () => {
  const admin = await entrarAdmin()
  const t = await crearTrabajador(admin)
  const inst = await crearInstalacion(admin)
  for (const descartar of [false, true]) {
    const j = jornada(inst, '2026-05-04T08:00:00', null, 'en_curso')
    await sync(t, { jornadas: [j] })
    const filas = (await llamar('/jornadas/abiertas', { token: admin.access })).json
    const fila = filas.find(f => f.trabajador === t.user.nombre)
    assert.ok(fila)
    const r = await llamar('/jornadas/' + fila.id, { method: descartar ? 'DELETE' : 'PATCH',
      body: descartar ? undefined : { fin: '2026-05-04T10:00:00' }, token: admin.access })
    assert.equal(r.status, 200)
    await sync(t, { jornadas: [j] })
    assert.ok(!(await llamar('/jornadas/abiertas', { token: admin.access })).json.some(f => f.id === fila.id))
    const p = await pendientes(admin, inst)
    assert.equal(p.horas.length, 1)
    assert.equal(p.horas[0].horas, 2)
  }
})

test('los cambios parciales conservan el resto del registro y protegen los accesos administrativos', async () => {
  const admin = await entrarAdmin()
  const t = await crearTrabajador(admin)
  const patch = (ruta, body) => llamar(ruta, { token: admin.access, method: 'PATCH', body })
  assert.equal((await patch('/trabajadores/' + t.id, { tarifa_hora: 33 })).status, 200)
  const fila = (await llamar('/trabajadores', { token: admin.access })).json.find(f => f.id === t.id)
  assert.equal(fila.email, t.email)
  assert.equal(fila.nombre, t.user.nombre)
  assert.equal((await patch('/trabajadores/' + t.id, { email: admin.user.email })).status, 409)
  assert.equal((await patch('/trabajadores/' + admin.user.id, { rol: 'trabajador' })).status, 409)
  assert.equal((await llamar('/trabajadores/' + admin.user.id, { token: admin.access, method: 'DELETE' })).status, 409)
  for (const [ruta, nombre, body, cambio] of [
    ['clientes', 'nombre', { nombre: 'Cliente ' + randomUUID(), direccion: 'Mayor' }, { email: 'uno@prueba.test' }],
    ['instalaciones', 'nombre', { nombre: 'Obra ' + randomUUID(), direccion: 'Norte' }, { descripcion: 'Planta baja' }],
    ['catalogo', 'descripcion', { descripcion: 'Material ' + randomUUID(), unidad: 'm' }, { precio: 7 }],
  ]) {
    const alta = await llamar('/' + ruta, { token: admin.access, method: 'POST', body })
    const id = alta.json.id
    assert.equal((await patch('/' + ruta + '/' + id, cambio)).status, 200)
    const editado = (await llamar('/' + ruta, { token: admin.access })).json.find(f => f.id === id)
    assert.equal(editado[nombre], body[nombre])
    assert.equal((await patch('/' + ruta + '/' + id, { [nombre]: ' ' })).status, 400)
    if (ruta === 'clientes') {
      assert.equal((await llamar('/instalaciones', { token: admin.access, method: 'POST', body: { nombre: 'Asociada', cliente_id: id } })).status, 201)
      assert.equal((await llamar('/clientes/' + id, { token: admin.access, method: 'DELETE' })).status, 409)
    }
  }
})
