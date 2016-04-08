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

// Set working directories and file destinations
$tempLocation = $options['temp_location'];
$archiveLocation = $tempLocation . "content_release.tar.gz";
$releaseFileLocation = $tempLocation . "content/";
$buildOptionsFile = $releaseFileLocation . "build.json";

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

// Set permissions such that php can render the files
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

// Done updating content files
// Read build options
if(file_exists($buildOptionsFile)){
	// Read options file and merge with default options
	$buildOptionsRaw = file_get_contents($buildOptionsFile);
	try {
		$options = array_merge($options, json_decode($buildOptionsRaw, true));
	} catch (Exception $e) {
		print($e);
	}
	unlink($buildOptionsFile);
}

// Parsedown instance:
if(!file_exists($options['parsedown_location'])){
  throw new Exception("Parsedown does not exist: " . $options['parsedown_location'], 1);
  die();
}

require $options['parsedown_location'];

// Init parsedown
$Parsedown = new Parsedown();

$fileListLocation = $options['dest_location'] . "file_list.json";

// Check if the file_list.json file exists
if(file_exists($fileListLocation)){
	// Remove previously rendered files
	$old_files_list = json_decode(file_get_contents($fileListLocation), true);
	foreach($old_files_list as $file){
		if($file && file_exists($options['dest_location'] . $file)){
			unlink($options['dest_location'] . $file);
		}
	}
}

// Create list for storing rendered files
$renderedFiles = array();

// Go through all files and parse markdown files
$iterator = new RecursiveDirectoryIterator($releaseFileLocation, RecursiveDirectoryIterator::SKIP_DOTS);
$contentfiles = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
foreach($contentfiles as $file) {
	// Get json settings file location
	$fileOptions = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename('.md') . ".json";
	$newFilePath = str_replace($releaseFileLocation, $options['dest_location'], $file->getPath() . DIRECTORY_SEPARATOR);
	$newFileRealPath = $newFilePath . $file->getBasename('.md') . ".html";
	$relativePath = substr($file->getRealPath(), strlen($releaseFileLocation));

	if ($file->isDir()){
    // Dir is empty since we first searched for children. We can remove this directory
    rmdir($file->getRealPath());
  } else {
    if($file->getExtension() == "md" && !in_array($relativePath, $options['exclude'])){
      $fOptions = $options;

      // Merge file specific options
      if(file_exists($fileOptions)){
        $fOptionsExtra = json_decode(file_get_contents($fileOptions), true);
        unlink($fileOptions);
        $fOptions = array_merge($fOptions, $fOptionsExtra);
      }

      // Read markdown file contents
      $fContents = file_get_contents($file->getRealPath());

      // Render markdown to html
      $fHtmlContents = $Parsedown->text($fContents);

      // Create new build system
      $buildsystemInstance = new $BuildSystemName($fHtmlContents, $fOptions);

      // Render data
      $newContent = $buildsystemInstance->render();

      $newfileHandle = fopen($newFileRealPath, "w");
      fwrite($newfileHandle, $newContent);
      fclose($newfileHandle);

			$renderedFiles[] = $relativePath;

      unlink($file->getRealPath());
    }elseif($file->getExtension() == "json"){
      // File is deleted after rendering markdown file
    }else{
      unlink($file->getRealPath());
    }
  }
}
rmdir($releaseFileLocation);

$listHandle = fopen($fileListLocation, "w");
$list_contents = json_encode($renderedFiles);
fwrite($listHandle, $list_contents);
fclose($listHandle);

echo "Done!";
?>
