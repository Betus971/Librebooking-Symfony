<?php
require __DIR__.'/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\ArrayLoader;

$loader = new ArrayLoader([
    'index' => '{{ weekStart|date("Y-m-d") }} | {{ weekStart|date_modify("+7 day")|date("Y-m-d") }} | {{ weekStart|date("Y-m-d") }}',
]);
$twig = new Environment($loader);

$date = new \DateTimeImmutable('2024-05-01');

echo $twig->render('index', ['weekStart' => $date]);
