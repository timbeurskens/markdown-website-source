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

// Check event: only release and push events are accepted
if(!isset($_SERVER['HTTP_X_GITHUB_EVENT']) || ($_SERVER['HTTP_X_GITHUB_EVENT'] != "release" && $_SERVER['HTTP_X_GITHUB_EVENT'] != "push")){
	throw new Exception("This is not a release or push event!", 1);
	die();
}

// Check if push events are accepted
if(!$options['allow_push_events'] && $_SERVER['HTTP_X_GITHUB_EVENT'] == "push"){
	throw new Exception("Push events are not enabled in options.json file", 1);
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
$archive_format = "tarball";
$git_repo_ref = $_SERVER['HTTP_X_GITHUB_EVENT'] == "release" ? $payload['release']['tag_name'] : $options['push_event_branch'];
$tarball_url = str_replace("{archive_format}", $archive_format, str_replace("{/ref}", $git_repo_ref, $repo['archive_url']));

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
		$options = merge_settings($options, json_decode($buildOptionsRaw, true));
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

$oldFileList = array();

// Check if the file_list.json file exists
if(file_exists($fileListLocation)){
	// Remove previously rendered files
	$oldFileList = json_decode(file_get_contents($fileListLocation), true);
	foreach($oldFileList as $file){
		if($file && file_exists($options['dest_location'] . $file . ".html")){
			unlink($options['dest_location'] . $file . ".html");
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
	$relativeRenderPath = substr($file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename('.md'), strlen($releaseFileLocation));

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
        $fOptions = merge_settings($fOptions, $fOptionsExtra);
      }

      // Read markdown file contents
      $fContents = file_get_contents($file->getRealPath());

      // Render markdown to html
      $fHtmlContents = $Parsedown->text($fContents);

      // Create new build system
      $buildsystemInstance = new $BuildSystemName($fHtmlContents, $fOptions, $file->getBasename('.md'), $relativeRenderPath);

      // Render data
      $newContent = $buildsystemInstance->render();

			// Check if path exists or create new if it does not exist
			if(strlen($newFilePath) > 0 && (!file_exists($newFilePath) || !is_dir($newFilePath))){
				if(!mkdir($newFilePath, 0755, true)) {
					throw new Exception("Error creating folders", 1);
					die();
				}
			}

			// Write rendered file
      $newfileHandle = fopen($newFileRealPath, "w");
      fwrite($newfileHandle, $newContent);
      fclose($newfileHandle);

			// Add file to rendered list
			$renderedFiles[] = $relativeRenderPath;

      unlink($file->getRealPath());
    }elseif($file->getExtension() == "json"){
      // File is deleted after rendering markdown file
    }else{
      unlink($file->getRealPath());
    }
  }
}
rmdir($releaseFileLocation);

// Compare old list with new list and remove empty directories
if(count($oldFileList) > 0){
	// Remove basenames of both lists
	$sOldFileList = array_unique(array_map(removeFileName, $oldFileList));
	$sNewFileList = array_unique(array_map(removeFileName, $renderedFiles));
	foreach ($sOldFileList as $oldPath) {
		if(strlen($oldPath) > 0 && !array_match("/^" . preg_quote($oldPath, "/") . "/", $sNewFileList)){
			removeDir($oldPath);
		}
	}
}

// Write file list to destination folder
$listHandle = fopen($fileListLocation, "w");
$list_contents = json_encode($renderedFiles);
fwrite($listHandle, $list_contents);
fclose($listHandle);

// Post render
$buildsystemInstance = new $BuildSystemName(null, $options, null, $options['dest_location']);
$buildsystemInstance->post_render($renderedFiles);

echo "Done!";
?>
