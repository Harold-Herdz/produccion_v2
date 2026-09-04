// Register: planilla digital de turno (entradas, autoguardado, finalizar)

const planilla = document.getElementById("planilla");

// Solo corre si hay un turno abierto en pantalla
if (planilla) {

    const indicador    = document.getElementById("indicadorGuardado");
    const zonaAvisos    = document.getElementById("zonaAvisos");
    const btnFinalizar  = document.getElementById("btnFinalizar");
    const btnConfirmar   = document.getElementById("btnConfirmarFinalizar");
    const inputFecha     = document.getElementById("fechaPlanilla");
    const spanCodigo     = document.getElementById("codigoPlanilla");
    const notaGeneral    = document.getElementById("notaGeneral");
    const tplEntrada     = document.getElementById("tplEntrada");
    const MAX_ENTRADAS   = 6;

    let sinGuardar   = false;   // hay cambios pendientes
    let guardando    = false;   // guardado en curso
    let finalizando  = false;   // finalización en curso
    let temporizador = null;    // debounce de autoguardado

    /* =========================================
       NOTA GENERAL (solo cliente + PDF, nunca en BD)
    ========================================= */
    let notaKey = "sellado_nota_" + planilla.dataset.codigo;
    try {
        const guardada = sessionStorage.getItem(notaKey);
        if (guardada) notaGeneral.value = guardada;
    } catch (e) {}
    notaGeneral.addEventListener("input", () => {
        try { sessionStorage.setItem(notaKey, notaGeneral.value); } catch (e) {}
    });

    /* =========================================
       INDICADOR DE ESTADO
    ========================================= */
    function setIndicador(estado, texto) {
        indicador.className = "indicador-guardado " + estado;
        indicador.querySelector(".texto").textContent = texto;
    }

    /* =========================================
       CONSTRUIR EL PAYLOAD DE LA PLANILLA
    ========================================= */
    function recolectar() {
        const maquinas = [...planilla.querySelectorAll(".grupo-maquina")].map(tb => {
            const op = tb.querySelector(".col-operario");
            return {
                maquina:       Number(tb.dataset.maquina),
                id_operario:   op.querySelector(".f-operario").value,
                otro_operario: op.querySelector(".f-otro-operario").value.trim(),
                jornada:       op.querySelector(".f-jornada").value,
                entradas: [...tb.querySelectorAll(".fila-entrada")].map(tr => ({
                    id_referencia: tr.querySelector(".f-ref").value,
                    id_color:      tr.querySelector(".f-color").value,
                    x70: tr.querySelector(".f-x70").value,
                    x90: tr.querySelector(".f-x90").value,
                    x98: tr.querySelector(".f-x98").value,
                    p1:  tr.querySelector(".f-p1").value,
                    p2:  tr.querySelector(".f-p2").value,
                    p3:  tr.querySelector(".f-p3").value,
                    p4:  tr.querySelector(".f-p4").value,
                    p5:  tr.querySelector(".f-p5").value,
                    obs: tr.querySelector(".f-obs").value.trim()
                }))
            };
        });
        return {
            codigo: planilla.dataset.codigo,
            fecha:  inputFecha.value,
            nota:   notaGeneral.value,
            maquinas
        };
    }

    /* =========================================
       AVISOS (operarios nuevos, errores)
    ========================================= */
    function mostrarAvisos(avisos) {
        zonaAvisos.innerHTML = "";
        (avisos || []).forEach(msg => {
            const p = document.createElement("p");
            p.className = "aviso aviso-info";
            p.textContent = msg;
            zonaAvisos.appendChild(p);
        });
    }

    // Actualizar el código si cambió la fecha (recodificación)
    function aplicarCodigo(codigo) {
        if (!codigo || codigo === planilla.dataset.codigo) return;
        const nuevaKey = "sellado_nota_" + codigo;
        try { sessionStorage.setItem(nuevaKey, notaGeneral.value); sessionStorage.removeItem(notaKey); } catch (e) {}
        notaKey = nuevaKey;
        planilla.dataset.codigo = codigo;
        spanCodigo.textContent = codigo;
    }

    /* =========================================
       GUARDADO PROGRESIVO
    ========================================= */
    async function guardar() {
        if (guardando || finalizando) return false;
        guardando = true;
        clearTimeout(temporizador);
        setIndicador("guardando", "Guardando…");

        try {
            const res  = await fetch("../spreadsheet/saveSpreadsheet.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(recolectar())
            });
            const data = await res.json();

            if (!data.ok) {
                setIndicador("error", data.error || "Error al guardar");
                return false;
            }
            aplicarCodigo(data.codigo);
            sinGuardar = false;
            setIndicador("ok", "Guardado " + data.guardado_en);
            mostrarAvisos(data.avisos);
            return true;

        } catch (e) {
            setIndicador("error", "Sin conexión. Se reintenta solo.");
            return false;
        } finally {
            guardando = false;
        }
    }

    // Autoguardado con retardo tras cada cambio
    function marcarCambio() {
        sinGuardar = true;
        if (!guardando) setIndicador("pendiente", "Cambios sin guardar");
        clearTimeout(temporizador);
        temporizador = setTimeout(guardar, 1500);
    }

    /* =========================================
       ROWSPAN DE LA COLUMNA MÁQUINA / OPERARIO
    ========================================= */
    function recalcularRowspan(tb) {
        const span = tb.querySelectorAll(".fila-entrada").length + 1; // + fila "+ Entrada"
        tb.querySelector(".col-maquina").rowSpan  = span;
        tb.querySelector(".col-operario").rowSpan = span;
    }

    /* =========================================
       EVENTOS DE LA PLANILLA
    ========================================= */
    planilla.addEventListener("input", e => {
        // Resaltar cuando se usa "Otro operario"
        if (e.target.classList.contains("f-otro-operario")) {
            const bloque = e.target.closest(".col-operario");
            const usa = e.target.value.trim() !== "";
            bloque.querySelector(".f-operario").disabled = usa;
            bloque.classList.toggle("usa-otro", usa);
        }
        marcarCambio();
    });
    planilla.addEventListener("change", marcarCambio);

    // Cambiar la fecha del turno (recodifica la planilla al guardar)
    inputFecha.addEventListener("change", marcarCambio);

    // Agregar / quitar entradas
    planilla.addEventListener("click", e => {

        // + Entrada
        if (e.target.classList.contains("btn-entrada")) {
            const tb = e.target.closest(".grupo-maquina");
            if (tb.querySelectorAll(".fila-entrada").length >= MAX_ENTRADAS) {
                tb.classList.add("tope");
                return;
            }
            const tr = tplEntrada.content.firstElementChild.cloneNode(true);
            tb.querySelector(".fila-add").before(tr);
            recalcularRowspan(tb);
            if (tb.querySelectorAll(".fila-entrada").length >= MAX_ENTRADAS) tb.classList.add("tope");
            marcarCambio();
        }

        // Quitar entrada
        if (e.target.classList.contains("btn-quitar")) {
            const tb    = e.target.closest(".grupo-maquina");
            const fila  = e.target.closest(".fila-entrada");
            const filas = [...tb.querySelectorAll(".fila-entrada")];

            if (filas.length <= 1) {
                // Única entrada: solo se limpia
                fila.querySelectorAll("input").forEach(i => i.value = "");
                fila.querySelectorAll("select").forEach(s => s.selectedIndex = 0);
            } else {
                // Si se quita la primera, mover las celdas máquina/operario a la siguiente
                if (fila === filas[0]) {
                    const sig = filas[1];
                    sig.insertBefore(fila.querySelector(".col-operario"), sig.firstChild);
                    sig.insertBefore(fila.querySelector(".col-maquina"), sig.firstChild);
                }
                fila.remove();
            }
            tb.classList.remove("tope");
            recalcularRowspan(tb);
            marcarCambio();
        }
    });

    /* =========================================
       FINALIZAR TURNO
    ========================================= */
    btnFinalizar.addEventListener("click", () => {
        if (!finalizando) abrirModal("modalFinalizar");
    });

    btnConfirmar.addEventListener("click", async () => {
        if (finalizando) return;               // anti doble clic
        finalizando = true;
        btnConfirmar.disabled = true;
        btnFinalizar.disabled = true;
        btnConfirmar.textContent = "Procesando…";

        try {
            const res  = await fetch("../spreadsheet/finalizeSpreadsheet.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(recolectar())
            });
            const data = await res.json();

            if (!data.ok) {
                alert(data.error || "No se pudo finalizar el turno.");
                finalizando = false;
                btnConfirmar.disabled = false;
                btnFinalizar.disabled = false;
                btnConfirmar.textContent = "Sí, finalizar";
                return;
            }

            // Éxito: la nota ya está en el PDF, se limpia el borrador local
            sinGuardar = false;
            try { sessionStorage.removeItem(notaKey); } catch (e) {}
            cerrarModal("modalFinalizar");

            const verPdf = document.getElementById("btnVerPdf");
            if (data.pdf_url) {
                verPdf.href = data.pdf_url;
                verPdf.style.display = "";
            } else {
                verPdf.style.display = "none";
            }
            document.getElementById("resultadoTexto").textContent =
                data.yaHecho
                    ? "Este turno ya estaba exportado a Google Sheets."
                    : "Turno finalizado: " + data.total + " registros enviados a REGISTROS y PDF guardado en Drive. " +
                      "Impórtalos desde el Dashboard para verlos en el Historial.";
            mostrarAvisos(data.avisos);
            abrirModal("modalResultado");

        } catch (e) {
            alert("Error de conexión al finalizar. Intenta de nuevo.");
            finalizando = false;
            btnConfirmar.disabled = false;
            btnFinalizar.disabled = false;
            btnConfirmar.textContent = "Sí, finalizar";
        }
    });

    /* =========================================
       PROTECCIONES
    ========================================= */
    // No avisar de cambios sin guardar tras confirmar "Cancelar"
    const formCancelar = document.getElementById("formCancelarTurno");
    if (formCancelar) {
        formCancelar.addEventListener("submit", e => {
            if (!e.defaultPrevented) finalizando = true;
        });
    }

    // Avisar si hay cambios sin guardar
    window.addEventListener("beforeunload", e => {
        if (sinGuardar && !finalizando) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    // Autoguardado periódico de respaldo
    setInterval(() => { if (sinGuardar && !guardando) guardar(); }, 60000);
}
