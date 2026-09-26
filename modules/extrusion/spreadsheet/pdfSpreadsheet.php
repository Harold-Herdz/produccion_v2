<?php
// PDF del día por máquina

require_once dirname(__DIR__, 2) . '/shared/fpdf/fpdf.php';

// UTF-8 a codificación FPDF
function pdfTxtExtrusion($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// Colores corporativos
function pdfColoresExtrusion(){
    return [
        'azul_oscuro'    => [22, 74, 125],
        'azul_claro'     => [227, 238, 249],
        'azul_muy_claro' => [240, 246, 252],
        'borde'          => [200, 208, 201],
        'texto'          => [40, 40, 40],
    ];
}

// Recortar al ancho
function textoAjustadoExtrusion($pdf, $texto, $ancho){
    $texto = pdfTxtExtrusion($texto);
    while($texto !== '' && $pdf->GetStringWidth($texto) > $ancho - 2){
        $texto = substr($texto, 0, -1);
    }
    return $texto;
}

function generarPdfExtrusion($fecha, $nombreMaquina, array $turnos){
    $col = pdfColoresExtrusion();
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetDrawColor(...$col['borde']);
    $pdf->SetLineWidth(0.2);
    $pdf->AddPage();

    // Título y fecha
    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetTextColor(...$col['azul_oscuro']);
    $pdf->Cell(0, 9, pdfTxtExtrusion('PRODUCCIÓN EXTRUSIÓN'), 0, 1, 'C');
    $pdf->Ln(2);

    $cols = ['ROLLO' => 22, 'REFERENCIA' => 52, 'COLOR' => 44, 'LÁMINA P' => 44, 'PESO' => 33];
    $anchoTotal = array_sum($cols);

    // Alto de fila ajustable
    $filasTotales = 0;
    foreach($turnos as $t){ $filasTotales += count($t['rollos']) + 3; }
    $disponible = $pdf->GetPageHeight() - $pdf->GetY() - 12;
    $alto = min(6.5, max(4.2, ($disponible - count($turnos) * 12) / max(1, $filasTotales)));
    $tam = $alto < 5 ? 7.5 : 9;
    $limiteY = $pdf->GetPageHeight() - 16;

    foreach($turnos as $t){
        // Datos del turno
        if($pdf->GetY() + 30 > $limiteY){ $pdf->AddPage(); }
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->SetTextColor(...$col['texto']);
        $info = 'Fecha: ' . date('d/m/Y', strtotime($fecha)) . '     Máquina: ' . $nombreMaquina
              . '     Turno: ' . $t['turno'] . '     Operador: ' . $t['operador'] . '     Código: ' . $t['codigo'];
        $pdf->Cell($anchoTotal, 8, pdfTxtExtrusion($info), 1, 1, 'C', true);

        $encabezado = function() use ($pdf, $cols, $col, $alto){
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetFillColor(...$col['azul_oscuro']);
            $pdf->SetTextColor(255);
            foreach($cols as $etq => $ancho){
                $pdf->Cell($ancho, $alto + 1, pdfTxtExtrusion($etq), 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetTextColor(...$col['texto']);
        };
        $encabezado();

        $pdf->SetFont('Helvetica', '', $tam);
        $par = false;
        $totalPeso = 0;
        foreach($t['rollos'] as $r){
            if($pdf->GetY() + $alto > $limiteY){
                $pdf->AddPage();
                $encabezado();
                $pdf->SetFont('Helvetica', '', $tam);
            }
            $par = !$par;
            if($par){ $pdf->SetFillColor(...$col['azul_muy_claro']); } else { $pdf->SetFillColor(255, 255, 255); }
            $totalPeso += $r['peso'];
            $pdf->Cell($cols['ROLLO'], $alto, str_pad($r['n'], 2, '0', STR_PAD_LEFT), 1, 0, 'C', true);
            $pdf->Cell($cols['REFERENCIA'], $alto, textoAjustadoExtrusion($pdf, $r['referencia'], $cols['REFERENCIA']), 1, 0, 'L', true);
            $pdf->Cell($cols['COLOR'], $alto, textoAjustadoExtrusion($pdf, $r['color'], $cols['COLOR']), 1, 0, 'L', true);
            $pdf->Cell($cols['LÁMINA P'], $alto, textoAjustadoExtrusion($pdf, $r['lamina'], $cols['LÁMINA P']), 1, 0, 'L', true);
            $pdf->Cell($cols['PESO'], $alto, number_format($r['peso'], 2, ',', '.'), 1, 1, 'C', true);
        }

        // Totales del turno
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->Cell($anchoTotal - $cols['PESO'], $alto + 1, pdfTxtExtrusion('Total rollos: ' . count($t['rollos'])), 1, 0, 'R', true);
        $pdf->Cell($cols['PESO'], $alto + 1, number_format($totalPeso, 2, ',', '.'), 1, 1, 'C', true);
        $pdf->Ln(5);
    }

    return $pdf->Output('S');
}
