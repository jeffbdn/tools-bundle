<?php
namespace JeffBdn\ToolsBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Service that provides a broadcast for a named city
 * Uses OpenWeatherMaps API https://openweathermap.org
 * @author Jean-Francois BAUDRON <jeanfrancois.baudron@free.fr>
 * todo correct all obsolescences
 */
class Weather
{

    private $apiKey;
    private $apiUrlBase;
    private $cachedir;
    private $cachefilepath;
    private $refresh;
    private $fs;

    /**
     * Weather constructor.
     * @param string $apikey - key used for calling OpenWeatherMaps API
     * @param string $apiurlbase - base url for calling OpenWeatherMaps API
     * @param string $cachedir - path to cache directory
     * @param string $cachefilepath - path to cache file
     * @param string $refresh - time interval before refreshing cached data
     * todo put those in config file : currently service.yml
     */
    public function __construct($apikey, $apiurlbase, $cachedir, $cachefilepath, $refresh){
        $this->setApiKey($apikey);
        $this->setApiUrlBase($apiurlbase);
        $this->setCachedir($cachedir);
        $this->setCachefilepath($cachefilepath);
        $this->setRefresh($refresh);
        $this->setFs(new Filesystem());
    }

    /**
     * Main Method of the Service
     * returns array with weather data from given location
     * if provided $location is not formated as precised below, returns array with errors
     * if cached data is too old regarding to configured refresh time, it is renewed.
     * @param string $location ref location - format "CityName,ISOCountry" like "Paris,fr"
     * @return array $weather
     */
    public function broadcast($location)
    {
        if (! $this->checkLocation($location)) return array(
            'ok' => false,
            'error_code' => '000',
            'error_message' => 'location contains invalid characters: brackets, semi-colom...');

        if ($weather = $this->getDataFromCache($location)) {
            $dateExpire = date_create("- ".$this->getRefresh());
            $dateThen = date_create($weather['date']);
            if ($dateThen > $dateExpire) return $weather;
        }

        // renew value in cache
        $weather = $this->formatWeatherDataFromAPI($this->callWeatherAPI($location));
        $this->updateDataInCache($weather, $location);
        return $weather;
    }

    /**
     * Call the OpenWeatherMaps API for given $location
     * and returns an array with raw collected data
     * @param string $location
     * @return array
     * based on OpenWeatherMaps API version as of 2017.08.09
     */
    // todo see implementation via guzzle
    public function callWeatherAPI($location)
    {
        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $this->getApiUrlBase() . $location);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: ' . $this->getApiKey()));
        $jsonWeather = \curl_exec($ch);
        return json_decode($jsonWeather, true);
    }

    /**
     * Format raw data from API call to an array with an  easier-to-read arborescence
     * @param array $arrayWeather
     * @return array $formatedWeatherData
     */
    public function formatWeatherDataFromAPI(array $arrayWeather){

        $formatedWeatherData = array();

        $formatedWeatherData['date']                   = date("Y-m-d H:i:s");
        $formatedWeatherData['temp_k']                 = $arrayWeather['main']['temp'];
        $formatedWeatherData['temp_k_min']             = $arrayWeather['main']['temp_min'];
        $formatedWeatherData['temp_k_max']             = $arrayWeather['main']['temp_max'];
        $formatedWeatherData['temp_c']                 = $arrayWeather['main']['temp'] - 272.15;
        $formatedWeatherData['temp_c_min']             = $arrayWeather['main']['temp_min'] - 272.15;
        $formatedWeatherData['temp_c_max']             = $arrayWeather['main']['temp_max'] - 272.15;
        $formatedWeatherData['temp_f']                 = (($arrayWeather['main']['temp']-272.15)*1.8)+32;
        $formatedWeatherData['temp_f_min']             = (($arrayWeather['main']['temp_min']-272.15)*1.8)+32;
        $formatedWeatherData['temp_f_max']             = (($arrayWeather['main']['temp_max']-272.15)*1.8)+32;
        $formatedWeatherData['humidity_percent']       = $arrayWeather['main']['humidity'];
        $formatedWeatherData['sky_description_short']  = $arrayWeather['weather'][0]['main'];
        $formatedWeatherData['sky_description_long']   = $arrayWeather['weather'][0]['description'];
        $formatedWeatherData['pressure_hpa']           = $arrayWeather['main']['pressure'];
        $formatedWeatherData['wind_speed_metersec']    = $arrayWeather['wind']['speed'];
        $formatedWeatherData['cloud_percent']          = $arrayWeather['clouds']['all'];
        $formatedWeatherData['sunrise']                = date("Y-m-d H:i:s", $arrayWeather['sys']['sunrise']);
        $formatedWeatherData['sunset']                 = date("Y-m-d H:i:s", $arrayWeather['sys']['sunset']);
        $formatedWeatherData['ok']                     = ($arrayWeather['cod'] != 200) ? false : true;
        $formatedWeatherData['error_code']             = $arrayWeather['cod'];
        $formatedWeatherData['error_string']           = ($arrayWeather['cod'] == 200) ? '' : $arrayWeather['message'];

        return $formatedWeatherData;
    }

    /**
     * Check if cache file for JeffBdnToolsBundle's Weather Service exists
     * if not, create it
     * @return bool
     * @throws IOException
     */
    public function checkCacheFileExists(){

        // if no cache dir, create one
        if (!$this->getFs()->exists($this->getCachedir()) && !is_dir($this->getCachedir())) {
            try {
                mkdir($this->getCachedir(),0777);
            } catch (IOException $e){
                throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');
            }
        }

        // if no cache file, touch one and return false
        if (!$this->getFs()->exists($this->getCachefilepath())){
            try {
                $this->getFs()->dumpFile($this->getCachefilepath(),'');
            } catch (IOException $e){
                throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');
            }
            return false;
        }
        return true;
    }

    /**
     * Get Broadcast data from cache for a location
     * If data is not present in cache file, return false
     * @param $location
     * @return bool
     * @return array
     */
    public function getDataFromCache($location){
        if ($this->checkCacheFileExists()){
            if ($cachefiledata = $this->getJsonFileDataAsArray($this->getCachefilepath()))
                if (array_key_exists($location, $cachefiledata))
                    return $cachefiledata[$location];
            return false;
        } else return false;
    }

    /**
     * Update Broadcast Data in Cache
     * @param string $data
     * @param string $location
     * @throws IOException
     */
    public function updateDataInCache($data='', $location){

        if ($this->checkCacheFileExists()){

            // if cache file is not empty
            if ($cachefiledata = $this->getJsonFileDataAsArray($this->getCachefilepath()))
                $cachefiledata[$location] = $data;
            else $cachefiledata = [$location => $data];

            try {
                $this->getFs()->dumpFile($this->getCachefilepath(), json_encode($cachefiledata));
            } catch (IOException $e){
                throw new IOException('cannot write in cache for jeffbdn:toolsbundle');
            }
        }
    }

    /**
     * Get data from JSON file and returns it as an array
     * @param $path
     * @return bool
     * @return array
     */
    public function getJsonFileDataAsArray($path)
    {
        if (empty($filedata = file_get_contents($path))) return false;
        $res = json_decode($filedata, true);
        return !is_null($res) ? $res : false;
    }

    /**
     * Validate Location format
     * "CityName,ISOCountry" like "Paris,fr"
     * regexp found on https://stackoverflow.com/questions/5963228/regex-for-names-with-special-characters-unicode
     * NOTE: this version do not support city names with apostrophes in their names,
     * like << Muḩāfaz̧at al ‘Āşimah,kw >>
     * @param $location
     * @return bool
     */
    public function checkLocation($location){
        $regexp = '~^[\p{L}\p{Mn}\p{Pd}\'\x{2019}\s]+$~u';
        $tmp = explode(',', $location);
        $check = (strlen($tmp[1]) === 2) && (preg_match($regexp, $tmp[0]));
        return $check ? true : false;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return string
     */
    public function getApiUrlBase()
    {
        return $this->apiUrlBase;
    }

    /**
     * @param string $apiUrlBase
     */
    public function setApiUrlBase($apiUrlBase)
    {
        $this->apiUrlBase = $apiUrlBase;
    }

    /**
     * @return string
     */
    public function getCachedir()
    {
        return $this->cachedir;
    }

    /**
     * @param string $cachedir
     */
    public function setCachedir($cachedir)
    {
        $this->cachedir = $cachedir;
    }

    /**
     * @return string
     */
    public function getCachefilepath()
    {
        return $this->cachefilepath;
    }

    /**
     * @param string $cachefilepath
     */
    public function setCachefilepath($cachefilepath)
    {
        $this->cachefilepath = $cachefilepath;
    }

    /**
     * @return string
     */
    public function getRefresh()
    {
        return $this->refresh;
    }

    /**
     * @param string $refresh
     */
    public function setRefresh($refresh)
    {
        $this->refresh = $refresh;
    }

    /**
     * @return Filesystem
     */
    public function getFs()
    {
        return $this->fs;
    }

    /**
     * @param Filesystem $fs
     */
    public function setFs($fs)
    {
        $this->fs = $fs;
    }
}