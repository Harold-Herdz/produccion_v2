<?php
/** @var array|null $planilla       Sobre del turno abierto (o null) */
/** @var array      $maquinas       Máquinas 01-17 */
/** @var array      $operarios      Operarios activos */
/** @var array      $referencias    Referencias activas */
/** @var array      $colores        Colores activos */
/** @var array      $datosMaquinas  Datos ya guardados por número de máquina */
/** @var array      $bloques        Bloques de turno disponibles */
/** @var string     $horarioTurno   Etiqueta horaria del turno abierto */

// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar registerController.php
require_once dirname(__DIR__) . '/controllers/registerController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';

// Ayudante: opciones <option> de un catálogo
if(!function_exists('opcionesCatalogo')){
    function opcionesCatalogo($lista, $idKey, $nombreKey, $seleccionado){
        // Primera opción realmente vacía (sin "-" ni guion)
        $html = '<option value=""></option>';
        foreach($lista as $item){
            $sel = ((string) $item[$idKey] === (string) $seleccionado) ? ' selected' : '';
            $html .= '<option value="' . $item[$idKey] . '"' . $sel . '>'
                   . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}

// Ayudante: valor limpio para inputs (evita mostrar NULL / 0.00 innecesario)
if(!function_exists('valPlanilla')){
    function valPlanilla($v){
        return ($v === null || $v === '') ? '' : htmlspecialchars($v);
    }
}

// Ayudante: las celdas de una entrada (referencia..observaciones + eliminar)
if(!function_exists('celdasEntradaPlanilla')){
    function celdasEntradaPlanilla($ent, $referencias, $colores){
        ob_start(); ?>
        <td><select class="f-ref"><?= opcionesCatalogo($referencias, 'id_referencia', 'nombre_referencia', $ent['id_referencia'] ?? '') ?></select></td>
        <td><select class="f-color"><?= opcionesCatalogo($colores, 'id_color', 'nombre_color', $ent['id_color'] ?? '') ?></select></td>
        <td><input type="number" class="f-x70" min="0" step="1" value="<?= valPlanilla($ent['x70'] ?? '') ?>"></td>
        <td><input type="number" class="f-x90" min="0" step="1" value="<?= valPlanilla($ent['x90'] ?? '') ?>"></td>
        <td><input type="number" class="f-x98" min="0" step="1" value="<?= valPlanilla($ent['x98'] ?? '') ?>"></td>
        <td><input type="number" class="f-p1" min="0" step="0.01" value="<?= valPlanilla($ent['p1'] ?? '') ?>"></td>
        <td><input type="number" class="f-p2" min="0" step="0.01" value="<?= valPlanilla($ent['p2'] ?? '') ?>"></td>
        <td><input type="number" class="f-p3" min="0" step="0.01" value="<?= valPlanilla($ent['p3'] ?? '') ?>"></td>
        <td><input type="number" class="f-p4" min="0" step="0.01" value="<?= valPlanilla($ent['p4'] ?? '') ?>"></td>
        <td><input type="number" class="f-p5" min="0" step="0.01" value="<?= valPlanilla($ent['p5'] ?? '') ?>"></td>
        <td><input type="text" class="f-obs" autocomplete="off" value="<?= valPlanilla($ent['obs'] ?? '') ?>"></td>
        <td class="celda-acciones"><button type="button" class="btn-quitar" title="Quitar entrada">&times;</button></td>
        <?php return ob_get_clean();
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<!-- Contenedor de Register -->
<div class="container" id="containerRegister">

    <h2 class="titulo-vista">Planilla de Producción · Sellado</h2>

    <!-- ============================================
         TURNO ABIERTO → PLANILLA DE TRABAJO
         (El inicio de turno se hace desde el modal del historial)
    ============================================ -->

    <!-- Encabezado del turno -->
    <div class="card encabezado-turno">
        <div class="dato-turno">
            <span class="dato-label">Fecha</span>
            <!-- Editable: al cambiarla se recodifica la planilla -->
            <input type="date" id="fechaPlanilla" value="<?= htmlspecialchars($planilla['fecha_planilla']) ?>">
        </div>
        <div class="dato-turno">
            <span class="dato-label">Turno</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['bloque']) ?> (<?= htmlspecialchars($horarioTurno) ?>)</span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Supervisor</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['supervisor_nombre']) ?></span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Código</span>
            <span class="dato-valor" id="codigoPlanilla"><?= htmlspecialchars($planilla['codigo']) ?></span>
        </div>
        <div class="indicador-guardado" id="indicadorGuardado">
            <span class="punto"></span>
            <span class="texto">Sin cambios</span>
        </div>
    </div>

    <!-- Zona de avisos (operarios nuevos, errores) -->
    <div id="zonaAvisos"></div>

    <!-- Planilla -->
    <div class="planilla-scroll">
        <table class="planilla" id="planilla"
               data-codigo="<?= htmlspecialchars($planilla['codigo']) ?>">
            <colgroup>
                <col class="c-maq"><col class="c-op"><col class="c-ref"><col class="c-color">
                <col class="c-x"><col class="c-x"><col class="c-x">
                <col class="c-p"><col class="c-p"><col class="c-p"><col class="c-p"><col class="c-p">
                <col class="c-obs"><col class="c-del">
            </colgroup>
            <thead>
                <tr>
                    <th>MÁQUINA</th>
                    <th>OPERARIO</th>
                    <th>REFERENCIA</th>
                    <th>COLOR</th>
                    <th>X70</th>
                    <th>X90</th>
                    <th>X98</th>
                    <th>PESO 1</th>
                    <th>PESO 2</th>
                    <th>PESO 3</th>
                    <th>PESO 4</th>
                    <th>PESO 5</th>
                    <th>OBSERVACIONES</th>
                    <th></th>
                </tr>
            </thead>

            <?php foreach($maquinas as $m):
                $num      = (int) $m['id_maquina'];
                $etq      = str_pad($num, 2, '0', STR_PAD_LEFT);
                $datos    = $datosMaquinas[$num] ?? null;
                $entradas = $datos['entradas'] ?? [];
                // Cada máquina arranca con al menos 3 filas de producción visibles
                while(count($entradas) < 3){
                    $entradas[] = [];
                }
                $span = count($entradas) + 1; // + fila del botón "+ Entrada"
            ?>
            <tbody class="grupo-maquina" data-maquina="<?= $num ?>">
                <?php foreach($entradas as $i => $ent): ?>
                <tr class="fila-entrada">
                    <?php if($i === 0): ?>
                        <!-- Número de máquina agrupando visualmente todas sus entradas -->
                        <td class="col-maquina" rowspan="<?= $span ?>"><?= $etq ?></td>
                        <!-- Bloque de operario / otro operario / jornada de la máquina -->
                        <td class="col-operario" rowspan="<?= $span ?>">
                            <div class="op-bloque">
                                <!-- Operario: sin subtítulo -->
                                <select class="f-operario" title="Operario"><?= opcionesCatalogo($operarios, 'id_operario', 'nombre_operario', $datos['id_operario'] ?? '') ?></select>
                                <!-- Otro operario: conserva subtítulo y placeholder -->
                                <label class="op-sub">Otro operario
                                    <input type="text" class="f-otro-operario" autocomplete="off" placeholder="Si no esta en la lista">
                                </label>
                                <!-- Jornada: conserva subtítulo -->
                                <label class="op-sub">Jornada
                                    <select class="f-jornada">
                                        <option value=""></option>
                                        <option value="8 Horas"  <?= (($datos['jornada'] ?? '') === '8 Horas')  ? 'selected' : '' ?>>8 Horas</option>
                                        <option value="12 Horas" <?= (($datos['jornada'] ?? '') === '12 Horas') ? 'selected' : '' ?>>12 Horas</option>
                                    </select>
                                </label>
                            </div>
                        </td>
                    <?php endif; ?>
                    <?= celdasEntradaPlanilla($ent, $referencias, $colores) ?>
                </tr>
                <?php endforeach; ?>

                <!-- Agregar otra entrada a esta máquina -->
                <tr class="fila-add">
                    <td colspan="12">
                        <button type="button" class="btn-entrada">+ Entrada</button>
                        <span class="tope-entradas">Máximo 6 entradas por máquina</span>
                    </td>
                </tr>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Nota general del turno (aparece en el PDF, NO se guarda en la base de datos) -->
    <div class="nota-general">
        <label for="notaGeneral">NOTA:</label>
        <textarea id="notaGeneral" rows="3"></textarea>
    </div>

    <!-- Acciones -->
    <div class="acciones-planilla">
        <!-- Cancelar: descarta el turno y vuelve a la pantalla de inicio -->
        <form method="POST" id="formCancelarTurno" class="form-cancelar"
              onsubmit="return confirm('¿Cancelar el turno? Se perderán los datos no finalizados de esta planilla.');">
            <input type="hidden" name="accion" value="cancelar">
            <button type="submit" class="btn btn-cancelar-turno">Cancelar</button>
        </form>
        <button type="button" class="btn btn-finalizar" id="btnFinalizar">Finalizar turno</button>
    </div>

    <!-- Plantilla de una entrada nueva (para el botón "+ Entrada") -->
    <template id="tplEntrada">
        <tr class="fila-entrada"><?= celdasEntradaPlanilla([], $referencias, $colores) ?></tr>
    </template>

</div>

<!-- Modal de confirmación de finalización -->
<div class="overlay" id="modalFinalizar">
    <div class="modal">
        <div class="modal-header">
            <h2>Finalizar turno</h2>
            <button type="button" onclick="cerrarModal('modalFinalizar')">X</button>
        </div>
        <p class="modal-texto">
            Se enviarán todos los registros a la hoja REGISTROS y el PDF (con la nota general) a Drive.<br>
            Luego deberás <strong>importar</strong> desde el Dashboard para verlos en el Historial.<br>
            Esta acción no se puede deshacer.
        </p>
        <div class="btn-row">
            <button type="button" class="btn" id="btnConfirmarFinalizar">Finalizar</button>
            <button type="button" class="btn btn-cancelar" onclick="cerrarModal('modalFinalizar')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal de resultado -->
<div class="overlay" id="modalResultado">
    <div class="modal">
        <div class="modal-header">
            <h2>Turno finalizado</h2>
        </div>
        <p class="modal-texto" id="resultadoTexto"></p>
        <div class="btn-row">
            <a class="btn" id="btnVerPdf" href="#" target="_blank">Ver PDF</a>
            <a class="btn" id="btnNuevaPlanilla" href="<?= BASE_URL ?>/modules/sellado/views/register.php">Nueva planilla</a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/sellado/scripts/register.js"></script>

<?php
// Importar footer.php
include dirname(__DIR__, 3) . '/templates/footer.php';
?>
