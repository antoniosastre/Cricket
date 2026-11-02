// Utilidades de las pruebas de la API: peticiones, sesiones y datos propios
// de cada prueba (trabajadores e instalaciones nuevos), para que las pruebas
// no dependan unas de otras ni del orden en que se ejecutan.
import { randomUUID } from 'node:crypto'

export const API = (process.env.CRICKET_API || 'http://127.0.0.1:8080').replace(/\/$/, '')

// Administrador de la semilla de desarrollo (migrate.php).
const ADMIN = { email: 'admin@empresa.com', password: 'admin-demo-2026' }

export async function llamar(path, { method = 'GET', body, token, headers = {} } = {}) {
  const h = { ...headers }
  if (body !== undefined) h['Content-Type'] = 'application/json'
  if (token) h.Authorization = `Bearer ${token}`
  const res = await fetch(API + path, {
    method,
    headers: h,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  const texto = await res.text()
  let json = null
  try { json = JSON.parse(texto) } catch {}
  return { status: res.status, texto, json, headers: res.headers }
}

export async function entrarAdmin() {
  const r = await llamar('/auth/login', { method: 'POST', body: ADMIN })
  if (r.status !== 200) throw new Error(`login admin: HTTP ${r.status} ${r.texto}`)
  return r.json
}

// Crea un trabajador nuevo con su PIN y devuelve su id y su sesion.
export async function crearTrabajador(admin, { pin = '246813', tarifa = 25 } = {}) {
  const email = `t-${randomUUID()}@prueba.test`
  const alta = await llamar('/trabajadores', {
    method: 'POST', token: admin.access,
    body: { nombre: `Trabajador ${email.slice(2, 8)}`, email, rol: 'trabajador', pin, tarifa_hora: tarifa },
  })
  if (alta.status !== 201) throw new Error(`alta trabajador: HTTP ${alta.status} ${alta.texto}`)
  const id = alta.json.id
  const s = await llamar('/auth/login-pin', { method: 'POST', body: { usuario: id, pin } })
  if (s.status !== 200) throw new Error(`login trabajador: HTTP ${s.status} ${s.texto}`)
  return { id, email, pin, ...s.json }
}

export async function crearInstalacion(admin) {
  const r = await llamar('/instalaciones', { method: 'POST', token: admin.access, body: { nombre: `Prueba ${randomUUID().slice(0, 8)}` } })
  if (r.status !== 201) throw new Error(`alta instalacion: HTTP ${r.status} ${r.texto}`)
  return r.json.id
}

export function jornada(instalacionId, inicio, fin, estado = 'confirmada') {
  return { uuid: randomUUID(), instalacion_id: instalacionId, inicio, fin, estado }
}

export function material(instalacionId, datos = {}) {
  return { uuid: randomUUID(), instalacion_id: instalacionId, descripcion: 'Magnetotermico 16A', cantidad: 2, unidad: 'ud', precio_unit: 8.5, origen: 'empresa', ...datos }
}

export async function sync(trabajador, { jornadas = [], materiales = [] }) {
  return llamar('/sync', { method: 'POST', token: trabajador.access, body: { jornadas, materiales } })
}

// Lineas pendientes de una instalacion (vista Trabajos del portal).
export async function pendientes(admin, instalacionId) {
  const r = await llamar(`/trabajos/${instalacionId}`, { token: admin.access })
  if (r.status !== 200) throw new Error(`trabajos: HTTP ${r.status} ${r.texto}`)
  return r.json
}

// CSV de un lote como filas de celdas (sin BOM ni cabecera).
export async function csvLote(admin, loteId) {
  const r = await llamar(`/facturacion/${loteId}/export.csv`, { token: admin.access })
  if (r.status !== 200) throw new Error(`csv: HTTP ${r.status} ${r.texto}`)
  const filas = r.texto.replace(/^﻿/, '').trim().split('\r\n').map(l => l.split(';'))
  return filas.slice(1)
}
