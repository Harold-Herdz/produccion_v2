/* =====================================================
   RELACIONES — Interacción de la vista
   ===================================================== */

function abrirModal(idModal) {
    document.getElementById(idModal).style.display = "flex";
}
function cerrarModal(idModal) {
    document.getElementById(idModal).style.display = "none";
}
window.addEventListener("click", function (evento) {
    if (evento.target.classList.contains("overlay")) {
        evento.target.style.display = "none";
    }
});

// Pinta la lista de resultados en el overlay y lo abre
function mostrarResultadoRelaciones(datos) {
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
    abrirModal("modalResultadoCatalogos");
}

// Exportar / importar las relaciones (AJAX)
function accionRelaciones(tipo) {
    const url = document.getElementById("accionesRelaciones").dataset.url;
    const overlayCarga = document.getElementById("overlayCargaCatalogos");
    document.getElementById("textoCargaCatalogos").textContent =
        tipo === "exportar" ? "Exportando…" : "Importando…";
    overlayCarga.style.display = "flex";

    const datos = new URLSearchParams();
    datos.set("accion", tipo);

    fetch(url, { method: "POST", body: datos })
        .then(res => res.json())
        .then(mostrarResultadoRelaciones)
        .catch(() => mostrarResultadoRelaciones({
            tipo: tipo,
            resultados: [{ etiqueta: "Error", detalle: "No se pudo conectar con el servidor.", error: true }]
        }))
        .finally(() => { overlayCarga.style.display = "none"; });
}

// Al cerrar el resultado de una importación, recargar para ver las casillas actualizadas
document.getElementById("modalResultadoCatalogos").addEventListener("click", e => {
    const cerrar = e.target.classList.contains("overlay") || e.target.classList.contains("cerrar-resultado");
    if (cerrar && document.getElementById("tituloResultadoCatalogos").textContent.startsWith("Importación")) {
        if (window.guardarScrollPagina) guardarScrollPagina();
        location.reload();
    }
});

/* =====================================================
   MATRIZ MÁQUINA × ÁREA / REFERENCIA (guardado de un clic)
   ===================================================== */
// Casillas de datos de una fila (sin "Todas" ni "Especiales")
function casillasFila(fila) {
    return Array.from(fila.querySelectorAll(".chk-matriz")).filter(c => c.dataset.accion !== "toggle_maquina_esp");
}

// "Todas" queda marcada solo si todas las casillas de la fila lo están
function sincronizarTodas(fila) {
    const todas = fila.querySelector(".chk-todas");
    if (!todas) return;
    const casillas = casillasFila(fila);
    todas.checked = casillas.length > 0 && casillas.every(c => c.checked);
}

function enviarCasilla(scroll, chk) {
    const datos = new URLSearchParams();
    datos.set("accion", chk.dataset.accion);
    datos.set("id_maquina", chk.dataset.maquina);
    if (chk.dataset.area) datos.set("id_area", chk.dataset.area);
    if (chk.dataset.referencia) datos.set("id_referencia", chk.dataset.referencia);
    return fetch(scroll.dataset.url, { method: "POST", body: datos });
}

document.querySelectorAll(".matriz-scroll tbody tr").forEach(sincronizarTodas);

document.addEventListener("change", e => {
    const chk = e.target;
    const scroll = chk.closest ? chk.closest(".matriz-scroll") : null;
    if (!scroll) return;
    const fila = chk.closest("tr");

    // Casilla "Todas": marca o desmarca toda la fila (solo se envían las que cambian)
    if (chk.classList.contains("chk-todas")) {
        casillasFila(fila).filter(c => !c.disabled && c.checked !== chk.checked).forEach(c => {
            c.checked = chk.checked;
            enviarCasilla(scroll, c).catch(() => {
                c.checked = !c.checked;
                sincronizarTodas(fila);
            });
        });
        return;
    }

    if (!chk.classList.contains("chk-matriz")) return;

    const esEsp = chk.dataset.accion === "toggle_maquina_esp";
    const alternarFila = marcado => {
        fila.classList.toggle("fila-usa-esp", marcado);
        casillasFila(fila).forEach(ref => { ref.disabled = marcado; });
        const todas = fila.querySelector(".chk-todas");
        if (todas) todas.disabled = marcado;
    };
    if (esEsp) alternarFila(chk.checked);
    else sincronizarTodas(fila);

    enviarCasilla(scroll, chk).catch(() => {
        chk.checked = !chk.checked;
        if (esEsp) alternarFila(chk.checked);
        else sincronizarTodas(fila);
    });
});

/* =====================================================
   BÚSQUEDA POR MÁQUINA (filtra las filas al escribir)
   ===================================================== */
const buscarMaquina = document.getElementById("buscarMaquina");
if (buscarMaquina) {
    buscarMaquina.addEventListener("input", () => {
        const texto = buscarMaquina.value.trim().toLowerCase();
        document.querySelectorAll(".matriz-scroll tbody tr").forEach(fila => {
            fila.hidden = texto !== "" && !fila.dataset.maquinaNombre.includes(texto);
        });
    });
    // Enter en el buscador no debe recargar la página
    buscarMaquina.closest("form").addEventListener("submit", e => e.preventDefault());
}
