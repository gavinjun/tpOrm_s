<?php
/**
 * Created by PhpStorm.
 * User: gavin
 * Date: 2019/1/22
 * Time: 下午7:43
 */
$dir = realpath('.');
define('APP_ROOT',$dir.'/');
function my_autoloader($class)
{
    if (class_exists($class, false))
    {
        return;
    }
    $file = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
    $file = APP_ROOT . $file;
    if (!file_exists($file))
    {
        throw new Exception('load class fail|' . $file);
    }
    require_once $file;
    return;
}

spl_autoload_register('my_autoloader');
/**
 * 以下示例均出自tp5的官方文档查询构造器示例
 */
echo Lib_orm_Db::table('think_user')
    ->where('id','>',1)
    ->where('name','thinkphp')
    ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->where([
    'name'	=>	'thinkphp',
    'status'=>	1
])->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->where([
    'name'	=>	'thinkphp',
    'status'=>	[1, 2]
])->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->where([
    ['name','=','thinkphp'],
    ['status','=',1]
])->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->where('type=1 AND status=1')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::name('user')->where('id','<>',100)->getSelectSql().PHP_EOL;

echo Lib_orm_Db::name('user')->where('name','like','thinkphp%')->getSelectSql().PHP_EOL;
//下面这个示例要用这种形式替代
echo "这个写法有误".PHP_EOL;
echo Lib_orm_Db::name('user')->where('name','like',['%think','php%'],'OR')->getSelectSql().PHP_EOL;
echo "应该替换为：".PHP_EOL;
echo Lib_orm_Db::name('user')->where('name',['like', '%thinkphp%'],['like','php%'],'OR')->getSelectSql().PHP_EOL;
echo Lib_orm_Db::name('user')->where('id','between',[1,8])->getSelectSql().PHP_EOL;

echo Lib_orm_Db::name('user')->whereNotBetween('id','1,8')->getSelectSql().PHP_EOL;
echo Lib_orm_Db::name('user')->whereIn('id','1,5,8')->getSelectSql().PHP_EOL;
echo Lib_orm_Db::name('user')->whereNotIn('id','1,5,8')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::name('user')->where('id','exp',' IN (1,3,8) ')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->alias('a')
    ->join('think_dept b ','b.user_id= a.id')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->alias(['think_user'=>'user','think_dept'=>'dept'])
    ->join('think_dept','dept.user_id= user.id')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->field('id,title,content')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->field('id,nickname as name')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->field('id,SUM(score)')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->field(['id','title','content'])->getSelectSql().PHP_EOL;;

echo Lib_orm_Db::table('think_user')->field(['id','nickname'=>'name'])->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->field(['id','concat(name,"-",id)'=>'truename','LEFT(title,7)'=>'sub_title'])->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where('status', 1)
    ->order('id', 'desc')
    ->limit(5)
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where('status', 1)
    ->order(['order','id'=>'desc'])
    ->limit(5)
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where('status', 1)
    ->orderRaw("field(name,'thinkphp','onethink','kancloud')")
    ->limit(5)
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->field('user_id,username,max(score)')
    ->group('user_id')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->field('user_id,test_time,username,max(score)')
    ->group('user_id,test_time')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->field('username,max(score)')
    ->group('user_id')
    ->having('count(test_time)>3')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_artist')
    ->alias('a')
    ->join('work w','a.id = w.artist_id')
    ->join('card c','a.card_id = c.id')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->alias('a')
    ->join(['think_work'=>'w'],'a.id=w.artist_id')
    ->join(['think_card'=>'c'],'a.card_id=c.id')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->alias('a')
    ->leftJoin('word w','a.id = w.artist_id')
        ->getSelectSql().PHP_EOL;
$subsql = Lib_orm_Db::table('think_work')
    ->where('status',1)
    ->field('artist_id,count(id) count')
    ->group('artist_id')
    ->buildSql();

echo Lib_orm_Db::table('think_user')
    ->alias('a')
    ->join([$subsql=> 'w'], 'a.artist_id = w.artist_id')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::field('name')
    ->table('think_user_0')
    ->union('SELECT name FROM think_user_1')
    ->union('SELECT name FROM think_user_2')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::field('name')
    ->table('think_user_0')
    ->union(function ($query) {
        $query->field('name')->table('think_user_1');
    })
    ->union(function ($query) {
        $query->field('name')->table('think_user_2');
    })
        ->getSelectSql().PHP_EOL;


echo Lib_orm_Db::field('name')
    ->table('think_user_0')
    ->union([
        'SELECT name FROM think_user_1',
        'SELECT name FROM think_user_2',
    ])
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::field('name')
    ->table('think_user_0')
    ->unionAll('SELECT name FROM think_user_1')
    ->unionAll('SELECT name FROM think_user_2')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::field('name')
    ->table('think_user_0')
    ->union(['SELECT name FROM think_user_1', 'SELECT name FROM think_user_2'], true)
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->distinct(true)->field('user_login')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::name('user')->where('id',1)->lock(true)->getSelectSql().PHP_EOL;

echo Lib_orm_Db::name('user')->where('id',1)->lock('lock in share mode')->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')->force('user')->getSelectSql().PHP_EOL;

//聚合操作不封装
echo Lib_orm_Db::field('COUNT(*) AS tp_count')
    ->table('think_user')->getSelectSql().PHP_EOL;

//高级查询
echo Lib_orm_Db::table('think_user')
    ->where('name|title','like','thinkphp%')
    ->where('create_time&update_time','>',0)->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where('name', ['like', '%thinkphp%'], ['like', '%kancloud%'], 'or')
    ->where('id', ['>', 0], ['<>', 10], 'and')
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where('name', 'like', '%think%')
    ->where('name', 'like', '%php%')
    ->where('id', 'in', [1, 5, 80, 50])
    ->where('id', '>', 10)
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where([
        ['name', 'like', 'thinkphp%'],
        ['title', 'like', '%thinkphp'],
        ['id', '>', 0],
        ['status', '=', 1],
    ])
        ->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where([
        ['name', 'like', 'thinkphp%'],
        ['title', 'like', '%thinkphp'],
        ['id', 'exp', Lib_orm_Db::raw('>score')],
        ['status', '=', 1],
    ])->getSelectSql().PHP_EOL;

$map1 = [
    ['name', 'like', 'thinkphp%'],
    ['title', 'like', '%thinkphp'],
];

$map2 = [
    ['name', 'like', 'kancloud%'],
    ['title', 'like', '%kancloud'],
];

echo Lib_orm_Db::table('think_user')
    ->whereOr([ $map1, $map2 ])
        ->getSelectSql().PHP_EOL;

$name = 'thinkphp';
$id = 10;
echo Lib_orm_Db::table('think_user')->where(function ($query) use($name, $id) {
    $query->where('name', $name)
        ->whereOr('id', '>', $id);
})->getSelectSql().PHP_EOL;

echo Lib_orm_Db::table('think_user')
    ->where('name', ['like', 'thinkphp%'], ['like', '%thinkphp'])
    ->where(function ($query) {
        $query->where('id', ['<', 10], ['>', 100], 'or');
    })
        ->getSelectSql().PHP_EOL;

$query = new Lib_orm_Query();
$query->where('id','>',0)
    ->where('name','like','%thinkphp')
    ->order('id','desc') // 不会传入后面的查询
    ->field('name,id'); // 不会传入后面的查询

echo Lib_orm_Db::table('think_user')
    ->where($query)
    ->where('title','like','thinkphp%') // 有效
        ->getSelectSql().PHP_EOL;


echo Lib_orm_Db::table('think_user')
    ->whereColumn('name','=','nickname')
        ->getSelectSql().PHP_EOL;


//如果比较复杂的and和or写法推荐,ps:and的优先级要高于or，所以正确写sql就是 and 要在 or之前

echo Lib_orm_Db::table('think_user')
        ->where('type', '>',100)
        ->whereOr(function ($query) {
            $query->where('name', '=','debug');
            $query->whereOr('id', ['<', 10], ['>', 100], 'or');
        })
        ->where('name','test')
        ->getSelectSql().PHP_EOL;

//忽略索引
echo Lib_orm_Db::table('think_user')
        ->where('name', 'like', '%think%')
        ->where('name', 'like', '%php%')
        ->where('id', 'in', [1, 5, 80, 50])
        ->where('id', '>', 10)
        ->ignore('create_time')
        ->getSelectSql().PHP_EOL;

//示例补全

//新增
$data = ['foo' => 'bar', 'bar' => 'foo'];
echo Lib_orm_Db::name('user')->getInsertSql($data).PHP_EOL;

//更新
echo Lib_orm_Db::name('user')
    ->where('id', 1)
    ->getUpdateSql(['name' => 'thinkphp']).PHP_EOL;

echo Lib_orm_Db::name('user')
    ->where('id', 1)
    ->getUpdateSql([
        'name'		=>	Lib_orm_Db::raw('UPPER(name)'),
        'score'		=>	Lib_orm_Db::raw('score-3'),
        'read_time'	=>	Lib_orm_Db::raw('read_time+1')
    ]).PHP_EOL;
//删除
echo Lib_orm_Db::table('think_user')->where('id','<',10)->getDeleteSql().PHP_EOL;
//删除操作需要带条件
//echo Lib_orm_Db::name('user')->getDeleteSql().PHP_EOL;
echo Lib_orm_Db::name('user')->getDeleteSql(true).PHP_EOL;
