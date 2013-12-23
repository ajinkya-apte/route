<?php
/**
 * Route - PHP REST Framework
 *
 * @author: Ajinkya Apte
 * @codeRepository: https://github.com/ajinkya-apte/route.git
 *
 * @file: Sample landing page.
 *
 * @annotationFormat: @<HTTP method type>='<URL>'
 * @annotationOptions:
 *                  1. URL name => /route/
 *                  2. URL input with type check => /number:id/ or /number:/
 *                  3. URL input without type check => /:id/ or /:/
 *                  4. Wildcard => /#/
 *
 * @configurationFile: route/config/route.config *
 *
 * @success: Calls the user defined function based on the annotation
 * @error: HTTP 500
 *       'ROUTE_ERROR_CONFIG_MISSING' => '181';
 *       'ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR' => '182'
 *       'ROUTE_ERROR_INCORRECT_HTTP_URL' => '183'
 *       'ROUTE_ERROR_INCORRECT_HTTP_PORT' => '184'
 *       'ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED' => '185'
 *       'ROUTE_ERROR_INCORRECT_ANNOTATION' => '186'
 */
require_once('route/routeHandler.php');

class Route {
    /**
     * @route|GET='/route/one/two/'
     */
    function one() {
        echo "@function: one(), @HTTP Request type: GET, @Route: '/route/one/two/'";
    }

    /**
     * @route|POST='/route/one/number:two/three/:/five'
     */
    function two($two, $four) {
        echo "@function: two(), @params: $two $four, @HTTP Request type: POST, @Route: '/route/one/number:two/three/:/five'";
    }

    /**
     * @route|PUT='/route/one/string:/three/'
     */
    function three($two) {
        echo "@function: three(), @params: $two, @HTTP Request type: PUT, @Route: '/route/one/string:/three/'";
    }

    /**
     * @route|DELETE='/route/one/#/three'
     */
    function four() {
        echo "@function: four(), @HTTP Request type: DELETE, @Route: '/route/one/#/three'";
    }
}

/*
 * @params:
 *          serverName: eg => xyz.com
 *          port: eg => 80
 *          getDataFromConfig => eg => false
 */
run('localhost', '80', false);
