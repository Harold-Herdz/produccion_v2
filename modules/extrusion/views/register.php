<?php
/** @var string $hoy */
/** @var array  $planilla */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/controllers/registerController.php';
include dirname(__DIR__, 3) . '/templates/header.php';

// Opciones <option> de un catálogo
if(!function_exists('opcionesCatalogoExtrusion')){
    function opcionesCatalogoExtrusion($lista, $idKey, $nombreKey, $prefijo = ''){
        $html = '';
        foreach($lista as $item){
            $html .= '<option value="' . $prefijo . $item[$idKey] . '">' . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">

<?php if(!$planilla): ?>

<!-- Formulario de inicio de turno -->
<div class="container container-formulario" id="containerRegister">
    <h2 class="titulo-vista">Registro de Producción · Extrusión</h2>

    <div class="card">
        <div class="aviso-toast" id="avisoInicio" hidden>
            <span class="aviso-toast-texto" id="avisoInicioTexto"></span>
            <div class="aviso-toast-barra" id="avisoInicioBarra"></div>
        </div>

        <form class="form-inicio" method="POST" action="<?= BASE_URL ?>/modules/extrusion/views/register.php">
            <input type="hidden" name="accion" value="iniciar">

            <div class="campo">
                <label>Máquina</label>
                <select name="id_maquina" required>
                    <option value=""></option>
                    <?= opcionesCatalogoExtrusion($maquinas, 'id_maquina', 'nombre_maquina') ?>
                </select>
            </div>

            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>

            <div class="campo">
                <label>Turno</label>
                <select name="id_turno" required>
                    <option value=""></option>
                    <?= opcionesCatalogoExtrusion($turnos, 'id_turno', 'nombre_turno') ?>
                </select>
            </div>

            <div class="campo">
                <label>Operador</label>
                <select name="id_operador" required>
                    <option value=""></option>
                    <?= opcionesCatalogoExtrusion($operadores, 'id_operador', 'nombre_operador') ?>
                </select>
            </div>

            <button type="submit" class="btn" id="btnIniciar">Iniciar planilla</button>
        </form>
    </div>

    <?php if(!empty($abiertas)): ?>
    <!-- Turnos abiertos para continuar -->
    <div class="card ext-abiertas">
        <h3 class="ext-subtitulo">Turnos abiertos</h3>
        <?php foreach($abiertas as $a): ?>
            <a class="ext-abierta" href="<?= BASE_URL ?>/modules/extrusion/views/register.php?id=<?= (int) $a['id_planilla'] ?>">
                <strong><?= htmlspecialchars($a['nombre_maquina']) ?></strong>
                <span><?= htmlspecialchars(date('d/m/Y', strtotime($a['fecha_planilla']))) ?> · <?= htmlspecialchars($a['nombre_turno']) ?> · <?= htmlspecialchars($a['nombre_operador']) ?></span>
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

<!-- Planilla del turno -->
<div class="container container-extrusion" id="containerRegister">

    <h2 class="titulo-vista">Registro de Producción · Extrusión</h2>

    <!-- Encabezado del turno -->
    <div class="card encabezado-turno">
        <div class="dato-turno">
            <span class="dato-label">Fecha</span>
            <span class="dato-valor"><?= htmlspecialchars(date('d/m/Y', strtotime($planilla['fecha_planilla']))) ?></span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Turno</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['nombre_turno']) ?></span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Operador</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['nombre_operador']) ?></span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Máquina</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['nombre_maquina']) ?></span>
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

    <!-- Zona de avisos -->
    <div id="zonaAvisos"></div>

    <!-- Referencia, color y lámina P -->
    <table class="ext-tabla" id="tablaSeleccion">
        <thead>
            <tr><th>REFERENCIA</th><th>COLOR</th><th>LÁMINA P</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <select id="selReferencia">
                        <option value=""></option>
                        <option value="otro">Otro</option>
                        <optgroup label="Referencias"><?= opcionesCatalogoExtrusion($referencias, 'id', 'nombre', 'r:') ?></optgroup>
                        <optgroup label="Referencias especiales"><?= opcionesCatalogoExtrusion($referenciasEsp, 'id', 'nombre', 'e:') ?></optgroup>
                    </select>
                </td>
                <td>
                    <select id="selColor">
                        <option value=""></option>
                        <option value="otro">Otro</option>
                        <?= opcionesCatalogoExtrusion($colores, 'id_color', 'nombre_color') ?>
                    </select>
                </td>
                <td>
                    <select id="selLamina">
                        <option value=""></option>
                        <option value="otro">Otro</option>
                        <?= opcionesCatalogoExtrusion($laminas, 'id', 'nombre') ?>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Pesos y botones de cambio -->
    <table class="ext-tabla ext-tabla-cuerpo">
        <thead>
            <tr><th>PESOS</th><th>CAMBIOS</th></tr>
        </thead>
        <tbody>
            <tr>
                <td class="ext-col-pesos">
                    <div class="ext-pesos-fila">
                        <div class="ext-pesos-lista">
                            <div id="listaPesos"></div>
                            <button type="button" class="btn-entrada btn-entrada-grande" id="btnAgregarPeso">+ Agregar</button>
                        </div>
                        <!-- Resumen en vivo del turno -->
                        <aside class="ext-resumen">
                            <div class="ext-resumen-titulo">Resumen del turno</div>
                            <div class="ext-resumen-linea"><span>Rollos pesados</span><strong id="resRollos">0</strong></div>
                            <div class="ext-resumen-linea"><span>Peso total</span><strong id="resPeso">0,00</strong></div>
                            <div class="ext-resumen-linea"><span>Promedio</span><strong id="resPromedio">0,00</strong></div>
                            <div class="ext-resumen-sub">Combinación actual</div>
                            <div class="ext-resumen-linea"><span>Referencia</span><strong id="resRef">-</strong></div>
                            <div class="ext-resumen-linea"><span>Color</span><strong id="resColor">-</strong></div>
                            <div class="ext-resumen-linea"><span>Lámina P</span><strong id="resLamina">-</strong></div>
                        </aside>
                    </div>
                </td>
                <td class="ext-col-cambios">
                    <div class="ext-cambios-lista">
                        <button type="button" class="btn" data-cambio="referencia">Cambiar Referencia</button>
                        <button type="button" class="btn" data-cambio="color">Cambiar Color</button>
                        <button type="button" class="btn" data-cambio="lamina">Cambiar Lámina P</button>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Acciones -->
    <div class="acciones-planilla">
        <form method="POST" id="formCancelarTurno" class="form-cancelar"
              onsubmit="return confirm('¿Cancelar el turno? Se perderán los datos no finalizados de esta planilla.');">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="id" value="<?= (int) $planilla['id_planilla'] ?>">
            <button type="submit" class="btn btn-cancelar-turno">Cancelar</button>
        </form>
        <button type="button" class="btn btn-finalizar" id="btnFinalizar">Finalizar turno</button>
    </div>
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
            <a class="btn" id="btnNuevaPlanilla" href="<?= BASE_URL ?>/modules/extrusion/views/register.php">Nueva planilla</a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script>const planillaExtrusion = <?= json_encode(['id' => (int) $planilla['id_planilla'], 'borrador' => $borrador, 'maxPesos' => 10], JSON_HEX_TAG) ?>;</script>
<script src="<?= BASE_URL ?>/modules/extrusion/scripts/register.js"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
