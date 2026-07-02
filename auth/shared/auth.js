// Abrir modal por ID
function abrirModal(idModal){
    document.getElementById(idModal).style.display = "flex";
}
// Cerrar modal por ID
function cerrarModal(idModal){
    document.getElementById(idModal).style.display = "none";
}
// Cerrar modal de Editar
function abrirEditar(
    id,
    usuario,
    rol,
    estado
){

    document.getElementById("edit_id").value = id;

    document.getElementById("edit_usuario").value = usuario;

    document.getElementById("edit_rol").value = rol;

    document.getElementById("edit_estado").value = estado;

    document.getElementById("edit_contrasena").value = "";

    abrirModal("modalEditar");
}