# Instagram Uploader

Automatically upload images from a flickr account to Instagram

This app makes use of the following libraries:  

 - https://github.com/mgp25/Instagram-API
 - https://github.com/Jeroen-G/Flickr

## Setup

Check this project out onto your computer, a server or a raspberry pi...

### PHP

 - Install [composer](https://getcomposer.org/)
 - Install dependencies via composer:

 ```
 composer install
 composer dump-autoload -o
 ```

### Instagram

 - Register for an account, make a note of your username and password

### flickr

 - Register for an account, request an [API key](https://www.flickr.com/services/apps/create/), make a note of your User ID, API key and secret

### Config

 - duplicate the `config.sample.php` file, call the new file `config.php` and enter relevant details

## Usage

 - To get the latest image from flickr, run this command:

 ```
 php download.php
 ```

 - To upload the latest image to Instagram, run this command:

 ```
 php upload.php
 ```

The easiest way to manage this is to set it up with a [cron](https://help.ubuntu.com/community/CronHowto) to run both of the scripts above periodically, e.g. every 10 minutes or so.

```
5,15,25,35,45,55 * * * * /usr/bin/php /home/pi/instagram-uploader/download.php
0,10,20,30,40,50 * * * * /usr/bin/php /home/pi/instagram-uploader/upload.php
```
