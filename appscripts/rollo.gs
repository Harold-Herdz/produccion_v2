// ------------------------------------------------
// CONFIGURACION
// ------------------------------------------------
const TOKEN = "PLASTYPETCO_BODEGA1";
const CARPETA_ID = "1GB7rr6TarxERg0TfA7SfF8-Xb7phbSyw";

// ------------------------------------------------
// ENTRADA (POST)
// ------------------------------------------------
function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== TOKEN) {
      return json({ ok: false, error: "Token inválido" });
    }

    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const resultado = { ok: true };

    // ------------------------------------------------
    // REGISTRO (fila en REGISTROS)
    // ------------------------------------------------
    if (body.fila) {
      const registros = ss.getSheetByName("REGISTROS");
      const fila = body.fila.slice();
      fila[0] = isoADate(fila[0]);
      const destino = ultimaFilaConDatos(registros, 2) + 1;
      registros.getRange(destino, 2, 1, fila.length).setValues([fila]);
    }

    // ------------------------------------------------
    // CIERRE DE DIA (LOGS + PDF)
    // ------------------------------------------------
    if (body.cierre) {
      const logs = obtenerOCrearHojaLogs(ss);
      const log = body.cierre.log.slice();
      log[1] = isoADate(log[1]);
      log[2] = isoADateHora(log[2]);
      log[3] = isoADateHora(log[3]);

      // Upsert por ID del día
      const filaLog = escribirLog(logs, body.cierre.id_dia, log);
      logs.getRange(filaLog, 2).setNumberFormat("dd/mm/yyyy");
      logs.getRange(filaLog, 3, 1, 2).setNumberFormat("dd/mm/yyyy hh:mm:ss");

      if (body.cierre.pdf && body.cierre.pdf.base64) {
        resultado.cierre_pdf_url = guardarPdf(body.cierre.pdf);
      }
    }

    SpreadsheetApp.flush();
    return json(resultado);
  } catch (err) {
    return json({ ok: false, error: String(err) });
  }
}

// ------------------------------------------------
// LOGS (crear o actualizar fila)
// ------------------------------------------------
function escribirLog(logs, idDia, log) {
  const ultima = logs.getLastRow();
  const colA = ultima > 0 ? logs.getRange(1, 1, ultima, 1).getValues() : [];
  for (let i = 0; i < colA.length; i++) {
    if (String(colA[i][0]).trim() === String(idDia).trim()) {
      logs.getRange(i + 1, 1, 1, log.length).setValues([log]);
      return i + 1;
    }
  }
  logs.appendRow(log);
  return logs.getLastRow();
}

function obtenerOCrearHojaLogs(ss) {
  let hoja = ss.getSheetByName("LOGS");
  if (!hoja) hoja = ss.insertSheet("LOGS");
  if (hoja.getLastRow() === 0) {
    hoja
      .getRange(1, 1, 1, 6)
      .setValues([
        ["ID FECHA", "FECHA", "INICIO", "FIN", "REGISTROS", "ESTADO"],
      ])
      .setFontWeight("bold")
      .setHorizontalAlignment("center");
    hoja.setFrozenRows(1);
  }
  return hoja;
}

// ------------------------------------------------
// PDF (carpeta del mes en Drive)
// ------------------------------------------------
function guardarPdf(pdf) {
  const carpetaMes = obtenerOCrear(DriveApp.getFolderById(CARPETA_ID), pdf.mes);

  // Reemplaza el PDF del mismo nombre
  const existentes = carpetaMes.getFilesByName(pdf.nombre);
  while (existentes.hasNext()) existentes.next().setTrashed(true);

  const blob = Utilities.newBlob(
    Utilities.base64Decode(pdf.base64),
    "application/pdf",
    pdf.nombre,
  );
  return carpetaMes.createFile(blob).getUrl();
}

function obtenerOCrear(padre, nombre) {
  const it = padre.getFoldersByName(nombre);
  return it.hasNext() ? it.next() : padre.createFolder(nombre);
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
    if (valores[i][0] !== "" && valores[i][0] !== null) ultima = i + 1;
  }
  return ultima;
}

// "2026-09-03" a Date (mediodía)
function isoADate(s) {
  const p = String(s).split("-");
  return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]), 12, 0, 0);
}

// "2026-09-03 10:28:27" a Date
function isoADateHora(s) {
  const d = new Date(String(s).replace(" ", "T"));
  return isNaN(d.getTime()) ? new Date() : d;
}

function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(
    ContentService.MimeType.JSON,
  );
}
