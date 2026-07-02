// Variables globales para instancias de gráficos
window.chartProduccion = window.chartProduccion || null;
window.chartOperarios  = window.chartOperarios  || null;
window.chartMeses      = window.chartMeses      || null;

// Cargar gráficos de producción y máquinas según filtros
function cargarDatos(tipo){
    let mes    = document.getElementById("filtroMes").value;
    let semana = document.getElementById("filtroSemana").value;

    fetch("../ajax/getProduccionRollo.php?tipo=" + tipo + "&mes=" + mes + "&semana=" + semana)
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
                plugins: {
                    legend: { labels: { color: 'white' } },
                },
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
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

        // Ordenar máquinas de mayor a menor producción
        let maquinasOrdenadas = data.operarios.map((maq, i) => ({
            maquina: maq,
            total:   data.totales_operarios[i]
        }));
        maquinasOrdenadas.sort((a, b) => b.total - a.total);
        let labelsMaquinas = maquinasOrdenadas.map(m => m.maquina);
        let datosMaquinas  = maquinasOrdenadas.map(m => m.total);

        // Gráfico de producción por máquina
        if(chartOperarios) chartOperarios.destroy();
        chartOperarios = new Chart(document.getElementById('graficoOperarios'), {
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
                plugins: {
                    legend: { labels: { color: 'white' } },
                },
                responsive: true,
                maintainAspectRatio: false,
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

    fetch(`../ajax/getProduccionMesesRollo.php?anio=${anio}`)
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
                    label: 'Producción mensual (kg)',
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

    // Obtener y mostrar total del año en kg
    fetch(`../ajax/getTotalAnioRollo.php?anio=${anio}`)
    .then(res => res.json())
    .then(data => {
        document.getElementById("totalAnio").innerText =
            "Total: " + Number(data.total).toLocaleString() + " kg";
    });
}
cargarGraficoMeses();