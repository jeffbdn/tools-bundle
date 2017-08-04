<?php
namespace JeffBdn\ToolsBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

class Weather
{
    private $subjectCache;
    private $entryCache;
    private $apiKey;
    private $apiUrlBase;
    private $cachedir;
    private $cachefilepath;
    private $fs;

    // todo put those in config file
    public function __construct(){
        $this->subjectCache = 'weather';
        $this->entryCache = 'weather';
        $this->apiKey = 'MY KEY';
        $this->apiUrlBase = 'http://api.openweathermap.org/data/2.5/weather?q=';
        //$this->cachedir = $this->container->get('kernel')->getCacheDir().'/jeffbdntoolsbundle';
        $this->cachedir = 'cachedir path';
        $this->cachefilepath = $this->cachedir.'/jeffbdntoolsbundle/weather.json';
        $this->fs = new Filesystem();
    }

    public function weather($location){

        if ($weather = $this->getDataFromCache()) return $weather;
        else {
            // renew value in cache
            $weather = $this->callWeatherAPI($location);
            $this->majDataInCache($weather);
            return $weather;
        }
    }

    // todo see implementation via guzzle
    public function callWeatherAPI($location){

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $this->apiUrlBase.$location);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: ' . $this->apiKey));
        $jsonWeather = \curl_exec($ch);

        $arrayWeather = json_decode($jsonWeather, true);

        return $arrayWeather;
    }

    public function checkCacheFileExists(){

        // if no cache dir, create one
        if (!$this->fs->exists($this->cachedir) && !is_dir($this->cachedir)) {
            try {
                mkdir($this->cachedir,0777);
            } catch (IOException $e){ throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');}}

        // if no cache file, touch one and return false
        if (!$this->fs->exists($this->cachefilepath)){
            try {
                $this->fs->dumpFile($this->cachefilepath,'{"test1": "test2"}');
            } catch (IOException $e){ throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');}
            return false;
        }

        return true;
    }

    public function getDataFromCache(){

        if ($this->checkCacheFileExists()){
            // get data from cache file
            if ($cachefiledata = $this->getJsonFileDataAsArray($this->cachefilepath)) {
                if ($this->entryCache){
                    if (array_key_exists($this->entryCache, $cachefiledata)) return $cachefiledata[$this->entryCache];
                } else {
                    return $cachefiledata;
                }
            } else return false;
        }
    }

    public function majDataInCache($data=''){

        if ($this->checkCacheFileExists()){

            // if cache file is not empty
            if ($cachefiledata = $this->getJsonFileDataAsArray($this->cachefilepath)) {
                $cachefiledata[$this->entryCache] = $data;
            }
            // if cache file is empty
            else $cachefiledata = [$this->entryCache => $data];

            try {
                $this->fs->dumpFile($this->cachefilepath, json_encode($cachefiledata), 0777);
            } catch (IOException $e){throw new IOException('cannot write in cache for jeffbdn:toolsbundle');}
        }
    }

    public function getJsonFileDataAsArray($path){
        if (empty($filedata = file_get_contents($path))) return false;
        $res = json_decode($filedata, true);
        return !is_null($res) ? $res : false;
    }
}