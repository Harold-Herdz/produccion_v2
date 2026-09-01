<?php
/* =================================================
   GENERACIÓN DEL PDF DE LA PLANILLA DE SELLADO
   -------------------------------------------------
   Reemplaza la exportación PDF de Google Sheets.
   Se genera SOLO al finalizar el turno y se guarda en:
     pdfs/PDFs Sellado/MM-yyyy/dd-MM-yyyy/Produccion_{codigo}.pdf
================================================= */

require_once dirname(__DIR__, 3) . '/libs/fpdf/fpdf.php';
require_once dirname(__DIR__) . '/models/registerModel.php';

// Pasar texto UTF-8 a la codificación de las fuentes base de FPDF
function pdfTxt($texto){
    $texto = (string) $texto;
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
    return $conv !== false ? $conv : $texto;
}

// Generar el PDF del turno y devolver la ruta absoluta del archivo
function generarPdfPlanilla($conexion, $planilla){
    $codigo = $planilla['codigo'];
    $fecha  = $planilla['fecha_planilla'];

    // Carpetas por mes y por día
    $base      = dirname(__DIR__, 3) . '/pdfs/PDFs Sellado';
    $carpeta   = $base . '/' . date('m-Y', strtotime($fecha)) . '/' . date('d-m-Y', strtotime($fecha));
    if(!is_dir($carpeta)){
        mkdir($carpeta, 0775, true);
    }
    $archivo = $carpeta . '/Produccion_' . $codigo . '.pdf';

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

    // Definición de columnas: etiqueta => ancho (mm)
    $cols = [
        'Máq.'          => 14,
        'Operario'      => 34,
        'Jornada'       => 16,
        'Referencia'    => 30,
        'Color'         => 22,
        'X70'           => 12,
        'X90'           => 12,
        'X98'           => 12,
        'P1'            => 11,
        'P2'            => 11,
        'P3'            => 11,
        'P4'            => 11,
        'P5'            => 11,
        'Total'         => 14,
        'Observaciones' => 34,
    ];

    // Imprimir la fila de encabezado de la tabla
    $encabezado = function() use ($pdf, $cols){
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(4, 42, 53);
        $pdf->SetTextColor(255);
        foreach($cols as $etiqueta => $ancho){
            $pdf->Cell($ancho, 7, pdfTxt($etiqueta), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetTextColor(0);
        $pdf->SetFont('Helvetica', '', 8);
    };
    $encabezado();

    // Filas de datos
    $limiteY = $pdf->GetPageHeight() - 14;
    $par = false;
    $totalFilas = 0;

    if($res && $res->num_rows > 0){
        while($fila = $res->fetch_assoc()){
            // Salto de página con repetición de encabezado
            if($pdf->GetY() > $limiteY){
                $pdf->AddPage();
                $encabezado();
            }

            // Zebra suave
            $par = !$par;
            $pdf->SetFillColor($par ? 234 : 255, $par ? 240 : 255, $par ? 243 : 255);

            $maq = str_pad((int) $fila['id_maquina'], 2, '0', STR_PAD_LEFT);
            $valores = [
                ['Máq.', $maq, 'C'],
                ['Operario', $fila['nombre_operario'] ?? '', 'L'],
                ['Jornada', $fila['jornada'] ?? '', 'C'],
                ['Referencia', $fila['nombre_referencia'] ?? '', 'L'],
                ['Color', $fila['nombre_color'] ?? '', 'L'],
                ['X70', $fila['paquetes_x70'], 'C'],
                ['X90', $fila['paquetes_x90'], 'C'],
                ['X98', $fila['paquetes_x98'], 'C'],
                ['P1', $fila['peso_hora1'], 'C'],
                ['P2', $fila['peso_hora2'], 'C'],
                ['P3', $fila['peso_hora3'], 'C'],
                ['P4', $fila['peso_hora4'], 'C'],
                ['P5', $fila['peso_hora5'], 'C'],
                ['Total', $fila['paquetes_total'], 'C'],
                ['Observaciones', $fila['obs_sellado'] ?? '', 'L'],
            ];

            foreach($valores as [$etiqueta, $valor, $align]){
                $ancho = $cols[$etiqueta];
                $texto = pdfTxt($valor === null ? '' : (string) $valor);
                // Recortar textos largos para que no rompan la celda
                while($texto !== '' && $pdf->GetStringWidth($texto) > $ancho - 2){
                    $texto = substr($texto, 0, -1);
                }
                $pdf->Cell($ancho, 6, $texto, 1, 0, $align, true);
            }
            $pdf->Ln();
            $totalFilas++;
        }
    } else {
        $pdf->Cell(array_sum($cols), 7, pdfTxt('Sin registros en el turno'), 1, 1, 'C');
    }

    // Pie con el total de entradas
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell(array_sum($cols), 7, pdfTxt('Total de entradas: ' . $totalFilas), 1, 1, 'R');

    $pdf->Output('F', $archivo);
    return $archivo;
}

// Convertir la ruta absoluta del PDF en URL web relativa a BASE_URL
function urlPdfPlanilla($rutaAbsoluta){
    $raiz = str_replace('\\', '/', dirname(__DIR__, 3));
    $ruta = str_replace('\\', '/', $rutaAbsoluta);
    $rel  = str_replace($raiz, '', $ruta);
    // Codificar cada segmento (hay espacios en "PDFs Sellado")
    $partes = array_map('rawurlencode', explode('/', ltrim($rel, '/')));
    return BASE_URL . '/' . implode('/', $partes);
}
