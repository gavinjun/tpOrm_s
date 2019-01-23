<?php
class Lib_orm_Query
{
    // 当前数据表名称（不含前缀）
    protected $name = '';
    // 查询参数
    protected $options = [];
    // 参数绑定
    protected $bind = [];
    // 使用Builder类
    protected $builder;

    // 日期查询快捷方式
    protected $timeExp = ['d' => 'today', 'w' => 'week', 'm' => 'month', 'y' => 'year'];
    // 日期查询表达式
    protected $timeRule = [
        'today'      => ['today', 'tomorrow'],
        'yesterday'  => ['yesterday', 'today'],
        'week'       => ['this week 00:00:00', 'next week 00:00:00'],
        'last week'  => ['last week 00:00:00', 'this week 00:00:00'],
        'month'      => ['first Day of this month 00:00:00', 'first Day of next month 00:00:00'],
        'last month' => ['first Day of last month 00:00:00', 'first Day of this month 00:00:00'],
        'year'       => ['this year 1/1', 'next year 1/1'],
        'last year'  => ['last year 1/1', 'this year 1/1'],
    ];

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        if (!$this->builder) {
            $class = 'Lib_orm_builder_Mysql';
            $this->builder = new $class;
        }
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @return Query
     */
    public function newQuery()
    {
        return new static();
    }



    /**
     * 指定当前数据表名（不含前缀）
     * @access public
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 得到当前或者指定名称的数据表
     * @access public
     * @param string $name
     * @return string
     */
    public function getTable($name = '')
    {
        if (empty($name) && isset($this->options['table'])) {
            return $this->options['table'];
        }

        $name = $name ?: $this->name;

        return Lib_orm_Db::parseName($name);
    }


    /**
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     * @access public
     * @param string|array $field 字段名
     * @param mixed        $value 字段值
     * @return integer
     */
    public function setField($field, $value = '')
    {
        $condition = !empty($this->options['where']) ? $this->options['where'] : [];

        if (empty($condition)) {
            // 没有条件不做任何更新
            throw new Exception('no data to update');
        }

        if (is_array($field)) {
            $data = $field;
        } else {
            $data[$field] = $value;
        }

        return $this->update($data);
    }

    /**
     * 字段值(延迟)增长
     * @access public
     * @param string  $field    字段名
     * @param integer $step     增长值
     * @return integer|true
     * @throws Exception
     */
    public function setInc($field, $step = 1)
    {
        return $this->setField($field, ['inc', $step]);
    }

    /**
     * 字段值（延迟）减少
     * @access public
     * @param string  $field    字段名
     * @param integer $step     减少值
     * @return integer|true
     * @throws Exception
     */
    public function setDec($field, $step = 1)
    {
        return $this->setField($field, ['dec', $step]);
    }

    /**
     * 查询SQL组装 join
     * @access public
     * @param mixed  $join      关联的表名
     * @param mixed  $condition 条件
     * @param string $type      JOIN类型
     * @return $this
     */
    public function join($join, $condition = null, $type = 'INNER')
    {
        if (empty($condition)) {
            // 如果为组数，则循环调用join
            foreach ($join as $key => $value) {
                if (is_array($value) && 2 <= count($value)) {
                    $this->join($value[0], $value[1], isset($value[2]) ? $value[2] : $type);
                }
            }
        } else {
            $table = $this->getJoinTable($join);

            $this->options['join'][] = [$table, strtoupper($type), $condition];
        }

        return $this;
    }

    /**
     * LEFT JOIN
     * @access public
     * @param mixed  $join      关联的表名
     * @param mixed  $condition 条件
     * @return $this
     */
    public function leftJoin($join, $condition = null)
    {
        return $this->join($join, $condition, 'LEFT');
    }

    /**
     * RIGHT JOIN
     * @access public
     * @param mixed  $join      关联的表名
     * @param mixed  $condition 条件
     * @return $this
     */
    public function rightJoin($join, $condition = null)
    {
        return $this->join($join, $condition, 'RIGHT');
    }

    /**
     * FULL JOIN
     * @access public
     * @param mixed  $join      关联的表名
     * @param mixed  $condition 条件
     * @return $this
     */
    public function fullJoin($join, $condition = null)
    {
        return $this->join($join, $condition, 'FULL');
    }

    /**
     * 获取Join表名及别名 支持
     * ['prefix_table或者子查询'=>'alias'] 'prefix_table alias' 'table alias'
     * @access public
     * @param array|string $join
     * @return array|string
     */
    protected function getJoinTable($join, &$alias = null)
    {
        // 传入的表名为数组
        if (is_array($join)) {
            $table = $join;
        } else {
            $join = trim($join);

            if (false !== strpos($join, '(')) {
                // 使用子查询
                $table = $join;
            } else {
                if (strpos($join, ' ')) {
                    // 使用别名
                    list($table, $alias) = explode(' ', $join);
                } else {
                    $table = $join;
                    if (false === strpos($join, '.') && 0 !== strpos($join, '__')) {
                        $alias = $join;
                    }
                }

                if (false === strpos($table, '.')  && 0 !== strpos($table, '__')) {
                    $table = $this->getTable($table);
                }
            }

            if (isset($alias) && $table != $alias) {
                $table = [$table => $alias];
            }
        }

        return $table;
    }

    /**
     * 查询SQL组装 union
     * @access public
     * @param mixed   $union
     * @param boolean $all
     * @return $this
     */
    public function union($union, $all = false)
    {
        $this->options['union']['type'] = $all ? 'UNION ALL' : 'UNION';

        if (is_array($union)) {
            $this->options['union'] = array_merge($this->options['union'], $union);
        } else {
            $this->options['union'][] = $union;
        }

        return $this;
    }

    /**
     * 查询SQL组装 union all
     * @access public
     * @param  mixed   $union
     * @return $this
     */
    public function unionAll($union)
    {
        return $this->union($union, true);
    }

    /**
     * 指定查询字段 支持字段排除和指定数据表
     * @access public
     * @param mixed   $field
     * @param boolean $except    是否排除
     * @param string  $tableName 数据表名
     * @param string  $prefix    字段前缀
     * @param string  $alias     别名前缀
     * @return $this
     */
    public function field($field, $except = false, $tableName = '', $prefix = '', $alias = '')
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            if (preg_match('/[\<\'\"\(]/', $field)) {
                return $this->fieldRaw($field);
            }
            $field = array_map('trim', explode(',', $field));
        }

        if (true === $field) {
            // 获取全部字段
            $fields = $this->getTableFields($tableName);
            $field  = $fields ?: ['*'];
        } elseif ($except) {
            // 字段排除
            $fields = $this->getTableFields($tableName);
            $field  = $fields ? array_diff($fields, $field) : $field;
        }

        if ($tableName) {
            // 添加统一的前缀
            $prefix = $prefix ?: $tableName;
            foreach ($field as $key => &$val) {
                if (is_numeric($key) && $alias) {
                    $field[$prefix . '.' . $val] = $alias . $val;
                    unset($field[$key]);
                } elseif (is_numeric($key)) {
                    $val = $prefix . '.' . $val;
                }
            }
        }

        if (isset($this->options['field'])) {
            $field = array_merge((array) $this->options['field'], $field);
        }

        $this->options['field'] = array_unique($field);

        return $this;
    }

    /**
     * 表达式方式指定查询字段
     * @access public
     * @param  string $field    字段名
     * @return $this
     */
    public function fieldRaw($field)
    {
        $this->options['field'][] = $this->raw($field);

        return $this;
    }

    /**
     * 设置数据
     * @access public
     * @param mixed $field 字段名或者数据
     * @param mixed $value 字段值
     * @return $this
     */
    public function data($field, $value = null)
    {
        if (is_array($field)) {
            $this->options['data'] = isset($this->options['data']) ? array_merge($this->options['data'], $field) : $field;
        } else {
            $this->options['data'][$field] = $value;
        }

        return $this;
    }

    /**
     * 字段值增长
     * @access public
     * @param string|array $field 字段名
     * @param integer      $step  增长值
     * @return $this
     */
    public function inc($field, $step = 1, $op = 'INC')
    {
        $fields = is_string($field) ? explode(',', $field) : $field;

        foreach ($fields as $field => $val) {
            if (is_numeric($field)) {
                $field = $val;
            } else {
                $step = $val;
            }

            $this->data($field, [$op, $step]);
        }

        return $this;
    }

    /**
     * 字段值减少
     * @access public
     * @param string|array $field 字段名
     * @param integer      $step  增长值
     * @return $this
     */
    public function dec($field, $step = 1)
    {
        return $this->inc($field, $step, 'DEC');
    }

    /**
     * 使用表达式设置数据
     * @access public
     * @param string $field 字段名
     * @param string $value 字段值
     * @return $this
     */
    public function exp($field, $value)
    {
        $this->data($field, $this->raw($value));
        return $this;
    }

    /**
     * 使用表达式设置数据
     * @access public
     * @param  mixed $value 表达式
     * @return Lib_orm_Expression
     */
    public function raw($value)
    {
        return new Lib_orm_Expression($value);
    }

    /**
     * 指定JOIN查询字段
     * @access public
     * @param string|array $table 数据表
     * @param string|array $field 查询字段
     * @param string|array $on    JOIN条件
     * @param string       $type  JOIN类型
     * @return $this
     */
    public function view($join, $field = true, $on = null, $type = 'INNER')
    {
        $this->options['view'] = true;

        if (is_array($join) && key($join) !== 0) {
            foreach ($join as $key => $val) {
                $this->view($key, $val[0], isset($val[1]) ? $val[1] : null, isset($val[2]) ? $val[2] : 'INNER');
            }
        } else {
            $fields = [];
            $table  = $this->getJoinTable($join, $alias);

            if (true === $field) {
                $fields = $alias . '.*';
            } else {
                if (is_string($field)) {
                    $field = explode(',', $field);
                }
                foreach ($field as $key => $val) {
                    if (is_numeric($key)) {
                        $fields[]                   = $alias . '.' . $val;
                        $this->options['map'][$val] = $alias . '.' . $val;
                    } else {
                        if (preg_match('/[,=\.\'\"\(\s]/', $key)) {
                            $name = $key;
                        } else {
                            $name = $alias . '.' . $key;
                        }
                        $fields[]                   = $name . ' AS ' . $val;
                        $this->options['map'][$val] = $name;
                    }
                }
            }

            $this->field($fields);

            if ($on) {
                $this->join($table, $on, $type);
            } else {
                $this->table($table);
            }
        }

        return $this;
    }

    /**
     * 设置分表规则
     * @access public
     * @param array  $data  操作的数据
     * @param string $field 分表依据的字段
     * @param array  $rule  分表规则
     * @return $this
     */
    public function partition($data, $field, $rule = [])
    {
        $this->options['table'] = $this->getPartitionTableName($data, $field, $rule);

        return $this;
    }

    /**
     * 指定AND查询条件
     * @access public
     * @param mixed $field     查询字段
     * @param mixed $op        查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('AND', $field, $op, $condition, $param);
    }

    /**
     * 指定表达式查询条件
     * @access public
     * @param  string $where  查询条件
     * @param  array  $bind   参数绑定
     * @param  string $logic  查询逻辑 and or xor
     * @return $this
     */
    public function whereRaw($where, $bind = [], $logic = 'AND')
    {
        if ($bind) {
            $this->bindParams($where, $bind);
        }

        $this->options['where'][$logic][] = $this->raw($where);

        return $this;
    }

    /**
     * 指定表达式查询条件 OR
     * @access public
     * @param  string $where  查询条件
     * @param  array  $bind   参数绑定
     * @return $this
     */
    public function whereOrRaw($where, $bind = [])
    {
        return $this->whereRaw($where, $bind, 'OR');
    }

    /**
     * 指定OR查询条件
     * @access public
     * @param mixed $field     查询字段
     * @param mixed $op        查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('OR', $field, $op, $condition, $param);
    }

    /**
     * 指定XOR查询条件
     * @access public
     * @param mixed $field     查询字段
     * @param mixed $op        查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function whereXor($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);
        return $this->parseWhereExp('XOR', $field, $op, $condition, $param);
    }

    /**
     * 指定Null查询条件
     * @access public
     * @param mixed  $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNull($field, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NULL', null, [], true);
    }

    /**
     * 指定NotNull查询条件
     * @access public
     * @param mixed  $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNotNull($field, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOTNULL', null, [], true);
    }

    /**
     * 指定Exists查询条件
     * @access public
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereExists($condition, $logic = 'AND')
    {
        if (is_string($condition)) {
            $condition = $this->raw($condition);
        }

        $this->options['where'][strtoupper($logic)][] = ['', 'EXISTS', $condition];
        return $this;
    }

    /**
     * 指定NotExists查询条件
     * @access public
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotExists($condition, $logic = 'AND')
    {
        if (is_string($condition)) {
            $condition = $this->raw($condition);
        }

        $this->options['where'][strtoupper($logic)][] = ['', 'NOT EXISTS', $condition];
        return $this;
    }

    /**
     * 指定In查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereIn($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'IN', $condition, [], true);
    }

    /**
     * 指定NotIn查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotIn($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT IN', $condition, [], true);
    }

    /**
     * 指定Like查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereLike($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'LIKE', $condition, [], true);
    }

    /**
     * 指定NotLike查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotLike($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT LIKE', $condition, [], true);
    }

    /**
     * 指定Between查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereBetween($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'BETWEEN', $condition, [], true);
    }

    /**
     * 指定NotBetween查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param mixed  $condition 查询条件
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereNotBetween($field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT BETWEEN', $condition, [], true);
    }

    /**
     * 比较两个字段
     * @access public
     * @param string|array  $field1     查询字段
     * @param string        $operator   比较操作符
     * @param string        $field2     比较字段
     * @param string        $logic      查询逻辑 and or xor
     * @return $this
     */
    public function whereColumn($field1, $operator, $field2 = null, $logic = 'AND')
    {
        if (is_array($field1)) {
            foreach ($field1 as $item) {
                $this->whereColumn($item[0], $item[1], isset($item[2]) ? $item[2] : null);
            }
            return $this;
        }

        if (is_null($field2)) {
            $field2   = $operator;
            $operator = '=';
        }

        return $this->parseWhereExp($logic, $field1, 'COLUMN', [$operator, $field2], [], true);
    }

    /**
     * 设置软删除字段及条件
     * @access public
     * @param false|string  $field     查询字段
     * @param mixed         $condition 查询条件
     * @return $this
     */
    public function useSoftDelete($field, $condition = null)
    {
        if ($field) {
            $this->options['soft_delete'] = [$field, $condition];
        }

        return $this;
    }

    /**
     * 指定Exp查询条件
     * @access public
     * @param mixed  $field     查询字段
     * @param string $where     查询条件
     * @param array  $bind      参数绑定
     * @param string $logic     查询逻辑 and or xor
     * @return $this
     */
    public function whereExp($field, $where, $logic = 'AND')
    {

        $this->options['where'][$logic][] = [$field, 'EXP', $this->raw($where)];

        return $this;
    }

    /**
     * 分析查询表达式
     * @access public
     * @param string    $logic     查询逻辑 and or xor
     * @param mixed     $field     查询字段
     * @param mixed     $op        查询表达式
     * @param mixed     $condition 查询条件
     * @param array     $param     查询参数
     * @param  bool     $strict    严格模式
     * @return $this
     */
    protected function parseWhereExp($logic, $field, $op, $condition, $param = [], $strict = false)
    {
        if ($field instanceof $this) {
            $this->options['where'] = $field->getOptions('where');
            return $this;
        }

        $logic = strtoupper($logic);

        if ($field instanceof Where) {
            $this->options['where'][$logic] = $field->parse();
            return $this;
        }

        if (is_string($field) && !empty($this->options['via']) && !strpos($field, '.')) {
            $field = $this->options['via'] . '.' . $field;
        }
        if ($field instanceof Lib_orm_Expression) {
            return $this->whereRaw($field, is_array($op) ? $op : []);
        } elseif ($strict) {
            // 使用严格模式查询
            $where = [$field, $op, $condition];
        } elseif (is_array($field)) {
            // 解析数组批量查询
            return $this->parseArrayWhereItems($field, $logic);
        } elseif ($field instanceof \Closure) {
            $where = $field;
            $field = '';
        } elseif (is_string($field)) {
            if (preg_match('/[,=\<\'\"\(\s]/', $field)) {
                return $this->whereRaw($field, $op);
            } elseif (is_string($op) && strtolower($op) == 'exp') {
                $bind = isset($param[2]) && is_array($param[2]) ? $param[2] : null;
                return $this->whereExp($field, $condition, $logic);
            }

            $where = $this->parseWhereItem($logic, $field, $op, $condition, $param);
        }

        if (!empty($where)) {
            $this->options['where'][$logic][] = $where;
        }

        return $this;
    }

    /**
     * 分析查询表达式
     * @access protected
     * @param  string   $logic     查询逻辑 and or xor
     * @param  mixed    $field     查询字段
     * @param  mixed    $op        查询表达式
     * @param  mixed    $condition 查询条件
     * @param  array    $param     查询参数
     * @return mixed
     */
    protected function parseWhereItem($logic, $field, $op, $condition, $param = [])
    {
        if (is_array($op)) {
            // 同一字段多条件查询
            array_unshift($param, $field);
            $where = $param;
        } elseif ($field && is_null($condition)) {
            if (in_array(strtoupper($op), ['NULL', 'NOTNULL', 'NOT NULL'], true)) {
                // null查询
                $where = [$field, $op, ''];
            } elseif (in_array($op, ['=', 'eq', 'EQ', null], true)) {
                $where = [$field, 'NULL', ''];
            } elseif (in_array($op, ['<>', 'neq', 'NEQ'], true)) {
                $where = [$field, 'NOTNULL', ''];
            } else {
                // 字段相等查询
                $where = [$field, '=', $op];
            }
        } elseif (in_array(strtoupper($op), ['REGEXP', 'NOT REGEXP', 'EXISTS', 'NOT EXISTS', 'NOTEXISTS'], true)) {
            $where = [$field, $op, is_string($condition) ? $this->raw($condition) : $condition];
        } else {
            $where = $field ? [$field, $op, $condition] : null;
        }

        return $where;
    }

    /**
     * 数组批量查询
     * @access protected
     * @param  array    $field     批量查询
     * @param  string   $logic     查询逻辑 and or xor
     * @return $this
     */
    protected function parseArrayWhereItems($field, $logic)
    {
        if (key($field) !== 0) {
            $where = [];
            foreach ($field as $key => $val) {
                if ($val instanceof Lib_orm_Expression) {
                    $where[] = [$key, 'exp', $val];
                } elseif (is_null($val)) {
                    $where[] = [$key, 'NULL', ''];
                } else {
                    $where[] = [$key, is_array($val) ? 'IN' : '=', $val];
                }
            }
        } else {
            // 数组批量查询
            $where = $field;
        }

        if (!empty($where)) {
            $this->options['where'][$logic] = isset($this->options['where'][$logic]) ? array_merge($this->options['where'][$logic], $where) : $where;
        }

        return $this;
    }

    /**
     * 去除某个查询条件
     * @access public
     * @param string $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function removeWhereField($field, $logic = 'AND')
    {
        $logic = strtoupper($logic);

        if (isset($this->options['where'][$logic])) {
            foreach ($this->options['where'][$logic] as $key => $val) {
                if (is_array($val) && $val[0] == $field) {
                    unset($this->options['where'][$logic][$key]);
                }
            }
        }

        return $this;
    }

    /**
     * 去除查询参数
     * @access public
     * @param string|bool $option 参数名 true 表示去除所有参数
     * @return $this
     */
    public function removeOption($option = true)
    {
        if (true === $option) {
            $this->options = [];
        } elseif (is_string($option) && isset($this->options[$option])) {
            unset($this->options[$option]);
        }

        return $this;
    }

    /**
     * 条件查询
     * @access public
     * @param mixed             $condition  满足条件（支持闭包）
     * @param \Closure|array    $query      满足条件后执行的查询表达式（闭包或数组）
     * @param \Closure|array    $otherwise  不满足条件后执行
     * @return $this
     */
    public function when($condition, $query, $otherwise = null)
    {
        if ($condition instanceof \Closure) {
            $condition = $condition($this);
        }

        if ($condition) {
            if ($query instanceof \Closure) {
                $query($this, $condition);
            } elseif (is_array($query)) {
                $this->where($query);
            }
        } elseif ($otherwise) {
            if ($otherwise instanceof \Closure) {
                $otherwise($this, $condition);
            } elseif (is_array($otherwise)) {
                $this->where($otherwise);
            }
        }

        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return $this
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }

        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');

        return $this;
    }

    /**
     * 指定分页
     * @access public
     * @param mixed $page     页数
     * @param mixed $listRows 每页数量
     * @return $this
     */
    public function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) {
            list($page, $listRows) = explode(',', $page);
        }

        $this->options['page'] = [intval($page), intval($listRows)];

        return $this;
    }

    /**
     * 指定当前操作的数据表
     * @access public
     * @param mixed $table 表名
     * @return $this
     */
    public function table($table)
    {
        if (is_string($table)) {
            if (strpos($table, ')')) {
                // 子查询
            } elseif (strpos($table, ',')) {
                $tables = explode(',', $table);
                $table  = [];

                foreach ($tables as $item) {
                    list($item, $alias) = explode(' ', trim($item));
                    if ($alias) {
                        $this->alias([$item => $alias]);
                        $table[$item] = $alias;
                    } else {
                        $table[] = $item;
                    }
                }
            } elseif (strpos($table, ' ')) {
                list($table, $alias) = explode(' ', $table);

                $table = [$table => $alias];
                $this->alias($table);
            }
        } else {
            $tables = $table;
            $table  = [];

            foreach ($tables as $key => $val) {
                if (is_numeric($key)) {
                    $table[] = $val;
                } else {
                    $this->alias([$key => $val]);
                    $table[$key] = $val;
                }
            }
        }
        $this->options['table'] = $table;

        return $this;
    }

    /**
     * USING支持 用于多表删除
     * @access public
     * @param mixed $using
     * @return $this
     */
    public function using($using)
    {
        $this->options['using'] = $using;
        return $this;
    }

    /**
     * 指定排序 order('id','desc') 或者 order(['id'=>'desc','create_time'=>'desc'])
     * @access public
     * @param string|array $field 排序字段
     * @param string       $order 排序
     * @return $this
     */
    public function order($field, $order = null)
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            if (!empty($this->options['via'])) {
                $field = $this->options['via'] . '.' . $field;
            }

            if (strpos($field, ',')) {
                $field = array_map('trim', explode(',', $field));
            } else {
                $field = empty($order) ? $field : [$field => $order];
            }
        } elseif (!empty($this->options['via'])) {
            foreach ($field as $key => $val) {
                if (is_numeric($key)) {
                    $field[$key] = $this->options['via'] . '.' . $val;
                } else {
                    $field[$this->options['via'] . '.' . $key] = $val;
                    unset($field[$key]);
                }
            }
        }

        if (!isset($this->options['order'])) {
            $this->options['order'] = [];
        }

        if (is_array($field)) {
            $this->options['order'] = array_merge($this->options['order'], $field);
        } else {
            $this->options['order'][] = $field;
        }

        return $this;
    }

    /**
     * 表达式方式指定Field排序
     * @access public
     * @param  string $field 排序字段
     * @param  array  $bind  参数绑定
     * @return $this
     */
    public function orderRaw($field, $bind = [])
    {
        if ($bind) {
            $this->bindParams($field, $bind);
        }

        $this->options['order'][] = $this->raw($field);

        return $this;
    }

    /**
     * 指定Field排序 order('id',[1,2,3],'desc')
     * @access public
     * @param  string|array $field 排序字段
     * @param  string       $values 排序值
     * @param  string       $order
     * @return $this
     */
    public function orderField($field, array $values = [], $order = '')
    {
        if (!empty($values)) {
            $values['sort'] = $order;

            $this->options['order'][$field] = $values;
        }

        return $this;
    }

    /**
     * 随机排序
     * @access public
     * @return $this
     */
    public function orderRand()
    {
        $this->options['order'][] = '[rand]';
        return $this;
    }

    /**
     * 查询缓存
     * @access public
     * @param mixed             $key    缓存key
     * @param integer|\DateTime $expire 缓存有效期
     * @param string            $tag    缓存标签
     * @return $this
     */
    public function cache($key = true, $expire = null, $tag = null)
    {
        // 增加快捷调用方式 cache(10) 等同于 cache(true, 10)
        if ($key instanceof \DateTime || (is_numeric($key) && is_null($expire))) {
            $expire = $key;
            $key    = true;
        }

        if (false !== $key) {
            $this->options['cache'] = ['key' => $key, 'expire' => $expire, 'tag' => $tag];
        }

        return $this;
    }

    /**
     * 指定group查询
     * @access public
     * @param string $group GROUP
     * @return $this
     */
    public function group($group)
    {
        $this->options['group'] = $group;
        return $this;
    }

    /**
     * 指定having查询
     * @access public
     * @param string $having having
     * @return $this
     */
    public function having($having)
    {
        $this->options['having'] = $having;
        return $this;
    }

    /**
     * 指定查询lock
     * @access public
     * @param bool|string $lock 是否lock
     * @return $this
     */
    public function lock($lock = false)
    {
        $this->options['lock']   = $lock;
        $this->options['master'] = true;

        return $this;
    }

    /**
     * 指定distinct查询
     * @access public
     * @param string $distinct 是否唯一
     * @return $this
     */
    public function distinct($distinct)
    {
        $this->options['distinct'] = $distinct;
        return $this;
    }

    /**
     * 指定数据表别名
     * @access public
     * @param mixed $alias 数据表别名
     * @return $this
     */
    public function alias($alias)
    {
        if (is_array($alias)) {
            foreach ($alias as $key => $val) {
                $table = $key;
                $this->options['alias'][$table] = $val;
            }
        } else {
            if (isset($this->options['table'])) {
                $table = is_array($this->options['table']) ? key($this->options['table']) : $this->options['table'];
            } else {
                $table = $this->getTable();
            }

            $this->options['alias'][$table] = $alias;
        }

        return $this;
    }

    /**
     * 指定强制索引
     * @access public
     * @param string $force 索引名称
     * @return $this
     */
    public function force($force)
    {
        $this->options['force'] = $force;
        return $this;
    }


    /**
     * 忽略索引
     * @access public
     * @param string $force 索引名称
     * @return $this
     */
    public function ignore($ignore)
    {
        $this->options['ignore'] = $ignore;
        return $this;
    }

    /**
     * 查询注释
     * @access public
     * @param string $comment 注释
     * @return $this
     */
    public function comment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 获取执行的SQL语句
     * @access public
     * @param boolean $fetch 是否返回sql
     * @return $this
     */
    public function fetchSql($fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;
        return $this;
    }

    /**
     * 不主动获取数据集
     * @access public
     * @param bool $pdo 是否返回 PDOStatement 对象
     * @return $this
     */
    public function fetchPdo($pdo = true)
    {
        $this->options['fetch_pdo'] = $pdo;
        return $this;
    }

    /**
     * 设置是否返回数据集对象（支持设置数据集对象类名）
     * @access public
     * @param  bool|string  $collection  是否返回数据集对象
     * @return $this
     */
    public function fetchCollection($collection = true)
    {
        $this->options['collection'] = $collection;
        return $this;
    }

    /**
     * 设置从主服务器读取数据
     * @access public
     * @return $this
     */
    public function master()
    {
        $this->options['master'] = true;
        return $this;
    }

    /**
     * 设置是否严格检查字段名
     * @access public
     * @param bool $strict 是否严格检查字段
     * @return $this
     */
    public function strict($strict = true)
    {
        $this->options['strict'] = $strict;
        return $this;
    }

    /**
     * 设置查询数据不存在是否抛出异常
     * @access public
     * @param bool $fail 数据不存在是否抛出异常
     * @return $this
     */
    public function failException($fail = true)
    {
        $this->options['fail'] = $fail;
        return $this;
    }

    /**
     * 设置自增序列名
     * @access public
     * @param string $sequence 自增序列名
     * @return $this
     */
    public function sequence($sequence = null)
    {
        $this->options['sequence'] = $sequence;
        return $this;
    }

    /**
     * 设置需要隐藏的输出属性
     * @access public
     * @param  mixed $hidden 需要隐藏的字段名
     * @return $this
     */
    public function hidden($hidden)
    {
        if ($this->model) {
            $this->options['hidden'] = $hidden;
            return $this;
        }

        return $this->field($hidden, true);
    }

    /**
     * 设置需要输出的属性
     * @access public
     * @param  array $visible 需要输出的属性
     * @return $this
     */
    public function visible(array $visible)
    {
        $this->options['visible'] = $visible;
        return $this;
    }

    /**
     * 设置需要追加输出的属性
     * @access public
     * @param  array $append 需要追加的属性
     * @return $this
     */
    public function append(array $append)
    {
        $this->options['append'] = $append;
        return $this;
    }

    /**
     * 设置数据字段获取器
     * @access public
     * @param  string|array $name       字段名
     * @param  callable     $callback   闭包获取器
     * @return $this
     */
    public function withAttr($name, $callback = null)
    {
        if (is_array($name)) {
            $this->options['with_attr'] = $name;
        } else {
            $this->options['with_attr'][$name] = $callback;
        }

        return $this;
    }

    /**
     * 设置JSON字段信息
     * @access public
     * @param  array $json JSON字段
     * @param  bool  $assoc 是否取出数组
     * @return $this
     */
    public function json(array $json = [], $assoc = false)
    {
        $this->options['json']       = $json;
        $this->options['json_assoc'] = $assoc;
        return $this;
    }

    /**
     * 设置字段类型信息
     * @access public
     * @param  array $type 字段类型信息
     * @return $this
     */
    public function setJsonFieldType(array $type)
    {
        $this->options['field_type'] = $type;
        return $this;
    }

    /**
     * 获取字段类型信息
     * @access public
     * @param  string $field 字段名
     * @return string|null
     */
    public function getJsonFieldType($field)
    {
        return isset($this->options['field_type'][$field]) ? $this->options['field_type'][$field] : null;
    }

    /**
     * 添加查询范围
     * @access public
     * @param array|string|\Closure   $scope 查询范围定义
     * @param array                   $args  参数
     * @return $this
     */
    public function scope($scope, ...$args)
    {
        // 查询范围的第一个参数始终是当前查询对象
        array_unshift($args, $this);

        if ($scope instanceof \Closure) {
            call_user_func_array($scope, $args);
            return $this;
        }

        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }

        if ($this->model) {
            // 检查模型类的查询范围方法
            foreach ($scope as $name) {
                $method = 'scope' . trim($name);

                if (method_exists($this->model, $method)) {
                    call_user_func_array([$this->model, $method], $args);
                }
            }
        }

        return $this;
    }

    /**
     * 指定数据表主键
     * @access public
     * @param string $pk 主键
     * @return $this
     */
    public function pk($pk)
    {
        $this->pk = $pk;
        return $this;
    }

    /**
     * 查询日期或者时间
     * @access public
     * @param string       $name  时间表达式
     * @param string|array $rule  时间范围
     * @return $this
     */
    public function timeRule($name, $rule)
    {
        $this->timeRule[$name] = $rule;
        return $this;
    }

    /**
     * 查询日期或者时间
     * @access public
     * @param string       $field 日期字段名
     * @param string|array $op    比较运算符或者表达式
     * @param string|array $range 比较范围
     * @return $this
     */
    public function whereTime($field, $op, $range = null)
    {
        if (is_null($range)) {
            if (is_array($op)) {
                $range = $op;
            } else {
                if (isset($this->timeExp[strtolower($op)])) {
                    $op = $this->timeExp[strtolower($op)];
                }

                if (isset($this->timeRule[strtolower($op)])) {
                    $range = $this->timeRule[strtolower($op)];
                } else {
                    $range = $op;
                }
            }

            $op = is_array($range) ? 'between' : '>=';
        }

        $this->where($field, strtolower($op) . ' time', $range);

        return $this;
    }

    /**
     * 查询日期或者时间范围
     * @access public
     * @param string    $field 日期字段名
     * @param string    $startTime    开始时间
     * @param string    $endTime 结束时间
     * @return $this
     */
    public function whereBetweenTime($field, $startTime, $endTime = null)
    {
        if (is_null($endTime)) {
            $time    = is_string($startTime) ? strtotime($startTime) : $startTime;
            $endTime = strtotime('+1 day', $time);
        }

        $this->where($field, 'between time', [$startTime, $endTime]);

        return $this;
    }


    /**
     * 参数绑定
     * @access public
     * @param  mixed   $value 绑定变量值
     * @param  integer $type  绑定类型
     * @param  string  $name  绑定标识
     * @return $this|string
     */
    public function bind($value,$name = null)
    {
        if (is_array($value)) {
            $this->bind = array_merge($this->bind, $value);
        } else {
            $name = $name ?: 'ThinkBind_' . (count($this->bind) + 1) . '_';

            $this->bind[$name] = [$value];
            return $name;
        }

        return $this;
    }

    /**
     * 参数绑定
     * @access public
     * @param  string $sql    绑定的sql表达式
     * @param  array  $bind   参数绑定
     * @return void
     */
    protected function bindParams(&$sql, array $bind = [])
    {
        foreach ($bind as $key => $value) {
            if (is_array($value)) {
                $name = $this->bind($value[0], $value[1], isset($value[2]) ? $value[2] : null);
            } else {
                $name = $this->bind($value);
            }

            if (is_numeric($key)) {
                $sql = substr_replace($sql, ':' . $name, strpos($sql, '?'), 1);
            } else {
                $sql = str_replace(':' . $key, ':' . $name, $sql);
            }
        }
    }

    /**
     * 检测参数是否已经绑定
     * @access public
     * @param string $key 参数名
     * @return bool
     */
    public function isBind($key)
    {
        return isset($this->bind[$key]);
    }

    /**
     * 查询参数赋值
     * @access public
     * @param  string $name     参数名
     * @param  mixed  $value    值
     * @return $this
     */
    public function option($name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * 查询参数赋值
     * @access protected
     * @param array $options 表达式参数
     * @return $this
     */
    protected function options(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 获取当前的查询参数
     * @access public
     * @param string $name 参数名
     * @return mixed
     */
    public function getOptions($name = '')
    {
        if ('' === $name) {
            return $this->options;
        }

        return isset($this->options[$name]) ? $this->options[$name] : null;
    }

    /**
     * 设置当前的查询参数
     * @access public
     * @param string $option 参数名
     * @param mixed  $value  参数值
     * @return $this
     */
    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
        return $this;
    }



    /**
     * 获取绑定的参数 并清空
     * @access public
     * @param bool $clear
     * @return array
     */
    public function getBind($clear = true)
    {
        $bind = $this->bind;
        if ($clear) {
            $this->bind = [];
        }

        return $bind;
    }

    /**
     * 创建子查询SQL
     * @access public
     * @param bool $sub
     * @return string
     * @throws DbException
     */
    public function buildSql($sub = true)
    {
        return $sub ? '( ' . $this->getfetchSql() . ' )' : $this->getfetchSql();
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access protected
     * @param Query     $query   查询对象
     * @return array
     */
    protected function parseOptions()
    {
        $options = $this->getOptions();

        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
        }

        if (!isset($options['where'])) {
            $options['where'] = [];
        } elseif (isset($options['view'])) {
            // 视图查询条件处理
            $this->parseView($options);
        }

        if (!isset($options['field'])) {
            $options['field'] = '*';
        }

        foreach (['data', 'order'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = [];
            }
        }

        if (!isset($options['strict'])) {
            $options['strict'] = false;
        }

        foreach (['master', 'lock', 'fetch_pdo', 'distinct'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }


        foreach (['join', 'union', 'group', 'having', 'limit', 'force', 'comment','ignore'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = '';
            }
        }

        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page                  = $page > 0 ? $page : 1;
            $listRows              = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset                = $listRows * ($page - 1);
            $options['limit']      = $offset . ',' . $listRows;
        }

        $this->options = $options;

        return $options;
    }


    public function getSelectSql(){
        $query = $this;
        // 分析查询表达式
        $query->parseOptions();
        $options = $query->getOptions();

        $data = isset($options['data'])?$options['data']:null;

        $query->setOption('data', $data);

        // 生成查询SQL
        $sql = $this->builder->select($query);

        $bind = $query->getBind();
        return $this->builder->getRealSql($sql, $bind);
    }

    public function getUpdateSql($data=[]){
        $this->parseOptions();
        $this->options['data'] = array_merge($this->options['data'], $data);
        // 生成查询SQL
        $sql = $this->builder->update($this);
        $bind = $this->getBind();
        return $this->builder->getRealSql($sql, $bind);
    }

    public function getDeleteSql($force=false){
        $this->parseOptions();
        $options = $this->getOptions();

        $data = isset($options['data'])?$options['data']:null;

        $this->setOption('data', $data);

        if (true !== $force && empty($options['where'])) {
            // 如果条件为空 不进行删除操作 除非设置 1=1
            throw new Exception('delete without condition');
        }

        // 生成删除SQL语句
        $sql = $this->builder->delete($this);
        $bind = $this->getBind();
        return $this->builder->getRealSql($sql, $bind);
    }

    public function getInsertSql($data=[]){
        $this->parseOptions();
        $this->options['data'] = array_merge($this->options['data'], $data);
        // 生成查询SQL
        $sql = $this->builder->insert($this);
        $bind = $this->getBind();
        return $this->builder->getRealSql($sql, $bind);
    }



    public function getfetchSql()
    {
        // 分析查询表达式
        $this->parseOptions();
        $options = $this->getOptions();

        $data = isset($options['data'])?$options['data']:null;

        $this->setOption('data', $data);

        // 生成查询SQL
        $sql = $this->builder->select($this);

        $bind = $this->getBind();
        return $this->builder->getRealSql($sql, $bind);
    }
}
