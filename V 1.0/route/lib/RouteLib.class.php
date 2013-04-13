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
    private $url;
    private $port;
    private $routeClass;
    private $isGETAllowed;
    private $isPOSTAllowed;
    private $isPUTAllowed;
    private $isDELETEAllowed;

    function __construct($url, $port, $routeConfig) {
        $this->url = $url;
        $this->port = $port;
        $this->routeClass = $routeConfig['routeClassName'];
        $routeHttpProtocols = explode(",",$routeConfig['routeHttpProtocols']);
        if(in_array("GET", $routeHttpProtocols)) {
            $this->isGETAllowed = true;
        }
        else {
            $this->isGETAllowed = false;
        }

        if(in_array("POST", $routeHttpProtocols)) {
            $this->isPOSTAllowed = true;
        }
        else {
            $this->isPOSTAllowed = false;
        }

        if(in_array("PUT", $routeHttpProtocols)) {
            $this->isPUTAllowed = true;
        }
        else {
            $this->isPUTAllowed = false;
        }

        if(in_array("DELETE", $routeHttpProtocols)) {
            $this->isDELETEAllowed = true;
        }
        else {
            $this->isDELETEAllowed = false;
        }
    }

    function checkIfValidRequest() {
        if(!isset($_SERVER['SERVER_NAME']) || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != $this->url)) {
            header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_HTTP_URL, true, 500);
            exit(0);
        }

        if(!isset($_SERVER['SERVER_PORT']) || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != $this->port)) {
            header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_HTTP_PORT, true, 500);
            exit(0);
        }

        if(!isset($_SERVER['REQUEST_METHOD'])) {
            header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED, true, 500);
            exit(0);
        }
        else {
            $varName = 'is'.$_SERVER['REQUEST_METHOD'].'Allowed';
            if(!$this->$varName) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED, true, 500);
                exit(0);
            }
        }
    }

    function makeRouteArray($requestMethod) {
        $routeArray = array();
        $array = array();

        $methodNames = get_class_methods($this->routeClass);
        foreach ($methodNames as $methodName) {
            $method = new ReflectionMethod($this->routeClass, $methodName);
            $fullAnnotation = $method->getDocComment();
            if($fullAnnotation === false) {
                continue;
            }

            //Eg: @GET='/one/two/'
            //Eg: @GET='/one/integer:two/' => /one/integer:/
            //Eg: @GET='/one/:/'
            //Eg: @GET='/one/:two/' => /one/:two/

            $httpURIArray = explode('=', $fullAnnotation);
            if(!isset($httpURIArray[0]) || !isset($httpURIArray[1])) {
                header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_ANNOTATION, true, 500);
                exit(0);
            }

            $httpMethod = explode("@",$httpURIArray[0]);
            if($httpMethod[1] != $requestMethod) {
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
                    if($methodURI != "#") {
                        header('HTTP/1.1 500 Internal Server Error: Route: '.ROUTE_ERROR_INCORRECT_ANNOTATION, true, 500);
                        exit(0);
                    }
                }

                $tempArray[] = $methodURI;
            }

            if($slashEdgeCase) {
                $tempArray[] = "#";
            }

            $ref = &$array;
            foreach ($tempArray as $key) {
                //$ref[$key] = array();
                $ref = &$ref[$key];
            }
            $ref = $methodName;

            $routeArray[$httpMethod[1]] = $array;
        }

        return $routeArray;
    }

    function callUsersFunction() {

        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestURIWithGetParams = explode("?", $_SERVER['REQUEST_URI']);
        $requestURI = $requestURIWithGetParams[0];

        $routeArray = $this->makeRouteArray($requestMethod);
        $previousArray = $routeArray[$requestMethod];

        $tempArray = explode("/",$requestURI);
        $requestURIArray = array();
        foreach($tempArray as $temp) {
            if($temp == "" || $temp == null) {
                continue;
            }
            $requestURIArray[] = $temp;
        }
        $countRequestURIArray = count($requestURIArray);

        $userMethod = "";
        $functionArguments = array();

        foreach($requestURIArray as $request) {
            $variableType = "string";
            if(is_numeric($request)) {
                $variableType = "number";
            }

            if(($countRequestURIArray-1) > 0) {
                if(isset($previousArray[$request]) && is_array($previousArray[$request])) {
                    $previousArray = $previousArray[$request];
                    $countRequestURIArray --;
                    continue;
                }
                else if(isset($previousArray[$variableType.":"]) && is_array($previousArray[$variableType.":"])) {
                    $previousArray = $previousArray[$variableType.":"];
                    $functionArguments[] = $request;
                    $countRequestURIArray --;
                    continue;
                }
                else if(isset($previousArray[":variable"]) && is_array($previousArray[":variable"])) {
                    $previousArray = $previousArray[":variable"];
                    $functionArguments[] = $request;
                    $countRequestURIArray --;
                    continue;
                }
                else if(isset($previousArray["#"]) && is_array($previousArray["#"])) {
                    $previousArray = $previousArray["#"];
                    $countRequestURIArray --;
                    continue;
                }
                break;
            }
            else {
                if(isset($previousArray[$request]) && is_string($previousArray[$request])) {
                    $userMethod = $previousArray[$request];
                    break;
                }
                else if(isset($previousArray[$variableType.":"]) && is_string($previousArray[$variableType.":"])) {
                    $userMethod = $previousArray[$variableType.":"];
                    $functionArguments[] = $request;
                    break;
                }
                else if(isset($previousArray[":variable"]) && is_string($previousArray[":variable"])) {
                    $userMethod = $previousArray[":variable"];
                    $functionArguments[] = $request;
                    break;
                }
                else if(isset($previousArray["#"]) && is_string($previousArray["#"])) {
                    $userMethod = $previousArray["#"];
                    break;
                }
            }
        }

        if($userMethod == "") {
            header('HTTP/1.1 404 Not found', true, 404);
            exit(0);
        }
        call_user_func_array( array( new $this->routeClass, $userMethod), $functionArguments );
    }

    function run() {
        $this->checkIfValidRequest();
        $this->callUsersFunction();
    }
}