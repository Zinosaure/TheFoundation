<?php

/**
 * 
 */
namespace src;

/**
 * 
 */
class Request {

    /**
     * Send a request by CURL
     * 
     * @param string $url - http url
     * @param array $curl_options - array of CURLOPT_*
     * @return object - {Status: bool, Response: mixed} or an object
     */
    final public static function send(string $url, array $curl_options = [CURLOPT_POSTFIELDS => []]): ?object {
        curl_setopt_array($curl = curl_init(), array_replace([
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ], $curl_options));
        $exec = ['Response' => curl_exec($curl), 'Parameters' => curl_getinfo($curl), 'Error' => curl_errno($curl) ? curl_error($curl) : null];
        curl_close($curl);

        return (object) $exec;
    }
}
?>