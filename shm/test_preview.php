<?php
$_POST['action'] = 'preview';
$_POST['start_year'] = 2024;
$_POST['start_month'] = 1;
$_POST['end_year'] = 2024;
$_POST['end_month'] = 12;
include 'send_bulk_invoices.php';
