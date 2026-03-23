<?php
$dbservername =" locaslhost";
$dbuser= "root"
$dbpassword = "";
$dbname ="sistema_login"

$conn = new mysqli($dbservername,$dbuser,$dbpassword,$dbname);
  if ($conn->connect_error){
  die("falha na conecao".$conn->connect_error);
  }
  echo(" connecao bem sucedida");

  <?