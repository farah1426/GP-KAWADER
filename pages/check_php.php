
<?php
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Loaded php.ini: " . (php_ini_loaded_file() ?: "None") . "<br>";
echo "Fileinfo enabled: " . (class_exists('finfo') ? "Yes" : "No");
?>
