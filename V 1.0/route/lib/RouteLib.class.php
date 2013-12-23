<?php
/**
 * Route - PHP REST Framework
 *
 * @author: Ajinkya Apte
 * @license: Mozilla Public License Version 2.0
 * @licenseFileLocation: route/License.txt
 * @codeRepository: https://github.com/ajinkya-apte/route.git
 *
 * @file: Main RouteLib class which parses through the class
 *        method annotations and calls the user defined function
 * Date: 3/25/13
 * Time: 11:25 PM
 */

class RouteLib {
    private $url = 'localhost';
    private $port = '80';
    private $routeClass = 'Route';
    private $isGETAllowed = true;
    private $isPOSTAllowed = true;
    private $isPUTAllowed = true;
    private $isDELETEAllowed = true;
    private $shutdownCallback = null;
    private $timerCallback = null;
    private $ignoreURLPORT = false;

    private $requestMethod = null;
    private $requestURI = null;

    function __construct($url, $port, $routeConfig) {
        $this->url = $url;
        $this->port = $port;
        $this->routeClass = $routeConfig[ROUTE_CONFIG_CLASS_NAME_VAR];
        $routeHttpProtocols = explode(",",$routeConfig[ROUTE_CONFIG_HTTP_PROTOCOLS_VAR]);
        if(!in_array("GET", $routeHttpProtocols)) {
            $this->isGETAllowed = false;
        }

        if(!in_array("POST", $routeHttpProtocols)) {
            $this->isPOSTAllowed = false;
        }

        if(!in_array("PUT", $routeHttpProtocols)) {
            $this->isPUTAllowed = false;
        }

        if(!in_array("DELETE", $routeHttpProtocols)) {
            $this->isDELETEAllowed = false;
        }

        if(isset($routeConfig[ROUTE_CONFIG_SHUTDOWN_CALLBACK_VAR])) {
            $this->shutdownCallback = $routeConfig[ROUTE_CONFIG_SHUTDOWN_CALLBACK_VAR];
        }

        if(isset($routeConfig[ROUTE_CONFIG_TIMER_CALLBACK_VAR])) {
            $this->timerCallback = $routeConfig[ROUTE_CONFIG_TIMER_CALLBACK_VAR];
        }

        if(isset($routeConfig[ROUTE_CONFIG_URL_PORT_IGNORE_VAR]) && $routeConfig[ROUTE_CONFIG_URL_PORT_IGNORE_VAR] == "true") {
            $this->ignoreURLPORT = true;
        }
    }

    function checkIfValidRequestAndSetContext() {
        if(!$this->ignoreURLPORT) {
            if(!isset($_SERVER['SERVER_NAME']) || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != $this->url)) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_HTTP_URL, true, 500);
                exit(0);
            }

            if(!isset($_SERVER['SERVER_PORT']) || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != $this->port)) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_HTTP_PORT, true, 500);
                exit(0);
            }
        }

        $httpHeaders = getallheaders();
        $httpMethodOverrideHeader = 'X-HTTP-Method-Override';
        if(isset($httpHeaders[$httpMethodOverrideHeader])) {
            $httpHeaders[$httpMethodOverrideHeader] = trim($httpHeaders[$httpMethodOverrideHeader]);
            if($httpHeaders[$httpMethodOverrideHeader] === null || $httpHeaders[$httpMethodOverrideHeader] === "") {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED, true, 500);
                exit(0);
            }
            else {
                $this->requestMethod = $_SERVER['REQUEST_METHOD'] = $httpHeaders[$httpMethodOverrideHeader];
            }
        }
        else if(isset($httpHeaders[strtolower($httpMethodOverrideHeader)])) {
            $httpMethodOverrideHeaderLower = strtolower($httpMethodOverrideHeader);
            $httpHeaders[$httpMethodOverrideHeaderLower] = trim($httpHeaders[$httpMethodOverrideHeaderLower]);
            if($httpHeaders[$httpMethodOverrideHeaderLower] === null || $httpHeaders[$httpMethodOverrideHeaderLower] === "") {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED, true, 500);
                exit(0);
            }
            else {
                $this->requestMethod = $_SERVER['REQUEST_METHOD'] = $httpHeaders[$httpMethodOverrideHeaderLower];
            }
        }
        else {
            if(!isset($_SERVER['REQUEST_METHOD'])) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED, true, 500);
                exit(0);
            }
            else {
                $this->requestMethod = $_SERVER['REQUEST_METHOD'];
            }
        }

        $varName = 'is'.$this->requestMethod.'Allowed';
        if(property_exists(__CLASS__, $varName) && !$this->$varName) {
            header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED, true, 500);
            exit(0);
        }

        $this->requestURI = $_SERVER['REQUEST_URI'];
    }

    function makeRouteArray() {
        $routeArray = array();

        $methodNames = get_class_methods($this->routeClass);
        foreach ($methodNames as $methodName) {
            $method = new ReflectionMethod($this->routeClass, $methodName);
            $fullAnnotationString = $method->getDocComment();
            if($fullAnnotationString === false) {
                continue;
            }

            $routeString = "";
            $fullAnnotationArray = explode("\n",$fullAnnotationString);
            foreach($fullAnnotationArray as $fullAnnotation) {
                if(strpos($fullAnnotation,"@route|") !== false) {
                    $tempString = explode("@route|",$fullAnnotation);
                    if(count($tempString) != 2) {
                        continue;
                    }
                    $routeString = trim($tempString[1]);
                }
            }

            //If there is no comment with the keyword @route then continue to the next function
            if($routeString === "") {
                continue;
            }

            //Eg: GET='/one/two/'
            //Eg: GET='/one/integer:two/' => /one/integer:/
            //Eg: GET='/one/:/'
            //Eg: GET='/one/:two/' => /one/:two/

            $httpURIArray = explode('=', $routeString);
            if(!isset($httpURIArray[0]) || !isset($httpURIArray[1])) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_ANNOTATION, true, 500);
                exit(0);
            }

            //$httpMethod = explode("@",$httpURIArray[0]);
            if(strtoupper($httpURIArray[0]) != strtoupper($this->requestMethod)) {
                continue;
            }

            $methodURITemp = explode("'", $httpURIArray[1]);
            if(count($methodURITemp) != 3 && $methodURITemp[0] != "" && $methodURITemp[2] != "" && strpos($methodURITemp[1],"/") === false) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_ANNOTATION, true, 500);
                exit(0);
            }

            $methodURIArray = explode('/', $methodURITemp[1]);

            $tempArray = array();

            //Edge case when user gives @GET='/'
            $slashEdgeCase = true;

            foreach($methodURIArray as $methodURI) {
                if($methodURI == "" || $methodURI == null) {
                    continue;
                }

                $slashEdgeCase = false;

                if(strpos($methodURI,":") !== false) {
                    $colonArray = explode(":", $methodURI);
                    if(count($colonArray) != 2) {
                        header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_ANNOTATION, true, 500);
                        exit(0);
                    }

                    if($colonArray[0] != "" && $colonArray[1] == "") {
                        if($colonArray[0] != "number" && $colonArray[0] != "string") {
                            header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_ANNOTATION, true, 500);
                            exit(0);
                        }
                        $methodURI = $colonArray[0].":";
                    }
                    else {
                        $methodURI = ":variable";
                    }
                }
                else if(strpos($methodURI,"#") !== false) {
                    if($methodURI == '#') {
                        $methodURI = '#';
                    }
                }

                $tempArray[] = $methodURI;
            }

            if($slashEdgeCase) {
                $tempArray[] = "#";
            }

            $routeArray['route'][] = $tempArray;
            $routeArray['method'][] = $methodName;
        }

        return $routeArray;
    }

    function callUsersFunction() {

        $requestURIWithGetParams = explode("?",$this->requestURI);
        $requestURI = $requestURIWithGetParams[0];

        $routeArray = $this->makeRouteArray();
        if(!isset($routeArray['route'])) {
            header('HTTP/1.1 404 Not found', true, 404);
            exit(0);
        }

        $tempArray = explode("/",$requestURI);
        $requestURIArray = array();
        foreach($tempArray as $temp) {
            if($temp == "" || $temp == null) {
                continue;
            }
            $requestURIArray[] = $temp;
        }
        $countRequestURIArray = count($requestURIArray);
        if($countRequestURIArray == 0) {
            $countRequestURIArray++;
            $requestURIArray[] = "#";
        }

        $userMethod = "";
        for($i=0;$i<count($routeArray['route']);$i++) {
            $userMethod = "";
            $functionArguments = array();
            if(count($routeArray['route'][$i]) != $countRequestURIArray) {
                continue;
            }
            $routeArrayRoute = $routeArray['route'][$i];
            $countRouteArrayRoute = count($routeArrayRoute);
            for($j=0;$j<$countRouteArrayRoute;$j++) {
                $variableType = "string";
                if(is_numeric($requestURIArray[$j])) {
                    $variableType = "number";
                }

                if(strtolower($routeArrayRoute[$j]) == strtolower($requestURIArray[$j])) {
                    continue;
                }
                else if($routeArrayRoute[$j] == $variableType.":") {
                    $functionArguments[] = $requestURIArray[$j];
                    continue;
                }
                else if($routeArrayRoute[$j] == ":variable") {
                    $functionArguments[] = $requestURIArray[$j];
                    continue;
                }
                else if($routeArrayRoute[$j] == "#") {
                    continue;
                }
                else {
                    if(strpos($routeArrayRoute[$j],"#") !== false) {
                        $wcKey = str_replace('#','*',$routeArrayRoute[$j]);
                        if(fnmatch($wcKey,$requestURIArray[$j])) {
                            continue;
                        }
                    }
                }
                break;
            }
            //Now if J has reached the end that means we have found our function
            if($j >= $countRouteArrayRoute) {
                $userMethod = $routeArray['method'][$i];
                break;
            }
        }

        if($userMethod == "") {
            header('HTTP/1.1 404 Not found', true, 404);
            exit(0);
        }

        $startLogTime = microtime(true);

        //Register the Shutdown callback
        if($this->shutdownCallback !== null) {
            $logArguments = array(
                'callerClass' => $this->routeClass,
                'startTime'=> $startLogTime,
                'requestMethod' => $this->requestMethod,
                'url' => $requestURI,
                'userMethod'=> $userMethod,
                'functionalArguments' => $functionArguments
            );
            if(strpos($this->shutdownCallback, ".") !== false) {
                $classMethodArray = explode(".", $this->shutdownCallback);
                register_shutdown_function(array( $classMethodArray[0], $classMethodArray[1]),$logArguments);
            }
            else {
                register_shutdown_function($this->timerCallback,$logArguments);
            }
        }

        call_user_func_array( array( new $this->routeClass, $userMethod), $functionArguments );
        $endLogTime = microtime(true);

        if($this->timerCallback !== null) {
            $elapsedTime = $endLogTime - $startLogTime;
            $logArguments = array(
                'callerClass' => $this->routeClass,
                'startTime'=> $startLogTime,
                'endTime'=> $endLogTime,
                'elapsedTime'=> $elapsedTime,
                'requestMethod' => $this->requestMethod,
                'url' => $requestURI,
                'userMethod'=> $userMethod,
                'functionalArguments' => $functionArguments
            );

            if(strpos($this->timerCallback, ".") !== false) {
                $classMethodArray = explode(".", $this->timerCallback);
                call_user_func( array( $classMethodArray[0], $classMethodArray[1]), $logArguments);
            }
            else {
                call_user_func($this->timerCallback, $logArguments);
            }
        }
    }

    function run() {
        $this->checkIfValidRequestAndSetContext();
        $this->callUsersFunction();
    }
}