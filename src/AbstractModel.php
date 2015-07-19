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
        //! PK field and alias
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
    protected $disableValidation = false;
    protected $useRelation = true;

    protected $logs = array();
    protected $errors = array();
    protected $messages = array();

    //! Statement handle
    protected $stmt;

    const
        Magic = 'findBy|existsBy|unique|deleteBy|update';

    const
        E_Method = 'Method doesn\'t exists',
        E_Param = 'Field %s: not enough parameters',
        E_Data = 'No data provided',
        E_Invalid = 'Invalid parameter',
        E_Query = 'Query error',
        E_Record = 'No record to fetch anymore',
        E_PrimaryKey = 'Primary key was no defined';

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
    abstract public function primaryKey();

    /**
     * Return table name
     */
    public function table()
    {
        $class = explode('\\', get_called_class());
        return Instance::snakecase(lcfirst(end($class)));
    }

    /**
     * Relation
     * Format :
     * [
     *     'join table on table.column = {table}.column',
     *     'join (select * from table) x on x.column = {table}.column'
     * ]
     */
    public function relation()
    {
        return array();
    }

    /**
     * Get db instance
     */
    public function db()
    {
        return DB::instance();
    }

    /**
     * Save
     */
    public function save(array $data = array())
    {
        $this->dry()?$this->insert($data):$this->update($data);
    }

    /**
     * Insert, on duplicate key update
     */
    public function insert(array $data = array(), $update = false)
    {
        $data = array_merge($this->schema['init'],
            array_filter($this->schema['values']),
            array_filter($this->schema['others']), $data);
        if (empty($data))
            throw new Exception(self::E_Data, 1);

        $params = array();
        foreach ($data as $key => $value)
            if (in_array($key, $this->schema['pk']) && !$value)
                continue;
            elseif (is_array($value))
                throw new Exception(self::E_Invalid, 1);
            elseif (isset($this->schema['fields'][$key]))
                $params[':'.$key] = $value;

        if ($update && !$this->schema['pk'])
            throw new Exception(self::E_PrimaryKey, 1);

        $query = $this->buildInsert($params, $update);

        if (!$this->validate($params))
            return false;

        $result = $this->run($query, $params);
        $this->assign($params);

        return $result;
    }

    /**
     * Update, only when there is primary key
     */
    public function update(array $data = array(), array $criteria = array())
    {
        $data = array_merge(array_filter($this->schema['others']), $data);
        if (empty($data))
            throw new Exception(self::E_Data, 1);

        $params = array();
        foreach ($data as $key => $value)
            if (is_array($value))
                throw new Exception(self::E_Invalid, 1);
            elseif (isset($this->schema['fields'][$key]))
                $params[':'.$key] = $value;

        $query = $this->buildUpdate($params, $criteria);

        if (!$this->validate($params))
            return false;

        $result = $this->run($query, $params);
        $this->assign($params);

        return $result;
    }

    public function validate(array &$params)
    {
        if ($this->disableValidation)
            return true;
        foreach ($params as $token => $value)
            if (!$this->performValidate(str_replace(':', '', $token), $params[$token]))
                return false;
        return true;
    }

    public function disableValidation($disable = true)
    {
        $this->disableValidation = $disable;
        return $this;
    }

    public function useRelation($use = true)
    {
        $this->useRelation = $use;
        return $this;
    }

    /**
     * delete, only when there is primary key
     */
    public function delete(array $criteria = array())
    {
        $query = $this->buildDelete($criteria);
        $result = $this->run($query, $criteria);
        $this->next();

        return $result;
    }

    /**
     * Get data
     */
    public function cast()
    {
        return $this->schema['values'];
    }

    /**
     * Get next row and Assign row to schema values
     */
    public function next()
    {
        if (!$this->hasError()) {
            $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
            $this->assign($row?:array());
        }
        return $this;
    }

    /**
     * Get count
     */
    public function count($force = false)
    {
        if ($this->stmt && !$force)
            return $this->stmt->rowCount();
        $this->select('count(*) as total', true);
        $query = $this->buildSelect($params);
        $this->run($query, $params);
        return $this->stmt->fetchColumn(0);
    }

    /**
     * Get resultset
     */
    public function get($limit = 0)
    {
        $this->limit($limit);
        $query = $this->buildSelect($params);
        $this->run($query, $params);
        return $this->next();
    }

    /**
     * Get all
     */
    public function all($obj = false, $limit = 0)
    {
        $this->limit($limit);
        $query = $this->buildSelect($params);
        return $this->run($query, $params)?$this->stmt->fetchAll($obj?PDO::FETCH_OBJ:PDO::FETCH_ASSOC):array();
    }

    /**
     * Paging
     */
    public function page($page = 1, $limit = 10)
    {
        $this->limit($limit)->offset($page*$limit-$limit);
        $query = $this->buildSelect($params);
        return array(
            'data'=>$this->run($query, $params)?$this->stmt->fetchAll(PDO::FETCH_ASSOC):array(),
            'total'=>($total = $this->limit(0)->offset(0)->count(true)),
            'totalPage'=>$limit>0?ceil($total/$limit):0,
            );
    }

    /**
     * Find
     */
    public function find($criteria, array $values = array())
    {
        return $this->select()->where($criteria, $values);
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
            $token = ':'.$field;
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
        $pk = array();
        foreach ($this->schema['pk'] as $field)
            if (isset($this->schema['values'][$field]))
                $pk[$field] = $this->schema['values'][$field];
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
        return !$that->get()->dry();
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
        $that->get();
        return ($that->dry() || $this->pkCompare($that->pkValue()));
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
     * Where
     */
    public function where($criteria, array $values = array(), $before = 'AND')
    {
        $this->select[__FUNCTION__] .= ($this->select[__FUNCTION__]?
            ' '.$before.' ':'').trim($criteria);
        $this->select['params'] = array_merge($this->select['params'], $values);
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
    public function having($criteria, array $values = array(), $before = 'AND')
    {
        $this->select[__FUNCTION__] .= ($this->select[__FUNCTION__]?
            ' '.$before.' ':'').trim($where);
        $this->select['params'] = array_merge($this->select['params'], $values);
        return $this;
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
     * Return fields schema
     * @return array fields schema
     */
    public function fields()
    {
        return $this->schema[__FUNCTION__];
    }

    /**
     * Check weather
     */
    public function dry()
    {
        return count(array_filter($this->schema['values']))==0;
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
     * Get validation object
     */
    public function validation()
    {
        return Validation::instance();
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
                break;
            default:
                $this->schema['values'] = $this->schema['init'];
                $this->schema['others'] = array();
                break;
        }
        return $this;
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
        $this->stmt = $this->db()->pdo->prepare($query);
        $this->stmt->execute($params);
        if ($this->stmt->errorCode()!='00000') {
            $this->errors[] = Instance::stringify($this->stmt->errorInfo());
            return false;
        }
        return true;
    }

    /**
     * insert Builder
     */
    protected function buildInsert(&$params, $update)
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
            $query .= ' on duplicate key update '.implode(',', $update);
        }
        $this->logs[] = $query;
        return $this->lastQuery();
    }

    /**
     * update Builder
     */
    protected function buildUpdate(&$params, array $criteria = array())
    {
        $query  = 'update '.$this->table().' set ';
        foreach ($params as $token=>$value) {
            $field = str_replace(':', '', $token);
            $query .= $field.'='.$token.',';
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
        if (!$where)
            throw new Exception(self::E_PrimaryKey, 1);
        $params = array_merge($params, $where_param);
        $query = rtrim($query, ',').' where '.implode(' and ', $where);
        $this->logs[] = $query;
        return $this->lastQuery();
    }

    /**
     * delete Builder
     */
    protected function buildDelete(array &$criteria = array())
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
            throw new Exception(self::E_PrimaryKey, 1);
        $criteria = $where_param;
        $query = 'delete from '.$this->table().' where '.implode(' and ', $where);
        $this->logs[] = $query;
        return $this->lastQuery();
    }

    /**
     * select Builder
     */
    protected function buildSelect(&$params = array())
    {
        $this->select['select']  || $this->select();
        if ($this->useRelation && $relation = $this->relation()) {
            $this->select['join'] = implode("\n", $relation);
        } else
            $this->select['join'] = '';
        $cp     = $this->select;
        $params = $cp['params'];
        unset($cp['params']);
        !$cp['where']  || $cp['where']  = ' where '    .trim($cp['where']);
        !$cp['group']  || $cp['group']  = ' group by ' .trim($cp['group']);
        !$cp['having'] || $cp['having'] = ' having '   .trim($cp['having']);
        !$cp['order']  || $cp['order']  = ' order by ' .trim($cp['order']);
        !$cp['limit']  || $cp['limit']  = ' limit '    .trim($cp['limit']);
        !$cp['offset'] || $cp['offset'] = ' offset '   .trim($cp['offset']);

        $cp['select']  = 'select '.trim($cp['select']);
        $cp['from']    = ' from '.$this->table();
        $this->logs[]  = implode("\n", array_filter($cp));
        return $this->lastQuery();
    }

    /**
     * Translate param
     */
    protected function translateParam($method, array $args)
    {
        $params = array();
        $columns = explode('_', Instance::snakecase(str_replace('_', '-', lcfirst($method))));
        for ($pointer = 0, $last = count($columns); $pointer < $last; $pointer++) {
            $column = str_replace('-', '_', $columns[$pointer]);
            if (!$args)
                throw new Exception(sprintf(self::E_Param, $column), 1);
            $params[$column] = array_shift($args);
        }
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
        $columns = explode('_', Instance::snakecase(str_replace('_', '-', lcfirst($method))));
        $n_token = 'not';
        $p_token = 'and or';
        $o_token = 'in between like begin end';
        for ($pointer = 0, $last = count($columns); $pointer < $last; $pointer++) {
            $column = str_replace('-', '_', $columns[$pointer]);
            if ($this->inToken($column, $n_token.' '.$p_token.' '.$o_token))
                continue;
            if (!$args)
                throw new Exception(sprintf(self::E_Param, $column), 1);
            $negation = (isset($columns[$pointer-1]) && $this->inToken($columns[$pointer-1], $n_token));
            $punc     = 'and';
            $operator = ($negation?'!':'').'=';
            $params   = array();
            $token    = ':'.$column;
            if (isset($columns[$pointer+1])) {
                // bisa jadi punc or opr
                $next = $columns[$pointer+1];
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
                }
            }
            if (isset($columns[$pointer+2]) && $this->inToken($columns[$pointer+2], $p_token)) {
                $punc = $columns[$pointer+2];
            }
            $params || $params[$token] = array_shift($args);
            $result['criteria'] .= ' '.$column.' '.$operator.' '.$token.' '.$punc;
            $result['params'] = array_merge($result['params'], $params);
        }
        $result['criteria'] = preg_replace('/ (and|or)$/i', '', $result['criteria']);
        return $result;
    }

    protected function inToken($what, $token)
    {
        return in_array($what, explode(' ', $token));
    }

    public function __isset($var)
    {
        return (isset($this->schema['values'][$var]) ||
                isset($this->schema['others'][$var]));
    }

    public function __set($var, $val)
    {
        $this->schema['others'][$var] = $val;
    }

    public function __get($var)
    {
        return isset($this->schema['values'][$var])?
                     $this->schema['values'][$var]:(
                isset($this->schema['others'][$var])?
                      $this->schema['others'][$var]:null);
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
        } else
            throw new Exception(self::E_Method, 1);
    }

    public function __construct()
    {
        foreach ($this->schema() as $key => $value) {
            is_array($value) || $value    = array($value);
            $field  = array_shift($value);
            $filter = array_shift($value);
                is_array($filter) || $filter = array($filter);
                $filter = array_filter($filter);
            $init   = array_shift($value);
            $this->schema['fields'][$key] = $field;
            $this->schema['filter'][$key] = $filter;
            $this->schema['init'][$key]   = $init;
            $this->schema['values'][$key] = null;
        }
        $pk = $this->primaryKey();
        $this->schema['pk'] = is_array($pk)?$pk:array($pk);
        if (!array_filter($this->schema['fields']))
            throw new Exception('There is no schema defined', 1);
        $this->disableValidation = count(array_filter($this->schema['filter']))==0;
    }
}
