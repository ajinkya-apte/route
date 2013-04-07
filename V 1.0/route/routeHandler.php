<?php
/**
 * Route - PHP REST Framework
 *
 * @author: Ajinkya Apte
 * @license: Mozilla Public License Version 2.0
 * @licenseFileLocation: route/License.txt
 * @codeRepository: https://github.com/ajinkya-apte/route.git
 *
 * @file: A handler to the RouteLib class
 * Date: 3/23/13
 * Time: 12:32 PM
 */
require_once(dirname(__FILE__).'/config/config.php');
require_once(dirname(__FILE__).'/lib/RouteLib.class.php');

function run($url, $port, $getFromRouteConfigFile=true) {
    $routeLib = new RouteLib($url, $port, populateFromRouteConfig($getFromRouteConfigFile));
    $routeLib->run();

}

function populateFromRouteConfig($getFromRouteConfigFile=true) {
    $routeConfig = array(
        'routeHttpProtocols'=>'GET,POST,PUT,DELETE',
        'routeClassName'=>'Route'
    );

    if(!$getFromRouteConfigFile) {
        return $routeConfig;
    }

    if(!file_exists(dirname(__FILE__).ROUTE_CONFIG)) {
        header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_CONFIG_MISSING, true, 500);
        exit(0);
    }

    $routeConfigFH = fopen(dirname(__FILE__).ROUTE_CONFIG,'r');
    while(!feof($routeConfigFH)) {
        $line = fgets($routeConfigFH);
        if(strpos($line, ROUTE_CONFIG_COMMENT) === false) {
            if(strpos($line, ROUTE_CONFIG_HTTP_PROTOCOLS_VAR) !== false) {
                $routeConfigLandingPageArray = explode('=', $line);
                if(!isset($routeConfigLandingPageArray[1])) {
                    fclose($routeConfigFH);
                    header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR, true, 500);
                    exit(0);
                }
                $routeConfig['routeHttpProtocols'] = $routeConfigLandingPageArray[1];
            }
            else if(strpos($line, ROUTE_CONFIG_LANDING_PAGE_VAR) !== false) {
                $routeConfigLandingPageArray = explode('=', $line);
                if(!isset($routeConfigLandingPageArray[1]) || (isset($routeConfigLandingPageArray[1]) && !file_exists(dirname(__FILE__).'/../'.$routeConfigLandingPageArray[1]))) {
                    fclose($routeConfigFH);
                    header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR, true, 500);
                    exit(0);
                }
                require_once(dirname(__FILE__).'/../'.$routeConfigLandingPageArray[1]);
                $routeConfig['routeClassName'] = $routeConfigLandingPageArray[1];
            }
        }
    }
    fclose($routeConfigFH);
    return $routeConfig;
}