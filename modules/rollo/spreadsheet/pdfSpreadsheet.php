<?php
// PDF del día de Rollos

require_once dirname(__DIR__, 2) . '/shared/fpdf/fpdf.php';

// UTF-8 a codificación FPDF
function pdfTxtRollo($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// Colores corporativos
function pdfColoresRollo(){
    return [
        'azul_oscuro'    => [22, 74, 125],
        'azul_claro'     => [227, 238, 249],
        'azul_muy_claro' => [240, 246, 252],
        'borde'          => [200, 208, 201],
        'texto'          => [40, 40, 40],
    ];
}

// $filas: array de ['operario','maquina','referencia','color','peso_rollo','peso_retal','peso_total']
function generarPdfDiaRollo($fecha, $filas){
    $col = pdfColoresRollo();
    $pdf = new FPDF('L', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetDrawColor(...$col['borde']);
    $pdf->SetLineWidth(0.2);
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetTextColor(...$col['azul_oscuro']);
    $pdf->Cell(0, 9, pdfTxtRollo('PRODUCCIÓN ROLLOS'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(...$col['texto']);
    $pdf->SetFillColor(...$col['azul_claro']);
    $pdf->Cell(0, 8, pdfTxtRollo('Fecha: ' . date('d/m/Y', strtotime($fecha))), 1, 1, 'C', true);
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

    // Encabezado, repetido en cada página
    $encabezado = function() use ($pdf, $cols, $col){
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(...$col['azul_oscuro']);
        $pdf->SetTextColor(255);
        foreach($cols as $etiqueta => $ancho){
            $pdf->Cell($ancho, 9, pdfTxtRollo($etiqueta), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(...$col['texto']);
        $pdf->SetFont('Helvetica', '', 9.5);
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
            if($par){ $pdf->SetFillColor(...$col['azul_muy_claro']); } else { $pdf->SetFillColor(255, 255, 255); }

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
                $pdf->Cell($ancho, 7, $texto, 1, 0, $align, true);
            }
            $pdf->Ln();
        }
    } else {
        $pdf->Cell($anchoTotal, 8, pdfTxtRollo('Sin registros'), 1, 1, 'C');
    }

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(...$col['azul_claro']);
    $pdf->Cell($anchoTotal, 8, pdfTxtRollo('Total de registros: ' . count($filas)), 1, 1, 'R', true);

    return $pdf->Output('S');
}

// Nombre del PDF del día
function nombrePdfDiaRollo($id_dia){
    return $id_dia . '.pdf';
}
