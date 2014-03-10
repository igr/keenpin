<?php

require "keenpin.php";

/**
 * Uploads new tasks.
 */

$pass = $_POST["password"];
if ($pass == null) {
    accessDenied();
}

$passOk = checkListPassword($template, $pass, $passwordFile);
if ($passOk != true) {
    accessDenied();
}

$tasks = $_POST["tasks"];

if ($tasks != null) {
    writeTaskList($tasks);
}

redirect("index.php");

?>