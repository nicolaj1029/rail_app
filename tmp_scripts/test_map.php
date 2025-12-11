<?php
require 'vendor/autoload.php';
$c = new App\Controller\ReimbursementController(new Cake\Http\ServerRequest(), new Cake\Http\Response());
$ref = new ReflectionClass($c);
$m = $ref->getMethod('loadNationalFieldMap');
$m->setAccessible(true);
$p = 'C:/wamp64/www/rail_app/webroot/files/Nationale PDF former/DK_rejsetidsgaranti_EN.pdf';
var_export($m->invoke($c, $p));
?>
