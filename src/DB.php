<?php

namespace moe;

use Exception;
use PDO;

class DB extends Prefab
{
    protected $config = array(
        // General
        'type'=>'',
        'charset'=>'',
        'name'=>'',
        // For MySQL, MariaDB, MSSQL, Sybase, PostgreSQL, Oracle
        'server'=>'localhost',
        'username'=>'',
        'password'=>'',
        // For SQLite
        'file'=>'',
        // For MySQL or MariaDB with unix_socket
        'socket'=>'',
        // Optional
        'port'=>3306,
        'option'=>array(),
        );
    // Variable
    protected $logs = array();

    public $pdo;

    const
        //! Configuration not valid
        E_Config = 'Incorrect database configuration';

    /**
     * This construct method was taken from medoo db framework (medoo.in)
     *
     * // required
     * 'type' => 'mysql',
     *
     * // required if mysql
     * 'name' => 'name',
     * 'username' => 'your_username',
     * 'password' => 'your_password',
     *
     * // required if sqlite
     * 'file'=>'db_filename'
     *
     * // optional
     * 'server' => 'localhost', // default localhost
     * 'charset' => 'utf8', // default base ENCODING
     * 'port' => 3306, // default 3306
     *
     * @param array $options database optionsuration
     */
    public function __construct(array $options = array())
    {
        $fw = Base::instance();
        $options || $options = $fw->get('DATABASE');
        if (!isset($options['type'], $options['name'],
                   $options['username'], $options['password']))
            throw new Exception(self::E_Config, 1);
        (isset($options['charset'])) ||
            $options['charset'] = $fw->get('ENCODING')?:'utf8';
        $this->config = $options+$this->config;
        try {
            $commands = array();

            if (isset($this->config['port']) && is_int($this->config['port'] * 1))
                $port = $this->port;

            $type = strtolower($this->config['type']);
            $is_port = isset($port);

            switch ($type) {
                case 'mariadb':
                    $type = 'mysql';

                case 'mysql':
                    if ($this->config['socket'])
                        $dsn = $type . ':unix_socket=' . $this->config['socket'] . ';dbname=' . $this->config['name'];
                    else
                        $dsn = $type . ':host=' . $this->config['server'] . ($is_port ? ';port=' . $port : '') . ';dbname=' . $this->config['name'];

                    // Make MySQL using standard quoted identifier
                    $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
                    break;

                case 'pgsql':
                    $dsn = $type . ':host=' . $this->config['server'] . ($is_port ? ';port=' . $port : '') . ';dbname=' . $this->config['name'];
                    break;

                case 'sybase':
                    $dsn = 'dblib:host=' . $this->config['server'] . ($is_port ? ':' . $port : '') . ';dbname=' . $this->config['name'];
                    break;

                case 'oracle':
                    $dbname = $this->config['server'] ?
                        '//' . $this->config['server'] . ($is_port ? ':' . $port : ':1521') . '/' . $this->config['name'] :
                        $this->config['name'];

                    $dsn = 'oci:dbname=' . $dbname . ($this->config['charset'] ? ';charset=' . $this->config['charset'] : '');
                    break;

                case 'mssql':
                    $dsn = strstr(PHP_OS, 'WIN') ?
                        'sqlsrv:server=' . $this->config['server'] . ($is_port ? ',' . $port : '') . ';database=' . $this->config['name'] :
                        'dblib:host=' . $this->config['server'] . ($is_port ? ':' . $port : '') . ';dbname=' . $this->config['name'];

                    // Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
                    $commands[] = 'SET QUOTED_IDENTIFIER ON';
                    break;

                case 'sqlite':
                    $dsn = $type . ':' . $this->config['file'];
                    $this->config['username'] = null;
                    $this->config['password'] = null;
                    break;
            }

            if (
                in_array($type, explode(' ', 'mariadb mysql pgsql sybase mssql')) &&
                $this->config['charset']
            )
                $commands[] = "SET NAMES '" . $this->config['charset'] . "'";

            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['option']
            );

            foreach ($commands as $value)
                $this->pdo->exec($value);
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }
}
