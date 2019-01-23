<?php
/**
 * mysql数据库驱动
 */
class Lib_orm_builder_Mysql extends Lib_orm_Builder
{
    // 查询表达式解析
    protected $parser = [
        'parseCompare'     => ['=', '<>', '>', '>=', '<', '<='],
        'parseLike'        => ['LIKE', 'NOT LIKE'],
        'parseBetween'     => ['NOT BETWEEN', 'BETWEEN'],
        'parseIn'          => ['NOT IN', 'IN'],
        'parseExp'         => ['EXP'],
        'parseRegexp'      => ['REGEXP', 'NOT REGEXP'],
        'parseNull'        => ['NOT NULL', 'NULL'],
        'parseBetweenTime' => ['BETWEEN TIME', 'NOT BETWEEN TIME'],
        'parseTime'        => ['< TIME', '> TIME', '<= TIME', '>= TIME'],
        'parseExists'      => ['NOT EXISTS', 'EXISTS'],
        'parseColumn'      => ['COLUMN'],
    ];

    protected $updateSql    = 'UPDATE %TABLE% %JOIN% SET %SET% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';


    /**
     * 正则查询
     * @access protected
     * @param  Query        $query        查询对象
     * @param  string       $key
     * @param  string       $exp
     * @param  Expression   $value
     * @param  string       $field
     * @return string
     */
    protected function parseRegexp(Lib_orm_Query $query, $key, $exp, Expression $value, $field)
    {
        return $key . ' ' . $exp . ' ' . $value->getValue();
    }

    /**
     * 字段和表名处理
     * @access public
     * @param  Query     $query        查询对象
     * @param  string    $key
     * @param  bool      $strict   严格检测
     * @return string
     */
    public function parseKey(Lib_orm_Query $query, $key, $strict = false)
    {
        if (is_numeric($key)) {
            return $key;
        } elseif ($key instanceof Lib_orm_Expression) {
            return $key->getValue();
        }

        $key = trim($key);

        if (strpos($key, '->') && false === strpos($key, '(')) {
            // JSON字段支持
            list($field, $name) = explode('->', $key, 2);

            return 'json_extract(' . $field . ', \'$.' . str_replace('->', '.', $name) . '\')';
        } elseif (strpos($key, '.') && !preg_match('/[,\'\"\(\)`\s]/', $key)) {
            list($table, $key) = explode('.', $key, 2);

            $alias = $query->getOptions('alias');

            if ('__TABLE__' == $table) {
                $table = $query->getOptions('table');
                $table = is_array($table) ? array_shift($table) : $table;
            }

            if (isset($alias[$table])) {
                $table = $alias[$table];
            }
        }

        if ($strict && !preg_match('/^[\w\.\*]+$/', $key)) {
            throw new Exception('not support data:' . $key);
        }

        if (isset($table)) {

            $key = $table .'.'. $key;
        }

        return $key;
    }

    /**
     * 随机排序
     * @access protected
     * @param Query     $query        查询对象
     * @return string
     */
    protected function parseRand(Lib_orm_Query $query)
    {
        return 'rand()';
    }

}
