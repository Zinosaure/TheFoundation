<?php

/**
 * 
 */
namespace Foundation\RouterHttp\Response;

/**
 * 
 */
class Template {

    /**
     * 
     */
    private $document = null;
    private $attributes = [];

    /**
     * 
     */
    final public function __construct(string $document, array $attributes = []) {
        $this->document = $document;
        $this->attributes = $attributes;
    }

    /**
    *
    */
    public function &__get(string $name) {
        return $this->attributes[$name];
    }

    /**
    *
    */
    public function __set(string $name, $mix_value) {
        $this->attributes[$name] = $mix_value;
    }

    /**
    *
    */
    public function __toString(): string {
        ob_start();
            extract($this->attributes);

            if (preg_match('/\.phtml$/is', $this->document) && is_file($this->document))
                include $this->document;
            else
                echo eval('?>' . $this->document);
        
        return ob_get_clean();
    }
}
?>