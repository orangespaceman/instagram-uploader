<?php

namespace Api\Uploader;

class Uploader
{
  protected $format = 'php_serial';
  protected $lastImageNumberFile = __DIR__ . '/../data/uploader-last-image.txt';

  public function __construct($config)
  {
    $this->config = $config;
  }

  public function connect()
  {
    $this->instagram = new \InstagramAPI\Instagram($this->config['debug']);
    $this->instagram->setUser($this->config['instagram']['username'], $this->config['instagram']['password']);

    try {
        $this->instagram->login();
    } catch (Exception $e) {
        $e->getMessage();
        exit();
    }
  }

  public function update()
  {
    $this->imageNumber = $this->getNextImageNumber();

    $data = $this->getData();
    if (!$data) return;

    $image = $this->getImage($data);
    $type = $this->getType($data);
    $caption = $this->getCaption($data);

    try {
      if (isset($image) && !empty($image)) {
        $this->connect();
        if ($type == "photo") {
          $this->instagram->uploadPhoto($image, $caption);
        } else if ($type == "video") {
          $this->instagram->uploadVideo($image, $caption);
        }
      }
    } catch (Exception $e) {
      echo $e->getMessage();
    }

    $this->incrementImageNumber();
  }

  private function getNextImageNumber()
  {
    $imageNumber = @file_get_contents($this->lastImageNumberFile);
    if (!$imageNumber) {
      return 1;
    } else {
      return intval($imageNumber) + 1;
    }
  }

  private function getData()
  {
    $data = @file_get_contents(__DIR__ . '/../files/' . $this->imageNumber . '.json', 'w');
    if (!$data) return;
    return json_decode($data);
  }

  private function getImage($data)
  {
    if (!isset($data->img)) return;
    return $data->img;
  }

  private function getType($data)
  {
    if (!isset($data->sizes)) return;
    return $data->sizes->media;
  }

  private function getCaption($data)
  {
    $caption = "";
    if (isset($data->info->title)) {
      $caption .= "\n" . $data->info->title . "\n";
    }
    if (isset($data->info->description)) {
      $caption .= "\n" . $data->info->description . "\n";
    }
    if (isset($data->info->date)) {
      $caption .= "\nPhoto taken on " . $data->info->date . "\n";
    }

    $caption .= "\n";

    if (isset($data->exif)) {

      if (isset($data->exif->Model) && isset($data->exif->Make)) {
        if (strpos($data->exif->Model, $data->exif->Make) !== false) {
          $caption .= "\nCamera: " . $data->exif->Model;
        } else {
          $caption .= "\nCamera: " . $data->exif->Make . " " . $data->exif->Model;
        }
      }

      if (isset($data->exif->{"Lens Model"})) {
        $caption .= "\nLens: " . $data->exif->{"Lens Model"};
      }
      if (isset($data->exif->{"Exposure"})) {
        $caption .= "\nExposure: " . $data->exif->{"Exposure"};
      }
      if (isset($data->exif->{"Aperture"})) {
        $caption .= "\nAperture: " . $data->exif->{"Aperture"};
      }
      if (isset($data->exif->{"Focal Length"})) {
        $caption .= "\nFocal Length: " . $data->exif->{"Focal Length"};
      }
      if (isset($data->exif->{"ISO Speed"})) {
        $caption .= "\nISO Speed: " . $data->exif->{"ISO Speed"};
      }

      $caption .= "\n";
    }

    if (isset($data->info->tags)) {
      $caption .= "\n" . $data->info->tags;
    }

    return trim($caption);
  }

  private function incrementImageNumber()
  {
    $fp = fopen($this->lastImageNumberFile, 'w');
    fwrite($fp, $this->imageNumber);
    fclose($fp);
  }
}
