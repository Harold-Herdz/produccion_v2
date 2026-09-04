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

// Generar el PDF del turno y devolverlo como cadena binaria
function generarPdfPlanilla($conexion, $planilla, $nota = ''){
    $codigo = $planilla['codigo'];
    $fecha  = $planilla['fecha_planilla'];

    // Datos de las entradas del turno
    $res = obtenerEntradasPlanillaPdf($conexion, $codigo);

    // Documento horizontal tamaño carta
    $pdf = new FPDF('L', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    // Encabezado del documento
    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->Cell(0, 8, pdfTxt('PRODUCCIÓN SELLADO'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, pdfTxt(
        'Fecha: ' . date('d/m/Y', strtotime($fecha)) .
        '     Turno: ' . $planilla['bloque'] .
        '     Supervisor: ' . $planilla['supervisor_nombre'] .
        '     Código: ' . $codigo
    ), 0, 1, 'C');
    $pdf->Ln(2);

    // Columnas de la planilla: etiqueta => ancho (mm)
    $cols = [
        'MÁQUINA'       => 11,
        'OPERARIO'      => 34,
        'JORNADA'       => 15,
        'REFERENCIA'    => 33,
        'COLOR'         => 22,
        'X70'           => 12,
        'X90'           => 12,
        'X98'           => 12,
        'P1'            => 12,
        'P2'            => 12,
        'P3'            => 12,
        'P4'            => 12,
        'P5'            => 12,
        'OBSERVACIONES' => 47,
    ];
    $anchoTotal = array_sum($cols);

    // Fila de encabezado de la tabla (se repite al saltar de página)
    $encabezado = function() use ($pdf, $cols){
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->SetFillColor(4, 42, 53);
        $pdf->SetTextColor(255);
        foreach($cols as $etiqueta => $ancho){
            $pdf->Cell($ancho, 7, pdfTxt($etiqueta), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0);
        $pdf->SetFont('Helvetica', '', 7);
    };
    $encabezado();

    // Filas de datos, agrupadas visualmente por máquina
    $limiteY    = $pdf->GetPageHeight() - 22;
    $totalFilas = 0;
    $maqAnterior = null;

    if($res && $res->num_rows > 0){
        while($fila = $res->fetch_assoc()){
            if($pdf->GetY() > $limiteY){
                $pdf->AddPage();
                $encabezado();
                $maqAnterior = null;
            }

            $numMaq   = (int) $fila['id_maquina'];
            $nuevaMaq = ($numMaq !== $maqAnterior);
            $maqAnterior = $numMaq;

            // Los datos de máquina/operario/jornada solo en la primera fila del bloque
            $celMaq      = $nuevaMaq ? str_pad($numMaq, 2, '0', STR_PAD_LEFT) : '';
            $celOperario = $nuevaMaq ? ($fila['nombre_operario'] ?? '') : '';
            $celJornada  = $nuevaMaq ? ($fila['jornada'] ?? '') : '';

            // Línea superior más marcada al empezar una máquina nueva
            if($nuevaMaq && $totalFilas > 0){
                $y = $pdf->GetY();
                $pdf->SetLineWidth(0.5);
                $pdf->Line(10, $y, 10 + $anchoTotal, $y);
                $pdf->SetLineWidth(0.2);
            }

            $valores = [
                ['MÁQUINA', $celMaq, 'C'],
                ['OPERARIO', $celOperario, 'L'],
                ['JORNADA', $celJornada, 'C'],
                ['REFERENCIA', $fila['nombre_referencia'] ?? '', 'L'],
                ['COLOR', $fila['nombre_color'] ?? '', 'L'],
                ['X70', $fila['paquetes_x70'], 'C'],
                ['X90', $fila['paquetes_x90'], 'C'],
                ['X98', $fila['paquetes_x98'], 'C'],
                ['P1', $fila['peso_hora1'], 'C'],
                ['P2', $fila['peso_hora2'], 'C'],
                ['P3', $fila['peso_hora3'], 'C'],
                ['P4', $fila['peso_hora4'], 'C'],
                ['P5', $fila['peso_hora5'], 'C'],
                ['OBSERVACIONES', $fila['obs_sellado'] ?? '', 'L'],
            ];

            foreach($valores as [$etiqueta, $valor, $align]){
                $ancho = $cols[$etiqueta];
                $texto = pdfTxt($valor === null ? '' : (string) $valor);
                // Recortar textos largos para que no rompan la celda
                while($texto !== '' && $pdf->GetStringWidth($texto) > $ancho - 2){
                    $texto = substr($texto, 0, -1);
                }
                $pdf->Cell($ancho, 6, $texto, 1, 0, $align);
            }
            $pdf->Ln();
            $totalFilas++;
        }
    } else {
        $pdf->Cell($anchoTotal, 7, pdfTxt('Sin registros en el turno'), 1, 1, 'C');
    }

    // Total de entradas
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell($anchoTotal, 7, pdfTxt('Total de entradas: ' . $totalFilas), 1, 1, 'R');

    // Nota general del turno (solo en el PDF, no en la base de datos)
    $pdf->Ln(4);
    if($pdf->GetY() > $pdf->GetPageHeight() - 30){
        $pdf->AddPage();
    }
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(0, 6, pdfTxt('NOTA GENERAL DEL TURNO'), 0, 1);
    $pdf->SetFont('Helvetica', '', 9);
    $nota = trim((string) $nota);
    $pdf->MultiCell($anchoTotal, 5, pdfTxt($nota !== '' ? $nota : '(Sin nota)'), 1);

    // Devolver el PDF como cadena binaria
    return $pdf->Output('S');
}

// Nombre del archivo PDF del turno
function nombrePdfPlanilla($codigo){
    return 'Produccion_' . $codigo . '.pdf';
}
