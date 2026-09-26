/* =====================================================
   INICIO — resumen de toda la página + gráficos y tablas dinámicos por módulo
   ===================================================== */
(function () {
    const panel = document.getElementById("homePanel");
    if (!panel) return;

    const URL_DATOS = panel.dataset.url;
    const BASE = panel.dataset.base;
    const CLAVE_ALMACEN = "homeEstado4:" + panel.dataset.usuario;

    const ACENTOS = { sellado: "#2f7ec2", rollo: "#2f7ec2", plana: "#2f7ec2", extrusion: "#2f7ec2", peletizado: "#2f7ec2" }; // mismo azul en todos los módulos

    const nf = new Intl.NumberFormat("es-CO", { maximumFractionDigits: 2 });
    const fmt = n => nf.format(n);

    let meta = {};                    // módulos, dimensiones y medidas (del servidor)
    const estado = cargarEstado();    // configuración del usuario (se guarda en el navegador)
    const graficos = {};              // instancias Chart.js por módulo
    const cacheGrafico = {};          // filas del gráfico por módulo
    const opcionesCache = {};         // "modulo|dim" -> valores

    /* ---------- estado persistente ---------- */
    function cargarEstado() {
        try {
            const e = JSON.parse(localStorage.getItem(CLAVE_ALMACEN) || "null");
            if (e && typeof e === "object") return e;
        } catch (err) { /* sin almacenamiento */ }
        return {};
    }
    function guardarEstado() {
        try { localStorage.setItem(CLAVE_ALMACEN, JSON.stringify(estado)); } catch (err) { /* ignorar */ }
    }

    /* ---------- utilidades ---------- */
    const el = (tag, clase, texto) => {
        const e = document.createElement(tag);
        if (clase) e.className = clase;
        if (texto !== undefined) e.textContent = texto;
        return e;
    };
    const hoyISO = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const sumarDias = (iso, n) => {
        const d = new Date(iso + "T12:00:00");
        d.setDate(d.getDate() + n);
        return d.toISOString().slice(0, 10);
    };
    const fechaCorta = iso => (iso && /^\d{4}-\d{2}-\d{2}/.test(iso)) ? iso.slice(8, 10) + "/" + iso.slice(5, 7) + "/" + iso.slice(0, 4) : "—";
    const fechaHora = ts => (ts && ts.length >= 16) ? fechaCorta(ts) + " " + ts.slice(11, 16) : "—";

    const MESES = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    const nombreMes = ym => (/^\d{4}-\d{2}$/.test(ym) ? MESES[parseInt(ym.slice(5, 7), 10) - 1] + " " + ym.slice(0, 4) : ym);

    // "2026-09-24" -> "24/09/2026"; "2026-09" -> "Septiembre 2026"; "2026-09 S2" -> "Septiembre 2026 · Semana 2"
    function etiquetaValor(tipoDim, v) {
        if (tipoDim === "fecha" && /^\d{4}-\d{2}-\d{2}$/.test(v)) return fechaCorta(v);
        if (tipoDim === "mes") return nombreMes(v);
        if (tipoDim === "semana" && /^\d{4}-\d{2} S\d$/.test(v)) return nombreMes(v.slice(0, 7)) + " · Semana " + v.slice(9);
        return v;
    }

    function consulta(params) {
        const q = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => {
            if (v === null || v === undefined || v === "") return;
            if (k === "dims") v.forEach(d => q.append("dims[]", d));
            else if (k === "f") Object.entries(v).forEach(([dim, vals]) => vals.forEach(x => q.append("f[" + dim + "][]", x)));
            else q.set(k, v);
        });
        return fetch(URL_DATOS + "?" + q.toString()).then(r => r.json());
    }

    function opciones(modulo, dim) {
        const k = modulo + "|" + dim;
        if (!opcionesCache[k]) {
            opcionesCache[k] = consulta({ accion: "opciones", modulo, dim }).then(r => (r.ok ? r.valores : []));
        }
        return opcionesCache[k];
    }

    /* =====================================================
       RANGO DE FECHAS POR MÓDULO
    ===================================================== */
    const PRESETS = [
        ["hoy", "Hoy"], ["ayer", "Ayer"], ["semana", "Semana (actual)"], ["mes", "Mes (actual)"],
        ["ultimos", "Últimos: N días"], ["anio", "Año (actual)"], ["todo", "Todo"]
    ];
    const NOMBRE_PRESET = { hoy: "Hoy", ayer: "Ayer", semana: "Semana actual", mes: "Mes actual", anio: "Año actual", todo: "Todo el historial" };

    function calcularRango(preset, n) {
        const hoy = hoyISO();
        if (preset === "hoy") return { desde: hoy, hasta: hoy };
        if (preset === "ayer") { const a = sumarDias(hoy, -1); return { desde: a, hasta: a }; }
        if (preset === "semana") {
            const dow = (new Date(hoy + "T12:00:00").getDay() + 6) % 7; // lunes = 0
            return { desde: sumarDias(hoy, -dow), hasta: hoy };
        }
        if (preset === "mes") return { desde: hoy.slice(0, 8) + "01", hasta: hoy };
        if (preset === "ultimos") return { desde: sumarDias(hoy, -(Math.max(1, n || 1) - 1)), hasta: hoy };
        if (preset === "anio") return { desde: hoy.slice(0, 4) + "-01-01", hasta: hoy };
        return { desde: "", hasta: "" }; // todo
    }

    function estadoModulo(m) {
        estado.modulos = estado.modulos || {};
        if (!estado.modulos[m]) {
            estado.modulos[m] = { preset: "todo", ultimos: 30, desde: "", hasta: "", visible: false };
        }
        return estado.modulos[m];
    }

    function textoRango(m) {
        const e = estadoModulo(m);
        if (e.preset === "ultimos") return "Últimos " + e.ultimos + " días";
        if (NOMBRE_PRESET[e.preset]) return NOMBRE_PRESET[e.preset];
        return "Personalizado";
    }

    /* =====================================================
       NAVEGACIÓN + TARJETAS SUPERIORES
    ===================================================== */
    function construirNav() {
        const nav = document.getElementById("homeNav");
        Object.keys(meta).forEach(m => {
            const b = el("button", "btn", meta[m].etiqueta);
            b.type = "button";
            b.addEventListener("click", () => irAModulo(m));
            nav.appendChild(b);
        });
    }
    function irAModulo(m) {
        const sec = document.getElementById("home-" + m);
        if (sec) sec.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    // Línea "etiqueta: valor" de una tarjeta
    function linea(etiqueta, valor, clase) {
        const l = el("div", "home-linea" + (clase ? " " + clase : ""));
        l.append(el("span", "home-linea-etq", etiqueta), el("span", "home-linea-val", valor));
        return l;
    }

    // Pinta (y consulta en segundo plano) los registros pendientes de importar de un módulo
    function lineaPendientes(m, forzar) {
        const cont = el("div", "home-linea");
        cont.append(el("span", "home-linea-etq", "Pendientes por importar"));
        const val = el("span", "home-linea-val", "Consultando…");
        cont.appendChild(val);
        consulta({ accion: "pendientes", modulo: m, forzar: forzar ? 1 : "" }).then(r => {
            if (!r.ok || !r.disponible) { val.textContent = "—"; return; }
            if (r.pendientes === null || r.pendientes === undefined) { val.textContent = "No se pudo consultar"; val.classList.add("alerta"); return; }
            if (r.pendientes === 0) { val.textContent = "Todo importado"; val.classList.add("ok"); }
            else { val.textContent = fmt(r.pendientes) + " por importar"; val.classList.add("alerta"); }
        }).catch(() => { val.textContent = "No se pudo consultar"; });
        return cont;
    }

    function cargarGeneral() {
        consulta({ accion: "general" }).then(r => {
            if (!r.ok) return;
            const cont = document.getElementById("homeResumen");
            cont.innerHTML = "";

            // Tarjeta general
            const gen = el("div", "home-kpi home-kpi-general");
            gen.appendChild(el("div", "home-kpi-titulo", "General"));
            gen.appendChild(el("div", "home-kpi-valor", fmt(r.total_registros)));
            gen.appendChild(el("div", "home-kpi-sub", "Registros totales (todos los módulos)"));
            gen.appendChild(linea("Módulos", String(Object.keys(meta).length)));
            gen.appendChild(linea("Notificaciones por revisar", String(r.notificaciones), r.notificaciones ? "alerta" : ""));
            const cat = linea("Catálogos por exportar", "…");
            gen.appendChild(cat);
            cont.appendChild(gen);
            consulta({ accion: "catalogos" }).then(c => {
                if (!c.ok) return;
                cat.querySelector(".home-linea-val").textContent = c.sin_exportar ? String(c.sin_exportar) : "Ninguno";
                cat.classList.add(c.sin_exportar ? "alerta" : "ok");
            });

            // Una tarjeta por módulo (mes actual + estado)
            Object.keys(meta).forEach(m => {
                const info = meta[m], d = r.modulos[m], est = d.estado;
                const kpi = el("div", "home-kpi");
                kpi.style.setProperty("--acento", ACENTOS[m] || "#2f7ec2");
                kpi.appendChild(el("div", "home-kpi-titulo", info.etiqueta));
                kpi.appendChild(el("div", "home-kpi-valor", fmt(d.mes.medidas[info.principal] || 0)));
                const principal = info.medidas.find(x => x.clave === info.principal);
                kpi.appendChild(el("div", "home-kpi-sub", (principal ? principal.etiqueta : "") + " · mes actual"));
                kpi.appendChild(linea("Registros del mes", fmt(d.mes.registros)));
                kpi.appendChild(linea("Último registro", fechaCorta(est.ultimo_registro)));
                if (est.importa) {
                    kpi.appendChild(linea("Último ID Sheet importado", est.ultimo_id_sheet || "—"));
                    kpi.appendChild(linea("Última importación", fechaHora(est.ultima_importacion)));
                    kpi.appendChild(lineaPendientes(m));
                } else {
                    kpi.appendChild(linea("Importación", "Sin Sheet"));
                }
                kpi.addEventListener("click", () => irAModulo(m));
                cont.appendChild(kpi);
            });
        });
    }

    /* =====================================================
       CATÁLOGOS
    ===================================================== */
    function cargarCatalogos() {
        consulta({ accion: "catalogos" }).then(r => {
            if (!r.ok) return;
            const cont = document.getElementById("homeCatalogos");
            cont.innerHTML = "";

            const cab = el("div", "home-modulo-cab");
            cab.appendChild(el("h3", "", "Catálogos"));
            const a = el("a", "btn btn-secundario", "Administrar");
            a.href = BASE + "/modules/catalogs/views/catalogs.php";
            cab.appendChild(a);
            cont.appendChild(cab);

            const card = el("div", "home-card");
            const tabla = el("table", "tabla home-tabla");
            const trh = tabla.createTHead().insertRow();
            ["Catálogo", "Registros (activos / total)", "Última exportación", "Última importación", "Estado"].forEach(t => {
                const th = el("th", "", t); trh.appendChild(th);
            });
            const tb = tabla.createTBody();
            r.catalogos.forEach(c => {
                const tr = tb.insertRow();
                tr.insertCell().textContent = c.etiqueta;
                tr.insertCell().textContent = fmt(c.activos) + " / " + fmt(c.total);
                tr.insertCell().textContent = fechaHora(c.exportado);
                tr.insertCell().textContent = fechaHora(c.importado);
                const td = tr.insertCell();
                const badge = el("span", "home-badge " + (c.al_dia ? "ok" : "alerta"), c.al_dia ? "Exportado" : "Pendiente por exportar");
                td.appendChild(badge);
            });
            const wrap = el("div", "home-tabla-scroll");
            wrap.appendChild(tabla);
            card.appendChild(wrap);
            cont.appendChild(card);
        });
    }

    /* =====================================================
       FILTROS POR DIMENSIÓN
       "Filtrar por:" [elegir tabla]  ->  aparece un desplegable de esa tabla con
       buscador y casillas. "Nuevo filtro" agrega otra tabla. Solo un desplegable
       abierto a la vez.
    ===================================================== */
    let ddAbierto = null; // { cerrar() } del desplegable abierto
    document.addEventListener("click", e => {
        if (ddAbierto && !e.target.closest(".home-dd")) { ddAbierto.cerrar(); ddAbierto = null; }
    });

    const sinTildes = t => String(t).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

    // obj = estado {filtros:{dim:[valores]}, activos:[dims]}; alCambiar() se llama tras cambiar la selección
    function crearFiltros(modulo, obj, alCambiar) {
        obj.filtros = obj.filtros || {};
        obj.activos = obj.activos || Object.keys(obj.filtros);

        const caja = el("div", "home-filtros");
        const fila = el("div", "home-filtros-fila");
        fila.appendChild(el("span", "home-filtros-titulo", "Filtrar por:"));
        const selDim = el("select");
        const btnNuevo = el("button", "btn btn-secundario", "Nuevo filtro");
        btnNuevo.type = "button";
        fila.append(selDim, btnNuevo);
        const activosDiv = el("div", "home-filtros-activos");
        caja.append(fila, activosDiv);

        const refrescos = {};       // dim -> función que repinta su lista (Semana depende de Mes)
        let agregando = false;
        let temporizador = null;
        const notificar = () => {
            clearTimeout(temporizador);
            temporizador = setTimeout(() => { guardarEstado(); alCambiar(); }, 250);
        };

        function refrescarSelector() {
            const disponibles = meta[modulo].dims.filter(d => !obj.activos.includes(d.clave));
            selDim.innerHTML = "";
            const ph = el("option", ""); ph.value = ""; selDim.appendChild(ph);
            disponibles.forEach(d => { const o = el("option", "", d.etiqueta); o.value = d.clave; selDim.appendChild(o); });
            selDim.value = "";
            const hayActivos = obj.activos.length > 0;
            selDim.hidden = hayActivos && !agregando;
            btnNuevo.hidden = !hayActivos || agregando || !disponibles.length;
            if (hayActivos && !disponibles.length) selDim.hidden = true;
        }
        selDim.addEventListener("change", () => {
            if (!selDim.value) return;
            // Semana depende de Mes: si Mes aún no está, se agrega primero
            if (selDim.value === "semana" && !obj.activos.includes("mes")) obj.activos.push("mes");
            obj.activos.push(selDim.value);
            agregando = false;
            guardarEstado();
            pintarActivos();
        });
        btnNuevo.addEventListener("click", () => { agregando = true; refrescarSelector(); selDim.focus(); });

        function pintarActivos() {
            activosDiv.innerHTML = "";
            Object.keys(refrescos).forEach(k => delete refrescos[k]);
            obj.activos.forEach(clave => {
                const d = meta[modulo].dims.find(x => x.clave === clave);
                if (!d) return;
                const bloque = el("div", "home-filtro-bloque");
                bloque.appendChild(el("span", "home-filtro-nombre", d.etiqueta));
                const dd = crearDesplegable(modulo, d, obj, () => {
                    // Semana depende de Mes: se descartan semanas de meses no elegidos
                    if (d.clave === "mes") podarSemanas();
                    Object.values(refrescos).forEach(f => f());
                    notificar();
                });
                refrescos[d.clave] = dd.refrescar;
                bloque.appendChild(dd.raiz);
                const quitar = el("button", "home-filtro-quitar", "✕");
                quitar.type = "button";
                quitar.title = "Quitar este filtro";
                quitar.addEventListener("click", () => {
                    const tenia = (obj.filtros[d.clave] || []).length > 0;
                    delete obj.filtros[d.clave];
                    obj.activos = obj.activos.filter(x => x !== d.clave);
                    if (d.clave === "mes") podarSemanas();
                    guardarEstado();
                    pintarActivos();
                    if (tenia) alCambiar();
                });
                bloque.appendChild(quitar);
                activosDiv.appendChild(bloque);
            });
            refrescarSelector();
        }
        function podarSemanas() {
            const meses = obj.filtros.mes || [];
            if (obj.filtros.semana) {
                const ok = obj.filtros.semana.filter(v => meses.includes(v.slice(0, 7)));
                if (ok.length) obj.filtros.semana = ok; else delete obj.filtros.semana;
            }
        }
        pintarActivos();
        return caja;
    }

    // Desplegable de selección múltiple con buscador
    function crearDesplegable(modulo, d, obj, alCambiarSel) {
        const raiz = el("div", "home-dd");
        const btn = el("button", "home-dd-btn");
        btn.type = "button";
        const panel = el("div", "home-dd-panel");
        panel.hidden = true;

        const buscar = el("input", "home-dd-buscar");
        buscar.type = "text"; buscar.placeholder = "Buscar…"; buscar.autocomplete = "off";
        const acciones = el("div", "home-dd-acciones");
        const bMarcar = el("button", "", "Marcar todos"); bMarcar.type = "button";
        const bQuitar = el("button", "", "Eliminar todos"); bQuitar.type = "button";
        acciones.append(bMarcar, bQuitar);
        const lista = el("div", "home-dd-lista");
        panel.append(buscar, acciones, lista);
        raiz.append(btn, panel);

        let valores = null;
        const seleccion = () => obj.filtros[d.clave] || [];
        const fijar = arr => { if (arr.length) obj.filtros[d.clave] = arr; else delete obj.filtros[d.clave]; };

        function titulo() {
            const sel = seleccion();
            if (!sel.length) return d.clave === "semana" && !(obj.filtros.mes || []).length ? "Elija un mes" : "Todos";
            if (sel.length === 1) return etiquetaValor(d.tipo, sel[0]);
            return sel.length + " seleccionados";
        }
        function pintarBoton() { btn.textContent = titulo(); }

        // Valores que se pueden mostrar (Semana solo de los meses elegidos en el filtro Mes)
        function disponibles() {
            if (d.clave !== "semana") return valores;
            const meses = obj.filtros.mes || [];
            return valores.filter(v => meses.includes(v.slice(0, 7)));
        }

        function pintarLista() {
            lista.innerHTML = "";
            if (valores === null) { lista.textContent = "Cargando…"; return; }
            if (d.clave === "semana" && !(obj.filtros.mes || []).length) {
                lista.appendChild(el("div", "home-dd-vacio", "Elija un mes en el filtro Mes para ver sus semanas."));
                return;
            }
            const t = sinTildes(buscar.value.trim());
            const visibles = disponibles().filter(v => !t || sinTildes(etiquetaValor(d.tipo, v)).includes(t));
            if (!visibles.length) { lista.appendChild(el("div", "home-dd-vacio", "Sin resultados")); return; }
            let mesActual = "";
            visibles.forEach(v => {
                if (d.clave === "semana" && v.slice(0, 7) !== mesActual) {
                    mesActual = v.slice(0, 7);
                    lista.appendChild(el("div", "home-dd-subtitulo", nombreMes(mesActual)));
                }
                const lab = el("label", "home-dd-op");
                const chk = document.createElement("input");
                chk.type = "checkbox"; chk.value = v;
                chk.checked = seleccion().includes(v);
                chk.addEventListener("change", () => {
                    const sel = new Set(seleccion());
                    if (chk.checked) sel.add(v); else sel.delete(v);
                    fijar(Array.from(sel));
                    pintarBoton();
                    alCambiarSel();
                });
                const texto = d.clave === "semana" ? "Semana " + v.slice(9) : etiquetaValor(d.tipo, v);
                lab.append(chk, document.createTextNode(" " + (texto === "" ? "(vacío)" : texto)));
                lista.appendChild(lab);
            });
        }

        bMarcar.addEventListener("click", () => {
            const t = sinTildes(buscar.value.trim());
            const visibles = disponibles().filter(v => !t || sinTildes(etiquetaValor(d.tipo, v)).includes(t));
            fijar(Array.from(new Set(seleccion().concat(visibles))));
            pintarBoton(); pintarLista(); alCambiarSel();
        });
        bQuitar.addEventListener("click", () => { fijar([]); pintarBoton(); pintarLista(); alCambiarSel(); });
        buscar.addEventListener("input", pintarLista);

        const control = {
            cerrar() { panel.hidden = true; btn.classList.remove("abierto"); },
        };
        btn.addEventListener("click", () => {
            if (!panel.hidden) { control.cerrar(); ddAbierto = null; return; }
            if (ddAbierto) ddAbierto.cerrar();   // solo uno abierto a la vez
            panel.hidden = false;
            btn.classList.add("abierto");
            ddAbierto = control;
            buscar.value = "";
            pintarLista();
            buscar.focus();
            if (valores === null) {
                opciones(modulo, d.clave).then(v => { valores = v; pintarLista(); });
            }
        });

        pintarBoton();
        return {
            raiz,
            refrescar() { pintarBoton(); if (!panel.hidden) pintarLista(); }
        };
    }

    /* =====================================================
       ESTRUCTURA POR MÓDULO
    ===================================================== */
    function estadoGrafico(m) {
        estado.graficos = estado.graficos || {};
        if (!estado.graficos[m]) {
            estado.graficos[m] = {
                dim: "fecha", tipo: "bar",
                series: meta[m].medidas.filter(x => x.defecto).map(x => x.clave),
                rangoDesde: "", rangoHasta: "", filtros: {}
            };
        }
        return estado.graficos[m];
    }
    function estadoTabla(m) {
        estado.tablas = estado.tablas || {};
        if (!estado.tablas[m]) {
            estado.tablas[m] = { dims: meta[m].tabla_dims_defecto.slice(), filtros: {}, orden: null, pagina: 0 };
        }
        return estado.tablas[m];
    }

    function campo(titulo, control) {
        const c = el("div", "home-campo");
        c.appendChild(el("label", "", titulo));
        c.appendChild(control);
        return c;
    }

    function construirModulo(m) {
        const info = meta[m];
        const sec = el("section", "home-modulo");
        sec.id = "home-" + m;
        sec.style.setProperty("--acento", ACENTOS[m] || "#2f7ec2");

        // ----- Cabecera con accesos -----
        const cab = el("div", "home-modulo-cab");
        cab.appendChild(el("h3", "", info.etiqueta));
        const accesos = el("div", "home-accesos");
        if (info.url_hoja) {
            const a = el("a", "btn btn-verde", "Ver Registros");
            a.href = info.url_hoja; a.target = "_blank"; a.rel = "noopener";
            a.title = "Abre el Sheet en Google (con tu cuenta; si eres Lector no puedes editar)";
            accesos.appendChild(a);
        }
        if (info.url_pdfs) {
            const a = el("a", "btn btn-verde", "Ver PDFs");
            a.href = info.url_pdfs; a.target = "_blank"; a.rel = "noopener";
            a.title = "Abre la carpeta de Drive con los PDFs de " + info.etiqueta;
            accesos.appendChild(a);
        }
        if (info.url) {
            const a = el("a", "btn btn-secundario", "Ver Panel");
            a.href = BASE + info.url;
            accesos.appendChild(a);
        }
        cab.appendChild(accesos);
        sec.appendChild(cab);

        // ----- Resumen del módulo -----
        const resumen = el("div", "home-kpi home-resumen-modulo");
        resumen.id = "res-" + m;
        sec.appendChild(resumen);

        // ----- Botón + panel de filtro por fechas -----
        const barra = el("div", "home-filtrar-barra");
        const btnFiltrar = el("button", "btn", "Filtrar fecha");
        btnFiltrar.type = "button";
        const etiquetaRango = el("span", "home-rango-actual");
        etiquetaRango.id = "rango-" + m;
        barra.append(btnFiltrar, etiquetaRango);
        sec.appendChild(barra);

        const e = estadoModulo(m);
        const panelF = el("div", "home-filtro-fechas");
        panelF.hidden = !e.visible;
        btnFiltrar.textContent = e.visible ? "Ocultar" : "Filtrar fecha";

        const selPreset = el("select");
        PRESETS.forEach(([v, t]) => { const o = el("option", "", t); o.value = v; selPreset.appendChild(o); });
        const oPers = el("option", "", "Personalizado"); oPers.value = "personalizado"; selPreset.appendChild(oPers);
        selPreset.value = e.preset;

        // Casilla de "Últimos: N días": ocupa siempre su espacio (visibility) para que Desde/Hasta no se muevan
        const inUlt = el("input"); inUlt.type = "number"; inUlt.min = "1"; inUlt.max = "3650"; inUlt.value = e.ultimos;
        const campoUlt = campo("Días atrás", inUlt);
        campoUlt.classList.add("home-campo-ultimos");
        const mostrarUlt = () => { campoUlt.style.visibility = selPreset.value === "ultimos" ? "visible" : "hidden"; };
        mostrarUlt();

        const inDesde = el("input"); inDesde.type = "date"; inDesde.value = e.desde;
        const inHasta = el("input"); inHasta.type = "date"; inHasta.value = e.hasta;

        const rellenarDesdePreset = () => {
            mostrarUlt();
            if (selPreset.value === "personalizado") return;
            const r = calcularRango(selPreset.value, parseInt(inUlt.value, 10));
            inDesde.value = r.desde; inHasta.value = r.hasta;
        };
        selPreset.addEventListener("change", rellenarDesdePreset);
        inUlt.addEventListener("input", rellenarDesdePreset);
        [inDesde, inHasta].forEach(i => i.addEventListener("input", () => { selPreset.value = "personalizado"; mostrarUlt(); }));

        const aplicar = () => {
            e.preset = selPreset.value;
            e.ultimos = Math.max(1, parseInt(inUlt.value, 10) || 1);
            e.desde = inDesde.value; e.hasta = inHasta.value;
            guardarEstado();
            refrescarModulo(m);
        };
        const bAplicar = el("button", "btn", "Filtrar"); bAplicar.type = "button"; bAplicar.addEventListener("click", aplicar);
        const bLimpiar = el("button", "btn btn-secundario", "Limpiar"); bLimpiar.type = "button";
        bLimpiar.addEventListener("click", () => {
            selPreset.value = "todo"; inUlt.value = 30;
            rellenarDesdePreset();
            aplicar();
        });
        // El mismo botón muestra u oculta el panel y cambia su texto
        btnFiltrar.addEventListener("click", () => {
            e.visible = panelF.hidden;
            panelF.hidden = !e.visible;
            btnFiltrar.textContent = e.visible ? "Ocultar" : "Filtrar fecha";
            guardarEstado();
        });

        panelF.append(campo("Período", selPreset), campoUlt, campo("Desde", inDesde), campo("Hasta", inHasta));
        const acc = el("div", "home-filtro-acciones");
        acc.append(bAplicar, bLimpiar);
        panelF.appendChild(acc);
        sec.appendChild(panelF);

        // ----- Gráfico dinámico -----
        const g = estadoGrafico(m);
        const cardG = el("div", "home-card");
        cardG.appendChild(el("div", "home-card-titulo", "Gráfico"));
        const ctrl = el("div", "home-controles");

        const selX = el("select");
        info.dims.forEach(d => { const o = el("option", "", d.etiqueta); o.value = d.clave; selX.appendChild(o); });
        selX.value = g.dim;
        selX.addEventListener("change", () => { g.dim = selX.value; guardarEstado(); renderGrafico(m); });
        ctrl.appendChild(campo("Eje X", selX));

        const selTipo = el("select");
        [["bar", "Barras"], ["line", "Líneas"]].forEach(([v, t]) => { const o = el("option", "", t); o.value = v; selTipo.appendChild(o); });
        selTipo.value = g.tipo;
        selTipo.addEventListener("change", () => { g.tipo = selTipo.value; guardarEstado(); renderGrafico(m, true); });
        ctrl.appendChild(campo("Tipo", selTipo));

        cardG.appendChild(ctrl);

        const series = el("div", "home-series");
        series.appendChild(el("span", "home-filtros-titulo", "Series:"));
        info.medidas.forEach(md => {
            const lab = el("label", "home-serie");
            const chk = document.createElement("input");
            chk.type = "checkbox";
            chk.checked = g.series.includes(md.clave);
            chk.addEventListener("change", () => {
                g.series = info.medidas.filter(x => (x.clave === md.clave ? chk.checked : g.series.includes(x.clave))).map(x => x.clave);
                if (!g.series.length) { g.series = [md.clave]; chk.checked = true; }
                guardarEstado();
                renderGrafico(m, true);
            });
            const punto = el("span", "home-serie-punto");
            punto.style.background = md.color;
            lab.append(chk, punto, document.createTextNode(" " + md.etiqueta));
            series.appendChild(lab);
        });
        cardG.appendChild(series);

        cardG.appendChild(crearFiltros(m, g, () => renderGrafico(m)));
        const notaG = el("div", "home-nota-periodo"); notaG.dataset.tipo = "g"; cardG.appendChild(notaG);

        const lienzo = el("div", "home-lienzo");
        const canvas = document.createElement("canvas");
        canvas.id = "grafico-" + m;
        lienzo.appendChild(canvas);
        const vacio = el("div", "home-vacio", "Sin datos para este rango");
        vacio.id = "vacio-" + m;
        vacio.hidden = true;
        lienzo.appendChild(vacio);
        cardG.appendChild(lienzo);
        sec.appendChild(cardG);

        // ----- Tabla dinámica -----
        const t = estadoTabla(m);
        const cardT = el("div", "home-card");
        cardT.appendChild(el("div", "home-card-titulo", "Tabla"));
        const colsBox = el("div", "home-series");
        colsBox.appendChild(el("span", "home-filtros-titulo", "Columnas:"));
        info.dims.forEach(d => {
            const lab = el("label", "home-serie");
            const chk = document.createElement("input");
            chk.type = "checkbox";
            chk.checked = t.dims.includes(d.clave);
            chk.addEventListener("change", () => {
                if (chk.checked) t.dims.push(d.clave); else t.dims = t.dims.filter(x => x !== d.clave);
                if (!t.dims.length) { t.dims = [d.clave]; chk.checked = true; }
                if (t.orden && !t.dims.includes(t.orden.col) && !info.medidas.some(x => x.clave === t.orden.col)) t.orden = null;
                t.pagina = 0;
                guardarEstado();
                renderTabla(m);
            });
            lab.append(chk, document.createTextNode(" " + d.etiqueta));
            colsBox.appendChild(lab);
        });
        cardT.appendChild(colsBox);

        cardT.appendChild(crearFiltros(m, t, () => { t.pagina = 0; renderTabla(m); }));
        const notaT = el("div", "home-nota-periodo"); notaT.dataset.tipo = "t"; cardT.appendChild(notaT);

        const envoltura = el("div", "home-tabla-scroll");
        envoltura.id = "tabla-" + m;
        cardT.appendChild(envoltura);
        const pie = el("div", "home-tabla-pie");
        pie.id = "pie-" + m;
        cardT.appendChild(pie);
        sec.appendChild(cardT);

        return sec;
    }

    // ¿El gráfico/tabla tiene un filtro de fecha, semana o mes? Entonces el período de arriba no aplica
    const DIMS_FECHA = ["fecha", "semana", "mes"];
    const filtroDeFecha = obj => DIMS_FECHA.some(k => ((obj.filtros || {})[k] || []).length > 0);
    function rangoAplicado(m, obj) {
        if (filtroDeFecha(obj)) return { desde: "", hasta: "" };
        const e = estadoModulo(m);
        return { desde: e.desde, hasta: e.hasta };
    }
    function pintarNota(m, tipo, obj) {
        const nota = document.querySelector("#home-" + m + " .home-nota-periodo[data-tipo='" + tipo + "']");
        if (!nota) return;
        const e = estadoModulo(m);
        nota.textContent = filtroDeFecha(obj)
            ? "Se usan los filtros de fecha, semana o mes de arriba; el período del módulo no aplica aquí."
            : "Período aplicado: " + textoRango(m) + (e.desde || e.hasta ? " (" + fechaCorta(e.desde) + " – " + fechaCorta(e.hasta) + ")" : "") + " (Cámbialo con el botón Filtrar fecha)";
    }

    // Recarga resumen, gráfico y tabla de un módulo con su rango
    function refrescarModulo(m) {
        const e = estadoModulo(m);
        document.getElementById("rango-" + m).textContent =
            textoRango(m) + (e.desde || e.hasta ? " · " + fechaCorta(e.desde) + " – " + fechaCorta(e.hasta) : "");
        cargarResumenModulo(m);
        renderGrafico(m);
        renderTabla(m);
    }

    /* =====================================================
       RESUMEN DEL MÓDULO
    ===================================================== */
    function cargarResumenModulo(m) {
        const e = estadoModulo(m);
        const info = meta[m];
        const cont = document.getElementById("res-" + m);
        Promise.all([
            consulta({ accion: "resumen", modulo: m, desde: e.desde, hasta: e.hasta }),
            consulta({ accion: "estado", modulo: m })
        ]).then(([rr, re]) => {
            if (!rr.ok || !re.ok) return;
            const r = rr.resumen, est = re.estado;
            cont.innerHTML = "";

            const colPeriodo = el("div", "home-res-col");
            colPeriodo.appendChild(el("div", "home-res-titulo", "Producción · " + textoRango(m)));
            colPeriodo.appendChild(linea("Registros", fmt(r.registros)));
            info.medidas.forEach(md => {
                const principal = md.clave === info.principal;
                colPeriodo.appendChild(linea(md.etiqueta, fmt(r.medidas[md.clave] || 0), principal ? "destacada" : ""));
            });
            colPeriodo.appendChild(linea("Máquinas con producción", fmt(r.maquinas)));
            colPeriodo.appendChild(linea("Días con producción", fmt(r.dias)));

            const colEstado = el("div", "home-res-col");
            colEstado.appendChild(el("div", "home-res-titulo", "Estado del módulo"));
            colEstado.appendChild(linea("Registros totales", fmt(est.total_registros)));
            colEstado.appendChild(linea("Último registro", fechaCorta(est.ultimo_registro)));
            est.seguimiento.forEach(s => colEstado.appendChild(linea(s.etiqueta, fmt(s.valor), s.alerta ? "alerta" : "")));

            const colImport = el("div", "home-res-col");
            colImport.appendChild(el("div", "home-res-titulo", "Importación desde el Sheet"));
            if (est.importa) {
                colImport.appendChild(linea("Último ID Sheet importado", est.ultimo_id_sheet || "—"));
                colImport.appendChild(linea("Fecha de la última importación", fechaHora(est.ultima_importacion)));
                if (est.ultima_importacion_detalle) colImport.appendChild(linea("Resultado", est.ultima_importacion_detalle));
                colImport.appendChild(lineaPendientes(m));
                const a = el("a", "home-enlace", "Ir a importar");
                a.href = BASE + meta[m].url + "?importar=1"; // abre el overlay para elegir "nuevos" o "todo"
                colImport.appendChild(a);
            } else {
                colImport.appendChild(el("div", "home-linea-etq", "Este módulo aún no tiene Sheet ni importación."));
            }
            cont.append(colPeriodo, colEstado, colImport);
        });
    }

    /* =====================================================
       GRÁFICO
    ===================================================== */
    function renderGrafico(m, sinRecargar) {
        const g = estadoGrafico(m);
        const info = meta[m];
        const e = estadoModulo(m);
        const dimInfo = info.dims.find(d => d.clave === g.dim);

        const pintar = filas => {
            const visibles = filas;
            document.getElementById("vacio-" + m).hidden = visibles.length > 0;

            const usados = info.medidas.filter(x => g.series.includes(x.clave));
            const usaY1 = usados.some(x => x.eje === "y1");
            const datasets = usados.map(x => ({
                label: x.etiqueta,
                data: visibles.map(f => f.medidas[x.clave]),
                backgroundColor: g.tipo === "bar" ? x.color : x.color + "33",
                borderColor: x.color,
                borderWidth: 2,
                tension: 0.25,
                fill: false,
                yAxisID: x.eje
            }));
            const config = {
                type: g.tipo,
                data: { labels: visibles.map(f => etiquetaValor(dimInfo.tipo, f.dims[g.dim])), datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: "index", intersect: false },
                    plugins: { legend: { position: "top" } },
                    scales: {
                        x: { title: { display: true, text: dimInfo.etiqueta } },
                        y: { beginAtZero: true, position: "left" },
                        y1: { beginAtZero: true, position: "right", display: usaY1, grid: { drawOnChartArea: false } }
                    }
                }
            };
            if (graficos[m]) graficos[m].destroy();
            graficos[m] = new Chart(document.getElementById("grafico-" + m), config);
        };

        // Al cambiar solo el rango X / series / tipo se reutilizan los datos ya pedidos
        if (sinRecargar && cacheGrafico[m]) { pintar(cacheGrafico[m]); return; }
        pintarNota(m, "g", g);
        const rg = rangoAplicado(m, g);
        consulta({ accion: "datos", modulo: m, dims: [g.dim], desde: rg.desde, hasta: rg.hasta, f: g.filtros }).then(r => {
            cacheGrafico[m] = r.ok ? r.filas : [];
            pintar(cacheGrafico[m]);
        });
    }

    /* =====================================================
       TABLA DINÁMICA
    ===================================================== */
    const POR_PAGINA = 50;

    function renderTabla(m) {
        const t = estadoTabla(m);
        const info = meta[m];
        const e = estadoModulo(m);
        pintarNota(m, "t", t);
        const rt = rangoAplicado(m, t);
        consulta({ accion: "datos", modulo: m, dims: t.dims, desde: rt.desde, hasta: rt.hasta, f: t.filtros }).then(r => {
            const cont = document.getElementById("tabla-" + m);
            const pie = document.getElementById("pie-" + m);
            cont.innerHTML = "";
            if (!r.ok) { cont.textContent = "No se pudieron cargar los datos."; return; }

            let filas = r.filas;
            const dimsCols = r.dims.map(k => info.dims.find(d => d.clave === k));
            const medCols = info.columnas.map(k => info.medidas.find(x => x.clave === k));

            if (t.orden) {
                const { col, dir } = t.orden;
                const esMedida = medCols.some(x => x.clave === col);
                filas = filas.slice().sort((a, b) => {
                    const va = esMedida ? a.medidas[col] : a.dims[col];
                    const vb = esMedida ? b.medidas[col] : b.dims[col];
                    const c = esMedida ? va - vb : String(va).localeCompare(String(vb), "es", { numeric: true });
                    return dir === "asc" ? c : -c;
                });
            }

            const tabla = el("table", "tabla home-tabla");
            const thead = el("thead");
            const trh = el("tr");
            const encabezado = (texto, col, esMedida) => {
                const th = el("th", esMedida ? "num" : "");
                th.textContent = texto + (t.orden && t.orden.col === col ? (t.orden.dir === "asc" ? " ▲" : " ▼") : "");
                th.classList.add("ordenable");
                th.addEventListener("click", () => {
                    t.orden = { col, dir: t.orden && t.orden.col === col && t.orden.dir === "asc" ? "desc" : "asc" };
                    guardarEstado();
                    renderTabla(m);
                });
                trh.appendChild(th);
            };
            dimsCols.forEach(d => encabezado(d.etiqueta, d.clave, false));
            medCols.forEach(x => encabezado(x.etiqueta, x.clave, true));
            thead.appendChild(trh);
            tabla.appendChild(thead);

            const totalPaginas = Math.max(1, Math.ceil(filas.length / POR_PAGINA));
            if (t.pagina >= totalPaginas) t.pagina = totalPaginas - 1;
            const tbody = el("tbody");
            filas.slice(t.pagina * POR_PAGINA, (t.pagina + 1) * POR_PAGINA).forEach(f => {
                const tr = el("tr");
                dimsCols.forEach(d => tr.appendChild(el("td", "", etiquetaValor(d.tipo, f.dims[d.clave]))));
                medCols.forEach(x => tr.appendChild(el("td", "num", fmt(f.medidas[x.clave]))));
                tbody.appendChild(tr);
            });
            if (!filas.length) {
                const tr = el("tr");
                const td = el("td", "", "Sin datos para este rango");
                td.colSpan = dimsCols.length + medCols.length;
                tr.appendChild(td);
                tbody.appendChild(tr);
            }
            tabla.appendChild(tbody);

            if (filas.length) {
                const tfoot = el("tfoot");
                const trf = el("tr");
                const primera = el("td", "", "Total");
                primera.colSpan = dimsCols.length;
                trf.appendChild(primera);
                medCols.forEach(x => trf.appendChild(el("td", "num", fmt(filas.reduce((s, f) => s + f.medidas[x.clave], 0)))));
                tfoot.appendChild(trf);
                tabla.appendChild(tfoot);
            }
            cont.appendChild(tabla);

            pie.innerHTML = "";
            pie.appendChild(el("span", "", fmt(filas.length) + " filas"));
            if (totalPaginas > 1) {
                const ant = el("button", "btn btn-secundario", "‹");
                ant.type = "button"; ant.disabled = t.pagina === 0;
                ant.addEventListener("click", () => { t.pagina--; guardarEstado(); renderTabla(m); });
                const sig = el("button", "btn btn-secundario", "›");
                sig.type = "button"; sig.disabled = t.pagina >= totalPaginas - 1;
                sig.addEventListener("click", () => { t.pagina++; guardarEstado(); renderTabla(m); });
                pie.append(ant, el("span", "", "Página " + (t.pagina + 1) + " de " + totalPaginas), sig);
            }
        });
    }

    /* =====================================================
       ARRANQUE
    ===================================================== */
    consulta({ accion: "meta" }).then(r => {
        if (!r.ok) return;
        meta = r.modulos;

        construirNav();
        cargarGeneral();
        cargarCatalogos();

        const cont = document.getElementById("homeModulos");
        Object.keys(meta).forEach(m => cont.appendChild(construirModulo(m)));
        Object.keys(meta).forEach(refrescarModulo);

        // Llegada desde el visor de hojas (#home-modulo)
        if (location.hash.startsWith("#home-")) {
            setTimeout(() => irAModulo(location.hash.slice(6)), 400);
        }
    });
})();
