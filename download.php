<?php

require "vendor/autoload.php";
require "config.php";

$downloader = new \Api\Downloader\Downloader($config);
$downloader->update();
