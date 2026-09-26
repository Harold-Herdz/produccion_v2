// Mantiene el scroll al recargar
(function () {
    var CLAVE = 'scrollPagina';
    var VIGENCIA_MS = 20000; // solo si recarga enseguida

    function guardar() {
        try {
            sessionStorage.setItem(CLAVE, JSON.stringify({
                ruta: location.pathname,
                y: window.pageYOffset || document.documentElement.scrollTop || 0,
                t: Date.now()
            }));
        } catch (e) { /* almacenamiento bloqueado: se ignora */ }
    }
    // Para navegación con window.location
    window.guardarScrollPagina = guardar;

    // Formularios (guardar scroll)
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.hasAttribute && form.hasAttribute('data-sin-scroll')) return;
        guardar();
    }, true);

    // Enlaces a la misma página
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.target === '_blank' || a.hasAttribute('data-sin-scroll')) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        try {
            var url = new URL(a.href, location.href);
            if (url.origin === location.origin && url.pathname === location.pathname) guardar();
        } catch (err) { /* href inválido */ }
    }, true);

    // Restaurar al cargar
    var guardado = null;
    try {
        guardado = JSON.parse(sessionStorage.getItem(CLAVE) || 'null');
        sessionStorage.removeItem(CLAVE);
    } catch (e) { guardado = null; }

    if (!guardado || guardado.ruta !== location.pathname || Date.now() - guardado.t > VIGENCIA_MS) return;

    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    var restaurar = function () { window.scrollTo(0, guardado.y); };
    restaurar();
    document.addEventListener('DOMContentLoaded', restaurar);
    window.addEventListener('load', function () {
        restaurar();
        // Repetir por alto cambiante
        setTimeout(restaurar, 150);
        setTimeout(restaurar, 500);
    });
})();
