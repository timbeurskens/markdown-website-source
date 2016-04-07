<?php
require "utils.php";

$default_options = array(
	"temp_location" => "/home/user/public_html/temp/",
	"dest_location" => "/home/user/public_html/",
	"working_dir" => "/home/user/public_html/latest/",
	"secret" => "",
	"buildscript" => "/home/user/public_html/build/buildscript.php",
	"parsedown_location" => "/home/user/public_html/parsedown/Parsedown.php",
	"exclude" => array()
);

if(!file_exists("../options.json")){
	throw new Exception("options.json: file not found at " . realpath("../options.json"), 1);
	die();
}

$optionsRaw = file_get_contents("../options.json");
$options = $default_options;
try {
	$options = array_merge($options, json_decode($optionsRaw, true));
} catch (Exception $e) {
	print($e);
}
$tempLocation = $options['temp_location'];
$archiveLocation = $tempLocation . "release.tar.gz";
$releaseFileLocation = $options['working_dir'];

$secretHash = "sha1=" . sha1($options['secret']);

if(!isset($_SERVER['HTTP_X_HUB_SIGNATURE']) || $_SERVER['HTTP_X_HUB_SIGNATURE'] != $secretHash){
	throw new Exception("Not a valid signature!", 1);
	die();
}

if(!isset($_POST['payload'])){
	throw new Exception("No payload available!", 1);
	die();
}

$payload = array();

try{
	$payload = json_decode($_POST['payload'], true);
} catch(Exception $e){
	die("Error decoding payload");
}

if(!isset($payload['release']) || !isset($payload['repository'])){
	throw new Exception("Payload does not contain the right information", 1);
	die();
}

$repo = $payload['repository'];
$release_match = "/" . str_replace("/", "-", $repo['full_name']) . ".*/"; // match name-repo-version regex
$release = $payload['release'];
$tarball_url = $release['tarball_url'];

try {
	$dest_file = fopen($archiveLocation, 'wb');
	$options = array(
		CURLOPT_FILE    => $dest_file,
		CURLOPT_TIMEOUT => 28800, // set this to 8 hours so we dont timeout on big files
		CURLOPT_URL     => $tarball_url,
		CURLOPT_USERAGENT => "markdown-update-downloader",
		CURLOPT_FOLLOWLOCATION => true
	);

	$ch = curl_init();
	curl_setopt_array($ch, $options);
	curl_exec($ch);
	curl_close($ch);
	fclose($dest_file);

	if(!(filesize($archiveLocation) > 0)){
		throw new Exception("Empty file!", 1);
	}
} catch (Exception $e) {
	die("Error downloading file: " . print_r($e, true));
}

try {
	$phar = new PharData($archiveLocation);
	$phar->extractTo($tempLocation, null, true);
	unset($phar);
	Phar::unlinkArchive($archiveLocation);
} catch (Exception $e) {
	die("Error extracting archive: " . print_r($e, true));
}

try {
	$dirs = scandir($tempLocation);
	removeDir($releaseFileLocation);
	foreach($dirs as $dir){
		if(preg_match($release_match, $dir) == 1){
			rename($tempLocation . $dir, $releaseFileLocation);
		}
	}
	setDirectoryPermissions($releaseFileLocation, 0644, 0755);
} catch (Exception $e) {
	die("Error moving files or changing permissions: " . print_r($e, true));
}
?>
