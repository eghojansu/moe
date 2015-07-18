<?php

namespace moe;

/**
 * Note method must return boolean exactly if they're should return boolean
 */
class Validation extends Prefab
{
    protected static $messages = array(
        'required'=>'{f} cannot be empty',
        'is_numeric'=>'{f} must be numeric',
        'alpha'=>'{f} must be alpha text',
        'alphanumeric'=>'{f} must be alpha-numeric text',
        'uppercase'=>'{f} must be in uppercase',
        'lowercase'=>'{f} must be in lowercase',
        'in_array'=>'{f} must be in ({p})',
        'min_length'=>'{f} minimal {p} character',
        'max_length'=>'{f} maximal {p} character',
        );

    public function addMessage($func, $pattern)
    {
        self::$messages[$func] = $pattern;
    }

    public function message($func, $field, $value, $param = null)
    {
        if (isset(self::$messages[$func]))
            return str_replace(array(
                '{f}',
                '{v}',
                '{p}',
                ), array(
                $field,
                is_array($value)?implode(', ', $value):$value,
                is_array($param)?implode(', ', $param):$param,
                ), self::$messages[$func]);
        else
            return $field.' was invalid';
    }

    public function required($val)
    {
        return (isset($val) || $val!='');
    }

    public function alpha($string)
    {
        return (bool) preg_match('/^[a-z ]+$/i', $string);
    }

    public function alphanumeric($string)
    {
        return (bool) preg_match('/^[a-z0-9_\-\. ]+$/i', $string);
    }

    public function uppercase($string)
    {
        return (bool) preg_match('/^[A-Z0-9_\-\. ]+$/', $string);
    }

    public function lowercase($string)
    {
        return (bool) preg_match('/^[a-z0-9_\-\. ]+$/', $string);
    }

    public function min_length($string, $min)
    {
        return is_scalar($string)?strlen($string)>=$min:false;
    }

    public function max_length($string, $max)
    {
        return is_scalar($string)?strlen($string)<=$max:false;
    }
}
