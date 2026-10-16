<?php
require __DIR__ . '/koneksi.php';
if (!empty($_SESSION['u'])) logact('Logout dari sistem');
session_destroy();
header('Location: index.php');
