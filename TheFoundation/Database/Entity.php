<?php

/**
 *
 */
namespace TheFoundation\Database;

/**
 *
 */
abstract class Entity implements \JsonSerializable {

    /**
     *
     */
    const ENTITY_TABLE_NAME = null;
    const ENTITY_PRIMARY_KEY = 'ID';
    const ENTITY_FIELDS = [];

    /**
     *
     */
    protected static $PDO = null;
    private $properties = [];

    /**
     * 
     */
    public function __construct($data = null) {
        if (is_string($data) || is_int($data)) {
            $query_string = sprintf('SELECT 
                    * 
                FROM 
                    %s 
                WHERE 
                    %s = ?', 
                static::ENTITY_TABLE_NAME, static::ENTITY_PRIMARY_KEY);

            if (($sth = static::$PDO->prepare($query_string)) && $sth->execute([$data]))
                $data = $sth->fetch(\PDO::FETCH_ASSOC);
        }
        
        foreach (array_replace(static::ENTITY_FIELDS, is_array($data) ? $data : []) as $name => $_)
            $this->__set($name, $data[$name] ?? null);
    }

    /**
     * 
     */
    public function __invoke($data = null) {
        $this->__construct($data);
    }

    /**
     *
     */
    public function __toString() {
        return json_encode($this, \JSON_PRETTY_PRINT);
    }

    /**
     * 
     */
    final public function __isset(string $name) {
        if ((static::ENTITY_FIELDS[$name] ?? false) || ($this->{$name} ?? false))
            return true;
        
        return false;
    }

    /**
     * 
     */
    final public function __get(string $name) {
        if (static::ENTITY_FIELDS[$name] ?? null)
            return $this->properties[$name];
        
        return $this->{$name};
    }

    /**
     * 
     */
    final public function __set(string $name, $value) {
        if ($type = (static::ENTITY_FIELDS[$name] ?? null)) {
            if (!in_array($type, ['bool', 'int', 'float', 'string', 'list', 'dict', 'object']))
                $this->properties[$name] = null;
            else if ($type == 'bool')
                $this->properties[$name] = (bool) $value;
            else if ($type == 'int' && !is_null($value))
                $this->properties[$name] = (int) $value;
            else if ($type == 'float' && !is_null($value))
                $this->properties[$name] = (float) $value;
            else if ($type == 'string' && !is_null($value))
                $this->properties[$name] = (string) $value;
            else if ($type == 'list') {
                if (is_string($value) && preg_match('/^\[(.*)\]$/s', $value))
                    $value = (($decoded = json_decode($value)) && \json_last_error() === \JSON_ERROR_NONE) ? (array) $decoded : [];
                
                if (is_array($value))
                    $this->properties[$name] = array_values((array) $value);
                else
                    $this->properties[$name] = [];
            } else if ($type == 'dict') {
                if (is_string($value) && preg_match('/^\{(.*)\}$/s', $value))
                    $this->properties[$name] = (($decoded = json_decode($value)) && \json_last_error() === \JSON_ERROR_NONE) ? $decoded : (object) [];
                else if (is_array($value) || (is_object($value) && $value instanceof \StdClass))
                    $this->properties[$name] = json_decode(json_encode((object) $value));
                else if (is_object($value) && $value instanceof \JsonSerializable)
                    $this->properties[$name] = $value;
                else
                    $this->properties[$name] = (object) [];
            } else if ($type == 'object' && $value instanceof self)
                $this->properties[$name] = $value;
            else
                $this->properties[$name] = null;
        } else
            $this->{$name} = $value;
    }

    /**
     *
     */
    public function jsonSerialize() {
        return $this->properties;
    }
    
    /**
     *
     */
    final public static function PDO(): ?\PDO {
        return static::$PDO;
    }

    /**
     *
     */
    final public static function attachPDO(\PDO $PDO) {
        static::$PDO = $PDO;
    }

    /**
     *
     */
    final public static function load($pk_value): ?self {
        $query_string = sprintf('SELECT 
                * 
            FROM 
                %s 
            WHERE
                %s = ?', 
            static::ENTITY_TABLE_NAME, static::ENTITY_PRIMARY_KEY);

        if (($sth = static::$PDO->prepare($query_string))
                && $sth->execute([$pk_value])
                && $data = $sth->fetch(\PDO::FETCH_ASSOC)) {
            $self = new static();
            
            foreach ($data as $name => $value)
                $self->__set($name, $value);

            return $self;
        }

        return null;
    }

    /**
     * 
     */
    final public static function list(int $offset = 0, int $max_items = 20): array {
        if (!static::$PDO
            || !static::ENTITY_TABLE_NAME
            || !static::ENTITY_PRIMARY_KEY)
            return [];

        $query_string = sprintf('SELECT
                *, 
                (SELECT COUNT(*) FROM %s) AS totalcount
            FROM
                %s 
            ORDER BY
                %s DESC
            LIMIT
                %d, %d',
            static::ENTITY_TABLE_NAME, static::ENTITY_TABLE_NAME, static::ENTITY_PRIMARY_KEY, $offset, $max_items);
        
        if (($sth = static::$PDO->prepare($query_string)) && $sth->execute())
            return $sth->fetchAll(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, get_called_class());
        
        return [];
    }

    /**
     *
     */
    final public function save(): bool {
        if (!static::$PDO
            || !static::ENTITY_TABLE_NAME
            || !static::ENTITY_PRIMARY_KEY
            || !static::ENTITY_FIELDS)
            return false;

        $values = array_map(function($name) {
            if (!$type = (static::ENTITY_FIELDS[$name] ?? null))
                return null;

            $value = $this->properties[$name];

            if ($type == 'object')
                return $value instanceof self ? $value->{$value::ENTITY_PRIMARY_KEY} : null;
            else if ($type == 'list' || $type == 'dict')
                return json_encode($value);
            else if (in_array($type, ['bool', 'int', 'float', 'string']))
                return $value;

            return null;
        }, $field_names = array_keys(static::ENTITY_FIELDS));
        
        if (!empty($this->{static::ENTITY_PRIMARY_KEY})) {
            $values[] = $this->{static::ENTITY_PRIMARY_KEY};
            $query_string = sprintf('UPDATE 
                    %s 
                SET 
                    %s = ? 
                WHERE 
                    %s = ?;',
                static::ENTITY_TABLE_NAME, implode(' = ?, ', $field_names), static::ENTITY_PRIMARY_KEY);

            if (($sth = static::$PDO->prepare($query_string)) && $sth->execute($values))
                return true;
        } else {
            var_dump(1);
            $query_string = sprintf('INSERT INTO 
                    %s (%s) 
                VALUES
                    (%s);', 
                static::ENTITY_TABLE_NAME, implode(', ', $field_names), str_repeat('?, ', count(static::ENTITY_FIELDS) - 1) . '?');

            if (($sth = static::$PDO->prepare($query_string)) && $sth->execute($values))
                return ($this->{static::ENTITY_PRIMARY_KEY} = static::$PDO->lastInsertId()) || true;
        }

        return false;
    }

    /**
     *
     */
    final public function delete(): bool {
        if (!static::$PDO
            || !static::ENTITY_TABLE_NAME
            || !static::ENTITY_PRIMARY_KEY
            || !static::ENTITY_FIELDS
            || is_null($this->{static::ENTITY_PRIMARY_KEY}))
            return false;
        
        $query_string = sprintf('DELETE FROM 
                %s 
            WHERE 
                %s = ?;', 
            static::ENTITY_TABLE_NAME, static::ENTITY_PRIMARY_KEY);

        if (($sth = static::$PDO->prepare($query_string))
                && $sth->execute([$this->{static::ENTITY_PRIMARY_KEY}])
                && $sth->rowCount() > 0) {
            foreach (static::ENTITY_FIELDS as $name => $type)
                $this->__set($name, null);

            return true;
        }

        return false;
    }
}
?>