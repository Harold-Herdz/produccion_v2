<?php
/** @var mysqli_result $resultado */
/** @var int $pagina */
/** @var int $total_paginas */
/** @var string $busqueda */
/** @var string $fecha */

// Importar proteger.php
require_once dirname(__DIR__, 3) . '/auth/proteger.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar listaController.php
include dirname(__DIR__) . '/controllers/listaController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';
?>

<!-- Contenedor del Historial -->
<div class="container" id="containerHistorial">
    <!-- Título -->
    <h2 class="titulo-vista">Historial Producción Rollo</h2>

        <!-- Botón para registrar producción en Google Forms -->
        <a class="btn" id="btnRegistrar" href="https://docs.google.com/forms/d/e/1FAIpQLSch9DWsxKlht9EWeGYErV7ZpUKCQ0anjesuLoku87wk8ds8Bw/viewform?usp=dialog" target="_blank">
            Registrar Producción
        </a>

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
                <a class="btn" href="lista.php">Limpiar</a>

            </form>
            <br>
            <!-- Tabla de registros -->
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Máquina</th>
                        <th>Referencia</th>
                        <th>Color</th>
                        <th>Peso</th>
                        <th>Retal</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <!-- Filas de registros de la base de datos -->
                <tbody>
                    <?php while($fila = mysqli_fetch_assoc($resultado)): ?>
                        <tr>
                            <td><?php echo $fila['id']; ?></td>
                            <td><?php echo $fila['fecha_roll']; ?></td>
                            <td><?php echo $fila['nombre_maquina']; ?></td>
                            <td><?php echo $fila['nombre_referencia']; ?></td>
                            <td><?php echo $fila['nombre_color']; ?></td>
                            <td><?php echo $fila['peso_rollo']; ?></td>
                            <td><?php echo $fila['retal_roll']; ?></td>
                            <td><?php echo $fila['total_roll']; ?></td>
                            <!-- Botones de editar o eliminar registros -->
                            <td>
                                <a class="btn"
                                href="editar.php?id=<?php echo $fila['id']; ?>">
                                    Editar
                                </a>
                                <?php if($_SESSION['rol'] == 'admin'){ ?>
                                    <a class="btn btn-eliminar"
                                    href="../controllers/eliminarController.php?id=<?php echo $fila['id']; ?>"
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
                echo '<a href="?pagina='.($pagina-1).'&buscar='.urlencode($busqueda).'&fecha='.urlencode($fecha).'">«</a> ';
            }
            // Primera página si el rango no empieza en 1
            if($inicio > 1){
                echo '<a href="?pagina=1&buscar='.urlencode($busqueda).'&fecha='.urlencode($fecha).'">1</a> ... ';
            }
            // Páginas del rango
            for($i = $inicio; $i <= $fin; $i++){
                if($i == $pagina){
                    echo "<strong>$i</strong> ";
                }else{
                    echo '<a href="?pagina='.$i.'&buscar='.urlencode($busqueda).'&fecha='.urlencode($fecha).'">'.$i.'</a> ';
                }
            }
            // Última página si faltan páginas al final
            if($fin < $total_paginas){
                echo ' ... <a href="?pagina='.$total_paginas.'&buscar='.urlencode($busqueda).'&fecha='.urlencode($fecha).'">'.$total_paginas.'</a>';
            }
            // Botón de siguiente
            if($pagina < $total_paginas){
                echo ' <a href="?pagina='.($pagina+1).'&buscar='.urlencode($busqueda).'&fecha='.urlencode($fecha).'">»</a>';
            }
        ?>
    </div>

    <br>

    <!-- Botones de navegación -->
    <div class="acciones">
        <!-- Redirigir al Dashboard (solo Administradores) -->
        <?php if($_SESSION['rol'] == 'admin'){ ?>
            <a class="btn" href="dashboard.php">Volver al Dashboard</a>
        <?php } ?>
        <!-- Redirigir al Index -->
        <a class="btn" href="<?= BASE_URL ?>/index.php">Volver al Menú</a>
    </div>

</div>

<?php 
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>