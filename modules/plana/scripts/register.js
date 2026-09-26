// Formulario de Plana

const formPlana = document.getElementById("formRegistroPlana");

if (formPlana) {
    // Reparar cierres pendientes
    fetch("../spreadsheet/closeSpreadsheet.php").catch(() => {});

    const aviso         = document.getElementById("avisoPlana");
    const btnRegistrar  = document.getElementById("btnRegistrarPlana");
    const btnVolver      = document.getElementById("btnVolverPlana");
    const campoFecha     = document.getElementById("fechaPlana");
    let enviando = false;        // evita doble registro

    /* =========================================
       AVISO (toast compartido: ver modules/shared/alertToast.js)
    ========================================= */
    const avisoToast = crearAvisoToast("avisoPlana", "avisoPlanaTexto", "avisoPlanaBarra");
    function mostrarAviso(texto, tipo, autoOcultar) { avisoToast.mostrar(texto, tipo, autoOcultar); }
    function ocultarAviso() { avisoToast.ocultar(); }

    // Ocultar aviso al editar
    formPlana.addEventListener("input", () => {
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
    formPlana.addEventListener("change", e => {
        if (!e.target.classList.contains("tiene-otro")) return;
        const libre = e.target.nextElementSibling;
        const esOperario = e.target.id === "operarioPlana";
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
    formPlana.addEventListener("input", e => {
        if (e.target.classList.contains("campo-libre")) {
            const limpio = soloLetras(e.target.value);
            if (limpio !== e.target.value) e.target.value = limpio;
        }
    });

    // Capitalizar al salir del campo
    formPlana.addEventListener("blur", e => {
        if (!e.target.classList.contains("campo-libre")) return;
        e.target.value = capitalizar(e.target.value);
        if (e.target.id !== "operarioPlanaTexto" && e.target.value === "") {
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

    const selectMaquina = document.getElementById("maquinaPlana");

    // Limpiar campos, conservar fecha
    function limpiarFormulario() {
        restaurarCampoConOtro("operarioPlana");
        selectMaquina.selectedIndex = 0;
        restaurarCampoConOtro("referenciaPlana");
        ["pesoRolloPlana", "pesoRetalPlana", "bultosPlana", "pesoTotalPlana"].forEach(id => { document.getElementById(id).value = ""; });
        document.getElementById("operarioPlana").focus();
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
    formPlana.addEventListener("submit", async e => {
        e.preventDefault();
        if (enviando) return; // evita envío repetido
        enviando = true;
        btnRegistrar.disabled = true;
        btnRegistrar.textContent = "Registrando…";
        if (btnVolver) btnVolver.classList.add("bloqueado");
        ocultarAviso();

        const payload = {
            fecha:         campoFecha.value,
            id_operario:   valorConOtro("operarioPlana"),
            id_maquina:    selectMaquina.value,
            id_referencia: valorConOtro("referenciaPlana"),
            peso_rollo:    document.getElementById("pesoRolloPlana").value,
            peso_retal:    document.getElementById("pesoRetalPlana").value,
            bultos:        document.getElementById("bultosPlana").value,
            peso_total:    document.getElementById("pesoTotalPlana").value
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
