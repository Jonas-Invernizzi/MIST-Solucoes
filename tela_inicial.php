<?php 
require_once('carregar_pdo.php');
require_once('carregar_twig.php');

echo $twig->render('tela_inicial.html');