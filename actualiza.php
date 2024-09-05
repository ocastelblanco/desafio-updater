<?php

// Funciones para compatibilidad backward
if (!function_exists('str_contains')) {
  function str_contains($haystack, $needle)
  {
    return $needle !== '' && mb_strpos($haystack, $needle) !== false;
  }
}

date_default_timezone_set('America/Bogota');

$dry = in_array("--dry", $argv);

// Constantes generales
const  BACKUP_FOLDER = __DIR__ . DIRECTORY_SEPARATOR . "backup" . DIRECTORY_SEPARATOR;
//const ORIGEN = "Z:" . DIRECTORY_SEPARATOR . "CPCAN" . DIRECTORY_SEPARATOR . "global_assets" . DIRECTORY_SEPARATOR . "resource" . DIRECTORY_SEPARATOR;
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

debug("Inicia proceso de actualización de Desafios, con " . count($listaFuentes) . " fuentes de ajuste.", -1);

// Itera entre las fuentes, extrae los recursos a intervenir y crea un listado de cambios editoriales.
$listaCambios = [];
foreach ($listaFuentes as $fuente) {
  $countCambios = count($listaCambios);
  $gestor = fopen($fuente, "r");
  $num = 0;
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
      $id = getRecursoID($fila, $indice);
      if (null === $id) {
        debug("ERROR: No se encontraron en el índice los datos de la fuente " . $fila["titulo"] . " | " . $fila["unidad"] . " | " . $fila["id"] . ".", 1);
      }
      $nomPag = $np[$fila[$col["pag"]]];
      if (!array_key_exists($id, $listaCambios)) { // Si no existe el ID como KEY del array, lo crea, junto con la ruta y el array de cambios
        $ruta = ORIGEN . getRutaRecurso($id);
        $listaCambios[$id] = [
          "ruta" => $ruta,
          "cambiosEditoriales" => [],
          "cambiosFijos" => $cambiosFijos,
          "fuente" => $fuente,
        ];
      }
      if (!array_key_exists($nomPag, $listaCambios[$id]["cambiosEditoriales"])) { // Si no existe la página, se crea
        $listaCambios[$id]["cambiosEditoriales"][$nomPag] = [];
      }
      switch ($nomPag) { // Se crean los siguientes niveles de contenido.
        case "pag08": // La pag08, Meta, solo tiene un título. No se aceptan más elementos.
          $listaCambios[$id]["cambiosEditoriales"][$nomPag] = $fila[$col["principal"]];
          break;
        case "pag06": // La pag06, Ve más allá, es un listado de textos
          if (!array_key_exists($nomPag, $listaCambios[$id]["cambiosEditoriales"])) {
            $listaCambios[$id]["cambiosEditoriales"][$nomPag] = [];
          }
          $listaCambios[$id]["cambiosEditoriales"][$nomPag][] = "<li>" . $fila[$col["principal"]] . "</li>";
          break;
        case "pag04": // La pag04 tiene 2 sec: sec01, un listado, y sec04 con dos contenidos: un listado de enlaces y un texto con enlace en inglés
          $nomSec = $ns[trim($fila[$col["tipo"]])];
          if ($nomSec == "sec01") {
            if (!array_key_exists($nomSec, $listaCambios[$id]["cambiosEditoriales"][$nomPag])) {
              $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec] = [];
            }
            $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec][] = "<li>" . $fila[$col["principal"]] . " " . $fila[$col["adicional"]] . "</li>";
          }
          if ($nomSec == "sec04_enlaces") {
            if (!array_key_exists($nomSec, $listaCambios[$id]["cambiosEditoriales"][$nomPag])) {
              $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec] = [];
            }
            $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec][] = "<li><a target=\"_blank\" href=\"" . $fila[$col["enlace"]] . "\">" . $fila[$col["principal"]] . "</a>" . $fila[$col["adicional"]] . "</li>";
          }
          if ($nomSec == "sec04_ingles") {
            $listaCambios[$id]["cambiosEditoriales"][$nomPag][$nomSec] = "<a target=\"_blank\" href=\"" . $fila[$col["enlace"]] . "\">" . $fila[$col["principal"]] . "</a>" . $fila[$col["adicional"]];
          }
          break;
      }
    }
    $num++;
  }
  fclose($gestor);
  debug($fuente . ": " . (count($listaCambios) - $countCambios) . " recursos a intervenir", 0);
}

// Itera la lista de recursos para reemplazar los arrays de pag06 y secciones de pag04 por HTML completo (adiciona la etiqueta <ul>) en los cambios editoriales
// Mueve los cambios al elemento ["replacement"] y crea el ["pattern"] para todas las combinaciones pag / seccion
foreach ($listaCambios as &$recurso) {
  // Para pag06
  if (array_key_exists("pag06", $recurso["cambiosEditoriales"])) { // Si existe la pag "pag06"
    $pag06replacement = "<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag06"]) . "\n</ul>";
    $recurso["cambiosEditoriales"]["pag06"] = [];
    $recurso["cambiosEditoriales"]["pag06"]["replacement"] = $pag06replacement;
    $recurso["cambiosEditoriales"]["pag06"]["pattern"] = '/<ul>.*?<\/ul-{0,2}>/s';
  } else {
    debug("ADVERTENCIA: No existe ajuste editorial para la página Ve más allá del recurso " . $recurso["ruta"], 2);
  }
  // Para pag08, se tienen que crear 2 <h3 class="texto-resaltado">
  if (array_key_exists("pag08", $recurso["cambiosEditoriales"])) { // Si existe la pag "pag08"
    $pag08replacement = preg_replace(
      '/(.*)\n?(¿Serás[^\?]+\?)/i',
      "<h3 class=\"texto-resaltado\">$1</h3>\n<h3 class=\"texto-resaltado\">$2</h3>",
      remueveTag($recurso["cambiosEditoriales"]["pag08"], "strong")
    );
    $recurso["cambiosEditoriales"]["pag08"] = [];
    $recurso["cambiosEditoriales"]["pag08"]["replacement"] = $pag08replacement;
    $recurso["cambiosEditoriales"]["pag08"]["pattern"] = '/<h3\s+class="texto-resaltado">.*?<\/h3>(\s*<h3\s+class="texto-resaltado">.*?<\/h3>)?/s';
  } else {
    debug("ADVERTENCIA: No existe ajuste editorial para la página Meta del recurso " . $recurso["ruta"], 2);
  }
  // Para pag04
  // sec01
  if (array_key_exists("sec01", $recurso["cambiosEditoriales"]["pag04"])) { // Si existe la clave "sec01"
    $pag04sec01replacement = "$1<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag04"]["sec01"]) . "\n</ul>";
    $recurso["cambiosEditoriales"]["pag04"]["sec01"] = [];
    $recurso["cambiosEditoriales"]["pag04"]["sec01"]["replacement"] = $pag04sec01replacement;
    $recurso["cambiosEditoriales"]["pag04"]["sec01"]["pattern"] = '/(<p>\s*<strong>\s*(Propón|Inspírate)[^:]*:\s*<\/strong>\s*<\/p>\s*)<ul>.*?<\/ul>/s';
  } else {
    debug("ADVERTENCIA: No existe ajuste editorial en la sección Lista de Escoge tu camino del recurso " . $recurso["ruta"], 2);
  }
  // sec04_enlaces
  if (array_key_exists("sec04_enlaces", $recurso["cambiosEditoriales"]["pag04"])) { // Si existe la clave "sec04_enlaces"
    $pag04sec04enlacesReplacement = "$1<ul>\n" . implode("\n", $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]) . "\n</ul>";
    $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"] = [];
    $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]["replacement"] = $pag04sec04enlacesReplacement;
    $recurso["cambiosEditoriales"]["pag04"]["sec04_enlaces"]["pattern"] = '/(<h3\s*class="texto-resaltado">¿Qu.*?Internet\?<\/h3>\s*<div\s*class="texto">\s*<p>\s*(<strong>)?\s*[^:]+:\s*(<\/strong>)?\s*<\/p>\s*)<ul>.*?<\/ul>/s';
  } else {
    debug("ADVERTENCIA: No existe ajuste editorial en la sección Enlaces de Escoge tu camino del recurso " . $recurso["ruta"], 2);
  }
  // sec04_ingles 
  if (array_key_exists("sec04_ingles", $recurso["cambiosEditoriales"]["pag04"])) { // Si existe la clave "sec04_ingles"
    $pag04sec04inglesReplacement = "$1" . $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"] . "$2";
    $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"] = [];
    $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"]["replacement"] = $pag04sec04inglesReplacement;
    $recurso["cambiosEditoriales"]["pag04"]["sec04_ingles"]["pattern"] = '/(<p>\s*(<strong>)?\s*Si\s+te\s+atreves\s+con\s+[^<]*(<\/strong>)?\s*).*?(<\/p>)/s';
  } else {
    debug("ADVERTENCIA: No existe ajuste editorial en la sección Inglés de Escoge tu camino del recurso " . $recurso["ruta"], 2);
  }
}

// Itera la lista de recursos y aplica los cambios <------------- Cambios sobre archivos y carpetas
debug("Se inicia el ciclo de aplicación de " . count($listaCambios) . " cambios en total.", -1);
$zip = new ZipArchive;
$conteo = 0;
foreach ($listaCambios as $idRecurso => $cambio) {
  $identificador = findPorID($idRecurso, $indice);
  debug("Realizando cambios al asset $identificador", 1);
  $rutaRecursoZIP = ORIGEN . getRutaRecurso($idRecurso, true);
  if (!file_exists($rutaRecursoZIP)) {
    debug("ERROR: No existe el recurso en la ruta $rutaRecursoZIP. Se continuará con el siguiente recurso.", 1);
    continue;
  }
  // Copia el ZIP para tenerlo de backup.
  $destino = creaDirs(getRutaRecurso($idRecurso, false), BACKUP_FOLDER) . NOMBRE_ZIP;
  debug("Creando copia de seguridad del archivo $destino", 2);
  if (file_exists($destino)) {
    debug("No se creó una copia de seguridad, ya existe.", 3);
  } else {
    if (!$dry) copy($cambio["ruta"], $destino);
    debug("Se creó una copia de seguridad del ZIP original.", 3);
  }
  // Se abre el ZIP en la carpeta destino
  $zip->open($cambio["ruta"]);
  // --------- Efectúa los cambios fijos
  debug("Realizando cambios comunes", 2);
  // Reemplazos de archivos
  $reemplazar = $cambio["cambiosFijos"]["archivos"]["reemplazar"];
  debug("Se reemplazarán " . count($reemplazar) . " archivos en el ZIP original.", 3);
  foreach ($reemplazar as $replace) {
    $nomArchRemp = ltrim($replace, "/");
    $idRep = $zip->locateName($nomArchRemp);
    $filePath = RUTA_MODELO . $nomArchRemp;
    $seReemp = true;
    if (!$dry) {
      // $seReemp = $zip->replaceFile($filePath, $idRep); --> solo para versiones PHP 8+ con PECL zip correctamente instaladas
      //$seEliminaParaRemp = $zip->deleteIndex($idRep);
      $seReemp = $zip->addFile($filePath, $nomArchRemp);
    }
    debug("Reemplazando archivo $replace: " . ($seReemp ? "OK" : "ERROR"), 4);
  }
  // Eliminación de archivos
  $eliminar = $cambio["cambiosFijos"]["archivos"]["eliminar"];
  debug("Se eliminarán " . count($eliminar) . " patrones de archivos en el ZIP.", 3);
  foreach ($eliminar as $elimina) eliminaPorNombre($zip, $elimina);
  // Cambios fijos de texto en vistas
  $contenidos = $cambio["cambiosFijos"]["contenidos"];
  debug("Se reemplazarán textos en " . count($contenidos) . " vistas.", 3);
  foreach ($contenidos as $contenido) {
    $rutaCont = $contenido["ruta"] . $contenido["nombre"];
    debug("Cambiando texto en $rutaCont.", 4);
    $subject = $zip->getFromName($rutaCont);
    $reempCont = $contenido["reemplazos"];
    $ciclo = 0;
    debug("Se harán " . count($reempCont) . " reemplazos de texto.", 5);
    foreach ($reempCont as $repCont) {
      $ciclo++;
      $subject = preg_replace($repCont["pattern"], $repCont["replacement"], $subject, -1, $countR);
      debug("Reemplazando texto número $ciclo: " . ($countR == 1 ? "OK" : "ERROR"), 6);
      if ($rutaCont == "views/pag06.html" && $ciclo == 3 && $countR != 1) { // Ajuste manual: algunas pag06 tienen un texto final diferente.
        $reem = '/(<p>\s*<strong>\s*)Además,.*?<em>Refuerza tu aprendizaje\.?<\/em>\.?(<\/strong><\/p>)/s';
        $subject = preg_replace(
          $reem,
          $repCont["replacement"],
          $subject,
          -1,
          $countR
        );
        debug("Reemplazando texto número $ciclo (segundo patrón): " . ($countR == 1 ? "OK" : "ERROR"), 6);
      }
    }
    debug("Se reemplaza el HTML original con el HTML resultado de los cambios.", 5);
    if (!$dry) $zip->deleteName($rutaCont);
    if (!$dry) $zip->addFromString($rutaCont, $subject); //, ZipArchive::OVERWRITE); --> Solo para PHP 8+
  }
  // Cambios editoriales de texto en vistas
  $zip->close(); // Se cierra el ZIP porque PHP 7 no puede mantener la info persistente
  $zip->open($cambio["ruta"]); // Vuelve y se abre
  $contenidosEd = $cambio["cambiosEditoriales"];
  debug("Se harán cambios editoriales en " . count($contenidosEd) . " vistas", 3);
  foreach ($contenidosEd as $pag => $editorial) {
    $rutaCont = "views/$pag.html";
    debug("Cambiando texto editorial en $rutaCont.", 4);
    $subject = $zip->getFromName($rutaCont);
    if ($pag != "pag04") {
      $subject = preg_replace($editorial["pattern"], $editorial["replacement"], $subject, -1, $countC);
      debug("Reemplazo editorial: " . ($countC == 1 ? "OK" : "ERROR"), 5);
    } else {
      debug("Se harán " . count($editorial) . " reemplazos editoriales en la vista $rutaCont.", 5);
      foreach ($editorial as $sec => $cambioPag04) {
        if ($subject && $subject != "") {
          $subject = preg_replace($cambioPag04["pattern"], $cambioPag04["replacement"], $subject, -1, $countC);
          debug("Reemplazo editorial en la sección $sec: " . ($countC == 1 ? "OK" : "ERROR"), 6);
        } else {
          debug("ERROR: Se destruyó el contenido de la pag04 en el recurso $identificador. Se recomienda restaurarlo y ajustarlo manualmente.", 3);
        }
      }
    }
    if (!$dry) $zip->deleteName($rutaCont);
    if (!$dry) $zip->addFromString($rutaCont, $subject); //, ZipArchive::OVERWRITE); --> Solo para PHP 8+
  }
  // Se cierra el ZIP.
  $zip->close();
  debug("         ", -1);
  $conteo++;
  //if ($conteo >= 5) exit;
}



// Funciones
function getRecursoID($fila, $indice)
{
  global $col;
  $titulo = trim(strtolower($fila[$col["titulo"]]));
  $unidad = trim(strtolower(preg_replace('/_Recurso\d{2,4}/s', "", $fila[$col["unidad"]])));
  $id = $fila[$col["id"]];
  foreach ($indice as $recurso) {
    $recTitulo = trim(strtolower($recurso["titulo"]));
    $recUnidad = trim(strtolower($recurso["unidad"]));
    if ($id == $recurso["id"]) {
      if ($recUnidad != $unidad) debug("ERROR: No coincide el nombre de unidad de la fuente $unidad con el del índice $recUnidad.", 1);
      if ($recTitulo != $titulo) debug("ERROR: No coincide el nombre del asset de la fuente $titulo con el del índice $recTitulo.", 1);
    }
    if ($recTitulo == $titulo && $recUnidad == $unidad) {
      // También debe coincidir $recurso["id"] con $id
      if ($id != $recurso["id"]) debug("ADVERTENCIA: No coincide el ID de la fuente $id con el del índice " . $recurso["indice"], 1);
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
  global $dry;
  $rutaCompleta = rtrim($destino, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($ruta, DIRECTORY_SEPARATOR);
  if (!file_exists($rutaCompleta) && !$dry) mkdir($rutaCompleta, 0777, true);
  return $rutaCompleta;
}
function eliminaPorNombre($zip, $extension)
{
  global $dry;
  if (str_contains($extension, "*")) {
    $pattern = '/' . preg_quote(trim($extension, '*'), '/') . '$/i';
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $componentes = explode("/", $zip->getNameIndex($i));
      $nombreArchivo = array_pop($componentes);
      if (preg_match($pattern, $nombreArchivo) && $componentes == []) {
        $seElimino = true;
        if (!$dry) $zip->deleteName($nombreArchivo);
        debug("Borrando $nombreArchivo: " . ($seElimino ? "OK" : "ERROR"), 4);
      }
    }
    return;
  }
  $borraExt = true;
  if (!$dry) $zip->deleteName($extension);
  debug("Borrando $extension: " . ($borraExt ? "OK" : "ERROR"), 4);
}
function debug($texto, $nivel)
{
  $fechaHora = new DateTime();
  $timestampISO = $fechaHora->format(DateTime::ATOM);
  $salida = $timestampISO . (($nivel < 0) ? " " : str_repeat(" ", $nivel) . " └─ ") . $texto . PHP_EOL;
  file_put_contents("debug.txt", $salida, FILE_APPEND);
  if ($nivel < 4) print $salida;
}
function findPorID($id, $array)
{
  $resp = null;
  foreach ($array as $el) {
    if ($el["id"] == $id) $resp = "ID: " . $el["id"] . " | TITULO: " . $el["titulo"] . " | UNIDAD: " . $el["unidad"];
  }
  return $resp;
}
