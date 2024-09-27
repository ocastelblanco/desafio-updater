<?php

const BACKUP_FOLDER = __DIR__ . DIRECTORY_SEPARATOR . "backupES" . DIRECTORY_SEPARATOR;
const ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
const NOMBRE_ZIP = "_resource_content.zip";
$dry = false;
if (in_array("--dry", $argv) || in_array("-dry", $argv)) $dry = true;
$gestor = fopen("DesafiosES_CO.csv", "r");
$enc = fgetcsv($gestor);
while (($fila = fgetcsv($gestor)) != FALSE) {
  $origen = ORIGEN . getRutaRecurso($fila[0]) . NOMBRE_ZIP;
  $destino = ORIGEN . getRutaRecurso($fila[1]) . NOMBRE_ZIP;
  $backup = creaDirs(getRutaRecurso($fila[1]), BACKUP_FOLDER) . NOMBRE_ZIP;
  print $origen . " => " . $destino . PHP_EOL;
  print "Creando backup" . PHP_EOL;
  if (!$dry) rename($destino, $backup);
  print "Copiando origen en destino" . PHP_EOL;
  if (!$dry) copy($origen, $destino);
  print PHP_EOL;
}
function getRutaRecurso($id)
{
  return implode(DIRECTORY_SEPARATOR, str_split(str_pad($id, 9, "0", STR_PAD_LEFT), 3)) . DIRECTORY_SEPARATOR;
}
function creaDirs($ruta, $destino)
{
  global $dry;
  $rutaCompleta = rtrim($destino, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($ruta, DIRECTORY_SEPARATOR);
  if (!file_exists($rutaCompleta) && !$dry) mkdir($rutaCompleta, 0777, true);
  return $rutaCompleta;
}
