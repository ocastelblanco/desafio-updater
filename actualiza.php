<?php

// Constantes generales
const  BACKUP_FOLDER = __DIR__ . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR;
//CONST ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
const ORIGEN = __DIR__ . DIRECTORY_SEPARATOR . "origen" . DIRECTORY_SEPARATOR;
const FUENTES = __DIR__ . DIRECTORY_SEPARATOR . "fuentes" . DIRECTORY_SEPARATOR;
const NOMBRE_ZIP = "_resource_content.zip";

// Variables generales
$np = [ // Convierte los nombres de las páginas
  "Escoge tu camino" => "pag04",
  "Ve más allá" => "pag06",
  "Meta" => "pag08",
];
$ns = [ // Convierte los nombres de las secciones de la pag04 únicamente
  "Lista" => "sec01",
  "Enlace" => "sec04_enlaces",
  "Inglés" => "sec04_ingles",
];


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

// Genera el listado de archivos que se usarán como fuente
$listaFuentes = array();
$iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FUENTES));
foreach ($iterador as $fuente) {
  $esPunto = substr(basename($fuente->getPathname()), 0, 1) == ".";
  if (!$esPunto) array_push($listaFuentes, $fuente->getPathname());
}

// Itera entre las fuentes, extrae los recursos a intervenir y crea un listado de cambios editoriales.
$listaCambios = [];
foreach ($listaFuentes as $fuente) {
  $gestor = fopen($fuente, "r");
  $num = 0;
  while (($fila = fgetcsv($gestor, null, ";")) !== false) { // Recorre cada línea del archivo fuente
    if ($num > 0) {
      $id = getRecursoID($fila, $indice);
      $nomPag = $np[$fila[3]];
      if (!array_key_exists($id, $listaCambios)) { // Si no existe el ID como KEY del array, lo crea, junto con la ruta y el array de cambios
        $ruta = ORIGEN . getRutaRecurso($id);
        $listaCambios[$id] = [
          "ruta" => $ruta,
          "cambiosEditoriales" => [],
          "cambiosFijos" => []
        ];
      }
      if (!array_key_exists($nomPag, $listaCambios[$id]["cambiosEditoriales"])) { // Si no existe la página, se crea
        $listaCambios[$id]["cambiosEditoriales"][$nomPag] = [];
      }
      switch ($nomPag) { // Se crean los siguientes niveles de contenido.
        case "pag08": // La pag08, Meta, solo tiene un título. No se aceptan más elementos.
          $listaCambios[$id]["cambiosEditoriales"][$nomPag] = $fila[6];
          break;
        case "pag06": // La pag06, Ve más allá, es un listado de textos
          if (!array_key_exists($nomPag, $listaCambios[$id]["cambiosEditoriales"])) {
            $listaCambios[$id]["cambiosEditoriales"][$nomPag] = [];
          }
          $listaCambios[$id]["cambiosEditoriales"][$nomPag][] = "<li>" . $fila[6] . "</li>";
          break;
        case "pag04": // La pag04 tiene 2 sec: sec01, un listado, y sec04 con dos contenidos: un listado de enlaces y un texto con enlace en inglés
          $nomSec = $ns[$fila[4]];
          if ($nomSec == "sec01") {
            if (!array_key_exists($nomSec, $listaCambios[$id]["cambiosEditoriales"][$nomPag])) {
              $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec] = [];
            }
            $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec][] = "<li>" . $fila[6] . " " . $fila[7] . "</li>";
          }
          if ($nomSec == "sec04_enlaces") {
            if (!array_key_exists($nomSec, $listaCambios[$id]["cambiosEditoriales"][$nomPag])) {
              $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec] = [];
            }
            $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec][] = "<li><a target=\"_blank\" href=\"" . $fila[5] . "\">" . $fila[6] . "</a>" . $fila[7] . "</li>";
          }
          if ($nomSec == "sec04_ingles") {
            $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec] = "<a target=\"_blank\" href=\"" . $fila[5] . "\">" . $fila[6] . "</a>" . $fila[7];
          }
          break;
      }
    }
    $num++;
  }
  fclose($gestor);
}

// Itera la lista de recursos para reemplazar los arrays de pag06 y secciones de pag04 por HTML completo (adiciona la etiqueta <ul>) en los cambios editoriales
foreach ($listaCambios as &$recurso) {
  $recurso["cambiosEditoriales"]["pag06"] = "<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag06"]) . "\n</ul>";
  $recurso["cambiosEditoriales"]["pag04"]["sec01"] = "<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag04"]["sec01"]) . "\n</ul>";
  $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"] = "<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]) . "\n</ul>";
}



print_r($listaCambios);
print PHP_EOL;








/*
$zip = new ZipArchive;
*/

// Funciones
function getRecursoID($fila, $indice)
{
  $titulo = $fila[0];
  $unidad = substr($fila[1], 0, strpos($fila[1], "_Recurso"));
  foreach ($indice as $recurso) {
    //if ($recurso["titulo"] == $titulo && $recurso["unidad"] == $unidad) {
    if ($recurso["titulo"] == $titulo || $recurso["unidad"] == $unidad) {
      return $recurso["id"];
    }
  }
  return null;
}
function getRutaRecurso($id)
{
  return implode(DIRECTORY_SEPARATOR, str_split(str_pad($id, 9, "0", STR_PAD_LEFT), 3)) . DIRECTORY_SEPARATOR . NOMBRE_ZIP;
}
