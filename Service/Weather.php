<?php
namespace JeffBdn\ToolsBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Service that provides a broadcast for a named city
 * @author Jean-Francois BAUDRON <jeanfrancois.baudron@free.fr>
 */
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

    /**
     * Main Method of the Service
     * returns array with weather data from given location
     * if provided $location is not formatted as precised below, returns array with errors
     * if cached data is too old regarding to configured refresh time, it is renewed.
     * @param string $location ref location - format "CityName,ISOCountry" like "Paris,fr"
     * @return array
     * todo offer option to force renewing of the cache if wanted
     */
    public function broadcast($location)
    {
        if (! $this->checkLocation($location)) return array(
            'ok' => false,
            'error_code' => '000',
            'error_message' => 'location contains invalid characters: brackets, semi-colom...');

        if ($weather = $this->getDataFromCache()) {
            $dateExpire = date_create("- ".$this->refresh);
            $dateThen = date_create($weather['date']);
            if ($dateThen > $dateExpire) return $weather;
        }

        // renew value in cache
        $weather = $this->callWeatherAPI($location);
        $this->updateDataInCache($weather);
        return $weather;
    }

    /**
     * Call the OpenWeatherMaps API for given $location
     * and returns an array with explicited collected data
     * @param string $location
     * @return array
     */
    // todo see implementation via guzzle
    public function callWeatherAPI($location){

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $this->apiUrlBase.$location);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: ' . $this->apiKey));
        $jsonWeather = \curl_exec($ch);

        $arrayWeather = json_decode($jsonWeather, true);

        $result = array();

        $result['date']                   = date("Y-m-d H:i:s");
        $result['temp_k']                 = $arrayWeather['main']['temp'];
        $result['temp_k_min']             = $arrayWeather['main']['temp_min'];
        $result['temp_k_max']             = $arrayWeather['main']['temp_max'];
        $result['temp_c']                 = $arrayWeather['main']['temp'] - 272.15;
        $result['temp_c_min']             = $arrayWeather['main']['temp_min'] - 272.15;
        $result['temp_c_max']             = $arrayWeather['main']['temp_max'] - 272.15;
        $result['temp_f']                 = (($arrayWeather['main']['temp']-272.15)*1.8)+32;
        $result['temp_f_min']             = (($arrayWeather['main']['temp_min']-272.15)*1.8)+32;
        $result['temp_f_max']             = (($arrayWeather['main']['temp_max']-272.15)*1.8)+32;
        $result['humidity_percent']       = $arrayWeather['main']['humidity'];
        $result['sky_description_short']  = $arrayWeather['weather'][0]['main'];
        $result['sky_description_long']   = $arrayWeather['weather'][0]['description'];
        $result['pressure_hpa']           = $arrayWeather['main']['pressure'];
        $result['wind_speed_metersec']    = $arrayWeather['wind']['speed'];
        $result['wind_direction_degrees'] = $arrayWeather['wind']['deg'];
        $result['cloud_percent']          = $arrayWeather['clouds']['all'];
        $result['sunrise']                = date("Y-m-d H:i:s", $arrayWeather['sys']['sunrise']);
        $result['sunset']                 = date("Y-m-d H:i:s", $arrayWeather['sys']['sunset']);
        $result['ok']                     = ($arrayWeather['cod'] != 200) ? false : true;
        $result['error_code']             = $arrayWeather['cod'];
        $result['error_string']           = ($arrayWeather['cod'] == 200) ? '' : $arrayWeather['message'];

        return $result;
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
                $this->fs->dumpFile($this->cachefilepath,'{"test1": "test2"}');
            } catch (IOException $e){
                throw new IOException('cannot create cache directory for jeffbdn:toolsbundle');
            }
            return false;
        }

        return true;
    }

    /**
     * Get Broadcast data from cache
     * @return bool
     * @return array
     * todo make it possible to store several broadcast for several locations
     */
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

    /**
     * Update Broadcast Data in Cache
     * @param string $data
     * @throws IOException
     * todo make it possible to update specific location data
     */
    public function updateDataInCache($data=''){

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

    /**
     * Get data from JSON file and returns it as an array
     * @param $path
     * @return bool
     * @return array
     */
    public function getJsonFileDataAsArray($path){
        if (empty($filedata = file_get_contents($path))) return false;
        $res = json_decode($filedata, true);
        return !is_null($res) ? $res : false;
    }

    /*
     * solution found on https://stackoverflow.com/questions/5963228/regex-for-names-with-special-characters-unicode
     * to be read like:
     * ^   # start of subject
        (?:     # match this:
            [           # match a:
                \p{L}       # Unicode letter, or
                \p{Mn}      # Unicode accents, or
                \p{Pd}      # Unicode hyphens, or
                \'          # single quote, or
                \x{2019}    # single quote (alternative)
            ]+              # one or more times
            \s          # any kind of space
            [               #match a:
                \p{L}       # Unicode letter, or
                \p{Mn}      # Unicode accents, or
                \p{Pd}      # Unicode hyphens, or
                \'          # single quote, or
                \x{2019}    # single quote (alternative)
            ]+              # one or more times
            \s?         # any kind of space (0 or more times)
        )+      # one or more times
        $   # end of subject

        NOTE: this version do not support city names with apostrophes in their names, like
            Muḩāfaz̧at al ‘Āşimah,kw
     */
    /**
     * Validate Location format
     * "CityName,ISOCountry" like "Paris, fr"
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