// Diálogos propios (reemplazan alert()/confirm() del navegador)
(function () {
    let overlay, caja, tituloEl, mensajeEl, accionesEl;

    // Crea el overlay una sola vez
    function construir() {
        if (overlay) return;
        overlay = document.createElement("div");
        overlay.className = "dlg-overlay";
        overlay.innerHTML =
            '<div class="dlg-caja">' +
                '<div class="dlg-titulo" id="dlgTitulo"></div>' +
                '<div class="dlg-cuerpo">' +
                    '<p class="dlg-mensaje" id="dlgMensaje"></p>' +
                    '<div class="dlg-acciones" id="dlgAcciones"></div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
        caja       = overlay.querySelector(".dlg-caja");
        tituloEl   = overlay.querySelector("#dlgTitulo");
        mensajeEl  = overlay.querySelector("#dlgMensaje");
        accionesEl = overlay.querySelector("#dlgAcciones");
    }

    function abrir() { overlay.classList.add("activo"); }
    function cerrar() { overlay.classList.remove("activo"); }

    function boton(texto, clase) {
        const b = document.createElement("button");
        b.type = "button";
        b.className = "dlg-btn " + clase;
        b.textContent = texto;
        return b;
    }

    // Reemplazo de alert(): un botón; se resuelve al cerrar
    window.mostrarAviso = function (mensaje, opciones) {
        opciones = opciones || {};
        construir();
        caja.className = "dlg-caja" + (opciones.tipo === "error" ? " dlg-peligro" : "");
        tituloEl.textContent = opciones.titulo || (opciones.tipo === "error" ? "Atención" : "Aviso");
        mensajeEl.textContent = mensaje;
        accionesEl.innerHTML = "";
        return new Promise(function (resolve) {
            const bOk = boton(opciones.boton || "Aceptar", "dlg-btn-principal");
            bOk.addEventListener("click", function () { cerrar(); resolve(); });
            accionesEl.appendChild(bOk);
            abrir();
            bOk.focus();
        });
    };

    // Reemplazo de confirm(): Sí/No; se resuelve true o false
    window.mostrarConfirmacion = function (mensaje, opciones) {
        opciones = opciones || {};
        construir();
        caja.className = "dlg-caja" + (opciones.peligro ? " dlg-peligro" : "");
        tituloEl.textContent = opciones.titulo || "Confirmar";
        mensajeEl.textContent = mensaje;
        accionesEl.innerHTML = "";
        return new Promise(function (resolve) {
            const bNo = boton(opciones.textoNo || "No", "dlg-btn-secundario");
            const bSi = boton(opciones.textoSi || "Sí", opciones.peligro ? "dlg-btn-peligro" : "dlg-btn-principal");
            function terminar(valor) { cerrar(); resolve(valor); }
            bNo.addEventListener("click", function () { terminar(false); });
            bSi.addEventListener("click", function () { terminar(true); });
            accionesEl.append(bNo, bSi);
            abrir();
            bNo.focus();
        });
    };

    /* =========================================
       Enlaces "Eliminar" (historial): confirmación estándar
       <a class="btn-eliminar" href="..." data-confirmar="Mensaje...">
       ========================================= */
    document.addEventListener("click", function (e) {
        const a = e.target.closest("a.btn-eliminar[data-confirmar]");
        if (!a) return;
        e.preventDefault();
        mostrarConfirmacion(a.dataset.confirmar, { peligro: true, textoSi: "Eliminar" }).then(function (ok) {
            if (ok) window.location.href = a.href;
        });
    });

    /* =========================================
       Formularios con confirmación estándar
       <form data-confirmar="Mensaje..." data-peligro="1">
       ========================================= */
    document.addEventListener("submit", function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirmar) return;
        e.preventDefault();
        mostrarConfirmacion(form.dataset.confirmar, {
            peligro: form.dataset.peligro === "1",
            textoSi: form.dataset.textoSi || "Sí"
        }).then(function (ok) {
            if (ok) form.submit(); // no vuelve a disparar "submit": pasa de largo
        });
    });
})();
