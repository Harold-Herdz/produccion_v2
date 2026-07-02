<?php 
/** @var array $meses */
/** @var int $semana_actual */
/** @var int $mes_actual */
/** @var int $mes_anterior */
/** @var int $mes1 */
/** @var int $mes2 */
/** @var int $total */
/** @var int $semana */
/** @var int $mes */
/** @var array $top_operario */
/** @var int $total_mes1 */
/** @var int $total_mes2 */
/** @var array $mejor_dia_mes1 */
/** @var array $peor_dia_mes1 */
/** @var array $mejor_dia_mes2 */
/** @var array $peor_dia_mes2 */
/** @var array $top_operario_mes1 */
/** @var array $top_operario_mes2 */
/** @var float $diferencia */
/** @var float $porcentaje */
/** @var float $promedio_mes1 */
/** @var float $promedio_mes2 */
/** @var string $desde */
/** @var string $hasta */
/** @var mysqli_result $res_tabla_fecha */
/** @var mysqli_result $res_tabla_operario */
/** @var string $ultima_fecha */

// Restringir acceso solo a administradores
$soloAdmin = true;
// Importar proteger.php
require_once dirname(__DIR__, 3) . '/auth/proteger.php';
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
    <h2 class="titulo-vista">Producción Sellado</h2>
    
    <!-- KPIs principales -->
    <div class="kpis">
        <!-- KPI de total historico de producción -->
        <div class="card-kpi">
            <p>Total histórico</p>
            <h2><?php echo number_format($total); ?></h2>
        </div>
        <!-- KPI de producción de la semana actual -->
        <div class="card-kpi">
            <p>Producción semanal</p>
            <h2><?php echo number_format($semana); ?></h2>
        </div>
        <!-- KPI de producción del mes actual -->
        <div class="card-kpi">
            <p>Producción del mes</p>
            <h2><?php echo number_format($mes); ?></h2>
        </div>
    </div>

    <!-- Top operario del mes -->
    <div class="top-container">
        <div class="card-kpi top-card" id="top-card">
            <p>Top operario del mes</p>
            <h2>
                <?php 
                if(!empty($top_operario)){
                    echo $top_operario['nombre_operario'] . "<br><span style='font-size:14px'>(".$top_operario['total']." paquetes)</span>";
                }else{
                    echo "Sin datos";
                }
                ?>
            </h2>
        </div>
    </div>

    <!-- Comparativo mes 1 y mes 2 -->
    <div class="resumenes">
        <!-- Resumen mes 1 -->
        <div class="resumen-mes1">
            <h3> Resumen de 
                <!-- Selección del mes 1 a comparar -->
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
                📦 Producción total: <?php 
                echo number_format($total_mes1); ?> paquetes
            </p>
            <p>
                📅 Mejor día: <?php 
                echo $mejor_dia_mes1['fecha'] != "Sin datos" && $mejor_dia_mes1['fecha'] != "" 
                    ? date("d M Y", strtotime($mejor_dia_mes1['fecha'])) 
                    : "Sin datos";
                ?>
                (<?php echo number_format($mejor_dia_mes1['total']); ?> paquetes)
            </p>
            <p>
                📉 Peor día: <?php 
                echo $peor_dia_mes1['fecha'] != "Sin datos" && $peor_dia_mes1['fecha'] != ""
                    ? date("d M Y", strtotime($peor_dia_mes1['fecha']))
                    : "Sin datos";
                ?>
                (<?php echo number_format($peor_dia_mes1['total']); ?> paquetes)
            </p>
            <p>
                📊 Promedio diario: <?php 
                echo round($promedio_mes1); ?> paquetes
            </p>
            <p>
                👷 Mejor operario: <?php 
                echo $top_operario_mes1['nombre_operario'] ?? 'Sin datos'; ?>
                <?php if(!empty($top_operario_mes1['total']) && $top_operario_mes1['total'] > 0){ ?>
                    (<?php echo number_format($top_operario_mes1['total']); ?> paquetes)
                <?php } ?>
            </p>
        </div>

        <?php
        // Verificar de estado 
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
                    <span>+<?php echo number_format($diferencia); ?> paquetes</span>
                <?php elseif ($diferencia < 0): ?>
                    <span>▼ Bajó <?php echo round($porcentaje, 1); ?>%</span>
                    <span>-<?php echo number_format(abs($diferencia)); ?> paquetes</span>
                <?php else: ?>
                    <span>Sin cambios</span>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Resumen mes 2 -->
        <div class="resumen-mes2">
            <h3> Resumen de 
                <!-- Selección del mes 2 a comparar -->
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
                📦 Producción total: <?php 
                echo number_format($total_mes2); ?> paquetes
            </p>
            <p>
                📅 Mejor día: <?php 
                echo ($mejor_dia_mes2['fecha'] != "Sin datos" && $mejor_dia_mes2['fecha'] != "")
                    ? date("d M Y", strtotime($mejor_dia_mes2['fecha'])) 
                    : "Sin datos"; 
                ?>
                (<?php echo number_format($mejor_dia_mes2['total']); ?> paquetes)
            </p>
            <p>
                📉 Peor día: <?php 
                echo ($peor_dia_mes2['fecha'] != "Sin datos") 
                    ? date("d M Y", strtotime($peor_dia_mes2['fecha'])) 
                    : "Sin datos"; 
                ?>
                (<?php echo number_format($peor_dia_mes2['total']); ?> paquetes)
            </p>
            <p>
                📊 Promedio diario: <?php 
                echo round($promedio_mes2); ?> paquetes
            </p>
            <p>
                👷 Mejor operario: <?php 
                echo $top_operario_mes2['nombre_operario'] ?? 'Sin datos'; ?>
                <?php if(!empty($top_operario_mes2['total']) && $top_operario_mes2['total'] > 0){ ?>
                    (<?php echo number_format($top_operario_mes2['total']); ?> paquetes)
                <?php } ?>
            </p>
        </div>
    </div>

    <!-- Botón para abrir modal de importación -->
    <a class="btn" id="btnImportar" onclick="abrirModal('modalImportar')">Importar Producción</a>

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
                            <?php if($num == $mes_actual) echo "selected"; ?> >
                            <?php echo $nombre; ?>
                        </option>
                    <?php } ?>
                </select>
            </label>
            <!-- Filtrar por semana segun mes -->
            <label class="label" for="filtroSemana">
                Semana:
                <select id="filtroSemana" onchange="actualizarFiltros()">
                    <!-- Todas las semanas del mes seleccionado-->
                    <option value = "" <?php if($semana_actual == "") echo "selected"; ?> >
                        Todas
                    </option>
                    <option value="1" <?php if($semana_actual == 1) echo "selected"; ?>>Semana 1</option>
                    <option value="2" <?php if($semana_actual == 2) echo "selected"; ?>>Semana 2</option>
                    <option value="3" <?php if($semana_actual == 3) echo "selected"; ?>>Semana 3</option>
                    <option value="4" <?php if($semana_actual == 4) echo "selected"; ?>>Semana 4</option>
                    <option value="5" <?php if($semana_actual == 5) echo "selected"; ?>>Semana 5</option>
                </select>
            </label>
            <!-- Filtro de año agrupado por semanas -->
            <button class="btn" id="btnAnio" onclick="cargarDatos('anio')">Año</button>
        </div>

        <!-- Gráficos de producción y operarios -->
        <div class="grid-graficos">
            <div class="card-grafico">
                <h3>Producción de paquetes</h3>
                <canvas id="graficoProduccion"></canvas>
            </div>

            <div class="card-grafico">
                <h3>Producción por Operario</h3>
                <canvas id="graficoOperarios"></canvas>
            </div>
        </div>

    </div>

    <br><br><br>

    <!-- Gráfico de producción mensual por año -->
    <div class="contenedor-grafico">
        <h3>Producción por año (Sellado)</h3>
            <div class="header-grafico-meses">
                <!-- Selector de año -->
                <select id="filtroAnioMes" onchange="cargarGraficoMeses()">
                    <?php for($i=date('Y'); $i>=2023; $i--){ ?>
                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php } ?>
                </select>

                <div id="totalAnio" class="total-anio">Total: 0</div>

            </div>
        <canvas id="graficoMeses"></canvas>
    </div>

    <br> <br> <br>

    <!-- Tablas -->
    <form class="filtro-fechas" method="GET">
        <!-- Filtros de fechas -->
        <label>Desde</label>
            <input type="date" name="desde"
                value="<?php echo $desde; ?>">
        <label>Hasta</label>
            <input type="date" name="hasta"
                value="<?php echo $hasta; ?>">
        <!-- Botón de filtrar -->
        <button class="btn" type="submit">Filtrar</button>
    </form>

    <div class="grid-tablas">
        <!-- Tabla de producción por fecha -->
        <div class="tabla-dashboard">
            <h3>Producción por fecha</h3>
            <table>
                <tr>
                    <th>Fecha</th>
                    <th>Paquetes producidos</th>
                </tr>
                <?php
                $total_fecha = 0;
                while($row = mysqli_fetch_assoc($res_tabla_fecha)){ 
                    $total_fecha += $row['total'];
                ?>
                <tr>
                    <td><?php echo date("d M Y", strtotime($row['fecha'])); ?></td>
                    <td><?php echo number_format($row['total']); ?></td>
                </tr>
                <?php } ?>
                <!-- Fila de total -->
                <tr class="fila-total">
                    <td><strong>TOTAL</strong></td>
                    <td><strong><?php echo number_format($total_fecha); ?></strong></td>
                </tr>
            </table>
        </div>

        <!-- Tabla de producción por operario -->
        <div class="tabla-dashboard">
            <h3>Producción por operario</h3>
            <table>
                <tr>
                    <th>Operario</th>
                    <th>Paquetes producidos</th>
                </tr>
                <?php
                $total_operario = 0;
                while($row = mysqli_fetch_assoc($res_tabla_operario)){
                    $total_operario += $row['total'];
                ?>
                <tr>
                    <td><?php echo $row['nombre_operario']; ?></td>
                    <td><?php echo number_format($row['total']); ?></td>
                </tr>
                <?php } ?>
                <!-- Fila de total -->
                <tr class="fila-total">
                    <td><strong>TOTAL</strong></td>
                    <td><strong><?php echo number_format($total_operario); ?></strong></td>
                </tr>
            </table>
        </div>
    </div>

    <br><br><br>

    <!-- Botones de navegación -->
    <div class="acciones">
        <!-- Redirigir al Lista -->
        <a class="btn" href="lista.php">Ver Historial</a>
        <!-- Redirigir al Index -->
        <a class="btn" href="<?= BASE_URL ?>/index.php">Volver al Menú</a>
    </div>

</div>

<!-- Modal de importación -->
<div class="overlay" id="modalImportar">
    <div class="modal">  
        <div class="modal-header">
            <h2>Importar Sellado</h2>
            <p>Última Fecha Importada: <strong><?php echo $ultima_fecha; ?></strong></p>
            <button id="cerrarBtn" onclick="cerrarModal('modalImportar')">X</button>
        </div>
        <!-- Opciones de importación -->
        <div class="btn-row">
            <a class="btn-nuevos" href="<?= BASE_URL ?>/importar/controllers/imp_sellado.php?modo=nuevos">
                <div class="btn-text"><span class="btn-icon">🗲</span>Importar Nuevos<span class="btn-arrow">›</span></div>
            </a>
            <a class="btn-todo" href="<?= BASE_URL ?>/importar/controllers/imp_sellado.php?modo=todo">
                <div class="btn-text"><span class="btn-icon">⟳</span>Reimportar Todo<span class="btn-arrow">›</span></div>
            </a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/sellado/scripts/sellado.js"></script>

<?php 
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>