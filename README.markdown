GSStreamWrapper (beta)
===============

These stream wrappers allow you to use Google Storage like a local file system use Oauth2.0 Authentication:

  - `file_get_contents('gs://bucket/path/to/a/file.txt');`

  - `scandir('gs://bucket/path/to/a/dir/');`

  - `fopen(...);`

  - `stat(...);`

  - ...

Requirements
------------

  - PHP 5.4.0 (or higher)
  - Google APIs Client Library 2.0

Usage
-----
``` php
    <?php
    //include composer autoload
    include_once __DIR__ . '/vendor/autoload.php';
    require_once 'GSStreamWrapper.php';

    $client = new Google_Client();
    //add your server_secrets.json file (account service)
    $client->setAuthConfigFile('server_secrets.json');
    $client->useApplicationDefaultCredentials();
    $client->addScope(array(
        Google_Service_Storage::CLOUD_PLATFORM,
        Google_Service_Storage::CLOUD_PLATFORM_READ_ONLY,
        Google_Service_Storage::DEVSTORAGE_FULL_CONTROL,
        Google_Service_Storage::DEVSTORAGE_READ_ONLY,
        Google_Service_Storage::DEVSTORAGE_READ_WRITE,
    ));
    $service = new Google_Service_Storage($client);

    \GSStreamWrapper::setService($service);
    \GSStreamWrapper::registerWrapper();

    // Use it!
```