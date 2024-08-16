<?php

// Constantes generales
const  BACKUP_FOLDER = __DIR__ . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR;
//CONST ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
const ORIGEN = __DIR__ . DIRECTORY_SEPARATOR . "origen" . DIRECTORY_SEPARATOR;
const FUENTES = __DIR__ . DIRECTORY_SEPARATOR . "fuentes" . DIRECTORY_SEPARATOR;
const NOMBRE_ZIP = "_resource_content.zip";
const RUTA_MODELO = __DIR__ . DIRECTORY_SEPARATOR . "modelo" . DIRECTORY_SEPARATOR;
const RUTA_DATOS = RUTA_MODELO . "data" . DIRECTORY_SEPARATOR . "cambios.json";

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

// Genera los cambios fijos para, luego, insertarlos en la estructura de cambios de cada recurso
$cambiosFijos = json_decode(file_get_contents(RUTA_DATOS), true);

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
          "cambiosFijos" => $cambiosFijos
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
// Mueve los cambios al elemento ["replacement"] y crea el ["pattern"] para todas las combinaciones pag / seccion
foreach ($listaCambios as &$recurso) {
  // Para pag06
  $pag06replacement = "<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag06"]) . "\n</ul>";
  $recurso["cambiosEditoriales"]["pag06"] = [];
  $recurso["cambiosEditoriales"]["pag06"]["replacement"] = $pag06replacement;
  $recurso["cambiosEditoriales"]["pag06"]["pattern"] = '/<ul>.*?<\/ul>/s';
  // Para pag08
  $pag08replacement = "$1" . remueveTag($recurso["cambiosEditoriales"]["pag08"], "strong") . "$2";
  $recurso["cambiosEditoriales"]["pag08"] = [];
  $recurso["cambiosEditoriales"]["pag08"]["replacement"] = $pag08replacement;
  $recurso["cambiosEditoriales"]["pag08"]["pattern"] = '/(<h3\s+class="texto-resaltado">).+(<\\/h3>)/s';
  // Para pag04
  // sec01
  $pag04sec01replacement = "$1<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag04"]["sec01"]) . "\n</ul>";
  $recurso["cambiosEditoriales"]["pag04"]["sec01"] = [];
  $recurso["cambiosEditoriales"]["pag04"]["sec01"]["replacement"] = $pag04sec01replacement;
  $recurso["cambiosEditoriales"]["pag04"]["sec01"]["pattern"] = '/(<p>.*<strong>.*Propón.+uno.+o.+escoge.+entre.+los.+siguientes:.*<\/strong>.*<\/p>.*)<ul>.*?<\/ul>/s';
  // sec04_enlaces
  $pag04sec04enlacesReplacement = "$1<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]) . "\n</ul>";
  $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"] = [];
  $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]["replacement"] = $pag04sec04enlacesReplacement;
  $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]["pattern"] = '/(<p>.*<strong>.*Para.+tener.+una.+visión.+general.+sobre.+el.+tema,.+consulta.+estos.+enlaces:.*<\/strong>.*<\/p>).*<ul>.*?<\/ul>/s';
  // sec04_ingles
  $pag04sec04inglesReplacement = "$1" . $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"] . "$2";
  $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"] = [];
  $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"]["replacement"] = $pag04sec04inglesReplacement;
  $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"]["pattern"] = '/(<p>.*<strong>.*Si.+te.+atreves.+con.+el.+inglés,.+consulta.*<\/strong>.*).*?(<\/p>)/s';
}
debug("Se inicia el ciclo de aplicación de cambios" . "\n");
// Itera la lista de recursos y aplica los cambios <------------- Cambios sobre archivos y carpetas
$zip = new ZipArchive;
foreach ($listaCambios as $idRecurso => $cambio) {
  debug("└─ Realizando cambios al recurso $idRecurso" . "\n");
  // Copia el ZIP para tenerlo de backup.
  $destino = creaDirs(getRutaRecurso($idRecurso, false), BACKUP_FOLDER) . NOMBRE_ZIP;
  if (file_exists($destino)) {
    debug(" └─ La copia de seguridad ya existe" . "\n");
  } else {
    copy($cambio["ruta"], $destino);
    debug(" └─ Creando copia de seguridad del ZIP original" . "\n");
  }
  // Se abre el ZIP en la carpeta destino
  $zip->open($cambio["ruta"]);
  // --------- Efectúa los cambios fijos
  // Reemplazos de archivos
  $reemplazar = $cambio["cambiosFijos"]["archivos"]["reemplazar"];
  foreach ($reemplazar as $replace) {
    $idRep = $zip->locateName(ltrim($replace, "/"));
    $filePath = RUTA_MODELO . ltrim($replace, "/");
    $zip->replaceFile($filePath, $idRep);
    debug(" └─ Reemplazando archivo $replace" . "\n");
  }
  // Eliminación de archivos
  $eliminar = $cambio["cambiosFijos"]["archivos"]["eliminar"];
  foreach ($eliminar as $elimina) {
    eliminaPorNombre($zip, $elimina);
  }
  // Cambios de texto en vistas
  $contenidos = $cambio["cambiosFijos"]["contenidos"];
  foreach ($contenidos as $contenido) {
    $rutaCont = $contenido["ruta"] . $contenido["nombre"];
    debug(" └─ Cambiando texto en $rutaCont" . "\n");
    $subject = $zip->getFromName($rutaCont);
    $reempCont = $contenido["reemplazos"];
    $ciclo = 0;
    foreach ($reempCont as $repCont) {
      $ciclo++;
      $subject = preg_replace($repCont["pattern"], $repCont["replacement"], $subject, -1, $countR);
      debug("   └─ Reemplazo $ciclo: $countR" . "\n");
    }
    $zip->deleteName($rutaCont);
    $zip->addFromString($rutaCont, $subject, ZipArchive::OVERWRITE);
  }
  // Se cierra el ZIP.
  $zip->close();
}



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
function getRutaRecurso($id, $conZIP = true)
{
  return implode(DIRECTORY_SEPARATOR, str_split(str_pad($id, 9, "0", STR_PAD_LEFT), 3)) . DIRECTORY_SEPARATOR .
    ($conZIP ? NOMBRE_ZIP : "");
}
function remueveTag($texto, $tag)
{
  $pattern = sprintf('/<%s\b[^>]*>(.*?)<\/%s>/is', preg_quote($tag, '/'), preg_quote($tag, '/'));
  $textoLimpio = preg_replace($pattern, '$1', $texto);
  return $textoLimpio;
}
function creaDirs($ruta, $destino)
{
  $rutaCompleta = rtrim($destino, '/') . '/' . ltrim($ruta, '/');
  if (!file_exists($rutaCompleta)) mkdir($rutaCompleta, 0777, true);
  return $rutaCompleta;
}
function eliminaPorNombre($zip, $extension)
{
  if (str_contains($extension, "*")) {
    $pattern = '/' . preg_quote(trim($extension, '*'), '/') . '$/i';
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $componentes = explode("/", $zip->getNameIndex($i));
      $nombreArchivo = array_pop($componentes);
      if (preg_match($pattern, $nombreArchivo) && $componentes == []) {
        $zip->deleteName($nombreArchivo);
        debug(" └─ Borrando $nombreArchivo" . "\n");
      }
    }
    return;
  }
  debug(" └─ Borrando $extension" . "\n");
  $zip->deleteName($extension);
}
function debug($texto)
{
  $fechaHora = new DateTime();
  $timestampISO = $fechaHora->format(DateTime::ATOM);
  file_put_contents("debug.txt", $timestampISO . " :: " . $texto, FILE_APPEND);
}
