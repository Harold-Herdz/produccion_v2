// Planilla de turno (autoguardado)

const planilla = document.getElementById("planilla");

// Solo con turno abierto
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
       CAMPOS "OTRO" (el select sigue visible mostrando "Otro"; aparece
       una casilla nueva al lado para escribir el nombre)
    ========================================= */
    // Valor activo (select u Otro)
    function valorConOtro(contenedor, clase) {
        const select = contenedor.querySelector("select." + clase);
        if (!select) return "";
        if (select.value === "otro") {
            const libre = contenedor.querySelector("input." + clase + ".campo-libre");
            return libre ? libre.value.trim() : "";
        }
        return select.value;
    }

    /* =========================================
       CONSTRUIR EL PAYLOAD DE LA PLANILLA
    ========================================= */
    function recolectar() {
        const maquinas = [...planilla.querySelectorAll(".grupo-maquina")].map(tb => {
            const op = tb.querySelector(".col-operario");
            return {
                maquina:     Number(tb.dataset.maquina),
                id_maquina:  Number(tb.dataset.idMaquina),
                id_operario: valorConOtro(op, "f-operario"),
                jornada:     valorConOtro(op, "f-jornada"),
                entradas: [...tb.querySelectorAll(".fila-entrada")].map(tr => ({
                    id_referencia: valorConOtro(tr, "f-ref"),
                    id_color:      valorConOtro(tr, "f-color"),
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
       AVISOS (operarios nuevos, errores): se leen y desaparecen solos
    ========================================= */
    let avisosTimeout = null;
    function mostrarAvisos(avisos) {
        clearTimeout(avisosTimeout);
        zonaAvisos.innerHTML = "";
        avisos = avisos || [];
        avisos.forEach(msg => {
            const p = document.createElement("p");
            p.className = "aviso aviso-info";
            p.textContent = msg;
            zonaAvisos.appendChild(p);
        });
        if (avisos.length > 0) {
            avisosTimeout = setTimeout(() => { zonaAvisos.innerHTML = ""; }, 5000);
        }
    }

    // Recodificar si cambia la fecha
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

    // Autoguardado con retardo
    function marcarCambio() {
        sinGuardar = true;
        if (!guardando) setIndicador("pendiente", "Cambios sin guardar");
        clearTimeout(temporizador);
        temporizador = setTimeout(guardar, 1500);
    }

    /* =========================================
       REFERENCIAS SEGÚN LA MÁQUINA (mapaReferenciasMaquina, embebido en register.php)
    ========================================= */
    function poblarReferenciasFila(tr, idMaquina) {
        const datos = typeof mapaReferenciasMaquina !== "undefined" ? mapaReferenciasMaquina[idMaquina] : null;
        const select = tr.querySelector(".f-ref");
        if (!select || !datos) return;
        select.innerHTML = '<option value=""></option><option value="otro">Otro</option>';
        datos.opciones.forEach(op => {
            const option = document.createElement("option");
            option.value = op.id;
            option.textContent = op.nombre;
            select.appendChild(option);
        });
    }

    /* =========================================
       ROWSPAN DE LA COLUMNA MÁQUINA / OPERARIO
    ========================================= */
    function recalcularRowspan(tb) {
        const span = tb.querySelectorAll(".fila-entrada").length + 1; // + fila "+ Agregar"
        tb.querySelector(".col-maquina").rowSpan  = span;
        tb.querySelector(".col-operario").rowSpan = span;
    }

    /* =========================================
       NOMBRE LIBRE DE OPERARIO ("Otro"): solo letras, capitaliza cada palabra
    ========================================= */
    function soloLetras(texto) {
        return texto.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, "");
    }
    function capitalizarNombre(texto) {
        const limpio = texto.trim().replace(/\s+/g, " ");
        if (!limpio) return "";
        return limpio
            .toLowerCase()
            .split(" ")
            .map(palabra => palabra.charAt(0).toUpperCase() + palabra.slice(1))
            .join(" ");
    }

    /* =========================================
       EVENTOS DE LA PLANILLA
    ========================================= */
    planilla.addEventListener("input", e => {
        if (e.target.classList.contains("f-operario") && e.target.classList.contains("campo-libre")) {
            const limpio = soloLetras(e.target.value);
            if (limpio !== e.target.value) e.target.value = limpio;
        }
        marcarCambio();
    });
    planilla.addEventListener("change", e => {
        if (e.target.classList.contains("tiene-otro")) {
            const wrap    = e.target.closest(".campo-otro-wrap");
            const libre   = wrap.querySelector(".campo-libre");
            const volver  = wrap.querySelector(".btn-volver-lista");
            const esOperario = e.target.classList.contains("f-operario");
            if (e.target.value === "otro") {
                if (esOperario) {
                    // Operario: espacio reservado
                    libre.classList.add("activo");
                } else {
                    // Reemplaza el select por Otro
                    e.target.hidden = true;
                    libre.hidden = false;
                    volver.hidden = false;
                }
                libre.focus();
            } else {
                if (esOperario) {
                    libre.classList.remove("activo");
                } else {
                    libre.hidden = true;
                }
                libre.value = "";
                e.target.hidden = false;
                if (volver) volver.hidden = true;
            }
        }
        marcarCambio();
    });
    // Capitalizar nombre libre
    planilla.addEventListener("blur", e => {
        if (e.target.classList.contains("f-operario") && e.target.classList.contains("campo-libre")) {
            e.target.value = capitalizarNombre(e.target.value);
        }
    }, true); // blur no burbujea

    // Agregar/quitar entradas
    planilla.addEventListener("click", e => {

        // Volver al select
        if (e.target.classList.contains("btn-volver-lista")) {
            const wrap   = e.target.closest(".campo-otro-wrap");
            const select = wrap.querySelector("select");
            const libre  = wrap.querySelector(".campo-libre");
            libre.hidden = true;
            libre.value = "";
            select.hidden = false;
            select.selectedIndex = 0;
            e.target.hidden = true;
            marcarCambio();
        }

        // + Agregar
        if (e.target.classList.contains("btn-entrada")) {
            const tb = e.target.closest(".grupo-maquina");
            if (tb.querySelectorAll(".fila-entrada").length >= MAX_ENTRADAS) {
                tb.classList.add("tope");
                return;
            }
            const tr = tplEntrada.content.firstElementChild.cloneNode(true);
            poblarReferenciasFila(tr, Number(tb.dataset.idMaquina));
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
                // Mover celdas a la siguiente
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
        const restaurarBoton = () => {
            finalizando = false;
            btnConfirmar.disabled = false;
            btnFinalizar.disabled = false;
            btnConfirmar.textContent = "Sí";
        };

        try {
            const res  = await fetch("../spreadsheet/finalizeSpreadsheet.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(recolectar())
            });
            const data = await res.json();

            if (!data.ok) {
                alert(data.error || "No se pudo finalizar el turno.");
                restaurarBoton();
                return;
            }

            // Éxito: limpiar borrador local
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
                    ? "Este turno ya estaba exportado"
                    : "Turno finalizado correctamente";
            mostrarAvisos(data.avisos);
            abrirModal("modalResultado");
            // Turno ya finalizado

        } catch (e) {
            alert("Error de conexión al finalizar. Intenta de nuevo.");
            restaurarBoton();
        }
    });

    /* =========================================
       PROTECCIONES
    ========================================= */
    // Sin aviso tras cancelar
    const formCancelar = document.getElementById("formCancelarTurno");
    if (formCancelar) {
        formCancelar.addEventListener("submit", e => {
            if (!e.defaultPrevented) finalizando = true;
        });
    }

    // Avisar cambios sin guardar
    window.addEventListener("beforeunload", e => {
        if (sinGuardar && !finalizando) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    // Autoguardado periódico de respaldo
    setInterval(() => { if (sinGuardar && !guardando) guardar(); }, 60000);
}
