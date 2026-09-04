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

    // Limpiar los campos de un registro, manteniendo la fecha
    function limpiarFormulario() {
        document.getElementById("operarioRollo").selectedIndex = 0;
        document.getElementById("maquinaRollo").selectedIndex = 0;
        document.getElementById("referenciaRollo").selectedIndex = 0;
        document.getElementById("colorRollo").selectedIndex = 0;
        document.getElementById("pesoRolloInput").value = "";
        document.getElementById("pesoRetalInput").value = "";
        document.getElementById("operarioRollo").focus();
    }

    formRollo.addEventListener("submit", async e => {
        e.preventDefault();
        if (enviando) return;
        enviando = true;
        btnRegistrar.disabled = true;
        aviso.hidden = true;

        const payload = {
            fecha:         campoFecha.value,
            id_operario:   document.getElementById("operarioRollo").value,
            id_maquina:    document.getElementById("maquinaRollo").value,
            id_referencia: document.getElementById("referenciaRollo").value,
            id_color:      document.getElementById("colorRollo").value,
            peso_rollo:    document.getElementById("pesoRolloInput").value,
            peso_retal:    document.getElementById("pesoRetalInput").value
        };

        try {
            const res  = await fetch("../spreadsheet/saveRegistro.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (!data.ok) {
                mostrarAviso(data.error || "No se pudo registrar.", "error");
                return;
            }
            mostrarAviso("Registro guardado" + (data.dia_cerrado ? " · día anterior cerrado y PDF generado" : ""), "info");
            limpiarFormulario();

        } catch (err) {
            mostrarAviso("Sin conexión. Intenta de nuevo.", "error");
        } finally {
            enviando = false;
            btnRegistrar.disabled = false;
        }
    });
}
