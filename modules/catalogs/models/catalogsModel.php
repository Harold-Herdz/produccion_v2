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

        // Turnos (la columna `nombre_turno` es generada a partir
        // de `bloque_horario` + `jornada`, por eso NO se inserta directamente)
        'turnos' => [
            'etiqueta' => 'Turnos',
            'tabla'    => 'TURNOS',
            'id'       => 'id_turno',
            'nombre'   => 'nombre_turno',
            'campos'   => [
                'bloque_horario' => [
                    'etiqueta' => 'Bloque horario',
                    'tipo'     => 'select',
                    'opciones' => ['Día', 'Tarde', 'Noche'],
                ],
                'jornada' => [
                    'etiqueta' => 'Jornada',
                    'tipo'     => 'select',
                    'opciones' => ['8 Horas', '12 Horas'],
                ],
            ],
        ],

        // Turnos del área de extrusión
        'turnos_extrusion' => [
            'etiqueta' => 'Turnos de extrusión',
            'tabla'    => 'TURNOS_EXTRUSION',
            'id'       => 'id_turno_ext',
            'nombre'   => 'nombre_turno_ext',
            'campos'   => [
                'nombre_turno_ext' => [
                    'etiqueta' => 'Nombre del turno',
                    'tipo'     => 'text',
                ],
            ],
        ],

        // Operadores del área de extrusión
        'operadores_extrusion' => [
            'etiqueta' => 'Operadores de extrusión',
            'tabla'    => 'OPERADORES_EXTRUSION',
            'id'       => 'id_operador_ext',
            'nombre'   => 'nombre_operador_ext',
            'campos'   => [
                'nombre_operador_ext' => [
                    'etiqueta' => 'Nombre del operador',
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
   LISTAR  (GET)
   -----------------------------------------------------
   Devuelve todos los registros del catálogo, con un
   filtro opcional de búsqueda por nombre.
===================================================== */
function listarRegistros($conexion, $cfg, $busqueda = '')
{
    $tabla  = $cfg['tabla'];
    $nombre = $cfg['nombre'];

    $sql = "SELECT * FROM $tabla";

    // Aplicar filtro de búsqueda por nombre (si se envió texto)
    if ($busqueda !== '') {
        $busqueda = mysqli_real_escape_string($conexion, $busqueda);
        $sql .= " WHERE $nombre LIKE '%$busqueda%'";
    }

    // Ordenar alfabéticamente por el nombre visible
    $sql .= " ORDER BY $nombre ASC";

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
        $valor = trim($datos[$columna] ?? '');

        // Todos los campos del formulario son obligatorios
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
