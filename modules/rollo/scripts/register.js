// Formulario de Rollos

const formRollo = document.getElementById("formRegistroRollo");

if (formRollo) {
    // Reparar cierres pendientes
    fetch("../spreadsheet/closeSpreadsheet.php").catch(() => {});

    const aviso         = document.getElementById("avisoRollo");
    const btnRegistrar  = document.getElementById("btnRegistrarRollo");
    const btnVolver      = document.getElementById("btnVolverRollo");
    const campoFecha     = document.getElementById("fechaRollo");
    let enviando = false;        // evita doble registro

    /* =========================================
       AVISO (toast compartido: ver modules/shared/alertToast.js)
    ========================================= */
    const avisoToast = crearAvisoToast("avisoRollo", "avisoRolloTexto", "avisoRolloBarra");
    function mostrarAviso(texto, tipo, autoOcultar) { avisoToast.mostrar(texto, tipo, autoOcultar); }
    function ocultarAviso() { avisoToast.ocultar(); }

    // Ocultar aviso al editar
    formRollo.addEventListener("input", () => {
        if (!enviando && !aviso.hidden) ocultarAviso();
    });

    // Solo letras y espacios
    function soloLetras(texto) {
        return texto.replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g, "");
    }
    // Cada palabra con mayúscula inicial
    function capitalizar(texto) {
        const limpio = texto.trim().replace(/\s+/g, " ");
        if (!limpio) return "";
        return limpio
            .toLowerCase()
            .split(" ")
            .map(palabra => palabra.charAt(0).toUpperCase() + palabra.slice(1))
            .join(" ");
    }

    // Operario: casilla Otro al lado
    // Referencia/Color: reemplaza el select
    formRollo.addEventListener("change", e => {
        if (!e.target.classList.contains("tiene-otro")) return;
        const libre = e.target.nextElementSibling;
        const esOperario = e.target.id === "operarioRollo";
        if (e.target.value === "otro") {
            if (!esOperario) e.target.hidden = true;
            libre.hidden = false;
            libre.value = "";
            libre.focus();
        } else {
            libre.hidden = true;
            libre.value = "";
            e.target.hidden = false;
        }
    });

    // Filtra letras mientras se escribe
    formRollo.addEventListener("input", e => {
        if (e.target.classList.contains("campo-libre")) {
            const limpio = soloLetras(e.target.value);
            if (limpio !== e.target.value) e.target.value = limpio;
        }
    });

    // Capitalizar al salir del campo
    formRollo.addEventListener("blur", e => {
        if (!e.target.classList.contains("campo-libre")) return;
        e.target.value = capitalizar(e.target.value);
        if (e.target.id !== "operarioRolloTexto" && e.target.value === "") {
            const select = e.target.previousElementSibling;
            e.target.hidden = true;
            select.hidden = false;
            select.selectedIndex = 0;
        }
    }, true); // blur no burbujea

    // Valor del select u Otro
    function valorConOtro(idSelect) {
        const select = document.getElementById(idSelect);
        return select.value === "otro" ? capitalizar(select.nextElementSibling.value) : select.value;
    }

    // Restaurar select tras Otro
    function restaurarCampoConOtro(idSelect) {
        const select = document.getElementById(idSelect);
        const libre = select.nextElementSibling;
        libre.hidden = true;
        libre.value = "";
        select.selectedIndex = 0;
    }

    /* =========================================
       REFERENCIA SEGÚN LA MÁQUINA (mapaReferenciasMaquina, embebido en register.php)
    ========================================= */
    const selectMaquina    = document.getElementById("maquinaRollo");
    const selectReferencia = document.getElementById("referenciaRollo");

    function poblarReferenciasRollo(idMaquina) {
        const datos = typeof mapaReferenciasMaquina !== "undefined" ? mapaReferenciasMaquina[idMaquina] : null;
        restaurarCampoConOtro("referenciaRollo");
        selectReferencia.innerHTML = '<option value=""></option><option value="otro">Otro</option>';
        if (!datos) return;
        datos.opciones.forEach(op => {
            const option = document.createElement("option");
            option.value = op.id;
            option.textContent = op.nombre;
            selectReferencia.appendChild(option);
        });
    }

    selectMaquina.addEventListener("change", () => poblarReferenciasRollo(Number(selectMaquina.value)));

    // Limpiar campos, conservar fecha
    function limpiarFormulario() {
        restaurarCampoConOtro("operarioRollo");
        selectMaquina.selectedIndex = 0;
        poblarReferenciasRollo(null);
        restaurarCampoConOtro("colorRollo");
        document.getElementById("pesoRolloInput").value = "";
        document.getElementById("pesoRetalInput").value = "";
        document.getElementById("operarioRollo").focus();
    }

    /* =========================================
       BLOQUEOS MIENTRAS SE ESTÁ REGISTRANDO
    ========================================= */
    // Bloquea "Volver" mientras procesa
    if (btnVolver) {
        btnVolver.addEventListener("click", e => {
            if (enviando) e.preventDefault();
        });
    }

    // Avisa antes de cerrar/recargar
    window.addEventListener("beforeunload", e => {
        if (enviando) {
            e.preventDefault();
            e.returnValue = "";
        }
    });

    /* =========================================
       ENVÍO DEL REGISTRO
    ========================================= */
    formRollo.addEventListener("submit", async e => {
        e.preventDefault();
        if (enviando) return; // evita envío repetido
        enviando = true;
        btnRegistrar.disabled = true;
        btnRegistrar.textContent = "Registrando…";
        if (btnVolver) btnVolver.classList.add("bloqueado");
        ocultarAviso();

        const payload = {
            fecha:         campoFecha.value,
            id_operario:   valorConOtro("operarioRollo"),
            id_maquina:    selectMaquina.value,
            id_referencia: valorConOtro("referenciaRollo"),
            id_color:      valorConOtro("colorRollo"),
            peso_rollo:    document.getElementById("pesoRolloInput").value,
            peso_retal:    document.getElementById("pesoRetalInput").value
        };

        try {
            const res  = await fetch("../spreadsheet/saveSpreadsheet.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (!data.ok) {
                mostrarAviso(data.error || "No se pudo registrar.", "error", true);
                return;
            }
            mostrarAviso("Registrado correctamente" + (data.aviso ? " · " + data.aviso : ""), "info", true);
            limpiarFormulario();

        } catch (err) {
            mostrarAviso("Sin conexión. Intenta de nuevo.", "error", true);
        } finally {
            enviando = false;
            btnRegistrar.disabled = false;
            btnRegistrar.textContent = "Registrar";
            if (btnVolver) btnVolver.classList.remove("bloqueado");
        }
    });
}
