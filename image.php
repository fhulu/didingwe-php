<?php

class image_exception extends Exception {};

class image {

  static function resize($path, $width=0, $height=0)
  {
    if ($width ==0 && $height == 0) return;

    list($old_w,$old_h) = getimagesize($path);
    if ($height == 0) {
      $new_w = $width;
      $new_h = $old_h * round($new_w/$old_w,2);
    }
    if ($width == 0) {
      $new_h = $height;
      $new_w = $old_w * round($new_h/$old_h,2);
    }

    $source = imagecreatefromjpeg($path); // Set the resource for the source image
    $dest = imagecreatetruecolor($new_w,$new_h); // Create resource for thumbnail
    imagecopyresized($dest, $source, 0,0,0,0, $new_w, $new_h, $old_w, $old_h);

    $new_path = tempnam("/tmp", "image");

    imagejpeg($dest,$new_path,85); // Stream image to file $new_path
    imagedestroy($source);
    imagedestroy($dest);
    rename($new_path, $path);
  }
}

