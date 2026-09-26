<?php
// Genera el PDF de la planilla (bytes en memoria; se envía a Drive vía Apps Script)
require_once dirname(__DIR__, 2) . '/shared/fpdf/fpdf.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

// Pasar texto UTF-8 a la codificación de las fuentes base de FPDF
function pdfTxt($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// Recorta un texto para que quepa en un ancho (mm) dado, con "…" si se corta
function pdfTxtRecortado($pdf, $texto, $anchoMax){
    $texto = pdfTxt($texto);
    if($texto === '' || $pdf->GetStringWidth($texto) <= $anchoMax){
        return $texto;
    }
    while($texto !== '' && $pdf->GetStringWidth($texto . '...') > $anchoMax){
        $texto = substr($texto, 0, -1);
    }
    return $texto . '...';
}

// Colores corporativos (ver assets/css/root.css)
function pdfColoresSellado(){
    return [
        'azul_oscuro'    => [22, 74, 125],
        'azul'           => [47, 126, 194],
        'azul_claro'     => [227, 238, 249],
        'azul_muy_claro' => [240, 246, 252],
        'borde'          => [200, 208, 201],
        'texto'          => [40, 40, 40],
    ];
}

// Generar el PDF del turno y devolverlo como cadena binaria
// $maquinasFijas/$porMaquinaFijo: para regenerar PDFs históricos desde un CSV
// (fuera de línea, sin base de datos). Se ignoran en el flujo normal.
function generarPdfPlanilla($conexion, $planilla, $nota = '', $maquinasFijas = null, $porMaquinaFijo = null){
    $codigo = $planilla['codigo'];
    $fecha  = $planilla['fecha_planilla'];
    $col    = pdfColoresSellado();

    // Máquinas de Sellado en orden (mismas que ve el formulario)
    $maquinas = $maquinasFijas ?? obtenerMaquinasConReferencias($conexion, 'sellado')['maquinas'];

    if($porMaquinaFijo !== null){
        $porMaquina = $porMaquinaFijo;
    } else {
    // Entradas agrupadas por número de máquina
    $res = obtenerEntradasPlanillaPdf($conexion, $codigo);
    $porMaquina = [];
    if($res){
        while($f = $res->fetch_assoc()){
            $num = (int) $f['numero_maquina'];
            if(!isset($porMaquina[$num])){
                $porMaquina[$num] = [
                    'operario'            => $f['nombre_operario'] ?? '',
                    'operario_verificado' => isset($f['operario_verificado']) ? (bool) $f['operario_verificado'] : true,
                    'jornada'             => $f['jornada'] ?? '',
                    'entradas'            => [],
                ];
            }
            $porMaquina[$num]['entradas'][] = $f;
        }
    }
    }

    // Divide las máquinas en dos páginas: 1-8 en la primera, el resto en la segunda
    $primeraPagina = 8;
    $paginas = [array_slice($maquinas, 0, $primeraPagina), array_slice($maquinas, $primeraPagina)];

    $pdf = new FPDF('L', 'mm', 'Letter');
    // Márgenes chicos (lados 5 mm, arriba 7 mm): el espacio extra va a las observaciones
    $margenLado = 5;
    $pdf->SetMargins($margenLado, 7, $margenLado);
    $pdf->SetAutoPageBreak(false);
    $lineaNormal = 0.2; // ancho de línea por defecto (bordes finos de celda)
    $pdf->SetDrawColor(...$col['borde']);
    $pdf->SetLineWidth($lineaNormal);

    // Columnas: etiqueta => ancho (mm); OBSERVACIONES se lleva lo que sobre
    $cols = [
        'MÁQUINA'    => 17,
        'OPERARIO'   => 32,
        'REFERENCIA' => 24,
        'COLOR'      => 20,
        'X70'        => 11,
        'X90'        => 11,
        'X98'        => 11,
        'P1' => 12, 'P2' => 12, 'P3' => 12, 'P4' => 12, 'P5' => 12,
    ];
    $anchoTotal      = $pdf->GetPageWidth() - 2 * $margenLado;
    $cols['OBSERV']  = $anchoTotal - array_sum($cols);
    $anchoPeso       = $cols['P1'] + $cols['P2'] + $cols['P3'] + $cols['P4'] + $cols['P5'];

    // Encabezado de la primera página: título + datos del turno
    $dibujarPortada = function() use ($pdf, $planilla, $codigo, $fecha, $anchoTotal, $col){
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->SetTextColor(...$col['azul_oscuro']);
        $pdf->Cell($anchoTotal, 9, pdfTxt('PRODUCCION SELLADO'), 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$col['texto']);
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->Cell($anchoTotal, 8, pdfTxt(
            'Fecha: ' . date('d/m/Y', strtotime($fecha)) .
            '      Turno: ' . $planilla['bloque'] .
            '      Supervisor: ' . $planilla['supervisor_nombre'] .
            '      Codigo: ' . $codigo
        ), 1, 1, 'C', true);
        $pdf->Ln(2);
    };

    // Encabezado de la tabla (columnas)
    $dibujarEncabezadoTabla = function() use ($pdf, $cols, $anchoPeso, $col){
        $altoCab = 8;
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(...$col['azul_oscuro']);
        $pdf->SetTextColor(255);
        $pdf->Cell($cols['MÁQUINA'], $altoCab, pdfTxt('MAQUINA'), 1, 0, 'C', true);
        $pdf->Cell($cols['OPERARIO'], $altoCab, pdfTxt('OPERARIO'), 1, 0, 'C', true);
        $pdf->Cell($cols['REFERENCIA'], $altoCab, pdfTxt('REFERENCIA'), 1, 0, 'C', true);
        $pdf->Cell($cols['COLOR'], $altoCab, pdfTxt('COLOR'), 1, 0, 'C', true);
        $pdf->Cell($cols['X70'], $altoCab, 'X70', 1, 0, 'C', true);
        $pdf->Cell($cols['X90'], $altoCab, 'X90', 1, 0, 'C', true);
        $pdf->Cell($cols['X98'], $altoCab, 'X98', 1, 0, 'C', true);
        $pdf->Cell($anchoPeso, $altoCab, pdfTxt('PESO POR HORA'), 1, 0, 'C', true);
        $pdf->Cell($cols['OBSERV'], $altoCab, pdfTxt('OBSERVACIONES'), 1, 1, 'C', true);
        $pdf->SetTextColor(...$col['texto']);
        $pdf->SetFont('Helvetica', '', 8);
    };

    // Una celda de texto (recortado si es necesario). $colorRelleno = null -> sin relleno
    $celda = function($ancho, $alto, $texto, $align = 'C', $colorRelleno = null) use ($pdf, $col){
        $relleno = $colorRelleno !== null;
        if($relleno){ $pdf->SetFillColor(...$colorRelleno); }
        // Si el texto no cabe se achica la letra (hasta 5 pt) antes de recortarlo
        $tamOriginal = 8; // tamaño de las celdas de datos
        $tam = $tamOriginal;
        $conv = pdfTxt((string) $texto);
        while($tam > 5 && $pdf->GetStringWidth($conv) > $ancho - 2){
            $tam -= 0.5;
            $pdf->SetFontSize($tam);
        }
        $pdf->Cell($ancho, $alto, pdfTxtRecortado($pdf, (string) $texto, $ancho - 2), 1, 0, $align, $relleno);
        $pdf->SetFontSize($tamOriginal);
    };

    // Línea gruesa (mismo azul de los demás elementos) que separa cada bloque de máquina
    $lineaGruesaDivisoria = function($x0, $y, $ancho) use ($pdf, $col, $lineaNormal){
        $pdf->SetDrawColor(...$col['azul_oscuro']);
        $pdf->SetLineWidth(0.8);
        $pdf->Line($x0, $y, $x0 + $ancho, $y);
        $pdf->SetDrawColor(...$col['borde']);
        $pdf->SetLineWidth($lineaNormal);
    };

    // Dibuja el bloque de una máquina (número fusionado a la izquierda + sus filas)
    $dibujarMaquina = function($numMaq, $datos, $filaAlto) use ($pdf, $cols, $col, $celda, $anchoTotal){
        $entradas = $datos['entradas'] ?? [];
        $filasDatos = max(2, count($entradas)); // mínimo 2 filas de datos por máquina
        $filasTotal = $filasDatos + 2;           // + JORNADA (etiqueta y valor)
        $altoBloque = $filasTotal * $filaAlto;

        $x0 = $pdf->GetX();
        $y0 = $pdf->GetY();

        // Número de máquina: una sola celda fusionada, centrada verticalmente
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->Rect($x0, $y0, $cols['MÁQUINA'], $altoBloque, 'DF');
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetXY($x0, $y0 + ($altoBloque / 2) - 4);
        $pdf->Cell($cols['MÁQUINA'], 8, (string) $numMaq, 0, 0, 'C');

        // Operario: una sola celda fusionada (ancho limitado a esta columna, no toda la tabla),
        // con "NOMBRE" fijo arriba y el nombre debajo (una sola vez por máquina, no por fila)
        $xOp = $x0 + $cols['MÁQUINA'];
        $pdf->Rect($xOp, $y0, $cols['OPERARIO'], $altoBloque, 'D');
        $pdf->SetFont('Helvetica', 'B', 6);
        $pdf->SetTextColor(...$col['azul_oscuro']);
        $pdf->SetXY($xOp + 1.5, $y0 + 1);
        $pdf->Cell($cols['OPERARIO'] - 3, 3, pdfTxt('NOMBRE'), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...$col['texto']);
        $pdf->SetXY($xOp + 1.5, $y0 + 4);
        $nombreOperario = $datos['operario'] ?? '';
        if($nombreOperario !== '' && empty($datos['operario_verificado'])){
            $nombreOperario = 'Otro: ' . $nombreOperario;
        }
        $pdf->Cell($cols['OPERARIO'] - 3, 5, pdfTxtRecortado($pdf, $nombreOperario, $cols['OPERARIO'] - 5), 0, 0, 'L');

        // Filas de datos (Referencia, Color, pesos...), con fondo intercalado (zebra)
        for($i = 0; $i < $filasDatos; $i++){
            $pdf->SetXY($xOp + $cols['OPERARIO'], $y0 + $i * $filaAlto);
            $ent = $entradas[$i] ?? null;
            $fondo = ($i % 2 === 1) ? $col['azul_muy_claro'] : null;
            $celda($cols['REFERENCIA'], $filaAlto, $ent['nombre_referencia'] ?? '', 'L', $fondo);
            $celda($cols['COLOR'], $filaAlto, $ent['nombre_color'] ?? '', 'L', $fondo);
            $celda($cols['X70'], $filaAlto, $ent['paquetes_x70'] ?? '', 'C', $fondo);
            $celda($cols['X90'], $filaAlto, $ent['paquetes_x90'] ?? '', 'C', $fondo);
            $celda($cols['X98'], $filaAlto, $ent['paquetes_x98'] ?? '', 'C', $fondo);
            $celda($cols['P1'], $filaAlto, $ent['peso_hora1'] ?? '', 'C', $fondo);
            $celda($cols['P2'], $filaAlto, $ent['peso_hora2'] ?? '', 'C', $fondo);
            $celda($cols['P3'], $filaAlto, $ent['peso_hora3'] ?? '', 'C', $fondo);
            $celda($cols['P4'], $filaAlto, $ent['peso_hora4'] ?? '', 'C', $fondo);
            $celda($cols['P5'], $filaAlto, $ent['peso_hora5'] ?? '', 'C', $fondo);
            $celda($cols['OBSERV'], $filaAlto, $ent['obs_sellado'] ?? '', 'L', $fondo);
        }

        // JORNADA: etiqueta + valor, cada una en una fila fusionada de ancho completo
        $anchoResto = $anchoTotal - $cols['MÁQUINA'];
        $pdf->SetXY($x0 + $cols['MÁQUINA'], $y0 + $filasDatos * $filaAlto);
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->Cell($anchoResto, $filaAlto, pdfTxt('JORNADA'), 1, 1, 'L', true);
        $pdf->SetX($x0 + $cols['MÁQUINA']);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...$col['texto']);
        $pdf->Cell($anchoResto, $filaAlto, pdfTxt($datos['jornada'] ?? ''), 1, 1, 'L');

        $pdf->SetXY($x0, $y0 + $altoBloque);
    };

    foreach($paginas as $indicePagina => $maquinasPagina){
        if(empty($maquinasPagina)){ continue; }
        $esUltimaPagina = ($indicePagina === count($paginas) - 1);
        $pdf->AddPage();
        if($indicePagina === 0){ $dibujarPortada(); }
        $dibujarEncabezadoTabla();
        $xTabla = $pdf->GetX();

        // Altura de fila: se ajusta para que las máquinas (y, en la última página,
        // también la nota) quepan siempre dentro de la página, sin páginas de más
        // La nota ocupa 10 mm (espacio + título) + 5 mm por línea de texto
        $reservaNota = 0;
        if($esUltimaPagina){
            $pdf->SetFont('Helvetica', '', 9);
            $textoNota = pdfTxt(trim((string) $nota) !== '' ? trim((string) $nota) : '(Sin nota)');
            $lineasNota = 0;
            foreach(explode("\n", $textoNota) as $parrafo){
                $lineasNota += max(1, (int) ceil($pdf->GetStringWidth($parrafo) / ($anchoTotal - 2)));
            }
            $reservaNota = 11 + 5 * $lineasNota;
        }
        $totalFilas = 0;
        foreach($maquinasPagina as $m){
            $entradas = $porMaquina[$m['numero_maquina']]['entradas'] ?? [];
            $totalFilas += max(2, count($entradas)) + 2;
        }
        // Margen inferior de 7 mm. Las filas se comprimen hasta 2,8 mm (con letra de 8 pt
        // aún se leen): así caben ~60 filas por página antes de que algo se salga.
        $altoDisponible = $pdf->GetPageHeight() - $pdf->GetY() - 7 - $reservaNota;
        $filaAlto = $totalFilas > 0 ? min(7, max(2.8, $altoDisponible / $totalFilas)) : 7;

        foreach($maquinasPagina as $m){
            $num = (int) $m['numero_maquina'];
            $datos = $porMaquina[$num] ?? ['operario' => '', 'operario_verificado' => true, 'jornada' => '', 'entradas' => []];
            $dibujarMaquina($num, $datos, $filaAlto);
            $lineaGruesaDivisoria($xTabla, $pdf->GetY(), $anchoTotal);
        }

        // Nota general del turno: al final de la última página, no en una página aparte
        if($esUltimaPagina){
            $pdf->Ln(3);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(...$col['azul_oscuro']);
            $pdf->Cell($anchoTotal, 7, pdfTxt('NOTA GENERAL DEL TURNO'), 0, 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(...$col['texto']);
            $notaTexto = trim((string) $nota);
            $pdf->MultiCell($anchoTotal, 5, pdfTxt($notaTexto !== '' ? $notaTexto : '(Sin nota)'), 1);
        }
    }

    // Devolver el PDF como cadena binaria
    return $pdf->Output('S');
}

// Nombre del archivo PDF del turno: el código tal cual, con el turno a 2 dígitos
// (S20260924_T002 -> S20260924_T02.pdf), sin el prefijo "Produccion_"
function nombrePdfPlanilla($codigo){
    $nombre = preg_replace_callback('/_T(\d{3})$/', function($m){
        return '_T' . str_pad((int) $m[1], 2, '0', STR_PAD_LEFT);
    }, $codigo);
    return $nombre . '.pdf';
}
