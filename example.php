<?php
ini_set('display_errors',1);
include_once __DIR__ . '/vendor/autoload.php';
require_once './GSStreamWrapper.php';

session_start();
$client = new Google_Client();
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
$bucket_name = 'my_bucket';
mkdir('gs://'.$bucket_name.'/dir/');
mkdir('gs://'.$bucket_name.'/dir/subdir-1/');
mkdir('gs://'.$bucket_name.'/dir/subdir-2/');
mkdir('gs://'.$bucket_name.'/dir/subdir-3/');
var_dump(is_dir('gs://'.$bucket_name.'/dir/subdir-3/'), is_file('gs://'.$bucket_name.'/dir/subdir-3/'));
file_put_contents('gs://'.$bucket_name.'/dir/file1.txt', 'file1 content');
file_put_contents('gs://'.$bucket_name.'/dir/file2.txt', 'file2 content');
file_put_contents('gs://'.$bucket_name.'/dir/file3.txt', 'file3 content');
rename('gs://'.$bucket_name.'/dir/file1.txt', 'gs://'.$bucket_name.'/dir/subdir-1/file4.txt');
file_put_contents('gs://'.$bucket_name.'/dir/subdir-1/file4.txt', 'add subdir-1 content', FILE_APPEND);
copy('gs://'.$bucket_name.'/dir/subdir-1/file4.txt', 'gs://'.$bucket_name.'/dir/file1.txt');
rmdir('gs://'.$bucket_name.'/dir/subdir-2/');
var_dump(filesize('gs://'.$bucket_name.'/dir/file1.txt'), is_dir('gs://'.$bucket_name.'/dir/file1.txt'), is_file('gs://'.$bucket_name.'/dir/file1.txt'));
