<?php
require_once("common.php");

class Request
{
    private $_response = array(
        'http_code' => null,
        'result' => null,
        'effective_url' => null,
        'request_method' => null,
        'verbose_info' => null,
    );

    /**
     * @param $parameter - Key for response parameters
     * @return mixed - Response parameter value
     */
    private function _getResponseParameter($parameter)
    {
        return isset($this->_response[$parameter]) ? $this->_response[$parameter] : null;
    }

    /**
     * Get response body AS IS
     *
     * @return mixed Raw response
     */
    public function getRawResult()
    {
        return $this->_getResponseParameter('result');
    }

    /**
     * Get JSON decoded response body
     *
     * @return array JSON decoded response
     */
    public function getResult()
    {
        return json_decode($this->_getResponseParameter('result'), true);
    }

    /**
     * @return int http_code of the response
     */
    public function getHTTPCode()
    {
        return $this->_getResponseParameter('http_code');
    }

    /**
     * Return True if response http_code is 200 OK
     *
     * @return bool
     */
    public function isOK()
    {
        return $this->getHTTPCode() == 200;
    }

    /**
     * Support method for debugging
     *
     * @param array $otherDebugData - Outside debug information
     * @return Request - Request class object
     */
    public function debug($otherDebugData = array())
    {
        foreach ($otherDebugData as $title => $value)
        {
            echo '<b>' . $title . ': </b>' . $value . '<br>';
        }
        echo '<b>HTTP ' . $this->getHTTPCode() . '</b><br>';
        echo '<b>' . $this->_getResponseParameter('request_method') . ': </b>' . $this->_getResponseParameter('effective_url');

        if (is_debugmode('verbose'))
        {
            echo '<br><b>Verbose info: </b><br>' . nl2br($this->_getResponseParameter('verbose_info'));
        }

        echo '<br><b>Result: </b>';
        var_dump($this->getRawResult());
        echo '<hr>';
        return $this;
    }

    /**
     * GET request for API. The heart of the Request class
     *
     * @param array $params - Request params
     * @return Request - Request class object
     */
    public static function doGet(Array $params)
    {
        $request = new self();

        $params = array_merge(array('url' => '', 'data' => array(), 'requestMethod' => 'GET', 'tryNum' => 1), $params);
        $url = $params['url'];
        $tryNum = $params['tryNum'];
        $data = http_build_query($params['data']);

        $curl_options = array(
            CURLOPT_VERBOSE => 0,
            CURLOPT_HEADER => 0,
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSLVERSION => 3,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array("Content-Type: application/x-www-form-urlencoded", "Accept: text/plain"),
        );

        if (!in_array($params['requestMethod'], array('GET', 'POST')))
        {
            $curl_options[CURLOPT_CUSTOMREQUEST] = $params['requestMethod'];
        }

        if (is_debugmode('verbose')) {
            $verboseFile = fopen('php://temp', 'r+');
            $curl_options[CURLOPT_VERBOSE] = 1;
            $curl_options[CURLOPT_STDERR] = $verboseFile;
        }

        if ($data)
        {
            if ($params['requestMethod'] == 'POST')
            {
                $curl_options[CURLOPT_POST] = 1;
                $curl_options[CURLOPT_POSTFIELDS] = $data;
            } else {
                $url .= '?' . $data;
            }
        }
        $curl_options[CURLOPT_URL] = $url;

        $ch = curl_init();
        curl_setopt_array($ch, $curl_options);

        do
        {
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $tryNum--;
        } while ($http_code >= 500 && $tryNum > 0);
        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($params['requestMethod'] == 'POST')
        {
            $effective_url .= '?' . $data;
        }

        $request->_setAnswer(array(
            'result' => $result,
            'http_code' => $http_code,
            'effective_url' => $effective_url,
            'request_method' => $params['requestMethod'],
        ));

        if (is_debugmode('request')) {
            $request->debug(array(
                'Mode' => 'Request'
            ));
        }
        if (is_debugmode('verbose')) {
            rewind($verboseFile);
            $request->_setAnswer(array(
                'verbose_info' => fread($verboseFile, 8192),
            ));

            $request->debug(array(
                'Mode' => 'Verbose request'
            ));
        }
        return $request;
    }

    /**
     * POST request for API
     *
     * @param array $params - Request params
     * @return Request - Request class object
     */
    public static function doPost(Array $params) {
        $params['requestMethod'] = 'POST';
        $params['tryNum'] = 5;
        return self::doGet($params);
    }

    /**
     * DELETE request for API
     *
     * @param array $params - Request params
     * @return Request - Request class object
     */
    public static function doDelete(Array $params) {
        $params['requestMethod'] = 'DELETE';
        return self::doGet($params);
    }

    /**
     * Set http_code and result based on request answer
     *
     * @param array $data - Answer array
     */
    protected function _setAnswer(Array $data)
    {
        foreach ($this->_response as $parameter => $value)
        {
            if (isset($data[$parameter]))
            {
                $this->_response[$parameter] = $data[$parameter];
            }
        }
    }
}
