<?php
 class ControladorVistas{
        function cargarSeccion(){

           if(isset($_GET['seccion'])){
            $ruta = 'vista/'.$_GET['seccion'].'.php';
         //echo $ruta;
            if(file_exists($ruta)){
                include $ruta;
            }
            else{
                echo "404";
            }
           }
           else{
                include 'vista/manual.php';
      }
        
        }
 }
?>