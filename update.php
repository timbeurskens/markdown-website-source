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
if(isset($_POST['payload'])){
	$payload = json_decode($_POST['payload'], true);
	if(isset($payload['action']) && $payload['action'] == "published" && isset($payload['release']) && isset($payload['repository'])){
		$repo = $payload['repository'];
		$release_match = "/" . str_replace("/", "-", $repo['full_name']) . ".*/";
		$release = $payload['release'];
		if(isset($release['tarball_url']) && $release['tarball_url'] != ""){
			$tarball_url = $release['tarball_url'];
			//download file
			try{
				//set_time_limit(0); // unlimited max execution time
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

				$phar = new PharData($archiveLocation);
				$phar->extractTo($tempLocation, null, true);
				unset($phar);
				Phar::unlinkArchive($archiveLocation);
				$dirs = scandir($tempLocation);
				removeDir($releaseFileLocation);
				foreach($dirs as $dir){
					if(preg_match($release_match, $dir) == 1){
						rename($tempLocation . $dir, $releaseFileLocation);
					}
				}
				setDirectoryPermissions($releaseFileLocation, 0644, 0755);
			} catch(Exception $e){
				print_r($e);
			}
		}else{
			echo "No tarball url detected";
		}
	}else{
		echo "Not a valid request.";
		print_r($payload);
	}
}else{
	echo "no payload";
}
?>
