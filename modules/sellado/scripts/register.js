/* =================================================
   REGISTER · PLANILLA DIGITAL DE SELLADO
   Interacción: entradas, guardado progresivo,
   recuperación, avisos y finalización del turno.
================================================= */

const planilla = document.getElementById("planilla");

// Solo corre si hay un turno abierto en pantalla
if (planilla) {

    const codigo        = planilla.dataset.codigo;
    const indicador     = document.getElementById("indicadorGuardado");
    const zonaAvisos    = document.getElementById("zonaAvisos");
    const btnGuardar    = document.getElementById("btnGuardar");
    const btnFinalizar  = document.getElementById("btnFinalizar");
    const btnConfirmar  = document.getElementById("btnConfirmarFinalizar");
    const MAX_ENTRADAS  = 5;

    let sinGuardar  = false;   // hay cambios pendientes
    let guardando   = false;   // guardado en curso
    let finalizando = false;   // finalización en curso
    let temporizador = null;   // debounce de autoguardado

    /* =========================================
       INDICADOR DE ESTADO
    ========================================= */
    function setIndicador(estado, texto) {
        indicador.className = "indicador-guardado " + estado;
        indicador.querySelector(".texto").textContent = texto;
    }

    /* =========================================
       CONSTRUIR LA PLANILLA (payload)
    ========================================= */
    function recolectar() {
        const maquinas = [...planilla.querySelectorAll(".grupo-maquina")].map(tb => {
            const info = tb.querySelector(".fila-info");
            return {
                maquina:       Number(tb.dataset.maquina),
                id_operario:   info.querySelector(".f-operario").value,
                otro_operario: info.querySelector(".f-otro-operario").value.trim(),
                jornada:       info.querySelector(".f-jornada").value,
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
        return { codigo, maquinas };
    }

    /* =========================================
       MOSTRAR AVISOS (operarios nuevos, etc.)
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

    /* =========================================
       GUARDADO PROGRESIVO
    ========================================= */
    async function guardar(silencioso) {
        if (guardando || finalizando) return false;
        guardando = true;
        clearTimeout(temporizador);
        if (!silencioso) btnGuardar.disabled = true;
        setIndicador("guardando", "Guardando…");

        try {
            const res  = await fetch("../ajax/savePlanilla.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(recolectar())
            });
            const data = await res.json();

            if (!data.ok) {
                setIndicador("error", data.error || "Error al guardar");
                return false;
            }
            sinGuardar = false;
            setIndicador("ok", "Guardado " + data.guardado_en);
            mostrarAvisos(data.avisos);
            return true;

        } catch (e) {
            setIndicador("error", "Sin conexión. Reintentar.");
            return false;
        } finally {
            guardando = false;
            btnGuardar.disabled = false;
        }
    }

    // Autoguardado con retardo tras cada cambio
    function marcarCambio() {
        sinGuardar = true;
        if (!guardando) setIndicador("pendiente", "Cambios sin guardar");
        clearTimeout(temporizador);
        temporizador = setTimeout(() => guardar(true), 1500);
    }

    /* =========================================
       EVENTOS DE LA PLANILLA
    ========================================= */
    planilla.addEventListener("input", e => {
        // Resaltar cuando se usa "Otro operario"
        if (e.target.classList.contains("f-otro-operario")) {
            const info = e.target.closest(".fila-info");
            const sel  = info.querySelector(".f-operario");
            const usa  = e.target.value.trim() !== "";
            sel.disabled = usa;
            info.classList.toggle("usa-otro", usa);
        }
        marcarCambio();
    });
    planilla.addEventListener("change", marcarCambio);

    // Agregar / quitar entradas
    planilla.addEventListener("click", e => {

        // + Entrada
        if (e.target.classList.contains("btn-entrada")) {
            const tb    = e.target.closest(".grupo-maquina");
            const filas = tb.querySelectorAll(".fila-entrada");
            if (filas.length >= MAX_ENTRADAS) {
                tb.classList.add("tope");
                return;
            }
            const nueva = filas[filas.length - 1].cloneNode(true);
            nueva.querySelectorAll("input").forEach(i => i.value = "");
            nueva.querySelectorAll("select").forEach(s => s.selectedIndex = 0);
            tb.querySelector(".fila-add").before(nueva);
            if (tb.querySelectorAll(".fila-entrada").length >= MAX_ENTRADAS) {
                tb.classList.add("tope");
            }
            marcarCambio();
        }

        // Quitar entrada
        if (e.target.classList.contains("btn-quitar")) {
            const tb    = e.target.closest(".grupo-maquina");
            const fila  = e.target.closest(".fila-entrada");
            const filas = tb.querySelectorAll(".fila-entrada");
            if (filas.length > 1) {
                fila.remove();
            } else {
                // Última fila: solo se limpia
                fila.querySelectorAll("input").forEach(i => i.value = "");
                fila.querySelectorAll("select").forEach(s => s.selectedIndex = 0);
            }
            tb.classList.remove("tope");
            marcarCambio();
        }
    });

    // Guardar manual
    btnGuardar.addEventListener("click", () => guardar(false));

    /* =========================================
       FINALIZAR TURNO
    ========================================= */
    btnFinalizar.addEventListener("click", () => {
        if (finalizando) return;
        abrirModal("modalFinalizar");
    });

    btnConfirmar.addEventListener("click", async () => {
        if (finalizando) return;               // anti doble clic
        finalizando = true;
        btnConfirmar.disabled = true;
        btnFinalizar.disabled = true;
        btnConfirmar.textContent = "Procesando…";

        try {
            const res  = await fetch("../ajax/finalizarPlanilla.php", {
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

            // Éxito: ya no hay cambios pendientes
            sinGuardar = false;
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
                    ? "Este turno ya estaba finalizado."
                    : "Turno finalizado con " + data.total + " registros. El PDF fue guardado.";
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
    // Avisar si hay cambios sin guardar
    window.addEventListener("beforeunload", e => {
        if (sinGuardar && !finalizando) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    // Autoguardado periódico de respaldo
    setInterval(() => { if (sinGuardar && !guardando) guardar(true); }, 60000);
}
