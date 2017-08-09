<?php

namespace JeffBdn\ToolsBundle\Tests\Service;

use JeffBdn\ToolsBundle\Service\Weather;

/**
 * test this class with the following command:
 * phpunit vendor/jeffbdn/tools-bundle/JeffBdn/ToolsBundle/Tests/Weather.php
 * @author Jean-Francois BAUDRON <jeanfrancois.baudron@free.fr>
 */
class WeatherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Store fresh API call data for tests on current results from OpenWeatherMaps
     * @var $apicalldata
     */
    private static $apicalldata;

    /**
     * ---------- PROVIDERS ----------
     */

    /**
     * @param Weather $weather
     * @return array
     * @large
     */
    public static function getAPIdataProvider(){
        $weather = self::weatherObjProvider()[0][0];
        if (self::$apicalldata == '')
            self::$apicalldata = $weather->callWeatherAPI('Paris,fr');
        return array(
            array(self::$apicalldata)
        );
    }

    public static function weatherObjProvider(){
        return array(array(
            new Weather(
                'MY API KEY',
                'http://api.openweathermap.org/data/2.5/weather?q=',
                '/home/jeff/DATA/Projets/dummyshop_sf2/app/cache/dev/jeffbdntoolsbundle',
                '/home/jeff/DATA/Projets/dummyshop_sf2/app/cache/dev/jeffbdntoolsbundle/weather.json',
                '1 day'
            )));
    }

    /**
     * ---------- TESTS ----------
     */

    /**
     * This test makes an API call
     * @param array $data
     * @dataProvider getAPIdataProvider
     */
    public function test_callWeatherAPI($data){
        $this->assertEquals(200, $data['cod']);
    }



    /**
     * An error here means OpenWeatherMaps changed their JSON format by deleting an entry.
     * check https://openweathermap.org/current on paragraph JSON
     * This test makes an API call
     * @param array $data
     * @depends test_callWeatherAPI
     * @dataProvider getAPIdataProvider
     */
    public function test_formatWeatherDataFromAPI($data)
    {
        $this->assertTrue(isset($data['main']['temp']));
        $this->assertTrue(isset($data['main']['temp_min']));
        $this->assertTrue(isset($data['main']['temp_max']));
        $this->assertTrue(isset($data['main']['humidity']));
        $this->assertTrue(isset($data['weather'][0]['main']));
        $this->assertTrue(isset($data['weather'][0]['description']));
        $this->assertTrue(isset($data['main']['pressure']));
        $this->assertTrue(isset($data['wind']['speed']));
        $this->assertTrue(isset($data['clouds']['all']));
        $this->assertTrue(isset($data['sys']['sunrise']));
        $this->assertTrue(isset($data['sys']['sunset']));
        $this->assertTrue(isset($data['cod']));
        $this->assertFalse(isset($data['message']));
    }

    /**
     * @param Weather $weather
     * @dataProvider weatherObjProvider
     */
    public function test_notEmpty_broadcast($weather)
    {
        $location = 'Paris,fr';
        $broadcast = $weather->broadcast($location);
        $this->assertNotEmpty($broadcast);
    }

    /**
     * @param Weather $weather
     * @depends test_notEmpty_broadcast
     * @dataProvider weatherObjProvider
     */
    public function test_correctKeys_broadcast($weather){
        $location = 'Paris,fr';
        $broadcast = $weather->broadcast($location);

        $required = array(
            'date',
            'temp_k',
            'temp_k_min',
            'temp_k_max',
            'temp_c',
            'temp_c_min',
            'temp_c_max',
            'temp_f',
            'temp_f_min',
            'temp_f_max',
            'humidity_percent',
            'sky_description_short',
            'sky_description_long',
            'pressure_hpa',
            'wind_speed_metersec',
            'cloud_percent',
            'sunrise',
            'sunset',
            'ok',
            'error_code',
            'error_string'
        );

        $this->assertEquals(
            count(array_intersect_key(array_flip($required), $broadcast)),
            count($required)
        );
    }

    /**
     * @param Weather $weather
     * @dataProvider weatherObjProvider
     */
    public function test_checkLocation($weather)
    {
        $this->assertTrue($weather->checkLocation('Paris,fr'));
        $this->assertTrue($weather->checkLocation('Münich,de'));
        $this->assertTrue($weather->checkLocation('State of Haryāna,in'));
        $this->assertTrue($weather->checkLocation('Bāgmatī Zone,np'));
        $this->assertTrue($weather->checkLocation('Mar’ina Roshcha,ru'));
        $this->assertTrue($weather->checkLocation('San Sebastián Municipio,pr'));
        // this city name has an apostrophe in it, currently not supported
        //$this->assertTrue($weather->checkLocation('Muḩāfaz̧at al ‘Āşimah,kw'));
        $this->assertTrue($weather->checkLocation('Fröschen,de'));
    }

    /**
     * @param Weather $weather
     * @depends test_checkLocation
     * @depends test_correctKeys_broadcast
     * @depends test_formatWeatherDataFromAPI
     * @dataProvider weatherObjProvider
     */
    public function test_ok_broadcast($weather){
        $location = 'Paris,fr';
        $broadcast = $weather->broadcast($location);
        $this->assertTrue($broadcast['ok']);
    }

    /**
     * A global test on the broadcast() function,
     * the main function of the Weather Service
     * @depends test_ok_broadcast
     */
    public function test_global_broadcast()
    {
        $this->assertTrue(true);
    }

    /**
     * WeatherTest constructor.
     */
    public function __construct($name = null, array $data = array(), $dataName = ''){
        $this->preSetUp();
        parent::__construct($name, $data, $dataName);
    }

    public function preSetUp(){
        if (!self::$apicalldata)
            self::$apicalldata = '';
    }
}