// Estado de la sesion de trabajo en curso, persistido en localStorage para
// sobrevivir a recargas/cierres de la app. Una sesion = una instalacion con
// uno o varios tramos de tiempo (segmentos) y una lista de materiales.
import { reactive, watch } from 'vue'

function uuid() {
  return crypto.randomUUID
    ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = (Math.random() * 16) | 0
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16)
      })
}

const saved = JSON.parse(localStorage.getItem('session') || 'null')

export const session = reactive(
  saved || {
    instalacion_id: null,
    instalacion_nombre: '',
    segmentos: [], // { uuid, inicio, fin }
    materiales: [], // { uuid, descripcion, cantidad, unidad, precio_unit, origen, catalogo_id, fecha }
  }
)

watch(session, () => localStorage.setItem('session', JSON.stringify(session)), { deep: true })

export function startSession(instalacion) {
  session.instalacion_id = instalacion.id
  session.instalacion_nombre = instalacion.nombre
  session.segmentos = []
  session.materiales = []
}

export function segmentoActivo() {
  return session.segmentos.find(s => !s.fin)
}

export function iniciarTramo() {
  if (segmentoActivo()) return
  session.segmentos.push({ uuid: uuid(), inicio: new Date().toISOString(), fin: null })
}

export function pararTramo() {
  const s = segmentoActivo()
  if (s) s.fin = new Date().toISOString()
}

export function minutosTotales() {
  const now = Date.now()
  return session.segmentos.reduce((acc, s) => {
    const fin = s.fin ? new Date(s.fin).getTime() : now
    return acc + Math.max(0, (fin - new Date(s.inicio).getTime()) / 60000)
  }, 0)
}

export function anadirMaterial(m) {
  session.materiales.push({ uuid: uuid(), fecha: new Date().toISOString().slice(0, 10), ...m })
}

export function quitarMaterial(uuidM) {
  session.materiales = session.materiales.filter(m => m.uuid !== uuidM)
}

export function limpiarSesion() {
  session.instalacion_id = null
  session.instalacion_nombre = ''
  session.segmentos = []
  session.materiales = []
  localStorage.removeItem('session')
}

export { uuid }
