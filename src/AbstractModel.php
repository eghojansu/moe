<?php

namespace moe;

use Exception;
use PDO;

/**
 * This class require all fields in lowercase
 */
abstract class AbstractModel extends Prefab
{
    //! select Builder
    protected $select = array(
        'select' => '',
        'from'   => '',
        'join'   => '',
        'where'  => '',
        'group'  => '',
        'having' => '',
        'order'  => '',
        'limit'  => '',
        'offset' => '',
        'params' => array(),
        );
    //! Model
    protected $schema = array(
        //! PK field(s)
        'pk'  =>array(),
        //! Pair field and alias
        'fields'  =>array(),
        //! Pair field and filter
        'filters' =>array(),
        //! Pair field and value
        'init'    =>array(),
        'values'  =>array(),
        //! Other/updated values
        'others'  =>array(),
        );
    protected $validation      = false;
    protected $resetAfterBuild = true;
    protected $fetchMode       = PDO::FETCH_ASSOC;
    protected $pkFormat        = array('prefix'=>'', 'serial'=>0);
    protected $relation;

    protected $logs       = array();
    protected $errors     = array();
    protected $messages   = array();

    //! Statement handle
    protected $stmt;

    const
        Magic = 'where|findBy|existsBy|unique|deleteBy|update|having';

    const
        E_Method = 'Method doesn\'t exists',
        E_Param = 'Field %s: not enough parameters',
        E_Data = 'No data provided',
        E_Invalid = 'Invalid parameter',
        E_Query = 'Query error',
        E_Record = 'No record to fetch anymore',
        E_Schema = 'Schema was no defined',
        E_PrimaryKey = 'Primary key was no defined',
        E_Relation = 'Relation key must be table name and (or) its alias',
        E_NoRelation = 'Relation key "%s" was not exi, $this->relationsts';

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

    /**
     * Return table name
     */
    public function table()
    {
        $class = explode('\\', get_called_class());
        return Instance::snakecase(lcfirst(end($class)));
    }

    /**
     * Get last query
     */
    public function lastQuery()
    {
        return end($this->logs);
    }

    /**
     * Get log
     */
    public function log()
    {
        return $this->logs;
    }

    /**
     * Has error
     */
    public function hasError()
    {
        return count($this->errors)>0;
    }

    /**
     * Get error
     */
    public function error()
    {
        return $this->errors;
    }

    /**
     * Has message
     */
    public function hasMessage()
    {
        return count($this->messages)>0;
    }

    /**
     * Get message
     */
    public function message()
    {
        return $this->messages;
    }

    /**
     * Return fields schema
     * @return array fields schema
     */
    public function fields()
    {
        return $this->schema[__FUNCTION__];
    }

    /**
     * return field alias
     */
    public function field($name)
    {
        return isset($this->schema['fields'][$name])?
            $this->schema['fields'][$name]:null;
    }

    /**
     * array as html list
     */
    public function asList($what)
    {
        return '<ul><li>'.implode('</li><li>',
            in_array($what, array('messages', 'errors', 'logs'))
            ?$this->{$what}:array()).'</li></ul>';
    }

    /**
     * Reset
     */
    public function reset($what = 'values')
    {
        switch ($what) {
            case 'select':
                foreach ($this->{$what} as $key => $value)
                    $this->{$what}[$key] = is_array($value)?array():'';
                $this->relation = false;
                break;
            default:
                $this->schema['values'] = $this->schema['init'];
                $this->schema['others'] = array();
                break;
        }

        return $this;
    }

    /**
    *   Hydrate mapper object using hive array variable
    *   @return NULL
    *   @param $key string
    *   @param $func callback
    **/
    public function copyfrom($key,$func=NULL) {
        $var=is_array($key)?$key:Instance::get($key);
        if ($func)
            $var=call_user_func($func,$var);
        foreach ($var as $key=>$val)
            (in_array($key, $this->schema['pk']) ||
                !isset($this->schema['fields'][$key])) ||
            $this->set($key, $val);

        return $this;
    }

    /**
    *   Populate hive array variable with mapper fields
    *   @return NULL
    *   @param $key string
    **/
    public function copyto($key) {
        $var=&Base::instance()->ref($key);
        foreach (array_merge($this->schema['init'],
                 array_filter($this->schema['values'], array($this, 'filterRule')),
                 array_filter($this->schema['others'], array($this, 'filterRule'))) as $key=>$val)
            $var[$key]=$val;

        return $this;
    }

    /**
     * Set to others
     */
    public function set($var, $val)
    {
        $this->schema['others'][$var] = $val;

        return $this;
    }

    /**
     * Get fields value
     */
    public function get($var)
    {
        return isset($this->schema['values'][$var])?
                     $this->schema['values'][$var]:(
                isset($this->schema['others'][$var])?
                      $this->schema['others'][$var]:null);
    }

    /**
     * Select
     */
    public function select($fields = '*', $overwrite = false)
    {
        if ($overwrite)
            $this->select[__FUNCTION__] = $fields;
        else
            $this->select[__FUNCTION__] .= $fields;

        return $this;
    }

    /**
     * Join
     */
    public function join($table, $on = '', $mode = '')
    {
        $this->select[__FUNCTION__] .= $on?trim($mode).' join '.$table.' on '.$on:$table;

        return $this;
    }

    /**
     * Where
     */
    public function where($criteria, array $values = array(), $before = 'and')
    {
        $this->select[__FUNCTION__] .= ($this->select[__FUNCTION__]?
            ' '.$before.' ':'').trim($criteria);

        return $this->params($values);
    }

    /**
     * Params
     */
    public function params(array $params)
    {
        $this->select['params'] = array_merge($this->select['params'], $params);

        return $this;
    }

    /**
     * Group by
     */
    public function group($columns)
    {
        !$columns || $this->select[__FUNCTION__] .= ($this->select[__FUNCTION__]?
            ' ':'').trim($columns);

        return $this;
    }

    /**
     * Having
     */
    public function having($criteria, array $values = array(), $before = 'and')
    {
        $this->select[__FUNCTION__] .= ($this->select[__FUNCTION__]?
            ' '.$before.' ':'').trim($where);

        return $this->params($values);
    }

    /**
     * Order by
     */
    public function order($columns)
    {
        !$columns || $this->select[__FUNCTION__] .= ($this->select[__FUNCTION__]?
            ' ':'').trim($columns);

        return $this;
    }

    /**
     * Limit
     */
    public function limit($limit)
    {
        !$limit || $this->select[__FUNCTION__] = $limit;

        return $this;
    }

    /**
     * Offset
     */
    public function offset($offset)
    {
        $this->select[__FUNCTION__] = $offset;

        return $this;
    }

    /**
     * where synonym
     */
    public function find($criteria, array $values = array())
    {
        return $this->where($criteria, $values);
    }

    /**
     * Find by PK
     */
    public function findByPK()
    {
        $pk = func_get_args();
        if (!$this->schema['pk'] || !$pk)
            throw new Exception(self::E_PrimaryKey, 1);

        !is_array(reset($pk)) || $pk = array_shift($pk);
        $criteria = $values = array();
        foreach ($this->schema['pk'] as $field) {
            $token = ':pk_'.$field;
            $criteria[] = $field.'='.$token;
            $values[$token] = isset($pk[$field])?$pk[$field]:array_shift($pk);
        }

        return $this->find(implode(' and ', $criteria), $values);
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
                $this->{$this->schema['pk'][$key]} = $arg;
            return $this;
        }

        $pk = array_intersect_key($this->schema['values'],
            array_fill_keys($this->schema['pk'], null));

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
        !$this->dry() || $this->pkValue($this->generatePK());

        return $this;
    }

    /**
     * Generate PK only if pk count = 1
     * @return string Next PK
     */
    public function generatePK()
    {
        $format = $this->pkFormat;
        $pk     = $this->schema['pk'];
        if (isset($pk[1]))
            throw new Exception('This method not designed to work with multiple primary key', 1);

        $pk   = array_shift($pk);
        if (!$pk)
            throw new Exception(self::E_PrimaryKey, 1);

        $last = (int) $this
            ->select(($format['serial']?
                'right('.$pk.', '.$format['serial'].')':
                $pk).' as last')
            ->order($pk.' desc')
            ->read(1)
            ->last;

        return $format['prefix'].trim(str_pad($last+1,
            $format['serial'], $format['prefix']?'0':' ', STR_PAD_LEFT));
    }

    /**
     * Check existance
     */
    public function exists($criteria = null, array $values = array())
    {
        if (!$this->schema['pk'] || (!$values && !$criteria))
            throw new Exception(self::E_PrimaryKey, 1);

        $that = clone $this;
        if ($values)
            $that->find($criteria, $values);
        else
            $that->findByPK($criteria);

        return !$that->read(1)->dry();
    }

    /**
     * Check not existance
     */
    public function unique($criteria = null, array $values = array())
    {
        if (!$this->schema['pk'] || (!$values && !$criteria))
            throw new Exception(self::E_PrimaryKey, 1);

        $that = clone $this;
        if ($values)
            $that->find($criteria, $values);
        else
            $that->findByPK($criteria);
        $that->read(1);

        return ($that->dry() || $this->pkCompare($that->pkValue()));
    }

    /**
     * Get data
     */
    public function cast($init = true)
    {
        return array_merge($init?$this->schema['init']:array(),
            array_filter($this->schema['values'], array($this, 'filterRule')),
            array_filter($this->schema['others'], array($this, 'filterRule')));
    }

    /**
     * Paging
     */
    public function page($page = 1, $limit = 10)
    {
        $query = $this
            ->limit($limit)
            ->offset($page*$limit-$limit)
            ->disableResetAfterBuild()
            ->buildSelect($params);

        return array(
            'data'=>$this->run($query, $params)?
                $this->stmt->fetchAll($this->fetchMode):array(),
            'recordsTotal'=>($total = $this
                ->enableResetAfterBuild()
                ->limit(0)
                ->offset(0)
                ->count(true)),
            'totalPage'=>$limit>0?ceil($total/$limit):0,
            );
    }

    /**
     * Check weather
     */
    public function dry()
    {
        return count(array_filter($this->schema['values'], array($this, 'filterRule')))==0;
    }

    /**
     * get next row and assign row to schema values
     */
    public function fetch()
    {
        if (!$this->hasError()) {
            $row = $this->stmt->fetch($this->fetchMode);
            $this->assign($row?:array_fill_keys(array_keys(
                $this->schema['fields']), null));
        }

        return $this;
    }

    /**
     * perform select query
     */
    public function read($limit = 0)
    {
        $this->limit($limit);
        $query = $this->buildSelect($params);
        $this->run($query, $params);

        return $this->fetch();
    }

    /**
     * Get all
     */
    public function all($limit = 0)
    {
        $this->limit($limit);
        $query = $this->buildSelect($params);

        return $this->run($query, $params)?
            $this->stmt->fetchAll($this->fetchMode):array();
    }

    /**
     * Get count
     */
    public function count($force = false)
    {
        if ($this->stmt && !$force)
            return $this->stmt->rowCount();

        $this->select('count(*)', true);
        $query = $this->buildSelect($params);

        return $this->run($query, $params)?$this->stmt->fetchColumn(0):0;
    }

    /**
     * Save
     */
    public function save(array $data = array())
    {
        return $this->dry()?$this->insert($data):$this->update($data);
    }

    /**
     * Insert, on duplicate key update
     */
    public function insert(array $data = array(), $update = false)
    {
        $data = array_merge($this->schema['init'],
            array_filter($this->schema['values'], array($this, 'filterRule')),
            array_filter($this->schema['others'], array($this, 'filterRule')),
            $data);

        $params = array();
        foreach ($data as $key => $value)
            if (in_array($key, $this->schema['pk']) && !$value)
                continue;
            elseif (is_array($value))
                return false;
            elseif (isset($this->schema['fields'][$key]))
                $params[':'.$key] = $value;

        if (empty($params) || ($update && !$this->schema['pk']))
            return false;

        $query = $this->buildInsert($params, $update);

        if (!($query && $this->validate($params)))
            return false;

        if ($result = $this->run($query, $params))
            $this->assign($params);

        return $result;
    }

    /**
     * Update, only when there is primary key
     */
    public function update(array $data = array(), array $criteria = array())
    {
        $data = array_merge(
            array_filter($this->schema['others'], array($this, 'filterRule')),
            $data);

        $params = array();
        $values = array_filter($this->schema['values']);
        $others = array_filter($this->schema['others']);
        foreach ($data as $key => $value)
            if (is_array($value))
                return false;
            elseif (isset($this->schema['fields'][$key]))
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

        if ($result = $this->run($query, $params))
            $this->assign($params);

        return $result;
    }

    /**
     * delete, only when there is primary key
     */
    public function delete(array $criteria = array())
    {
        $query = $this->buildDelete($criteria);
        if ($result = $this->run($query, $criteria))
            $this->fetch();

        return $result;
    }

    public function validate(array &$params)
    {
        if (!$this->validation)
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
            $this->fetchMode = $mode;

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
            str_replace(':', '', $values).') values ('.
            $values.')';

        if ($update) {
            $update = array();
            foreach ($params as $token=>$value) {
                $field = str_replace(':', '', $token);
                in_array($field, $this->schema['pk']) || $update[] = $field.'='.$token;
            }
            !$update || $query .= ' on duplicate key update '.implode(',', $update);
        }
        $this->logs[] = $query;

        return $this->lastQuery();
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
        } elseif (!$this->dry()) {
            foreach ($this->schema['pk'] as $field) {
                $token = ':'.$field.'_where';
                $where[] = $field.'='.$token;
                $where_param[$token] = $this->schema['values'][$field];
            }
        }

        if (!$where || !$set)
            return;

        $params = array_merge($params, $where_param);
        $query = 'update '.$this->table().' set '.rtrim($set, ',').' where '.implode(' and ', $where);
        $this->logs[] = $query;

        return $this->lastQuery();
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
        } elseif (!$this->dry()) {
            foreach ($this->schema['pk'] as $field) {
                $token = ':'.$field.'_where';
                $where[] = $field.'='.$token;
                $where_param[$token] = $this->schema['values'][$field];
            }
        }

        if (!$where)
            return;

        $criteria = $where_param;
        $query = 'delete from '.$this->table().' where '.implode(' and ', $where);
        $this->logs[] = $query;

        return $this->lastQuery();
    }

    /**
     * select Builder
     */
    public function buildSelect(&$params = array())
    {
        $this->select['select']  || $this->select();
        $this->select['join'] .= $this->buildRelation();
        $cp     = $this->select;
        $params = $cp['params'];
        unset($cp['params']);
        !$this->resetAfterBuild || $this->reset('select');

        !$cp['where']  || $cp['where']  = ' where '    .trim($cp['where']);
        !$cp['group']  || $cp['group']  = ' group by ' .trim($cp['group']);
        !$cp['having'] || $cp['having'] = ' having '   .trim($cp['having']);
        !$cp['order']  || $cp['order']  = ' order by ' .trim($cp['order']);
        !$cp['limit']  || $cp['limit']  = ' limit '    .trim($cp['limit']);
        $cp['offset']  = $cp['offset']==''?'':' offset '   .trim($cp['offset']);

        $cp['select']  = 'select '.trim($cp['select']);
        $cp['from']    = ' from '.$this->table();
        $this->logs[]  = str_replace('{table}', $this->table(),
            implode("\n", array_filter($cp)));

        return $this->lastQuery();
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
        if (!$filter = $this->schema['filter'][$key])
            return true;

        $validation = Validation::instance();
        $moe = Base::instance();
        $field = $this->schema['fields'][$key];
        foreach ($filter as $func => $param) {
            if (is_numeric($func)) {
                $func = $param;
                $args = array();
            } else
                $args = array($param);

            $function = $func;
            if (method_exists($validation, $func))
                $func = array($validation, $func);
            elseif (method_exists($this, $func) ||
                preg_match('/^'.self::Magic.'/', $func))
                $func = array($this, $func);
            elseif (method_exists($moe, $func))
                $func = array($moe, $func);

            array_unshift($args, $value);
            if (false === $result = $moe->call($func, $args)) {
                $this->messages[] = $validation->message($function, $field, $value, $param);
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
            if (isset($this->schema['fields'][$key]))
                $this->schema['values'][$key] = $value;
            else
                $this->schema['others'][$key] = $value;
        }

        return $this;
    }

    /**
     * Run query from builder
     */
    protected function run($query, $params)
    {
        if (!$query)
            return;

        $this->stmt = $this->db()->pdo->prepare($query);
        $this->stmt->execute($params);
        if ($this->stmt->errorCode()!='00000') {
            $this->errors[] = Instance::stringify($this->stmt->errorInfo());

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
            $relation = array($this->relation=>$relation[$this->relation]);
        elseif (strpos($this->relation, ',')!==false) {
            $tmp = explode(',', $this->relation);
            foreach ($relation as $key => $value)
                if (!in_array($key, $tmp))
                    unset($relation[$key]);
        } elseif ($this->relation!='all')
            throw new Exception(sprintf(self::E_NoRelation, $this->relation), 1);

        $master_table = $this->table();
        $rel = array();
        foreach ($relation as $key => $value) {
            if (is_numeric($key))
                throw new Exception(self::E_Relation, 1);

            $tmp = explode(' ', $key);
            $table = $tmp[0];
            $alias = isset($tmp[1])?$tmp[1]:null;
            if (strpos($value, 'select')!==false) {
                $alias = $table;
                $table = null;
            }

            $rel[] = str_replace(array('{join}', '{j}', '{table}'),
                array($table, $alias, $master_table), $value);
        }

        return implode("\n", $rel);
    }

    /**
     * Translate param
     */
    protected function translateParam($method, array $args)
    {
        $params = array();
        foreach ($this->translateColumn($method) as $column)
            if (!$args)
                throw new Exception(sprintf(self::E_Param, $column), 1);
            else
                $params[$column] = array_shift($args);

        return $params;
    }

    /**
     * Translate Criteria
     */
    protected function translateCriteria($method, array $args)
    {
        $result = array(
            'criteria'=>'',
            'params'=>array()
            );
        $columns = $this->translateColumn($method);
        $n_token = '{not}';
        $p_token = '{and}{or}';
        $o_token = '{in}{between}{like}{begin}{end}';
        $last    = count($columns);
        for ($pointer = 0; $pointer < $last; $pointer++) {
            $column   = $columns[$pointer];
            if ($this->inToken($column, $n_token))
                continue;
            if (!$args)
                throw new Exception(sprintf(self::E_Param, $column), 1);
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
                            $operator = ($negation?'not ':'').$next;
                            $arg = array_shift($args);
                            is_array($arg) || $arg = array($arg);
                            $i = 1;
                            $token = '(';
                            foreach ($arg as $value) {
                                $t = ':'.$column.$i++;
                                $token .= $t.',';
                                $params[$t] = $value;
                            }
                            $token = rtrim($token,',').')';
                            break;
                        case 'between': # between :field1 and :field2
                            $operator = ($negation?'not ':'').$next;
                            $arg = array_shift($args);
                            is_array($arg) || $arg = array($arg);
                            if (count($arg)<2)
                                throw new Exception(sprintf(self::E_Param, $column), 1);
                            $token = ':'.$column.'1 and :'.$column.'2';
                            $params[':'.$column.'1'] = array_shift($arg);
                            $params[':'.$column.'2'] = array_shift($arg);
                            break;
                        case 'like': # %:field%
                        case 'begin': # :field%
                        case 'end': # %:field*/
                            $operator = ($negation?'not ':'').'like';
                            $params[$token] = ($next=='begin'?'':'%').
                                              array_shift($args).
                                              ($next=='end'?'':'%');
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
            }

            $params || $params[$token] = array_shift($args);
            $result['criteria'] .= ' {table}.'.$column.' '.$operator.' '.$token.' '.$punc;
            $result['params'] = array_merge($result['params'], $params);
            $pointer += $skip;
        }
        $result['criteria'] = preg_replace('/ (and|or)$/i', '', $result['criteria']);

        return $result;
    }

    protected function translateColumn($str)
    {
        $columns = array_values(array_filter(explode('_', Instance::snakecase(lcfirst($str)))));
        $last = count($columns);
        $newColumns = array();
        for ($i=0; $i < $last; $i++) {
            $column = $columns[$i].(isset($columns[$i+1])?'_'.$columns[$i+1]:'');
            $isset  = isset($this->schema['fields'][$column]);
            $newColumns[] = $isset?$column:$columns[$i];
            $i += (int) $isset;
        }

        return $newColumns;
    }

    protected function inToken($what, $token)
    {
        return strpos($token, '{'.$what.'}')!==false;
    }

    public function __isset($var)
    {
        return (isset($this->schema['values'][$var]) ||
                isset($this->schema['others'][$var]));
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
            $func = $match['method'];
            $method = str_replace($func, '', $method);
            $func = str_replace('By', '', $func);
            switch ($func) {
                case 'delete':
                case 'update':
                    return $this->{$func}($this->translateParam($method, $args));
                default:
                    $find = $this->translateCriteria($method, $args);
                    return $this->{$func}($find['criteria'], $find['params']);
            }
        } elseif (preg_match('/^(?<en>enable|disable)/', $method, $match)) {
            $state = lcfirst(preg_replace('/^'.$match['en'].'/', '', $method));
            if (property_exists($this, $state) && is_bool($this->{$state}))
                $this->{$state} = $match['en']=='enable';
            return $this;
        } else
            throw new Exception(self::E_Method, 1);
    }

    public function __construct()
    {
        $pk = $this->primaryKey();
        $this->schema['pk'] = is_array($pk)?$pk:array($pk);

        foreach ($this->schema() as $key => $value) {
            is_array($value) || $value    = array($value);
            $field  = array_shift($value);
            $filter = array_shift($value);
                is_array($filter) || $filter = array($filter);
                $filter = array_filter($filter, array($this, 'filterRule'));
            $init   = array_shift($value);
            $this->schema['fields'][$key] = $field;
            $this->schema['filter'][$key] = $filter;
            $this->schema['init'][$key]   = $init;
            $this->schema['values'][$key] = null;
        }

        if (!array_filter($this->schema['fields'], array($this, 'filterRule')))
            throw new Exception(self::E_Schema, 1);

        $this->validation = count(array_filter($this->schema['filter'], array($this, 'filterRule')))>0;
    }
}
