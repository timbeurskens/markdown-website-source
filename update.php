<?php
require "utils.php";

// Set working directories and file destinations
$tempLocation = $options['temp_location'];
$archiveLocation = $tempLocation . "src_release.tar.gz";
$releaseFileLocation = $options['working_dir'];

// Create sha1 hash to validate payload
$rawPayload = file_get_contents("php://input");
$secretHash = "sha1=" . hash_hmac("sha1", $rawPayload, $options['secret']);

// Validate payload and source of request
if(!isset($_SERVER['HTTP_X_HUB_SIGNATURE']) || $_SERVER['HTTP_X_HUB_SIGNATURE'] != $secretHash){
	throw new Exception("Not a valid signature!: ", 1);
	die();
}

// Check event: only release events are accepted
if(!isset($_SERVER['HTTP_X_GITHUB_EVENT']) || $_SERVER['HTTP_X_GITHUB_EVENT'] != "release"){
	throw new Exception("This is not a release event!", 1);
	die();
}

// Check decoded payload data
if(!isset($_POST['payload'])){
	throw new Exception("No url encoded payload available!", 1);
	die();
}

// Assuming the Github Webhook documentation is correct: we don't need to check if data exists.
// Decode payload JSON data
$payload = json_decode($_POST['payload'], true);

$repo = $payload['repository'];
$release_match = "/" . str_replace("/", "-", $repo['full_name']) . ".*/"; // match name-repo-version regex
$release = $payload['release'];
$tarball_url = $release['tarball_url'];

// Download release tarball
try {
	$dest_file = fopen($archiveLocation, 'wb');
	$curl_options = array(
		CURLOPT_FILE    => $dest_file,
		CURLOPT_TIMEOUT => 28800, // set this to 8 hours so we dont timeout on big files
		CURLOPT_URL     => $tarball_url,
		CURLOPT_USERAGENT => "markdown-update-downloader",
		CURLOPT_FOLLOWLOCATION => true
	);

	$ch = curl_init();
	curl_setopt_array($ch, $curl_options);
	curl_exec($ch);
	curl_close($ch);
	fclose($dest_file);

	if(!(filesize($archiveLocation) > 0)){
		throw new Exception("Empty file!", 1);
	}
} catch (Exception $e) {
	die("Error downloading file: " . print_r($e, true));
}

// Extract release to given location and remove tar file
try {
	$phar = new PharData($archiveLocation);
	$phar->extractTo($tempLocation, null, true);
	unset($phar);
	Phar::unlinkArchive($archiveLocation);
} catch (Exception $e) {
	die("Error extracting archive: " . print_r($e, true));
}

// Set permissions such that php can execute the program correctly
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

// Updated to the latest version!
echo "done";
?>
