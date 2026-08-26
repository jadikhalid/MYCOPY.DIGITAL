<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout();
}

header('Location: ' . route_url('home'));
exit;
