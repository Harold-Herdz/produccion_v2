// Register Rollos: registrar producción y limpiar el formulario para el siguiente

const formRollo = document.getElementById("formRegistroRollo");

if (formRollo) {
    const aviso        = document.getElementById("avisoRollo");
    const btnRegistrar = document.getElementById("btnRegistrarRollo");
    const campoFecha    = document.getElementById("fechaRollo");
    let enviando = false; // evita doble clic

    function mostrarAviso(texto, tipo) {
        aviso.textContent = texto;
        aviso.className = "aviso aviso-" + tipo;
        aviso.hidden = false;
    }

    // "Otro": el select se convierte en texto libre en el mismo lugar
    formRollo.addEventListener("change", e => {
        if (e.target.classList.contains("tiene-otro") && e.target.value === "otro") {
            const libre = e.target.nextElementSibling;
            e.target.hidden = true;
            libre.hidden = false;
            libre.focus();
        }
    });

    // Valor activo de un campo con "Otro": el select o, si ya se convirtió, el input
    function valorConOtro(idSelect) {
        const select = document.getElementById(idSelect);
        return select.hidden ? select.nextElementSibling.value.trim() : select.value;
    }

    // Limpiar los campos de un registro, manteniendo la fecha
    function limpiarFormulario() {
        const operario = document.getElementById("operarioRollo");
        if (operario.hidden) {
            const libre = operario.nextElementSibling;
            libre.hidden = true;
            libre.value = "";
            operario.hidden = false;
        }
        operario.selectedIndex = 0;
        document.getElementById("maquinaRollo").selectedIndex = 0;
        document.getElementById("referenciaRollo").selectedIndex = 0;
        document.getElementById("colorRollo").selectedIndex = 0;
        document.getElementById("pesoRolloInput").value = "";
        document.getElementById("pesoRetalInput").value = "";
        operario.focus();
    }

    formRollo.addEventListener("submit", async e => {
        e.preventDefault();
        if (enviando) return;
        enviando = true;
        btnRegistrar.disabled = true;
        btnRegistrar.textContent = "Registrando…";
        aviso.hidden = true;

        const payload = {
            fecha:         campoFecha.value,
            id_operario:   valorConOtro("operarioRollo"),
            id_maquina:    document.getElementById("maquinaRollo").value,
            id_referencia: document.getElementById("referenciaRollo").value,
            id_color:      document.getElementById("colorRollo").value,
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
                mostrarAviso(data.error || "No se pudo registrar.", "error");
                return;
            }
            mostrarAviso("Registrado correctamente" + (data.aviso ? " · " + data.aviso : ""), "info");
            limpiarFormulario();

        } catch (err) {
            mostrarAviso("Sin conexión. Intenta de nuevo.", "error");
        } finally {
            enviando = false;
            btnRegistrar.disabled = false;
            btnRegistrar.textContent = "Registrar";
        }
    });
}
