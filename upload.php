<?php

require "vendor/autoload.php";
require "config.php";

$uploader = new \Api\Uploader\Uploader($config);
$uploader->update();
