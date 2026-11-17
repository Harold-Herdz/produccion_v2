// ------------------------------------------------
// APPS SCRIPT - MEZCLAS
// ------------------------------------------------

// ------------------------------------------------
// CONFIGURACION
// ------------------------------------------------
const TOKEN = 'PLASTYPETCO_MEZCLAS';

// ------------------------------------------------
// ENTRADA
// ------------------------------------------------
function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== TOKEN) {
      return json({ ok: false, error: 'Token inválido' });
    }
    if (body.accion !== 'finalizar') {
      return json({ ok: false, error: 'Acción no válida' });
    }

    const ss        = SpreadsheetApp.getActiveSpreadsheet();
    const registros  = ss.getSheetByName('REGISTROS');
    const logs       = obtenerOCrearHojaLogs(ss);
    const cols       = columnasLogs(logs);

    // ------------------------------------------------
    // REGISTROS
    // ------------------------------------------------
    const idSolicitud = body.id_solicitud || '';
    const cache = CacheService.getScriptCache();
    const cacheKey = 'mz_' + idSolicitud;
    const cacheado = idSolicitud ? cache.get(cacheKey) : null;

    const filas = body.registros || [];
    let idSheetAsignado = null;
    if (filas.length > 0) {
      if (cacheado) {
        idSheetAsignado = cacheado; // reusar ID existente
      } else {
        const destino = escribirRegistros(registros, filas);
        SpreadsheetApp.flush();
        idSheetAsignado = String(registros.getRange(destino, 1).getValue() || '') || null;
        if (idSolicitud && idSheetAsignado) cache.put(cacheKey, idSheetAsignado, 21600);
      }
    }

    // ------------------------------------------------
    // LOGS
    // ------------------------------------------------
    const filaLog = buscarFilaLog(logs, cols, body.log.id_dia, body.log.maquina);
    escribirLog(logs, cols, filaLog, body.log);

    SpreadsheetApp.flush();
    return json({ ok: true, registros: filas.length, id_sheet: idSheetAsignado });

  } catch (err) {
    return json({ ok: false, error: String(err) });
  }
}

// ------------------------------------------------
// REGISTROS
// ------------------------------------------------
function escribirRegistros(registros, filas) {
  filas.forEach(function (f) { f[0] = isoADate(f[0]); });
  const destino = ultimaFilaConDatos(registros, 2) + 1;
  registros.getRange(destino, 2, filas.length, filas[0].length).setValues(filas);
  registros.getRange(destino, 2, filas.length, 1).setNumberFormat('dd/mm/yyyy');
  return destino;
}

// ------------------------------------------------
// LOGS
// ------------------------------------------------
function obtenerOCrearHojaLogs(ss) {
  let hoja = ss.getSheetByName('LOGS');
  if (!hoja) hoja = ss.insertSheet('LOGS');
  if (hoja.getLastRow() === 0) {
    hoja.getRange(1, 1, 1, 7)
      .setValues([['ID FECHA', 'FECHA', 'MÁQUINA', 'INICIO', 'FIN', 'REGISTROS', 'ESTADO']])
      .setFontWeight('bold').setHorizontalAlignment('center');
    hoja.setFrozenRows(1);
  }
  return hoja;
}

// Posición de cada columna
function columnasLogs(logs) {
  const enc = logs.getRange(1, 1, 1, Math.max(logs.getLastColumn(), 1)).getValues()[0].map(normalizar);
  const pos = function (nombres) {
    for (let i = 0; i < enc.length; i++) {
      if (nombres.indexOf(enc[i]) !== -1) return i + 1;
    }
    return 0;
  };
  const cols = {
    id:        pos(['IDFECHA', 'ID']),
    fecha:     pos(['FECHA']),
    maquina:   pos(['MAQUINA']),
    inicio:    pos(['INICIO']),
    fin:       pos(['FIN']),
    registros: pos(['REGISTROS']),
    estado:    pos(['ESTADO']),
    total:     enc.length
  };
  if (!cols.id || !cols.fecha || !cols.maquina || !cols.inicio || !cols.fin || !cols.registros) {
    throw new Error('LOGS necesita las columnas ID FECHA, FECHA, MÁQUINA, INICIO, FIN y REGISTROS');
  }
  return cols;
}

// Fila existente del mes
function buscarFilaLog(logs, cols, idDia, maquina) {
  const ultima = logs.getLastRow();
  if (ultima < 2) return 0;
  const datos = logs.getRange(2, 1, ultima - 1, cols.total).getValues();
  for (let i = 0; i < datos.length; i++) {
    if (String(datos[i][cols.id - 1]).trim() === String(idDia).trim() &&
        String(datos[i][cols.maquina - 1]).trim() === String(maquina).trim()) {
      return i + 2;
    }
  }
  return 0;
}

// Crea o actualiza la fila del mes
function escribirLog(logs, cols, filaLog, log) {
  const fin = isoADateHora(log.fin);
  if (filaLog > 0) {
    logs.getRange(filaLog, cols.fin).setValue(fin).setNumberFormat('dd/mm/yyyy hh:mm:ss');
    logs.getRange(filaLog, cols.registros).setValue(log.registros);
    if (cols.estado > 0) logs.getRange(filaLog, cols.estado).setValue('COMPLETADO');
    return;
  }
  const fila = new Array(cols.total).fill('');
  fila[cols.id - 1]        = log.id_dia;
  fila[cols.fecha - 1]     = isoADate(log.fecha);
  fila[cols.maquina - 1]   = log.maquina;
  fila[cols.inicio - 1]    = isoADateHora(log.inicio);
  fila[cols.fin - 1]       = fin;
  fila[cols.registros - 1] = log.registros;
  if (cols.estado > 0) fila[cols.estado - 1] = 'COMPLETADO';
  logs.appendRow(fila);
  const n = logs.getLastRow();
  logs.getRange(n, cols.fecha).setNumberFormat('dd/mm/yyyy');
  logs.getRange(n, cols.inicio).setNumberFormat('dd/mm/yyyy hh:mm:ss');
  logs.getRange(n, cols.fin).setNumberFormat('dd/mm/yyyy hh:mm:ss');
}

// ------------------------------------------------
// UTILIDADES
// ------------------------------------------------
// Última fila con datos
function ultimaFilaConDatos(hoja, columna) {
  const limite = hoja.getLastRow();
  if (limite < 1) return 0;
  const valores = hoja.getRange(1, columna, limite, 1).getValues();
  let ultima = 0;
  for (let i = 0; i < valores.length; i++) {
    if (valores[i][0] !== '' && valores[i][0] !== null) ultima = i + 1;
  }
  return ultima;
}

// Normaliza el encabezado
function normalizar(s) {
  return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '').toUpperCase();
}

// "2026-09-03" a Date (mediodía)
function isoADate(s) {
  const p = String(s).split('-');
  return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]), 12, 0, 0);
}

// "2026-09-03 10:28:27" a Date
function isoADateHora(s) {
  const d = new Date(String(s).replace(' ', 'T'));
  return isNaN(d.getTime()) ? new Date() : d;
}

function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
