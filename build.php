<?php
require "utils.php";

// Set the default classname for the registered build system.
// Since this is a global variable, you can change it in the build system php file.
$BuildSystemName = "BuildSystem";

if(!file_exists($options['buildscript'])){
	throw new Exception("options.json: file not found at " . realpath("../options.json"), 1);
	die();
}

require $options['buildscript'];


?>
