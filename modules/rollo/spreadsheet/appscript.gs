/**
 * WEB APP DE ROLLOS — recibe cada registro desde PHP y lo agrega a REGISTROS;
 * si viene un "cierre", actualiza/crea la fila del día en LOGS y sube el PDF a Drive.
 *
 * Pega este archivo en el Apps Script del spreadsheet de Rollos.
 * Cambia TOKEN y CARPETA_ID, y usa el MISMO token en modules/rollo/spreadsheet/appscript.php
 * Implementar → Nueva implementación → Aplicación web
 *   - Ejecutar como: Yo
 *   - Quién tiene acceso: Cualquier usuario
 */

const TOKEN      = 'PEGAR_AQUI_UN_TOKEN_SECRETO';
const CARPETA_ID = 'PEGAR_AQUI_EL_ID_DE_LA_CARPETA_DE_DRIVE';

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== TOKEN) {
      return json({ ok: false, error: 'Token inválido' });
    }

    const ss = SpreadsheetApp.getActiveSpreadsheet();
    let resultado = { ok: true };

    // --- Agregar la fila del registro a REGISTROS (desde la columna B; A es fórmula) ---
    if (body.fila) {
      const registros = ss.getSheetByName('REGISTROS');
      const fila = body.fila.slice();
      fila[0] = isoADate(fila[0]); // FECHA

      const colA = registros.getRange('A1:A' + Math.max(registros.getLastRow(), 1)).getValues();
      let primeraVacia = colA.length + 1;
      for (let i = 0; i < colA.length; i++) {
        if (colA[i][0] === '' || colA[i][0] === null) { primeraVacia = i + 1; break; }
      }
      registros.getRange(primeraVacia, 2, 1, fila.length).setValues([fila]);
    }

    // --- Cerrar un día: upsert en LOGS + PDF a Drive ---
    if (body.cierre) {
      const logs = obtenerOCrearHojaLogs(ss);
      const log = body.cierre.log.slice();
      log[1] = isoADate(log[1]);      // FECHA
      log[2] = isoADateHora(log[2]);  // INICIO
      log[3] = isoADateHora(log[3]);  // FIN

      const idDia = body.cierre.id_dia;
      const colA = logs.getLastRow() > 0 ? logs.getRange(1, 1, logs.getLastRow(), 1).getValues() : [];
      let filaExistente = -1;
      for (let i = 0; i < colA.length; i++) {
        if (String(colA[i][0]).trim() === String(idDia).trim()) { filaExistente = i + 1; break; }
      }
      if (filaExistente > 0) {
        logs.getRange(filaExistente, 1, 1, log.length).setValues([log]);
      } else {
        logs.appendRow(log);
      }

      if (body.cierre.pdf && body.cierre.pdf.base64) {
        const principal  = DriveApp.getFolderById(CARPETA_ID);
        const carpetaMes = obtenerOCrear(principal, body.cierre.pdf.mes);
        const carpetaDia = obtenerOCrear(carpetaMes, body.cierre.pdf.dia);

        const existentes = carpetaDia.getFilesByName(body.cierre.pdf.nombre);
        while (existentes.hasNext()) existentes.next().setTrashed(true);

        const blob = Utilities.newBlob(Utilities.base64Decode(body.cierre.pdf.base64), 'application/pdf', body.cierre.pdf.nombre);
        resultado.cierre_pdf_url = carpetaDia.createFile(blob).getUrl();
      }
    }

    SpreadsheetApp.flush();
    return json(resultado);

  } catch (err) {
    return json({ ok: false, error: String(err) });
  }
}

function obtenerOCrearHojaLogs(ss) {
  let hoja = ss.getSheetByName('LOGS');
  if (!hoja) {
    hoja = ss.insertSheet('LOGS');
    hoja.appendRow(['ID', 'FECHA', 'INICIO', 'FIN', 'REGISTROS', 'ESTADO']);
  }
  return hoja;
}

function obtenerOCrear(padre, nombre) {
  const it = padre.getFoldersByName(nombre);
  return it.hasNext() ? it.next() : padre.createFolder(nombre);
}

// "2026-09-03" -> Date (mediodía local, evita corrimientos de zona horaria)
function isoADate(s) {
  const p = String(s).split('-');
  return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]), 12, 0, 0);
}

// "2026-09-03 10:28:27" -> Date
function isoADateHora(s) {
  const d = new Date(String(s).replace(' ', 'T'));
  return isNaN(d.getTime()) ? new Date() : d;
}

function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
