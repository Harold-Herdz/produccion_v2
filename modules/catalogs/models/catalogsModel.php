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
define('CATALOGOS_SEED_DIR', dirname(__DIR__) . '/seed');

// Ruta del archivo semilla del catálogo
function rutaSeedCatalogo($cfg)
{
    return CATALOGOS_SEED_DIR . '/' . strtolower($cfg['tabla']) . '.sql';
}

// Vuelca todos los registros al archivo semilla
function exportarCatalogo($conexion, $cfg)
{
    $tabla     = $cfg['tabla'];
    $colNombre = $cfg['nombre'];

    $res = mysqli_query($conexion, "SELECT {$colNombre} AS nombre, estado FROM {$tabla} ORDER BY {$colNombre} ASC");
    $lineas = ["-- {$cfg['etiqueta']} ({$tabla}) -- generado por Catalogos > Exportar el " . date('Y-m-d H:i')];
    $total = 0;
    if ($res) {
        while ($fila = mysqli_fetch_assoc($res)) {
            $valor  = str_replace("'", "''", $fila['nombre']);
            $estado = (int) $fila['estado'];
            $lineas[] = "INSERT INTO {$tabla} ({$colNombre}, estado) VALUES ('{$valor}', {$estado});";
            $total++;
        }
    }

    if (!is_dir(CATALOGOS_SEED_DIR)) {
        mkdir(CATALOGOS_SEED_DIR, 0777, true);
    }
    file_put_contents(rutaSeedCatalogo($cfg), implode("\n", $lineas) . "\n");

    return $total;
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
        if (!preg_match("/VALUES\s*\('((?:[^']|'')*)'\s*,\s*(\d)\)/i", $linea, $m)) {
            continue; // línea no reconocida
        }
        $valor  = trim(str_replace("''", "'", $m[1]));
        $estado = (int) $m[2];
        if ($valor === '') {
            continue;
        }
        $valorEsc = mysqli_real_escape_string($conexion, $valor);

        $res = mysqli_query($conexion, "SELECT {$colId} FROM {$tabla} WHERE {$colNombre} = '{$valorEsc}' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $existentes++;
            continue;
        }

        mysqli_query($conexion, "INSERT INTO {$tabla} ({$colNombre}, estado) VALUES ('{$valorEsc}', {$estado})");
        $agregados++;
    }

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
            $pass    = str_replace("'", "''", $fila['contrasena']);
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

        $passEsc = mysqli_real_escape_string($conexion, $pass);
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

/* =====================================================
   MATRIZ MÁQUINA ↔ ÁREA / REFERENCIA
===================================================== */
// Máquinas activas en orden por número real (no por id)
function maquinasOrdenadas($conexion)
{
    return mysqli_fetch_all(mysqli_query($conexion, "
        SELECT id_maquina, nombre_maquina, usa_referencias_esp
        FROM MAQUINAS
        WHERE estado = 1
        ORDER BY CAST(REGEXP_SUBSTR(nombre_maquina, '[0-9]+') AS UNSIGNED)
    "), MYSQLI_ASSOC);
}

// Máquinas x Áreas: qué máquinas están habilitadas en cada módulo
function obtenerMatrizMaquinaAreas($conexion)
{
    $areas = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_area, nombre_area FROM AREAS ORDER BY id_area"), MYSQLI_ASSOC);
    $relaciones = [];
    $res = mysqli_query($conexion, "SELECT id_maquina, id_area FROM MAQUINA_AREAS");
    while ($r = mysqli_fetch_assoc($res)) {
        $relaciones[$r['id_maquina']][$r['id_area']] = true;
    }
    return ['maquinas' => maquinasOrdenadas($conexion), 'areas' => $areas, 'relaciones' => $relaciones];
}

// Alterna si una máquina pertenece a un área
function alternarMaquinaArea($conexion, $idMaquina, $idArea)
{
    $idMaquina = (int) $idMaquina;
    $idArea    = (int) $idArea;
    $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_AREAS WHERE id_maquina = $idMaquina AND id_area = $idArea");
    if ($existe && mysqli_num_rows($existe) > 0) {
        return mysqli_query($conexion, "DELETE FROM MAQUINA_AREAS WHERE id_maquina = $idMaquina AND id_area = $idArea");
    }
    return mysqli_query($conexion, "INSERT INTO MAQUINA_AREAS (id_maquina, id_area) VALUES ($idMaquina, $idArea)");
}

// Máquinas x Referencias: qué referencias normales produce cada máquina
function obtenerMatrizMaquinaReferencias($conexion)
{
    $referencias = mysqli_fetch_all(mysqli_query($conexion, "SELECT id_referencia, nombre_referencia FROM REFERENCIAS WHERE estado = 1 ORDER BY id_referencia"), MYSQLI_ASSOC);
    $relaciones = [];
    $res = mysqli_query($conexion, "SELECT id_maquina, id_referencia FROM MAQUINA_REFERENCIAS");
    while ($r = mysqli_fetch_assoc($res)) {
        $relaciones[$r['id_maquina']][$r['id_referencia']] = true;
    }
    return ['maquinas' => maquinasOrdenadas($conexion), 'referencias' => $referencias, 'relaciones' => $relaciones];
}

// Alterna si una máquina produce una referencia
function alternarMaquinaReferencia($conexion, $idMaquina, $idReferencia)
{
    $idMaquina    = (int) $idMaquina;
    $idReferencia = (int) $idReferencia;
    $existe = mysqli_query($conexion, "SELECT 1 FROM MAQUINA_REFERENCIAS WHERE id_maquina = $idMaquina AND id_referencia = $idReferencia");
    if ($existe && mysqli_num_rows($existe) > 0) {
        return mysqli_query($conexion, "DELETE FROM MAQUINA_REFERENCIAS WHERE id_maquina = $idMaquina AND id_referencia = $idReferencia");
    }
    return mysqli_query($conexion, "INSERT INTO MAQUINA_REFERENCIAS (id_maquina, id_referencia) VALUES ($idMaquina, $idReferencia)");
}

// Alterna si una máquina usa el catálogo completo de Referencias Especiales ("Bolsa Basura")
function alternarUsaReferenciasEsp($conexion, $idMaquina)
{
    $idMaquina = (int) $idMaquina;
    return mysqli_query($conexion, "UPDATE MAQUINAS SET usa_referencias_esp = IF(usa_referencias_esp = 1, 0, 1) WHERE id_maquina = $idMaquina");
}
