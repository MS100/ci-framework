# CI 框架文档
 
> 基于**CI3.x**版本

> 项目手册只列出了与CI不同的地方，其他请查看[CI官方手册](http://codeigniter.org.cn/user_guide/)

***

## 0. 框架规范

> [PHP标准规范](https://psr.phphub.org/)

* 遵循 **PSR-1** [基础编码规范](https://laravel-china.org/topics/2078/psr-specification-psr-1-basic-coding-specification)，控制器层类、方法的命名规范详见**【[2. 类名、文件名和位置](#2-%E7%B1%BB%E5%90%8D%E6%96%87%E4%BB%B6%E5%90%8D%E5%92%8C%E4%BD%8D%E7%BD%AE)】**；属性名使用下划线分隔式 ($under_score)。
* 遵循 **PSR-2** [编码风格规范](https://laravel-china.org/topics/2079/psr-specification-psr-2-coding-style-specification)。
* 遵循 **PSR-3** [日志接口规范](https://laravel-china.org/topics/2080/psr-specification-psr-3-log-interface-specification)。
* 遵循 **PSR-4** [自动加载规范](https://laravel-china.org/topics/2081/psr-specification-psr-4-automatic-loading-specification)。

#### 其他约定：
* 文件名、类名 **不该** 使用复数；文件夹名 **建议** 使用复数。
* 重写框架的类库的时候，遵循 **CI** 的命名规范，类名首字母大写，函数名、方法名、变量名 **应该** 全部小写，单词间用下划线分割；其他情况遵循PSR规范。

***

## 1. 项目结构

项目中框架通过 composer 安装。我们在原 CI 框架的基础上做了一些修改和扩展，相当于外面包了一层，[项目位置](https://gitlab.ifchange.com/bpc/be/support-and-help/ci-framework)。

**ci-framework** 的作用：

1. 将应用层的 **libraries**、**helpers**、**大部分core**、**thrid_party**、**大部分config**、**language**、**公用的 logic、model、dao** (例如：在线配置configure、频率控制frequency、缓存刷新cache_append) 从应用层分离出来，统一管理。
2. 各应用层只关注 **controller**、**logic**、**model**、**dao** 里的业务逻辑，和 config 里私有的配置。
3. 各应用层之间互相调用时统一类和函数。

> 因为使用了命名空间与自动加载，所以某项目自用的类库可以写在项目自己的目录内，但切记加载方式必须使用自动加载，不能使用CI_Loader的加载。

另外我们将一些调用第三方接口的方法单独封装了起来作为 **rpc** 项目，它的作用是：

1. 用来封装对第三方接口的调用。
2. 方便各业务对接口的复用。

***

## 2. 类名、文件名和位置

使用 **CI** 原有加载方式的文件请遵循以下规则：

* 所有新定义的、继承的和重写的 **libraries** 放在 **ci-framework** 里，应用层中的定义、继承、重写不会被加载。

    > 项目中添加新类库使用命名空间自动加载的方式。

* 继承原CI框架的类库，可使用自动加载，保持与原文件名的大小写一致。
* 重写原CI框架的类库和函数库，文件名与原文件名保持大小写一致。
* 在 Controller、Logic、Model、Dao 及其他新增的类**必须**严格遵循PSR规范，类名、文件名使用大写开头的驼峰式（CamelCase），方法名使用小写开头的驼峰式 (camelCase)。
* 废弃 **CI** 原有的`$this->model('xxxx')`；使用命名空间配合自动加载，**logic**、**model**、**dao** 中的所有方法实现为 **静态** 方法。

### 命名空间
* 各项目的根命名空间为`App\`，各类的命名空间严格按照目录结构来。
* **ci-framework** 的根命名空间为`CI\`。
* 项目的控制器层根据运行方式不同分为 web、api、queue、cli，分别位于各自对应的复数名文件夹。
* **model**、**dao** 文件继承 `CI\core\Model`、`CI\core\Dao\<MysqlCacheDao|MysqlNoCacheDao|MongoDao>`。  

#### 以下各项请注意：
* 在静态话的文件中，尽量避免使用`get_instantce()`来取 **CI** 对象；
* 取配置项用`config_item(xxx, xxxx)`;
* 在所有地方取 **cache** 对象用 `cache(缓存配置项)`; 在 **Dao** 层用 `self::cache()`;
* 在 **Dao** 层取 **db** 对象用`self::db()`;

### 自动加载
* 项目利用 **Composer** 来实现类的自动加载和外部包管理。

    > [Composer中文网](http://docs.phpcomposer.com/)

* 若一个请求只在某处使用外部类，可以直接 `new` 对象。
* 若一个请求在多处用到外部类，**应该**使用参数传递对象。
* 若外部类的初始化不能通过配置文件完成，可以进行再次封装，例如 **CI/libraries/Twiggy** 就是对 **Twig** 进行了再次封装。


***

## 3. Controller、Logic、Model、Dao 层的作用

* **Controller** 层用来做 **request** 的 **基础校验**（必填、格式、长度等等）以及 **组装 response**。
* **Logic** 层用来实现具体 **业务逻辑** 和 **数据的进一步校验**（比如说权限、是否存在等等）。
* **Model** 层用来做 **Dao** 层方法的 **对外封装**、**多表操作** 和 **事务**。
* **Dao** 层用来处理 **单一数据表** 的各种查询的在数据库的具体实现和做缓存，一般一个 **Dao** 层的文件对应一张数据表。

**Controller 和 Logic 只可以使用 Model 层方法，不可用使用 Dao 层的方法；Dao 层的方法只有 Model 层可以使用。**

### CI 超级对象和 Controller 层的改变
**CI** 框架原本将 Controller 做为一个超级对象，所有加载的类都会变成 Controller 对象的一个属性；但实际上所有核心类 New 出来的对象无法统一管理。

所以现在将 **Controller** 层与 **CI 超级对象** 拆分开，作为两个独立的对象实例。CI 对象在所有核心类加载之前进行初始化，所有核心类直接加载为CI对象的一个属性；另外增加 `ci()` 函数，作用和 `get_instance()` 相同，都是用来获取超级对象。

#### **CI** 超级对象的类的继承关系为：
CI\core\CI << CI\core\Runner\\\<Fpm\Web|Swoole\Queue|Cli\>\CI 

#### **Controller** 层的继承关系为：
CI\core\Controller << 项目名\core\\\(Web|Api|Queue|Cli)Controller(这层可以没有) << 具体的Controller层文件

Controller 层最后可以直接 return 一个php标量数据或数组。此返回值会直接赋值给接口返回数据的 ['results'] 字段。如果直接返回布尔型，框架会自动将其放入 success 字段返回。

```json
    {
        "err_no": "0",
        "err_msg": "",
        "results": {
            "success": true
        }
    }
```

#### Controller 层代码示例

```php
<? 
    public function verifyAccount()
    {
        validate_post(); //验证数据
        $post_data = $this->input->post();
        return AccountLogic::verifyAccount($post_data);
    }
```

**注意：禁止定义会改变的静态属性，防止常驻内存方式执行时产生错误**

#### Url

`App\webs\FooFoo::TestTest($id)` 请求默认uri为 foo_foo/test_test/$id


### Dao 层及数据库操作
具体见 **CI/core/Dao/Dao.php | MysqlCacheDao.php | MysqlNoCacheDao.php** 三个文件里的注释。

```php
<?
    protected static $_table;//数据库表名，格式：库名.表名，mongodb为库名，集合名
    protected static $_active_group = '';//使用的数据库配置，见 ci-framework\config\环境\database.php
    protected static $_insert_fields = [];//可插入的字段，元素格式：field_name => '默认值'
    protected static $_update_fields = [];//可更新的字段，元素格式：field_name => ''
    protected static $_select_fields = [];//可查询的字段，元素格式：field_name
    protected static $_udx = [];//唯一索引，元素格式：['parent_id' => '', 'name' => '']
    protected static $_idx = [];//普通索引，元素格式：['where' => ['cate_id' => ''], 'sort' => ['id'=>'asc'], 'limit' => 200 ]，在mongo_Dao中也可以做>,<等查询，limit只在mysql_Dao_cache中生效表示此key只缓存前200条记录
    protected static $_primary_key = '';//主键
    protected static $_created_time_key = null;//插入时间字段key
    protected static $_updated_time_key = null;//修改时间字段key
    protected static $_auto_pk = true;//在mysql中控制是否为自增id，在mongodb中为自动生成的_id
```

**特别注意**：**一定**用 `self::get(Pk|Udx|Idx|IdxCount)CacheKey()` 来取得要操作的缓存的 **key**。
因为这些方法里集成了额外的功能，使得可以在不清空memcache的情况下，批量刷新一个表的缓存，尤其在表结构改变，需要清除之前缓存的时候，这显得十分方便。

#### Dao 层方法说明

```php
<?
    //返回单条数据的方法以fetch开头，返回多条数据的方法以find开头
    
    //根据唯一索引查找主键，找不到返回 null
    fetchPkByUdx(['udx' => 1]);

    //根据唯一索引查找数据，找不到返回 null
    fetchByUdx(['udx' => 1], ['要查的字段']);

    //根据唯一索引查找一组数据，返回 [udx1 => pk1, udx2 => pk2]]，找不到元素的不返回
    //例如有三个字段的唯一索引 u1_u2_u3
    findPksByUdxLastFieldWhereIn(['u1'=>1, 'u2'=>2], 'u3', [5,3,4]);
    //例如有一个字段的唯一索引 u 想批量查找
    findPksByUdxLastFieldWhereIn([], 'u', [1,3,2]);
    
    //根据唯一索引查找一组数据，返回 [udx1 => data1, udx2 => data2]]，找不到元素的不返回
    findByUdxLastFieldWhereIn(['u1'=>1, 'u2'=>2], 'u3', [5,3,4], ['要查的字段']);

    //根据主键查找数据，找不到返回 null
    fetchByPk('pk', ['要查的字段']);

    //根据一批主键查找数据，找不到元素的不返回
    findByPks(['pk1', 'pk2'], ['要查的字段']);

    //查询索引下的数据数量，返回一个整数
    //例如有索引 i1_i2
    countByIdx(['i1'=>1, 'i2'=>2]);
    
    //根据索引查找多组数据的数量，返回 [idx1 => count1，idx2 => count2]]
    //例如有索引 i1_i2_i3
    countMultiByIdxLastFieldWhereIn(['i1'=>1, 'i2'=>2], 'i3', [3,4,5]);

    //根据索引查找一组数据，可以翻页、排序、选择字段
    //返回 [ curent_page => 1, per_page => 5, total => 10, data => [ pk1 => data1, pk2 => date2]]
    //例如有索引 i1
    findByIdx(['i1'=>1], ['id'=>'desc'], ['page'=>1,'pagesize'=>10,'selected'=>['要查的字段']]);

    //根据索引查找一组主键，可以翻页和排序
    //返回 [ current_page => 2, per_page => 10, total => 40, data => [ pk1, pk2]]
    //例如有索引 i1
    findPksByIdx(['i1'=>1]);

    //根据索引查找一组主键，可以排序，不可以翻页
    //返回 [idx1 => [pk1, pk2], idx2 => [pk3, pk4]]]
    //例如有索引 i1_i2_i3
    findMultiPksByIdxLastFieldWhereIn(['i1'=>1, 'i2'=>2], 'i3', [5,3,4], ['排序']);

    //根据索引查找一组数据，可以排序和选择字段，不可以翻页
    //返回 [idx1 => [pk1 => data1, pk2 => data2], idx2 => [pk3 => data3, pk4 => data4]]]
    //例如有索引 i1
    findMultiByIdxLastFieldWhereIn([], 'i1', [5,3,4], ['排序'], ['要查的字段']);

    //插入数据
    insert(['field1'=>'v1', 'field2'=>'v2']);

    //批量插入数据
    insertBatch([['field1'=>'v1', 'field2'=>'v2'],['field1'=>'v3', 'field2'=>'v4']]);

    //根据主键更新数据
    updateByPk(['field1'=>'v1', 'field2'=>'v2'], 'pk1');

    //根据唯一索引更新数据
    updateByUdx(['field1'=>'v1', 'field2'=>'v2'], ['udx'=>1]);
    
    //根据唯一索引更新一组数据
    updateByUdxLastFieldWhereIn(['field1'=>'v1', 'field2'=>'v2'], ['u1'=>1, 'u2'=>2], 'u3', [5,3,4]);

    //根据索引更新数据
    updateByIdx(['field1'=>'v1', 'field2'=>'v2'], ['i1'=>1, 'i2'=>2]);

    //根据索引更新多组数据
    updateByIdxLastFieldWhereIn(['field1'=>'v1', 'field2'=>'v2'], ['i1'=>1, 'i2'=>2], 'i3', [3,4,5]);

    //根据一批主键更新数据
    updateByPks(['field1'=>'v1', 'field2'=>'v2'], ['pk1', 'pk2']);

    //根据主键删除
    deleteByPk('pk1');

    //根据唯一索引删除
    deleteByUdx(['u1'=>1, 'u2'=>2]);

    //根据索引删除
    deleteByIdx(['i1'=>1, 'i2'=>2]);

    //根据一批主键删除
    deleteByPks(['pk1', 'pk2']);

    //根据唯一索引删除一批数据
    deleteByudxLastFieldWhereIn(['u1'=>1, 'u2'=>2], 'u3', [5,3,4]);

    //根据索引删除一批数据
    deleteByIdxLastFieldWhereIn(['i1'=>1, 'i2'=>2], 'i3', [3,4,5]);

```

***

## 4. index.php 文件和主要常量的定义

在多前端的应用中通过 **nginx** 传来的 `$_SERVER['SOURCE']` 或 **PHP_SAPI** 来判断请求来自哪一端。并用它来定义常量`SERVER_SOURCE`它的值会有 **web app api job cli** 等等，它们分别对应 **CI/core/Runner** 命名空间中的文件，用来定义不同来源下的不同初始化、输出、校验等方法。

在 **ci-framework/config/constant.php** 中定义了下面的常量用来在程序或数据表的source字段中区分来源。

在各个应用的**index.php**中，需要定义了以下常量。

```php
<?
    define('APPPATH', __DIR__ . DIRECTORY_SEPARATOR);
    define('APPNAME', basename(APPPATH));//应用名
    
```

环境常量 **ENVIRONMENT** 是通过 docker 的环境变量定义的。
***


## 5. 接口返回值和错误
### 返回格式
除了显示图片、文件下载、RPC等特殊接口，一般接口的json返回数据格式统一是

```json
{
    "err_no" : "0", //错误码
    "err_msg" : "", //错误信息
    "results" : null, //结果或更详细的错误信息
}
```
前面提到过调用 Controller 层的方法的返回值，会直接放在 'results' 字段。

### 错误
#### 错误信息配置
所有错误信息配置在各项目下的 config/error.php 文件。格式为：

```php
<?
    $error['gearman_work_fail'] = [1210001, '服务失败']; //数组第一个元素为错误码，第二个元素为错误信息
    $error['gm_job_server_connect_failed'] = [1210002, '连接失败'];
    $error['gearman_work_timeout'] = [1210003, '服务超时'];
    $error['gearman_work_not_found'] = [1210004, '服务没有找到'];
```

#### 错误码定义规则：
**appid + 两位项目代号 + 错误码（三位）**

具体细节查看项目内的错误码

错误信息 key 的定义规则为 '模块名_错误描述'，用下划线分割单词，要求定义清晰不会产生歧义，后缀**不需要**跟 _err 和 _err_no。例如：

```php
<?
    $error['account_phone_bound'] = [1201006, '此手机号已被绑定'];
```

**注意** 要保证定义的错误码在项目范围内具有唯一性。
**注意** ci-framework 中定义的错误信息配置其他项目可以直接使用。

#### 错误信息类 
所有错误信息通过错误类 `CI\core\Error` 来传递

```php
<?

    namespace CI\core;
    
    class Error
    {
        protected $code = '0';
        protected $msg = '';
        protected $results = null;
    
        public function __construct(string $code, string $msg, array $results = [])
        {
            $this->code = $code;
            $this->msg = $msg;
            $this->results = $results;
        }
    
        public function getCode(): string
        {
            return $this->code;
        }
    
        public function getMsg(): string
        {
            return $this->msg;
        }
    
        public function getResults()
        {
            return $this->results;
        }
    
        public function setCode(string $code)
        {
            $this->code = $code;
        }
    
        public function setMsg(string $msg)
        {
            $this->msg = $msg;
        }
    
        public function setResults(array $results)
        {
            $this->results = $results;
        }
    
        public function appendResults(array $results)
        {
            $this->results = array_merge($this->results, $results);
        }
    }

```

你可以通过以下方式获得一个错误信息对象

```php
<?
    $error = new \CI\core\Error('1','这是错误信息', ['这是结果，可省略']); //某些没有写在 error.php 里的，或者不通用错误可以这样写，否则还是建议配置到 error.php 文件中
    //或者
    $error = err('error.php 中的 key', ['这是结果，可省略']);
```

#### 错误信息传递
错误信息类最终都要通过抛出异常的方式传递给框架，例如：

```php
<?
    throw new \SeniorException(
        '这个字符串会打印到日志里，记录、调试和排错使用',
        err('一个错误信息配置')，
        '这里写一个 LOG_LEVEL 常量，默认为 LOG_LEVEL_NOTICE'
    );
    
    throw new \JuniorException(err('一个错误信息配置'));
```
这两种的区别在于 Senior 会产生单独的日志，而 Junior 不会产生单独的日志。

**请按照实际情况使用，不可因为偷懒而全部使用 Junior。** 

除非有特殊处理（例如回滚，解锁等），否则不需要在你的代码外层 try catch 这个异常，框架会自动处理。

框架接收到错误异常后会将对应字段填充到返回的数据结构中。

#### Exception 类
所有 Exception 类抛出的异常将 getMessage() 打印一条 notice 级别的日志，接口返回的 err_msg 统一为"程序出错"

***

## 6. API 和 JOB

work 方式的请求现在已经废弃，注意看 Http API 的就可以了。

### 概述
内部 **API** 放在 **APPPATH/apis** 目录里，此目录只有来源是 **api**(http请求) 和 **job**(work请求)的情况才能被访问到。命名空间为 `namespace App\apis`，可以支持子目录。

**http** 和 **work** 接口的写法和普通 **Controller** 相同，**强烈** 建议使用表单验证 `validate_post()` 来验证数据有效性。

在 **apis** 目录中增加的接口可同时支持 http 和 work 请求，但是**特别注意**
> 因为work的进程在请求结束后是不会退出的，所以一定要注意用到的静态变量的初始化

每次请求默认会重新生成CI超级对象、其他的核心对象以及控制器对象。

### work启动方式
```shell
    php 项目路径/gearman.php
```
举例：

```shell
    php /opt/wwwroot/bpc/project_tpl/gearman.php 
```

> work的具体配置看 config/gearman.php 里的说明。

### 接口调用方式
例如要调用 ci-project-tpl/apis/Account::fetch 接口，方法如下：

* **http**请求 **URL**：api.bole.ifchange.com/account/fetch，**POST**参数：uid=1
> 注意不需要在url中加上api目录的路径，入口文件会自己判断从哪个文件夹找 controller。

* **work**请求 **work**名：项目前缀_account，参数**c**: account，**m**: fetch，**p**: {"uid":1}
> **work** 名是在/config/gearman.php中通过`prefix = xxx`来指定的。

***

## 7. CLI

**CLI** 为命令行执行方式，所有 **CLI** 脚本放在 **APPPATH/clis** 目录下，编写方式与普通的 **Contrller** 相同。命名空间为 `namespace App\clis`，并且不支持子目录。

> 此目录只有在命令行执行才能被访问到。

### 执行 CLI 脚本  
例如执行 **ci-project-tpl/clis/resume.php** 中的 **checkResume** 方法

```shell
    cd ci-project-tpl
    php cli.php resume check_resume [参数1[,参数2...]] 
```

***

## 8. Config类和 config 目录

**Config** 类具体看 **ci-framework/core/Config.php** 里的代码和注释。

配置文件里的数据库配置、缓存配置以及其他一些所有项目公用或相同的配置都放在 **ci-framework/config** 目录中，各项目不同的配置放在各自的 **config** 目录里。

#### 注意config的加载方式和顺序是：
```php
<?
    function load(xxx){
        if file_existed include(ci-framework/config/xxx.php);
        if file_existed include(ci-framework/config/environment/xxx.php);
        if file_existed include(ci-project-tpl/config/xxx.php);
        if file_existed include(ci-project-tpl/config/environment/xxx.php);
        //加载 后台.configure 表中 ci-project-tpl 应用 xxx 文件的配置;
    }
```

* **特别注意** 加载顺序靠后的文件里各配置项目的定义方式，不要用`$config = ['xxx' => 'xxx', 'vvv' => 'vvv'];`这样会把加载顺序靠前的文件里的配置覆盖掉。应该用`$config['xxx'] = 'xxx';`这样的定义方式。

* **后台.configure** 表中各项目的配置是通过 **admin** 后台配置的，配置针对每个项目的某个文件，配置加载的时候会针对特定的文件加载数据库里的配置。
> **config.php**、**cache.php**、**memcached.php**、**database.php** 这四个文件的不能使用 **后台.configure** 来配置，因为 **configure** 功能需要依赖这四个配置文件来初始化。

### 路由和表单验证的配置
路由和表单验证的配置分别放 routes 和 form_rules 文件夹中，根据运行方式进行区分。分别有 web.php、api.php、cli.php、queue.php。

### 关于配置的公共函数

#### config_item(\$item)
获取 Config 对象 $config 属性中的配置项

```php
<?
    //获取ci->config->config['aaa']['bbb']['ccc']
    config_item('aaa.bbb.ccc');
```

#### config_set_item(\$item,\$value)
设置 Config 对象 $config 属性中的配置项

```php
<?
    //设置ci->config->config['aaa']['bbb']['ccc']
    config_item('aaa.bbb.ccc', 'value');

```

#### config_load(\$file, \$use_sections = false, \$fail_gracefully = false)
加载配置文件到 Config 对象的 $config 属性

```php
<?
    //把aaa.php文件内容加载到 ci->config->config
    config_load('aaa');
    
    //把aaa.php文件内容加载到 ci->config->config['aaa']
    config_load('aaa', true);
    
```

#### config_file(\$file, \$fail_gracefully = false)
从文件中加在配置，并返回文件中的配置数组

```php
<?
    //获取aaa.php文件的内容
    $aaa = config_file('aaa');
```


***

## 9. Loader类

**Loader** 类具体看 **ci-framework/core/Loader.php** 里的代码和注释。
因为使用自动加载，所以此类已经被大大削弱，一般不会用到。

### 自动加载
具体见/config/autoload.php里的注释。

新建配置文件所有情况(所有来源)下都需要加载的放在`$autoload['config']`里，特定条件下才需要加载的用`$this->config->load('xxxx', xxx, xxx)`，注意第二和第三个参数的说明

```php
<?
    $autoload['config'] = ['common'];
    $autoload['helper'] = []; //是自动加载的函数库
```
### 废弃的方法

* add_package_path
* remove_package_path
* model

***

## 10. Twiggy类

**Twiggy** 类具体查看 **ci-framework/libraries/Twiggy.php** 里的代码和注释。

* **ci-framework/config/twiggy.php** 里有 `$config['register_functions'] = []; $config['register_filters'] = [];` 是用来注册 **twig** 函数和 **twig** 过滤器的。

***

## 11. Log类

**Log** 类具体查看 **ci-framework/core/Log.php** 里的代码和注释
> 此类完全重写，不同于 **CI** 原来的 **Log** 类。

* 增加了`LOG::trace()`方法可以输出调试信息，或者使用`log_message('trace', 0)`。
* **ci-framework/core/Common.php** 里增加了 **my_backtrace()** 函数可以获取追踪信息。

### 日志分割
框架利用monolog管理日志，各项目日志路径为
/opt/log/项目名/info-日期.log 和 /opt/log/项目名/warn-日期.log

> 具体配置方法可参考 **ci-framework/config/log.php**。

> mongolog文档 <https://github.com/Seldaek/monolog/tree/master/doc>

### 日志格式
在业务流程中抛出异常时，错误信息使用 **__METHOD__:** 开头。例如

```php
<?
    throw new \SeniorException(
        sprintf('%s: the user:%s exists', __METHOD__, $username),
        err('account_exists')
    );
```


### 日志等级
请严格遵守 [PSR-3日志接口规范](https://laravel-china.org/topics/2080/psr-specification-psr-3-log-interface-specification) 对日志等级的定义和下文规范，不可乱用日志等级。
* 业务流程的异常情况日志使用 **notice** 等级，例如：帐号密码错误，简历未完善等。
* 调用 work 接口失败时如果错误是由job server发出的，例如：work不存在、超时等，使用 **critical** 等级；如果是在 work 业务异常，例如：参数错误等，使用 **error** 等级。
* 调用 http 接口失败时，如果返回状态码不为200，使用 **critical** 等级；业务异常使用 **error** 等级。
* 基础类库、TWIG 模版错误日志使用 **critical** 等级。
* 请求结束后会写一条 **info** 级别日志。
* 请确保日志不要出现重复。


框架会自动处理收到的异常，进而打印日志，可以利用 

```
throw new \SeniorException($log_msg, $error, 日志级别常量);
```

来自定义错误打印的日志级别。

```php
<?
    //日志级别常量
    define('LOG_LEVEL_DEBUG', 'debug');
    define('LOG_LEVEL_INFO', 'info');
    define('LOG_LEVEL_NOTICE', 'notice');
    define('LOG_LEVEL_WARNING', 'warning');
    define('LOG_LEVEL_ERROR', 'error');
    define('LOG_LEVEL_CRITICAL', 'critical');
    define('LOG_LEVEL_ALERT', 'alert');
    define('LOG_LEVEL_EMERGENCY', 'emergency');
```

***

## 12.表单验证类

具体变更查看 **ci-framework/libraries/FormValidation.php** 里的代码和注释。

表单验证的配置文件放在 APPPATH/config/form_rules 文件夹中，根据运行方式进行区分。分别有 web.php、api.php、cli.php、queue.php。

### 使用注意
1. 直接使用 validate_post() 和 validate_get() 方法验证 \$_POST 和 \$_GET ；其中 \$_FILES 会合并到 \$_POST 中。其他情况下要使用表单验证直接 `new FormValidation(验证规则数组)`。
2. 不会过滤没有设置验证规则的字段，所以**一定不能**直接将验证完的数据直接作为参数传给数据库函数或其他对数据格式有严格要求的方法中。
3. 字段值不可以为 **null**，为 **null** 时候**一定**通不过验证。
4. 如果数据为二维及以上数组，请定义好每一维的规则；只有父级存在并通过验证，子级的验证规则才会生效。

    ```php
    <?
        $rules = [
            [
                'field' => 'info[id]',//rules只会作用于info[id]，不会作用于info的其他元素
                'label' => 'ID信息',
                'rules' => 'required|is_natural_no_zero',
            ],
        ];
        
        $_POST = []; //通过，因为 info 字段并没有定义为必填
        $_POST = ['info' => '']; //不能通过验证，会根据 info[id] 推断出 info 为数组
        $_POST = ['info' => []]; //不能通过验证，因为如果存在 info，则必须存在 info[id]
    ```

5. 除了字符串格式，其他格式的字段要用 is_类型（包括 is_array, is_bool, is_string, is_numeric, is_int, is_float）函数修饰，例如某一个字段为数组格式，规则里一般要写上 is_array；如果字段值为字符串 is_string 可以不写，执行时会自动补全。

    ```php
    //例如 **POST** 字段 **id** 是必填且不能是空数组，并且元素值不能是空字符串，那表单验证规则为
    <?
        $rules = [
            [
                'field' => 'id',
                'label' => 'ID',
                'rules' => 'required|is_array',
            ],
            [
                'field' => 'id[]',//此处的中括号表示匹配id下的每一个数组元素，rules会循环作用于每一个元素
                'label' => 'ID',
                'rules' => 'required',//这里如果没写 is_类型 函数，则默认为is_string，这里的 required，只用来限制元素值不能是空字符串
            ],
        ];
        
        $_POST = ['id' => ['a','b','c']]; //通过
        $_POST = ['id' => ['a'=>'a','b'=>'b','c'=>'c']]; //通过
        $_POST = ['id' => [1,2,3]]; //不能通过验证，因为 id 的元素必须是字符串
        $_POST = ['id' => ['','a','b']]; //不能通过验证，因为 id 的元素不能是空字符串
        $_POST = ['id' => []]; //不能通过验证，因为 id 的 required 限制它不能为空数组，将 required 换成 isset 则可以通过验证
        $_POST = []; //不能通过验证，id 字段必填

    ```
    
    ```php
    <?
        //上传图片的验证配置
        $rules = [
            [
                'field' => 'image',
                'label' => '图片',
                'rules' => 'required|is_array',//一定要记得定义父级
            ],
            [
                'field' => 'image[name]',
                'label' => '图片',
                'rules' => 'required|file_allowed_ext[jpg,jpeg,png,gif]',
            ],
            [
                'field' => 'image[size]',
                'label' => '图片',
                'rules' => 'is_int|file_size_max[2MB]',//这里一定要写 is_int
            ],
            [
                'field' => 'image[error]',
                'label' => '图片',
                'rules' => 'is_int|file_upload_error[0]',//这里一定要写 is_int
            ],
            /*[
                'field' => 'image[tmp_name]',
                'label' => '图片',
                'rules' => 'image_pixel_min[1,1]|image_pixel_max[1000,1000]||valid_image[image[name]]',
            ],*/
        ];
    ```


6. **注意** 表单字段不存在时，将只有 required, isset, matches, least_one_required, default_value 这几个规则生效；表单字段值为 空字符串、空数组时，将另有 not_empty_str, not_empty_array 这两个规则生效。

    ```php
    <?
        $rules = [
            [
                'field' => 'name',
                'label' => '名字',
                'rules' => 'min_length[3]',//name 字段不存在或者是可以通过验证的。若要必填，还需加上 required。
            ],
        ];
        
        $_POST = ['name' => '']; //通过
        $_POST = []; //通过;
        $_POST = ['name' => []]; //不能通过验证，默认为 is_string
        $_POST = ['name' => 'ab']; //不能通过验证，长度不足
    ```

7. 所有单个参数且返回值不为 bool 型的函数和方法（例如：`trim`、`array_value`、`filter_emoji`）都会改变字段的值。
8. `reset_error` 方法用来重置错误数据，可在批处理相同验证规则的数据时，不需要重复调用 `set_rules` 方法（若使用 `reset_validation` 方法重置验证类，则需要重复调用 `set_rules`）。
9. 验证规则里的 field 字段，可以无限层级（虽然实际情况很少会用到）：
    * name
    * basic[name]
    * work[]
    * work[][name]
    * basic[like][]
    * mm[nn][][ii]....
    * mm[nn][][ii][][jj]...
    
    ```php
    <?
        $rules = [
            [
                'field' => 'like',
                'label' => '爱好',
                'rules' => 'is_set|is_array',
            ],
            [
                'field' => 'like[]',
                'label' => '爱好信息',
                'rules' => 'required|is_array', //因为 like[][name] 设置了 required，所以这里的 required 实际上没有什么意义，但提示信息会不一样
            ],
            [
                'field' => 'like[][name]',
                'label' => '爱好名称',
                'rules' => 'required',
            ],
            [
                'field' => 'like[][level]',
                'label' => '爱好程度',
                'rules' => 'required',
            ],
            [
                'field' => 'like[][des]',
                'label' => '爱好描述',
                'rules' => 'max_length[30]',
            ],
        ];
        
        $_POST = []; //不能通过，因为 like 字段必须存在
        $_POST = ['like' => []]; //通过
        
        $_POST = [
            'like' => [
                'aaa', //不能通过，元素必须是数组
                ['name' => 'football', 'level' => 'normal'], //通过
                ['name' => 'basketball', 'des' => 'haha'], //不能通过，因为没有level字段
                ['name' => 'sing', 'level' => 'normal', 'des' => 'song'], //通过
            ],
        ];

    ```
    
10. feild 最后为 [] 的验证规则，会作用于没有被之前的同级规则匹配到的所有元素。所以 feild 最后为 [] 的验证规则一般要写在同级验证规则的最后。

    ```php
    <?
        $rules = [
            [
                'field' => 'name[a]',
                'label' => 'A',
                'rules' => 'required|min_length[3]',
            ],
            [
                'field' => 'name[]',
                'label' => '其他',
                'rules' => 'required|min_length[5]',
            ],
            [
                'field' => 'name[b]',
                'label' => 'B',
                'rules' => 'required|min_length[2]',
            ],
        ];
        
        $_POST = [
            'name' => [
                'a' => 'aaaa', //通过
                'b' => 'bbbb', //此字段会先被 [] 匹配不能通过验证规则
                'c' => 'ccccc', //通过
            ],
        ];
    ```

### CI原有规则
[表单验证规则](http://codeigniter.org.cn/user_guide/libraries/form_validation.html#id25)

### 修订和新增规则

|规则名|用法举例|描述|
|:---|:---:|-------|
|required|-|表示字段必须存在，且不可以为空字符串或空数组|
|isset|-|表示字段必须存在，但可以为空字符串或空数组；isset 组合 上not_empty_str 相当于 required|
|not_empty_str|-|表示字段可以不传，但不能为空字符。即：可以没有此字段，但不能传空字符，一般用于一个验证规则有多种提交情况的时候|
|not_empty_array|-|表示字段可以不传，但不能为空数组。即：前端可以不传此字段，但不能传空数组，一般用于一个验证规则有多种提交情况的时候|
|filter_emoji|-|可在表单验证时用来过滤表情符号；另表单验证时普通字符串数据酌情使用 trim 过滤前后空字符|
|default_value|default[abc]|如果字段不存在或者值为空字符串的时候，给字段设置默认值|
|least_one_required|least_one_required[其他字段名]|两个字段不可同时不传或为空|
|valid_card|-|验证身份证号码，会实现校验逻辑|
|valid_phone_or_email|-|验证字段值为手机号或邮箱其中一种|
|valid_phone|-|验证手机号|
|valid_tel|-|验证固定电话|
|valid_md5|-|验证md5后的字符串|
|max_length_gbk|max_length_gbk[20]|验证字段长度不超过20，英文为一个字符，中文为两个|
|min_length_gbk|min_length_gbk[30]|验证字段长度不小于30，英文为一个字符，中文为两个|
|date_later_than|date_later_than[2018-02] 或 date_later_than[其他字段名]|验证日期必须晚于设置的日期或字段值|
|date_before_than|date_before_than[2018-02] 或 date_later_than[其他字段名]|验证日期必须早于设置的日期或字段值|
|valid_date|valid_date 或 valid_date[-1] 或 valid_date[1]|验证日期合法性，参数 -1 表示过去的时间，1 表示未来的时间，不传参数表示合法日期即可|
|count_min|count_min[3]|表示数组元素数量不小于3个|
|count_max|count_max[5]|表示数组元素数量不超过5个|
|count_exact|count_exact[4]|表示数组元素数量为4个|
|valid_dfs_info_signature|valid_dfs_info_signature|写在所有规则最后，验证CI\libraries\Signature::encode_dfs_info签名的字符串并解码返回原数据|
|valid_url_path_signature|valid_url_path_signature|写在所有规则最后，验证CI\libraries\Signature::sign_url_path签名的字符串并解码返回原数据|

***

## 13. 数据库配置

所有数据库配置放在 **ci-framework/config/环境/database.php** 中。  
各 **Dao** 要用到哪个配置**必须**设置它的 `$_active_group` 属性。

> 数据库的加载代码可查看：  
> **ci-framework/libraries/DB** 
> **ci-framework/database/DB.php**  
> **ci-framework/database/DB_driver.php**

配置举例：

```php
<?
    //模版配置
    $db_tpl = [
        'hostname' => '127.0.0.1',
        'port' => 3306,
        'username' => 'devuser',
        'password' => 'devuser',
        'database' => '',
        'dbdriver' => 'mysqli',
        'pconnect' => false,
        'db_debug' => false,
        'cache_on' => false,
        'char_set' => 'utf8',
        'dbcollat' => 'utf8_general_ci',
        'encrypt' => false,
        'compress' => false,
        'stricton' => true,
    ];

    //默认库
    $db['default'] = $db_tpl;
    
```

程序生成的 sql 语句中会带有 库名.表名，所以不需要配置 database ；在同一台服务器同一个端口的库共用一个配置。

***

## 14. 第三方接口调用

所有要调用其他项目的 **http** 和 **gearman** 接口都封装在 **rpc** 项目中。

域名的配置放置在 rpc 的 config 文件夹内，可在项目的 rpc 目录中重定义。

调用方式分为 gearman、http、http_rpc 三种方式，各自 use 相应的 Trait 来实现。
***

## 15. 消息队列
### 定义
在 项目/config/mq_producer.php 和 mq_consumer.php 中定义消息队列，注意区分消息的生产者和消费者。

```php
<?
    //mq_producer.php
    return [
         //使用rabbitmq的消息队列
        '生产者名1' => [
            'driver' => 'rabbitmq',
            'server' => 'default', //从config/rabbitmq.php中读取
            'exchange' => '', //根据发布类型配置
            'routing_key' => 'bpc.project_tpl.resume_flush', //根据发布类型配置
        ],
    
        //使用redis的消息队列
            '生产者名2' => [
            'driver' => 'redis',
            'server' => 'redis_queue', //从config/cache.php中读取
            'queue' => 'bpc.project_tpl.resume_algorithm', //队列名
        ],
    ];
    
    //mq_consumer.php
    return [
        '消费者名1' => [
            'driver' => 'rabbitmq',
            'server' => 'default', //从config/rabbitmq.php中读取
            'queue' => 'bpc.project_tpl.resume_flush', //队列名
            'ack' => true, //开启确认模式，消费失败会发给下一个消费者；false为关闭
        ],
        
        '消费者名2' => [
            'driver' => 'redis',
            'server' => 'redis_queue', //从config/cache.php中读取
            'queue' => 'bpc.project_tpl.resume_algorithm', //队列名
            'ack' => true, //开启确认模式
            'rollback_wait' => 200, //秒，此时间后没有成功消费就会回滚
        ],
    ];

```
注意 rabbitmq 和 redis 两种消息队列定义方式的细微差别。

#### 消息队列命名规范

见 [开发规范](Dev.md)

### 生产者

```php
<?
    mq_producer('生产者名')->publish($msg); //发布单条消息
    
    call_user_func_array([mq_producer('生产者名'), 'publish'], $pks); //批量发布消息
   
    mq_producer('生产者名')->publish(...$msg); //批量发布消息 php7可用 
```

注意每一条消息的类型不做限制，底层会自动进行序列化。

### 消费者

```php
<?
    mq_consumer('消费者名')->setCallback('回调函数')->consume(); //每次消费一个消息，调试时候使用
```
此方法只是用作 cli 模式下的调试，不可写在业务代码中。

#### 回调函数
回调函数写在各项 APPPATH/queues 目录下。跟 Controller 相同的写法。

```php
<?
    public function consumer() //需要一个参数接受消息
    {
        //如果数据不符合规则，则直接从队列移除
        validate_post();
    
        //...处理消息
        if(处理成功){
            return true；
        }else{
            throw new JuniorException(); //抛出异常或程序终止，都会让消息回滚被重新消费
        }
    }
```
消费者收到的消息在底层会自动 json_decode 反序列化后传给回调函数。

注意：`return ture` 表示消息消费成功；其他情况表示消费失败，消息会被发给其他消费者再次消费。

#### 消费者进程
在项目的 config/swoole_queue.php 中配置消费者进程

```php
<?
    'SwooleManager' => [
        //...
    ],

    '消费者进程名' => [
        'mq_consumer' => '消费的队列名',
        //消费者进程数量
        'process_num' => 2,
        //回调函数
        'callback' => 'grab_resume/run',
    ],
```

消费者进程启动方式 php 项目路径/swoole.php -s queue


## 16. 验证类
在web编程的时候，**一定**要注意对数据的合法性、权限、是否存在等属性进行校验。

用户操作后产生的数据不直接入库，而是返回给浏览器，在用户点击保存按钮时才入库的情况，可以通过给数据增加签名的方式保证数据的合法性。

#### CI\libraries\Signature类

```php
<?
    class Signature
{
    /**
     * 获取一个签名，返回带签名字段的数组
     *
     * @param array $param
     * @return array
     */
    public static function make(array $param): array

    /**
     * 验证签名，成功返回true，失败返回false
     *
     * @param array $param
     * @return bool
     */
    public static function verify(array $param): bool
    

    /**
     * 对一个路径进行签名，在路径里添加签名信息
     *
     * @param string $path
     * @return string
     */
    public static function sign_url_path(string $path): string
    
    /**
     * 恢复一个路径，将签名信息从字符串中剔除
     *
     * @param string $path
     * @return bool|string
     */
    public static function restore_url_path(string $path)
    
    /**
     * 编码dfs信息，生成一个字符串
     *
     * @param string $groupname
     * @param string $filename
     * @param string $localname
     * @return string
     */
    public static function encode_dfs_info(string $groupname, string $filename, string $localname): string
    

    /**
     * 解码dfs信息，返回groupname和filename数组
     *
     * @param string $str
     * @return array|bool
     */
    public static function decode_dfs_info(string $str)
    
```

#### 用法举例

```php
<?
    //当图片上传的时候，要求在用户点击保存的时候再存储图片路径
    
    //--------上传图片时的部分代码--------
    $path = \rpc\picstore::upload();
    return CI\libraries\Signature::sign_url_path($path);
    
    
    //--------点击保存时的部分代码--------
    $path = $this->input->post('image');
    $path = CI\libraries\Signature::restore_url_path($path);

    
```

***

## 17. 语言包
语言包基于CI原有语言包进行了扩展，兼容CI原有的功能。

#### 表单验证
在 项目/language/语种/ 文件夹新建 from_validation_lang.php 文件，它会覆盖 ci原框架 和 ci-framework 同路径文件中的同名配置，例如：

```php
<?
    # config/form_validation.php 文件
    $config['account/do_account/do_login'] = [
        [
            'field' => 'name',
            'label' => '用户名',
            'rules' => 'required|valid_phone',
        ],
        [
            'field' => 'pwd',
            'label' => '密码',
            'rules' => 'required|valid_md5',
        ],
    ];
    
        
    # -----------------------------------
    # language/english/form_validation_lang.php 文件
    $lang['form_validation_required']		= 'The {field} field is required.';
    $lang['form_validation_valid_phone'] = 'The {field} must be valid mobile phone number';
    $lang['form_validation_valid_md5'] = 'The {field} must be MD5 encrypted string';
    
    // 下面的是翻译 label 字段
    $lang['form_label_用户名'] = 'username';
    $lang['form_label_密码'] = 'password';


    // 验证不通过输出：
    /*
    {
        "err_no": "1290001",
        "err_msg": "The input format is wrong",
        "results": {
            "name": "The username must be valid mobile phone number",
            "pwd": "The password field is required."
    }
    */
}
```

> 验证规则的英文翻译已经在 ci-framework 中配置，若无其他需求项目里不需要写；
> label 字段的翻译写在各项目的 form_validation_lang.php 文件中；


#### 错误码
在 项目/language/语种/ 文件夹中新建 error_lang.php 文件，它会覆盖 ci-framework 同路径文件中的同名配置，例如：

```php
<?
    # config/error.php 文件
    $error['param'] = ['1290002', '参数有误或不完整'];
    
    # -----------------------------------
    # language/english/error_lang.php 文件
    $lang['error_param'] = 'The param is wrong';
    
    # -----------------------------------
    echo err('param')->get_msg();
    // 英文环境下将会输出：The param is wrong，中文环境下输出：参数有误或不完整

```

> 若没有文件或翻译项不存在则继续使用 error.php 里的文字



#### 其他情况
在 项目/language/语种/ 文件夹中新建 common_lang.php 文件（也可以用其他名字，但是注意后缀必须是 _lang.php），它会覆盖 ci-framework 同路径文件的同名配置，文件内容如下：

```php
<?
    # language/english/common_lang.php
    
    $lang['common_等级'] = 'level';

    echo lang('等级', 'common');
    // 英文环境下将会输出：level，中文环境下输出：等级
    // lang() 第二个参数不传默认为 common，如果用的其他文件第二个参数必传
    
```
> 若没有文件或翻译项不存在则返回输入参数

# 结束

