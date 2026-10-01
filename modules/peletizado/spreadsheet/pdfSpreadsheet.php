<?php
// PDF del día por máquina

require_once dirname(__DIR__, 2) . '/shared/fpdf/fpdf.php';

// UTF-8 a codificación FPDF
function pdfTxtPeletizado($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// Colores corporativos
function pdfColoresPeletizado(){
    return [
        'azul_oscuro'    => [22, 74, 125],
        'azul_claro'     => [227, 238, 249],
        'azul_muy_claro' => [240, 246, 252],
        'borde'          => [200, 208, 201],
        'texto'          => [40, 40, 40],
    ];
}

// Recortar al ancho
function textoAjustadoPeletizado($pdf, $texto, $ancho){
    $texto = pdfTxtPeletizado($texto);
    while($texto !== '' && $pdf->GetStringWidth($texto) > $ancho - 2){
        $texto = substr($texto, 0, -1);
    }
    return $texto;
}

function generarPdfPeletizado($fecha, $nombreMaquina, array $turnos){
    $col = pdfColoresPeletizado();
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);
    $pdf->SetDrawColor(...$col['borde']);
    $pdf->SetLineWidth(0.2);
    $pdf->AddPage();

    // Título
    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetTextColor(...$col['azul_oscuro']);
    $pdf->Cell(0, 9, pdfTxtPeletizado('PRODUCCIÓN PELETIZADO'), 0, 1, 'C');
    $pdf->Ln(2);

    $cols = ['COLOR' => 34, 'ALTA RETAL' => 24, 'BAJA' => 20, 'REFILTRADO' => 26, 'SOPLADO' => 22, 'TORTA' => 20, 'LIMPIEZA' => 24, 'TOTAL' => 22];
    $anchoTotal = array_sum($cols);

    // Alto de fila ajustable
    $filasTotales = 0;
    foreach($turnos as $t){ $filasTotales += max(1, count($t['filas'])) + 4; }
    $disponible = $pdf->GetPageHeight() - $pdf->GetY() - 12;
    $alto = min(7, max(4.5, ($disponible - count($turnos) * 16) / max(1, $filasTotales)));
    $tam = $alto < 5.5 ? 7.5 : 9;
    $limiteY = $pdf->GetPageHeight() - 16;

    foreach($turnos as $t){
        // Datos del turno
        if($pdf->GetY() + 34 > $limiteY){ $pdf->AddPage(); }
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->SetTextColor(...$col['texto']);
        $operarios = trim($t['operario1'] . (($t['operario2'] ?? '') !== '' ? ' / ' . $t['operario2'] : ''));
        $info = 'Fecha: ' . date('d/m/Y', strtotime($fecha)) . '     Máquina: ' . $nombreMaquina
              . '     Turno: ' . $t['turno'] . '     Operario(s): ' . $operarios . '     Código: ' . $t['codigo'];
        $pdf->Cell($anchoTotal, 8, pdfTxtPeletizado($info), 1, 1, 'C', true);

        $encabezado = function() use ($pdf, $cols, $col, $alto){
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetFillColor(...$col['azul_oscuro']);
            $pdf->SetTextColor(255);
            foreach($cols as $etq => $ancho){
                $pdf->Cell($ancho, $alto + 1, pdfTxtPeletizado($etq), 1, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetTextColor(...$col['texto']);
        };
        $encabezado();

        $pdf->SetFont('Helvetica', '', $tam);
        $par = false;
        $totales = ['alta_retal'=>0,'baja'=>0,'refiltrado'=>0,'soplado'=>0,'torta'=>0,'limpieza'=>0,'total'=>0];
        foreach($t['filas'] as $f){
            if($pdf->GetY() + $alto > $limiteY){
                $pdf->AddPage();
                $encabezado();
                $pdf->SetFont('Helvetica', '', $tam);
            }
            $par = !$par;
            if($par){ $pdf->SetFillColor(...$col['azul_muy_claro']); } else { $pdf->SetFillColor(255, 255, 255); }
            foreach($totales as $k => $v){ $totales[$k] += $f[$k]; }
            $pdf->Cell($cols['COLOR'], $alto, textoAjustadoPeletizado($pdf, $f['color'], $cols['COLOR']), 1, 0, 'L', true);
            $pdf->Cell($cols['ALTA RETAL'], $alto, (string) $f['alta_retal'], 1, 0, 'C', true);
            $pdf->Cell($cols['BAJA'], $alto, (string) $f['baja'], 1, 0, 'C', true);
            $pdf->Cell($cols['REFILTRADO'], $alto, (string) $f['refiltrado'], 1, 0, 'C', true);
            $pdf->Cell($cols['SOPLADO'], $alto, (string) $f['soplado'], 1, 0, 'C', true);
            $pdf->Cell($cols['TORTA'], $alto, (string) $f['torta'], 1, 0, 'C', true);
            $pdf->Cell($cols['LIMPIEZA'], $alto, (string) $f['limpieza'], 1, 0, 'C', true);
            $pdf->Cell($cols['TOTAL'], $alto, (string) $f['total'], 1, 1, 'C', true);
        }

        // Totales del turno
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(...$col['azul_claro']);
        $pdf->Cell($cols['COLOR'], $alto + 1, pdfTxtPeletizado('TOTAL'), 1, 0, 'L', true);
        $pdf->Cell($cols['ALTA RETAL'], $alto + 1, (string) $totales['alta_retal'], 1, 0, 'C', true);
        $pdf->Cell($cols['BAJA'], $alto + 1, (string) $totales['baja'], 1, 0, 'C', true);
        $pdf->Cell($cols['REFILTRADO'], $alto + 1, (string) $totales['refiltrado'], 1, 0, 'C', true);
        $pdf->Cell($cols['SOPLADO'], $alto + 1, (string) $totales['soplado'], 1, 0, 'C', true);
        $pdf->Cell($cols['TORTA'], $alto + 1, (string) $totales['torta'], 1, 0, 'C', true);
        $pdf->Cell($cols['LIMPIEZA'], $alto + 1, (string) $totales['limpieza'], 1, 0, 'C', true);
        $pdf->Cell($cols['TOTAL'], $alto + 1, (string) $totales['total'], 1, 1, 'C', true);

        // Observaciones del turno
        if(trim((string) $t['observaciones']) !== ''){
            $pdf->SetFont('Helvetica', 'I', 8.5);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell($anchoTotal, 6, textoAjustadoPeletizado($pdf, 'Obs: ' . $t['observaciones'], $anchoTotal), 1, 1, 'L');
        }
        $pdf->Ln(5);
    }

    return $pdf->Output('S');
}
