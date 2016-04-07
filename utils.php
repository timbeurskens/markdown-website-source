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
?>
