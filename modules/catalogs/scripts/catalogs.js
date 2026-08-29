/* =====================================================
   CATÁLOGOS — Interacción de la vista
   ===================================================== */

// Abrir modal por ID
function abrirModal(idModal) {
    document.getElementById(idModal).style.display = "flex";
}

// Cerrar modal por ID
function cerrarModal(idModal) {
    document.getElementById(idModal).style.display = "none";
}

// Confirmar el cambio de estado antes de enviar el formulario
// estadoActual = 1 (activo)  -> se va a inhabilitar
// estadoActual = 0 (inactivo) -> se va a activar
function confirmarEstado(estadoActual) {
    var mensaje = (estadoActual === 1)
        ? "¿Deseas inhabilitar este registro?"
        : "¿Deseas activar este registro?";
    return confirm(mensaje);
}

// Cerrar el modal al hacer clic fuera de la tarjeta
window.addEventListener("click", function (evento) {
    if (evento.target.classList.contains("overlay")) {
        evento.target.style.display = "none";
    }
});
