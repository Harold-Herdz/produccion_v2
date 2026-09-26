// Campanita de notificaciones: valores de catálogo escritos a mano ("Otro")
// pendientes de revisión por un admin.
(function () {
  const btn = document.getElementById('campanaBtn');
  if (!btn) return;

  const url = btn.dataset.url;
  const badge = document.getElementById('campanaBadge');
  const panel = document.getElementById('campanaPanel');
  const body = document.getElementById('campanaPanelBody');

  function etiquetaFecha(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    if (isNaN(d)) return '';
    return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit' }) +
      ' ' + d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
  }

  function filaPendiente(item) {
    const meta = [item.etiqueta, item.contexto, item.modulo].filter(Boolean).join(' · ');
    const div = document.createElement('div');
    div.className = 'campana-item';
    div.innerHTML = `
      <div class="campana-item-info">
        <div class="campana-item-valor">${item.valor}</div>
        <div class="campana-item-meta">${meta} · ${etiquetaFecha(item.creado_en)}</div>
      </div>
      <div class="campana-item-acciones">
        <button type="button" class="campana-aprobar" title="Confirmar" data-id="${item.id}" data-accion="aprobar">&#10003;</button>
        <button type="button" class="campana-rechazar" title="Rechazar" data-id="${item.id}" data-accion="rechazar">&#10005;</button>
      </div>`;
    return div;
  }

  function filaHistorial(item) {
    const meta = [item.etiqueta, item.modulo].filter(Boolean).join(' · ');
    const esAprobado = item.decision === 'aprobado';
    const div = document.createElement('div');
    div.className = 'campana-item';
    div.innerHTML = `
      <div class="campana-item-info">
        <div class="campana-item-valor">${item.valor}</div>
        <div class="campana-item-meta">${meta} · ${etiquetaFecha(item.revisado_en)}</div>
      </div>
      <span class="campana-estado campana-estado-${esAprobado ? 'aprobado' : 'rechazado'}">${esAprobado ? 'Aprobado' : 'Rechazado'}</span>`;
    return div;
  }

  function render(data) {
    body.innerHTML = '';

    body.appendChild(Object.assign(document.createElement('div'), { className: 'campana-seccion-titulo', textContent: `Pendientes (${data.pendientes.length})` }));
    if (data.pendientes.length === 0) {
      body.appendChild(Object.assign(document.createElement('div'), { className: 'campana-vacio', textContent: 'No tienes notificaciones pendientes.' }));
    } else {
      data.pendientes.forEach(item => body.appendChild(filaPendiente(item)));
    }

    if (data.historial.length > 0) {
      body.appendChild(Object.assign(document.createElement('div'), { className: 'campana-seccion-titulo', textContent: 'Historial' }));
      data.historial.forEach(item => body.appendChild(filaHistorial(item)));
    }

    badge.textContent = data.contador;
    badge.hidden = data.contador === 0;
  }

  function cargar() {
    fetch(url + '?accion=listar')
      .then(r => r.json())
      .then(data => { if (data.ok) render(data); })
      .catch(() => {});
  }

  function accion(id, tipo) {
    const datos = new FormData();
    datos.append('accion', tipo);
    datos.append('id', id);
    fetch(url, { method: 'POST', body: datos })
      .then(r => r.json())
      .then(() => cargar());
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    const abrir = panel.hidden;
    panel.hidden = !abrir;
    if (abrir) cargar();
  });

  body.addEventListener('click', function (e) {
    const b = e.target.closest('button[data-accion]');
    if (!b) return;
    accion(b.dataset.id, b.dataset.accion);
  });

  document.addEventListener('click', function (e) {
    if (!panel.hidden && !panel.contains(e.target) && e.target !== btn) {
      panel.hidden = true;
    }
  });

  cargar();
  setInterval(cargar, 60000);
})();
