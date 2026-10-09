<?php
// PDF de un día+máquina: puede traer varias mezclas (una por referencia)

require_once dirname(__DIR__, 2) . '/shared/fpdf/fpdf.php';

// UTF-8 a codificación FPDF
function pdfTxtMezcla($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// Colores corporativos
function pdfColoresMezcla(){
    return [
        'azul_oscuro'    => [22, 74, 125],
        'azul_claro'     => [227, 238, 249],
        'azul_muy_claro' => [240, 246, 252],
        'borde'          => [200, 208, 201],
        'texto'          => [40, 40, 40],
    ];
}

// Columnas de cada tabla => etiqueta + unidad
function columnasPdfTabla1Mezcla(){
    return [
        'blanco_r' => ['BLANCO R', 'x25'], 'negro_r' => ['NEGRO R', 'x25'], 'rojo_r' => ['ROJO R', 'x25'],
        'amarillo_r' => ['AMARILLO R', 'x25'], 'azul_r' => ['AZUL R', 'x25'], 'verde_r' => ['VERDE R', 'x25'],
        'naranja_r' => ['NARANJA R', 'x25'], 'marron_r' => ['MARRÓN R', 'x25'], 'ladrillo_r' => ['LADRILLO R', 'x25'],
    ];
}
function columnasPdfTabla2Mezcla(){
    return [
        'original_b' => ['ORIGINAL B', 'x25'], 'fg' => ['FG', 'x25'],
        'master' => ['MASTER', 'x25'], 'lineal' => ['LINEAL', 'x25'], 'deshidratante' => ['DESHIDRATANTE', 'kg'],
    ];
}
function columnasPdfTabla3Mezcla(){
    return [
        'p_blanco' => ['P. BLANCO', 'kg'], 'p_negro' => ['P. NEGRO', 'kg'], 'p_rojo' => ['P. ROJO', 'kg'],
        'p_amarillo' => ['P. AMARILLO', 'kg'], 'p_azul' => ['P. AZUL', 'kg'], 'p_verde' => ['P. VERDE', 'kg'],
        'p_naranja' => ['P. NARANJA', 'kg'], 'p_marron' => ['P. MARRÓN', 'kg'], 'p_ladrillo' => ['P. LADRILLO', 'kg'],
    ];
}

// Una tabla con solo las columnas que sí tienen valor
function tablaMezclaPdf($pdf, $col, $datos, $columnas){
    $llenas = [];
    foreach($columnas as $campo => [$etiqueta, $unidad]){
        if($datos[$campo] !== null && $datos[$campo] !== ''){
            $texto = formatoNumeroMezcla($datos[$campo]);
            // x25 va pegado (4x25); kg lleva espacio (17 kg)
            $separador = ($unidad === 'x25') ? '' : ' ';
            $llenas[$etiqueta] = $texto . $separador . $unidad;
        }
    }
    if(empty($llenas)){
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$col['texto']);
        $pdf->Cell(0, 8, pdfTxtMezcla('Sin datos'), 1, 1, 'C');
        return;
    }
    $ancho = 190 / count($llenas);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(...$col['azul_oscuro']);
    $pdf->SetTextColor(255);
    foreach($llenas as $etiqueta => $valor){
        $pdf->Cell($ancho, 8, pdfTxtMezcla($etiqueta), 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(...$col['texto']);
    $pdf->SetFillColor(...$col['azul_muy_claro']);
    foreach($llenas as $valor){
        $pdf->Cell($ancho, 8, pdfTxtMezcla($valor), 1, 0, 'C', true);
    }
    $pdf->Ln();
}

// Un bloque (operario/referencia + las 2 tablas + observaciones) por mezcla
function bloqueMezclaPdf($pdf, $col, $datos){
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(...$col['texto']);
    $pdf->SetFillColor(...$col['azul_claro']);
    $pdf->Cell(95, 8, pdfTxtMezcla('Operario: ' . $datos['operario']), 1, 0, 'C', true);
    $pdf->Cell(95, 8, pdfTxtMezcla('Referencia: ' . $datos['referencia']), 1, 1, 'C', true);
    $pdf->Ln(2);

    tablaMezclaPdf($pdf, $col, $datos, columnasPdfTabla1Mezcla());
    $pdf->Ln(4);
    tablaMezclaPdf($pdf, $col, $datos, columnasPdfTabla2Mezcla());
    $pdf->Ln(4);
    tablaMezclaPdf($pdf, $col, $datos, columnasPdfTabla3Mezcla());

    if(trim((string) $datos['observaciones']) !== ''){
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(...$col['azul_oscuro']);
        $pdf->Cell(0, 7, pdfTxtMezcla('Observaciones'), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$col['texto']);
        $pdf->MultiCell(0, 6, pdfTxtMezcla($datos['observaciones']), 1);
    }
}

// $mezclas: una o varias, cada una ['operario','referencia','observaciones', + columnas de las 2 tablas]
function generarPdfMezcla($fecha, $nombreMaquina, array $mezclas){
    $col = pdfColoresMezcla();
    $pdf = new FPDF('P', 'mm', 'Letter');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->SetDrawColor(...$col['borde']);
    $pdf->SetLineWidth(0.2);
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetTextColor(...$col['azul_oscuro']);
    $pdf->Cell(0, 9, pdfTxtMezcla('PRODUCCIÓN MEZCLAS'), 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(...$col['texto']);
    $pdf->SetFillColor(...$col['azul_claro']);
    $pdf->Cell(95, 8, pdfTxtMezcla('Fecha: ' . date('d/m/Y', strtotime($fecha))), 1, 0, 'C', true);
    $pdf->Cell(95, 8, pdfTxtMezcla('Máquina: ' . $nombreMaquina), 1, 1, 'C', true);
    $pdf->Ln(4);

    $primero = true;
    foreach($mezclas as $datos){
        if(!$primero){
            $pdf->Ln(6);
            $y = $pdf->GetY();
            $pdf->Line(10, $y, 200, $y);
            $pdf->Ln(6);
        }
        $primero = false;
        bloqueMezclaPdf($pdf, $col, $datos);
    }

    return $pdf->Output('S');
}
