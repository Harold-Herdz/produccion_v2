<?php
// Genera el PDF del día de Rollos (bytes en memoria; se envía a Drive vía Apps Script)

require_once dirname(__DIR__, 2) . '/shared/fpdf/fpdf.php';

// Pasar texto UTF-8 a la codificación de las fuentes base de FPDF
function pdfTxtRollo($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// $filas: array de ['operario','maquina','referencia','color','peso_rollo','peso_retal','peso_total']
function generarPdfDiaRollo($fecha, $filas){
    $pdf = new FPDF('L', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->Cell(0, 8, pdfTxtRollo('PRODUCCIÓN DE ROLLOS'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, pdfTxtRollo('Fecha: ' . date('d/m/Y', strtotime($fecha))), 0, 1, 'C');
    $pdf->Ln(2);

    $cols = [
        'OPERARIO'   => 48,
        'MÁQUINA'    => 38,
        'REFERENCIA' => 42,
        'COLOR'      => 32,
        'PESO ROLLO' => 32,
        'PESO RETAL' => 32,
        'TOTAL'      => 32,
    ];
    $anchoTotal = array_sum($cols);

    // Fila de encabezado (se repite al saltar de página)
    $encabezado = function() use ($pdf, $cols){
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(4, 42, 53);
        $pdf->SetTextColor(255);
        foreach($cols as $etiqueta => $ancho){
            $pdf->Cell($ancho, 8, pdfTxtRollo($etiqueta), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0);
        $pdf->SetFont('Helvetica', '', 9);
    };
    $encabezado();

    $limiteY = $pdf->GetPageHeight() - 18;
    $par = false;

    if(!empty($filas)){
        foreach($filas as $fila){
            if($pdf->GetY() > $limiteY){
                $pdf->AddPage();
                $encabezado();
            }
            $par = !$par;
            $pdf->SetFillColor($par ? 234 : 255, $par ? 240 : 255, $par ? 243 : 255);

            $valores = [
                ['OPERARIO', $fila['operario'], 'L'],
                ['MÁQUINA', $fila['maquina'], 'L'],
                ['REFERENCIA', $fila['referencia'], 'L'],
                ['COLOR', $fila['color'], 'L'],
                ['PESO ROLLO', $fila['peso_rollo'], 'C'],
                ['PESO RETAL', $fila['peso_retal'], 'C'],
                ['TOTAL', $fila['peso_total'], 'C'],
            ];
            foreach($valores as [$etiqueta, $valor, $align]){
                $ancho = $cols[$etiqueta];
                $texto = pdfTxtRollo($valor === null ? '' : (string) $valor);
                while($texto !== '' && $pdf->GetStringWidth($texto) > $ancho - 2){
                    $texto = substr($texto, 0, -1);
                }
                $pdf->Cell($ancho, 6, $texto, 1, 0, $align, true);
            }
            $pdf->Ln();
        }
    } else {
        $pdf->Cell($anchoTotal, 7, pdfTxtRollo('Sin registros'), 1, 1, 'C');
    }

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell($anchoTotal, 7, pdfTxtRollo('Total de registros: ' . count($filas)), 1, 1, 'R');

    return $pdf->Output('S');
}

// Nombre del archivo PDF del día
function nombrePdfDiaRollo($id_dia){
    return 'Rollos_' . $id_dia . '.pdf';
}
