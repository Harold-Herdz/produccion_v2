// Abrir modal por ID
function abrirModal(idModal){
    document.getElementById(idModal).style.display = "flex";
}
// Cerrar modal por ID
function cerrarModal(idModal){
    document.getElementById(idModal).style.display = "none";
}
// Formatear número con separadores de miles
function formatearNumero(numero){
    return Number(numero).toLocaleString();
}
// Llegada desde Panel General ("Ir a importar"): abre directamente el overlay de importar
document.addEventListener("DOMContentLoaded", function () {
    if (new URLSearchParams(location.search).has("importar") && document.getElementById("modalImportar")) {
        abrirModal("modalImportar");
    }
});
