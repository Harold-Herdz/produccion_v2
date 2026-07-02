// Variables globales para instancias de gráficos
window.chartProduccion = window.chartProduccion || null;
window.chartOperarios  = window.chartOperarios  || null;
window.chartMeses      = window.chartMeses      || null;

// Cargar gráficos de producción y operarios según el tipo de filtro
function cargarDatos(tipo){
    let mes    = document.getElementById("filtroMes").value;
    let semana = document.getElementById("filtroSemana").value;

    fetch("../ajax/getProduccion.php?tipo=" + tipo + "&mes=" + mes + "&semana=" + semana)
    .then(res => res.json())
    .then(data => {

        // Gráfico de producción (línea por mes/semana, barras por año)
        if(chartProduccion) chartProduccion.destroy();
        const tipo_grafico = (tipo === 'anio') ? 'bar' : 'line';
        chartProduccion = new Chart(document.getElementById('graficoProduccion'), {
            type: tipo_grafico,
            data: {
                labels: data.fechas,
                datasets: [{
                    label: 'Producción',
                    data: data.totales,
                    tension: 0.3,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: 'white' } },
                    tooltip: {
                        enabled: true,
                        bodyFont:  { size: 12 },
                        titleFont: { size: 13 },
                        padding: 10,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        ticks:  { color: 'white' },
                        border: { color: 'white' }
                    },
                    y: {
                        ticks:  { color: 'white' },
                        border: { color: 'white' }
                    }
                }
            }
        });

        // Ordenar operarios de mayor a menor producción
        let combinado = data.operarios.map((operario, i) => ({
            nombre: operario,
            total:  data.totales_operarios[i]
        }));
        combinado.sort((a, b) => b.total - a.total);
        let operariosOrdenados = combinado.map(o => o.nombre);
        let totalesOrdenados   = combinado.map(o => o.total);

        // Gráfico de operarios
        if(chartOperarios) chartOperarios.destroy();
        chartOperarios = new Chart(document.getElementById('graficoOperarios'), {
            type: 'bar',
            data: {
                labels: operariosOrdenados,
                datasets: [{
                    label: 'Operarios',
                    data: totalesOrdenados,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { color: 'white' } },
                    tooltip: {
                        enabled: true,
                        bodyFont:  { size: 12 },
                        titleFont: { size: 13 },
                        padding: 10,
                    }
                },
                scales: {
                    x: {
                        ticks:  { color: 'white' },
                        border: { color: 'white' }
                    },
                    y: {
                        ticks:  { color: 'white' },
                        border: { color: 'white' }
                    }
                }
            }
        });
    });
}

// Aplicar valor de meses y recargar resumenes
const mes1 = document.getElementById("mes1");
const mes2 = document.getElementById("mes2");

if(mes1 && mes2){

    function actualizarComparacion(){

        const valorMes1 = mes1.value;
        const valorMes2 = mes2.value;

        window.location.href =
            "?mes1=" + valorMes1 +
            "&mes2=" + valorMes2;
    }

    mes1.addEventListener("change", actualizarComparacion);
    mes2.addEventListener("change", actualizarComparacion);
}

// Aplicar filtros y recargar gráficos
function actualizarFiltros(){
    let semana = document.getElementById("filtroSemana").value;
    if(semana == ""){
        cargarDatos('mes');
    } else {
        cargarDatos('semana');
    }
}
actualizarFiltros();

// Cargar gráfico de producción mensual por año
function cargarGraficoMeses(){
    let anio = document.getElementById("filtroAnioMes").value;

    fetch(`../ajax/getProduccionMeses.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {

        if(chartMeses) chartMeses.destroy();

        const nombresMeses = [
            "Ene","Feb","Mar","Abr","May","Jun",
            "Jul","Ago","Sep","Oct","Nov","Dic"
        ];

        // Obtener y mostrar total del año
        fetch(`../ajax/getTotalAnio.php?anio=${anio}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById("totalAnio").innerText =
                "Total: " + Number(data.total).toLocaleString();
        });

        // Gráfico de barras por mes
        chartMeses = new Chart(document.getElementById('graficoMeses'), {
            type: 'bar',
            data: {
                labels: data.meses.map(m => nombresMeses[m-1]),
                datasets: [{
                    label: 'Producción mensual',
                    data: data.totales
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend:  { labels: { font: { size: 12 }, color: 'white' } },
                    tooltip: { bodyFont: { size: 12 }, titleFont: { size: 12 } }
                },
                scales: {
                    x: {
                        ticks:  { font: { size: 13 }, color: 'white' },
                        border: { color: 'white' }
                    },
                    y: {
                        ticks:  { font: { size: 13 }, color: 'white' },
                        border: { color: 'white' }
                    }
                }
            }
        });
    });
}
cargarGraficoMeses();