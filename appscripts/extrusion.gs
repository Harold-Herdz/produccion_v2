// ------------------------------------------------
// APPS SCRIPT - EXTRUSION
// ------------------------------------------------

// ------------------------------------------------
// CONFIGURACION
// ------------------------------------------------
const TOKEN      = 'PLASTYPETCO_BODEGA1';
const CARPETA_ID = '1x9vnygja6Pv4S9pA8tbm-1sGzM75-m2b';

// ------------------------------------------------
// ENTRADA (POST)
// ------------------------------------------------
function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== TOKEN) {
      return json({ ok: false, error: 'Token inválido' });
    }
    // TEMPORAL: carga histórica (borrar con historicoLogs)
    if (body.accion === 'historico_logs') return json(historicoLogs(body.logs));
    if (body.accion === 'historico_pdf')  return json({ ok: true, pdf_url: guardarPdf(body.pdf) });
    if (body.accion !== 'finalizar') {
      return json({ ok: false, error: 'Acción no válida' });
    }

    const ss        = SpreadsheetApp.getActiveSpreadsheet();
    const registros = ss.getSheetByName('REGISTROS');
    const logs      = obtenerOCrearHojaLogs(ss);
    const cols      = columnasLogs(logs);

    // ------------------------------------------------
    // ANTI-DUPLICADO (turno ya en LOGS)
    // ------------------------------------------------
    const filaLog = buscarFilaLog(logs, cols, body.log.id_dia, body.log.maquina);
    if (filaLog > 0 && cols.turnos > 0) {
      const turnos = String(logs.getRange(filaLog, cols.turnos).getValue());
      if (turnos.indexOf(body.log.turno_codigo) !== -1) {
        return json({ ok: false, error: 'yaExportado', mensaje: 'Este turno ya fue exportado.' });
      }
    }

    // ------------------------------------------------
    // REGISTROS (una fila por peso, desde la columna B)
    // ------------------------------------------------
    const filas = body.registros || [];
    if (filas.length > 0) {
      escribirRegistros(registros, filas);
    }

    // ------------------------------------------------
    // LOGS (fila del día y la máquina)
    // ------------------------------------------------
    escribirLog(logs, cols, filaLog, body.log);

    // ------------------------------------------------
    // PDF (maquina / mes en Drive)
    // ------------------------------------------------
    let pdfUrl = null;
    if (body.pdf && body.pdf.base64) {
      pdfUrl = guardarPdf(body.pdf);
    }

    SpreadsheetApp.flush();
    return json({ ok: true, registros: filas.length, pdf_url: pdfUrl });

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
}

// ------------------------------------------------
// LOGS (columnas por nombre de encabezado)
// ------------------------------------------------
function obtenerOCrearHojaLogs(ss) {
  let hoja = ss.getSheetByName('LOGS');
  if (!hoja) hoja = ss.insertSheet('LOGS');
  if (hoja.getLastRow() === 0) {
    hoja.getRange(1, 1, 1, 8)
      .setValues([['ID FECHA', 'FECHA', 'MÁQUINA', 'TURNOS', 'INICIO', 'FIN', 'REGISTROS', 'ESTADO']])
      .setFontWeight('bold').setHorizontalAlignment('center');
    hoja.setFrozenRows(1);
  }
  return hoja;
}

// Posición (1-based) de cada columna; 0 si no existe
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
    turnos:    pos(['TURNOS', 'TURNO']),
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

// Fila (1-based) del día y máquina; 0 si no existe
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

// Crea la fila del día o actualiza fin, registros y turnos
function escribirLog(logs, cols, filaLog, log) {
  const fin = isoADateHora(log.fin);
  if (filaLog > 0) {
    logs.getRange(filaLog, cols.fin).setValue(fin).setNumberFormat('dd/mm/yyyy hh:mm:ss');
    logs.getRange(filaLog, cols.registros).setValue(log.registros);
    if (cols.turnos > 0) {
      const celda = logs.getRange(filaLog, cols.turnos);
      celda.setValue(String(celda.getValue()) ? celda.getValue() + ',' + log.turno_codigo : log.turno_codigo);
    }
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
  if (cols.turnos > 0) fila[cols.turnos - 1] = log.turno_codigo;
  if (cols.estado > 0) fila[cols.estado - 1] = 'COMPLETADO';
  logs.appendRow(fila);
  const n = logs.getLastRow();
  logs.getRange(n, cols.fecha).setNumberFormat('dd/mm/yyyy');
  logs.getRange(n, cols.inicio).setNumberFormat('dd/mm/yyyy hh:mm:ss');
  logs.getRange(n, cols.fin).setNumberFormat('dd/mm/yyyy hh:mm:ss');
}

// ------------------------------------------------
// PDF (carpeta maquina / mes en Drive)
// ------------------------------------------------
function guardarPdf(pdf) {
  const carpetaMaquina = obtenerOCrear(DriveApp.getFolderById(CARPETA_ID), pdf.maquina);
  const carpetaMes     = obtenerOCrear(carpetaMaquina, pdf.mes);

  // Reemplaza el PDF del mismo nombre
  const existentes = carpetaMes.getFilesByName(pdf.nombre);
  while (existentes.hasNext()) existentes.next().setTrashed(true);

  const blob = Utilities.newBlob(Utilities.base64Decode(pdf.base64), 'application/pdf', pdf.nombre);
  return carpetaMes.createFile(blob).getUrl();
}

function obtenerOCrear(padre, nombre) {
  const it = padre.getFoldersByName(nombre);
  return it.hasNext() ? it.next() : padre.createFolder(nombre);
}

// ------------------------------------------------
// TEMPORAL: CARGA HISTORICA (borrar despues)
// ------------------------------------------------
function historicoLogs(items) {
  const ss   = SpreadsheetApp.getActiveSpreadsheet();
  const logs = obtenerOCrearHojaLogs(ss);
  const cols = columnasLogs(logs);

  const ultima = logs.getLastRow();
  const datos  = ultima > 1 ? logs.getRange(2, 1, ultima - 1, cols.total).getValues() : [];
  const indice = {};
  datos.forEach(function (f, i) {
    indice[String(f[cols.id - 1]).trim() + '|' + String(f[cols.maquina - 1]).trim()] = i;
  });

  const nuevas = [];
  items.forEach(function (l) {
    const clave = String(l.id_dia).trim() + '|' + String(l.maquina).trim();
    let fila;
    if (clave in indice) {
      fila = datos[indice[clave]];
    } else {
      fila = new Array(cols.total).fill('');
      fila[cols.id - 1] = l.id_dia;
      fila[cols.maquina - 1] = l.maquina;
      nuevas.push(fila);
    }
    fila[cols.fecha - 1]     = isoADate(l.fecha);
    fila[cols.inicio - 1]    = isoADateHora(l.inicio);
    fila[cols.fin - 1]       = isoADateHora(l.fin);
    fila[cols.registros - 1] = l.registros;
    if (cols.turnos > 0) fila[cols.turnos - 1] = l.turnos;
    if (cols.estado > 0) fila[cols.estado - 1] = 'COMPLETADO';
  });

  if (datos.length > 0) logs.getRange(2, 1, datos.length, cols.total).setValues(datos);
  if (nuevas.length > 0) logs.getRange(datos.length + 2, 1, nuevas.length, cols.total).setValues(nuevas);

  const total = datos.length + nuevas.length;
  if (total > 0) {
    logs.getRange(2, cols.fecha, total, 1).setNumberFormat('dd/mm/yyyy');
    logs.getRange(2, cols.inicio, total, 1).setNumberFormat('dd/mm/yyyy hh:mm:ss');
    logs.getRange(2, cols.fin, total, 1).setNumberFormat('dd/mm/yyyy hh:mm:ss');
  }
  SpreadsheetApp.flush();
  return { ok: true, actualizadas: datos.length, nuevas: nuevas.length };
}

// ------------------------------------------------
// UTILIDADES
// ------------------------------------------------
// Última fila con datos en una columna
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

// Encabezado sin tildes, espacios ni mayúsculas
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
