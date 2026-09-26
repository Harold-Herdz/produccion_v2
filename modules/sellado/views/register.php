<?php
/** @var array|null $planilla       Sobre del turno abierto (o null) */
/** @var array      $maquinas       Máquinas 01-17 */
/** @var array      $operarios      Operarios activos */
/** @var array      $referencias    Referencias activas */
/** @var array      $colores        Colores activos */
/** @var array      $datosMaquinas  Datos ya guardados por número de máquina */
/** @var array      $bloques        Bloques de turno disponibles */
/** @var string     $horarioTurno   Etiqueta horaria del turno abierto */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/controllers/registerController.php';
include dirname(__DIR__, 3) . '/templates/header.php';

// Ayudante: opciones <option> de un catálogo
if(!function_exists('opcionesCatalogo')){
    function opcionesCatalogo($lista, $idKey, $nombreKey, $seleccionado, $incluirVacio = true){
        // Primera opción realmente vacía (sin "-" ni guion)
        $html = $incluirVacio ? '<option value=""></option>' : '';
        foreach($lista as $item){
            $sel = ((string) $item[$idKey] === (string) $seleccionado) ? ' selected' : '';
            $html .= '<option value="' . $item[$idKey] . '"' . $sel . '>'
                   . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}

// Ayudante: select de catálogo + "Otro". En Operario aparece una casilla
// nueva al lado; en los demás la casilla reemplaza al select (ver register.js).
// El botón "volver" solo se usa/aparece en el modo de reemplazo (no Operario).
if(!function_exists('campoCatalogoConOtro')){
    function campoCatalogoConOtro($lista, $idKey, $nombreKey, $seleccionado, $clase, $titulo = ''){
        $esOperario = ($clase === 'f-operario');
        ob_start(); ?>
        <span class="campo-otro-wrap <?= $esOperario ? 'campo-otro-apilado' : '' ?>">
            <select class="<?= $clase ?> tiene-otro" <?= $titulo ? 'title="' . htmlspecialchars($titulo) . '"' : '' ?>>
                <option value=""></option>
                <option value="otro">Otro</option>
                <?= opcionesCatalogo($lista, $idKey, $nombreKey, $seleccionado, false) ?>
            </select>
            <?php if($esOperario): ?>
                <!-- El espacio de esta casilla queda reservado siempre (visibility, no display) -->
                <input type="text" class="<?= $clase ?> campo-libre campo-libre-reservado" autocomplete="off" placeholder="Escribe...">
            <?php else: ?>
                <input type="text" class="<?= $clase ?> campo-libre" hidden autocomplete="off" placeholder="Escribe...">
                <button type="button" class="btn-volver-lista" hidden title="Volver a la lista">&#8634;</button>
            <?php endif; ?>
        </span>
        <?php return ob_get_clean();
    }
}

// Ayudante: valor limpio para inputs (evita mostrar NULL / 0.00 innecesario)
if(!function_exists('valPlanilla')){
    function valPlanilla($v){
        return ($v === null || $v === '') ? '' : htmlspecialchars($v);
    }
}

// Ayudante: las celdas de una entrada (referencia..observaciones + eliminar).
// $referenciasMaquina ya viene filtrada a lo que produce esa máquina (o el catálogo
// completo de Referencias Especiales si $esEsp); el valor preseleccionado sale de
// id_referencia_esp en vez de id_referencia cuando corresponde.
if(!function_exists('celdasEntradaPlanilla')){
    function celdasEntradaPlanilla($ent, $referenciasMaquina, $colores, $esEsp = false){
        $seleccionadoRef = $esEsp ? ($ent['id_referencia_esp'] ?? '') : ($ent['id_referencia'] ?? '');
        ob_start(); ?>
        <td><?= campoCatalogoConOtro($referenciasMaquina, 'id', 'nombre', $seleccionadoRef, 'f-ref') ?></td>
        <td class="td-grueso"><?= campoCatalogoConOtro($colores, 'id_color', 'nombre_color', $ent['id_color'] ?? '', 'f-color') ?></td>
        <td><input type="number" class="f-x70" min="0" step="1" value="<?= valPlanilla($ent['x70'] ?? '') ?>"></td>
        <td><input type="number" class="f-x90" min="0" step="1" value="<?= valPlanilla($ent['x90'] ?? '') ?>"></td>
        <td class="td-grueso"><input type="number" class="f-x98" min="0" step="1" value="<?= valPlanilla($ent['x98'] ?? '') ?>"></td>
        <td><input type="number" class="f-p1" min="0" step="0.01" value="<?= valPlanilla($ent['p1'] ?? '') ?>"></td>
        <td><input type="number" class="f-p2" min="0" step="0.01" value="<?= valPlanilla($ent['p2'] ?? '') ?>"></td>
        <td><input type="number" class="f-p3" min="0" step="0.01" value="<?= valPlanilla($ent['p3'] ?? '') ?>"></td>
        <td><input type="number" class="f-p4" min="0" step="0.01" value="<?= valPlanilla($ent['p4'] ?? '') ?>"></td>
        <td class="td-grueso"><input type="number" class="f-p5" min="0" step="0.01" value="<?= valPlanilla($ent['p5'] ?? '') ?>"></td>
        <td><input type="text" class="f-obs" autocomplete="off" value="<?= valPlanilla($ent['obs'] ?? '') ?>"></td>
        <td class="celda-acciones"><button type="button" class="btn-quitar" title="Quitar entrada">&times;</button></td>
        <?php return ob_get_clean();
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<?php if(!$planilla): ?>

<!-- Sin turno abierto: formulario para iniciar uno nuevo -->
<div class="container container-formulario" id="containerRegister">
    <h2 class="titulo-vista">Registro de Producción · Sellado</h2>

    <div class="card">
        <div class="aviso-toast" id="avisoInicio" hidden>
            <span class="aviso-toast-texto" id="avisoInicioTexto"></span>
            <div class="aviso-toast-barra" id="avisoInicioBarra"></div>
        </div>

        <form class="form-inicio" method="POST" action="<?= BASE_URL ?>/modules/sellado/views/register.php">
            <input type="hidden" name="accion" value="iniciar">

            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
            </div>

            <div class="campo">
                <label>Turno</label>
                <select name="bloque" required>
                    <option value=""></option>
                    <?php foreach(bloquesTurno() as $nombre => $datosBloque): ?>
                        <option value="<?= $nombre ?>"><?= $nombre ?> (<?= $datosBloque['horario'] ?? '' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="campo">
                <label>Supervisor</label>
                <select name="id_supervisor" required <?= empty($supervisores) ? 'disabled' : '' ?>>
                    <option value=""></option>
                    <?php foreach($supervisores as $s): ?>
                        <option value="<?= $s['id_operario'] ?>"><?= htmlspecialchars($s['nombre_operario']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if(empty($supervisores)): ?>
                    <p class="aviso aviso-error">No hay operarios marcados como supervisor. Ve a Catálogos › Operarios para marcar uno.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn" id="btnIniciar" <?= empty($supervisores) ? 'disabled' : '' ?>>Iniciar planilla</button>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/alertToast.js"></script>
<?php $regError = $_GET['reg_error'] ?? ''; if($regError !== ''): ?>
<script>
    crearAvisoToast("avisoInicio", "avisoInicioTexto", "avisoInicioBarra")
        .mostrar(<?= json_encode($regError) ?>, "error", true);
</script>
<?php endif; ?>
<?php include dirname(__DIR__, 3) . '/templates/footer.php'; return; endif; ?>

<!-- Contenedor de Register -->
<div class="container container-planilla" id="containerRegister">

    <h2 class="titulo-vista">Registro de Producción · Sellado</h2>

    <!-- Encabezado del turno -->
    <div class="card encabezado-turno">
        <div class="dato-turno">
            <span class="dato-label">Fecha</span>
            <!-- Solo se puede elegir al iniciar el turno, no mientras se llena la planilla -->
            <input type="date" id="fechaPlanilla" value="<?= htmlspecialchars($planilla['fecha_planilla']) ?>" disabled>
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
                // numero_maquina (1-17) es solo para agrupar/etiquetar; id_maquina es el id
                // real del catálogo (no coincide con el número) y es el que se guarda como FK.
                $num       = (int) $m['numero_maquina'];
                $idMaquina = (int) $m['id_maquina'];
                $esEsp     = (bool) $m['usa_referencias_esp'];
                $referenciasMaquina = $m['referencias'];
                $etq      = str_pad($num, 2, '0', STR_PAD_LEFT);
                $datos    = $datosMaquinas[$num] ?? null;
                $entradas = $datos['entradas'] ?? [];
                // Cada máquina arranca con al menos 3 filas de producción visibles
                while(count($entradas) < 3){
                    $entradas[] = [];
                }
                $span = count($entradas) + 1; // + fila del botón "+ Entrada"
            ?>
            <tbody class="grupo-maquina" data-maquina="<?= $num ?>" data-id-maquina="<?= $idMaquina ?>">
                <?php foreach($entradas as $i => $ent): ?>
                <tr class="fila-entrada">
                    <?php if($i === 0): ?>
                        <!-- Número de máquina agrupando visualmente todas sus entradas (visual: sin cero a la izquierda; lo guardado no depende de este texto) -->
                        <td class="col-maquina" rowspan="<?= $span ?>"><?= $num ?></td>
                        <!-- Bloque de operario / jornada de la máquina -->
                        <td class="col-operario" rowspan="<?= $span ?>">
                            <div class="op-bloque">
                                <!-- Operario: "Otro" agrega una casilla nueva debajo (no reemplaza) -->
                                <label class="op-sub">Nombre
                                    <?= campoCatalogoConOtro($operarios, 'id_operario', 'nombre_operario', $datos['id_operario'] ?? '', 'f-operario', 'Operario') ?>
                                </label>
                                <!-- Jornada: "Otro" reemplaza el select en el mismo lugar -->
                                <label class="op-sub">Jornada
                                    <span class="campo-otro-wrap">
                                        <select class="f-jornada tiene-otro">
                                            <option value=""></option>
                                            <option value="otro">Otro</option>
                                            <option value="8 Horas"  <?= (($datos['jornada'] ?? '') === '8 Horas')  ? 'selected' : '' ?>>8 Horas</option>
                                            <option value="12 Horas" <?= (($datos['jornada'] ?? '') === '12 Horas') ? 'selected' : '' ?>>12 Horas</option>
                                        </select>
                                        <input type="text" class="f-jornada campo-libre" hidden autocomplete="off" placeholder="Escribe...">
                                        <button type="button" class="btn-volver-lista" hidden title="Volver a la lista">&#8634;</button>
                                    </span>
                                </label>
                            </div>
                        </td>
                    <?php endif; ?>
                    <?= celdasEntradaPlanilla($ent, $referenciasMaquina, $colores, $esEsp) ?>
                </tr>
                <?php endforeach; ?>

                <!-- Agregar otra entrada a esta máquina -->
                <tr class="fila-add">
                    <td colspan="2" class="td-grueso">
                        <button type="button" class="btn-entrada">+ Entrada</button>
                        <span class="tope-entradas">Máximo 6 entradas por máquina</span>
                    </td>
                    <td colspan="3" class="td-grueso"></td>
                    <td colspan="5" class="td-grueso"></td>
                    <td></td>
                    <td class="celda-acciones"></td>
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

    <!-- Plantilla de una entrada nueva (para el botón "+ Entrada"); la referencia
         se llena vacía y register.js la puebla según la máquina al clonar la fila -->
    <template id="tplEntrada">
        <tr class="fila-entrada"><?= celdasEntradaPlanilla([], [], $colores) ?></tr>
    </template>

</div>

<!-- Modal de confirmación de finalización -->
<div class="overlay" id="modalFinalizar">
    <div class="modal">
        <div class="modal-header">
            <h2>Confirmar finalización de turno</h2>
            <button type="button" onclick="cerrarModal('modalFinalizar')">X</button>
        </div>
        <div class="btn-row">
            <button type="button" class="btn" id="btnConfirmarFinalizar">Sí</button>
            <button type="button" class="btn btn-cancelar" onclick="cerrarModal('modalFinalizar')">No</button>
        </div>
    </div>
</div>

<!-- Modal de resultado -->
<div class="overlay" id="modalResultado">
    <div class="modal">
        <div class="modal-header">
            <h2 id="resultadoTexto">Turno finalizado correctamente</h2>
        </div>
        <div class="btn-row">
            <a class="btn" id="btnVerPdf" href="#" target="_blank">Ver PDF</a>
            <a class="btn" id="btnNuevaPlanilla" href="<?= BASE_URL ?>/modules/sellado/views/register.php">Nueva planilla</a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script>const mapaReferenciasMaquina = <?= json_encode($mapaReferenciasMaquina, JSON_HEX_TAG) ?>;</script>
<script src="<?= BASE_URL ?>/modules/sellado/scripts/register.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
