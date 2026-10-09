<?php
/** @var string $hoy */
/** @var array  $planilla */

require_once dirname(__DIR__, 3) . '/auth/authMiddleware.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__) . '/controllers/registerController.php';
include dirname(__DIR__, 3) . '/templates/header.php';

// Opciones <option> de un catálogo
if(!function_exists('opcionesCatalogoMezcla')){
    function opcionesCatalogoMezcla($lista, $idKey, $nombreKey){
        $html = '';
        foreach($lista as $item){
            $html .= '<option value="' . $item[$idKey] . '">' . htmlspecialchars($item[$nombreKey]) . '</option>';
        }
        return $html;
    }
}

// Select con Otro: el select y el texto libre no se envían directos, solo
// alimentan un input oculto con el valor final (ver scripts/register.js)
if(!function_exists('campoConOtroMezcla')){
    function campoConOtroMezcla($lista, $idKey, $nombreKey, $id, $nombre, $requerido = true){
        ob_start(); ?>
        <select id="<?= $id ?>" class="tiene-otro"<?= $requerido ? ' required' : '' ?>>
            <option value=""></option>
            <option value="otro">Otro</option>
            <?= opcionesCatalogoMezcla($lista, $idKey, $nombreKey) ?>
        </select>
        <input type="text" class="campo-libre" id="<?= $id ?>Texto" hidden autocomplete="off" placeholder="Escribe el nombre">
        <input type="hidden" name="<?= $nombre ?>" id="<?= $id ?>Valor">
        <?php return ob_get_clean();
    }
}

// Celda numérica vacía (nunca 0), con la unidad al lado del input
if(!function_exists('celdaNumeroMezcla')){
    function celdaNumeroMezcla($id, $unidad){
        return '<div class="mez-celda"><input type="number" name="' . $id . '" min="0" step="any" placeholder="">'
            . '<span class="mez-unidad">' . $unidad . '</span></div>';
    }
}

// Celda de texto libre (observaciones) dentro de una mez-tabla
if(!function_exists('celdaTextoMezcla')){
    function celdaTextoMezcla($id){
        return '<input type="text" class="mez-input-texto" name="' . $id . '" autocomplete="off">';
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/css/register.css') ?>">

<?php if(!$planilla): ?>

<!-- Formulario de inicio: máquina, luego fecha -->
<div class="container container-formulario" id="containerRegister" data-sel-zona="formulario">
    <h2 class="titulo-vista">Registro de Mezclas</h2>

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
                    <?= opcionesCatalogoMezcla($maquinas, 'id_maquina', 'nombre_maquina') ?>
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
        <!-- Paso 2: fecha -->
        <form class="form-inicio" method="POST" action="<?= BASE_URL ?>/modules/mezclas/views/register.php">
            <input type="hidden" name="accion" value="iniciar">
            <input type="hidden" name="id_maquina" value="<?= (int) $maquinaSeleccionada['id_maquina'] ?>">

            <div class="campo">
                <label>Máquina</label>
                <input type="text" value="<?= htmlspecialchars($maquinaSeleccionada['nombre_maquina']) ?>" readonly>
                <a class="cambiar-maquina" href="<?= BASE_URL ?>/modules/mezclas/views/register.php">Cambiar máquina</a>
            </div>

            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($hoy) ?>" required>
            </div>

            <button type="submit" class="btn" id="btnIniciar">Iniciar planilla</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if(!empty($abiertas)): ?>
    <!-- Planillas abiertas (de todas las máquinas si aún no se eligió una) -->
    <div class="card ext-abiertas">
        <h3 class="ext-subtitulo">Mezclas abiertas</h3>
        <?php foreach($abiertas as $a): ?>
            <a class="ext-abierta" href="<?= BASE_URL ?>/modules/mezclas/views/register.php?id=<?= (int) $a['id_planilla'] ?>">
                <strong><?= htmlspecialchars($a['nombre_maquina']) ?></strong>
                <span><?= htmlspecialchars(date('d/m/Y', strtotime($a['fecha_planilla']))) ?> · <?= htmlspecialchars($a['codigo']) ?></span>
                <span class="ext-continuar">Continuar</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/shared/alertToast.js"></script>
<?php $regError = $_GET['reg_error'] ?? ''; $aviso = $_GET['aviso'] ?? ''; if($regError !== '' || $aviso !== ''): ?>
<script>
    crearAvisoToast("avisoInicio", "avisoInicioTexto", "avisoInicioBarra")
        .mostrar(<?= json_encode($regError !== '' ? $regError : $aviso) ?>, "<?= $regError !== '' ? 'error' : 'info' ?>", true);
</script>
<?php endif; ?>
<?php include dirname(__DIR__, 3) . '/templates/footer.php'; return; endif; ?>

<!-- Planilla de la mezcla -->
<div class="container container-extrusion" id="containerRegister" data-sel-zona="formulario">

    <h2 class="titulo-vista">Registro de Mezclas</h2>

    <!-- Encabezado -->
    <div class="card encabezado-turno">
        <div class="dato-turno">
            <span class="dato-label">Fecha</span>
            <span class="dato-valor"><?= htmlspecialchars(date('d/m/Y', strtotime($planilla['fecha_planilla']))) ?></span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Máquina</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['nombre_maquina']) ?></span>
        </div>
        <div class="dato-turno">
            <span class="dato-label">Código</span>
            <span class="dato-valor"><?= htmlspecialchars($planilla['codigo']) ?></span>
        </div>
    </div>

    <!-- Zona de avisos -->
    <div id="zonaAvisos"></div>
    <?php $regError = $_GET['reg_error'] ?? ''; if($regError !== ''): ?>
    <div class="aviso-toast" id="avisoPlanilla"><span class="aviso-toast-texto"><?= htmlspecialchars($regError) ?></span></div>
    <?php endif; ?>

    <form method="POST" id="formFinalizarMezcla" action="<?= BASE_URL ?>/modules/mezclas/controllers/registerController.php">
        <input type="hidden" name="accion" value="finalizar">
        <input type="hidden" name="id" value="<?= (int) $planilla['id_planilla'] ?>">

        <!-- Operario y referencia -->
        <table class="ext-tabla mez-tabla-cabecera">
            <thead>
                <tr><th>OPERARIO</th><th>REFERENCIA</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= campoConOtroMezcla($operarios, 'id_operario', 'nombre_operario', 'operarioMezcla', 'id_operario') ?></td>
                    <td><?= campoConOtroMezcla($referencias, 'id_referencia', 'nombre_referencia', 'referenciaMezcla', 'id_referencia', false) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Tabla 1 -->
        <div class="mez-tabla-wrap">
        <table class="ext-tabla mez-tabla">
            <thead>
                <tr>
                    <th>BLANCO R</th><th>NEGRO R</th><th>ROJO R</th><th>AMARILLO R</th>
                    <th>AZUL R</th><th>VERDE R</th><th>NARANJA R</th><th>MARRÓN R</th><th>LADRILLO R</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= celdaNumeroMezcla('blanco_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('negro_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('rojo_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('amarillo_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('azul_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('verde_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('naranja_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('marron_r', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('ladrillo_r', 'x25') ?></td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- Tabla 2 -->
        <div class="mez-tabla-wrap">
        <table class="ext-tabla mez-tabla mez-tabla-mixta">
            <thead>
                <tr>
                    <th>ORIGINAL B</th><th>FG</th><th>MASTER</th><th>LINEAL</th>
                    <th>DESHIDRATANTE</th><th>OBSERVACIONES</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= celdaNumeroMezcla('original_b', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('fg', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('master', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('lineal', 'x25') ?></td>
                    <td><?= celdaNumeroMezcla('deshidratante', 'kg') ?></td>
                    <td><?= celdaTextoMezcla('observaciones') ?></td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- Tabla 3 -->
        <div class="mez-tabla-wrap">
        <table class="ext-tabla mez-tabla">
            <thead>
                <tr>
                    <th>P. BLANCO</th><th>P. NEGRO</th><th>P. ROJO</th><th>P. AMARILLO</th>
                    <th>P. AZUL</th><th>P. VERDE</th><th>P. NARANJA</th>
                    <th>P. MARRÓN</th><th>P. LADRILLO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= celdaNumeroMezcla('p_blanco', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_negro', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_rojo', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_amarillo', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_azul', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_verde', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_naranja', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_marron', 'kg') ?></td>
                    <td><?= celdaNumeroMezcla('p_ladrillo', 'kg') ?></td>
                </tr>
            </tbody>
        </table>
        </div>

    </form>

    <!-- Acciones -->
    <div class="acciones-planilla">
        <form method="POST" id="formCancelarMezcla" class="form-cancelar">
            <input type="hidden" name="accion" value="cancelar">
            <input type="hidden" name="id" value="<?= (int) $planilla['id_planilla'] ?>">
            <input type="hidden" name="id_maquina" value="<?= (int) $planilla['id_maquina'] ?>">
            <button type="submit" class="btn btn-cancelar-turno">Cancelar</button>
        </form>
        <button type="submit" form="formFinalizarMezcla" class="btn btn-finalizar" id="btnFinalizarMezcla">Finalizar Mezcla</button>
    </div>
</div>

<!-- Scripts -->
<script src="<?= BASE_URL ?>/modules/shared/global.js"></script>
<script src="<?= BASE_URL ?>/modules/mezclas/scripts/register.js?v=<?= filemtime(dirname(__DIR__) . '/scripts/register.js') ?>"></script>

<?php include dirname(__DIR__, 3) . '/templates/footer.php'; ?>
