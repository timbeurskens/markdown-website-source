<?php
public class BuildSystem extends BuildSystemStruct{
  public function render(){
    return $this->$sysParameters['content'];
  }
}
?>
