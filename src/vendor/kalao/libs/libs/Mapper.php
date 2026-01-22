<?php

namespace KS;
use Base,
DB\SQL;

abstract class Mapper extends SQL\Mapper
{

    //! Database service name
    const DB = 'DB';

    /**
     * Recursively cast a mapper object, including optional computed results
     * @param object|array $obj
     * @param array $computed
     * @return array
     */
    function cast($obj = NULL, array $computed = NULL)
    {
        if (!isset($obj))
            $obj = $this;
        $cast = [];
        if (is_array($obj))
            foreach ($obj as $k => $o)
                $cast[$k] = $this->cast(isset($o) ? $o : '', $computed);// we don't want to pass NULL here
        elseif (is_object($obj)) {
            $cast = $obj instanceof SQL\Mapper ?
                array_map(function ($row) {
                    return $row['value']; }, $obj->fields + $obj->adhoc) :
                (method_exists($obj, 'cast') ? $obj->cast() : get_object_vars($obj));
            if ($computed)
                foreach ($computed as $k => $name) {
                    foreach (is_string($k) ? [$k] : [$name, 'get' . ucfirst($name)] as $method)
                        if (method_exists($obj, $method)) {
                            $out = $obj->$method();
                            $cast[$name] = $this->cast(isset($out) ? $out : '', $computed);// we don't want to pass NULL here
                            break;
                        }
                }
        } else
            $cast = $obj;
        return $cast;
    }

    /**
     * Return records that match criteria and cast them immediately
     * NB: use this method on big datasets to save memory
     * @param string|array $filter
     * @param array $options
     * @param array $computed
     * @param callable $callback
     * @return array
     */
    function findAndCast($filter = NULL, array $options = NULL, array $computed = NULL, $callback = NULL)
    {
        $f3 = Base::instance();
        $fields = [];
        foreach ($this->fields as $key => $field)
            $fields[$key] = $this->db->quotekey($key);
        foreach ($this->adhoc as $key => $field)
            $fields[$key] = $field['expr'] . ' AS ' . $this->db->quotekey($key);
        list($sql, $args) = $this->stringify(implode(',', $fields), $filter, $options);
        $out = [];
        foreach ($this->db->exec($sql, $args) as $row) {
            foreach ($row as $field => &$val) {
                if (array_key_exists($field, $this->fields)) {
                    if (!is_null($val) || !$this->fields[$field]['nullable'])
                        $val = $this->db->value($this->fields[$field]['pdo_type'], $val);
                } elseif (array_key_exists($field, $this->adhoc))
                    $this->adhoc[$field]['value'] = $val;
                unset($val);
            }
            $mapper = $this->factory($row);
            $cast = $mapper->cast(NULL, $computed);
            if ($callback)
                $cast = $f3->call($callback, [$cast, $mapper]);
            $out[] = $cast;
        }
        return $out;
    }

    /**
     *	Return adhoc expressions
     *	@return array
     **/
    function adhoc()
    {
        return array_map(
            function ($field) {
                return $field['expr'];
            },
            $this->adhoc
        );
    }

    /**
     * @return SQL
     */
    function db()
    {
        return $this->db;
    }

    /**
     * Constructor
     * @param SQL $db
     * @param array|string $fields
     * @param int $ttl
     */
    function __construct(SQL $db = NULL, $fields = NULL, $ttl = 60)
    {
        if (!isset($db)) {
            $f3 = Base::instance();
            $service = $f3->get(static::DB);
            $db = is_callable($service) ? $service() : $service;
        }
        parent::__construct($db, static::TABLE, $fields, $ttl);
        foreach (['onload', 'beforeinsert', 'afterinsert', 'beforeupdate', 'afterupdate', 'beforeerase', 'aftererase'] as $hook)
            if (method_exists($class = get_called_class(), $method = '_' . $hook))
                $this->{$hook}([$class, $method]);
    }

}