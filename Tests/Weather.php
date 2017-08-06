<?php

namespace JeffBdn\ToolsBundle\Tests\Service;

use JeffBdn\ToolsBundle\Service\Weather;

/*
 * test this class with the following command:
 * phpunit vendor/jeffbdn/tools-bundle/JeffBdn/ToolsBundle/Tests/Weather.php
 */
class WeatherTest extends \PHPUnit_Framework_TestCase
{
    private $weather;

    public function __construct(){
        parent::__construct();

        $this->weather = new Weather(
            '26142b071b1d6e8839235a01803a0a08',
            'http://api.openweathermap.org/data/2.5/weather?q=',
            '/home/jeff/DATA/Projets/dummyshop_sf2/app/cache/dev/jeffbdntoolsbundle',
            '/home/jeff/DATA/Projets/dummyshop_sf2/app/cache/dev/jeffbdntoolsbundle/weather.json',
            '1 day'
        );
    }

    /*
     * @depends test_ok_broadcast
     */
    public function test_global_broadcast(){}

    /*
     * @depends test_correctKeys_broadcast_
     */
    public function test_ok_broadcast(){
        $location = 'Paris,fr';
        $broadcast = $this->weather->broadcast($location);
        $this->assertTrue($broadcast['ok']);
    }

    /*
     * @depends test_notEmpty_broadcast
     */
    public function test_correctKeys_broadcast(){
        $location = 'Paris,fr';
        $broadcast = $this->weather->broadcast($location);

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
            'wind_direction_degrees',
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

    public function test_notEmpty_broadcast()
    {
        $location = 'Paris,fr';
        $broadcast = $this->weather->broadcast($location);
        $this->assertNotEmpty($broadcast);
    }


}