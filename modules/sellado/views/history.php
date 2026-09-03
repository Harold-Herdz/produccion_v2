<?php
/** @var mysqli_result $resultado */
/** @var int $pagina */
/** @var int $total_paginas */
/** @var string $busqueda */
/** @var string $fecha */

// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar historyController.php
include dirname(__DIR__) . '/controllers/historyController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';

/* =================================================
   MODAL "REGISTRAR PRODUCCIÓN" (inicio de turno)
================================================= */
// Datos para el modal
$regError   = $_GET['reg_error'] ?? '';
$regHoy     = date('Y-m-d');
$regUsuario = $_SESSION['usuario'] ?? 'Sin usuario';
$regTurnos  = ['Día' => '6am - 2pm', 'Tarde' => '2pm - 10pm', 'Noche' => '10pm - 6am'];

// ¿Ya hay un turno abierto? (si la tabla existe)
$regAbierta = null;
try {
    $q = $conexion->query("SELECT codigo FROM sellado_planillas WHERE estado = 'abierta' ORDER BY id_planilla DESC LIMIT 1");
    $regAbierta = $q ? $q->fetch_assoc() : null;
} catch (Throwable $e) {
    // La planilla nunca se ha abierto y la tabla aún no existe
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<!-- Contenedor del Historial -->
<div class="container" id="containerHistorial">
    <!-- Título -->
    <h2 class="titulo-vista">Historial Producción Sellado</h2>

        <!-- Botón para registrar producción: abre el modal, o continúa el turno abierto -->
        <?php if($regAbierta): ?>
            <a class="btn" id="btnRegistrar" href="<?= BASE_URL ?>/modules/sellado/views/register.php">
                Continuar planilla
            </a>
        <?php else: ?>
            <a class="btn" id="btnRegistrar" onclick="abrirModal('modalRegistrar')">
                Registrar Producción
            </a>
        <?php endif; ?>

        <br> <br>

        <!-- Tarjeta -->
        <div class="card">
            <!-- Filtros de búsqueda -->
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="buscar" autocomplete="off" placeholder="Buscar..."
                    value="<?php echo $busqueda; ?>">
                <input type="date" name="fecha"
                    value="<?php echo $fecha; ?>">
                <!-- Botón para filtrar -->
                <button type="submit" class="btn" id="btnFiltrar">Filtrar</button>
                <!-- Botón para limpiar el filtro -->
                <a class="btn" href="history.php">Limpiar</a>
            </form>

            <br>

            <!-- Tabla de registros -->
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Máquina</th>
                        <th>Operario</th>
                        <th>Turno</th>
                        <th>Referencia</th>
                        <th>Color</th>
                        <th>Total Paquetes</th>
                        <th>Promedio Peso</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                
                <!-- Filas de registros de la base de datos -->
                <tbody>
                    <?php while($fila = mysqli_fetch_assoc($resultado)): ?>
                        <tr>
                            <td><?php echo $fila['id_sheet']; ?></td>
                            <td><?php echo $fila['fecha_sellado']; ?></td>
                            <td><?php echo $fila['nombre_maquina']; ?></td>
                            <td><?php echo $fila['nombre_operario']; ?></td>
                            <td><?php echo $fila['nombre_turno']; ?></td>
                            <td><?php echo $fila['nombre_referencia']; ?></td>
                            <td><?php echo $fila['nombre_color']; ?></td>
                            <td><?php echo $fila['paquetes_total']; ?></td>
                            <td>
                                <?= $fila['promedio_peso'] !== null
                                    ? number_format($fila['promedio_peso'],2).' kg'
                                    : '-' ?>
                            </td>
                            <!-- Botones de editar o eliminar registros -->
                            <td>
                                <a class="btn"
                                href="edit.php?id=<?php echo $fila['id']; ?>">
                                    Editar
                                </a>
                                <?php if($_SESSION['rol'] == 'admin'){ ?>
                                    <a class="btn btn-eliminar"
                                    href="../controllers/historyController.php?id=<?php echo $fila['id']; ?>"
                                    onclick="return confirm('¿Deseas eliminar este registro?');">
                                        Eliminar
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <br>

    <!-- Paginación -->
    <div class="paginacion" style="text-align:center;">
        <?php
            // Rango de páginas visibles en la paginación
            $rango = 5;
            $inicio = max(1, $pagina - $rango);
            $fin = min($total_paginas, $pagina + $rango);
            // Botón anterior
            if($pagina > 1){
                echo '<a href="?pagina='.($pagina-1).'&buscar='.$busqueda.'&fecha='.$fecha.'">«</a> ';
            }
            // Primera página si el rango no empieza en 1
            if($inicio > 1){
                echo '<a href="?pagina=1&buscar='.$busqueda.'&fecha='.$fecha.'">1</a> ... ';
            }
            // Páginas del rango
            for($i = $inicio; $i <= $fin; $i++){
                if($i == $pagina){
                    echo "<strong>$i</strong> ";
                }else{
                    echo '<a href="?pagina='.$i.'&buscar='.$busqueda.'&fecha='.$fecha.'">'.$i.'</a> ';
                }
            }
            // Última página si faltan páginas al final
            if($fin < $total_paginas){
                echo ' ... <a href="?pagina='.$total_paginas.'&buscar='.$busqueda.'&fecha='.$fecha.'">'.$total_paginas.'</a>';
            }
            // Botón de siguiente
            if($pagina < $total_paginas){
                echo ' <a href="?pagina='.($pagina+1).'&buscar='.$busqueda.'&fecha='.$fecha.'">»</a>';
            }
        ?>
    </div>

    <br>

</div>

<!-- ============================================
     MODAL: NUEVA PLANILLA (inicio de turno)
============================================ -->
<div class="overlay" id="modalRegistrar">
    <div class="modal">
        <div class="modal-header">
            <h2>Nueva planilla · Sellado</h2>
            <button type="button" onclick="cerrarModal('modalRegistrar')">X</button>
        </div>

        <?php if($regError): ?>
            <p class="aviso aviso-error"><?= htmlspecialchars($regError) ?></p>
        <?php endif; ?>

        <!-- El formulario envía a register.php, que crea el turno y abre la planilla -->
        <form class="form-inicio" method="POST"
              action="<?= BASE_URL ?>/modules/sellado/views/register.php">
            <input type="hidden" name="accion" value="iniciar">

            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($regHoy) ?>" required>
            </div>

            <div class="campo">
                <label>Turno</label>
                <select name="bloque" required>
                    <option value="">Seleccione el turno...</option>
                    <?php foreach($regTurnos as $nombre => $horario): ?>
                        <option value="<?= $nombre ?>"><?= $nombre ?> (<?= $horario ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Supervisor</label>
                <input type="text" value="<?= htmlspecialchars($regUsuario) ?>" readonly>
            </div>

            <button type="submit" class="btn" id="btnIniciar">Iniciar planilla</button>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<?php if($regError): ?>
<script>abrirModal('modalRegistrar');</script>
<?php endif; ?>

<?php
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>