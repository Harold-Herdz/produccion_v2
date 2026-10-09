// Instancias de gráficos
window.chartProduccion  = window.chartProduccion  || null;
window.chartReferencias = window.chartReferencias || null;
window.chartMeses       = window.chartMeses       || null;

// Cargar gráficos según filtros
function cargarDatos(tipo){
    let mes    = document.getElementById("filtroMes").value;
    let semana = document.getElementById("filtroSemana").value;

    fetch("../ajax/productionByPeriod.php?tipo=" + tipo + "&mes=" + mes + "&semana=" + semana)
    .then(res => res.json())
    .then(data => {

        // Gráfico de mezclas por fecha
        if(chartProduccion) chartProduccion.destroy();
        const tipo_grafico = (tipo === 'anio') ? 'bar' : 'line';
        chartProduccion = new Chart(document.getElementById('graficoProduccion'), {
            type: tipo_grafico,
            data: {
                labels: data.fechas,
                datasets: [{
                    label: 'Mezclas',
                    data: data.totales,
                    tension: 0.3
                }]
            },
            options: {
                plugins: {
                    legend: { labels: { color: '#4a4a4a' } },
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    },
                    y: {
                        ticks:  { color: '#4a4a4a', stepSize: 1 },
                        border: { color: '#4a4a4a' }
                    }
                }
            }

        });

        // Ordenar referencias por cantidad de mezclas
        let referenciasOrdenadas = data.referencias.map((ref, i) => ({
            referencia: ref,
            total:      data.totales_referencias[i]
        }));
        referenciasOrdenadas.sort((a, b) => b.total - a.total);
        let labelsReferencias = referenciasOrdenadas.map(r => r.referencia);
        let datosReferencias  = referenciasOrdenadas.map(r => r.total);

        // Gráfico de mezclas por referencia
        if(chartReferencias) chartReferencias.destroy();
        chartReferencias = new Chart(document.getElementById('graficoReferencias'), {
            type: 'bar',
            data: {
                labels: labelsReferencias,
                datasets: [{
                    label: 'Mezclas',
                    data: datosReferencias,
                    maxBarThickness: 40
                }]
            },
            options: {
                plugins: {
                    legend: { labels: { color: '#4a4a4a' } },
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    },
                    y: {
                        ticks:  { color: '#4a4a4a', stepSize: 1 },
                        border: { color: '#4a4a4a' }
                    }
                }
            }
        });
    });
}

// Recargar resúmenes por mes
const mes1 = document.getElementById("mes1");
const mes2 = document.getElementById("mes2");

if(mes1 && mes2){

    function actualizarComparacion(){

        const valorMes1 = mes1.value;
        const valorMes2 = mes2.value;

        if (window.guardarScrollPagina) guardarScrollPagina();
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

// Gráfico mensual por año
function cargarGraficoMeses(){
    let anio = document.getElementById("filtroAnioMes").value;

    fetch(`../ajax/productionByMonth.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        if(chartMeses) chartMeses.destroy();

        const nombresMeses = [
            "Ene","Feb","Mar","Abr","May","Jun",
            "Jul","Ago","Sep","Oct","Nov","Dic"
        ];

        // Gráfico de barras por mes
        chartMeses = new Chart(document.getElementById('graficoMeses'), {
            type: 'bar',
            data: {
                labels: data.meses.map(m => nombresMeses[m-1]),
                datasets: [{
                    label: 'Mezclas',
                    data: data.totales
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend:  { labels: { font: { size: 12 }, color: '#4a4a4a' } },
                    tooltip: { bodyFont: { size: 12 }, titleFont: { size: 12 } }
                },
                scales: {
                    x: {
                        ticks:  { font: { size: 13 }, color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    },
                    y: {
                        ticks:  { font: { size: 13 }, color: '#4a4a4a', stepSize: 1 },
                        border: { color: '#4a4a4a' }
                    }
                }
            }
        });
    });

    // Total del año (mezclas)
    fetch(`../ajax/productionByYear.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        document.getElementById("totalAnio").innerText =
            "Total: " + Number(data.total).toLocaleString();
    });
}
cargarGraficoMeses();
