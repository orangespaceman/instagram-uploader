<?php

namespace Api\Downloader;

class Downloader
{
  protected $format = 'php_serial';
  protected $lastImageNumberFile = __DIR__ . '/../data/downloader-last-image.txt';

  public function __construct($config)
  {
    $this->config = $config;
    $this->flickr = new \JeroenG\Flickr\Flickr(new \JeroenG\Flickr\Api($this->config['flickr']['key'], $this->format));
  }

  public function update()
  {
    $this->imageNumber = $this->getNextImageNumber();
    $this->debug('imageNumber:', $this->imageNumber);

    $photo = $this->getPhoto();
    if (!$photo) {
      $this->debug('No new photos:');
      return;
    }

    $info = $this->getPhotoInfo($photo);
    $this->debug('info:', $info);

    $exif = $this->getPhotoExif($photo);
    $this->debug('exif:', $exif);

    $sizes = $this->getPhotoSizes($photo);
    $this->debug('sizes:', $sizes);

    if ($sizes['media'] == 'photo') {
      $img = $this->downloadImage($sizes['source']);
      $this->checkImageRatio($img);
    } else if ($sizes['media'] == 'video') {
      $img = $this->downloadVideo($sizes['source']);
    } else {
      $img = '';
    }

    $this->saveData($info, $exif, $sizes, $img);

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

  private function getPhoto()
  {
    $photo = $this->flickr->request('flickr.photos.search', [
      'user_id' => $this->config['flickr']['user_id'],
      'oauth_token' => $this->config['flickr']['oauth_token'],
      'oauth_consumer_key' => $this->config['flickr']['key'],
      'sort' => 'date-posted-asc',
      'per_page' => '1',
      'page' => $this->imageNumber,
    ]);
    if (($this->imageNumber) > $photo->photos['total']) {
      return;
    } else {
      return $photo->photos['photo'][0];
    }
  }

  private function getPhotoInfo($photo)
  {
    $info = $this->flickr->request('flickr.photos.getInfo', [
      'photo_id' => $photo['id'],
      'secret' => $photo['secret'],
      'oauth_token' => $this->config['flickr']['oauth_token'],
      'oauth_consumer_key' => $this->config['flickr']['key'],
    ]);

    $albums = $this->flickr->request('flickr.photos.getAllContexts', [
      'photo_id' => $photo['id'],
      'secret' => $photo['secret'],
      'oauth_token' => $this->config['flickr']['oauth_token'],
      'oauth_consumer_key' => $this->config['flickr']['key'],
    ]);

    return [
      'title' => $info->photo['title']['_content'],
      'description' => strip_tags($info->photo['description']['_content']),
      'date' => date('l jS F Y, g:i:s a', strtotime($info->photo['dates']['taken'])),
      'albums' => $this->parseAlbums($albums->set),
      'tags' => $this->parseTags($info->photo['tags']['tag'])
    ];
  }

  private function getPhotoExif($photo)
  {
    $exif = $this->flickr->request('flickr.photos.getExif', [
      'photo_id' => $photo['id'],
      'secret' => $photo['secret'],
      'oauth_token' => $this->config['flickr']['oauth_token'],
      'oauth_consumer_key' => $this->config['flickr']['key'],
    ]);

    $data = [];
    $fields = ['Make', 'Model', 'Lens Model', 'Exposure', 'Aperture', 'Focal Length', 'ISO Speed'];

    foreach ($exif->photo['exif'] as $item) {
      if (in_array($item['label'], $fields)) {
        if (isset($item['clean'])) {
          $data[$item['label']] = $item['clean']['_content'];
        } else {
          $data[$item['label']] = $item['raw']['_content'];
        }
      }
    }

    return $data;
  }

  private function getPhotoSizes($photo)
  {
    $sizes = $this->flickr->request('flickr.photos.getSizes', [
      'photo_id' => $photo['id'],
      'secret' => $photo['secret'],
      'oauth_token' => $this->config['flickr']['oauth_token'],
      'oauth_consumer_key' => $this->config['flickr']['key'],
    ]);
    return @end($sizes->sizes['size']);
  }

  private function downloadImage($path)
  {
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $extension = explode('?', $extension)[0];
    $dest = __DIR__ . '/../files/' . $this->imageNumber . '.' . $extension;

    $this->downloadFile($path, $dest);

    $this->debug('downloadImage:', $dest);
    return $dest;
  }

  private function downloadVideo($path)
  {
    $headers = $this->downloadFileHeaders($path);
    $headersArray = $this->createHeadersArrayFromCurlResponse($headers);
    if (count($headersArray) < 1) return;

    $location = $headersArray[0]['Location'];
    $filename = explode('?', $location)[0];
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    $dest = __DIR__ . '/../files/' . $this->imageNumber . '.' . $extension;

    $this->downloadFile($location, $dest);

    $this->debug('downloadVideo:', $dest);
    return $dest;
  }

  private function downloadFile($path, $dest)
  {
    $ch = curl_init($path);
    $fp = fopen($dest, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
  }

  private function downloadFileHeaders($path)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $path);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $headers = curl_exec($ch);
    curl_close($ch);
    return $headers;
  }

  private function createHeadersArrayFromCurlResponse($headerContent)
  {
    $headers = array();
    $arrRequests = explode("\r\n\r\n", $headerContent);
    for ($index = 0; $index < count($arrRequests) -1; $index++) {
        foreach (explode("\r\n", $arrRequests[$index]) as $i => $line)
        {
            if ($i === 0)
                $headers[$index]['http_code'] = $line;
            else
            {
                list ($key, $value) = explode(': ', $line);
                $headers[$index][$key] = $value;
            }
        }
    }
    return $headers;
  }

  private function checkImageRatio($img)
  {
    $maxLandscapeRatio = 1.8/1;
    $maxPortraitRatio = 5/4;

    $imageDimensions = getimagesize($img);
    $width = $imageDimensions[0];
    $height = $imageDimensions[1];

    if ($width > $height && $width/$height > $maxLandscapeRatio) {
      $newHeight = floor($width / $maxLandscapeRatio);
      $x = 0;
      $y = -floor(($newHeight - $height) / 2);
      $this->resizeImage($img, $width, $newHeight, $x, $y);
    } else if ($height > $width && $height/$width > $maxPortraitRatio) {
      $newWidth = floor($height / $maxPortraitRatio);
      $y = 0;
      $x = -floor(($newWidth - $width) / 2);
      $this->resizeImage($img, $newWidth, $height, $x, $y);
    }
  }

  private function resizeImage($img, $width, $height, $x, $y)
  {
    $pathinfo = pathinfo($img);

    if ($pathinfo['extension'] == "jpg" || $pathinfo['extension'] == "jpeg") {
      $src = imagecreatefromjpeg($img);
    } else if ($pathinfo['extension'] == "png") {
      $src = imagecreatefrompng($img);
    } else if ($pathinfo['extension'] == "gif") {
      $src = imagecreatefromgif($img);
    } else {
      return;
    }

    copy($img, $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '-original.' . $pathinfo['extension']);

    $tmp = imagecreatetruecolor($width, $height);
    $backgroundColor = imagecolorallocate($tmp, 255, 255, 255);
    imagefill($tmp, 0, 0, $backgroundColor);
    imagecopyresampled($tmp, $src, 0, 0, $x, $y, $width, $height, $width, $height);
    imagejpeg($tmp, $img, 100);
    imagedestroy($src);
    imagedestroy($tmp);

    $this->debug('resizeImage:', $img);
  }

  private function saveData($info, $exif, $sizes, $img)
  {
    $data = [
      'info' => $info,
      'exif' => $exif,
      'sizes' => $sizes
    ];

    if (isset($img) && !empty($img)) {
      $data['img'] = $img;
    }

    $fp = fopen(__DIR__ . '/../files/' . $this->imageNumber . '.json', 'w');
    fwrite($fp, json_encode($data));
    fclose($fp);
  }

  private function incrementImageNumber()
  {
    $fp = fopen($this->lastImageNumberFile, 'w');
    fwrite($fp, $this->imageNumber);
    fclose($fp);
  }

  private function parseTags($tags)
  {
    $tagString = '';
    foreach ($tags as $tag) {
      $tagString .= $this->camelCase($tag['raw']) . ', ';
    }
    return rtrim(trim($tagString), ', ');
  }

  private function parseAlbums($albums)
  {
    $albumString = '';
    foreach ($albums as $album) {
      $albumString .= $album['title'] . ', ';
    }
    return rtrim(trim($albumString), ', ');
  }

  private function camelCase($str)
  {
    $str = preg_replace('/[^a-z0-9]+/i', ' ', $str);
    $str = trim($str);
    $str = ucwords($str);
    $str = str_replace(' ', '', $str);
    $str = lcfirst($str);
    return $str;
  }

  private function debug($msg, $obj = null)
  {
    if ($this->config['debug']) {
      echo $msg;
      if (isset($obj)) {
        print_r($obj);
      }
      echo "\n";
    }
  }
}
