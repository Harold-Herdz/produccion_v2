// Planilla de turno de Peletizado (autoguardado)

const listaColores = document.getElementById("listaColores");

if (listaColores) {
    const selOperario1   = document.getElementById("selOperario1");
    const selOperario2   = document.getElementById("selOperario2");
    const plantillaSelect = document.getElementById("plantillaSelectColor");
    const btnAgregar     = document.getElementById("btnAgregarColor");
    const btnFinalizar   = document.getElementById("btnFinalizar");
    const txtObs         = document.getElementById("txtObservaciones");
    const indicador      = document.getElementById("indicadorGuardado");
    const MAX             = planillaPeletizado.maxColores;
    const CAMPOS_NUMERICOS = ["alta_retal", "baja", "refiltrado", "soplado", "torta", "limpieza"];

    let sinGuardar  = false;
    let guardando   = false;
    let finalizando = false;
    let temporizador = null;

    /* =========================================
       INDICADOR
    ========================================= */
    function setIndicador(estado, texto) {
        indicador.className = "indicador-guardado " + estado;
        indicador.querySelector(".texto").textContent = texto;
    }
    const hacerBuscable = select => window.hacerSelectBuscable(select);

    /* =========================================
       CAMPO "OTRO" (texto libre; se crea en catálogo solo al finalizar)
    ========================================= */
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
       FILAS DE COLOR
    ========================================= */
    function crearFilaColor(datos) {
        datos = datos || {};
        const fila = document.createElement("tr");
        fila.className = "pel-fila-color";

        const select = plantillaSelect.cloneNode(true);
        select.removeAttribute("id");
        select.removeAttribute("hidden");
        select.removeAttribute("data-sel-buscador");
        select.removeAttribute("data-sel-nativo");
        select.className = "f-color";
        const campoSelect = agregarOtro(select, hacerBuscable(select));
        fijarCampo(select, datos.color || "");
        const tdColor = document.createElement("td");
        tdColor.append(campoSelect);
        fila.append(tdColor);

        CAMPOS_NUMERICOS.forEach(clave => {
            const input = document.createElement("input");
            input.type = "number";
            input.min = "0";
            input.step = "1";
            input.placeholder = "0";
            input.className = "f-" + clave;
            input.value = datos[clave] || "";
            const td = document.createElement("td");
            td.append(input);
            fila.append(td);
        });

        const quitar = document.createElement("button");
        quitar.type = "button";
        quitar.className = "ext-quitar";
        quitar.title = "Quitar fila";
        quitar.textContent = "×";
        const tdQuitar = document.createElement("td");
        tdQuitar.append(quitar);
        fila.append(tdQuitar);

        return fila;
    }

    // Habilita/deshabilita "+ Agregar" según el máximo de filas
    function actualizar() {
        btnAgregar.disabled = listaColores.children.length >= MAX;
    }

    /* =========================================
       PAYLOAD
    ========================================= */
    function recolectar() {
        const colores = [...listaColores.children].map(fila => {
            const datos = { color: valorCampo(fila.querySelector(".f-color")) };
            CAMPOS_NUMERICOS.forEach(clave => { datos[clave] = fila.querySelector(".f-" + clave).value; });
            return datos;
        });
        return {
            id: planillaPeletizado.id,
            borrador: {
                id_operario: valorCampo(selOperario1),
                id_operario2: valorCampo(selOperario2),
                colores,
                observaciones: txtObs.value,
            },
        };
    }

    /* =========================================
       RESTAURAR BORRADOR
    ========================================= */
    function restaurar() {
        const b = planillaPeletizado.borrador || {};
        fijarCampo(selOperario1, b.id_operario || "");
        fijarCampo(selOperario2, b.id_operario2 || "");

        const colores = b.colores || [];
        if (colores.length > 0) {
            colores.forEach(c => listaColores.appendChild(crearFilaColor(c)));
        } else {
            listaColores.appendChild(crearFilaColor({}));
        }
        txtObs.value = b.observaciones || "";
        actualizar();
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

    function marcarCambio() {
        sinGuardar = true;
        if (!guardando) setIndicador("pendiente", "Cambios sin guardar");
        clearTimeout(temporizador);
        temporizador = setTimeout(guardar, 1500);
    }

    /* =========================================
       EVENTOS
    ========================================= */
    const alCambiar = () => marcarCambio();
    listaColores.addEventListener("input", alCambiar);
    listaColores.addEventListener("change", alCambiar);
    selOperario1.addEventListener("change", alCambiar);
    selOperario2.addEventListener("change", alCambiar);
    txtObs.addEventListener("input", alCambiar);

    listaColores.addEventListener("click", e => {
        if (!e.target.classList.contains("ext-quitar")) return;
        e.target.closest(".pel-fila-color").remove();
        actualizar();
        marcarCambio();
    });

    btnAgregar.addEventListener("click", () => {
        if (btnAgregar.disabled) return;
        listaColores.appendChild(crearFilaColor({}));
        actualizar();
        marcarCambio();
    });

    /* =========================================
       FINALIZAR
    ========================================= */
    btnFinalizar.addEventListener("click", async () => {
        if (finalizando) return;
        if (!(await guardar())) {
            await mostrarAviso("No se pudo guardar el turno. Revisa la conexión e intenta de nuevo.", { tipo: "error" });
            return;
        }
        const ok = await mostrarConfirmacion("", { titulo: "Confirmar finalización", textoSi: "Sí" });
        if (!ok) return;

        finalizando = true;
        clearTimeout(temporizador);
        btnFinalizar.disabled = true;
        const textoOriginal = btnFinalizar.textContent;
        btnFinalizar.textContent = "Procesando…";
        const restaurarBoton = () => {
            finalizando = false;
            btnFinalizar.disabled = false;
            btnFinalizar.textContent = textoOriginal;
        };

        try {
            const res  = await fetch("../spreadsheet/finalizeSpreadsheet.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(recolectar())
            });
            const data = await res.json();
            if (!data.ok) {
                await mostrarAviso(data.error || "No se pudo finalizar el turno.", { tipo: "error" });
                restaurarBoton();
                return;
            }

            sinGuardar = false;
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
            await mostrarAviso("Error de conexión al finalizar. Intenta de nuevo.", { tipo: "error" });
            restaurarBoton();
        }
    });

    /* =========================================
       PROTECCIONES
    ========================================= */
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

    window.addEventListener("beforeunload", e => {
        if (sinGuardar && !finalizando) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    setInterval(() => { if (sinGuardar && !guardando) guardar(); }, 60000);

    agregarOtro(selOperario1, hacerBuscable(selOperario1));
    agregarOtro(selOperario2, hacerBuscable(selOperario2));
    restaurar();
}
