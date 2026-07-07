<?php   
class Conexion{
    function conectar(){
        $servername = "localhost";
        $username = "root";
        $password = "mi6uel_666";
        $database = "upgop";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);

}

//echo "Connected successfully";
return $conn;

}
}


?>