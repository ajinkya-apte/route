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
        'routeClassName'=>'Route',
        'timerCallback'=>'Util.logTime'
    );

    if(!$getFromRouteConfigFile) {
        if(!verifyRouteConfigArray($routeConfig)) {
            header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR, true, 500);
            exit(0);
        }
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
                $routeConfig[ROUTE_CONFIG_HTTP_PROTOCOLS_VAR] = $routeConfigLandingPageArray[1];
            }
            else if(strpos($line, ROUTE_CONFIG_CLASS_NAME_VAR) !== false) {
                $routeConfigLandingPageArray = explode('=', $line);
                if(!isset($routeConfigLandingPageArray[1])) {
                    fclose($routeConfigFH);
                    header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR, true, 500);
                    exit(0);
                }
                $routeConfig[ROUTE_CONFIG_CLASS_NAME_VAR] = $routeConfigLandingPageArray[1];
            }
            else if(strpos($line, ROUTE_CONFIG_TIMER_CALLBACK_VAR) !== false) {
                $routeConfigLandingPageArray = explode('=', $line);
                if(!isset($routeConfigLandingPageArray[1])) {
                    fclose($routeConfigFH);
                    header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR, true, 500);
                    exit(0);
                }
                $routeConfig[ROUTE_CONFIG_TIMER_CALLBACK_VAR] = $routeConfigLandingPageArray[1];
            }
        }
    }
    fclose($routeConfigFH);
    if(!verifyRouteConfigArray($routeConfig)) {
        header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR, true, 500);
        exit(0);
    }
    return $routeConfig;
}

function checkForMethodFunctionValidity($variable) {
    if(strpos($variable, ".") !== false) {
        $classMethodArray = explode(".", $variable);
        if(!isset($classMethodArray[0]) || !isset($classMethodArray[1]) || !method_exists($classMethodArray[0], $classMethodArray[1])) {
            return false;
        }
    }
    elseif(!function_exists($variable)) {
        return false;
    }
    return true;
}

function verifyRouteConfigArray($routeConfig) {
    //Check required parameters
    if(!isset($routeConfig[ROUTE_CONFIG_HTTP_PROTOCOLS_VAR]) || !isset($routeConfig[ROUTE_CONFIG_CLASS_NAME_VAR]) || !class_exists($routeConfig[ROUTE_CONFIG_CLASS_NAME_VAR])) {
        return false;
    }
    //Check optional parameters
    if(isset($routeConfig[ROUTE_CONFIG_TIMER_CALLBACK_VAR]) && !checkForMethodFunctionValidity($routeConfig['timerCallback'])) {
        return false;
    }
    return true;
}