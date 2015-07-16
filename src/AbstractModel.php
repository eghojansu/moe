<?php

namespace moe;

use medoo;

abstract class AbstractModel extends medoo
{
    const
        //! Configuration not valid
        E_Config = 'Incorrect database configuration';

    //! Validator
    public $validator;

    /**
     * Returns model filter
     * Format see Validator Class
     *
     * @param  array  $filter [description]
     * @return [type]         [description]
     */
    public function filter()
    {
        return array();
    }

    public static function instance()
    {
        if (!Registry::exists($class=get_called_class())) {
            $ref=new Reflectionclass($class);
            $args=func_get_args();
            Registry::set($class,
                $args?$ref->newinstanceargs($args):new $class);
        }
        return Registry::get($class);
    }

    /**
     * $config follow medoo database framework configuration
     * Default read DATABASE vars
     *
     * // required
     * 'database_type' => 'mysql',
     *
     * // required if mysql
     * 'database_name' => 'name',
     * 'username' => 'your_username',
     * 'password' => 'your_password',
     *
     * // required if sqlite
     * 'database_file'=>'db_filename'
     *
     * // optional
     * 'server' => 'localhost', // default localhost
     * 'charset' => 'utf8', // default base ENCODING
     * 'port' => 3306, // default 3306
     *
     * @param array $config database configuration
     */
    public function __construct(array $config = array())
    {
        $fw = Base::instance();
        $config || $config = $fw->get('DATABASE');
        if (!isset($config['database_type'], $config['database_name'],
                   $config['username'], $config['password']))
            user_error(E_Config);
        (isset($config['server']) || $config['database_type']=='sqlite') ||
            $config['server'] = 'localhost';
        (isset($config['charset'])) ||
            $config['charset'] = $fw->get('ENCODING')?:'utf8';
        parent::__construct($config);
        $this->debug_mode = (bool) $fw->get('DEBUG');
        $this->validator = new Validator($this->rules());
    }
}
