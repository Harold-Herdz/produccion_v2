// Toast con autoocultado
function crearAvisoToast(idContenedor, idTexto, idBarra, segundos) {
  segundos = segundos || 4;
  const el = document.getElementById(idContenedor);
  const texto = document.getElementById(idTexto);
  const barra = document.getElementById(idBarra);
  let temporizador = null;

  function ocultar() {
    clearTimeout(temporizador);
    if (el) el.hidden = true;
  }

  // tipo: info | error
  function mostrar(mensaje, tipo, autoOcultar) {
    if (!el || !texto || !barra) return;
    clearTimeout(temporizador);
    texto.textContent = mensaje;
    el.className = "aviso-toast aviso-" + (tipo || "info");
    el.hidden = false;

    if (autoOcultar === false) {
      barra.style.transition = "none";
      barra.style.width = "0%";
      return;
    }

    barra.style.transition = "none";
    barra.style.width = "100%";
    void barra.offsetWidth;
    barra.style.transition = "width " + segundos + "s linear";
    barra.style.width = "0%";
    temporizador = setTimeout(ocultar, segundos * 1000);
  }

  return { mostrar, ocultar };
}
