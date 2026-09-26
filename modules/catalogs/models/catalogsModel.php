<?php
// Modelo de catálogos

// Configuración de catálogos
function catalogosDisponibles()
{
    return [

        // Operarios de producción
        'operarios' => [
            'etiqueta' => 'Operarios',
            'tabla'    => 'OPERARIOS',
            'id'       => 'id_operario',
            'nombre'   => 'nombre_operario',
            'campos'   => [
                'nombre_operario' => [
                    'etiqueta' => 'Nombre del operario',
                    'tipo'     => 'text',
                ],
                'es_supervisor' => [
                    'etiqueta' => 'Es supervisor',
                    'tipo'     => 'checkbox',
                ],
            ],
        ],

        // Máquinas
        'maquinas' => [
            'etiqueta' => 'Máquinas',
            'tabla'    => 'MAQUINAS',
            'id'       => 'id_maquina',
            'nombre'   => 'nombre_maquina',
            'campos'   => [
                'nombre_maquina' => [
                    'etiqueta' => 'Nombre de la máquina',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Referencias de producto
        'referencias' => [
            'etiqueta' => 'Referencias',
            'tabla'    => 'REFERENCIAS',
            'id'       => 'id_referencia',
            'nombre'   => 'nombre_referencia',
            'campos'   => [
                'nombre_referencia' => [
                    'etiqueta' => 'Nombre de la referencia',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Referencias especiales
        'referencias_esp' => [
            'etiqueta' => 'Referencias Especiales',
            'tabla'    => 'REFERENCIAS_ESP',
            'id'       => 'id_referencia_esp',
            'nombre'   => 'nombre_referencia_esp',
            'campos'   => [
                'nombre_referencia_esp' => [
                    'etiqueta' => 'Nombre de la referencia especial',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Colores
        'colores' => [
            'etiqueta' => 'Colores',
            'tabla'    => 'COLORES',
            'id'       => 'id_color',
            'nombre'   => 'nombre_color',
            'campos'   => [
                'nombre_color' => [
                    'etiqueta' => 'Nombre del color',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Turnos: catálogo cerrado
        'turnos' => [
            'etiqueta' => 'Turnos',
            'tabla'    => 'TURNOS',
            'id'       => 'id_turno',
            'nombre'   => 'nombre_turno',
            'campos'   => [
                'nombre_turno' => [
                    'etiqueta' => 'Nombre del turno',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Jornadas
        'jornadas' => [
            'etiqueta' => 'Jornadas',
            'tabla'    => 'JORNADAS',
            'id'       => 'id_jornada',
            'nombre'   => 'nombre_jornada',
            'campos'   => [
                'nombre_jornada' => [
                    'etiqueta' => 'Nombre de la jornada',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Operadores (área de extrusión)
        'operadores' => [
            'etiqueta' => 'Operadores',
            'tabla'    => 'OPERADORES',
            'id'       => 'id_operador',
            'nombre'   => 'nombre_operador',
            'campos'   => [
                'nombre_operador' => [
                    'etiqueta' => 'Nombre del operador',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Lámina P (área de extrusión)
        'lamina_p' => [
            'etiqueta' => 'Lámina P',
            'tabla'    => 'LAMINA_P',
            'id'       => 'id_lamina_p',
            'nombre'   => 'nombre_lamina_p',
            'campos'   => [
                'nombre_lamina_p' => [
                    'etiqueta' => 'Nombre de la lámina',
                    'tipo'     => 'text',
                ],
            ],
        ],

    ];
}

/**
 * Configuración por clave
 */
function obtenerConfigCatalogo($clave)
{
    $catalogos = catalogosDisponibles();
    return $catalogos[$clave] ?? null;
}

/* =====================================================
   EXPORTAR / IMPORTAR (archivos semilla)
===================================================== */
require_once dirname(__DIR__, 2) . '/shared/systemState.php';
require_once dirname(__DIR__, 3) . '/auth/shared/passwords.php';
define('CATALOGOS_SEED_DIR', dirname(__DIR__) . '/seed');

// Ruta del archivo semilla
function rutaSeedCatalogo($cfg)
{
    return CATALOGOS_SEED_DIR . '/' . strtolower($cfg['tabla']) . '.sql';
}

// Líneas INSERT del catálogo
function lineasExportCatalogo($conexion, $cfg)
{
    $tabla     = $cfg['tabla'];
    $colNombre = $cfg['nombre'];

    // Operarios: incluye supervisor
    $conSupervisor = ($tabla === 'OPERARIOS');
    $colExtra = $conSupervisor ? ', es_supervisor' : '';

    $res = mysqli_query($conexion, "SELECT {$colNombre} AS nombre, estado{$colExtra} FROM {$tabla} ORDER BY {$colNombre} ASC");
    $lineas = [];
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $valor  = str_replace("'", "''", $fila['nombre']);
            $estado = (int) $fila['estado'];
            if ($conSupervisor) {
                $sup = (int) $fila['es_supervisor'];
                $lineas[] = "INSERT INTO {$tabla} ({$colNombre}, estado, es_supervisor) VALUES ('{$valor}', {$estado}, {$sup});";
            } else {
                $lineas[] = "INSERT INTO {$tabla} ({$colNombre}, estado) VALUES ('{$valor}', {$estado});";
            }
        }
    }
    return $lineas;
}

// Volcar al archivo semilla
function exportarCatalogo($conexion, $cfg)
{
    $filas  = lineasExportCatalogo($conexion, $cfg);
    $lineas = array_merge(["-- {$cfg['etiqueta']} ({$cfg['tabla']}) -- generado por Catalogos > Exportar el " . date('Y-m-d H:i')], $filas);

    if (!is_dir(CATALOGOS_SEED_DIR)) {
        mkdir(CATALOGOS_SEED_DIR, 0777, true);
    }
    file_put_contents(rutaSeedCatalogo($cfg), implode("\n", $lineas) . "\n");
    estadoSistemaGuardar('catalogos', strtolower($cfg['tabla']), ['exportado' => date('Y-m-d H:i:s')]);

    return count($filas);
}

// ¿Semilla al día?
function catalogoAlDia($conexion, $cfg)
{
    $ruta = rutaSeedCatalogo($cfg);
    if (!is_file($ruta)) {
        return false;
    }
    $guardadas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    array_shift($guardadas); // cabecera con la fecha
    return $guardadas === lineasExportCatalogo($conexion, $cfg);
}

// Importar lo que falte
function importarCatalogo($conexion, $cfg)
{
    $ruta = rutaSeedCatalogo($cfg);
    if (!is_file($ruta)) {
        return ['agregados' => 0, 'existentes' => 0, 'error' => 'Todavía no se ha exportado este catálogo (no existe el archivo semilla).'];
    }

    $tabla     = $cfg['tabla'];
    $colId     = $cfg['id'];
    $colNombre = $cfg['nombre'];

    $agregados  = 0;
    $existentes = 0;

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        // Tercer valor: es_supervisor
        if (!preg_match("/VALUES\s*\('((?:[^']|'')*)'\s*,\s*(\d)(?:\s*,\s*(\d))?\)/i", $linea, $m)) {
            continue; // línea no reconocida
        }
        $valor  = trim(str_replace("''", "'", $m[1]));
        $estado = (int) $m[2];
        $supervisor = ($tabla === 'OPERARIOS') ? (int) ($m[3] ?? 0) : 0;
        if ($valor === '') {
            continue;
        }
        $valorEsc = mysqli_real_escape_string($conexion, $valor);

        $res = mysqli_query($conexion, "SELECT {$colId} FROM {$tabla} WHERE {$colNombre} = '{$valorEsc}' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            // Conservar marca de supervisor
            if ($supervisor === 1) {
                mysqli_query($conexion, "UPDATE {$tabla} SET es_supervisor = 1 WHERE {$colNombre} = '{$valorEsc}'");
            }
            $existentes++;
            continue;
        }

        if ($tabla === 'OPERARIOS') {
            mysqli_query($conexion, "INSERT INTO {$tabla} ({$colNombre}, estado, es_supervisor) VALUES ('{$valorEsc}', {$estado}, {$supervisor})");
        } else {
            mysqli_query($conexion, "INSERT INTO {$tabla} ({$colNombre}, estado) VALUES ('{$valorEsc}', {$estado})");
        }
        $agregados++;
    }

    estadoSistemaGuardar('catalogos', strtolower($tabla), ['importado' => date('Y-m-d H:i:s'), 'agregados' => $agregados]);

    return ['agregados' => $agregados, 'existentes' => $existentes, 'error' => null];
}

// Ruta semilla de usuarios
function rutaSeedUsuarios()
{
    return CATALOGOS_SEED_DIR . '/usuarios.sql';
}

// Volcar usuarios a la semilla
function exportarUsuarios($conexion)
{
    $res = mysqli_query($conexion, "SELECT usuario, contrasena, rol, estado FROM USUARIOS ORDER BY usuario ASC");
    $lineas = ["-- Usuarios (USUARIOS) -- generado por Catalogos > Exportar el " . date('Y-m-d H:i')];
    $total = 0;
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $usuario = str_replace("'", "''", $fila['usuario']);
            // Nunca texto plano
            $guardada = contrasenaEsHash($fila['contrasena']) ? $fila['contrasena'] : cifrarContrasena($fila['contrasena']);
            $pass    = str_replace("'", "''", $guardada);
            $rol     = str_replace("'", "''", $fila['rol']);
            $estado  = (int) $fila['estado'];
            $lineas[] = "INSERT INTO USUARIOS (usuario, contrasena, rol, estado) VALUES ('{$usuario}', '{$pass}', '{$rol}', {$estado});";
            $total++;
        }
    }

    if (!is_dir(CATALOGOS_SEED_DIR)) {
        mkdir(CATALOGOS_SEED_DIR, 0777, true);
    }
    file_put_contents(rutaSeedUsuarios(), implode("\n", $lineas) . "\n");

    return $total;
}

// Importar usuarios faltantes
function importarUsuarios($conexion)
{
    $ruta = rutaSeedUsuarios();
    if (!is_file($ruta)) {
        return ['agregados' => 0, 'existentes' => 0, 'error' => 'Todavía no se han exportado los usuarios (no existe el archivo semilla).'];
    }

    $agregados  = 0;
    $existentes = 0;

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (!preg_match("/VALUES\s*\('((?:[^']|'')*)'\s*,\s*'((?:[^']|'')*)'\s*,\s*'((?:[^']|'')*)'\s*,\s*(\d)\)/i", $linea, $m)) {
            continue; // línea no reconocida
        }
        $usuario = trim(str_replace("''", "'", $m[1]));
        $pass    = str_replace("''", "'", $m[2]);
        $rol     = str_replace("''", "'", $m[3]);
        $estado  = (int) $m[4];
        if ($usuario === '') {
            continue;
        }
        $usuarioEsc = mysqli_real_escape_string($conexion, $usuario);

        $res = mysqli_query($conexion, "SELECT id_usuario FROM USUARIOS WHERE usuario = '{$usuarioEsc}' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $existentes++;
            continue;
        }

        // Cifrar si viene en texto
        $passEsc = mysqli_real_escape_string($conexion, contrasenaEsHash($pass) ? $pass : cifrarContrasena($pass));
        $rolEsc  = mysqli_real_escape_string($conexion, $rol);
        mysqli_query($conexion, "INSERT INTO USUARIOS (usuario, contrasena, rol, estado) VALUES ('{$usuarioEsc}', '{$passEsc}', '{$rolEsc}', {$estado})");
        $agregados++;
    }

    return ['agregados' => $agregados, 'existentes' => $existentes, 'error' => null];
}

/* =====================================================
   LISTAR  (GET)
   -----------------------------------------------------
   Devuelve todos los registros del catálogo, con un
   filtro opcional de búsqueda por nombre.
===================================================== */
function listarRegistros($conexion, $cfg, $busqueda = '')
{
    $tabla  = $cfg['tabla'];
    $nombre = $cfg['nombre'];
    $id     = $cfg['id'];

    $sql = "SELECT * FROM $tabla";

    // Filtro por nombre
    if ($busqueda !== '') {
        $busqueda = mysqli_real_escape_string($conexion, $busqueda);
        $sql .= " WHERE $nombre LIKE '%$busqueda%'";
    }

    // Orden por ID
    $sql .= " ORDER BY $id ASC";

    return mysqli_query($conexion, $sql);
}

/* =====================================================
   CREAR  (POST)
   -----------------------------------------------------
   Inserta un nuevo registro tomando únicamente las
   columnas declaradas en la configuración del catálogo.
   Devuelve true si la inserción fue exitosa.
===================================================== */
function crearRegistro($conexion, $cfg, $datos)
{
    $tabla    = $cfg['tabla'];
    $columnas = [];
    $valores  = [];

    // Campos editables
    foreach ($cfg['campos'] as $columna => $meta) {
        // Checkbox: 1 o 0
        if (($meta['tipo'] ?? '') === 'checkbox') {
            $columnas[] = $columna;
            $valores[]  = isset($datos[$columna]) ? '1' : '0';
            continue;
        }

        $valor = trim($datos[$columna] ?? '');

        // Demás campos obligatorios
        if ($valor === '') {
            return false;
        }

        $columnas[] = $columna;
        $valores[]  = "'" . mysqli_real_escape_string($conexion, $valor) . "'";
    }

    // Construir e insertar la sentencia
    $sql = "INSERT INTO $tabla (" . implode(', ', $columnas) . ")
            VALUES (" . implode(', ', $valores) . ")";

    return mysqli_query($conexion, $sql);
}

/* =====================================================
   CAMBIAR ESTADO  (POST)
   -----------------------------------------------------
   Alterna el estado del registro:
     activo (1)  ->  inhabilitado (0)
     inhabilitado (0)  ->  activo (1)
===================================================== */
function cambiarEstadoRegistro($conexion, $cfg, $idRegistro)
{
    $tabla      = $cfg['tabla'];
    $id         = $cfg['id'];
    $idRegistro = (int) $idRegistro;

    $sql = "UPDATE $tabla
            SET estado = IF(estado = 1, 0, 1)
            WHERE $id = $idRegistro";

    return mysqli_query($conexion, $sql);
}

// Alternar supervisor
function alternarSupervisorOperario($conexion, $idOperario)
{
    $idOperario = (int) $idOperario;
    $sql = "UPDATE OPERARIOS
            SET es_supervisor = IF(es_supervisor = 1, 0, 1)
            WHERE id_operario = $idOperario";

    return mysqli_query($conexion, $sql);
}
