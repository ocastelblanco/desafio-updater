<?php

//const ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
const ORIGEN = __DIR__ . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR;
const NOMBRE_ZIP = "_resource_content.zip";

$listaID = [
  "21930",
  "23155",
  "23244",
  "23341",
];
if (!file_exists("extraidos")) mkdir("extraidos");
foreach ($listaID as $id) {
  debug("Inicia extracción de $id.", -1);
  debug(extraerHtmlYGenerarCSV($id), 0);
}

// Funciones
function extraerHtmlYGenerarCSV($id)
{
  $ruta = ORIGEN . getRutaRecurso($id);
  if (!file_exists($ruta)) {
    return "ERROR: el ZIP no se encuentra en la ruta especificada.";
  }
  $zip = new ZipArchive;
  if ($zip->open($ruta) === TRUE) {
    $csvFile = __DIR__ . DIRECTORY_SEPARATOR . "extraidos" . DIRECTORY_SEPARATOR . "$id.csv";
    $fileHandle = fopen($csvFile, 'w');
    fwrite($fileHandle, "\xEF\xBB\xBF");  // Agregar BOM para indicar UTF-8
    fputcsv($fileHandle, ['Ruta', 'Pag', 'XPath', 'Texto']);
    for ($i = 1; $i <= 8; $i++) {
      $htmlFile = 'views/pag0' . $i . '.html';
      procesarHtmlDelZip($zip, $htmlFile, $ruta, $fileHandle);
    }
    procesarHtmlDelZip($zip, 'views/portada.html', $ruta, $fileHandle);
    fclose($fileHandle);
    $zip->close();
    return "Extraido correctamente.";
  } else {
    return "ERROR: No se pudo abrir el ZIP.";
  }
}
function procesarHtmlDelZip($zip, $htmlFile, $ruta, $fileHandle)
{
  $htmlContent = $zip->getFromName($htmlFile);
  if ($htmlContent === false) return;
  $htmlContent = mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8');
  $dom = new DOMDocument();
  @$dom->loadHTML($htmlContent); // El @ evita los errores de HTML malformado
  $xpath = new DOMXPath($dom);
  $textNodes = $xpath->query('//text()');
  foreach ($textNodes as $node) {
    $text = trim($node->nodeValue);
    if ($text !== '') {
      $nodeXPath = obtenerXPath($node);
      fputcsv($fileHandle, [$ruta, $htmlFile, $nodeXPath, $text]);
    }
  }
}
function obtenerXPath($node)
{
  $dom = $node->ownerDocument;
  $xpath = '';
  while ($node !== $dom->documentElement) {
    $position = 1;
    $sibling = $node;
    while ($sibling = $sibling->previousSibling) {
      if ($sibling->nodeType == XML_ELEMENT_NODE && $sibling->nodeName == $node->nodeName) {
        $position++;
      }
    }
    $xpathSegment = $node->nodeName;
    if ($node->nodeType === XML_TEXT_NODE) {
      $xpathSegment = 'text()';
    } elseif ($position > 1) {
      $xpathSegment .= "[$position]";
    }
    $xpath = '/' . $xpathSegment . $xpath;
    $node = $node->parentNode;
  }
  return '/' . $dom->documentElement->nodeName . $xpath;
}
function getRutaRecurso($id)
{
  return implode(DIRECTORY_SEPARATOR, str_split(str_pad($id, 9, "0", STR_PAD_LEFT), 3)) . DIRECTORY_SEPARATOR . NOMBRE_ZIP;
}
function debug($texto, $nivel)
{
  $fechaHora = new DateTime();
  $timestampISO = $fechaHora->format(DateTime::ATOM);
  $salida = $timestampISO . (($nivel < 0) ? " " : str_repeat(" ", $nivel) . " └─ ") . $texto . PHP_EOL;
  file_put_contents("debug_extraccion.txt", $salida, FILE_APPEND);
  if ($nivel < 4) print $salida;
}
