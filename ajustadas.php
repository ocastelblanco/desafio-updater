<?php

// Funciones para compatibilidad backward
if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle)
  {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
  }
}

const FUENTES = __DIR__ . DIRECTORY_SEPARATOR . "fuentes" . DIRECTORY_SEPARATOR;

date_default_timezone_set('America/Bogota');
// Almacena las variables enviadas por CLI en $_VAR
$_VAR = [];
foreach ($argv as $pos => $arg) {
  if ($pos == 0) continue;
  if (substr($arg, 0, 2) == '--') {
    $key = explode("=", substr($arg, 2))[0];
    $val = explode("=", substr($arg, 2))[1];
    $_VAR[$key] = $val;
  } elseif (substr($arg, 0, 1) == '-') {
    $key = substr($arg, 1);
    $_VAR[$key] = true;
  }
}

$col = [
  "titulo" => 0,
  "unidad" => 1,
  "id" => 2,
  "pag" => 3,
  "tipo" => 4,
  "enlace" => 5,
  "principal" => 6,
  "adicional" => 7,
];

$listaFuentes = fuentes();
$indice = indice();
$listaUnidades = [];

print "Se encontraron " . count($listaFuentes) . " archivos fuente." . PHP_EOL;
foreach ($listaFuentes as $fuente) {
  print "Abriendo $fuente" . PHP_EOL;
  $gestor = fopen($fuente, "r");
  $num = 0;
  while (($fila = fgetcsv($gestor, null, ";")) !== false) { // Recorre cada línea del archivo fuente
    if ($num < 1) { // Lee el encabezado y encuentra los valores de columna
      foreach ($fila as $nC => $nomCol) {
        switch ($nomCol) {
          case "Nombre recurso":
            $col["titulo"] = $nC;
            break;
          case "identificadorUnidad":
            $col["unidad"] = $nC;
            break;
          case "asset_id":
            $col["id"] = $nC;
            break;
          case "Página":
            $col["pag"] = $nC;
            break;
          case "Tipo":
            $col["tipo"] = $nC;
            break;
          case "Enlace":
            $col["enlace"] = $nC;
            break;
          case "Texto principal":
            $col["principal"] = $nC;
            break;
          case "Texto adicional":
            $col["adicional"] = $nC;
            break;
        }
      }
    } else {
      $unidad = getUnidad($fila, $indice);
      if (!in_array($unidad, $listaUnidades)) array_push($listaUnidades, $unidad);
    }
    $num++;
  }
  fclose($gestor);
}
print "Se incluirán " . count($listaUnidades) . " unidades en el listado." . PHP_EOL;
$gestorCSV = fopen("lista_unidades.csv", "w");
fputcsv($gestorCSV, ["unit_code"]);
foreach ($listaUnidades as $unidad) {
  fputcsv($gestorCSV, [$unidad]);
}
fclose($gestorCSV);
print "Listado de unidades ajustadas terminado." . PHP_EOL;

// Funciones
function leerArchivoLista($archivo)
{
  if (file_exists($archivo)) {
    $contenido = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return $contenido;
  } else {
    return [];
  }
}
function indice()
{
  // Genera el índice de Desafíos
  $indice = [];
  $gestor = fopen("lista_desafios.csv", "r");
  $num = 0;
  while (($fila = fgetcsv($gestor, null, ";")) !== false) {
    if ($num > 0) {
      $indice[] = [
        "id" => $fila[0],
        "titulo" => $fila[1],
        "unidad" => $fila[2],
      ];
    }
    $num++;
  }
  fclose($gestor);
  return $indice;
}
function findPorID($id, $array)
{
  if (str_contains($id, "Recurso")) $id = preg_replace('/([A-Z]{2}.*)_Recurso\d{2,4}/', "$1", $id);
  foreach ($array as $el) {
    if ($el["id"] == $id || $el["titulo"] == $id || $el["unidad"] == $id) return $el;
  }
  return null;
}
function getRutaRecurso($id, $conZIP = true)
{
  return implode(DIRECTORY_SEPARATOR, str_split(str_pad($id, 9, "0", STR_PAD_LEFT), 3)) . DIRECTORY_SEPARATOR .
    ($conZIP ? NOMBRE_ZIP : "");
}
function restaurando($origen, $destino, $num, $numTotal)
{
  global $_VAR;
  print "Reemplazando archivo " . ($num + 1) . " de $numTotal:" . PHP_EOL . "   FROM: $origen" . PHP_EOL . "   TO: $destino" . PHP_EOL;
  if (!isset($_VAR["dry"])) copy($origen, $destino);
}
function fuentes()
{
  // Genera el listado de archivos que se usarán como fuente
  $listaFuentes = [];
  $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FUENTES));
  foreach ($iterador as $fuente) {
    $esPunto = substr(basename($fuente->getPathname()), 0, 1) == ".";
    if (!$esPunto) array_push($listaFuentes, $fuente->getPathname());
  }
  return $listaFuentes;
}
function getRecursoID($fila, $indice)
{
  global $col;
  $titulo = trim(strtolower($fila[$col["titulo"]]));
  $unidad = trim(strtolower(preg_replace('/_Recurso\d{2,4}/s', "", $fila[$col["unidad"]])));
  $id = $fila[$col["id"]];
  foreach ($indice as $recurso) {
    $recTitulo = trim(strtolower($recurso["titulo"]));
    $recUnidad = trim(strtolower($recurso["unidad"]));
    if ($recTitulo == $titulo && $recUnidad == $unidad) return $recurso["id"];
  }
  return null;
}
function getUnidad($fila, $indice)
{
  global $col;
  $titulo = trim(strtolower($fila[$col["titulo"]]));
  $unidad = trim(strtolower(preg_replace('/_Recurso\d{2,4}/s', "", $fila[$col["unidad"]])));
  $id = $fila[$col["id"]];
  foreach ($indice as $recurso) {
    $recTitulo = trim(strtolower($recurso["titulo"]));
    $recUnidad = trim(strtolower($recurso["unidad"]));
    if ($recTitulo == $titulo && $recUnidad == $unidad) return $recurso["unidad"];
  }
  return null;
}
