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
        'min'=>'{f} minimal {p}',
        'max'=>'{f} maximal {p}',
        'exists'=>'{f} "{v}" was not exists',
        'unique'=>'{f} "{v}" was exists',
        );

    public function __construct()
    {
        foreach (Instance::get('validation_message')?:array() as $key => $value)
            self::addMessage($key, $value);
    }

    public function addMessage($func, $pattern)
    {
        self::$messages[$func] = $pattern;
    }

    public function message($func, $field, $value, $param = null)
    {
        $message = '{f} was invalid';
        if (isset(self::$messages[$func]))
            $message = self::$messages[$func];
        else
            foreach (self::$messages as $func_msg => $msg)
                if (strpos($func, $func_msg)===0) {
                    $message = $msg;
                    break;
                }

        return str_replace(array('{f}','{v}','{p}',), array(
            $field,
            is_array($value)?implode(', ', $value):$value,
            is_array($param)?implode(', ', $param):$param,
            ), $message);
    }

    public function required($val)
    {
        return !(is_null($val) || ''==$val);
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

    public function min($string, $min)
    {
        return is_numeric($string)?$string>=$min:false;
    }

    public function max($string, $max)
    {
        return is_numeric($string)?$string<=$max:false;
    }

    public function exists($val, $func)
    {
        return Instance::call($func, $val);
    }
}
