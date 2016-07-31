<?php

require_once("request.php");

$response = Request::doGet(array(
    'url' => 'http://google.com/',
));

var_dump($response->getRawResult());
var_dump($response->getHTTPCode());
