<?php
$fuente = json_decode(file_get_contents("modelo/data/cambios.json"), true)["contenidos"][1]["reemplazos"][0];
$pattern = $fuente["pattern"];
$replacement = $fuente["replacement"];
print $pattern . PHP_EOL . $replacement . PHP_EOL;
$subject = file_get_contents(__DIR__ . "/origen/000/023/155/_resource_content/views/pag06.html");
$salida = preg_replace($pattern, $replacement, $subject, -1, $countR);
print PHP_EOL;
print "Se hicieron $countR cambios";
print PHP_EOL;
print PHP_EOL;
print $salida . PHP_EOL;
