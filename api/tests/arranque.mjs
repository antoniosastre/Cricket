// Ejecutar exclusivamente contra una base de ensayo vacía con clave conocida.
import assert from 'node:assert/strict'
import { llamar } from './ayuda.mjs'

const headers = { 'X-Clave-Despliegue': process.env.CRICKET_CLAVE_PRUEBA }
assert.ok(headers['X-Clave-Despliegue']?.length >= 32)
const migrar = () => llamar('/sistema/migrar', { method: 'POST', headers })
assert.ok((await migrar()).json.aplicadas.includes('001_schema.sql'))
assert.deepEqual((await migrar()).json.aplicadas, [])
assert.deepEqual((await llamar('/sistema/estado')).json, { necesita_admin: true })
const body = { nombre: 'Inicio', email: 'inicio@prueba.test', password: 'solo-para-esta-prueba' }
assert.equal((await llamar('/sistema/admin-inicial', { method: 'POST', body })).status, 403)
const crear = () => llamar('/sistema/admin-inicial', { method: 'POST', headers, body })
assert.deepEqual((await Promise.all([crear(), crear()])).map(r => r.status).sort(), [201, 409])
assert.deepEqual((await llamar('/sistema/estado')).json, { necesita_admin: false })
assert.equal((await llamar('/auth/login', { method: 'POST', body })).status, 200)
console.log('Arranque limpio, migración repetida y administrador único verificados.')
