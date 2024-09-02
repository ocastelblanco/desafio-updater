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

$indice = indice();

$listaOrigen = [];
$listaDestino = [];
$listaUnidades = [];
$iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BACKUP_FOLDER));
foreach ($iterador as $archivo) {
  $esPunto = substr(basename($archivo->getPathname()), 0, 1) == ".";
  if (!$esPunto) {
    $origen = $archivo->getPathname();
    array_push($listaOrigen, $origen);
    $destino = ORIGEN . str_replace(BACKUP_FOLDER, "", $origen);
    array_push($listaDestino, $destino);
    $id = str_replace(BACKUP_FOLDER, "", $origen);
    $id = str_replace(NOMBRE_ZIP, "", $id);
    $id = implode("", explode("/", $id));
    $id = preg_replace('/^0+/s', "", $id);
    $recurso = findPorID($id, $indice);
    array_push($listaUnidades, $recurso["unidad"]);
  }
}
$gestorCSV = fopen("unidades_restauradas.csv", "w");
fputcsv($gestorCSV, ["unit_code"]);
if (isset($_VAR["all"])) {
  debug("Se activó la opción ALL. Se restaurarán " . count($listaOrigen) . " archivos.", -1);
  for ($i = 0; $i < count($listaDestino); $i++) {
    $origen = $listaOrigen[$i];
    $destino = $listaDestino[$i];
    restaurando($origen, $destino, $i, count($listaDestino));
    fputcsv($gestorCSV, [$listaUnidades[$i]]);
  }
} else {
  if (isset($_VAR["fuente"])) {
    $listaRecs = leerArchivoLista($_VAR["fuente"]);
    $listaRest = [];
    foreach ($listaRecs as $num => $id) {
      if ($recurso = findPorID($id, $indice)) {
        $listaRest[] = BACKUP_FOLDER . getRutaRecurso($recurso["id"]);
        $listaUnidades[] = $recurso["unidad"];
      } else {
        debug("ERROR: El recurso $id de la línea $num del archivo " . $_VAR["fuente"] . " no es un Desafío. No se tendrá en cuenta para la restauración.", 1);
      }
    }
    debug("Se restaurarán " . count($listaRest) . " recursos en total.", 0);
    foreach ($listaRest as $numR => $origen) {
      $destino = ORIGEN . str_replace(BACKUP_FOLDER, "", $origen);
      if (file_exists($destino) && file_exists($origen)) {
        restaurando($origen, $destino, $numR, count($listaRest));
        fputcsv($gestorCSV, [$listaUnidades[$i]]);
      } else {
        debug("ERROR: El recurso $id de la línea $num del archivo " . $_VAR["fuente"] . " no se restaurará.", 1);
        if (!file_exists($origen)) {
          debug("La ruta origen $origen, no existe.", 2);
        }
        if (!file_exists($destino)) {
          debug("La ruta destino $destino, no existe.", 2);
        }
      }
    }
  }
}
fclose($gestorCSV);

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
  debug("Reemplazando archivo " . ($num + 1) . " de $numTotal.", 1);
  debug("FROM: $origen - TO: $destino", 2);
  if (!isset($_VAR["dry"])) copy($origen, $destino);
}
function debug($texto, $nivel)
{
  $fechaHora = new DateTime();
  $timestampISO = $fechaHora->format(DateTime::ATOM);
  $salida = $timestampISO . (($nivel < 0) ? " " : str_repeat(" ", $nivel) . " └─ ") . $texto . PHP_EOL;
  file_put_contents("debug_restauracion.txt", $salida, FILE_APPEND);
  if ($nivel < 4) print $salida;
}
