// Actualizar barra de progreso
function up(pct, msg) {
    document.getElementById('fill').style.width        = pct + '%';
    document.getElementById('pct').textContent         = pct;
    document.getElementById('msg').textContent         = msg;
    document.getElementById('dot').className           = 'dot';
    document.getElementById('status-lbl').textContent  = 'Procesando';
}
// Actualizar contadores y log por cada fila procesada
function tick(cur, total, ok, upd, dup, msgLog, type) {
    // Actualizar contadores de estado
    document.getElementById('s-ok').textContent  = ok;
    document.getElementById('s-upd').textContent = upd;
    document.getElementById('s-dup').textContent = dup;
    document.getElementById('counter').textContent = cur + ' / ' + total + ' registros';

    // Calcular y mostrar progreso
    var pct = (cur / total) * 100;
    document.getElementById('fill').style.width = pct + '%';
    document.getElementById('pct').textContent = Math.round(pct);
    document.getElementById('msg').textContent  =
        'Importando \u2026 (' + cur + '\u202f/\u202f' + total + ')';

    // Agregar fila al log
    var row = document.createElement('div');
    row.className = 'lr ' + type;
    row.innerHTML = '<span class="n">' + cur + '</span>'
                  + '<span class="t">' + msgLog + '</span>';

    var log = document.getElementById('log');
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
}
// Finalizar importación y mostrar resumen
function done(ok, upd, dup, total) {
    // Completar barra de progreso
    document.getElementById('fill').style.width        = '100%';
    document.getElementById('pct').textContent         = '100';
    document.getElementById('msg').textContent         = 'Importación completada exitosamente';
    document.getElementById('dot').className           = 'dot done';
    document.getElementById('status-lbl').textContent  = 'Completado';

    // Mostrar estadísticas finales
    document.getElementById('s-ok').textContent  = ok;
    document.getElementById('s-upd').textContent = upd;
    document.getElementById('s-dup').textContent = dup;
    document.getElementById('s-tot').textContent = total;

    // Mostrar controles finales
    document.getElementById('stats').classList.add('show');
    document.getElementById('toggle-btn').classList.add('show');
    document.getElementById('btn-volver').classList.add('show');
}
// Mostrar u ocultar el log
function toggleLog() {
    document.getElementById('toggle-btn').classList.toggle('open');
    document.getElementById('log').classList.toggle('open');
}
// Volver a la página anterior o al inicio
function volverAtras() {
    if (window.history.length > 2) {
        window.history.back();
    } else {
        var url = window.VOLVER_URL || '/index.php';
        window.location.href = url;
    }
}