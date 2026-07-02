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