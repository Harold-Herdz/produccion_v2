/* =====================================================
   CATÁLOGOS — Interacción de la vista
   ===================================================== */

// Abrir modal por ID
function abrirModal(idModal) {
    document.getElementById(idModal).style.display = "flex";
}

// Cerrar modal por ID
function cerrarModal(idModal) {
    document.getElementById(idModal).style.display = "none";
}

// Confirmar el cambio de estado antes de enviar el formulario
// estadoActual = 1 (activo)  -> se va a inhabilitar
// estadoActual = 0 (inactivo) -> se va a activar
function confirmarEstado(estadoActual) {
    var mensaje = (estadoActual === 1)
        ? "¿Deseas inhabilitar este registro?"
        : "¿Deseas activar este registro?";
    return confirm(mensaje);
}

// Cerrar el modal al hacer clic fuera de la tarjeta
window.addEventListener("click", function (evento) {
    if (evento.target.classList.contains("overlay")) {
        evento.target.style.display = "none";
    }
});

// Mostrar panel de exportar/importar en lugar de la tabla
function mostrarPanelCatalogos(tipo) {
    document.getElementById("containerHistorial").style.display = "none";
    document.getElementById("accionesCatalogos").style.display = "none";
    document.getElementById("panelExportar").style.display = tipo === "exportar" ? "block" : "none";
    document.getElementById("panelImportar").style.display = tipo === "importar" ? "block" : "none";
}

// Volver a la tabla normal
function ocultarPanelesCatalogos() {
    document.getElementById("panelExportar").style.display = "none";
    document.getElementById("panelImportar").style.display = "none";
    document.getElementById("containerHistorial").style.display = "block";
    document.getElementById("accionesCatalogos").style.display = "flex";
}

// Pinta la lista de resultados en el overlay y lo abre
function mostrarResultadoCatalogos(datos) {
    document.getElementById("tituloResultadoCatalogos").textContent =
        datos.tipo === "exportar" ? "Exportación completada" : "Importación completada";

    const lista = document.getElementById("listaResultadoCatalogos");
    lista.innerHTML = "";
    datos.resultados.forEach(r => {
        const li = document.createElement("li");
        if (r.error) li.classList.add("error");
        const etiqueta = document.createElement("span");
        etiqueta.className = "resultado-etiqueta";
        etiqueta.textContent = r.etiqueta;
        const detalle = document.createElement("span");
        detalle.className = "resultado-detalle";
        detalle.textContent = r.detalle;
        li.append(etiqueta, detalle);
        lista.appendChild(li);
    });

    ocultarPanelesCatalogos();
    abrirModal("modalResultadoCatalogos");
}

// Enviar exportar/importar por AJAX: overlay de carga, luego el de resultado
function enviarFormularioCatalogos(form, tipo) {
    const overlayCarga = document.getElementById("overlayCargaCatalogos");
    document.getElementById("textoCargaCatalogos").textContent =
        tipo === "exportar" ? "Exportando…" : "Importando…";
    overlayCarga.style.display = "flex";

    fetch(form.dataset.url, { method: "POST", body: new FormData(form) })
        .then(res => res.json())
        .then(mostrarResultadoCatalogos)
        .catch(() => mostrarResultadoCatalogos({
            tipo: tipo,
            resultados: [{ etiqueta: "Error", detalle: "No se pudo conectar con el servidor.", error: true }]
        }))
        .finally(() => { overlayCarga.style.display = "none"; });
}

const formExportarCatalogos = document.getElementById("formExportarCatalogos");
if (formExportarCatalogos) {
    formExportarCatalogos.addEventListener("submit", e => {
        e.preventDefault();
        enviarFormularioCatalogos(formExportarCatalogos, "exportar");
    });
}

const formImportarCatalogos = document.getElementById("formImportarCatalogos");
if (formImportarCatalogos) {
    formImportarCatalogos.addEventListener("submit", e => {
        e.preventDefault();
        enviarFormularioCatalogos(formImportarCatalogos, "importar");
    });
}

/* =====================================================
   MATRIZ MÁQUINA × ÁREA / REFERENCIA (guardado de un clic)
   ===================================================== */
document.addEventListener("change", e => {
    if (!e.target.classList.contains("chk-matriz")) return;
    const chk = e.target;
    const scroll = chk.closest(".matriz-scroll");
    if (!scroll) return;

    const esEsp = chk.dataset.accion === "toggle_maquina_esp";
    const alternarFila = marcado => {
        const fila = chk.closest("tr");
        fila.classList.toggle("fila-usa-esp", marcado);
        fila.querySelectorAll('input[data-accion="toggle_maquina_referencia"]').forEach(ref => {
            ref.disabled = marcado;
        });
    };
    if (esEsp) alternarFila(chk.checked);

    const datos = new URLSearchParams();
    datos.set("accion", chk.dataset.accion);
    datos.set("id_maquina", chk.dataset.maquina);
    if (chk.dataset.area) datos.set("id_area", chk.dataset.area);
    if (chk.dataset.referencia) datos.set("id_referencia", chk.dataset.referencia);

    fetch(scroll.dataset.url, { method: "POST", body: datos })
        .catch(() => {
            chk.checked = !chk.checked;
            if (esEsp) alternarFila(chk.checked);
        });
});
