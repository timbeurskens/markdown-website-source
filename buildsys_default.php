<?php
class BuildSystem extends BuildSystemStruct{
  public function render(){
    return $this->htmlContent;
  }

  public function post_render($file_list){
    return;
  }
}
?>
