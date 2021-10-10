<?php

/**
 * 
 */
namespace TheFoundation\RouterHttp;

/**
 * 
 */
class Response {

    /**
     * 
     */
    protected $Template = null;

    /**
     * 
     */
    public function __construct(?Response\Template $Template = null) {
        $this->Template = $Template;
    }

    /**
     * 
     */
    final public function __unset(string $name) {
        return false;
    }

    /**
     * 
     */
    final public function __set(string $name, $mix_value) {
        if (property_exists($this, $name))
            $this->{$name} = $mix_value;
    }

    /**
     * 
     */
    final public function __get(string $name) {
        if (property_exists($this, $name))
            return $this->{$name};
    }
    
    /**
     * 
     */
    final public function send(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        if (!headers_sent()) {
            foreach ($headers as $name => $value)
                header(sprintf('%s: %s', (string) $name, (string) $value), 0 === strcasecmp($name, 'Content-Type'), $status_code);
            
            foreach ($set_cookies as $name => $value)
                header(sprintf('Set-Cookie: %s=%s', (string) $name, (string) $value), false, $status_code);
            
            if ($status_code)
                http_response_code($status_code);
        }
        
        echo (string) $this->Template;
    }

    /**
     * 
     */
    public function head(array $headers = [], array $set_cookies = []) {
        $this->Template = null;

        return $this->send(204, $headers, $set_cookies);
    }

    /**
     * 
     */
    public function html(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        return $this->send($status_code, array_replace($headers, ['Content-Type' => 'text/html; charset=utf-8']), $set_cookies);
    }

    /**
     * 
     */
    public function plain(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        return $this->send($status_code, array_replace($headers, ['Content-Type' => 'text/plain; charset=utf-8']), $set_cookies);
    }

    /**
     * 
     */
    public function json(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        return $this->send($status_code, array_replace($headers, ['Content-Type' => 'application/json; charset=utf-8']), $set_cookies);
    }

    /**
     * 
     */
    public function jsonify(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        $this->Template = json_encode($this->Template);

        return $this->json($status_code, $headers, $set_cookies);
    }

    /**
     * 
     */
    public function xml(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        return $this->send($status_code, array_replace($headers, ['Content-Type' => 'application/xml; charset=utf-8']), $set_cookies);
    }

    /**
     * 
     */
    public function xmlify(int $status_code = 200, array $headers = [], array $set_cookies = []) {
        $this->Template = ($array_to_xml = function($data, SimpleXMLElement $node = null) use (&$array_to_xml) {
            if (is_null($node))
                $node = new SimpleXMLElement('<root/>');

            foreach ((array) $data as $name => $value) {
                if (is_numeric($name))
                    $name = 'item_' . $name;

                (is_array($value) || is_object($value)) 
                    ? $array_to_xml($value, $node->addChild($name))
                        : $node->addChild((string) $name, $value);
            }

            return $node;
        })($this->Template)->asXML();

        return $this->xml($status_code, $headers, $set_cookies);
    }

    /**
     * 
     */
    public function download(?string $filename, ?string $name = null, string $content_type = 'application/octet-stream') {
        header('Content-Description: File Transfer');
        header(sprintf('Content-Type: %s', $content_type));
        header('Content-Transfer-Encoding: binary');
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        if (file_exists($filename)) {
            header(sprintf('Content-Disposition: attachment; filename="%s"', htmlspecialchars($name ?: basename($filename))));
            header(sprintf('Content-Length: %d', filesize($filename)));
            readfile($filename);
            exit();
        } else if (is_null($filename) && $name) {
            header(sprintf('Content-Disposition: attachment; filename="%s"', htmlspecialchars($name)));
            echo (string) $this->Template;
            exit();
        }

        return http_response_code(404);
    }
}
?>