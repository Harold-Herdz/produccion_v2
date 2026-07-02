// Variables globales para instancias de gráficos
window.chartProduccion  = window.chartProduccion  || null;
window.chartOperarios   = window.chartOperarios   || null;
window.chartMeses       = window.chartMeses       || null;
window.chartReferencias = window.chartReferencias || null;

// Cargar gráficos de producción y operarios según filtros
function cargarDatos(tipo){
    let filtroMes    = document.getElementById("filtroMes").value;
    let filtroSemana = document.getElementById("filtroSemana").value;

    fetch("../ajax/getProduccionPlana.php?tipo=" + tipo + "&mes=" + filtroMes + "&semana=" + filtroSemana)
    .then(res => res.json())
    .then(data => {

        // Gráfico de producción y retal (línea por mes/semana, barras por año)
        if(chartProduccion) chartProduccion.destroy();
        const tipo_grafico = (tipo === 'anio') ? 'bar' : 'line';
        chartProduccion = new Chart(document.getElementById('graficoProduccion'), {
            type: tipo_grafico,
            data: {
                labels: data.fechas,
                datasets: [{
                    label: 'Producción (kg)',
                    data: data.totales,
                    tension: 0.3
                }, {
                    label: 'Retal (kg)',
                    data: data.retales,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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

        // Gráfico de bultos por operario
        if(chartOperarios) chartOperarios.destroy();
        chartOperarios = new Chart(document.getElementById('graficoOperarios'), {
            type: 'bar',
            data: {
                labels: data.operarios,
                datasets: [{
                    label: 'Bultos',
                    data: data.bultos_operarios
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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

// Cargar gráfico de producción y bultos por referencia
function cargarGraficoReferencias(){
    fetch("../ajax/getReferenciasPlana.php")
    .then(res => res.json())
    .then(data => {
        if(chartReferencias) chartReferencias.destroy();
        chartReferencias = new Chart(document.getElementById('graficoReferencias'), {
            type: 'bar',
            data: {
                labels: data.referencias,
                datasets: [{
                    label: 'Producción (kg)',
                    data: data.totales
                }, {
                    label: 'Bultos',
                    data: data.bultos
                }]
            },
            options: {  
                responsive: true,
                maintainAspectRatio: false,
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
    });
}
cargarGraficoReferencias();

// Cargar gráfico de producción mensual por año
function cargarGraficoMeses(){
    let anio = document.getElementById("filtroAnioMes").value;

    fetch(`../ajax/getProduccionMesesPlana.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        if(chartMeses) chartMeses.destroy();

        const meses = ["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"];

        // Gráfico de barras por mes
        chartMeses = new Chart(document.getElementById('graficoMeses'), {
            type: 'bar',
            data: {
                labels: data.meses.map(m => meses[m-1]),
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

    // Obtener y mostrar total del año en kg
    fetch(`../ajax/getTotalAnioPlana.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        document.getElementById("totalAnio").innerText =
            "Total: " + Number(data.total).toLocaleString() + " kg";
    });
}
cargarGraficoMeses();