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

        // Referencias especiales (usadas por Plana y como segunda referencia de Sellado/Extrusión)
        'referencias_esp' => [
            'etiqueta' => 'Referencias especiales',
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
