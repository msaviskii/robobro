<?php

function check_syntax_file($filename) {
    return @eval('return true; ?>' . file_get_contents($filename));
}

$dir = dirname(__FILE__);   //задаём имя директории
    if(is_dir($dir)) {   //проверяем наличие директории
         echo $dir.' - директория существует;<br>';
         $files = scandir($dir);    //сканируем (получаем массив файлов)
         array_shift($files); // удаляем из массива '.'
         array_shift($files); // удаляем из массива '..'
		 
		 # Проверяем все файлы директории и выводим результат
         for($i=0; $i<sizeof($files); $i++){
			if(strpos($files[$i], '.php')===false) continue;
		 	echo('<br>filename: <b>'.$files[$i].'</b> - ');	
			var_dump( check_syntax_file($dir.$files[$i]) );
			echo('<br>');		 
		 }  
    }
    else echo $dir.' -такой директории нет;<br>';

?>