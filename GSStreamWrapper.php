<?php
class GSStreamWrapper
{
    const SCHEME = 'gs';
    const DS = '/';

    protected static $service;

    protected $filePosition = 0;

    protected $bucket;
    protected $path;

    protected $file;
    protected $fileBody;
    protected $fileMode;
    protected $dir;
    protected static $mimes = array();

    /**
     * Set Google storage service object
     *
     * @param \Google_Service_Storage $service Google storage service object
     *
     * @return void
     */
    public static function setService(\Google_Service_Storage $service)
    {
        static::$service = $service;
    }

    /**
     * Register wrapper
     *
     * @return void
     */
    public static function registerWrapper()
    {
        stream_wrapper_register(static::SCHEME, get_called_class());
    }

    // }}}

    // {{{ Initialization

    /**
     * Constructor
     *
     * @return void
     * @throw  \Exception
     */
    public function __construct()
    {
        if (!static::$service) {
            throw new \Exception('Sevice did not set!');
        }
    }

    // }}}

    // {{{ Directory wrapper

    /**
     * mkdir() wrapper
     *
     * @param string  $path    Directory path
     * @param integer $mode    Permission mode
     * @param integer $options Options
     *
     * @return boolean
     */
    public function mkdir($path, $mode, $options)
    {
        $parsed = parse_url($path);
        $this->bucket = $parsed['host'];
        $this->path = substr($parsed['path'], 1);

        $postBody = new Google_Service_Storage_StorageObject();
        $postBody->setName($this->path);
        $postBody->setSize(0);

        $created = static::$service->objects->insert($this->bucket, $postBody, array(
            'uploadType' => 'media',
            'projection' => 'full',
            'data' => '',
            'ifGenerationMatch' => 0,
        ));

        return isset($created);
    }

    /**
     * opendir() wrapper
     *
     * @param string  $path    Directory patrh
     * @param integer $options Options NOT SUPPORT
     *
     * @return boolean
     */
    public function dir_opendir($path, $options)
    {
        try {
            $dir = $this->getItemByPath($this->parse_URI($path));
        } catch(\Exception $e){
            $dir = null;
        }
        $this->dir = $dir;

        return isset($dir);
    }

    /**
     * readdir() wrapper
     *
     * @return string
     */
    public function dir_readdir()
    {
        if (!isset($this->dirItems)) {
            $this->dirItems = $this->getSubitems($this->dir);
        }




        $item = each($this->dirItems);
        if (!$item[1]) {
            return false;
        }

        return ($item && !empty($item[1]))
            ?
            $item[1] : null;
    }

    /**
     * rmdir() wrapper
     *
     * @param string  $path    Directory patrh
     * @param integer $options Options NOT SUPPORT
     *
     * @return boolean
     */
    public function rmdir($path, $options)
    {
        $parsed = parse_url($path);
        $this->bucket = $parsed['host'];
        $this->path = substr($parsed['path'], 1);

        $deleted = static::$service->objects->delete($this->bucket, $this->path);
        return $deleted;
    }

    /**
     * stat() wrapper
     *
     * @param string  $path  Path
     * @param integer $flags Flags NOT SUPPORT
     *
     * @return array
     */
    public function url_stat($path, $flags)
    {
        $file = $this->getItemByPath($this->parse_URI($path));

        return $file ? $this->getStat($file) : false;
    }

    // }}}

    // {{{ File wrapper

    /**
     * fopen() wrapper
     *
     * @param string  $path    Directory patrh
     * @param string  $mode    File open mode
     * @param integer $options Options NOT SUPPORT
     *
     * @return boolean
     */
    public function stream_open($path, $mode, $options) {
        $this->filePosition = 0;
        try{
            $file = $this->getItemByPath($this->parse_URI($path));
        } catch(\Exception $e) {
            $file = null;
        }


        if ($file && 'x' == substr($mode, 0, 1)) {
            $file = null;

        }

        if($file) {
            $this->fileMode = $mode;
            $this->file = $file;
        }

        return isset($file);
    }

    /**
     * fread() wrapper
     *
     * @param integer $count Chunk lenght
     *
     * @return string
     */
    public function stream_read($count)
    {
        if($this->file) {
            if (0 < $count) {
                $result = substr($this->downloadFile(), $this->filePosition, $count);
                $this->filePosition += $count;
            }
        } else {
            $result = '';
        }


        return $result;
    }

    public function stream_write($data)
    {
        $size = 0;
        if ('r' != substr($this->fileMode, 0, 1)) {
            if($this->file && 'w' != substr($this->fileMode, 0, 1)) {
                $this->downloadFile();
            }

            if ('c' == substr($this->fileMode, 0, 1)) {
                $this->fileBody = substr($this->fileBody, 0, $this->filePosition) . $data . substr($this->fileBody, $this->filePosition + strlen($data));

            } else {
                $this->fileBody .= $data;
            }

            $this->filePosition += strlen($data);




            $this->file = $this->file
                ? $this->UpdateFile()
                : $this->InsertFile();
            $size = strlen($data);
        }

        return $size;
    }

    /**
     * fstat() wrapper
     *
     * @return array
     */
    public function stream_stat()
    {
        return false;
    }

    /**
     * fclose() wrapper
     *
     * @return boolean
     */
    public function close()
    {
        $this->file = null;
        $this->path = null;
        $this->fileBody = null;

        return true;
    }

    /**
     * unlink() wrapper
     *
     * @param string $path Path
     *
     * @return boolean
     */
    public function unlink($path)
    {
        $result = false;

        $file = $this->getItemByPath($this->parse_URI($path));
        if ($file) {
            $this->DeleteFile();
            $result = true;
        }

        return $result;
    }

    /**
     * rename() wrapper
     *
     * @param string $path_from Path (from)
     * @param string $path_to   Path (to)
     *
     * @return boolean
     */
    public function rename($path_from, $path_to)
    {
        $result = false;

        if (file_exists($path_from) && !file_exists($path_to)) {
            $file = $this->getItemByPath($this->parse_URI($path_from));
            $this->file = $file;
            $this->downloadFile();
            $this->DeleteFile();
            $this->parse_URI($path_to);
            $this->InsertFile();
            $result = $result;
//
        }

        return $result;
    }

    // }}}

    // {{{ Other function

    /**
     * Get file statistics
     *
     * @param \Google_Service_Storage_StorageObject $file File
     *
     * @return array
     */
    protected function getStat(\Google_Service_Storage_StorageObject $file)
    {
        $result = array(
            0,
            0,
            $this->isDir($file) ? 0040600 : 0100600,
            0,
            current($file->getOwner()),
            0,
            0,
            $file->getSize(),
            time(),
            $file->getUpdated() ? strtotime($file->getUpdated()) : time(),
            $file->getTimeCreated() ? strtotime($file->getTimeCreated()) : time(),
            -1,
            -1,
        );

        $result['dev']     = $result[0];
        $result['ino']     = $result[1];
        $result['mode']    = $result[2];
        $result['nlink']   = $result[3];
        $result['uid']     = $result[4];
        $result['gid']     = $result[5];
        $result['rdev']    = $result[6];
        $result['size']    = $result[7];
        $result['atime']   = $result[8];
        $result['mtime']   = $result[9];
        $result['ctime']   = $result[10];
        $result['blksize'] = $result[11];
        $result['blocks']  = $result[12];

        return $result;
    }

    /**
     * Check - specified file is file or directory
     *
     * @param \Google_Service_Storage_StorageObject $file File
     *
     * @return boolean
     */
    protected function isDir(\Google_Service_Storage_StorageObject $file)
    {
        $name = $file->getName();
        if(substr($name, -1)=='/')return true;
        return false;
    }

    /**
     * Detect MIME type
     *
     * @param string $path Path
     *
     * @return string
     */
    protected function detectMimetype($path)
    {
        return empty(static::$mimes[$path]) ? 'text/plain' : static::$mimes[$path];
    }

    protected function DeleteFile() {
        static::$service->objects->delete($this->bucket, $this->path);
    }

    protected  function UpdateFile() {
        $this->DeleteFile();
        $this->InsertFile();
        return true;
    }

    protected function InsertFile() {
        $postBody = new Google_Service_Storage_StorageObject();
        $postBody->setName($this->path);
        return static::$service->objects->insert($this->bucket, $postBody, array('data' => $this->fileBody, 'mimeType' => $this->detectMimetype($this->filePath), 'uploadType' => 'multipart'));
    }

    protected function getItemByPath($path) {
        try {
            $file = static::$service->objects->get($this->bucket, $path);
        } catch(\Exception $e) {
            return false;
        }
        return $file;
    }

    protected function parse_URI($path) {
        $parsed = parse_url($path);
        $this->bucket = $parsed['host'];
        $this->path = substr($parsed['path'], 1);
        return $this->path;
    }

    protected function downloadFile() {
        if (!isset($this->fileBody)) {
            $httpClient = new GuzzleHttp\Client();
            static::$service->getClient()->authorize($httpClient);
            $request = $httpClient->createRequest('GET', $this->file->mediaLink);
            $response = $httpClient->send($request);

            if ($response->getStatusCode() == 200) {
                $this->fileBody = $response->getBody();
            }
        } else {
            $this->fileBody = false;
        }
        return $this->fileBody;
    }

    protected  function getSubitems($file) {
        $array_filter = array();
        $array_filter['delimiter'] = '/';
        $array_filter['prefix'] = $file->getName();
        $array_filter['projection'] = 'full';

        $subitem = static::$service->objects->listObjects($this->bucket, $array_filter);
        $list_file = array();
        foreach($subitem->getItems() as $value) {
            if($value->getName()!=$array_filter['prefix'])
                $list_file[] = str_replace($array_filter['prefix'],'',$value->getName());
        }
        foreach($subitem->getPrefixes() as $value) {
            $list_file[] = str_replace($array_filter['prefix'],'',$value);
        }
        return $list_file;
    }

    // }}}
}
