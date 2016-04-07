<?php
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

function setPermissions($dir, $file_perm, $dir_perm){
	$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	foreach($files as $file) {
		if ($file->isDir()){
			chmod($file->getRealPath(), $dir_perm);
		}else{
			chmod($file->getRealPath(), $file_perm);
		}
	}
	chmod($dir, $perm);
}

$tempLocation = '/home/factoryc/public_html/projects/markdown-website/_sys/temp/';
$filename = $tempLocation . "release.tar.gz";
$releaseFileLocation = "/home/factoryc/public_html/projects/markdown-website/_sys/latest";
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
				$dest_file = fopen($filename, 'wb');
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

				if(!(filesize($filename) > 0)){
					throw new Exception("Empty file!", 1);
					
				}

				$phar = new PharData($filename);
				$phar->extractTo($tempLocation, null, true);
				unset($phar);
				Phar::unlinkArchive($filename);
				$dirs = scandir($tempLocation);
				removeDir($releaseFileLocation);
				foreach($dirs as $dir){
					if(preg_match($release_match, $dir) == 1){
						rename($tempLocation . $dir, $releaseFileLocation);
					}
				}
				setPermissions($releaseFileLocation, 0644, 0755);
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