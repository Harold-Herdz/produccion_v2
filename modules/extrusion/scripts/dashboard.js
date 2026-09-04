// Variables globales para instancias de gráficos
window.chartProduccion  = window.chartProduccion  || null;
window.chartMaquinas    = window.chartMaquinas    || null;
window.chartMeses       = window.chartMeses       || null;

// Cargar gráficos de producción y operarios según filtros
function cargarDatos(tipo){
    let mes    = document.getElementById("filtroMes").value;
    let semana = document.getElementById("filtroSemana").value;

    fetch("../ajax/productionByPeriod.php?tipo=" + tipo + "&mes=" + mes + "&semana=" + semana)
    .then(res => res.json())
    .then(data => {

        // Gráfico de producción y rollos (línea por mes/semana, barras por año)
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
                    label: 'Rollos',
                    data: data.rollos,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#4a4a4a' } },
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
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    },
                    y: {
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    }
                }
            }
        });

        // Ordenar máquinas de mayor a menor producción
        let maquinasOrdenadas = data.maquinas.map((maq, i) => ({
            maquina: maq,
            total:   data.totales_maquinas[i]
        }));
        maquinasOrdenadas.sort((a, b) => b.total - a.total);
        let labelsMaquinas = maquinasOrdenadas.map(m => m.maquina);
        let datosMaquinas  = maquinasOrdenadas.map(m => m.total);

        // Gráfico de producción de máquinas
        if(chartMaquinas) chartMaquinas.destroy();
        chartMaquinas = new Chart(document.getElementById('graficoMaquinas'), {
            type: 'bar',
            data: {
                labels: labelsMaquinas,
                datasets: [{
                    label: 'Producción por máquina',
                    data: datosMaquinas,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#4a4a4a' } },
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
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    },
                    y: {
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
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

    fetch(`../ajax/productionByMonth.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        if(chartMeses) chartMeses.destroy();

        const meses = [
            "Ene","Feb","Mar","Abr","May","Jun",
            "Jul","Ago","Sep","Oct","Nov","Dic"
        ];

        // Gráfico de barras por mes
        chartMeses = new Chart(document.getElementById('graficoMeses'), {
            type: 'bar',
            data: {
                labels: data.meses.map(m => meses[m-1]),
                datasets: [{
                    label: 'Producción mensual (kg)',
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
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    },
                    y: {
                        ticks:  { color: '#4a4a4a' },
                        border: { color: '#4a4a4a' }
                    }
                }
            }
        });
    });

    // Obtener y mostrar total del año en kg
    fetch(`../ajax/productionByYear.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        document.getElementById("totalAnio").innerText =
            "Total: " + Number(data.total).toLocaleString() + " kg";
    });
}
cargarGraficoMeses();