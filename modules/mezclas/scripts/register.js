// Planilla de Mezclas

const formFinalizar = document.getElementById("formFinalizarMezcla");

if (formFinalizar) {
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

    // Operario: casilla Otro al lado. Referencia: reemplaza el select.
    // Ninguno de los dos se envía directo, solo alimentan el input oculto
    // "...Valor" que sí tiene name (ver campoConOtroMezcla en register.php).
    function conectarCampoConOtro(prefijo, esOperario) {
        const select = document.getElementById(prefijo);
        const libre  = document.getElementById(prefijo + "Texto");
        const valor  = document.getElementById(prefijo + "Valor");
        if (!select || !libre || !valor) return;

        function sincronizar() {
            valor.value = select.value === "otro" ? capitalizar(libre.value) : select.value;
        }

        select.addEventListener("change", () => {
            const caja = select._selBuscadorWrap || select;
            if (select.value === "otro") {
                if (!esOperario) caja.style.display = "none";
                libre.hidden = false;
                libre.value = "";
                libre.focus();
            } else {
                libre.hidden = true;
                libre.value = "";
                caja.style.display = "";
            }
            sincronizar();
        });

        libre.addEventListener("input", () => {
            const limpio = soloLetras(libre.value);
            if (limpio !== libre.value) libre.value = limpio;
            sincronizar();
        });

        libre.addEventListener("blur", () => {
            libre.value = capitalizar(libre.value);
            sincronizar();
            if (!esOperario && libre.value === "") {
                const base = selDesenvolver(libre.previousElementSibling);
                libre.hidden = true;
                (base._selBuscadorWrap || base).style.display = "";
                base.selectedIndex = 0;
                if (base._refrescar) base._refrescar();
                sincronizar();
            }
        }, true); // blur no burbujea

        sincronizar();
    }

    conectarCampoConOtro("operarioMezcla", true);
    conectarCampoConOtro("referenciaMezcla", false);

    /* =========================================
       FINALIZAR / CANCELAR (confirmación)
    ========================================= */
    let finalizando = false;

    formFinalizar.addEventListener("submit", async e => {
        e.preventDefault();
        if (finalizando) return;
        const ok = await mostrarConfirmacion("", { titulo: "Confirmar finalización", textoSi: "Sí" });
        if (!ok) return;

        finalizando = true;
        const btnFinalizar = document.getElementById("btnFinalizarMezcla");
        if (btnFinalizar) {
            btnFinalizar.disabled = true;
            btnFinalizar.textContent = "Procesando…";
        }
        formFinalizar.submit();
    });

    const formCancelar = document.getElementById("formCancelarMezcla");
    if (formCancelar) {
        formCancelar.addEventListener("submit", async e => {
            e.preventDefault();
            if (finalizando) return;
            const ok = await mostrarConfirmacion(
                "¿Cancelar esta mezcla? Se perderán los datos sin guardar.",
                { peligro: true, textoSi: "Cancelar mezcla" }
            );
            if (!ok) return;
            finalizando = true;
            formCancelar.submit();
        });
    }
}
