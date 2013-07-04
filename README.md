route : PHP REST Framework
=====
@author: Ajinkya Apte

<span>
    <b>Description:</b>
<ol>
    <li>Simple setup and get going within minutes</li>
    <li>Just register a PHP class with the framework and write annotations to your class methods</li>
    <li>HTTP calls are routed to your class methods based on the annotations</li>
    <li>Framework records execution time of the user defined class method and calls a user registered function for logging</li>
    <li>Support for X-HTTP-Method-Override</li>
</ol>
</span>
=====
<span>
    <b>Usage:</b>
<ul>
    <li>annotationFormat: @(HTTP method type)='(URL)'</li>
    <li>annotationOptions:
        <ol>
            <li>URL name => /route/</li>
            <li>URL input with type check => /number:id/ or /number:/</li>
            <li>URL input without type check => /:id/ or /:/</li>
            <li>Wildcard => /#/ or /xyz#/</li>
        </ol>
    </li>
    <li>configurationFile: route/config/route.config *</li>
    <li>success: Calls the user defined function based on the annotation</li>
    <li>error: HTTP 500
        <ol>
            <li>'ROUTE_ERROR_CONFIG_MISSING' => '181';</li>
            <li>'ROUTE_ERROR_BAD_ROUTE_CONFIG_VAR' => '182'</li>
            <li>'ROUTE_ERROR_INCORRECT_HTTP_URL' => '183'</li>
            <li>'ROUTE_ERROR_INCORRECT_HTTP_PORT' => '184'</li>
            <li>'ROUTE_ERROR_HTTP_METHOD_NOT_SUPPORTED' => '185'</li>
            <li>'ROUTE_ERROR_INCORRECT_ANNOTATION' => '186'</li>
        </ol>
</ul>
 </span>
=====
<span>
    <b>Example end point:</b><br/>
     <code>
         require_once('route/routeHandler.php');

         class Route {
             /**
             * @GET='/route/one/two/'
             */
             function one() {
                echo "@function: one(), @HTTP Request type: GET, @Route: '/route/one/two/'";
             }

             /**
             * @POST='/route/one/number:two/three/:/five'
             */
             function two($two, $four) {
                echo "@function: two(), @params: $two $four, @HTTP Request type: POST, @Route: '/route/one/number:two/three/:/five'";
             }

             /**
             * @PUT='/route/one/string:/three/'
             */
             function three($two) {
                echo "@function: three(), @params: $two, @HTTP Request type: PUT, @Route: '/route/one/string:/three/'";
             }

             /**
             * @DELETE='/route/one/#/three'
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
 </span>

