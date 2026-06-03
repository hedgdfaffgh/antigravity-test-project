<?php
session_start();
header('Content-type: image/jpeg');

$text = $_SESSION['captcha'] = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);
$font_size = 8;

$image_width = 40;
$image_height = 22;

$image = imagecreate($image_width, $image_height);
imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 0, 0, 0);

imagettftext($image, $font_size, 0, 5, 15, $text_color, 'arial.ttf', $text);
imagejpeg($image);
imagedestroy($image);
?>