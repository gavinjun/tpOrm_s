<?php

class Lib_orm_Db
{
    /**
     * 查询类名
     * @var string
     */
    protected static $query;

    /**
     * 查询类自动映射
     * @var array
     */
    protected static $queryMap = [
    ];

    public static function setQuery($query)
    {
        self::$query = $query;
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @param string  $name 字符串
     * @param integer $type 转换类型
     * @param bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        } else {
            return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
        }
    }

    public static function __callStatic($method, $args)
    {
        if (!self::$query) {
            $class = 'Lib_orm_Query';

            self::$query = $class;
        }

        $class = self::$query;

        return call_user_func_array([new $class, $method], $args);
    }
}