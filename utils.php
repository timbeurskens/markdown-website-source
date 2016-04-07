<?php
define("SYSVERSION", "0.1");

// Default options.json data
$default_options = array(
	"temp_location" => "/home/user/public_html/temp/",
	"dest_location" => "/home/user/public_html/",
	"working_dir" => "/home/user/public_html/latest/",
	"secret" => "",
	"buildscript" => "/home/user/public_html/build/buildscript.php",
	"parsedown_location" => "/home/user/public_html/parsedown/Parsedown.php",
	"exclude" => array()
);

// Check if options file exists
if(!file_exists("../options.json")){
	throw new Exception("options.json: file not found at " . realpath("../options.json"), 1);
	die();
}

// Read options file and merge with default options
$optionsRaw = file_get_contents("../options.json");
$options = $default_options;
try {
	$options = array_merge($options, json_decode($optionsRaw, true));
} catch (Exception $e) {
	print($e);
}

// Remove directory and all its contents
function removeDir($dir){
	$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	foreach($files as $file) {
	    if ($file->isDir()){
	        rmdir($file->getRealPath());
	    } else {
	        unlink($file->getRealPath());
	    }
	}
	rmdir($dir);
}

// Set permission of directory and all its contents
function setDirectoryPermissions($dir, $file_perm, $dir_perm){
	$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	foreach($files as $file) {
		if ($file->isDir()){
			chmod($file->getRealPath(), $dir_perm);
		}else{
			chmod($file->getRealPath(), $file_perm);
		}
	}
	chmod($dir, $dir_perm);
}

// Abstract Build System class.
abstract class BuildSystemStruct {
	protected $sysParameters = array(
		"content" => "",
		"sys_version" => SYSVERSION,
		"content_version" => "?",
		"dest_location" => "",
		"options" => array(),
		"file_name" => "",
		"file_parameters" => array()
	);

	public function __construct($data){
		$this->sysParameters = array_merge($this->sysParameters, $data);
	}

	abstract public function render();
}
?>
