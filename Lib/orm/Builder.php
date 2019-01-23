<?php

abstract class Lib_orm_Builder
{

    // 查询表达式映射
    protected $exp = ['EQ' => '=', 'NEQ' => '<>', 'GT' => '>', 'EGT' => '>=', 'LT' => '<', 'ELT' => '<=', 'NOTLIKE' => 'NOT LIKE', 'NOTIN' => 'NOT IN', 'NOTBETWEEN' => 'NOT BETWEEN', 'NOTEXISTS' => 'NOT EXISTS', 'NOTNULL' => 'NOT NULL', 'NOTBETWEEN TIME' => 'NOT BETWEEN TIME'];

    // 查询表达式解析
    protected $parser = [
        'parseCompare'     => ['=', '<>', '>', '>=', '<', '<='],
        'parseLike'        => ['LIKE', 'NOT LIKE'],
        'parseBetween'     => ['NOT BETWEEN', 'BETWEEN'],
        'parseIn'          => ['NOT IN', 'IN'],
        'parseExp'         => ['EXP'],
        'parseNull'        => ['NOT NULL', 'NULL'],
        'parseBetweenTime' => ['BETWEEN TIME', 'NOT BETWEEN TIME'],
        'parseTime'        => ['< TIME', '> TIME', '<= TIME', '>= TIME'],
        'parseExists'      => ['NOT EXISTS', 'EXISTS'],
        'parseColumn'      => ['COLUMN'],
    ];

    // SQL表达式
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%IGNORE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT%%LOCK%%COMMENT%';

    protected $insertSql = '%INSERT% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';

    protected $updateSql = 'UPDATE %TABLE% SET %SET% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    protected $deleteSql = 'DELETE FROM %TABLE% %USING% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {

    }


    /**
     * 注册查询表达式解析
     * @access public
     * @param string    $name   解析方法
     * @param array     $parser 匹配表达式数据
     * @return $this
     */
    public function bindParser($name, $parser)
    {
        $this->parser[$name] = $parser;
        return $this;
    }

    /**
     * 数据分析
     * @access protected
     * @param  Query     $query     查询对象
     * @param  array     $data      数据
     * @param  array     $fields    字段信息
     * @param  array     $bind      参数绑定
     * @return array
     */
    protected function parseData(Lib_orm_Query $query, $data = [], $fields = [], $bind = [])
    {
        if (empty($data)) {
            return [];
        }

        $options = $query->getOptions();

        if (empty($fields)) {
            if ('*' == $options['field']) {
                $fields = array_keys($data);
            } else {
                $fields = $options['field'];
            }
        }

        $result = [];

        foreach ($data as $key => $val) {
            if ('*' != $options['field'] && !in_array($key, $fields, true)) {
                continue;
            }

            $item = $this->parseKey($query, $key, true);

            if ($val instanceof Lib_orm_Expression) {
                $result[$item] = $val->getValue();
                continue;
            } elseif (is_object($val) && method_exists($val, '__toString')) {
                // 对象数据写入
                $val = $val->__toString();
            }

            if (false !== strpos($key, '->')) {
                list($key, $name) = explode('->', $key);
                $item             = $this->parseKey($query, $key);
                $result[$item]    = 'json_set(' . $item . ', \'$.' . $name . '\', ' . $this->parseDataBind($query, $key, $val, $bind) . ')';
            } elseif (false === strpos($key, '.') && !in_array($key, $fields, true)) {
                if ($options['strict']) {
                    throw new Exception('fields not exists:[' . $key . ']');
                }
            } elseif (is_null($val)) {
                $result[$item] = 'NULL';
            } elseif (is_array($val) && !empty($val)) {
                switch (strtoupper($val[0])) {
                    case 'INC':
                        $result[$item] = $item . ' + ' . floatval($val[1]);
                        break;
                    case 'DEC':
                        $result[$item] = $item . ' - ' . floatval($val[1]);
                        break;
                    case 'EXP':
                        throw new Exception('not support data:[' . $val[0] . ']');
                }
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                $result[$item] = $this->parseDataBind($query, $key, $val, $bind);
            }
        }
        return $result;
    }

    /**
     * 数据绑定处理
     * @access protected
     * @param  Query     $query     查询对象
     * @param  string    $key       字段名
     * @param  mixed     $data      数据
     * @param  array     $bind      绑定数据
     * @return string
     */
    protected function parseDataBind(Lib_orm_Query $query, $key, $data, $bind = [])
    {
        if ($data instanceof Lib_orm_Expression) {
            return $data->getValue();
        }

        $name = $query->bind($data);

        return ':' . $name;
    }

    /**
     * 字段名分析
     * @access public
     * @param  Query  $query    查询对象
     * @param  mixed  $key      字段名
     * @param  bool   $strict   严格检测
     * @return string
     */
    public function parseKey(Lib_orm_Query $query, $key, $strict = false)
    {
        return $key;
    }

    /**
     * field分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $fields
     * @return string
     */
    protected function parseField(Lib_orm_Query $query, $fields)
    {
        if ('*' == $fields || empty($fields)) {
            $fieldsStr = '*';
        } elseif (is_array($fields)) {
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];

            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = $this->parseKey($query, $key) . ' AS ' . $this->parseKey($query, $field, true);
                } else {
                    $array[] = $this->parseKey($query, $field);
                }
            }

            $fieldsStr = implode(',', $array);
        }

        return $fieldsStr;
    }

    /**
     * table分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $tables
     * @return string
     */
    protected function parseTable(Lib_orm_Query $query, $tables)
    {
        $item    = [];
        $options = $query->getOptions();

        foreach ((array) $tables as $key => $table) {
            if (!is_numeric($key)) {
                $item[] = $key . ' ' . $table;
            } else {
                if (isset($options['alias'][$table])) {
                    $item[] = $table . ' ' . $options['alias'][$table];
                } else {
                    $item[] = $table;
                }
            }
        }

        return implode(',', $item);
    }

    /**
     * where分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $where   查询条件
     * @return string
     */
    protected function parseWhere(Lib_orm_Query $query, $where)
    {
        $options  = $query->getOptions();
        $whereStr = $this->buildWhere($query, $where);

        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param Query     $query        查询对象
     * @param mixed     $where
     * @param array     $options
     * @return string
     */
    public function buildWhere(Lib_orm_Query $query, $where)
    {
        if (empty($where)) {
            $where = [];
        }
        $whereStr = '';


        foreach ($where as $logic => $val) {
            $str = [];
            foreach ($val as $value) {
                if ($value instanceof Lib_orm_Expression) {
                    $str[] = ' ' . $logic . ' ( ' . $value->getValue() . ' )';
                    continue;
                }
                if (is_array($value)) {
                    if (key($value) !== 0) {
                        throw new Exception('where express error:' . var_export($value, true));
                    }
                    $field = array_shift($value);
                } elseif (!($value instanceof \Closure)) {
                    throw new Exception('where express error:' . var_export($value, true));
                }

                if ($value instanceof \Closure) {
                    // 使用闭包查询
                    $newQuery = $query->newQuery();
                    $value($newQuery);
                    $whereClause = $this->buildWhere($query, $newQuery->getOptions('where'));

                    if (!empty($whereClause)) {
                        $str[] = ' ' . $logic . ' ( ' . $whereClause . ' )';
                    }
                } elseif (is_array($field)) {
                    array_unshift($value, $field);
                    $str2 = [];
                    foreach ($value as $item) {
                        $str2[] = $this->parseWhereItem($query, array_shift($item), $item, $logic);
                    }

                    $str[] = ' ' . $logic . ' ( ' . implode(' AND ', $str2) . ' )';
                } elseif (strpos($field, '|')) {
                    // 不同字段使用相同查询条件（OR）
                    $array = explode('|', $field);
                    $item  = [];

                    foreach ($array as $k) {
                        $item[] = $this->parseWhereItem($query, $k, $value, '');
                    }

                    $str[] = ' ' . $logic . ' ( ' . implode(' OR ', $item) . ' )';
                } elseif (strpos($field, '&')) {
                    // 不同字段使用相同查询条件（AND）
                    $array = explode('&', $field);
                    $item  = [];

                    foreach ($array as $k) {
                        $item[] = $this->parseWhereItem($query, $k, $value, '');
                    }

                    $str[] = ' ' . $logic . ' ( ' . implode(' AND ', $item) . ' )';
                } else {
                    // 对字段使用表达式查询
                    $field = is_string($field) ? $field : '';
                    $str[] = ' ' . $logic . ' ' . $this->parseWhereItem($query, $field, $value, $logic);
                }
            }

            $whereStr .= empty($whereStr) ? substr(implode(' ', $str), strlen($logic) + 1) : implode(' ', $str);
        }
        return $whereStr;
    }

    // where子单元分析
    protected function parseWhereItem(Lib_orm_Query $query, $field, $val, $rule = '')
    {
        // 字段分析
        $key = $field ? $this->parseKey($query, $field, true) : '';

        // 查询规则和条件
        if (!is_array($val)) {
            $val = is_null($val) ? ['NULL', ''] : ['=', $val];
        }

        list($exp, $value) = $val;

        // 对一个字段使用多个查询条件
        if (is_array($exp)) {
            $item = array_pop($val);

            // 传入 or 或者 and
            if (is_string($item) && in_array($item, ['AND', 'and', 'OR', 'or'])) {
                $rule = $item;
            } else {
                array_push($val, $item);
            }

            foreach ($val as $k => $item) {
                $str[] = $this->parseWhereItem($query, $field, $item, $rule);
            }

            return '( ' . implode(' ' . $rule . ' ', $str) . ' )';
        }

        // 检测操作符
        $exp = strtoupper($exp);
        if (isset($this->exp[$exp])) {
            $exp = $this->exp[$exp];
        }

        if ($value instanceof Lib_orm_Expression) {

        } elseif (is_object($value) && method_exists($value, '__toString')) {
            // 对象数据写入
            $value = $value->__toString();
        }

        if (strpos($field, '->')) {
            $jsonType = $query->getJsonFieldType($field);
        } else {
        }

        if (is_scalar($value) && !in_array($exp, ['EXP', 'NOT NULL', 'NULL', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN']) && strpos($exp, 'TIME') === false) {
            $name  = $query->bind($value);
            $value = ':' . $name;
        }

        // 解析查询表达式
        foreach ($this->parser as $fun => $parse) {
            if (in_array($exp, $parse)) {
                $whereStr = $this->$fun($query, $key, $exp, $value, $field, isset($val[2]) ? $val[2] : 'AND');
                break;
            }
        }

        if (!isset($whereStr)) {
            throw new Exception('where express error:' . $exp);
        }

        return $whereStr;
    }

    /**
     * 模糊查询
     * @access protected
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  string    $exp
     * @param  mixed     $value
     * @param  string    $field
     * @param  integer   $bindType
     * @param  string    $logic
     * @return string
     */
    protected function parseLike(Lib_orm_Query $query, $key, $exp, $value, $field, $logic)
    {
        // 模糊匹配
        if (is_array($value)) {
            foreach ($value as $item) {
                $name    = $query->bind($item);
                $array[] = $key . ' ' . $exp . ' :' . $name;
            }

            $whereStr = '(' . implode($array, ' ' . strtoupper($logic) . ' ') . ')';
        } else {
            $whereStr = $key . ' ' . $exp . ' ' . $value;
        }

        return $whereStr;
    }

    /**
     * 表达式查询
     * @access protected
     * @param  Query        $query        查询对象
     * @param  string       $key
     * @param  string       $exp
     * @param  Lib_orm_Expression   $value
     * @param  string       $field
     * @param  integer      $bindType
     * @return string
     */
    protected function parseExp(Lib_orm_Query $query, $key, $exp, Lib_orm_Expression $value, $field)
    {
        // 表达式查询
        return '( ' . $key . ' ' . $value->getValue() . ' )';
    }

    /**
     * 表达式查询
     * @access protected
     * @param  Query        $query        查询对象
     * @param  string       $key
     * @param  string       $exp
     * @param  array        $value
     * @param  string       $field
     * @param  integer      $bindType
     * @return string
     */
    protected function parseColumn(Lib_orm_Query $query, $key, $exp, array $value, $field)
    {
        // 字段比较查询
        list($op, $field2) = $value;

        if (!in_array($op, ['=', '<>', '>', '>=', '<', '<='])) {
            throw new Exception('where express error:' . var_export($value, true));
        }

        return '( ' . $key . ' ' . $op . ' ' . $this->parseKey($query, $field2, true) . ' )';
    }

    /**
     * Null查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseNull(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        // NULL 查询
        return $key . ' IS ' . $exp;
    }

    /**
     * 范围查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseBetween(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        // BETWEEN 查询
        $data = is_array($value) ? $value : explode(',', $value);

        $min = $query->bind($data[0]);
        $max = $query->bind($data[1]);

        return $key . ' ' . $exp . ' :' . $min . ' AND :' . $max . ' ';
    }

    /**
     * Exists查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseExists(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        // EXISTS 查询
        if ($value instanceof \Closure) {
            $value = $this->parseClosure($query, $value, false);
        } elseif ($value instanceof Lib_orm_Expression) {
            $value = $value->getValue();
        } else {
            throw new Exception('where express error:' . $value);
        }

        return $exp . ' (' . $value . ')';
    }

    /**
     * 时间比较查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseTime(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        return $key . ' ' . substr($exp, 0, 2) . ' ' . $this->parseDateTime($query, $value, $field);
    }

    /**
     * 大小比较查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseCompare(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        if (is_array($value)) {
            throw new Exception('where express error:' . $exp . var_export($value, true));
        }

        // 比较运算
        if ($value instanceof \Closure) {
            $value = $this->parseClosure($query, $value);
        }

        return $key . ' ' . $exp . ' ' . $value;
    }

    /**
     * 时间范围查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseBetweenTime(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return $key . ' ' . substr($exp, 0, -4)
        . $this->parseDateTime($query, $value[0], $field)
        . ' AND '
        . $this->parseDateTime($query, $value[1], $field);

    }

    /**
     * IN查询
     * @access protected
     * @param Query     $query        查询对象
     * @param string    $key
     * @param string    $exp
     * @param mixed     $value
     * @param string    $field
     * @param integer   $bindType
     * @return string
     */
    protected function parseIn(Lib_orm_Query $query, $key, $exp, $value, $field)
    {
        // IN 查询
        if ($value instanceof \Closure) {
            $value = $this->parseClosure($query, $value, false);
        } else {
            $value = array_unique(is_array($value) ? $value : explode(',', $value));

            $array = [];

            foreach ($value as $k => $v) {
                $name    = $query->bind($v);
                $array[] = ':' . $name;
            }

            $zone = implode(',', $array);

            $value = empty($zone) ? "''" : $zone;
        }

        return $key . ' ' . $exp . ' (' . $value . ')';
    }

    /**
     * 闭包子查询
     * @access protected
     * @param Query     $query        查询对象
     * @param \Closure  $call
     * @param bool      $show
     * @return string
     */
    protected function parseClosure(Lib_orm_Query $query, $call, $show = true)
    {
        $newQuery = $query->newQuery();
        $call($newQuery);

        return $newQuery->buildSql($show);
    }


    /**
     * limit分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $limit
     * @return string
     */
    protected function parseLimit(Lib_orm_Query $query, $limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * join分析
     * @access protected
     * @param Query     $query        查询对象
     * @param array     $join
     * @return string
     */
    protected function parseJoin(Lib_orm_Query $query, $join)
    {
        $joinStr = '';

        if (!empty($join)) {
            foreach ($join as $item) {
                list($table, $type, $on) = $item;

                $condition = [];

                foreach ((array) $on as $val) {
                    if ($val instanceof Lib_orm_Expression) {
                        $condition[] = $val->getValue();
                    } elseif (strpos($val, '=')) {
                        list($val1, $val2) = explode('=', $val, 2);
                        $condition[]       = $this->parseKey($query, $val1) . '=' . $this->parseKey($query, $val2);
                    } else {
                        $condition[] = $val;
                    }
                }

                $table = $this->parseTable($query, $table);

                $joinStr .= ' ' . $type . ' JOIN ' . $table . ' ON ' . implode(' AND ', $condition);
            }
        }

        return $joinStr;
    }

    /**
     * order分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $order
     * @return string
     */
    protected function parseOrder(Lib_orm_Query $query, $order)
    {
        foreach ($order as $key => $val) {
            if ($val instanceof Lib_orm_Expression) {
                $array[] = $val->getValue();
            } elseif (is_array($val) && preg_match('/^[\w\.]+$/', $key)) {
                $array[] = $this->parseOrderField($query, $key, $val);
            } elseif ('[rand]' == $val) {
                $array[] = $this->parseRand($query);
            } elseif (is_string($val)) {
                if (is_numeric($key)) {
                    list($key, $sort) = explode(' ', strpos($val, ' ') ? $val : $val . ' ');
                } else {
                    $sort = $val;
                }

                if (preg_match('/^[\w\.]+$/', $key)) {
                    $sort    = strtoupper($sort);
                    $sort    = in_array($sort, ['ASC', 'DESC'], true) ? ' ' . $sort : '';
                    $array[] = $this->parseKey($query, $key, true) . $sort;
                } else {
                    throw new Exception('order express error:' . $key);
                }
            }
        }

        return empty($array) ? '' : ' ORDER BY ' . implode(',', $array);
    }

    /**
     * orderField分析
     * @access protected
     * @param  Query     $query        查询对象
     * @param  mixed     $key
     * @param  array     $val
     * @return string
     */
    protected function parseOrderField($query, $key, $val)
    {
        if (isset($val['sort'])) {
            $sort = $val['sort'];
            unset($val['sort']);
        } else {
            $sort = '';
        }

        $sort = strtoupper($sort);
        $sort = in_array($sort, ['ASC', 'DESC'], true) ? ' ' . $sort : '';

        $options = $query->getOptions();

        foreach ($val as $k => $item) {
            $val[$k] = $this->parseDataBind($query, $key, $item, []);
        }

        return 'field(' . $this->parseKey($query, $key, true) . ',' . implode(',', $val) . ')' . $sort;
    }

    /**
     * group分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $group
     * @return string
     */
    protected function parseGroup(Lib_orm_Query $query, $group)
    {
        return !empty($group) ? ' GROUP BY ' . $this->parseKey($query, $group) : '';
    }

    /**
     * having分析
     * @access protected
     * @param Query  $query        查询对象
     * @param string $having
     * @return string
     */
    protected function parseHaving(Lib_orm_Query $query, $having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * comment分析
     * @access protected
     * @param  Query  $query        查询对象
     * @param  string $comment
     * @return string
     */
    protected function parseComment(Lib_orm_Query $query, $comment)
    {
        if (false !== strpos($comment, '*/')) {
            $comment = strstr($comment, '*/', true);
        }

        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * distinct分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $distinct
     * @return string
     */
    protected function parseDistinct(Lib_orm_Query $query, $distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }

    /**
     * union分析
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $union
     * @return string
     */
    protected function parseUnion(Lib_orm_Query $query, $union)
    {
        if (empty($union)) {
            return '';
        }

        $type = $union['type'];
        unset($union['type']);

        foreach ($union as $u) {
            if ($u instanceof \Closure) {
                $sql[] = $type . ' ' . $this->parseClosure($query, $u);
            } elseif (is_string($u)) {
                $sql[] = $type . ' ( ' . $u . ' )';
            }
        }

        return ' ' . implode(' ', $sql);
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $index
     * @return string
     */
    protected function parseForce(Lib_orm_Query $query, $index)
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            $index = join(",", $index);
        }

        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }

    /**
     * index分析，可在操作链中指定需要忽略使用的索引
     * @access protected
     * @param Query     $query        查询对象
     * @param mixed     $index
     * @return string
     */
    protected function parseIgnore(Lib_orm_Query $query, $index)
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            $index = join(",", $index);
        }

        return sprintf(" IGNORE INDEX ( %s ) ", $index);
    }


    /**
     * 设置锁机制
     * @access protected
     * @param Query         $query        查询对象
     * @param bool|string   $lock
     * @return string
     */
    protected function parseLock(Lib_orm_Query $query, $lock = false)
    {
        if (is_bool($lock)) {
            return $lock ? ' FOR UPDATE ' : '';
        } elseif (is_string($lock) && !empty($lock)) {
            return ' ' . trim($lock) . ' ';
        }
    }

    /**
     * 生成查询SQL
     * @access public
     * @param Query  $query  查询对象
     * @return string
     */
    public function select(Lib_orm_Query $query)
    {
        $options = $query->getOptions();

        return str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%', '%IGNORE%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseDistinct($query, $options['distinct']),
                $this->parseField($query, $options['field']),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseGroup($query, $options['group']),
                $this->parseHaving($query, $options['having']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseUnion($query, $options['union']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
                $this->parseForce($query, $options['force']),
                $this->parseIgnore($query, $options['ignore']),
            ],
            $this->selectSql);
    }

    /**
     * 生成Insert SQL
     * @access public
     * @param Query     $query   查询对象
     * @param bool      $replace 是否replace
     * @return string
     */
    public function insert(Lib_orm_Query $query, $replace = false)
    {
        $options = $query->getOptions();

        // 分析并处理数据
        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return '';
        }

        $fields = array_keys($data);
        $values = array_values($data);

        return str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertSql);
    }

    /**
     * 生成slect insert SQL
     * @access public
     * @param Query     $query  查询对象
     * @param array     $fields 数据
     * @param string    $table  数据表
     * @return string
     */
    public function selectInsert(Lib_orm_Query $query, $fields, $table)
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        foreach ($fields as &$field) {
            $field = $this->parseKey($query, $field, true);
        }

        return 'INSERT INTO ' . $this->parseTable($query, $table) . ' (' . implode(',', $fields) . ') ' . $this->select($query);
    }

    /**
     * 生成update SQL
     * @access public
     * @param Query     $query  查询对象
     * @return string
     */
    public function update(Lib_orm_Query $query)
    {
        $options = $query->getOptions();

        $table = $this->parseTable($query, $options['table']);
        $data  = $this->parseData($query, $options['data']);

        if (empty($data)) {
            return '';
        }

        foreach ($data as $key => $val) {
            $set[] = $key . ' = ' . $val;
        }

        return str_replace(
            ['%TABLE%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                implode(' , ', $set),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->updateSql);
    }

    /**
     * 生成delete SQL
     * @access public
     * @param Query  $query  查询对象
     * @return string
     */
    public function delete(Lib_orm_Query $query)
    {
        $options = $query->getOptions();

        return str_replace(
            ['%TABLE%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                !empty($options['using']) ? ' USING ' . $this->parseTable($query, $options['using']) . ' ' : '',
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->deleteSql);
    }



    /**
     * 根据参数绑定组装最终的SQL语句 便于调试
     * @access public
     * @param string    $sql 带参数绑定的sql语句
     * @param array     $bind 参数绑定列表
     * @return string
     */
    public function getRealSql($sql, array $bind = [])
    {
        if (is_array($sql)) {
            $sql = implode(';', $sql);
        }

        foreach ($bind as $key => $val) {
            $value = is_array($val) ? $val[0] : $val;

            if(is_numeric($value)){
                if(!is_int($val)){
                    $value = (float) $value;
                }
            }else{
                $value = '\'' . addslashes($value) . '\'';
            }
            // 判断占位符
            $sql = is_numeric($key) ?
                substr_replace($sql, $value, strpos($sql, '?'), 1) :
                str_replace(':' . $key, $value, $sql);
        }

        return rtrim($sql);
    }

}
