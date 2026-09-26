<?php
/** @var array $meses */
/** @var string|int $semana_actual */
/** @var int $mes_actual */
/** @var int $mes_anterior */
/** @var int $mes1 */
/** @var int $mes2 */
/** @var float $total */
/** @var float $semana */
/** @var float $mes */
/** @var array $top_maquina */
/** @var float $peso_rollo_mes1 */
/** @var float $peso_retal_mes1 */
/** @var float $peso_total_mes1 */
/** @var float $eficiencia_mes1 */
/** @var float $peso_rollo_mes2 */
/** @var float $peso_retal_mes2 */
/** @var float $peso_total_mes2 */
/** @var float $eficiencia_mes2 */
/** @var array $mejor_dia_mes1 */
/** @var array $peor_dia_mes1 */
/** @var array $mejor_dia_mes2 */
/** @var array $peor_dia_mes2 */
/** @var array $top_maquina_mes1 */
/** @var array $top_maquina_mes2 */
/** @var float $diferencia */
/** @var float $porcentaje */
/** @var mysqli $conexion */
/** @var mysqli_result $res_tabla_fecha */
/** @var mysqli_result $res_tabla_maquina */
/** @var string $ultimo_id_sheet */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar dashboardController.php
require_once dirname(__DIR__) . '/controllers/dashboardController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';
?>

<!-- Contenedor Principal -->
<div class="container">
    <!-- Título -->
    <h2 class="titulo-vista">Pesos de Rollos</h2>

    <!-- KPIs principales -->
    <div class="kpis">
        <!-- KPI total histórico -->
        <div class="card-kpi">
            <p>Total histórico</p>
            <h2><?php echo number_format($total,2); ?> kg</h2>
        </div>
        <!-- KPI semana actual -->
        <div class="card-kpi">
            <p>Peso semanal</p>
            <h2><?php echo number_format($semana,2); ?> kg</h2>
        </div>
        <!-- KPI mes actual -->
        <div class="card-kpi">
            <p>Peso mensual</p>
            <h2><?php echo number_format($mes,2); ?> kg</h2>
        </div>
    </div>

    <!-- Top máquina y top operario -->
    <div class="top-container">
        <div class="kpis-top">
            <div class="card-kpi" id="top-card">
                <p>Top máquina</p>
                <h2>
                    <?php
                    if(!empty($top_maquina)){
                        echo $top_maquina['nombre_maquina'] . "<br><span style='font-size:14px'>(".$top_maquina['total']." kg)</span>";
                    }else{
                        echo "Sin datos";
                    }
                    ?>
                </h2>
            </div>
            <div class="card-kpi" id="top-operario-card">
                <p>Top operario</p>
                <h2>
                    <?php
                    if(!empty($top_operario)){
                        echo $top_operario['nombre_operario'] . "<br><span style='font-size:14px'>(".$top_operario['total']." kg)</span>";
                    }else{
                        echo "Sin datos";
                    }
                    ?>
                </h2>
            </div>
        </div>
    </div>

    <hr class="seccion-divisor">

    <!-- Comparativo de meses -->
    <div class="resumenes">
        <!-- Resumen mes 1 -->
        <div class="resumen-mes1">
            <h3>Resumen de 
                <!-- Mes 1 -->
                <select name="mes1" id="mes1">
                    <?php foreach($meses as $num => $nombre) { ?>
                        <option
                            value="<?= $num ?>"
                            <?= ($num == $mes1) ? "selected" : "" ?>>
                            <?= $nombre ?>        
                        </option>
                    <?php } ?>
                </select>
            </h3>
            <p>
                🏭 Peso rollo: <?php
                echo $peso_rollo_mes1 !== null
                    ? number_format($peso_rollo_mes1,2).' kg'
                    : 'Sin datos';
                ?>
            </p>
            <p>
                ♻️ Peso retal: <?php
                echo $peso_retal_mes1 !== null
                    ? number_format($peso_retal_mes1,2).' kg'
                    : 'Sin datos';
                ?>
            </p>
            <p>
                📦 Peso total: <?php
                echo $peso_total_mes1 !== null
                    ? number_format($peso_total_mes1,2).' kg'
                    : 'Sin datos';
                ?>
            </p>
            <p>
                ⚙️ Eficiencia: <?php 
                echo $eficiencia_mes1 !== null 
                    ? round($eficiencia_mes1,2).'%' 
                    : 'Sin datos'; 
                ?>
            </p>
            <p>
                📅 Mejor día: <?php 
                echo ($mejor_dia_mes1['fecha'] != "Sin datos") 
                    ? date("d M Y", strtotime($mejor_dia_mes1['fecha'])) 
                    : "Sin datos"; 
                ?>
                (<?php echo number_format($mejor_dia_mes1['total'],2); ?> kg)
            </p>
            <p>
                📉 Peor día: <?php 
                echo ($peor_dia_mes1['fecha'] != "Sin datos") 
                    ? date("d M Y", strtotime($peor_dia_mes1['fecha'])) 
                    : "Sin datos"; 
                ?>
                (<?php echo number_format($peor_dia_mes1['total'],2); ?> kg)
            </p>
            <p>
                👷 Mejor operario: <?php 
                echo $top_operario_mes1['nombre_operario'] ?? 'Sin datos'; ?>
                <?php if(!empty($top_operario_mes1['total']) && $top_operario_mes1['total'] > 0){ ?>
                    (<?php echo number_format($top_operario_mes1['total'],2); ?> kg)
                <?php } ?>
            </p>
            <p>
                🔧 Mejor máquina: <?php 
                echo $top_maquina_mes1['nombre_maquina'] ?? 'Sin datos'; ?>
                <?php if(!empty($top_maquina_mes1['total']) && $top_maquina_mes1['total'] > 0){ ?>
                    (<?php echo number_format($top_maquina_mes1['total'],2); ?> kg)
                <?php } ?>
            </p>
        </div>

        <?php
        // Verificar estado 
        $clase_estado = 'neutro';
        if ($diferencia > 0) {
            $clase_estado = 'positivo';
        } elseif ($diferencia < 0) {
            $clase_estado = 'negativo';
        }
        ?>
        <!-- Indicador de variación entre meses -->
        <div class="comparacion <?php echo $clase_estado; ?>">
            <p>
                <?php if ($diferencia > 0): ?>
                    <span>▲ Subió <?php echo round($porcentaje, 1); ?>%</span>
                    <span>+<?php echo number_format($diferencia); ?> kg</span>
                <?php elseif ($diferencia < 0): ?>
                    <span>▼ Bajó <?php echo round($porcentaje, 1); ?>%</span>
                    <span>-<?php echo number_format(abs($diferencia)); ?> kg</span>
                <?php else: ?>
                    <span>Sin cambios</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Resumen mes 2 -->
        <div class="resumen-mes2">
            <h3>Resumen de 
                <!-- Mes 2 -->
                <select name="mes2" id="mes2">
                    <?php foreach($meses as $num => $nombre) { ?>
                        <option
                            value="<?= $num ?>"
                            <?= ($num == $mes2) ? "selected" : "" ?>>
                            <?= $nombre ?>        
                        </option>
                    <?php } ?>
                </select>
            </h3>
            <p>
                🏭 Peso rollo: <?php
                echo $peso_rollo_mes2 !== null
                    ? number_format($peso_rollo_mes2,2).' kg'
                    : 'Sin datos';
                ?>
            </p>
            <p>
                ♻️ Peso retal: <?php
                echo $peso_retal_mes2 !== null
                    ? number_format($peso_retal_mes2,2).' kg'
                    : 'Sin datos';
                ?>
            </p>
            <p>
                📦 Peso total: <?php
                echo $peso_total_mes2 !== null
                    ? number_format($peso_total_mes2,2).' kg'
                    : 'Sin datos';
                ?>
            </p>
            <p>
                ⚙️ Eficiencia: <?php 
                echo $eficiencia_mes2 !== null 
                    ? round($eficiencia_mes2,2).'%' 
                    : 'Sin datos'; 
                ?>
            </p>
            <p>
                📅 Mejor día: <?php 
                echo ($mejor_dia_mes2['fecha'] != "Sin datos") 
                    ? date("d M Y", strtotime($mejor_dia_mes2['fecha'])) 
                    : "Sin datos"; 
                ?>
                (<?php echo number_format($mejor_dia_mes2['total'],2); ?> kg)
            </p>
            <p>
                📉 Peor día: <?php 
                echo ($peor_dia_mes2['fecha'] != "Sin datos") 
                    ? date("d M Y", strtotime($peor_dia_mes2['fecha'])) 
                    : "Sin datos"; 
                ?>
                (<?php echo number_format($peor_dia_mes2['total'],2); ?> kg)
            </p>
            <p>
                👷 Mejor operario: <?php 
                echo $top_operario_mes2['nombre_operario'] ?? 'Sin datos'; ?>
                <?php if(!empty($top_operario_mes2['total']) && $top_operario_mes2['total'] > 0){ ?>
                    (<?php echo number_format($top_operario_mes2['total'],2); ?> kg)
                <?php } ?>
            </p>
            <p>
                🔧 Mejor máquina: <?php 
                echo $top_maquina_mes2['nombre_maquina'] ?? 'Sin datos'; ?>
                <?php if(!empty($top_maquina_mes2['total']) && $top_maquina_mes2['total'] > 0){ ?>
                    (<?php echo number_format($top_maquina_mes2['total'],2); ?> kg)
                <?php } ?>
            </p>
        </div>
    </div>

    <hr class="seccion-divisor">

    <!-- Botón de importar -->
    <a class="btn" id="btnImportar" onclick="abrirModal('modalImportar')">Importar Registros</a>

    <!-- Sección de gráficos -->
    <div class="seccion-graficos">

        <!-- Filtros -->
        <div class="controles">
            <!-- Filtrar por mes -->
            <label class="label" for="filtroMes">
                Mes:
                <select id="filtroMes" onchange="actualizarFiltros()">
                    <?php foreach($meses as $num => $nombre){ ?>
                    <option 
                        value="<?php echo $num; ?>" 
                        <?php if($num == $mes_actual) echo "selected"; ?>>
                        <?php echo $nombre; ?>
                    </option>
                    <?php } ?>
                </select>
            </label>
            <!-- Filtrar por semana segun mes -->
            <label class="label" for="filtroSemana">
                Semana:
                <select id="filtroSemana" onchange="actualizarFiltros()">
                    <!-- Semanas del mes-->
                    <option value = "" <?php if($semana_actual == "") echo "selected"; ?> >
                        Todas
                    </option>
                    <option value = "1" <?php if($semana_actual == "1") echo "selected"; ?> >Semana 1</option>
                    <option value = "2" <?php if($semana_actual == "2") echo "selected"; ?> >Semana 2</option>
                    <option value = "3" <?php if($semana_actual == "3") echo "selected"; ?> >Semana 3</option>
                    <option value = "4" <?php if($semana_actual == "4") echo "selected"; ?> >Semana 4</option>
                    <option value = "5" <?php if($semana_actual == "5") echo "selected"; ?> >Semana 5</option>
                </select>
            </label>
            <!-- Filtro de año por semanas -->
            <button class ="btn" id="btnAnio" onclick="cargarDatos('anio')">Año</button>
        </div>

        <!-- Gráficos de producción y maquina -->
        <div class="grid-graficos">
            <div class="card-grafico">
                <h3>Pesos por Fecha</h3>
                <canvas id="graficoProduccion"></canvas>
            </div>

            <div class="card-grafico">   
                <h3>Peso por Máquina  </h3>
                <canvas id="graficoMaquinas"></canvas>
            </div>
        </div>

    </div>

    <!-- Gráfico mensual por año -->
    <div class="contenedor-grafico">
        <h3>Peso por año (Rollo)</h3>
            <div class="header-grafico-meses">
                <!-- Selector de año -->
                <select id="filtroAnioMes" onchange="cargarGraficoMeses()">
                    <?php for($i=date('Y'); $i>=2023; $i--){ ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php } ?>
                </select>

                <div id="totalAnio" class="total-anio">Total: 0 kg</div>

            </div>
        <canvas id="graficoMeses"></canvas>
    </div>

    <hr class="seccion-divisor">

    <!-- Tablas -->
    <form class="filtro-fechas" method="GET">
        <!-- Filtros de fechas -->
        <label>Desde</label>
            <input type="date" name="desde"
                value="<?php echo $_GET['desde'] ?? date('Y-m-01'); ?>">
        <label>Hasta</label>
            <input type="date" name="hasta"
                value="<?php echo $_GET['hasta'] ?? date('Y-m-d'); ?>">
        <!-- Botón de filtrar -->
        <button class="btn" type="submit">Filtrar</button>
    </form>

    <div class="tabla-dashboard">
        <!-- Tabla de producción por fecha -->
        <h3>Pesos por Fecha</h3>
        <table>
            <tr>
                <th>Fecha</th>
                <th>Peso Rollo (kg)</th>
                <th>Peso Retal (kg)</th>
                <th>Peso Total (kg)</th>
            </tr>
            <?php
            $total_peso_rollo = 0;
            $total_peso_retal = 0;
            $total_peso_total = 0;
            while($row = mysqli_fetch_assoc($res_tabla_fecha)){
            $total_peso_rollo += $row['peso_rollo'];
            $total_peso_retal += $row['peso_retal'];
            $total_peso_total += $row['peso_total'];
            ?>
            <tr>
                <td><?php echo date("d M Y", strtotime($row['fecha'])); ?></td>
                <td><?php echo number_format($row['peso_rollo'],2); ?></td>
                <td><?php echo number_format($row['peso_retal'],2); ?></td>
                <td><?php echo number_format($row['peso_total'],2); ?></td>
            </tr>
            <?php } ?>
            <!-- Fila de total -->
            <tr class="fila-total">
                <td><strong>TOTAL</strong></td>
                <td><strong><?php echo number_format($total_peso_rollo,2); ?></strong></td>
                <td><strong><?php echo number_format($total_peso_retal,2); ?></strong></td>
                <td><strong><?php echo number_format($total_peso_total,2); ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- Tabla de producción por operario -->
    <div class="tabla-dashboard">
        <h3>Pesos por Máquina</h3>
        <table>
            <tr>
                <th>Máquina</th>
                <th>Peso Rollo (kg)</th>
                <th>Peso Retal (kg)</th>
                <th>Peso Total (kg)</th>
            </tr>
            <?php
            $total_peso_rollo = 0;
            $total_peso_retal = 0;
            $total_peso_total = 0;
            while($row = mysqli_fetch_assoc($res_tabla_maquina)){
            $total_peso_rollo += $row['peso_rollo'];
            $total_peso_retal += $row['peso_retal'];
            $total_peso_total += $row['peso_total'];
            ?>
            <tr>
                <td><?php echo $row['nombre_maquina']; ?></td>
                <td><?php echo number_format($row['peso_rollo'],2); ?></td>
                <td><?php echo number_format($row['peso_retal'],2); ?></td>
                <td><?php echo number_format($row['peso_total'],2); ?></td>
            </tr>
            <?php } ?>
            <tr class="fila-total">
                <td><strong>TOTAL</strong></td>
                <td><strong><?php echo number_format($total_peso_rollo,2); ?></strong></td>
                <td><strong><?php echo number_format($total_peso_retal,2); ?></strong></td>
                <td><strong><?php echo number_format($total_peso_total,2); ?></strong></td>
            </tr>
        </table>
    </div>

</div>

<!-- Modal de importación -->
 <div class="overlay" id ="modalImportar">
    <div class="modal">
        <div class="modal-header">
            <h2>Importar Rollos</h2>
            <p>Último ID Importado: <strong><?php echo $ultimo_id_sheet; ?></strong></p>
            <button id="cerrarBtn" onclick="cerrarModal('modalImportar')">X</button>
        </div>
        <!-- Opciones de importación -->
        <div class="btn-row">
            <a class="btn-nuevos" href="<?= BASE_URL ?>/import/controllers/imp_rollo.php?modo=nuevos" >
                <div class="btn-text"><span class="btn-icon">🗲</span>Importar Nuevos<span class="btn-arrow">›</span></div>
            </a>
            <a class="btn-todo" href="<?= BASE_URL ?>/import/controllers/imp_rollo.php?modo=todo" >
                <div class="btn-text"><span class="btn-icon">⟳</span>Reimportar Todo<span class="btn-arrow">›</span></div>
            </a>
        </div>  
    </div>
 </div>

 <!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/rollo/scripts/dashboard.js"></script>

<?php 
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php'; 
?>