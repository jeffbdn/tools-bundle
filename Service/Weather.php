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
    private $refresh;
    private $fs;

    // todo put those in config file : currently service.yml
    public function __construct($apikey, $apiurlbase, $cachedir, $cachefilepath, $refresh){
        $this->subjectCache  = 'weather';
        $this->entryCache    = 'weather';
        $this->apiKey        = $apikey;
        $this->apiUrlBase    = $apiurlbase;
        $this->cachedir      = $cachedir;
        $this->cachefilepath = $cachefilepath;
        $this->refresh       = $refresh;
        $this->fs            = new Filesystem();
    }

    public function broadcast($location)
    {
        if ($weather = $this->getDataFromCache()) {
            $dateNow = date_create();
            $dateThen = date_create($weather['date']);
            if (date_diff($dateThen,$dateNow)->format('%d') < $this->refresh) return $weather;
        }
        // renew value in cache
        $weather = $this->callWeatherAPI($location);
        $this->majDataInCache($weather);
        return $weather;
    }

    // todo see implementation via guzzle
    public function callWeatherAPI($location){

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $this->apiUrlBase.$location);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: ' . $this->apiKey));
        $jsonWeather = \curl_exec($ch);

        $arrayWeather = json_decode($jsonWeather, true);

        $result = array();

        $result['date']                  = date("Y-m-d H:i:s");
        $result['temp_k']                = $arrayWeather['main']['temp'];
        $result['temp_k_min']            = $arrayWeather['main']['temp_min'];
        $result['temp_k_max']            = $arrayWeather['main']['temp_max'];
        $result['temp_c']                = $arrayWeather['main']['temp'] - 272.15;
        $result['temp_c_min']            = $arrayWeather['main']['temp_min'] - 272.15;
        $result['temp_c_max']            = $arrayWeather['main']['temp_max'] - 272.15;
        $result['temp_f']                = (($arrayWeather['main']['temp']-272.15)*1.8)+32;
        $result['temp_f_min']            = (($arrayWeather['main']['temp_min']-272.15)*1.8)+32;
        $result['temp_f_max']            = (($arrayWeather['main']['temp_max']-272.15)*1.8)+32;
        $result['humidity']              = $arrayWeather['main']['humidity'];
        $result['sky_description_short'] = $arrayWeather['weather'][0]['main'];
        $result['sky_description_long']  = $arrayWeather['weather'][0]['description'];
        $result['pressure_hpa']          = $arrayWeather['main']['pressure'];
        $result['sunrise']               = date("Y-m-d H:i:s", $arrayWeather['sys']['sunrise']);
        $result['sunset']                = date("Y-m-d H:i:s", $arrayWeather['sys']['sunset']);
        $result['ok']                    = ($arrayWeather['cod'] != 200) ? false : true;
        $result['error_code']            = $arrayWeather['cod'];
        $result['error_string']          = ($arrayWeather['cod'] == 200) ? '' : $arrayWeather['message'];

        return $result;
    }

    public function checkCacheFileExists(){

        // if no cache dir, create one
        if (!$this->fs->exists($this->cachedir) && !is_dir($this->cachedir)) {
            try {
                mkdir($this->cachedir,0777);
            } catch (IOException $e){
                throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');
            }
        }

        // if no cache file, touch one and return false
        if (!$this->fs->exists($this->cachefilepath)){
            try {
                $this->fs->dumpFile($this->cachefilepath,'{"test1": "test2"}');
            } catch (IOException $e){
                throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');
            }
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
            } catch (IOException $e){
                throw new IOException('cannot write in cache for jeffbdn:toolsbundle');
            }
        }
    }

    public function getJsonFileDataAsArray($path){
        if (empty($filedata = file_get_contents($path))) return false;
        $res = json_decode($filedata, true);
        return !is_null($res) ? $res : false;
    }
}