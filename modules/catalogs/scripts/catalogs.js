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

// Confirmar cambio de estado
function confirmarEstado(estadoActual) {
    var mensaje = (estadoActual === 1)
        ? "¿Deseas inhabilitar este registro?"
        : "¿Deseas activar este registro?";
    return confirm(mensaje);
}

// Cerrar modal al hacer clic fuera
window.addEventListener("click", function (evento) {
    if (evento.target.classList.contains("overlay")) {
        evento.target.style.display = "none";
    }
});

// Mostrar panel exportar/importar
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

// Pintar resultados en overlay
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

// Enviar por AJAX
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
