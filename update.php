<?php
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
				foreach($dirs as $dir){
					if(preg_match($release_match, $dir) == 1){
						rename($tempLocation . $dir, $releaseFileLocation);
					}
				}
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