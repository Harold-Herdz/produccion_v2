// Planilla de turno de Extrusión (autoguardado)

const listaPesos = document.getElementById("listaPesos");

if (listaPesos) {
    const selReferencia = document.getElementById("selReferencia");
    const selColor      = document.getElementById("selColor");
    const selLamina     = document.getElementById("selLamina");
    const btnAgregar    = document.getElementById("btnAgregarPeso");
    const btnFinalizar  = document.getElementById("btnFinalizar");
    const indicador     = document.getElementById("indicadorGuardado");
    const MAX           = planillaExtrusion.maxPesos;
    const MIN_INICIAL   = 1;

    // Selects por tipo de cambio
    const selectPorTipo = { referencia: selReferencia, color: selColor, lamina: selLamina };
    const etiquetaTipo  = { referencia: "Referencia", color: "Color", lamina: "Lámina P" };

    let sinGuardar  = false;
    let guardando   = false;
    let finalizando = false;
    let temporizador = null;

    /* =========================================
       INDICADOR Y AVISOS
    ========================================= */
    function setIndicador(estado, texto) {
        indicador.className = "indicador-guardado " + estado;
        indicador.querySelector(".texto").textContent = texto;
    }
    // Select buscador compartido
    const hacerBuscable = select => window.hacerSelectBuscable(select);

    /* =========================================
       CAMPO "OTRO" (texto libre; se crea en catálogo solo al finalizar)
    ========================================= */
    // Junto al select/lista aparece una casilla para escribir el valor nuevo
    function agregarOtro(select, contenedor) {
        const wrap = document.createElement("div");
        wrap.className = "ext-otro";
        const libre = document.createElement("input");
        libre.type = "text";
        libre.className = "ext-libre";
        libre.autocomplete = "off";
        libre.placeholder = "Escribe…";
        libre.hidden = true;
        const volver = document.createElement("button");
        volver.type = "button";
        volver.className = "ext-volver";
        volver.title = "Volver a la lista";
        volver.textContent = "↺";
        volver.hidden = true;

        if (contenedor.parentNode) contenedor.parentNode.replaceChild(wrap, contenedor);
        wrap.append(contenedor, libre, volver);
        select._libre = libre;

        const entrar = texto => {
            contenedor.style.display = "none";
            libre.hidden = false;
            volver.hidden = false;
            libre.value = texto || "";
        };
        select._entrarLibre = entrar;
        select.addEventListener("change", () => {
            if (select.value === "otro") { entrar(""); libre.focus(); }
        });
        volver.addEventListener("click", () => {
            libre.value = "";
            libre.hidden = true;
            volver.hidden = true;
            contenedor.style.display = "";
            select.value = "";
            if (select._refrescar) select._refrescar();
            select.dispatchEvent(new Event("change", { bubbles: true }));
        });
        return wrap;
    }

    // Valor de un campo: id del catálogo o "x:texto" si eligió Otro
    function valorCampo(select) {
        if (select.value === "otro") {
            const t = (select._libre ? select._libre.value : "").trim();
            return t ? "x:" + t : "";
        }
        return select.value;
    }

    // Pone un valor (id o "x:texto") en un campo ya preparado
    function fijarCampo(select, valor) {
        if (typeof valor === "string" && valor.startsWith("x:")) {
            select.value = "otro";
            select._entrarLibre(valor.slice(2));
        } else {
            select.value = valor || "";
        }
        if (select._refrescar) select._refrescar();
    }

    /* =========================================
       FILAS Y BLOQUES
    ========================================= */
    function crearFilaPeso(valor) {
        const fila = document.createElement("div");
        fila.className = "ext-fila-peso";
        const etq = document.createElement("span");
        etq.className = "ext-rollo";
        const input = document.createElement("input");
        input.type = "number";
        input.min = "0";
        input.step = "0.01";
        input.inputMode = "decimal";
        input.className = "f-peso";
        input.value = valor || "";
        const quitar = document.createElement("button");
        quitar.type = "button";
        quitar.className = "ext-quitar";
        quitar.title = "Quitar peso";
        quitar.textContent = "×";
        fila.append(etq, input, quitar);
        return fila;
    }

    function crearBloqueCambio(tipo, valor) {
        const bloque = document.createElement("div");
        bloque.className = "ext-bloque-cambio";
        bloque.dataset.tipo = tipo;
        const etq = document.createElement("label");
        etq.textContent = etiquetaTipo[tipo] + ":";
        // Resetea el clon ya envuelto
        const select = selectPorTipo[tipo].cloneNode(true);
        select.removeAttribute("id");
        select.removeAttribute("data-sel-buscador");
        select.style.display = "";
        select.className = "f-cambio";
        // Copia decisión de buscador
        const wrapOriginal = selectPorTipo[tipo]._selBuscadorWrap;
        const inputOriginal = wrapOriginal && wrapOriginal.querySelector(".sel-buscador-input");
        if (inputOriginal) select.dataset.selBuscar = inputOriginal.readOnly ? "no" : "si";
        const campoSelect = agregarOtro(select, hacerBuscable(select));
        fijarCampo(select, valor);
        const quitar = document.createElement("button");
        quitar.type = "button";
        quitar.className = "ext-quitar ext-quitar-bloque";
        quitar.title = "Quitar este cambio";
        quitar.textContent = "×";
        bloque.append(etq, campoSelect, quitar);
        return bloque;
    }

    // Numerar y limitar a 10
    function actualizar() {
        let n = 0;
        let enTramo = 0;
        let ultimoTramo = 0;
        [...listaPesos.children].forEach(el => {
            if (el.classList.contains("ext-bloque-cambio")) {
                enTramo = 0;
            } else {
                n++;
                enTramo++;
                el.querySelector(".ext-rollo").textContent = "Rollo " + String(n).padStart(2, "0") + ":";
                ultimoTramo = enTramo;
            }
        });
        resumir();
        btnAgregar.disabled = ultimoTramo >= MAX;
        // Quitar solo el último
        const filas = [...listaPesos.querySelectorAll(".ext-fila-peso")];
        filas.forEach(f => {
            const sig = f.nextElementSibling;
            const esUltimoDelTramo = !sig || sig.classList.contains("ext-bloque-cambio");
            f.querySelector(".ext-quitar").style.visibility = (esUltimoDelTramo && filas.length > 1) ? "visible" : "hidden";
        });
    }

    /* =========================================
       RESUMEN EN VIVO
    ========================================= */
    const nf = new Intl.NumberFormat("es-CO", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function textoOpcion(select, valor) {
        const op = [...select.options].find(o => o.value === valor && valor !== "");
        if (typeof valor === "string" && valor.startsWith("x:")) return valor.slice(2);
        return op ? op.textContent : "-";
    }
    function resumir() {
        const segs = recolectar().segmentos;
        const pesos = segs.flatMap(s => s.pesos).map(Number).filter(p => p > 0);
        const total = pesos.reduce((a, b) => a + b, 0);
        document.getElementById("resRollos").textContent = pesos.length;
        document.getElementById("resPeso").textContent = nf.format(total) + " kg";
        document.getElementById("resPromedio").textContent = nf.format(pesos.length ? total / pesos.length : 0) + " kg";
        const ultimo = segs[segs.length - 1];
        document.getElementById("resRef").textContent = textoOpcion(selReferencia, ultimo.ref);
        document.getElementById("resColor").textContent = textoOpcion(selColor, ultimo.color);
        document.getElementById("resLamina").textContent = textoOpcion(selLamina, ultimo.lamina);
    }

    /* =========================================
       PAYLOAD (segmentos con valores completos)
    ========================================= */
    function recolectar() {
        let ref = valorCampo(selReferencia), color = valorCampo(selColor), lamina = valorCampo(selLamina);
        const segmentos = [{ cambio: null, ref, color, lamina, pesos: [] }];
        [...listaPesos.children].forEach(el => {
            const actual = segmentos[segmentos.length - 1];
            if (el.classList.contains("ext-bloque-cambio")) {
                const tipo = el.dataset.tipo;
                const valor = valorCampo(el.querySelector(".f-cambio"));
                if (tipo === "referencia") ref = valor;
                if (tipo === "color") color = valor;
                if (tipo === "lamina") lamina = valor;
                segmentos.push({ cambio: tipo, ref, color, lamina, pesos: [] });
            } else {
                actual.pesos.push(el.querySelector(".f-peso").value);
            }
        });
        // Primer tramo usa el encabezado
        segmentos[0].ref = valorCampo(selReferencia);
        segmentos[0].color = valorCampo(selColor);
        segmentos[0].lamina = valorCampo(selLamina);
        return { id: planillaExtrusion.id, segmentos };
    }

    /* =========================================
       RESTAURAR BORRADOR
    ========================================= */
    function restaurar() {
        const borrador = planillaExtrusion.borrador;
        if (borrador.length > 0) {
            fijarCampo(selReferencia, borrador[0].ref);
            fijarCampo(selColor, borrador[0].color);
            fijarCampo(selLamina, borrador[0].lamina);
            borrador.forEach((seg, i) => {
                if (i > 0 && seg.cambio) {
                    const valor = seg.cambio === "referencia" ? seg.ref : (seg.cambio === "color" ? seg.color : seg.lamina);
                    listaPesos.appendChild(crearBloqueCambio(seg.cambio, valor));
                }
                (seg.pesos || []).forEach(p => listaPesos.appendChild(crearFilaPeso(p)));
            });
        }
        // Mínimo 1 fila al inicio
        let cantidad = listaPesos.querySelectorAll(".ext-fila-peso").length;
        while (cantidad < MIN_INICIAL && listaPesos.querySelectorAll(".ext-bloque-cambio").length === 0) {
            listaPesos.appendChild(crearFilaPeso(""));
            cantidad++;
        }
        if (listaPesos.children.length === 0) listaPesos.appendChild(crearFilaPeso(""));
        bloquearConfirmados();
        actualizar();
    }

    // Turno reabierto: bloquea lo que ya se envió a Google
    function bloquearConfirmados() {
        const n = planillaExtrusion.rollosConfirmados;
        if (!n || n <= 0) return;
        let contados = 0;
        [...listaPesos.children].forEach(el => {
            if (el.classList.contains("ext-fila-peso")) {
                if (contados < n) {
                    el.querySelector(".f-peso").readOnly = true;
                    el.classList.add("ext-fila-bloqueada");
                    el.querySelector(".ext-quitar").style.display = "none";
                }
                contados++;
            } else if (el.classList.contains("ext-bloque-cambio") && contados < n) {
                el.classList.add("ext-fila-bloqueada");
                el.querySelector(".ext-quitar-bloque").style.display = "none";
                const select = el.querySelector("select");
                if (select) {
                    select.disabled = true;
                    const wrap = select._selBuscadorWrap;
                    const input = wrap && wrap.querySelector(".sel-buscador-input");
                    if (input) input.disabled = true;
                    const limpiar = wrap && wrap.querySelector(".sel-buscador-limpiar");
                    if (limpiar) limpiar.style.display = "none";
                    if (select._libre) select._libre.readOnly = true;
                }
            }
        });
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
            sinGuardar = false;
            setIndicador("ok", "Guardado " + data.guardado_en);
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
       EVENTOS
    ========================================= */
    const alCambiar = () => { resumir(); marcarCambio(); };
    listaPesos.addEventListener("input", alCambiar);
    listaPesos.addEventListener("change", alCambiar);
    [selReferencia, selColor, selLamina].forEach(s => s.addEventListener("change", alCambiar));

    listaPesos.addEventListener("click", async e => {
        if (!e.target.classList.contains("ext-quitar")) return;

        // Quitar un cambio: sus pesos pasan al tramo anterior
        if (e.target.classList.contains("ext-quitar-bloque")) {
            const bloque = e.target.closest(".ext-bloque-cambio");
            const esBloque = el => el.classList.contains("ext-bloque-cambio");
            let previos = 0;
            for (let el = bloque.previousElementSibling; el && !esBloque(el); el = el.previousElementSibling) previos++;
            let siguientes = 0, conPesos = false;
            for (let el = bloque.nextElementSibling; el && !esBloque(el); el = el.nextElementSibling) {
                siguientes++;
                if (el.querySelector(".f-peso").value !== "") conPesos = true;
            }
            if (previos + siguientes > MAX) {
                await mostrarAviso("No se puede quitar: el tramo anterior pasaría de " + MAX + " pesos.", { tipo: "error" });
                return;
            }
            if (conPesos) {
                const ok = await mostrarConfirmacion("Los pesos de este cambio pasarán al tramo anterior. ¿Quitar el cambio?", { peligro: true, textoSi: "Quitar" });
                if (!ok) return;
            }
            bloque.remove();
            actualizar();
            marcarCambio();
            return;
        }

        e.target.closest(".ext-fila-peso").remove();
        actualizar();
        marcarCambio();
    });

    btnAgregar.addEventListener("click", () => {
        if (btnAgregar.disabled) return;
        const fila = crearFilaPeso("");
        listaPesos.appendChild(fila);
        actualizar();
        fila.querySelector("input").focus();
        marcarCambio();
    });

    // Bloque de cambio (sin peso automático: se agrega con "+ Agregar")
    document.querySelectorAll("[data-cambio]").forEach(btn => {
        btn.addEventListener("click", () => {
            const bloque = crearBloqueCambio(btn.dataset.cambio, "");
            listaPesos.appendChild(bloque);
            actualizar();
            const select = bloque.querySelector("select");
            const foco = (select._selBuscadorWrap || select).querySelector(".sel-buscador-input");
            (foco || select).focus();
            marcarCambio();
        });
    });

    /* =========================================
       FINALIZAR
    ========================================= */
    const btnConfirmar = document.getElementById("btnConfirmarFinalizar");

    btnFinalizar.addEventListener("click", async () => {
        if (!(await guardar())) {
            await mostrarAviso("No se pudo guardar el turno. Revisa la conexión e intenta de nuevo.", { tipo: "error" });
            return;
        }
        abrirModal("modalFinalizar");
    });

    btnConfirmar.addEventListener("click", async () => {
        finalizando = true;
        clearTimeout(temporizador);
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
                cerrarModal("modalFinalizar");
                await mostrarAviso(data.error || "No se pudo finalizar el turno.", { tipo: "error" });
                restaurarBoton();
                return;
            }

            // Éxito: turno finalizado
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
                data.yaHecho ? "Este turno ya estaba exportado" : "Turno finalizado correctamente";
            abrirModal("modalResultado");

        } catch (e) {
            cerrarModal("modalFinalizar");
            await mostrarAviso("Error de conexión al finalizar. Intenta de nuevo.", { tipo: "error" });
            restaurarBoton();
        }
    });

    /* =========================================
       PROTECCIONES
    ========================================= */
    // Cancelar turno: confirmación propia antes de enviar el formulario
    const formCancelar = document.getElementById("formCancelarTurno");
    if (formCancelar) {
        formCancelar.addEventListener("submit", async e => {
            e.preventDefault();
            const ok = await mostrarConfirmacion(
                "¿Cancelar el turno? Se perderán los datos no finalizados de esta planilla.",
                { peligro: true, textoSi: "Cancelar turno" }
            );
            if (!ok) return;
            finalizando = true;
            formCancelar.submit();
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

    agregarOtro(selReferencia, hacerBuscable(selReferencia));
    agregarOtro(selColor, hacerBuscable(selColor));
    agregarOtro(selLamina, hacerBuscable(selLamina));
    // Lo que se escribe en las casillas "Otro" del encabezado también se autoguarda
    document.getElementById("tablaSeleccion").addEventListener("input", () => { resumir(); marcarCambio(); });
    restaurar();
}
