<?php
/** @var array|null       $planilla       Sobre del turno abierto (o null) */
/** @var mysqli_result     $maquinas       Máquinas 01-17 */
/** @var mysqli_result     $operarios      Operarios activos */
/** @var mysqli_result     $referencias    Referencias activas */
/** @var mysqli_result     $colores        Colores activos */
/** @var array             $datosMaquinas  Datos ya guardados por número de máquina */
/** @var array             $bloques        Bloques de turno disponibles */
/** @var string            $horarioTurno   Etiqueta horaria del turno abierto */
/** @var string            $supervisor_nombre */
/** @var string            $hoy */
/** @var string            $error */

// Importar authMiddleware.php
require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
// Importar config.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
// Importar registerController.php
require_once dirname(__DIR__) . '/controllers/registerController.php';
// Importar header.php
include dirname(__DIR__, 3) . '/templates/header.php';

// Pasar los catálogos a arrays (se reutilizan en cada fila)
$opsOperarios = $opsReferencias = $opsColores = [];
if($planilla){
    while($o = mysqli_fetch_assoc($operarios))   { $opsOperarios[]   = $o; }
    while($r = mysqli_fetch_assoc($referencias)) { $opsReferencias[] = $r; }
    while($c = mysqli_fetch_assoc($colores))     { $opsColores[]     = $c; }
}

// Ayudante: opciones <option> de un catálogo
if(!function_exists('opcionesCatalogo')){
    function opcionesCatalogo($lista, $idKey, $nombreKey, $seleccionado){
        $html = '<option value="">—</option>';
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
        if($v === null || $v === ''){ return ''; }
        return htmlspecialchars($v);
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<!-- Contenedor de Register -->
<div class="container" id="containerRegister">

    <h2 class="titulo-vista">Planilla de Producción · Sellado</h2>

<?php if(!$planilla): ?>

    <!-- ============================================
         ESTADO 1: NO HAY TURNO ABIERTO → NUEVA PLANILLA
    ============================================ -->
    <div class="card card-inicio">

        <?php if($error): ?>
            <p class="aviso aviso-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <p class="inicio-texto">Abre una nueva planilla para comenzar el turno.</p>

        <form method="POST" class="form-inicio">
            <input type="hidden" name="accion" value="iniciar">

            <div class="campo">
                <label>Fecha</label>
                <input type="text" value="<?= date('d/m/Y', strtotime($hoy)) ?>" readonly>
            </div>

            <div class="campo">
                <label>Turno</label>
                <select name="bloque" required>
                    <option value="">Seleccione el turno...</option>
                    <?php foreach($bloques as $nombre => $info): ?>
                        <option value="<?= $nombre ?>">
                            <?= $nombre ?> (<?= $info['horario'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Supervisor</label>
                <input type="text" value="<?= htmlspecialchars($supervisor_nombre) ?>" readonly>
            </div>

            <button type="submit" class="btn" id="btnIniciar">Iniciar planilla</button>
        </form>
    </div>

<?php else: ?>

    <!-- ============================================
         ESTADO 2: TURNO ABIERTO → PLANILLA DE TRABAJO
    ============================================ -->

    <!-- Encabezado del turno -->
    <div class="card encabezado-turno">
        <div class="dato-turno">
            <span class="dato-label">Fecha</span>
            <span class="dato-valor"><?= date('d/m/Y', strtotime($planilla['fecha_planilla'])) ?></span>
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
            <span class="dato-valor"><?= htmlspecialchars($planilla['codigo']) ?></span>
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
            <thead>
                <tr>
                    <th class="col-maquina">Máquina</th>
                    <th>Referencia</th>
                    <th>Color</th>
                    <th>X70</th>
                    <th>X90</th>
                    <th>X98</th>
                    <th>Peso 1</th>
                    <th>Peso 2</th>
                    <th>Peso 3</th>
                    <th>Peso 4</th>
                    <th>Peso 5</th>
                    <th>Observaciones</th>
                    <th></th>
                </tr>
            </thead>

            <?php while($m = mysqli_fetch_assoc($maquinas)):
                $num      = (int) $m['id_maquina'];
                $etq      = str_pad($num, 2, '0', STR_PAD_LEFT);
                $datos    = $datosMaquinas[$num] ?? null;
                $entradas = $datos['entradas'] ?? [];
                if(empty($entradas)){
                    $entradas = [[]]; // al menos una fila vacía
                }
            ?>
            <tbody class="grupo-maquina" data-maquina="<?= $num ?>">

                <!-- Fila de datos compartidos de la máquina -->
                <tr class="fila-info">
                    <td class="col-maquina">Máquina <?= $etq ?></td>
                    <td colspan="12">
                        <div class="maq-datos">
                            <label>
                                Operario
                                <select class="f-operario">
                                    <?= opcionesCatalogo($opsOperarios, 'id_operario', 'nombre_operario', $datos['id_operario'] ?? '') ?>
                                </select>
                            </label>
                            <label>
                                Otro operario
                                <input type="text" class="f-otro-operario" autocomplete="off"
                                       placeholder="Escribir si no está en la lista">
                            </label>
                            <label>
                                Jornada
                                <select class="f-jornada">
                                    <option value="">—</option>
                                    <option value="8 Horas"  <?= (($datos['jornada'] ?? '') === '8 Horas')  ? 'selected' : '' ?>>8 Horas</option>
                                    <option value="12 Horas" <?= (($datos['jornada'] ?? '') === '12 Horas') ? 'selected' : '' ?>>12 Horas</option>
                                </select>
                            </label>
                        </div>
                    </td>
                </tr>

                <!-- Entradas de la máquina -->
                <?php foreach($entradas as $ent): ?>
                <tr class="fila-entrada">
                    <td class="col-maquina"></td>
                    <td>
                        <select class="f-ref">
                            <?= opcionesCatalogo($opsReferencias, 'id_referencia', 'nombre_referencia', $ent['id_referencia'] ?? '') ?>
                        </select>
                    </td>
                    <td>
                        <select class="f-color">
                            <?= opcionesCatalogo($opsColores, 'id_color', 'nombre_color', $ent['id_color'] ?? '') ?>
                        </select>
                    </td>
                    <td><input type="number" class="f-x70" min="0" step="1" value="<?= valPlanilla($ent['x70'] ?? '') ?>"></td>
                    <td><input type="number" class="f-x90" min="0" step="1" value="<?= valPlanilla($ent['x90'] ?? '') ?>"></td>
                    <td><input type="number" class="f-x98" min="0" step="1" value="<?= valPlanilla($ent['x98'] ?? '') ?>"></td>
                    <td><input type="number" class="f-p1" min="0" step="0.01" value="<?= valPlanilla($ent['p1'] ?? '') ?>"></td>
                    <td><input type="number" class="f-p2" min="0" step="0.01" value="<?= valPlanilla($ent['p2'] ?? '') ?>"></td>
                    <td><input type="number" class="f-p3" min="0" step="0.01" value="<?= valPlanilla($ent['p3'] ?? '') ?>"></td>
                    <td><input type="number" class="f-p4" min="0" step="0.01" value="<?= valPlanilla($ent['p4'] ?? '') ?>"></td>
                    <td><input type="number" class="f-p5" min="0" step="0.01" value="<?= valPlanilla($ent['p5'] ?? '') ?>"></td>
                    <td><input type="text" class="f-obs" autocomplete="off" value="<?= valPlanilla($ent['obs'] ?? '') ?>"></td>
                    <td class="celda-acciones">
                        <button type="button" class="btn-quitar" title="Quitar entrada">&times;</button>
                    </td>
                </tr>
                <?php endforeach; ?>

                <!-- Botón para agregar una entrada más -->
                <tr class="fila-add">
                    <td class="col-maquina"></td>
                    <td colspan="12">
                        <button type="button" class="btn-entrada">+ Entrada</button>
                        <span class="tope-entradas">Máximo 5 entradas por máquina</span>
                    </td>
                </tr>

            </tbody>
            <?php endwhile; ?>
        </table>
    </div>

    <!-- Acciones finales -->
    <div class="acciones-planilla">
        <button type="button" class="btn" id="btnGuardar">Guardar</button>
        <button type="button" class="btn btn-finalizar" id="btnFinalizar">Guardar y finalizar turno</button>
    </div>

<?php endif; ?>

</div>

<!-- Modal de confirmación de finalización -->
<div class="overlay" id="modalFinalizar">
    <div class="modal">
        <div class="modal-header">
            <h2>Finalizar turno</h2>
            <button type="button" onclick="cerrarModal('modalFinalizar')">X</button>
        </div>
        <p class="modal-texto">
            Se guardarán los cambios, se cerrará el turno y se generará el PDF.<br>
            Esta acción no se puede deshacer.
        </p>
        <div class="btn-row">
            <button type="button" class="btn" id="btnConfirmarFinalizar">Sí, finalizar</button>
            <button type="button" class="btn btn-cancelar" onclick="cerrarModal('modalFinalizar')">Cancelar</button>
        </div>
    </div>
</div>

<!-- Modal de resultado -->
<div class="overlay" id="modalResultado">
    <div class="modal">
        <div class="modal-header">
            <h2 id="resultadoTitulo">Turno finalizado</h2>
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
