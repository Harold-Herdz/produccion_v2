// Abrir modal por ID
function abrirModal(idModal){
    document.getElementById(idModal).style.display = "flex";
}
// Cerrar modal por ID
function cerrarModal(idModal){
    document.getElementById(idModal).style.display = "none";
}
// Número con separador de miles
function formatearNumero(numero){
    return Number(numero).toLocaleString();
}
// Abrir overlay de importar
document.addEventListener("DOMContentLoaded", function () {
    if (new URLSearchParams(location.search).has("importar") && document.getElementById("modalImportar")) {
        abrirModal("modalImportar");
    }
});
