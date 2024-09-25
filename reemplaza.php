<?php
error_reporting(E_ERROR | E_PARSE);
const ORIGEN = __DIR__ . DIRECTORY_SEPARATOR . "extraidos" . DIRECTORY_SEPARATOR;
if (in_array("--dry", $argv) || in_array("-dry", $argv)) {
	$dry = true;
} else {
	$dry = false;
}
debug("Se inicia el proceso de reemplazo de nodos de texto" . ($dry ? " en modo DRY." : "."), -1);
$listaAssets = [];
$iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ORIGEN));
foreach ($iterador as $archivo) {
	$esPunto = substr(basename($archivo->getPathname()), 0, 1) == ".";
	if (!$esPunto) $listaAssets[] = $archivo->getPathname();
}
debug("Se reemplazarán " . count($listaAssets) . " assets en total.", -1);
foreach ($listaAssets as $num => $csvFile) {
	$ruta = explode(DIRECTORY_SEPARATOR, $csvFile);
	$file = array_pop($ruta);
	$id = explode(".", $file)[0];
	debug("Se inicia el reemplazo de " . ($num + 1) . "/" . count($listaAssets) . " ID: $id", 0);
	reemplazar($csvFile);
}


// Funciones
function reemplazar($csvFile)
{
	global $dry;
	if (($handle = fopen($csvFile, "r")) !== FALSE) {
		$header = fgetcsv($handle);
		$numFila = 1;
		while (($data = fgetcsv($handle)) !== FALSE) {
			list($zipPath, $htmlFile, $nodeXPath, $newText) = $data;
			if (file_exists($zipPath)) {
				$zip = new ZipArchive;
				if ($zip->open($zipPath) === TRUE) {
					$htmlContent = $zip->getFromName($htmlFile);
					if ($htmlContent !== false) {
						$htmlContent = mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8');
						$dom = new DOMDocument();
						@$dom->loadHTML($htmlContent);
						$xpath = new DOMXPath($dom);
						$query = $xpath->query($nodeXPath);
						if ($query) {
							$textNode = $xpath->query($nodeXPath)->item(0);
							if ($textNode !== null) {
								$textNode->nodeValue = $newText;
								$nuevoContenidoHtml = $dom->saveHTML();
								if (!$dry) {
									$zip->deleteName($htmlFile);
									$zip->addFromString($htmlFile, $nuevoContenidoHtml);
								}
								debug("Nodo de texto de la línea $numFila correctamente reemplazado.", 2);
							} else {
								debug("ERROR: La query sobre el nodo $nodeXPath de la fila $numFila es NULL en el HTML $htmlFile", 2);
							}
						} else {
							debug("ERROR: El nodo $nodeXPath de la fila $numFila no se encontró en el HTML $htmlFile", 2);
						}
					} else {
						debug("ERROR: No se encontró el HTML $htmlFile", 2);
					}
					$zip->close();
				} else {
					debug("ERROR: No se pudo abrir el ZIP $zipPath", 1);
				}
			} else {
				debug("ERROR: No existe el ZIP $zipPath", 0);
			}
			$numFila++;
		}
		fclose($handle);
		debug("Textos reemplazados correctamente.", 1);
	} else {
		debug("ERROR: No se pudo abrir el archivo CSV $csvFile", 0);
	}
}
function debug($texto, $nivel)
{
	$fechaHora = new DateTime();
	$timestampISO = $fechaHora->format(DateTime::ATOM);
	$salida = $timestampISO . (($nivel < 0) ? " " : str_repeat(" ", $nivel) . " └─ ") . $texto . PHP_EOL;
	file_put_contents("debug_reemplazos.txt", $salida, FILE_APPEND);
	if ($nivel < 2) print $salida;
}
