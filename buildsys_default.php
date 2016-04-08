<?php
class BuildSystem extends BuildSystemStruct{
  public function render(){
    return $this->htmlContent;
  }

  static function post_render($file_list, $options){
    return;
  }
}
?>
