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

// Ayudante: opciones de catálogo
if(!function_exists('opcionesCatalogo')){
    function opcionesCatalogo($lista, $idKey, $nombreKey, $seleccionado, $incluirVacio = true){
        // Primera opción vacía
        $html = $incluirVacio ? '<option value=""></option>' : '';
        foreach($lista as $item){
            $sel = ((string) $item[$idKey] === (string) $seleccionado) ? ' selected' : '';
            $html .= '<option value="' . $item[$idKey] . '"' . $sel . '>'
                   . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}

// Ayudante: select + Otro
if(!function_exists('campoCatalogoConOtro')){
    function campoCatalogoConOtro($lista, $idKey, $nombreKey, $seleccionado, $clase, $titulo = '', $textoLibre = ''){
        $esOperario = ($clase === 'f-operario');
        $conTexto = ($textoLibre !== '' && $textoLibre !== null); // "Otro" escrito y aún sin crear
        ob_start(); ?>
        <span class="campo-otro-wrap <?= $esOperario ? 'campo-otro-apilado' : '' ?>">
            <select class="<?= $clase ?> tiene-otro" <?= $titulo ? 'title="' . htmlspecialchars($titulo) . '"' : '' ?> <?= ($conTexto && !$esOperario) ? 'hidden' : '' ?>>
                <option value=""></option>
                <option value="otro" <?= $conTexto ? 'selected' : '' ?>>Otro</option>
                <?= opcionesCatalogo($lista, $idKey, $nombreKey, $conTexto ? '' : $seleccionado, false) ?>
            </select>
            <?php if($esOperario): ?>
                <!-- Espacio siempre reservado -->
                <input type="text" class="<?= $clase ?> campo-libre campo-libre-reservado <?= $conTexto ? 'activo' : '' ?>" autocomplete="off" placeholder="Escribe..." value="<?= $conTexto ? htmlspecialchars($textoLibre) : '' ?>">
            <?php else: ?>
                <input type="text" class="<?= $clase ?> campo-libre" <?= $conTexto ? '' : 'hidden' ?> autocomplete="off" placeholder="Escribe..." value="<?= $conTexto ? htmlspecialchars($textoLibre) : '' ?>">
                <button type="button" class="btn-volver-lista" <?= $conTexto ? '' : 'hidden' ?> title="Volver a la lista">&#8634;</button>
            <?php endif; ?>
        </span>
        <?php return ob_get_clean();
    }
}

// Ayudante: valor limpio
if(!function_exists('valPlanilla')){
    function valPlanilla($v){
        return ($v === null || $v === '') ? '' : htmlspecialchars($v);
    }
}

// Ayudante: celdas de entrada
if(!function_exists('celdasEntradaPlanilla')){
    function celdasEntradaPlanilla($ent, $referenciasMaquina, $colores, $esEsp = false){
        $seleccionadoRef = $esEsp ? ($ent['id_referencia_esp'] ?? '') : ($ent['id_referencia'] ?? '');
        ob_start(); ?>
        <td><?= campoCatalogoConOtro($referenciasMaquina, 'id', 'nombre', $seleccionadoRef, 'f-ref', '', $ent['txt_ref'] ?? '') ?></td>
        <td class="td-grueso"><?= campoCatalogoConOtro($colores, 'id_color', 'nombre_color', $ent['id_color'] ?? '', 'f-color', '', $ent['txt_color'] ?? '') ?></td>
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

<!-- Formulario de inicio de turno -->
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

    <?php if(!empty($abiertas)): ?>
    <!-- Turnos abiertos para continuar -->
    <div class="card ext-abiertas">
        <h3 class="ext-subtitulo">Turnos abiertos</h3>
        <?php foreach($abiertas as $a): ?>
            <a class="ext-abierta" href="<?= BASE_URL ?>/modules/sellado/views/register.php?codigo=<?= urlencode($a['codigo']) ?>">
                <strong><?= htmlspecialchars($a['codigo']) ?></strong>
                <span><?= htmlspecialchars(date('d/m/Y', strtotime($a['fecha_planilla']))) ?> · <?= htmlspecialchars($a['bloque']) ?> · <?= htmlspecialchars($a['supervisor_nombre']) ?></span>
                <span class="ext-continuar">Continuar</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
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
            <!-- Solo al iniciar el turno -->
            <span class="dato-valor"><?= htmlspecialchars(date('d/m/Y', strtotime($planilla['fecha_planilla']))) ?></span>
            <input type="hidden" id="fechaPlanilla" value="<?= htmlspecialchars($planilla['fecha_planilla']) ?>">
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

    <!-- Zona de avisos -->
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
                    <th colspan="5">PESOS POR HORA</th>
                    <th>OBSERVACIONES</th>
                    <th></th>
                </tr>
            </thead>

            <?php foreach($maquinas as $m):
                // numero_maquina solo etiqueta
                $num       = (int) $m['numero_maquina'];
                $idMaquina = (int) $m['id_maquina'];
                $esEsp     = (bool) $m['usa_referencias_esp'];
                $referenciasMaquina = $m['referencias'];
                $etq      = str_pad($num, 2, '0', STR_PAD_LEFT);
                $datos    = $datosMaquinas[$num] ?? null;
                $entradas = $datos['entradas'] ?? [];
                // Mínimo 3 filas por máquina
                while(count($entradas) < 3){
                    $entradas[] = [];
                }
                $span = count($entradas) + 1; // Fila del botón + Agregar
            ?>
            <tbody class="grupo-maquina" data-maquina="<?= $num ?>" data-id-maquina="<?= $idMaquina ?>">
                <?php foreach($entradas as $i => $ent): ?>
                <tr class="fila-entrada">
                    <?php if($i === 0): ?>
                        <!-- Número de máquina -->
                        <td class="col-maquina" rowspan="<?= $span ?>"><?= $num ?></td>
                        <!-- Bloque operario / jornada -->
                        <td class="col-operario" rowspan="<?= $span ?>">
                            <div class="op-bloque">
                                <!-- Operario: Otro agrega casilla -->
                                <label class="op-sub">Nombre
                                    <?= campoCatalogoConOtro($operarios, 'id_operario', 'nombre_operario', $datos['id_operario'] ?? '', 'f-operario', 'Operario', $datos['txt_operario'] ?? '') ?>
                                </label>
                                <!-- Jornada: Otro reemplaza select -->
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

                <!-- Agregar entrada -->
                <tr class="fila-add">
                    <td colspan="2" class="td-grueso">
                        <button type="button" class="btn-entrada">+ Agregar</button>
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

    <!-- Nota del turno (solo PDF) -->
    <div class="nota-general">
        <label for="notaGeneral">NOTA:</label>
        <textarea id="notaGeneral" rows="3"></textarea>
    </div>

    <!-- Acciones -->
    <div class="acciones-planilla">
        <!-- Cancelar turno -->
        <form method="POST" id="formCancelarTurno" class="form-cancelar"
              onsubmit="return confirm('¿Cancelar el turno? Se perderán los datos no finalizados de esta planilla.');">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="codigo" value="<?= htmlspecialchars($planilla['codigo']) ?>">
            <button type="submit" class="btn btn-cancelar-turno">Cancelar</button>
        </form>
        <button type="button" class="btn btn-finalizar" id="btnFinalizar">Finalizar turno</button>
    </div>

    <!-- Plantilla de una entrada nueva (para el botón "+ Agregar"); la referencia
         se llena vacía y register.js la puebla según la máquina al clonar la fila -->
    <template id="tplEntrada">
        <tr class="fila-entrada"><?= celdasEntradaPlanilla([], [], $colores) ?></tr>
    </template>

</div>

<!-- Modal de confirmación de finalización -->
<div class="overlay" id="modalFinalizar">
    <div class="modal">
        <div class="modal-header">
            <h2>Confirmar finalización</h2>
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
