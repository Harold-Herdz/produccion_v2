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

// Cerrar modal al hacer clic fuera
window.addEventListener("click", function (evento) {
    if (evento.target.classList.contains("overlay")) {
        evento.target.style.display = "none";
    }
});

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

    cerrarModal("modalExportarCatalogos");
    cerrarModal("modalImportarCatalogos");
    abrirModal("modalResultadoCatalogos");
}

// Casilla "Todas" de una tabla de selección (Exportar/Importar)
document.querySelectorAll(".modal-seleccion .chk-todas-seleccion").forEach(todas => {
    const tabla = todas.closest("table");
    const casillas = () => Array.from(tabla.querySelectorAll('tbody input[type="checkbox"]:not(.chk-todas-seleccion)'));
    todas.addEventListener("change", () => {
        casillas().forEach(c => { c.checked = todas.checked; });
    });
    tabla.addEventListener("change", e => {
        if (e.target === todas) return;
        if (e.target.matches('tbody input[type="checkbox"]')) {
            todas.checked = casillas().every(c => c.checked);
        }
    });
});

// Enviar por AJAX
function enviarFormularioCatalogos(form, tipo) {
    if (!form.querySelector('input[name="catalogos[]"]:checked')) {
        mostrarAviso("Selecciona al menos un catálogo.", { tipo: "error" });
        return;
    }
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
   BÚSQUEDA (filtra la tabla al escribir)
   ===================================================== */
const buscarCatalogos = document.getElementById("buscar");
if (buscarCatalogos) {
    buscarCatalogos.addEventListener("input", () => {
        const texto = buscarCatalogos.value.trim().toLowerCase();
        document.querySelectorAll("#containerHistorial tbody tr").forEach(fila => {
            fila.hidden = texto !== "" && !(fila.dataset.nombre || "").includes(texto);
        });
    });
    // Enter no recarga
    buscarCatalogos.closest("form").addEventListener("submit", e => e.preventDefault());
}
