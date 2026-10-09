<?php
/** @var array $meses */
/** @var string|int $semana_actual */
/** @var int $mes_actual */
/** @var int $mes1 */
/** @var int $mes2 */
/** @var int $total */
/** @var int $semana */
/** @var int $mes */
/** @var array $referencia_top */
/** @var int $referencias_distintas */
/** @var int $registros_mes1 */
/** @var array $referencia_mes1 */
/** @var int $registros_mes2 */
/** @var array $referencia_mes2 */
/** @var int $diferencia */
/** @var float $porcentaje */
/** @var mysqli $conexion */
/** @var mysqli_result $res_tabla_fecha */
/** @var mysqli_result $res_tabla_referencia */

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
    <h2 class="titulo-vista">Registro de Mezclas</h2>

    <!-- KPIs principales -->
    <div class="kpis">
        <!-- KPI total histórico -->
        <div class="card-kpi">
            <p>Total de mezclas</p>
            <h2><?php echo $total; ?></h2>
        </div>
        <!-- KPI semana actual -->
        <div class="card-kpi">
            <p>Mezclas esta semana</p>
            <h2><?php echo $semana; ?></h2>
        </div>
        <!-- KPI mes actual -->
        <div class="card-kpi">
            <p>Mezclas este mes</p>
            <h2><?php echo $mes; ?></h2>
        </div>
    </div>

    <!-- Referencia más mezclada y referencias distintas -->
    <div class="top-container">
        <div class="kpis-top">
            <div class="card-kpi" id="top-card">
                <p>Referencia más mezclada</p>
                <h2>
                    <?php
                    if(!empty($referencia_top)){
                        echo $referencia_top['nombre_referencia'] . "<br><span style='font-size:14px'>(".$referencia_top['total']." mezclas)</span>";
                    }else{
                        echo "Sin datos";
                    }
                    ?>
                </h2>
            </div>
            <div class="card-kpi" id="top-operario-card">
                <p>Referencias distintas trabajadas</p>
                <h2><?php echo $referencias_distintas; ?></h2>
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
                🔢 Mezclas registradas: <?php echo $registros_mes1; ?>
            </p>
            <p>
                🧪 Referencia más mezclada: <?php echo $referencia_mes1['nombre_referencia']; ?>
                <?php if(!empty($referencia_mes1['total']) && $referencia_mes1['total'] > 0){ ?>
                    (<?php echo $referencia_mes1['total']; ?> mezclas)
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
                    <span>+<?php echo $diferencia; ?> mezclas</span>
                <?php elseif ($diferencia < 0): ?>
                    <span>▼ Bajó <?php echo round(abs($porcentaje), 1); ?>%</span>
                    <span>-<?php echo abs($diferencia); ?> mezclas</span>
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
                🔢 Mezclas registradas: <?php echo $registros_mes2; ?>
            </p>
            <p>
                🧪 Referencia más mezclada: <?php echo $referencia_mes2['nombre_referencia']; ?>
                <?php if(!empty($referencia_mes2['total']) && $referencia_mes2['total'] > 0){ ?>
                    (<?php echo $referencia_mes2['total']; ?> mezclas)
                <?php } ?>
            </p>
        </div>
    </div>

    <hr class="seccion-divisor">

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

        <!-- Gráficos de mezclas por fecha y referencia -->
        <div class="grid-graficos">
            <div class="card-grafico">
                <h3>Mezclas por Fecha</h3>
                <canvas id="graficoProduccion"></canvas>
            </div>

            <div class="card-grafico">
                <h3>Mezclas por Referencia</h3>
                <canvas id="graficoReferencias"></canvas>
            </div>
        </div>

    </div>

    <!-- Gráfico mensual por año -->
    <div class="contenedor-grafico">
        <h3>Mezclas por año</h3>
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
        <!-- Tabla de mezclas por fecha -->
        <h3>Mezclas por Fecha</h3>
        <table>
            <tr>
                <th>Fecha</th>
                <th>Registros</th>
            </tr>
            <?php
            $total_registros_tbl = 0;
            while($row = mysqli_fetch_assoc($res_tabla_fecha)){
            $total_registros_tbl += $row['registros'];
            ?>
            <tr>
                <td><?php echo date("d M Y", strtotime($row['fecha'])); ?></td>
                <td><?php echo $row['registros']; ?></td>
            </tr>
            <?php } ?>
            <!-- Fila de total -->
            <tr class="fila-total">
                <td><strong>TOTAL</strong></td>
                <td><strong><?php echo $total_registros_tbl; ?></strong></td>
            </tr>
        </table>
    </div>

    <!-- Tabla de mezclas por referencia -->
    <div class="tabla-dashboard">
        <h3>Mezclas por Referencia</h3>
        <table>
            <tr>
                <th>Referencia</th>
                <th>Registros</th>
            </tr>
            <?php
            $total_registros_tbl = 0;
            while($row = mysqli_fetch_assoc($res_tabla_referencia)){
            $total_registros_tbl += $row['registros'];
            ?>
            <tr>
                <td><?php echo $row['nombre_referencia']; ?></td>
                <td><?php echo $row['registros']; ?></td>
            </tr>
            <?php } ?>
            <tr class="fila-total">
                <td><strong>TOTAL</strong></td>
                <td><strong><?php echo $total_registros_tbl; ?></strong></td>
            </tr>
        </table>
    </div>

</div>

 <!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/mezclas/scripts/dashboard.js"></script>

<?php
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>
