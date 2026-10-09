<?php
/** @var string $hoy */
/** @var array  $planilla */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/controllers/registerController.php';
include dirname(__DIR__, 3) . '/templates/header.php';

// Opciones <option> de un catálogo
if(!function_exists('opcionesCatalogoPeletizado')){
    function opcionesCatalogoPeletizado($lista, $idKey, $nombreKey){
        $html = '';
        foreach($lista as $item){
            $html .= '<option value="' . $item[$idKey] . '">' . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/css/register.css') ?>">

<?php if(!$planilla): ?>

<!-- Formulario de inicio: máquina, luego turno -->
<div class="container container-formulario" id="containerRegister" data-sel-zona="formulario">
    <h2 class="titulo-vista">Registro de Producción · Peletizado</h2>

    <div class="card">
        <div class="aviso-toast" id="avisoInicio" hidden>
            <span class="aviso-toast-texto" id="avisoInicioTexto"></span>
            <div class="aviso-toast-barra" id="avisoInicioBarra"></div>
        </div>

        <?php if(!$maquinaSeleccionada): ?>
        <!-- Paso 1: elegir máquina -->
        <form class="form-inicio" id="formElegirMaquina">
            <div class="campo">
                <label>Máquina</label>
                <select id="selMaquinaInicio" required>
                    <option value=""></option>
                    <?= opcionesCatalogoPeletizado($maquinas, 'id_maquina', 'nombre_maquina') ?>
                </select>
            </div>
            <button type="button" class="btn" id="btnSiguienteMaquina">Siguiente</button>
        </form>
        <script>
        document.getElementById('btnSiguienteMaquina').addEventListener('click', function () {
            var v = document.getElementById('selMaquinaInicio').value;
            if (!v) return;
            window.location.href = 'register.php?id_maquina=' + v;
        });
        </script>
        <?php else: ?>
        <!-- Paso 2: fecha y turno -->
        <form class="form-inicio" method="POST" action="<?= BASE_URL ?>/modules/peletizado/views/register.php">
            <input type="hidden" name="accion" value="iniciar">
            <input type="hidden" name="id_maquina" value="<?= (int) $maquinaSeleccionada['id_maquina'] ?>">

            <div class="campo">
                <label>Máquina</label>
                <input type="text" value="<?= htmlspecialchars($maquinaSeleccionada['nombre_maquina']) ?>" readonly>
                <a class="cambiar-maquina" href="<?= BASE_URL ?>/modules/peletizado/views/register.php">Cambiar máquina</a>
            </div>

            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>

            <div class="campo">
                <label>Turno</label>
                <select name="id_turno" required>
                    <option value=""></option>
                    <?= opcionesCatalogoPeletizado($turnos, 'id_turno', 'nombre_turno') ?>
                </select>
            </div>

            <button type="submit" class="btn" id="btnIniciar">Iniciar planilla</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if(!empty($abiertas)): ?>
    <!-- Turnos abiertos (de todas las máquinas si aún no se eligió una) -->
    <div class="card ext-abiertas">
        <h3 class="ext-subtitulo">Turnos abiertos</h3>
        <?php foreach($abiertas as $a): ?>
            <a class="ext-abierta" href="<?= BASE_URL ?>/modules/peletizado/views/register.php?id=<?= (int) $a['id_planilla'] ?>">
                <strong><?= htmlspecialchars($a['nombre_maquina']) ?></strong>
                <span><?= htmlspecialchars(date('d/m/Y', strtotime($a['fecha_planilla']))) ?> · <?= htmlspecialchars($a['nombre_turno']) ?></span>
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
<div class="container container-extrusion" id="containerRegister" data-sel-zona="formulario">

    <h2 class="titulo-vista">Registro de Producción · Peletizado</h2>

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

    <!-- Operarios -->
    <table class="ext-tabla" id="tablaOperarios">
        <thead>
            <tr><th>OPERARIO 1</th><th>OPERARIO 2</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <select id="selOperario1" required>
                        <option value=""></option>
                        <option value="otro">Otro</option>
                        <?= opcionesCatalogoPeletizado($operarios, 'id_operario', 'nombre_operario') ?>
                    </select>
                </td>
                <td>
                    <select id="selOperario2">
                        <option value=""></option>
                        <option value="otro">Otro</option>
                        <?= opcionesCatalogoPeletizado($operarios, 'id_operario', 'nombre_operario') ?>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Tabla de colores -->
    <table class="ext-tabla pel-tabla-colores">
        <thead>
            <tr>
                <th>COLOR</th><th>ALTA RETAL</th><th>BAJA</th><th>REFILTRADO</th>
                <th>SOPLADO</th><th>TORTA</th><th>LIMPIEZA</th><th></th>
            </tr>
        </thead>
        <tbody id="listaColores"></tbody>
        <tfoot>
            <tr>
                <td colspan="8">
                    <button type="button" class="btn-entrada btn-entrada-grande" id="btnAgregarColor">+ Agregar</button>
                </td>
            </tr>
            <tr>
                <td colspan="8" class="pel-observaciones">
                    <label>Observaciones</label>
                    <textarea id="txtObservaciones"></textarea>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Plantilla oculta: se clona para cada fila (nunca se muestra ella misma) -->
    <select id="plantillaSelectColor" hidden data-sel-buscar="si" data-sel-nativo>
        <option value=""></option>
        <option value="otro">Otro</option>
        <?= opcionesCatalogoPeletizado($colores, 'id_color', 'nombre_color') ?>
    </select>

    <!-- Acciones -->
    <div class="acciones-planilla">
        <form method="POST" id="formCancelarTurno" class="form-cancelar">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="id" value="<?= (int) $planilla['id_planilla'] ?>">
            <input type="hidden" name="id_maquina" value="<?= (int) $planilla['id_maquina'] ?>">
            <button type="submit" class="btn btn-cancelar-turno">Cancelar</button>
        </form>
        <button type="button" class="btn btn-finalizar" id="btnFinalizar">Finalizar turno</button>
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
            <a class="btn" id="btnNuevaPlanilla" href="<?= BASE_URL ?>/modules/peletizado/views/register.php?id_maquina=<?= (int) $planilla['id_maquina'] ?>">Nueva planilla</a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script>const planillaPeletizado = <?= json_encode([
    'id' => (int) $planilla['id_planilla'],
    'borrador' => $borrador,
    'maxColores' => 6,
], JSON_HEX_TAG) ?>;</script>
<script src="<?= BASE_URL ?>/modules/peletizado/scripts/register.js?v=<?= filemtime(dirname(__DIR__) . '/scripts/register.js') ?>"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
