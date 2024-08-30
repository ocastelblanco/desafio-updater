<?php

// Funciones para compatibilidad backward
if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle)
  {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
  }
}

// Constantes generales
const  BACKUP_FOLDER = __DIR__ . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR;
//const ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
const ORIGEN = __DIR__ . DIRECTORY_SEPARATOR . "origen" . DIRECTORY_SEPARATOR;
const FUENTES = __DIR__ . DIRECTORY_SEPARATOR . "fuentes" . DIRECTORY_SEPARATOR;
const NOMBRE_ZIP = "_resource_content.zip";

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

$listaOrigen = [];
$listaDestino = [];
$iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BACKUP_FOLDER));
foreach ($iterador as $archivo) {
  $esPunto = substr(basename($archivo->getPathname()), 0, 1) == ".";
  if (!$esPunto) {
    $origen = $archivo->getPathname();
    array_push($listaOrigen, $origen);
    $destino = ORIGEN . str_replace(BACKUP_FOLDER, "", $origen);
    array_push($listaDestino, $destino);
  }
}

if (isset($_VAR["all"])) {
  print "Se restaurarán " . count($listaOrigen) . " archivos." . PHP_EOL;
  for ($i = 0; $i < count($listaDestino); $i++) {
    $origen = $listaOrigen[$i];
    $destino = $listaDestino[$i];
    restaurando($origen, $destino, $i, count($listaDestino));
  }
} else {
  if (isset($_VAR["fuente"])) {
    $listaRecs = leerArchivoLista($_VAR["fuente"]);
    $indice = indice();
    $listaRest = [];
    foreach ($listaRecs as $num => $id) {
      if ($recurso = findPorID($id, $indice)) {
        $listaRest[] = BACKUP_FOLDER . getRutaRecurso($recurso["id"]);
      } else {
        print "El recurso $id de la línea $num del archivo " . $_VAR["fuente"] . " no es un Desafío. No se tendrá en cuenta para la restauración." . PHP_EOL;
      }
    }
    print "Se restaurarán " . count($listaRest) . " recursos en total." . PHP_EOL;
    foreach ($listaRest as $numR => $origen) {
      $destino = ORIGEN . str_replace(BACKUP_FOLDER, "", $origen);
      if (file_exists($destino) && file_exists($origen)) {
        restaurando($origen, $destino, $numR, count($listaRest));
      } else {
        if (!file_exists($origen)) {
          print "El recurso $id de la línea $num del archivo " . $_VAR["fuente"] . " con ruta $origen, no existe. No se restaurará." . PHP_EOL;
        }
        if (!file_exists($destino)) {
          print "El recurso $id de la línea $num del archivo " . $_VAR["fuente"] . " con ruta $origen, no existe. No se restaurará." . PHP_EOL;
        }
      }
    }
  }
}



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
