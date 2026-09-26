<?php
/**
 * =====================================================
 *  MODELO DE CATÁLOGOS
 * =====================================================
 *  Define las tablas maestras que se pueden administrar
 *  desde el módulo y concentra todas las consultas SQL:
 *    - listar registros existentes            (GET)
 *    - crear nuevos registros                 (POST)
 *    - activar / inhabilitar el estado        (POST)
 *
 *  Todas las tablas cuentan con el campo
 *  `estado TINYINT(1) DEFAULT 1` (1 = activo, 0 = inhabilitado).
 */

/* =====================================================
   CONFIGURACIÓN DE CATÁLOGOS
   -----------------------------------------------------
   Cada clave describe un catálogo:
     - etiqueta : nombre visible en la interfaz
     - tabla    : nombre real de la tabla en la base de datos
     - id       : columna de llave primaria
     - nombre   : columna que se muestra como "Nombre"
     - campos   : columnas editables en el formulario de creación
                  (columna => [etiqueta, tipo, opciones])
===================================================== */
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

        // Referencias especiales (usadas por Plana y como segunda referencia de Sellado/Extrusión)
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

        // Turnos: catálogo cerrado de 4 valores fijos (Día, Tarde, Noche, 18 Horas).
        // Se llena manualmente; el código nunca crea turnos nuevos.
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

        // Jornadas (8 Horas, 12 Horas, ...)
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
 * Obtener la configuración de un catálogo por su clave.
 * Devuelve null si la clave recibida no corresponde a ningún catálogo válido.
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

// Ruta del archivo semilla del catálogo
function rutaSeedCatalogo($cfg)
{
    return CATALOGOS_SEED_DIR . '/' . strtolower($cfg['tabla']) . '.sql';
}

// Líneas INSERT que representan el contenido actual del catálogo (sin el comentario de cabecera)
function lineasExportCatalogo($conexion, $cfg)
{
    $tabla     = $cfg['tabla'];
    $colNombre = $cfg['nombre'];

    // Operarios: también se guarda si es supervisor
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

// Vuelca todos los registros al archivo semilla
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

// ¿El archivo semilla ya refleja el contenido actual? (false = hay cambios sin exportar)
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

// Agrega desde el archivo semilla lo que falte
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
        // Tercer valor opcional (es_supervisor, solo en Operarios)
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
            // Ya existe: si el archivo lo marca como supervisor, se conserva esa marca (nunca se quita)
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

// Ruta del archivo semilla de usuarios
function rutaSeedUsuarios()
{
    return CATALOGOS_SEED_DIR . '/usuarios.sql';
}

// Vuelca todos los usuarios (con contraseña y rol) al archivo semilla
function exportarUsuarios($conexion)
{
    $res = mysqli_query($conexion, "SELECT usuario, contrasena, rol, estado FROM USUARIOS ORDER BY usuario ASC");
    $lineas = ["-- Usuarios (USUARIOS) -- generado por Catalogos > Exportar el " . date('Y-m-d H:i')];
    $total = 0;
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $usuario = str_replace("'", "''", $fila['usuario']);
            // Nunca se exporta texto plano: si aún es una contraseña antigua, se cifra al exportar
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

// Agrega usuarios desde el archivo semilla lo que falte (por nombre de usuario exacto)
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

        // Si el archivo trae una contraseña en texto plano (archivo antiguo), se cifra al importar
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

    // Aplicar filtro de búsqueda por nombre (si se envió texto)
    if ($busqueda !== '') {
        $busqueda = mysqli_real_escape_string($conexion, $busqueda);
        $sql .= " WHERE $nombre LIKE '%$busqueda%'";
    }

    // Ordenar por ID (orden de creación), de menor a mayor
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

    // Recorrer los campos editables definidos para este catálogo
    foreach ($cfg['campos'] as $columna => $meta) {
        // Checkbox: nunca obligatorio, 1 si vino marcado, 0 si no
        if (($meta['tipo'] ?? '') === 'checkbox') {
            $columnas[] = $columna;
            $valores[]  = isset($datos[$columna]) ? '1' : '0';
            continue;
        }

        $valor = trim($datos[$columna] ?? '');

        // Todos los demás campos del formulario son obligatorios
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

// Alterna si un operario es supervisor (para Sellado: dropdown de "Supervisor")
function alternarSupervisorOperario($conexion, $idOperario)
{
    $idOperario = (int) $idOperario;
    $sql = "UPDATE OPERARIOS
            SET es_supervisor = IF(es_supervisor = 1, 0, 1)
            WHERE id_operario = $idOperario";

    return mysqli_query($conexion, $sql);
}
