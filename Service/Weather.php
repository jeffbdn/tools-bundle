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
     * @param $apikey key used for calling OpenWeatherMaps API
     * @param $apiurlbase base url for calling OpenWeatherMaps API
     * @param $cachedir path to cache directory
     * @param $cachefilepath path to cache file
     * @param $refresh time interval before refreshing cached data
     * todo put those in config file : currently service.yml
     */
    public function __construct($apikey, $apiurlbase, $cachedir, $cachefilepath, $refresh){
        $this->apiKey        = $apikey;
        $this->apiUrlBase    = $apiurlbase;
        $this->cachedir      = $cachedir;
        $this->cachefilepath = $cachefilepath;
        $this->refresh       = $refresh;
        $this->fs            = new Filesystem();
    }

    /**
     * Main Method of the Service
     * returns array with weather data from given location
     * if provided $location is not formated as precised below, returns array with errors
     * if cached data is too old regarding to configured refresh time, it is renewed.
     * @param string $location ref location - format "CityName,ISOCountry" like "Paris,fr"
     * @return array
     */
    public function broadcast($location)
    {
        if (! $this->checkLocation($location)) return array(
            'ok' => false,
            'error_code' => '000',
            'error_message' => 'location contains invalid characters: brackets, semi-colom...');

        if ($weather = $this->getDataFromCache($location)) {
            $dateExpire = date_create("- ".$this->refresh);
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
        \curl_setopt($ch, CURLOPT_URL, $this->apiUrlBase . $location);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: ' . $this->apiKey));
        $jsonWeather = \curl_exec($ch);
        return json_decode($jsonWeather, true);
    }

    /**
     * Format raw data from API call to an array with an  easier-to-read arborescence
     * @param array $arrayWeather
     * @return array $formatedWeatherData
     */
    // todo update README.md : no more wind.deg
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
                $this->fs->dumpFile($this->cachefilepath,'');
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
     * @return bool
     * @return array
     */
    public function getDataFromCache($location){
        if ($this->checkCacheFileExists()){
            if ($cachefiledata = $this->getJsonFileDataAsArray($this->cachefilepath))
                if (array_key_exists($location, $cachefiledata))
                    return $cachefiledata[$location];
            return false;
        } else return false;
    }

    /**
     * Update Broadcast Data in Cache
     * @param string $data
     * @throws IOException
     */
    public function updateDataInCache($data='', $location){

        if ($this->checkCacheFileExists()){

            // if cache file is not empty
            if ($cachefiledata = $this->getJsonFileDataAsArray($this->cachefilepath))
                $cachefiledata[$location] = $data;
            else $cachefiledata = [$location => $data];

            try {
                $this->fs->dumpFile($this->cachefilepath, json_encode($cachefiledata), 0777);
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
     * "CityName,ISOCountry" like "Paris, fr"
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
}