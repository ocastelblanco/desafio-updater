<?php
const NOMBRE_ZIP = "_resource_content.zip";
//const ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
const ORIGEN = __DIR__ . DIRECTORY_SEPARATOR . "origen" . DIRECTORY_SEPARATOR;
const RUTA_CSS = "css/app.css";
$tr = [
  "FQ" => "FQ",
  "BG" => "BG",
  "MN" => "BG",
  "GH" => "GH",
  "MS" => "GH",
];
$dry = in_array("--dry", $argv) || in_array("-dry", $argv);
$la = [];
$gestor = fopen("lista_desafios.csv", "r");
while ($linea = fgetcsv($gestor, null, ";")) {
  if ($linea[3] == "España") {
    $ma = $tr[substr($linea[2], 0, 2)];
    $la[] =
      [
        "id" => $linea[0],
        "ruta" => ORIGEN . getRutaRecurso($linea[0]),
        "area" => $ma,
        "css" => file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "materia" . DIRECTORY_SEPARATOR . $ma . DIRECTORY_SEPARATOR . "app.css"),
      ];
  }
}
fclose($gestor);
debug("Se inicia el ajuste de " . count($la) . " recursos.", -1);
foreach ($la as $nm => $as) {
  debug("Reemplazando estilo " . ($nm + 1) . " de " . count($la) . ": " . $as["ruta"], 0);
  if (!file_exists($as["ruta"])) {
    debug("ERROR: El archivo ZIP no existe.", 1);
    continue;
  } else {
    debug("El archivo ZIP existe en el filesystem. Se iniciará el proceso de eliminación y reemplazo del CSS.", 1);
  }
  $zip = new ZipArchive;
  $zip->open($as["ruta"]);
  if ($zip->locateName(RUTA_CSS) == false) {
    debug("ERROR: No se encuentra el CSS a reemplazar.", 1);
    continue;
  }
  if (!$dry) {
    if ($zip->deleteName(RUTA_CSS)) {
      debug("Archivo origen eliminado correctamente.", 2);
      if ($zip->addFromString(RUTA_CSS, $as["css"])) {
        debug("CSS final insertado correctamente.", 2);
      } else {
        debug("ERROR: No se pudo insertar el CSS directamente al ZIP.", 2);
      }
    } else {
      debug("ERROR: No se pudo eliminar el CSS original del ZIP.", 2);
    }
  }
  $zip->close();
}

// Funciones
function getRutaRecurso($id, $conZIP = true)
{
  return implode(DIRECTORY_SEPARATOR, str_split(str_pad($id, 9, "0", STR_PAD_LEFT), 3)) . DIRECTORY_SEPARATOR . ($conZIP ? NOMBRE_ZIP : "");
}
function debug($texto, $nivel)
{
  $fechaHora = new DateTime();
  $timestampISO = $fechaHora->format(DateTime::ATOM);
  $salida = $timestampISO . (($nivel < 0) ? " " : str_repeat(" ", $nivel) . " └─ ") . $texto . PHP_EOL;
  file_put_contents("debug_restauracion_css.txt", $salida, FILE_APPEND);
  if ($nivel < 4) print $salida;
}
