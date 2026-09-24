// Aviso tipo "toast": mensaje con barra de tiempo que se autooculta a los pocos
// segundos, para leerlo y que luego desaparezca solo. Compartido por todos los
// formularios/planillas (Sellado, Rollos, y los que se agreguen después).
//
// Uso: en la vista, un bloque con esta forma (ids a elección):
//   <div class="aviso-toast" id="miAviso" hidden>
//     <span class="aviso-toast-texto" id="miAvisoTexto"></span>
//     <div class="aviso-toast-barra" id="miAvisoBarra"></div>
//   </div>
// y en el script:
//   const aviso = crearAvisoToast("miAviso", "miAvisoTexto", "miAvisoBarra");
//   aviso.mostrar("Registrado correctamente", "info", true);
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

  // tipo: "info" | "error". autoOcultar: true = desaparece solo a los `segundos`
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
    void barra.offsetWidth; // fuerza reflow para que la transición sí anime
    barra.style.transition = "width " + segundos + "s linear";
    barra.style.width = "0%";
    temporizador = setTimeout(ocultar, segundos * 1000);
  }

  return { mostrar, ocultar };
}
