// ------------------------------------------------
// CONFIGURACION
// ------------------------------------------------
const TOKEN = "PLASTYPETCO_BODEGA1";
const SS_ID = "1B1A-pSUBLG9w56ibWcERxhAKEsaPJN74SjRjeNv2UCg";
const CARPETA_ID = "1YPCqkkM6EqUcPaHFJdYxh_oDqwBZJioO";

// ------------------------------------------------
// ENTRADA (POST)
// ------------------------------------------------
function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.token !== TOKEN) {
      return json({ ok: false, error: "Token inválido" });
    }

    const ss = SpreadsheetApp.openById(SS_ID);
    const registros = ss.getSheetByName("REGISTROS");
    const logs = ss.getSheetByName("LOGS");

    // ------------------------------------------------
    // ANTI-DUPLICADO (turno ya en LOGS)
    // ------------------------------------------------
    if (turnoYaExportado(logs, body.log[0])) {
      return json({
        ok: false,
        error: "yaExportado",
        mensaje: "Este turno ya fue exportado.",
      });
    }

    // ------------------------------------------------
    // REGISTROS (filas del turno)
    // ------------------------------------------------
    const filas = body.registros || [];
    if (filas.length > 0) {
      escribirRegistros(registros, filas);
    }

    // ------------------------------------------------
    // LOGS (fila del turno)
    // ------------------------------------------------
    const log = body.log.slice();
    log[1] = isoADate(log[1]);
    log[4] = isoADateHora(log[4]);
    log[5] = isoADateHora(log[5]);
    logs.appendRow(log);
    logs.getRange(logs.getLastRow(), 2, 1, 1).setNumberFormat("dd/mm/yyyy");

    // ------------------------------------------------
    // PDF (Drive)
    // ------------------------------------------------
    let pdfUrl = null;
    if (body.pdf && body.pdf.base64) {
      pdfUrl = guardarPdfEnDrive(body.pdf);
    }

    SpreadsheetApp.flush();
    return json({ ok: true, registros: filas.length, pdf_url: pdfUrl });
  } catch (err) {
    return json({ ok: false, error: String(err) });
  }
}

// ------------------------------------------------
// ANTI-DUPLICADO
// ------------------------------------------------
function turnoYaExportado(logs, idTurno) {
  const ultima = logs.getLastRow();
  if (ultima < 1) return false;
  const colA = logs.getRange(1, 1, ultima, 1).getValues();
  for (let i = 0; i < colA.length; i++) {
    if (String(colA[i][0]).trim() === String(idTurno).trim()) return true;
  }
  return false;
}

// ------------------------------------------------
// REGISTROS (desde la columna B)
// ------------------------------------------------
function escribirRegistros(registros, filas) {
  // Fecha ISO a Date (columna B)
  filas.forEach(function (f) {
    f[0] = isoADate(f[0]);
  });

  // Primera fila con columna A vacía (A es fórmula)
  const colA = registros
    .getRange("A1:A" + Math.max(registros.getLastRow(), 1))
    .getValues();
  let primeraVacia = colA.length + 1;
  for (let i = 0; i < colA.length; i++) {
    if (colA[i][0] === "" || colA[i][0] === null) {
      primeraVacia = i + 1;
      break;
    }
  }
  registros
    .getRange(primeraVacia, 2, filas.length, filas[0].length)
    .setValues(filas);
  registros
    .getRange(primeraVacia, 2, filas.length, 1)
    .setNumberFormat("dd/mm/yyyy");
}

// ------------------------------------------------
// PDF (carpeta mes / dia en Drive)
// ------------------------------------------------
function guardarPdfEnDrive(pdf) {
  const principal = DriveApp.getFolderById(CARPETA_ID);
  const carpetaMes = obtenerOCrear(principal, pdf.mes);
  const carpetaDia = obtenerOCrear(carpetaMes, pdf.dia);

  // Reemplaza el PDF del mismo nombre
  const existentes = carpetaDia.getFilesByName(pdf.nombre);
  while (existentes.hasNext()) existentes.next().setTrashed(true);

  const blob = Utilities.newBlob(
    Utilities.base64Decode(pdf.base64),
    "application/pdf",
    pdf.nombre,
  );
  return carpetaDia.createFile(blob).getUrl();
}

function obtenerOCrear(padre, nombre) {
  const it = padre.getFoldersByName(nombre);
  return it.hasNext() ? it.next() : padre.createFolder(nombre);
}

// ------------------------------------------------
// UTILIDADES
// ------------------------------------------------
// "2026-07-01" a Date (mediodía)
function isoADate(s) {
  const p = String(s).split("-");
  return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]), 12, 0, 0);
}

// "2026-07-01 10:28:27" a Date
function isoADateHora(s) {
  const d = new Date(String(s).replace(" ", "T"));
  return isNaN(d.getTime()) ? new Date() : d;
}

function json(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(
    ContentService.MimeType.JSON,
  );
}
