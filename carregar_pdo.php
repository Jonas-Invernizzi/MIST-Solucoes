<?php
$pdo = new PDO('mysql:host=localhost;dbname=mist_solucoes', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Agora o PHP avisa se o SQL falhar
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
]);