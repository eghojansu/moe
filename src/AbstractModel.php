<?php

namespace moe;

use Exception;
use PDO;

/**
 * This class require all fields in lowercase
 */
abstract class AbstractModel extends Prefab
{
    //! is view?
    protected $isView = false;

    //! properties
    protected $_properties = array(
        'table_name'  => null,
        'primary_key' => array(),
        'relation'    => array(),
        //! Pair field and alias
        'fields'      => array(),
        //! Pair field and filter
        'filters'     => array(),
        );

    //! select builder
    protected $_select = array(
        'select' => '*',
        'from'   => '{table}',
        'join'   => '',
        'where'  => '',
        'group'  => '',
        'having' => '',
        'order'  => '',
        'limit'  => '',
        'offset' => '',
        'params' => array(),
        );

    //! runtime
    protected $_schema = array(
        //! Pair field and value
        'init'   => array(),
        'values' => array(),
        //! Other/updated values
        'others' => array(),
        //! select query
        'select' => null,
        //! flag switch config
        'config' => array(
            'validation'      => false,
            'log'             => false,
            'resetAfterBuild' => true,
            'reUseStatement'  => false,
            ),
        //! fetch mode
        'fetch'  => PDO::FETCH_ASSOC,
        );

    //! Editable primary key format, prefix in {} will be treated as date token
    protected $codeDesign = array('prefix'=>'', 'serial'=>0);

    protected $_logs      = array();
    protected $_queries   = array();
    protected $_errors    = array();
    protected $_messages  = array();

    //! Statement handle
    protected $_stmt;

    const
        Magic        = 'where|having|findBy|existsBy|unique|deleteBy|update';

    const
        E_Method     = '%s: method %s doesn\'t exists',
        E_Param      = '%s: field %s not enough parameters',
        E_Data       = '%s: no data provided',
        E_Invalid    = '%s: Invalid parameter',
        E_Query      = '%s: Query error',
        E_Record     = '%s: No record to fetch anymore',
        E_Schema     = '%s: Schema was no defined',
        E_PrimaryKey = '%s: Primary key was no defined',
        E_Relation   = '%s: Relation key must be table name and (or) its alias',
        E_NoRelation = '%s: Relation key "%s" was not exists',
        E_Composit   = '%s: method %s can\'t use composit key',
        E_View       = 'View can\'t do %s operation';

    /**
     * Returns fields pair with its alias an its filter/sanitizer
     * Format:
     *   array(
     *       fieldname=>array(
     *           alias,
     *           filter, // must be array
     *           default value
     *       )
     *   )
     *
     * @param  array  $filter [description]
     * @return array        [description]
     */
    abstract protected function schema();

    /**
     * Return primary key
     */
    public function primaryKey()
    {
        return array();
    }

    /**
     * Relation
     * Format :
     * [
     *     joina=>'join {join} on {join}.column = {table}.column',
     *     joinb b=>'join {join} {b} on {b}.column = {table}.column',
     *     c=>'join (select * from table) {j} on {j}.column = {table}.column'
     * ]
     */
    public function relation()
    {
        return array();
    }

    public function beforeInsert(array &$new)
    {
        return true;
    }

    public function beforeUpdate(array &$new)
    {
        return true;
    }

    public function beforeDelete(array &$new)
    {
        return true;
    }

    public function afterInsert()
    {
        return true;
    }

    public function afterUpdate(AbstractModel $old)
    {
        return true;
    }

    public function afterDelete(AbstractModel $old)
    {
        return true;
    }

    /**
     * Return table name
     */
    public function table()
    {
        if (!$this->_properties['table_name']) {
            $class = explode('\\', get_called_class());
            $this->_properties['table_name'] = Instance::snakecase(lcfirst(end($class)));
        }

        return $this->_properties['table_name'];
    }

    /**
     * Get last query
     */
    public function lastQuery()
    {
        return end($this->_logs);
    }

    /**
     * Get log
     */
    public function log()
    {
        return $this->_logs;
    }

    /**
     * log as html list
     */
    public function logList()
    {
        return '<ul><li>'.implode('</li><li>', $this->_logs).'</li></ul>';
    }

    /**
     * Get last real query
     */
    public function lastRealQuery()
    {
        return end($this->_queries);
    }

    /**
     * Get query
     */
    public function query()
    {
        return $this->_queries;
    }

    /**
     * query as html list
     */
    public function queryList()
    {
        return '<ul><li>'.implode('</li><li>', $this->_queries).'</li></ul>';
    }

    /**
     * Has error
     */
    public function hasError()
    {
        return count($this->_errors)>0;
    }

    /**
     * Get error
     */
    public function error()
    {
        return $this->_errors;
    }

    /**
     * error as html list
     */
    public function errorList()
    {
        return '<ul><li>'.implode('</li><li>', $this->_errors).'</li></ul>';
    }

    /**
     * Has message
     */
    public function hasMessage()
    {
        return count($this->_messages)>0;
    }

    /**
     * Get message
     */
    public function message()
    {
        return $this->_messages;
    }

    /**
     * message as html list
     */
    public function messageList()
    {
        return '<ul><li>'.implode('</li><li>', $this->_messages).'</li></ul>';
    }

    /**
     * Return fields schema
     * @return array fields schema
     */
    public function fields()
    {
        return $this->_properties[__FUNCTION__];
    }

    /**
     * return field alias
     */
    public function field($name)
    {
        return isset($this->_properties['fields'][$name])?
            $this->_properties['fields'][$name]:null;
    }

    /**
     * init
     */
    public function init()
    {
        $this->_schema['values'] = $this->_schema['init'];
        $this->_schema['others'] = array();

        return $this;
    }

    /**
    *   Hydrate mapper object using hive array variable
    *   @return NULL
    *   @param $key string
    *   @param $func callback
    **/
    public function copyfrom($key,$func=NULL)
    {
        $var=is_array($key)?$key:Instance::get($key);
        if ($func)
            $var=call_user_func($func,$var);
        foreach ($var as $key=>$val)
            !isset($this->_properties['fields'][$key]) || $this->set($key, $val);

        return $this;
    }

    /**
    *   Populate hive array variable with mapper fields
    *   @return NULL
    *   @param $key string
    **/
    public function copyto($key)
    {
        $var=&Base::instance()->ref($key);
        foreach (array_merge($this->_schema['init'],
                 array_filter($this->_schema['values'], array($this, 'filterRule')),
                 array_filter($this->_schema['others'], array($this, 'filterRule'))) as $key=>$val)
            $var[$key]=$val;

        return $this;
    }

    /**
     * Set to others
     */
    public function clear($var)
    {
        $this->_schema['others'][$var] = null;

        return $this;
    }

    /**
     * Set to others
     */
    public function set($var, $val)
    {
        $this->_schema['others'][$var] = $val;

        return $this;
    }

    /**
     * Get fields value
     */
    public function get($var)
    {
        return isset($this->_schema['values'][$var])?
                     $this->_schema['values'][$var]:(
                isset($this->_schema['others'][$var])?
                      $this->_schema['others'][$var]:null);
    }

    public function select($fields, $overwrite = true)
    {
        if ($overwrite)
            $this->_schema['select'][__FUNCTION__] = $fields;
        else
            $this->_schema['select'][__FUNCTION__] .= ', '.$fields;

        return $this;
    }

    public function from($table, $overwrite = false)
    {
        if ($overwrite)
            $this->_schema['select'][__FUNCTION__] = $table;
        else
            $this->_schema['select'][__FUNCTION__] .= ', '.$table;

        return $this;
    }

    public function join($table, $on = '', $mode = '')
    {
        $this->_schema['select'][__FUNCTION__] .= $on?trim($mode).' join '.$table.' on '.$on:$table;

        return $this;
    }

    public function where($criteria, array $values = array(), $before = 'and')
    {
        $this->_schema['select'][__FUNCTION__] .= ($this->_schema['select'][__FUNCTION__]?' '.$before.' ':'').trim($criteria);

        return $this->params($values);
    }

    public function group($columns)
    {
        !$columns || $this->_schema['select'][__FUNCTION__] .= ' '.trim($columns);

        return $this;
    }

    public function having($criteria, array $values = array(), $before = 'and')
    {
        $this->_schema['select'][__FUNCTION__] .= ($this->_schema['select'][__FUNCTION__]?' '.$before.' ':'').trim($criteria);

        return $this->params($values);
    }

    public function order($columns)
    {
        !$columns || $this->_schema['select'][__FUNCTION__] .= ' '.trim($columns);

        return $this;
    }

    public function limit($limit)
    {
        $limit < 1 || $this->_schema['select'][__FUNCTION__] = $limit;

        return $this;
    }

    public function offset($offset)
    {
        $this->_schema['select'][__FUNCTION__] = $offset;

        return $this;
    }

    public function params(array $params)
    {
        $this->_schema['select'][__FUNCTION__] = array_merge($this->_schema['select'][__FUNCTION__], $params);

        return $this;
    }

    public function initSelect()
    {
        $this->_schema['select'] = $this->_select;

        return $this;
    }

    public function buildSelect()
    {
        $this->_schema['select']['join'] = $this->buildRelation().PHP_EOL.$this->_schema['select']['join'];

        $cp = $this->_schema['select'];

        !$this->_schema['config']['resetAfterBuild'] || $this->initSelect();

        $cp['select']  = 'select '.trim($cp['select']);
        $cp['from']    = 'from (' .trim($cp['from']).')';
        !$cp['where']  || $cp['where']  = 'where '          .trim($cp['where']);
        !$cp['group']  || $cp['group']  = 'group by '       .trim($cp['group']);
        !$cp['having'] || $cp['having'] = 'having '         .trim($cp['having']);
        !$cp['order']  || $cp['order']  = 'order by '       .trim($cp['order']);
        !$cp['limit']  || $cp['limit']  = 'limit '          .trim($cp['limit']);
        $cp['offset']  = $cp['offset']=== (''?'':'offset ') .trim($cp['offset']);

        return array(
            'param'=>array_pop($cp)?:array(),
            'query'=>implode(PHP_EOL, array_filter($cp)),
            );
    }

    /**
     * where synonym
     */
    public function find($criteria, array $values = [])
    {
        return $this->where($criteria, $values)->read();
    }

    /**
     * Find by PK
     */
    public function findByPK()
    {
        $pk = func_get_args();
        if (!$this->_properties['primary_key'] || !$pk)
            throw new Exception(sprintf(self::E_PrimaryKey, get_called_class()), 1);

        !is_array(reset($pk)) || $pk = array_shift($pk);
        $criteria = $values = array();
        foreach ($this->_properties['primary_key'] as $field) {
            $token = ':pk_'.$field;
            $criteria[] = '{table}.'.$field.'='.$token;
            $values[$token] = isset($pk[$field])?$pk[$field]:array_shift($pk);
        }

        return $this->limit(1)->find(implode(' and ', $criteria), $values);
    }

    /**
     * Get PK Value
     * @return mixed pk value
     */
    public function pkValue()
    {
        if ($args = func_get_args()) {
            !is_array($args[0]) || $args = array_shift($args);
            $args = array_values($args);
            foreach ($args as $key=>$arg)
                $this->{$this->_properties['primary_key'][$key]} = $arg;

            return $this;
        }

        $pk = array_intersect_key($this->_schema['values'],
            array_fill_keys($this->_properties['primary_key'], null));

        return count($pk)==0?null:(count($pk)==1?array_shift($pk):$pk);
    }

    /**
     * Compare PK
     */
    public function pkCompare($pk)
    {
        $pkThis = $this->pkValue();

        is_array($pkThis) || $pkThis = array($pkThis);
        is_array($pk)     || $pk = array($pk);

        if (count($pkThis)!=count($pk))
            return false;

        foreach ($pkThis as $key => $value)
            if (!isset($pk[$key]) || $pk[$key]!=$value)
                return false;

        return true;
    }

    /**
     * Assign new pk
     */
    public function regeneratePK()
    {
        $this->wet() || $this->pkValue($this->generatePK());

        return $this;
    }

    /**
     * Generate PK only if pk count = 1
     * @return string Next PK
     */
    public function generatePK()
    {
        $format = $this->pkFormat;
        $pk     = $this->_properties['primary_key'];
        if (isset($pk[1]))
            throw new Exception(sprintf(self::E_Composit, get_called_class(), __FUNCTION__), 1);

        $pk     = array_shift($pk);
        if (!$pk)
            throw new Exception(sprintf(self::E_PrimaryKey, get_called_class()), 1);

        $last = (int) $this
            ->select(($format['serial']?
                'right('.$pk.', '.$format['serial'].')':
                $pk).' as last')
            ->order($pk.' desc')
            ->read(1)
            ->last;

        // compile prefix format
        if (preg_match('/\{(?<date>.+)\}/', $format['prefix'], $match))
            $newPK = preg_replace('/\{.+\}/', date($match['date']), $format['prefix']);
        else
            $newPK = $format['prefix'];

        $newPK .= trim(str_pad($last+1, $format['serial'], $format['prefix']?'0':' ', STR_PAD_LEFT));

        return $newPK;
    }

    /**
     * Check existance
     */
    public function exists($criteria = null, array $values = array())
    {
        if (!$this->_properties['primary_key'] || (!$values && !$criteria))
            throw new Exception(sprintf(self::E_PrimaryKey, get_called_class()), 1);

        $that = clone $this;
        $values ? $that->find($criteria, $values) : $that->findByPK($criteria);

        return $that->wet();
    }

    /**
     * Check not existance
     */
    public function unique($criteria = null, array $values = array())
    {
        if (!$this->_properties['primary_key'] || (!$values && !$criteria))
            throw new Exception(sprintf(self::E_PrimaryKey, get_called_class()), 1);

        $that = clone $this;
        $values ? $that->find($criteria, $values) : $that->findByPK($criteria);

        return ($that->dry() || $this->pkCompare($that->pkValue()));
    }

    /**
     * Get data
     */
    public function cast($init = true)
    {
        return array_merge($init?$this->_schema['init']:array(),
            array_filter($this->_schema['values'], array($this, 'filterRule')),
            array_filter($this->_schema['others'], array($this, 'filterRule')));
    }

    /**
     * Paging
     */
    public function page($page = 1, $limit = 10)
    {
        $query = $this
            ->disableResetAfterBuild()
            ->limit($limit)
            ->offset($page*$limit-$limit)
            ->buildSelect();

        return [
            'data'=>$this->run($query['query'], $query['param'])?
                $this->_stmt->fetchAll($this->_schema['fetch']):[],
            'recordsTotal'=>($total = $this
                ->enableResetAfterBuild()
                ->limit(0)
                ->offset(0)
                ->count(true)),
            'totalPage'=>$limit>0?ceil($total/$limit):0,
            ];
    }

    /**
     * Check weather
     */
    public function dry()
    {
        return count(array_filter($this->_schema['values'], array($this, 'filterRule')))==0;
    }

    public function wet()
    {
        return !$this->dry();
    }

    /**
     * get next row and assign row to schema values
     */
    public function next()
    {
        if (!$this->hasError()) {
            $row = $this->_stmt->fetch($this->_schema['fetch']);
            $this->assign($row?:array_fill_keys(array_keys(
                $this->_properties['fields']), null));
        }

        return $this;
    }

    /**
     * perform select query
     */
    public function read($limit = 0)
    {
        $this->limit($limit);
        $query = $this->buildSelect();
        $this->run($query['query'], $query['param']);

        return $this->next();
    }

    /**
     * Get all
     */
    public function readAll($limit = 0)
    {
        $this->limit($limit);
        $query = $this->buildSelect($params);

        return $this->run($query['query'], $query['param'])?$this->_stmt->fetchAll($this->_schema['fetch']):array();
    }

    /**
     * Get count
     */
    public function count($force = false)
    {
        if ($this->_stmt && !$force)
            return $this->_stmt->rowCount();

        $this->select('count(*)', true);
        $query = $this->buildSelect($params);

        return $this->run($query['query'], $query['param'])?$this->_stmt->fetchColumn(0):0;
    }

    /**
     * Save
     */
    public function save(array $data = array(), $update = false)
    {
        $this->viewCheck(__FUNCTION__);

        return $this->dry()?$this->insert($data, $update):$this->update($data);
    }

    /**
     * Insert, on duplicate key update
     */
    public function insert(array $data = array(), $update = false)
    {
        $this->viewCheck(__FUNCTION__);

        $data = array_merge($this->_schema['init'],
            array_filter($this->_schema['values'], array($this, 'filterRule')),
            array_filter($this->_schema['others'], array($this, 'filterRule')),
            $data);

        $params = array();
        foreach ($data as $key => $value)
            if (in_array($key, $this->_properties['primary_key']) && !$value)
                continue;
            elseif (is_array($value))
                return false;
            elseif (isset($this->_properties['fields'][$key]))
                $params[':'.$key] = $value;

        if (empty($params) || ($update && !$this->_properties['primary_key']))
            return false;

        $query = $this->buildInsert($params, $update);

        if (!($query && $this->validate($params)))
            return false;

        $old = clone $this;
        if (!$this->beforeInsert($params))
            return false;

        if ($result = $this->run($query, $params))
            $this->assign($params);

        if (!$this->afterInsert($old))
            return false;

        return $result;
    }

    /**
     * Update, only when there is primary key
     */
    public function update(array $data = array(), array $criteria = array())
    {
        $this->viewCheck(__FUNCTION__);

        $data = array_merge(
            array_filter($this->_schema['others'], array($this, 'filterRule')),
            $data);

        $params = array();
        $values = array_filter($this->_schema['values']);
        $others = array_filter($this->_schema['others']);
        foreach ($data as $key => $value)
            if (is_array($value))
                return false;
            elseif (isset($this->_properties['fields'][$key]))
                if (isset($values[$key]) && isset($others[$key])
                    && $values[$key]==$others[$key]
                )
                    continue;
                else
                    $params[':'.$key] = $value;

        if (empty($params))
            return true;

        $query = $this->buildUpdate($params, $criteria);

        if (!($query && $this->validate($params)))
            return false;

        $old = clone $this;
        if (!$this->beforeUpdate($params))
            return false;

        if ($result = $this->run($query, $params))
            $this->assign($params);

        if (!$this->afterUpdate($old))
            return false;

        return $result;
    }

    /**
     * delete, only when there is primary key
     */
    public function delete(array $criteria = [])
    {
        $this->viewCheck(__FUNCTION__);

        $query = $this->buildDelete($criteria);

        $old = clone $this;
        if (!$this->beforeDelete($criteria))
            return false;

        if ($result = $this->run($query, $criteria))
            $this->next();

        if (!$this->afterDelete($old))
            return false;

        return $result;
    }

    public function validate(array &$params)
    {
        if (!$this->_schema['config']['validation'])
            return true;

        foreach ($params as $token => $value)
            if (!$this->performValidate(str_replace(':', '', $token), $params[$token]))
                return false;

        return true;
    }

    /**
     * Get database instance
     */
    public function db()
    {
        return DB::instance();
    }

    /**
     * Get validation object
     */
    public function validation()
    {
        return Validation::instance();
    }

    /**
     * Fetch mode
     */
    public function fetchMode($mode)
    {
        if ($mode = @constant('PDO::FETCH_'.strtoupper($mode)))
            $this->_schema['fetch'] = $mode;

        return $this;
    }

    /**
     * Set relation to use, can use 'all' or relation key to use all relation
     */
    public function useRelation($what = 'all')
    {
        $this->relation = $what;

        return $this;
    }

    /**
     * insert Builder
     */
    public function buildInsert(&$params, $update = false)
    {
        $values = implode(',', array_keys($params));
        $query  = 'insert into '.$this->table().' ('.
            str_replace(':', '', $values).') values'.PHP_EOL.' ('.
            $values.')';

        if ($update) {
            $update = array();
            foreach ($params as $token=>$value) {
                $field = str_replace(':', '', $token);
                in_array($field, $this->_properties['primary_key']) || $update[] = $field.'='.$token;
            }
            !$update || $query .= PHP_EOL.' on duplicate key update '.implode(',', $update);
        }

        return $query;
    }

    /**
     * update Builder
     */
    public function buildUpdate(&$params, array $criteria = array())
    {
        $set = '';
        foreach ($params as $token=>$value) {
            $field = str_replace(':', '', $token);
            $set .= $field.'='.$token.',';
        }

        $where = $where_param = array();
        if ($criteria) {
            foreach ($criteria as $field=>$value) {
                $token = ':'.$field.'_where';
                $where[] = $field.'='.$token;
                $where_param[$token] = $value;
            }
        } elseif ($this->wet()) {
            foreach ($this->_properties['primary_key'] as $field) {
                $token = ':'.$field.'_where';
                $where[] = $field.'='.$token;
                $where_param[$token] = $this->_schema['values'][$field];
            }
        }

        if (!$where || !$set)
            return;

        $params = array_merge($params, $where_param);
        $query = 'update '.$this->table().' set '.PHP_EOL.rtrim($set, ',').PHP_EOL.' where '.implode(' and ', $where);

        return $query;
    }

    /**
     * delete Builder
     */
    public function buildDelete(array &$criteria = array())
    {
        $where = $where_param = array();
        if ($criteria) {
            foreach ($criteria as $field=>$value) {
                $token = ':'.$field.'_where';
                $where[] = $field.'='.$token;
                $where_param[$token] = $value;
            }
        } elseif ($this->wet()) {
            foreach ($this->_properties['primary_key'] as $field) {
                $token = ':'.$field.'_where';
                $where[] = $field.'='.$token;
                $where_param[$token] = $this->_schema['values'][$field];
            }
        }

        if (!$where)
            return;

        $criteria = $where_param;
        $query = 'delete from '.$this->table().' where '.implode(' and ', $where);

        return $query;
    }

    /**
     * For filtering rule
     */
    public function filterRule($var)
    {
        return !(is_null($var) || false===$var);
    }

    /**
     * Perform validation
     * We can use method in Base class or any method with full name call but
     * you need to define a message to override default message
     * Be aware when use a method
     */
    protected function performValidate($key, &$value)
    {
        if (!$filter = $this->_schema['filter'][$key])
            return true;

        $validation = Validation::instance();
        $moe = Base::instance();
        $field = $this->_properties['fields'][$key];
        foreach ($filter as $func => $param) {
            if (is_numeric($func)) {
                $func = $param;
                $args = array();
            } else
                $args = [$param];

            $function = $func;
            if (method_exists($validation, $func))
                $func = array($validation, $func);
            elseif (method_exists($this, $func) ||
                preg_match('/^('.self::Magic.')/', $func))
                $func = array($this, $func);
            elseif (method_exists($moe, $func))
                $func = array($moe, $func);

            array_unshift($args, $value);
            if (false === $result = $moe->call($func, $args)) {
                $this->_messages[] = $validation->message($function, $field, $value, $param);
                return false;
            } else
                is_bool($result) || $value = $result;
        }

        return true;
    }

    /**
     * Assign
     */
    protected function assign(array $data)
    {
        foreach ($data as $key => $value) {
            $key = str_replace(':', '', $key);
            if (isset($this->_properties['fields'][$key]))
                $this->_schema['values'][$key] = $value;
            else
                $this->_schema['others'][$key] = $value;
        }

        return $this;
    }

    /**
     * Run query
     */
    protected function run($query, array $params)
    {
        if (!$query)
            return;

        $query  = str_replace('{table}', $this->table(), $query);
        $pdo    = $this->db()->pdo;
        if ($this->_schema['config']['log']) {
            $quoted = $params;
            foreach ($quoted as $key => $value)
                $quoted[$key] = $pdo->quote($value);
            $this->_logs[]    = str_replace(array_keys($quoted), array_values($quoted), $query);
            $this->_queries[] = $query;
            unset($quoted);
        }

        if (!($this->_schema['config']['reUseStatement'] && $this->_stmt))
            $this->_stmt = $pdo->prepare($query);

        $this->_stmt->execute($params);
        if ($this->_stmt->errorCode()!='00000') {
            $this->_errors[] = Instance::stringify($this->_stmt->errorInfo());

            return false;
        }

        return true;
    }

    /**
     * Build relation
     */
    protected function buildRelation()
    {
        if (!($this->relation && $relation = $this->relation()))
            return '';

        if (isset($relation[$this->relation]))
            $relation = [$this->relation=>$relation[$this->relation]];
        elseif (strpos($this->relation, ',')!==false) {
            $tmp = explode(',', $this->relation);
            foreach ($relation as $key => $value)
                if (!in_array($key, $tmp))
                    unset($relation[$key]);
        } elseif ($this->relation!='all')
            throw new Exception(sprintf(self::E_NoRelation, get_called_class(), $this->relation), 1);

        $rel = array();
        foreach ($relation as $key => $value) {
            if (is_numeric($key))
                throw new Exception(sprintf(self::E_Relation, get_called_class()), 1);

            $tmp = explode(' ', $key);
            $table = $tmp[0];
            $alias = isset($tmp[1])?$tmp[1]:null;
            if (strpos($value, 'select')!==false) {
                $alias = $table;
                $table = null;
            }

            $rel[] = str_replace(array('{join}', '{j}'), array($table, $alias), $value);
        }

        return implode(PHP_EOL, $rel);
    }

    /**
     * Translate param
     */
    protected function translateParam($method, array $args)
    {
        $params = array();
        foreach ($this->translateColumn($method) as $column)
            if (!$args)
                throw new Exception(sprintf(self::E_Param, get_called_class(), $column), 1);
            else
                $params[$column] = array_shift($args);

        return $params;
    }

    /**
     * Translate Criteria
     */
    protected function translateCriteria($method, array $args)
    {
        $result = [
            'criteria'=>'',
            'params'=>[]
            ];
        $columns = $this->translateColumn($method);
        $n_token = '{not}';
        $p_token = '{and}{or}';
        $o_token = '{in}{between}{like}{begin}{end}{null}';
        $last    = count($columns);
        for ($pointer = 0; $pointer < $last; $pointer++) {
            $column   = $columns[$pointer];
            if ($this->inToken($column, $n_token))
                continue;
            $negation = (isset($columns[$pointer-1]) && $this->inToken($columns[$pointer-1], $n_token));
            $punc     = 'and';
            $operator = ($negation?'!':'').'=';
            $params   = array();
            $token    = ':'.$column;
            $skip     = 0;
            if (isset($columns[$pointer+1])) {
                // bisa jadi punc or opr
                $next = $columns[$pointer+1];
                ++$skip;
                if ($this->inToken($next, $p_token)) {
                    $punc = $next;
                } elseif ($this->inToken($next, $o_token)) {
                    switch ($next) {
                        case 'in': # in (:field1, :field2, ..., :fieldn)
                            if (!$args)
                                throw new Exception(sprintf(self::E_Param, get_called_class(), $column), 1);
                            $operator = ($negation?'not ':'').$next;
                            $arg      = array_shift($args);
                            is_array($arg) || $arg = [$arg];
                            $i = 1;
                            $token = '(';
                            foreach ($arg as $value) {
                                $t          = ':'.$column.$i++;
                                $token      .= $t.',';
                                $params[$t] = $value;
                            }
                            $token = rtrim($token,',').')';
                            break;
                        case 'between': # between :field1 and :field2
                            if (!$args)
                                throw new Exception(sprintf(self::E_Param, get_called_class(), $column), 1);
                            $operator = ($negation?'not ':'').$next;
                            $arg      = array_shift($args);
                            is_array($arg) || $arg = [$arg];
                            if (count($arg)<2)
                                throw new Exception(sprintf(self::E_Param, $column), 1);
                            $token = ':'.$column.'1 and :'.$column.'2';
                            $params[':'.$column.'1'] = array_shift($arg);
                            $params[':'.$column.'2'] = array_shift($arg);
                            break;
                        case 'like': # %:field%
                        case 'begin': # :field%
                        case 'end': # %:field
                            if (!$args)
                                throw new Exception(sprintf(self::E_Param, get_called_class(), $column), 1);
                            $operator = ($negation?'not ':'').'like';
                            $params[$token] = ($next=='begin'?'':'%').
                                              array_shift($args).
                                              ($next=='end'?'':'%');
                            break;
                        case 'null':
                            $token    = '';
                            $operator = 'is '.($negation?'not ':'').$next;
                            break;
                    }
                } else
                    --$skip;

                if (isset($columns[$pointer+2]) &&
                    $this->inToken($columns[$pointer+2], $p_token)
                ) {
                    $punc = $columns[$pointer+2];
                    ++$skip;
                }
            } else
                $params[$token] = array_shift($args);

            $result['criteria'] .= rtrim(' {table}.'.$column.' '.$operator.' '.$token.' '.$punc);
            $result['params']   = array_merge($result['params'], $params);
            $pointer += $skip;
        }

        $result['criteria'] = preg_replace('/ (and|or)$/i', '', $result['criteria']);

        return $result;
    }

    protected function translateColumn($str)
    {
        $columns = array_values(array_filter(explode('_', Instance::snakecase($str))));
        $last = count($columns);
        $newColumns = array();
        for ($i=0; $i < $last; $i++) {
            $column = $columns[$i];
            $isset  = false;
            $skip   = 0;
            for ($j=$i+1; $j < $last; $j++)
                if (isset($columns[$j])) {
                    $column .= '_'.$columns[$j];
                    if ($isset = isset($this->_properties['fields'][$column])) {
                        $skip = $j-$i;
                        break;
                    }
                }
            $newColumns[] = $isset?$column:$columns[$i];
            $i += $skip;
        }

        return $newColumns;
    }

    protected function inToken($what, $token)
    {
        return strpos($token, '{'.$what.'}')!==false;
    }

    private function viewCheck($method)
    {
        if ($this->isView)
            throw new Exception(sprintf(self::E_View, $method), 1);
    }

    public function __isset($var)
    {
        return (isset($this->_schema['values'][$var]) ||
                isset($this->_schema['others'][$var]));
    }

    public function __unset($var)
    {
        $this->clear($var);
    }

    public function __set($var, $val)
    {
        $this->set($var, $val);
    }

    public function __get($var)
    {
        return $this->get($var);
    }

    public function __call($method, array $args)
    {
        if (preg_match('/^(?<method>'.self::Magic.')/', $method, $match)) {
            $func   = $match['method'];
            $method = str_replace($func, '', $method);
            $func   = str_replace('By', '', $func);
            switch ($func) {
                case 'delete':
                case 'update':

                    return call_user_func_array(array($this, $func), [$this->translateParam($method, $args)]);
                default:
                    $argsExec = array();
                    if (preg_match('/^PK(And)?/', $method, $match)) {
                        $method     = preg_replace('/^PK(And)?/', '', $method);
                        $func       = 'findByPK';
                        $argsExec[] = array_shift($args);
                        $find       = $this->translateCriteria($method, $args);

                        call_user_func_array(array($this, 'where'), array($find['criteria'], $find['params']));
                    } else {
                        $find     = $this->translateCriteria($method, $args);
                        $argsExec = array($find['criteria'], $find['params']);
                    }

                    return call_user_func_array(array($this, $func), $argsExec);
            }
        } elseif (preg_match('/^(?<en>enable|disable)/', $method, $match)) {
            $state = lcfirst(preg_replace('/^'.$match['en'].'/', '', $method));
            if (isset($this->_schema['config'][$state]))
                $this->_schema['config'][$state] = $match['en']=='enable';

            return $this;
        } else
            throw new Exception(sprintf(self::E_Method, get_called_class(), $method), 1);
    }

    public function __construct()
    {
        $this->initSelect();

        $pk = $this->primaryKey();
        $this->_properties['primary_key'] = is_array($pk)?$pk:[$pk];

        $default = array();
        foreach ($this->schema() as $key => $value) {
            is_array($value) || $value    = [$value];
            $field  = array_shift($value);
            $filter = array_shift($value);
                is_array($filter) || $filter = [$filter];
                $filter = array_filter($filter, array($this, 'filterRule'));
            $this->_properties['fields'][$key] = $field;
            $this->_schema['filter'][$key] = $filter;
            $this->_schema['values'][$key] = null;
            $default[$key]                 = array_shift($value);
        }

        foreach ($default as $key => $value)
            $this->_schema['init'][$key] = is_callable($value)?
                call_user_func($value): (strpos($value, '->')===false?$value:
                    Instance::call($value));

        if (!$this->isView && !array_filter($this->_properties['fields'], array($this, 'filterRule')))
            throw new Exception(sprintf(self::E_Schema, get_called_class()), 1);

        $this->_schema['config']['validation'] = count(array_filter($this->_schema['filter']?:array(), array($this, 'filterRule')))>0;
    }
}
