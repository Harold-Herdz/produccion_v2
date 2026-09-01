<?php
/* =================================================
   MODELO REGISTER · PLANILLA DIGITAL DE SELLADO
   -------------------------------------------------
   Reemplaza la antigua planilla de Google Sheets.
   - Cada entrada real termina como una fila normal
     en PRODUCCION_SELLADO (una entrada = un registro).
   - El "sobre" del turno (estado, supervisor, PDF) se
     guarda en la tabla aislada SELLADO_PLANILLAS.
================================================= */

// Zona horaria del proyecto (Colombia, GMT-5) para fecha/código del turno
date_default_timezone_set('America/Bogota');

/* =================================================
   TABLA DEL SOBRE DEL TURNO
================================================= */
// Crear la tabla de planillas si aún no existe (auto-despliegue)
function asegurarTablaPlanillas($conexion){
    $conexion->query("
        CREATE TABLE IF NOT EXISTS sellado_planillas (
            id_planilla       INT(11) NOT NULL AUTO_INCREMENT,
            codigo            VARCHAR(20) NOT NULL,
            fecha_planilla    DATE NOT NULL,
            bloque            VARCHAR(10) NOT NULL,
            id_supervisor     INT(11) DEFAULT NULL,
            supervisor_nombre VARCHAR(50) DEFAULT NULL,
            estado            ENUM('abierta','finalizada') NOT NULL DEFAULT 'abierta',
            ruta_pdf          VARCHAR(255) DEFAULT NULL,
            total_registros   INT(11) NOT NULL DEFAULT 0,
            creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finalizado_en     DATETIME DEFAULT NULL,
            PRIMARY KEY (id_planilla),
            UNIQUE KEY codigo (codigo),
            KEY idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/* =================================================
   BLOQUES DE TURNO
================================================= */
// Bloques disponibles: etiqueta horaria y código de turno (T001..T003)
function bloquesTurno(){
    return [
        'Día'   => ['horario' => '6am - 2pm',  'codigo' => '001'],
        'Tarde' => ['horario' => '2pm - 10pm', 'codigo' => '002'],
        'Noche' => ['horario' => '10pm - 6am', 'codigo' => '003'],
    ];
}

// Construir el código del turno: S{yyyyMMdd}_T{001|002|003}
function construirCodigoPlanilla($fecha, $bloque){
    $bloques = bloquesTurno();
    $cod = $bloques[$bloque]['codigo'] ?? '000';
    return 'S' . date('Ymd', strtotime($fecha)) . '_T' . $cod;
}

/* =================================================
   CONSULTAS DEL SOBRE
================================================= */
// Obtener la planilla abierta actual (solo puede haber una a la vez)
function obtenerPlanillaAbierta($conexion){
    $res = $conexion->query("
        SELECT * FROM sellado_planillas
        WHERE estado = 'abierta'
        ORDER BY id_planilla DESC
        LIMIT 1
    ");
    return $res ? $res->fetch_assoc() : null;
}

// Obtener una planilla por su código
function obtenerPlanillaPorCodigo($conexion, $codigo){
    $stmt = $conexion->prepare("SELECT * FROM sellado_planillas WHERE codigo = ? LIMIT 1");
    $stmt->bind_param('s', $codigo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Crear una nueva planilla de turno; devuelve la fila creada o la existente
function crearPlanilla($conexion, $fecha, $bloque, $id_supervisor, $supervisor_nombre){
    $codigo = construirCodigoPlanilla($fecha, $bloque);
    try {
        $stmt = $conexion->prepare("
            INSERT INTO sellado_planillas (codigo, fecha_planilla, bloque, id_supervisor, supervisor_nombre)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssis', $codigo, $fecha, $bloque, $id_supervisor, $supervisor_nombre);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        // Clave única: la planilla ya existía (doble clic / carrera). Se recupera abajo.
    }
    return obtenerPlanillaPorCodigo($conexion, $codigo);
}

// Marcar la planilla como finalizada
function finalizarPlanillaSobre($conexion, $codigo, $total){
    $stmt = $conexion->prepare("
        UPDATE sellado_planillas
        SET estado = 'finalizada', total_registros = ?, finalizado_en = NOW()
        WHERE codigo = ? AND estado = 'abierta'
    ");
    $stmt->bind_param('is', $total, $codigo);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Guardar la ruta del PDF generado
function guardarRutaPdfPlanilla($conexion, $codigo, $ruta){
    $stmt = $conexion->prepare("UPDATE sellado_planillas SET ruta_pdf = ? WHERE codigo = ?");
    $stmt->bind_param('ss', $ruta, $codigo);
    $stmt->execute();
}

/* =================================================
   CATÁLOGOS DE LA PLANILLA
================================================= */
// Máquinas de Sellado (exactamente las 17 primeras)
function obtenerMaquinasSellado($conexion){
    return $conexion->query("
        SELECT id_maquina, nombre_maquina
        FROM maquinas
        WHERE id_maquina BETWEEN 1 AND 17
        ORDER BY id_maquina
    ");
}

// Operarios activos para el menú desplegable
function obtenerOperariosActivos($conexion){
    return $conexion->query("
        SELECT id_operario, nombre_operario
        FROM operarios
        WHERE estado = 1
        ORDER BY nombre_operario
    ");
}

// Referencias activas
function obtenerReferenciasActivas($conexion){
    return $conexion->query("
        SELECT id_referencia, nombre_referencia
        FROM referencias
        WHERE estado = 1
        ORDER BY nombre_referencia
    ");
}

// Colores activos
function obtenerColoresActivos($conexion){
    return $conexion->query("
        SELECT id_color, nombre_color
        FROM colores
        WHERE estado = 1
        ORDER BY nombre_color
    ");
}

/* =================================================
   RESOLUCIÓN DE TURNO Y OPERARIO
================================================= */
// Obtener el id_turno a partir del bloque + jornada; se crea si no existe
function obtenerIdTurno($conexion, $bloque, $jornada){
    $stmt = $conexion->prepare("
        SELECT id_turno FROM turnos
        WHERE bloque_horario = ? AND jornada = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $bloque, $jornada);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if($fila){
        return (int) $fila['id_turno'];
    }
    // Crear el turno si la combinación es nueva
    $stmt = $conexion->prepare("INSERT INTO turnos (bloque_horario, jornada) VALUES (?, ?)");
    $stmt->bind_param('ss', $bloque, $jornada);
    $stmt->execute();
    return $conexion->insert_id;
}

// Obtener el id de un operario por nombre exacto; se crea si no está en el catálogo
// Devuelve [id_operario, fue_creado]
function obtenerOperarioIdPorNombre($conexion, $nombre){
    $nombre = trim($nombre);
    $stmt = $conexion->prepare("SELECT id_operario FROM operarios WHERE nombre_operario = ? LIMIT 1");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if($fila){
        return [(int) $fila['id_operario'], false];
    }
    $stmt = $conexion->prepare("INSERT INTO operarios (nombre_operario) VALUES (?)");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    return [$conexion->insert_id, true];
}

/* =================================================
   GUARDADO PROGRESIVO DE LA PLANILLA
================================================= */
// Convertir a entero o NULL si viene vacío
function valorEnteroONulo($valor){
    $valor = trim((string) $valor);
    return ($valor === '') ? null : (int) $valor;
}
// Convertir a decimal o NULL si viene vacío
function valorDecimalONulo($valor){
    $valor = trim((string) $valor);
    if($valor === ''){
        return null;
    }
    return (float) str_replace(',', '.', $valor);
}
// ¿Todos los campos de la entrada están vacíos?
function entradaVacia($ent){
    $campos = ['id_referencia','id_color','x70','x90','x98','p1','p2','p3','p4','p5','obs'];
    foreach($campos as $c){
        if(isset($ent[$c]) && trim((string) $ent[$c]) !== ''){
            return false;
        }
    }
    return true;
}

// Guardar toda la planilla: cada entrada no vacía se vuelve una fila en PRODUCCION_SELLADO.
// Es idempotente: reejecutarla con los mismos datos no crea duplicados (UPSERT por id_sheet).
function guardarPlanilla($conexion, $planilla, $maquinas){
    $codigo  = $planilla['codigo'];
    $bloque  = $planilla['bloque'];
    $fecha   = $planilla['fecha_planilla'];
    $avisos  = [];
    $guardados = 0;
    $idSheetsVigentes = [];

    // Sentencia de inserción/actualización por entrada
    $sql = "INSERT INTO produccion_sellado
                (id_sheet, fecha_sellado, id_operario, id_maquina, id_referencia, id_color, id_turno,
                 paquetes_x70, paquetes_x90, paquetes_x98,
                 peso_hora1, peso_hora2, peso_hora3, peso_hora4, peso_hora5, obs_sellado)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                id_operario   = VALUES(id_operario),
                id_maquina    = VALUES(id_maquina),
                id_referencia = VALUES(id_referencia),
                id_color      = VALUES(id_color),
                id_turno      = VALUES(id_turno),
                paquetes_x70  = VALUES(paquetes_x70),
                paquetes_x90  = VALUES(paquetes_x90),
                paquetes_x98  = VALUES(paquetes_x98),
                peso_hora1    = VALUES(peso_hora1),
                peso_hora2    = VALUES(peso_hora2),
                peso_hora3    = VALUES(peso_hora3),
                peso_hora4    = VALUES(peso_hora4),
                peso_hora5    = VALUES(peso_hora5),
                obs_sellado   = VALUES(obs_sellado)";
    $stmt = $conexion->prepare($sql);

    foreach($maquinas as $m){
        $numMaq = (int) ($m['maquina'] ?? 0);
        if($numMaq < 1 || $numMaq > 17){
            continue;
        }
        $maqEtiqueta = str_pad($numMaq, 2, '0', STR_PAD_LEFT);

        // Resolver operario: el texto libre de "Otro operario" tiene prioridad
        $idOperario = null;
        $otro = trim($m['otro_operario'] ?? '');
        if($otro !== ''){
            [$idOperario, $fueCreado] = obtenerOperarioIdPorNombre($conexion, $otro);
            if($fueCreado){
                $avisos[] = "Se agregó «{$otro}» como nuevo operario (Máquina {$maqEtiqueta}). Falta verificarlo en Catálogos.";
            }
        } elseif(!empty($m['id_operario'])){
            $idOperario = (int) $m['id_operario'];
        }

        // Resolver turno con el bloque del encabezado + la jornada de la máquina
        $jornada = trim($m['jornada'] ?? '');
        $idTurno = ($jornada !== '') ? obtenerIdTurno($conexion, $bloque, $jornada) : null;

        // Recorrer las entradas de la máquina (máximo 5)
        $entradas = $m['entradas'] ?? [];
        foreach(array_values($entradas) as $e => $ent){
            if($e > 4){
                break;
            }
            if(entradaVacia($ent)){
                continue;
            }

            $idSheet = $codigo . '-' . $maqEtiqueta . '-' . ($e + 1);
            $idSheetsVigentes[] = $idSheet;

            $idReferencia = valorEnteroONulo($ent['id_referencia'] ?? '');
            $idColor      = valorEnteroONulo($ent['id_color'] ?? '');
            $x70 = valorEnteroONulo($ent['x70'] ?? '');
            $x90 = valorEnteroONulo($ent['x90'] ?? '');
            $x98 = valorEnteroONulo($ent['x98'] ?? '');
            $p1  = valorDecimalONulo($ent['p1'] ?? '');
            $p2  = valorDecimalONulo($ent['p2'] ?? '');
            $p3  = valorDecimalONulo($ent['p3'] ?? '');
            $p4  = valorDecimalONulo($ent['p4'] ?? '');
            $p5  = valorDecimalONulo($ent['p5'] ?? '');
            $obs = trim($ent['obs'] ?? '');

            $stmt->bind_param(
                'ssiiiiiiiiddddds',
                $idSheet, $fecha, $idOperario, $numMaq, $idReferencia, $idColor, $idTurno,
                $x70, $x90, $x98, $p1, $p2, $p3, $p4, $p5, $obs
            );
            $stmt->execute();
            $guardados++;
        }
    }

    // Borrar las entradas que quedaron vacías o se eliminaron en la interfaz
    $like = $conexion->real_escape_string($codigo) . '-%';
    if(!empty($idSheetsVigentes)){
        $lista = implode(',', array_map(function($s) use ($conexion){
            return "'" . $conexion->real_escape_string($s) . "'";
        }, $idSheetsVigentes));
        $conexion->query("DELETE FROM produccion_sellado WHERE id_sheet LIKE '{$like}' AND id_sheet NOT IN ({$lista})");
    } else {
        $conexion->query("DELETE FROM produccion_sellado WHERE id_sheet LIKE '{$like}'");
    }

    return [
        'guardados' => $guardados,
        'avisos'    => array_values(array_unique($avisos)),
    ];
}

/* =================================================
   RECUPERACIÓN DE LA PLANILLA
================================================= */
// Contar las filas ya guardadas de una planilla
function contarRegistrosPlanilla($conexion, $codigo){
    $like = $conexion->real_escape_string($codigo) . '-%';
    $res = $conexion->query("SELECT COUNT(*) total FROM produccion_sellado WHERE id_sheet LIKE '{$like}'");
    $fila = $res->fetch_assoc();
    return (int) $fila['total'];
}

// Reconstruir la planilla guardada como estructura por máquina para la vista / JS
function obtenerPlanillaEstructurada($conexion, $codigo){
    $like = $conexion->real_escape_string($codigo) . '-%';
    $res = $conexion->query("
        SELECT s.id_sheet, s.id_maquina, s.id_operario, s.id_referencia, s.id_color,
               s.paquetes_x70, s.paquetes_x90, s.paquetes_x98,
               s.peso_hora1, s.peso_hora2, s.peso_hora3, s.peso_hora4, s.peso_hora5,
               s.obs_sellado, t.jornada
        FROM produccion_sellado s
        LEFT JOIN turnos t ON s.id_turno = t.id_turno
        WHERE s.id_sheet LIKE '{$like}'
        ORDER BY s.id_maquina, s.id_sheet
    ");

    $maquinas = [];
    while($fila = $res->fetch_assoc()){
        $num = (int) $fila['id_maquina'];
        if(!isset($maquinas[$num])){
            $maquinas[$num] = [
                'id_operario' => $fila['id_operario'],
                'jornada'     => $fila['jornada'],
                'entradas'    => [],
            ];
        }
        $maquinas[$num]['entradas'][] = [
            'id_referencia' => $fila['id_referencia'],
            'id_color'      => $fila['id_color'],
            'x70'           => $fila['paquetes_x70'],
            'x90'           => $fila['paquetes_x90'],
            'x98'           => $fila['paquetes_x98'],
            'p1'            => $fila['peso_hora1'],
            'p2'            => $fila['peso_hora2'],
            'p3'            => $fila['peso_hora3'],
            'p4'            => $fila['peso_hora4'],
            'p5'            => $fila['peso_hora5'],
            'obs'           => $fila['obs_sellado'],
        ];
    }
    return $maquinas;
}

// Datos completos de las entradas (con nombres) para el PDF
function obtenerEntradasPlanillaPdf($conexion, $codigo){
    $like = $conexion->real_escape_string($codigo) . '-%';
    return $conexion->query("
        SELECT s.id_maquina, m.nombre_maquina,
               o.nombre_operario, t.jornada,
               r.nombre_referencia, c.nombre_color,
               s.paquetes_x70, s.paquetes_x90, s.paquetes_x98, s.paquetes_total,
               s.peso_hora1, s.peso_hora2, s.peso_hora3, s.peso_hora4, s.peso_hora5,
               s.obs_sellado
        FROM produccion_sellado s
        LEFT JOIN maquinas m    ON s.id_maquina    = m.id_maquina
        LEFT JOIN operarios o   ON s.id_operario   = o.id_operario
        LEFT JOIN referencias r ON s.id_referencia = r.id_referencia
        LEFT JOIN colores c     ON s.id_color      = c.id_color
        LEFT JOIN turnos t      ON s.id_turno      = t.id_turno
        WHERE s.id_sheet LIKE '{$like}'
        ORDER BY s.id_maquina, s.id_sheet
    ");
}
