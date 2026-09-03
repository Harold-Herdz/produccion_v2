/**
 * WEB APP DE SELLADO — recibe la planilla finalizada desde el servidor PHP
 * y escribe en las hojas REGISTROS / LOGS + guarda el PDF en Drive.
 *
 * Pega este archivo en el Apps Script del spreadsheet de REGISTROS/LOGS.
 * Cambia TOKEN por un valor secreto y usa el MISMO en modules/sellado/planilla/appscript.php
 * Luego: Implementar → Nueva implementación → Aplicación web
 *   - Ejecutar como: Yo
 *   - Quién tiene acceso: Cualquier usuario
 */

const TOKEN      = 'PEGAR_AQUI_UN_TOKEN_SECRETO';
const SS_ID      = '1B1A-pSUBLG9w56ibWcERxhAKEsaPJN74SjRjeNv2UCg';
const CARPETA_ID = '1YPCqkkM6EqUcPaHFJdYxh_oDqwBZJioO';   // carpeta "PDFs Sellado"

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);

    if (body.token !== TOKEN) {
      return json({ ok: false, error: 'Token inválido' });
    }

    const ss        = SpreadsheetApp.openById(SS_ID);
    const registros = ss.getSheetByName('REGISTROS');
    const logs      = ss.getSheetByName('LOGS');

    // --- Anti-duplicado: ¿el id del turno ya está en LOGS? ---
    const idTurno = body.log[0];
    const ultimaFila = logs.getLastRow();
    if (ultimaFila >= 1) {
      const colA = logs.getRange(1, 1, ultimaFila, 1).getValues();
      for (let i = 0; i < colA.length; i++) {
        if (String(colA[i][0]).trim() === String(idTurno).trim()) {
          return json({ ok: false, error: 'yaExportado', mensaje: 'Este turno ya fue exportado.' });
        }
      }
    }

    // --- Escribir filas en REGISTROS a partir de la columna B (A es fórmula) ---
    const filas = body.registros || [];
    if (filas.length > 0) {
      // Convertir la fecha (col 0, ISO) a objeto Date para que el Sheet la guarde como fecha
      filas.forEach(function (f) { f[0] = isoADate(f[0]); });

      const colA = registros.getRange('A1:A' + Math.max(registros.getLastRow(), 1)).getValues();
      let primeraVacia = colA.length + 1;
      for (let i = 0; i < colA.length; i++) {
        if (colA[i][0] === '' || colA[i][0] === null) { primeraVacia = i + 1; break; }
      }
      registros.getRange(primeraVacia, 2, filas.length, filas[0].length).setValues(filas);
    }

    // --- Registrar en LOGS ---
    const log = body.log.slice();
    log[1] = isoADate(log[1]);          // FECHA
    log[4] = isoADateHora(log[4]);      // INICIO
    log[5] = isoADateHora(log[5]);      // FIN
    logs.appendRow(log);

    // --- Guardar el PDF en Drive ---
    let pdfUrl = null;
    if (body.pdf && body.pdf.base64) {
      const principal  = DriveApp.getFolderById(CARPETA_ID);
      const carpetaMes = obtenerOCrear(principal, body.pdf.mes);
      const carpetaDia = obtenerOCrear(carpetaMes, body.pdf.dia);

      // Reemplazar si ya existía
      const existentes = carpetaDia.getFilesByName(body.pdf.nombre);
      while (existentes.hasNext()) existentes.next().setTrashed(true);

      const blob = Utilities.newBlob(Utilities.base64Decode(body.pdf.base64), 'application/pdf', body.pdf.nombre);
      pdfUrl = carpetaDia.createFile(blob).getUrl();
    }

    SpreadsheetApp.flush();
    return json({ ok: true, registros: filas.length, pdf_url: pdfUrl });

  } catch (err) {
    return json({ ok: false, error: String(err) });
  }
}

function obtenerOCrear(padre, nombre) {
  const it = padre.getFoldersByName(nombre);
  return it.hasNext() ? it.next() : padre.createFolder(nombre);
}

// "2026-07-01" -> Date (mediodía local, para evitar corrimientos de zona horaria)
function isoADate(s) {
  const p = String(s).split('-');
  return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]), 12, 0, 0);
}

// "2026-07-01 10:28:27" -> Date
function isoADateHora(s) {
  const t = String(s).replace(' ', 'T');
  const d = new Date(t);
  return isNaN(d.getTime()) ? new Date() : d;
}

function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
