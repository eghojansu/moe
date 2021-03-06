<?php

namespace moe;

use Exception;
use PDO;

class DB extends Prefab
{
    protected $config = array(
        //! General
        'type'=>'',
        'charset'=>'',
        'name'=>'',
        //! For MySQL, MariaDB, MSSQL, Sybase, PostgreSQL, Oracle
        'server'=>'localhost',
        'username'=>'',
        'password'=>'',
        //! For SQLite
        'file'=>'',
        //! For MySQL or MariaDB with unix_socket
        'socket'=>'',
        //! Optional
        'port'=>3306,
        'option'=>array(),
        );

    //! PDO object
    public $pdo;

    /**
     * This construct method was taken from medoo db framework (medoo.in)
     *
     * @param array $config configuration
     */
    public function __construct(array $config = array())
    {
        $fw = Base::instance();
        $config || $config = $fw->get('DATABASE');
        $this->config = $config+$this->config;

        if (!$this->config['charset'] && $charset = $fw->get('ENCODING'))
            $this->config['charset'] = $charset;

        $commands = array();

        if (isset($this->config['port']) && is_int($this->config['port'] * 1))
            $port = $this->config['port'];

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

        if (!isset($dsn))
            throw new Exception('No database type given :(', 1);

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
    }
}
