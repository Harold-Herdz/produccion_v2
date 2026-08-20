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
?>

<!-- Contenedor del Historial -->
<div class="container" id="containerHistorial">
    <!-- Título -->
    <h2 class="titulo-vista">Historial Producción Rollo</h2>

        <!-- Botón para registrar producción en Google Forms -->
        <a class="btn" id="btnRegistrar" href="https://docs.google.com/forms/d/e/1FAIpQLScylHvavBGIPO_o_gF4Wio0Gnn69H1Q3wBJWaBx0age4sAvcQ/viewform?usp=dialog" target="_blank">
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
                <a class="btn" href="history.php">Limpiar</a>

            </form>
            <br>
            <!-- Tabla de registros -->
            <table class="tabla">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Operario</th>
                        <th>Máquina</th>
                        <th>Referencia</th>
                        <th>Color</th>
                        <th>Bruto (kg)</th>
                        <th>Retal (kg)</th>
                        <th>Total (kg)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <!-- Filas de registros de la base de datos -->
                <tbody>
                    <?php while($fila = mysqli_fetch_assoc($resultado)): ?>
                        <tr>
                            <td><?php echo $fila['id_sheet']; ?></td>
                            <td><?php echo $fila['fecha_rollo']; ?></td>
                            <td><?php echo $fila['nombre_operario']; ?></td>
                            <td><?php echo $fila['nombre_maquina']; ?></td>
                            <td><?php echo $fila['nombre_referencia']; ?></td>
                            <td><?php echo $fila['nombre_color']; ?></td>
                            <td><?php echo $fila['peso_rollo']; ?></td>
                            <td><?php echo $fila['retal_rollo']; ?></td>
                            <td><?php echo $fila['total_rollo']; ?></td>
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

</div>

<?php 
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>