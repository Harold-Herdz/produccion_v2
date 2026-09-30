// Select con estilo propio (global)
(function () {
    const UMBRAL_BUSCADOR = 10; // más de esta cantidad de opciones -> con buscador
    const sinTildes = t => String(t).normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();
    const textoOp = o => o.textContent.replace(/\s+/g, " ").trim();

    // Etiqueta del campo
    function etiquetaDe(select) {
        const wrap = select._selBuscadorWrap;
        const anterior = wrap && wrap.previousElementSibling;
        if (anterior && anterior.tagName === "LABEL") return anterior.textContent.trim();
        const contenedor = (wrap || select).closest("div");
        const lbl = contenedor && contenedor.querySelector("label");
        return lbl ? lbl.textContent.trim() : "un campo";
    }

    // Envuelve un select
    function hacerSelectBuscable(select) {
        if (select.dataset.selBuscador === "listo") return select._selBuscadorWrap;

        // Buscador: solo zona formulario
        const opciones = [...select.options].filter(o => o.value !== "" && o.value !== "otro");
        const enZonaFormulario = !!select.closest('[data-sel-zona="formulario"]');
        const conBuscador = select.dataset.selBuscar
            ? select.dataset.selBuscar === "si"
            : (enZonaFormulario && opciones.length > UMBRAL_BUSCADOR);

        const wrap = document.createElement("div");
        wrap.className = "sel-buscador";
        const input = document.createElement("input");
        input.type = "text";
        input.className = "sel-buscador-input";
        input.autocomplete = "off";
        input.readOnly = !conBuscador;
        if (conBuscador) input.placeholder = select.dataset.placeholder !== undefined ? select.dataset.placeholder : "Buscar…";
        input.disabled = select.disabled;

        // Solo se puede vaciar si el select ya tiene una opción vacía
        const permiteVacio = !!select.querySelector('option[value=""]');
        const limpiar = document.createElement("button");
        limpiar.type = "button";
        limpiar.className = "sel-buscador-limpiar";
        limpiar.title = "Quitar selección";
        limpiar.tabIndex = -1;
        limpiar.textContent = "×";

        // La lista vive en <body> para que no la recorten tarjetas con overflow:hidden
        const lista = document.createElement("div");
        lista.className = "sel-buscador-lista";
        lista.hidden = true;
        document.body.appendChild(lista);

        if (select.parentNode) select.parentNode.replaceChild(wrap, select);
        wrap.append(select, input, limpiar);
        select.style.display = "none";
        select.dataset.selBuscador = "listo";
        select._selBuscadorWrap = wrap;
        wrap._select = select; // wrap -> select

        const textoActual = () => {
            const op = select.options[select.selectedIndex];
            return op ? textoOp(op) : "";
        };
        const refrescar = () => {
            input.value = textoActual();
            wrap.classList.toggle("tiene-valor", permiteVacio && select.value !== "");
        };
        select._refrescar = refrescar;
        // Resincroniza en cambios externos
        select.addEventListener("change", refrescar);

        function pintar(filtro) {
            lista.innerHTML = "";
            const t = sinTildes(filtro || "");
            let visibles = 0;
            [...select.children].forEach(hijo => {
                const opts = hijo.tagName === "OPTGROUP" ? [...hijo.children] : [hijo];
                const coinciden = opts.filter(o => (o.value !== "" || textoOp(o) !== "") && (!t || sinTildes(textoOp(o)).includes(t)));
                if (!coinciden.length) return;
                if (hijo.tagName === "OPTGROUP") {
                    const titulo = document.createElement("div");
                    titulo.className = "sel-buscador-grupo";
                    titulo.textContent = hijo.label;
                    lista.appendChild(titulo);
                }
                coinciden.forEach(o => {
                    const item = document.createElement("div");
                    item.className = "sel-buscador-op" + (o.value === select.value ? " activa" : "");
                    item.textContent = textoOp(o);
                    item.dataset.valor = o.value;
                    lista.appendChild(item);
                    visibles++;
                });
            });
            if (!visibles) {
                const vacio = document.createElement("div");
                vacio.className = "sel-buscador-vacio";
                vacio.textContent = "Sin resultados";
                lista.appendChild(vacio);
            }
        }
        function ubicar() {
            const r = input.getBoundingClientRect();
            const ancho = Math.max(r.width, 160);
            let izq = r.left;
            if (izq + ancho > window.innerWidth - 8) izq = Math.max(8, window.innerWidth - ancho - 8);
            lista.style.position = "fixed";
            lista.style.top = r.bottom + 2 + "px";
            lista.style.left = izq + "px";
            lista.style.minWidth = ancho + "px";
        }
        const cerrarPorScroll = e => {
            if (e && e.target && (e.target === lista || lista.contains(e.target))) return;
            cerrar();
        };
        const abrir = () => {
            if (input.disabled) return;
            pintar(conBuscador ? input.value : "");
            ubicar();
            lista.hidden = false;
            window.addEventListener("scroll", cerrarPorScroll, true);
            window.addEventListener("resize", cerrarPorScroll);
            if (conBuscador) input.select();
        };
        const cerrar = () => {
            lista.hidden = true;
            refrescar();
            window.removeEventListener("scroll", cerrarPorScroll, true);
            window.removeEventListener("resize", cerrarPorScroll);
        };
        function elegir(valor) {
            select.value = valor;
            select.dispatchEvent(new Event("change", { bubbles: true }));
            cerrar();
            input.blur();
        }

        input.addEventListener("focus", abrir);
        if (conBuscador) {
            input.addEventListener("input", () => { lista.hidden = false; pintar(input.value); });
        }
        input.addEventListener("keydown", e => {
            if (e.key === "Escape") { cerrar(); input.blur(); }
            if (e.key === "Enter") {
                e.preventDefault();
                const primera = lista.querySelector(".sel-buscador-op");
                if (primera) elegir(primera.dataset.valor);
            }
        });
        // El scroll sobre la lista nunca mueve la página, ni al llegar al tope
        lista.addEventListener("wheel", e => {
            e.preventDefault();
            lista.scrollTop += e.deltaY;
        }, { passive: false });
        // Evita cierre prematuro por blur
        lista.addEventListener("mousedown", e => {
            e.preventDefault();
            const item = e.target.closest(".sel-buscador-op");
            if (item) elegir(item.dataset.valor);
        });
        input.addEventListener("blur", cerrar);

        // Quitar selección
        limpiar.addEventListener("mousedown", e => {
            e.preventDefault();
            if (!permiteVacio || select.value === "") return;
            elegir("");
        });

        refrescar();
        return wrap;
    }

    // Envuelve varios select
    function inicializarSelectsBuscables(raiz) {
        (raiz || document).querySelectorAll("select").forEach(select => {
            if (select.dataset.selNativo !== undefined) return; // escape hatch: data-sel-nativo
            if (select.dataset.selBuscador === "listo") return;
            hacerSelectBuscable(select);
        });
    }

    // Helpers de compatibilidad
    window.selSiguiente = select => (select._selBuscadorWrap || select).nextElementSibling;
    window.selDesenvolver = el => (el && el._select) ? el._select : el;

    window.hacerSelectBuscable = hacerSelectBuscable;
    window.inicializarSelectsBuscables = inicializarSelectsBuscables;

    document.addEventListener("DOMContentLoaded", () => inicializarSelectsBuscables(document));

    // Valida required a mano
    document.addEventListener("submit", function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const requeridos = form.querySelectorAll("select[required]");
        for (const select of requeridos) {
            if (select.dataset.selBuscador !== "listo" || select.disabled || select.value !== "") continue;
            e.preventDefault();
            e.stopPropagation();
            const wrap = select._selBuscadorWrap;
            const input = wrap && wrap.querySelector(".sel-buscador-input");
            if (input) {
                input.focus();
                input.classList.add("sel-buscador-input-error");
                setTimeout(() => input.classList.remove("sel-buscador-input-error"), 1500);
            }
            if (window.mostrarAviso) {
                window.mostrarAviso("Completa el campo: " + etiquetaDe(select), { tipo: "error" });
            }
            return;
        }
    }, true);
})();
