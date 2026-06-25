<?php
declare(strict_types=1);

/* UTF-8 fallbacks: shared hosting may not have mbstring enabled. */
if(!function_exists('mb_strtolower')){
    function mb_strtolower(string $value,?string $encoding=null):string{
        static $upper=null;
        if($upper===null)$upper=array_combine(
            preg_split('//u','АӘБВГҒДЕЁЖЗИЙКҚЛМНҢОӨПРСТУҰҮФХҺЦЧШЩЪЫІЬЭЮЯЇЄҐЎ',-1,PREG_SPLIT_NO_EMPTY),
            preg_split('//u','аәбвгғдеёжзийкқлмнңоөпрстуұүфхһцчшщъыіьэюяїєґў',-1,PREG_SPLIT_NO_EMPTY)
        );
        return strtolower(strtr($value,$upper?:[]));
    }
}
if(!function_exists('mb_strlen')){
    function mb_strlen(string $value,?string $encoding=null):int{
        if($value==='')return 0;
        return preg_match_all('/./us',$value,$m)?:0;
    }
}
if(!function_exists('mb_substr')){
    function mb_substr(string $value,int $start,?int $length=null,?string $encoding=null):string{
        $chars=preg_split('//u',$value,-1,PREG_SPLIT_NO_EMPTY);
        if(!is_array($chars))return substr($value,$start,$length);
        $slice=$length===null?array_slice($chars,$start):array_slice($chars,$start,$length);
        return implode('',$slice);
    }
}

/* CONFIG */
const APP='Mini Headless CMS';
const DB='sqlite'; // sqlite | mysql
const SQLITE=__DIR__.'/storage/cms.sqlite';
const MYSQL='mysql:host=localhost;dbname=cms;charset=utf8mb4';
const MYSQL_USER='root';
const MYSQL_PASS='';
const LOGIN='admin';
const PASS='admin123';
const PASS_HASH='';
const LANGS=['ru'=>'Русский','kk'=>'Қазақша','en'=>'English'];
const CONTENT_LANGS=['ru'=>'Русский','kk'=>'Қазақша','en'=>'English','tr'=>'Türkçe','de'=>'Deutsch','fr'=>'Français','es'=>'Español','it'=>'Italiano','pt'=>'Português','zh'=>'中文','ja'=>'日本語','ko'=>'한국어','ar'=>'العربية','hi'=>'हिन्दी','uz'=>'O‘zbekcha'];
const LANG_COOKIE='cms_ui_lang';
const THEME_COOKIE='cms_ui_theme';
const THEMES=['light'=>'Light','dark'=>'Dark'];
const UPLOAD_DIR=__DIR__.'/uploads';
const UPLOAD_URL='uploads';
const UPLOAD_MAX=10485760;
const FILE_EXT=['jpg','jpeg','png','webp','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','rar'];
const IMAGE_EXT=['jpg','jpeg','png','webp','gif'];
const CONFIG_FILE=__DIR__.'/storage/config.json';
const LOGIN_ATTEMPTS_FILE=__DIR__.'/storage/login_attempts.json';
const LOGIN_MAX_FAILS=5;
const LOGIN_BLOCK_SECONDS=600;
const AUTOSAVE_RETENTION_DAYS=30;
const MAINTENANCE_INTERVAL=86400;
const APP_SCHEMA_VERSION=10;
const APP_CACHE_VERSION='2026.06.25.4';
const API_VERSION='1.4';
const CACHE_DIR=__DIR__.'/storage/cache';
const API_CACHE_TTL=300;

/* CORE: small single-file modules. Business functions below remain compatible. */
final class RequestCache{
    private static array $items=[];
    public static function has(string $key):bool{return array_key_exists($key,self::$items);}
    public static function get(string $key,mixed $default=null):mixed{return self::$items[$key]??$default;}
    public static function set(string $key,mixed $value):mixed{return self::$items[$key]=$value;}
    public static function remember(string $key,callable $resolver):mixed{return self::has($key)?self::$items[$key]:self::set($key,$resolver());}
    public static function forget(string $prefix=''):void{if($prefix===''){self::$items=[];return;}foreach(array_keys(self::$items) as $key)if(str_starts_with($key,$prefix))unset(self::$items[$key]);}
}

final class ConfigStore{
    private static ?array $data=null;
    public static function path():string{return CONFIG_FILE;}
    public static function exists():bool{return is_file(self::path());}
    public static function read():array{
        if(self::$data!==null)return self::$data;
        if(!self::exists())return self::$data=[];
        $raw=@file_get_contents(self::path());
        $decoded=is_string($raw)?json_decode($raw,true):null;
        return self::$data=is_array($decoded)?$decoded:[];
    }
    public static function get(string $path,mixed $default=null):mixed{
        $value=self::read();
        foreach(explode('.',$path) as $segment){if(!is_array($value)||!array_key_exists($segment,$value))return $default;$value=$value[$segment];}
        return $value;
    }
    public static function write(array $data):array{
        $dir=dirname(self::path());if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Config directory is not writable');
        $data['updated_at']=date('Y-m-d H:i:s');
        $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE);
        if($json===false)throw new RuntimeException('Config encoding failed');
        $tmp=self::path().'.tmp.'.bin2hex(random_bytes(4));
        if(file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('Config write failed');
        @chmod($tmp,0640);
        if(!@rename($tmp,self::path())){@unlink(self::path());if(!@rename($tmp,self::path())){@unlink($tmp);throw new RuntimeException('Config replace failed');}}
        clearstatcache(true,self::path());return self::$data=$data;
    }
    public static function update(callable $mutator):array{$data=self::read();$mutator($data);return self::write($data);}
    public static function reset():void{if(self::exists())@unlink(self::path());self::$data=null;RequestCache::forget();}
    public static function reload():void{self::$data=null;}
}

final class ResponseCache{
    private static ?string $revision=null;
    private static bool $invalidated=false;
    private static function ensureDir():void{if(!is_dir(CACHE_DIR))@mkdir(CACHE_DIR,0775,true);}
    private static function revisionFile():string{return CACHE_DIR.'/revision';}
    public static function revision():string{
        if(self::$revision!==null)return self::$revision;
        self::ensureDir();$file=self::revisionFile();
        if(!is_file($file))@file_put_contents($file,'1',LOCK_EX);
        $value=@file_get_contents($file);return self::$revision=trim((string)$value)?:'1';
    }
    public static function invalidate():void{
        if(self::$invalidated)return;self::$invalidated=true;self::ensureDir();
        $value=sprintf('%.6F',microtime(true));@file_put_contents(self::revisionFile(),$value,LOCK_EX);self::$revision=$value;
    }
    private static function file(string $key):string{self::ensureDir();return CACHE_DIR.'/api_'.hash('sha256',APP_CACHE_VERSION.'|'.self::revision().'|'.$key).'.cache';}
    public static function key(string $scope='api'):string{
        $query=$_GET;ksort($query);$normalized=http_build_query($query,'','&',PHP_QUERY_RFC3986);$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));if($method==='HEAD')$method='GET';
        return $scope.'|'.$method.'|'.$normalized;
    }
    public static function fetch(string $key):?array{
        $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));if(!in_array($method,['GET','HEAD'],true))return null;
        $file=self::file($key);if(!is_file($file))return null;$raw=@file_get_contents($file);if(!is_string($raw)||$raw==='')return null;
        $item=json_decode($raw,true);if(!is_array($item)||(int)($item['expires']??0)<time()){@unlink($file);return null;}
        return $item;
    }
    public static function store(string $key,string $body,int $status,int $ttl,?string $lastModified,bool $private=false):void{
        if($private||$status!==200||strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!=='GET')return;
        $item=['expires'=>time()+max(1,$ttl),'status'=>$status,'etag'=>'"'.hash('sha256',$body).'"','last_modified'=>$lastModified,'body'=>$body];
        @file_put_contents(self::file($key),json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    }
    public static function serve(string $key,int $maxAge=API_CACHE_TTL):bool{
        $item=self::fetch($key);if(!$item)return false;$etag=(string)($item['etag']??'');$last=(string)($item['last_modified']??'');
        header('Cache-Control: public, max-age='.$maxAge.', stale-while-revalidate=30');header('Vary: Accept-Encoding, Origin');header('X-API-Version: '.API_VERSION);
        if($etag!=='')header('ETag: '.$etag);if($last!=='')header('Last-Modified: '.$last);
        if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);return true;}
        http_response_code((int)($item['status']??200));header('Content-Type: application/json; charset=utf-8');
        if(function_exists('debug_enabled')&&debug_enabled())header('X-CMS-Cache: HIT');if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!=='HEAD')echo (string)($item['body']??'');return true;
    }
    public static function cleanup(int $olderThan=86400):void{
        if(!is_dir(CACHE_DIR))return;$cut=time()-max(3600,$olderThan);foreach(glob(CACHE_DIR.'/api_*.cache')?:[] as $file)if(@filemtime($file)<$cut)@unlink($file);
    }
}

final class Database{
    private static ?PDO $pdo=null;
    private static bool $dirty=false;
    private static int $queries=0;
    private static float $queryTime=0.0;
    public static function pdo():PDO{
        if(self::$pdo)return self::$pdo;
        $cfg=ConfigStore::read();$db=$cfg['db']??[];$driver=(string)($db['driver']??DB);
        $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
        try{
            if($driver==='sqlite'){
                if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('pdo_sqlite is disabled');$file=(string)($db['sqlite_path']??SQLITE);$dir=dirname($file);
                if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('storage is not writable');
                self::$pdo=new PDO('sqlite:'.$file,null,null,$options);self::$pdo->exec('PRAGMA foreign_keys=ON;PRAGMA journal_mode=WAL;PRAGMA synchronous=NORMAL;PRAGMA temp_store=MEMORY;PRAGMA busy_timeout=5000');
                return self::$pdo;
            }
            if($driver==='mysql'){
                if(!extension_loaded('pdo_mysql'))throw new RuntimeException('pdo_mysql is disabled');$mysql=$db['mysql']??[];$host=(string)($mysql['host']??'localhost');$name=(string)($mysql['database']??'cms');$charset=(string)($mysql['charset']??'utf8mb4');
                $dsn='mysql:host='.$host.';dbname='.$name.';charset='.$charset;self::$pdo=new PDO($dsn,(string)($mysql['user']??MYSQL_USER),(string)($mysql['password']??MYSQL_PASS),$options);
                return self::$pdo;
            }
            throw new RuntimeException('Unknown DB driver');
        }catch(Throwable $e){exit('DB error: '.htmlspecialchars($e->getMessage(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));}
    }
    private static function writeQuery(string $sql):bool{return preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i',$sql)===1;}
    private static function affectsPublicCache(string $sql):bool{
        if(!self::writeQuery($sql))return false;
        if(preg_match('/^\s*(?:INSERT(?:\s+OR\s+\w+)?\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)\s+[`"]?([a-z_][a-z0-9_]*)/i',$sql,$m)!==1)return false;
        return in_array(strtolower($m[1]),['p','c','f','e','g','gc','files','forms','form_fields'],true);
    }
    public static function query(string $sql,array $params=[]):PDOStatement{
        $started=microtime(true);$stmt=self::pdo()->prepare($sql);$stmt->execute($params);self::$queries++;self::$queryTime+=microtime(true)-$started;
        if(self::writeQuery($sql))RequestCache::forget();
        if(self::affectsPublicCache($sql)&&!self::$dirty){self::$dirty=true;register_shutdown_function(static fn()=>ResponseCache::invalidate());}
        return $stmt;
    }
    public static function all(string $sql,array $params=[]):array{return self::query($sql,$params)->fetchAll();}
    public static function one(string $sql,array $params=[]):?array{$row=self::query($sql,$params)->fetch();return is_array($row)?$row:null;}
    public static function scalar(string $sql,array $params=[]):mixed{return self::query($sql,$params)->fetchColumn();}
    public static function insert(string $sql,array $params=[]):int{self::query($sql,$params);return (int)self::pdo()->lastInsertId();}
    public static function transaction(callable $callback):mixed{$pdo=self::pdo();$owner=!$pdo->inTransaction();if($owner)$pdo->beginTransaction();try{$result=$callback($pdo);if($owner)$pdo->commit();return $result;}catch(Throwable $e){if($owner&&$pdo->inTransaction())$pdo->rollBack();throw $e;}}
    public static function stats():array{return ['queries'=>self::$queries,'seconds'=>self::$queryTime];}
}

final class Runtime{
    private static function needsSession():bool{
        if(isset($_COOKIE[session_name()]))return true;
        $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
        if(isset($_GET['api'])&&in_array($method,['GET','HEAD','OPTIONS'],true))return false;
        if(array_key_exists('form',$_GET)&&in_array($method,['GET','HEAD','POST','OPTIONS'],true))return false;
        return true;
    }
    public static function start():void{
        error_reporting(E_ALL);ini_set('display_errors','0');ini_set('display_startup_errors','0');ini_set('html_errors','0');ini_set('log_errors','1');
        $debug=(bool)ConfigStore::get('settings.debug_mode',false);ini_set('display_errors',$debug?'1':'0');ini_set('display_startup_errors',$debug?'1':'0');ini_set('html_errors',$debug?'1':'0');
        ini_set('session.use_strict_mode','1');ini_set('session.cookie_httponly','1');ini_set('session.use_only_cookies','1');ini_set('session.lazy_write','1');session_cache_limiter('');
        $_SESSION=$_SESSION??[];
        if(self::needsSession()&&session_status()!==PHP_SESSION_ACTIVE){$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();}
        if(PHP_SAPI!=='cli'&&!headers_sent()){header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('Permissions-Policy: geolocation=(), camera=(), microphone=()');}
    }
}

/* PHP RUNTIME */
if(isset($_GET['asset']))serve_internal_asset((string)$_GET['asset']);
Runtime::start();

/* I18N */
function lang(){static $l=null;if($l!==null)return $l;$fallback='ru';if(function_exists('cfg_setting'))$fallback=(string)cfg_setting('ui_language','ru');if(!isset(LANGS[$fallback]))$fallback='ru';$x=$_COOKIE[LANG_COOKIE]??($_SESSION['_lang']??$fallback);$l=isset(LANGS[$x])?$x:$fallback;$_SESSION['_lang']=$l;return $l;}
function set_lang($l){$l=isset(LANGS[$l])?$l:'ru';$_SESSION['_lang']=$l;$_COOKIE[LANG_COOKIE]=$l;setcookie(LANG_COOKIE,$l,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $l;}
function theme(){static $x=null;if($x!==null)return $x;$fallback='light';if(function_exists('cfg_setting'))$fallback=(string)cfg_setting('ui_theme','light');if(!isset(THEMES[$fallback]))$fallback='light';$v=$_COOKIE[THEME_COOKIE]??($_SESSION['_theme']??$fallback);$x=isset(THEMES[$v])?$v:$fallback;$_SESSION['_theme']=$x;return $x;}
function set_theme($v){$v=isset(THEMES[$v])?$v:'light';$_SESSION['_theme']=$v;$_COOKIE[THEME_COOKIE]=$v;setcookie(THEME_COOKIE,$v,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $v;}
function T($k){static $t=[
'app'=>['ru'=>'Мини Headless CMS','kk'=>'Mini Headless CMS','en'=>'Mini Headless CMS'],'login'=>['ru'=>'Логин','kk'=>'Логин','en'=>'Login'],'password'=>['ru'=>'Пароль','kk'=>'Құпиясөз','en'=>'Password'],'enter'=>['ru'=>'Войти','kk'=>'Кіру','en'=>'Sign in'],'logout'=>['ru'=>'Выйти','kk'=>'Шығу','en'=>'Logout'],'wrong_login'=>['ru'=>'Неверный логин или пароль','kk'=>'Логин немесе құпиясөз қате','en'=>'Wrong login or password'],
'home'=>['ru'=>'Главная','kk'=>'Басты бет','en'=>'Home'],'breadcrumb_nav'=>['ru'=>'Хлебные крошки','kk'=>'Навигациялық жол','en'=>'Breadcrumbs'],'groups'=>['ru'=>'Разделы контента','kk'=>'Контент бөлімдері','en'=>'Content sections'],'group'=>['ru'=>'Раздел контента','kk'=>'Контент бөлімі','en'=>'Content section'],'new_group'=>['ru'=>'Создать раздел контента','kk'=>'Контент бөлімін жасау','en'=>'Create content section'],'edit_group'=>['ru'=>'Редактировать раздел','kk'=>'Бөлімді өзгерту','en'=>'Edit section'],'delete_group'=>['ru'=>'Удалить раздел','kk'=>'Бөлімді жою','en'=>'Delete section'],'delete_group_q'=>['ru'=>'Удалить раздел? Коллекции, записи, поля и файлы останутся в CMS.','kk'=>'Бөлімді жоясыз ба? Коллекциялар, жазбалар, өрістер және файлдар CMS ішінде қалады.','en'=>'Delete the section? Collections, entries, fields, and files will remain in the CMS.'],'group_saved'=>['ru'=>'Раздел контента сохранён','kk'=>'Контент бөлімі сақталды','en'=>'Content section saved'],'group_deleted'=>['ru'=>'Раздел контента удалён','kk'=>'Контент бөлімі жойылды','en'=>'Content section deleted'],'select_collections'=>['ru'=>'Выбери коллекции','kk'=>'Коллекцияларды таңдаңыз','en'=>'Select collections'],'group_api_hint'=>['ru'=>'Один запрос отдаёт несколько коллекций сразу','kk'=>'Бір сұраныс бірнеше коллекцияны бірге қайтарады','en'=>'One request returns multiple collections at once'],'collections'=>['ru'=>'Коллекции','kk'=>'Коллекциялар','en'=>'Collections'],'collection'=>['ru'=>'Коллекция','kk'=>'Коллекция','en'=>'Collection'],'new_collection'=>['ru'=>'Создать коллекцию','kk'=>'Коллекция жасау','en'=>'Create collection'],'edit_collection'=>['ru'=>'Редактировать коллекцию','kk'=>'Коллекцияны өзгерту','en'=>'Edit collection'],'name'=>['ru'=>'Название','kk'=>'Атауы','en'=>'Name'],'slug'=>['ru'=>'Slug','kk'=>'Slug','en'=>'Slug'],'description'=>['ru'=>'Описание','kk'=>'Сипаттама','en'=>'Description'],
'save'=>['ru'=>'Сохранить','kk'=>'Сақтау','en'=>'Save'],'close'=>['ru'=>'Закрыть','kk'=>'Жабу','en'=>'Close'],'delete'=>['ru'=>'Удалить','kk'=>'Жою','en'=>'Delete'],'delete_collection'=>['ru'=>'Удалить коллекцию','kk'=>'Коллекцияны жою','en'=>'Delete collection'],'delete_collection_q'=>['ru'=>'Удалить коллекцию вместе с полями и записями?','kk'=>'Коллекцияны өрістерімен және жазбаларымен бірге жоясыз ба?','en'=>'Delete collection with fields and entries?'],
'collection_saved'=>['ru'=>'Коллекция сохранена','kk'=>'Коллекция сақталды','en'=>'Collection saved'],'collection_deleted'=>['ru'=>'Коллекция удалена','kk'=>'Коллекция жойылды','en'=>'Collection deleted'],'name_required'=>['ru'=>'Название обязательно','kk'=>'Атауы міндетті','en'=>'Name is required'],
'entries'=>['ru'=>'Записи','kk'=>'Жазбалар','en'=>'Entries'],'new_entry'=>['ru'=>'Новая запись','kk'=>'Жаңа жазба','en'=>'New entry'],'edit_entry'=>['ru'=>'Редактировать запись','kk'=>'Жазбаны өзгерту','en'=>'Edit entry'],'title'=>['ru'=>'Заголовок','kk'=>'Тақырып','en'=>'Title'],'service_title'=>['ru'=>'Служебное название','kk'=>'Қызметтік атау','en'=>'Internal title'],'status'=>['ru'=>'Статус','kk'=>'Статус','en'=>'Status'],'draft'=>['ru'=>'Черновик','kk'=>'Жоба','en'=>'Draft'],'published'=>['ru'=>'Опубликовано','kk'=>'Жарияланды','en'=>'Published'],'data'=>['ru'=>'Данные','kk'=>'Деректер','en'=>'Data'],'entry_saved'=>['ru'=>'Запись сохранена','kk'=>'Жазба сақталды','en'=>'Entry saved'],'entry_deleted'=>['ru'=>'Запись удалена','kk'=>'Жазба жойылды','en'=>'Entry deleted'],'title_required'=>['ru'=>'Заголовок обязателен','kk'=>'Тақырып міндетті','en'=>'Title is required'],'delete_entry_q'=>['ru'=>'Удалить запись?','kk'=>'Жазбаны жоясыз ба?','en'=>'Delete entry?'],
'fields'=>['ru'=>'Поля','kk'=>'Өрістер','en'=>'Fields'],'field'=>['ru'=>'Поле','kk'=>'Өріс','en'=>'Field'],'new_field'=>['ru'=>'Новое поле','kk'=>'Жаңа өріс','en'=>'New field'],'edit_field'=>['ru'=>'Редактировать поле','kk'=>'Өрісті өзгерту','en'=>'Edit field'],'label'=>['ru'=>'Метка','kk'=>'Атауы','en'=>'Label'],'key'=>['ru'=>'Ключ','kk'=>'Кілт','en'=>'Key'],'type'=>['ru'=>'Тип','kk'=>'Түрі','en'=>'Type'],'required'=>['ru'=>'Обязательное','kk'=>'Міндетті','en'=>'Required'],'order'=>['ru'=>'Порядок','kk'=>'Реті','en'=>'Order'],'current_fields'=>['ru'=>'Текущие поля','kk'=>'Қазіргі өрістер','en'=>'Current fields'],'field_saved'=>['ru'=>'Поле сохранено','kk'=>'Өріс сақталды','en'=>'Field saved'],'field_deleted'=>['ru'=>'Поле удалено','kk'=>'Өріс жойылды','en'=>'Field deleted'],'field_required'=>['ru'=>'Название поля обязательно','kk'=>'Өріс атауы міндетті','en'=>'Field label is required'],'delete_field_q'=>['ru'=>'Удалить поле?','kk'=>'Өрісті жоясыз ба?','en'=>'Delete field?'],
'api'=>['ru'=>'API','kk'=>'API','en'=>'API'],'theme'=>['ru'=>'Тема','kk'=>'Тақырып','en'=>'Theme'],'light'=>['ru'=>'Светлая','kk'=>'Жарық','en'=>'Light'],'dark'=>['ru'=>'Тёмная','kk'=>'Қараңғы','en'=>'Dark'],'open_api'=>['ru'=>'Открыть API','kk'=>'API ашу','en'=>'Open API'],'settings'=>['ru'=>'Настройки','kk'=>'Баптаулар','en'=>'Settings'],'language'=>['ru'=>'Язык','kk'=>'Тіл','en'=>'Language'],'db'=>['ru'=>'База','kk'=>'База','en'=>'Database'],'no_collections'=>['ru'=>'Нет коллекций','kk'=>'Коллекциялар жоқ','en'=>'No collections'],'no_entries'=>['ru'=>'Нет записей','kk'=>'Жазбалар жоқ','en'=>'No entries'],'no_fields'=>['ru'=>'Нет полей','kk'=>'Өрістер жоқ','en'=>'No fields'],'yes'=>['ru'=>'да','kk'=>'иә','en'=>'yes'],'no'=>['ru'=>'нет','kk'=>'жоқ','en'=>'no'],'updated'=>['ru'=>'Обновлено','kk'=>'Жаңартылды','en'=>'Updated'],'created'=>['ru'=>'Создано','kk'=>'Жасалды','en'=>'Created'],
'files'=>['ru'=>'Файлы','kk'=>'Файлдар','en'=>'Files'],'file'=>['ru'=>'Файл','kk'=>'Файл','en'=>'File'],'current_file'=>['ru'=>'Текущий файл','kk'=>'Қазіргі файл','en'=>'Current file'],'remove_file'=>['ru'=>'Убрать файл из записи','kk'=>'Файлды жазбадан өшіру','en'=>'Remove file from entry'],'file_too_large'=>['ru'=>'Файл слишком большой','kk'=>'Файл тым үлкен','en'=>'File is too large'],'file_type_denied'=>['ru'=>'Недопустимый тип файла','kk'=>'Файл түріне рұқсат жоқ','en'=>'File type is not allowed'],'upload_error'=>['ru'=>'Ошибка загрузки файла','kk'=>'Файл жүктеу қатесі','en'=>'File upload error'],'upload_error_details'=>['ru'=>'Подробная причина загрузки','kk'=>'Жүктеу қатесінің толық себебі','en'=>'Detailed upload error'],'clean_files'=>['ru'=>'Очистить файлы','kk'=>'Файлдарды тазалау','en'=>'Clean files'],'clean_files_q'=>['ru'=>'Удалить неиспользуемые файлы?','kk'=>'Қолданылмайтын файлдарды жоясыз ба?','en'=>'Delete unused files?'],'files_cleaned'=>['ru'=>'Файлы очищены. Удалено: ','kk'=>'Файлдар тазаланды. Жойылды: ','en'=>'Files cleaned. Deleted: '],'trash_files'=>['ru'=>'Мусорные файлы','kk'=>'Артық файлдар','en'=>'Trash files'],'used'=>['ru'=>'Используется','kk'=>'Қолданылады','en'=>'Used'],'unused'=>['ru'=>'Не используется','kk'=>'Қолданылмайды','en'=>'Unused'],'no_files'=>['ru'=>'Файлов пока нет','kk'=>'Файлдар әзірге жоқ','en'=>'No files yet'],'file_size'=>['ru'=>'Размер','kk'=>'Өлшемі','en'=>'Size'],'open'=>['ru'=>'Открыть','kk'=>'Ашу','en'=>'Open'],
'cancel'=>['ru'=>'Отмена','kk'=>'Болдырмау','en'=>'Cancel'],'actions'=>['ru'=>'Действия','kk'=>'Әрекеттер','en'=>'Actions'],'more'=>['ru'=>'Ещё','kk'=>'Тағы','en'=>'More'],'action_menu'=>['ru'=>'Меню действий','kk'=>'Әрекеттер мәзірі','en'=>'Action menu'],'schema'=>['ru'=>'Схема','kk'=>'Схема','en'=>'Schema'],'content'=>['ru'=>'Контент','kk'=>'Контент','en'=>'Content'],'danger_zone'=>['ru'=>'Опасная зона','kk'=>'Қауіпті аймақ','en'=>'Danger zone'],'back'=>['ru'=>'Назад','kk'=>'Артқа','en'=>'Back'],
'users'=>['ru'=>'Пользователи','kk'=>'Пайдаланушылар','en'=>'Users'],'user'=>['ru'=>'Пользователь','kk'=>'Пайдаланушы','en'=>'User'],'new_user'=>['ru'=>'Новый пользователь','kk'=>'Жаңа пайдаланушы','en'=>'New user'],'edit_user'=>['ru'=>'Редактировать пользователя','kk'=>'Пайдаланушыны өзгерту','en'=>'Edit user'],'username'=>['ru'=>'Логин','kk'=>'Логин','en'=>'Username'],'display_name'=>['ru'=>'Имя','kk'=>'Аты','en'=>'Display name'],'new_password'=>['ru'=>'Новый пароль','kk'=>'Жаңа құпиясөз','en'=>'New password'],'password_hint'=>['ru'=>'Оставь пустым, если не нужно менять пароль','kk'=>'Құпиясөзді өзгертпесеңіз, бос қалдырыңыз','en'=>'Leave empty to keep current password'],'role'=>['ru'=>'Роль','kk'=>'Рөл','en'=>'Role'],'admin'=>['ru'=>'Администратор','kk'=>'Әкімші','en'=>'Administrator'],'editor'=>['ru'=>'Редактор','kk'=>'Редактор','en'=>'Editor'],'active'=>['ru'=>'Активен','kk'=>'Белсенді','en'=>'Active'],'inactive'=>['ru'=>'Отключён','kk'=>'Өшірілген','en'=>'Inactive'],'user_saved'=>['ru'=>'Пользователь сохранён','kk'=>'Пайдаланушы сақталды','en'=>'User saved'],'user_deleted'=>['ru'=>'Пользователь удалён','kk'=>'Пайдаланушы жойылды','en'=>'User deleted'],'user_required'=>['ru'=>'Логин обязателен','kk'=>'Логин міндетті','en'=>'Username is required'],'password_required'=>['ru'=>'Пароль обязателен','kk'=>'Құпиясөз міндетті','en'=>'Password is required'],'delete_user'=>['ru'=>'Удалить пользователя','kk'=>'Пайдаланушыны жою','en'=>'Delete user'],'delete_user_q'=>['ru'=>'Удалить пользователя?','kk'=>'Пайдаланушыны жоясыз ба?','en'=>'Delete user?'],'self_protected'=>['ru'=>'Нельзя удалить или отключить текущего пользователя','kk'=>'Ағымдағы пайдаланушыны жоюға немесе өшіруге болмайды','en'=>'You cannot delete or disable the current user'],'access_denied'=>['ru'=>'Нет доступа','kk'=>'Қолжетімсіз','en'=>'Access denied'],
'setup_welcome'=>['ru'=>'Первоначальная настройка','kk'=>'Бастапқы баптау','en'=>'Initial setup'],
'setup_welcome_hint'=>['ru'=>'Настройте интерфейс, контент и подключение к базе данных. Позже всё можно изменить в настройках CMS.','kk'=>'Интерфейсті, контентті және дерекқорға қосылуды баптаңыз. Кейін бәрін CMS баптауларында өзгертуге болады.','en'=>'Configure the interface, content, and database connection. Everything can be changed later in CMS settings.'],
'setup_step_interface'=>['ru'=>'Интерфейс','kk'=>'Интерфейс','en'=>'Interface'],
'setup_step_content'=>['ru'=>'Контент','kk'=>'Контент','en'=>'Content'],
'setup_step_database'=>['ru'=>'База данных','kk'=>'Дерекқор','en'=>'Database'],
'interface_language'=>['ru'=>'Язык интерфейса','kk'=>'Интерфейс тілі','en'=>'Interface language'],
'interface_language_hint'=>['ru'=>'На этом языке будут отображаться меню, кнопки, настройки и системные сообщения CMS.','kk'=>'CMS мәзірлері, батырмалары, баптаулары және жүйелік хабарламалары осы тілде көрсетіледі.','en'=>'CMS menus, buttons, settings, and system messages will use this language.'],
'choose_theme'=>['ru'=>'Тема интерфейса','kk'=>'Интерфейс тақырыбы','en'=>'Interface theme'],
'choose_theme_hint'=>['ru'=>'Выберите светлое или тёмное оформление. Тема применяется сразу.','kk'=>'Жарық немесе қараңғы көріністі таңдаңыз. Тақырып бірден қолданылады.','en'=>'Choose a light or dark appearance. The theme is applied immediately.'],
'light_theme_hint'=>['ru'=>'Светлый фон и высокая читаемость днём','kk'=>'Жарық фон және күндіз жоғары оқылымдылық','en'=>'Bright background and strong daytime readability'],
'dark_theme_hint'=>['ru'=>'Комфортное оформление для работы вечером','kk'=>'Кешкі жұмысқа ыңғайлы көрініс','en'=>'Comfortable appearance for evening work'],
'content_language_setup_hint'=>['ru'=>'Выберите, будет ли проект одноязычным или мультиязычным.','kk'=>'Жобаның біртілді немесе көптілді болуын таңдаңыз.','en'=>'Choose whether the project will use one language or multiple languages.'],
'single_language_mode'=>['ru'=>'Один язык','kk'=>'Бір тіл','en'=>'Single language'],
'multilingual_mode'=>['ru'=>'Несколько языков','kk'=>'Бірнеше тіл','en'=>'Multiple languages'],
'single_language_mode_hint'=>['ru'=>'CMS использует один выбранный язык контента без языковых спойлеров.','kk'=>'CMS тіл спойлерлерінсіз бір таңдалған контент тілін пайдаланады.','en'=>'The CMS uses one selected content language without language accordions.'],
'multilingual_mode_hint'=>['ru'=>'Для каждого выбранного языка появятся отдельные блоки в записях, коллекциях, разделах, формах и полях.','kk'=>'Әр таңдалған тіл үшін жазбаларда, коллекцияларда, бөлімдерде, формаларда және өрістерде бөлек блоктар пайда болады.','en'=>'Each selected language gets separate blocks in entries, collections, sections, forms, and fields.'],
'choose_content_languages'=>['ru'=>'Выберите языки проекта','kk'=>'Жоба тілдерін таңдаңыз','en'=>'Choose project languages'],
'choose_at_least_one_language'=>['ru'=>'Выберите хотя бы один язык контента.','kk'=>'Кемінде бір контент тілін таңдаңыз.','en'=>'Select at least one content language.'],
'finish_setup'=>['ru'=>'Завершить настройку','kk'=>'Баптауды аяқтау','en'=>'Finish setup'],
'setup_summary'=>['ru'=>'Проверьте настройки и создайте конфигурацию CMS.','kk'=>'Баптауларды тексеріп, CMS конфигурациясын жасаңыз.','en'=>'Review the settings and create the CMS configuration.'],
'setup_db'=>['ru'=>'Выбор базы данных','kk'=>'Дерекқор таңдау','en'=>'Database setup'],'setup_db_hint'=>['ru'=>'Выберите, где CMS будет хранить данные. SQLite выбран по умолчанию.','kk'=>'CMS деректерді қайда сақтайтынын таңдаңыз. SQLite әдепкі бойынша таңдалған.','en'=>'Choose where CMS stores data. SQLite is selected by default.'],'sqlite'=>['ru'=>'SQLite','kk'=>'SQLite','en'=>'SQLite'],'mysql'=>['ru'=>'MySQL','kk'=>'MySQL','en'=>'MySQL'],'sqlite_hint'=>['ru'=>'Простой режим: файл базы будет создан автоматически.','kk'=>'Қарапайым режим: база файлы автоматты жасалады.','en'=>'Simple mode: database file is created automatically.'],'mysql_hint'=>['ru'=>'Для MySQL база должна быть создана заранее.','kk'=>'MySQL үшін база алдын ала жасалуы керек.','en'=>'For MySQL the database must already exist.'],'host'=>['ru'=>'Хост','kk'=>'Хост','en'=>'Host'],'database'=>['ru'=>'База данных','kk'=>'Дерекқор','en'=>'Database'],'user_db'=>['ru'=>'Пользователь БД','kk'=>'БД пайдаланушысы','en'=>'DB user'],'db_password'=>['ru'=>'Пароль БД','kk'=>'БД құпиясөзі','en'=>'DB password'],'continue'=>['ru'=>'Продолжить','kk'=>'Жалғастыру','en'=>'Continue'],'db_saved'=>['ru'=>'Настройка базы сохранена','kk'=>'База баптауы сақталды','en'=>'Database settings saved'],'db_reset'=>['ru'=>'Сбросить выбор базы','kk'=>'База таңдауын тастау','en'=>'Reset database choice'],'db_reset_hint'=>['ru'=>'Будет удалён только config.json. SQLite-файл и загруженные файлы не удаляются.','kk'=>'Тек config.json өшіріледі. SQLite файлы және жүктелген файлдар өшірілмейді.','en'=>'Only config.json will be removed. SQLite file and uploads are not deleted.'],'db_reset_q'=>['ru'=>'Сбросить выбор базы данных?','kk'=>'Дерекқор таңдауын тастайсыз ба?','en'=>'Reset database choice?'],'current_db'=>['ru'=>'Текущая база','kk'=>'Қазіргі база','en'=>'Current database'],'first_user'=>['ru'=>'Первый пользователь','kk'=>'Бірінші пайдаланушы','en'=>'First user'],'first_user_hint'=>['ru'=>'Создайте администратора, который первым войдёт в CMS.','kk'=>'CMS жүйесіне бірінші кіретін әкімшіні жасаңыз.','en'=>'Create the administrator who will sign in first.'],
'ui_settings'=>['ru'=>'Настройки интерфейса','kk'=>'Интерфейс баптаулары','en'=>'Interface settings'],
'settings_navigation'=>['ru'=>'Разделы настроек','kk'=>'Баптаулар бөлімдері','en'=>'Settings sections'],
'settings_section_interface'=>['ru'=>'Интерфейс','kk'=>'Интерфейс','en'=>'Interface'],
'settings_section_interface_hint'=>['ru'=>'Язык и внешний вид административной панели.','kk'=>'Әкімшілік панельдің тілі мен сыртқы көрінісі.','en'=>'Language and appearance of the administration panel.'],
'settings_section_content'=>['ru'=>'Проект и контент','kk'=>'Жоба және контент','en'=>'Project and content'],
'settings_section_content_hint'=>['ru'=>'Активный проект и языки, в которых редактируется контент.','kk'=>'Белсенді жоба және контент өңделетін тілдер.','en'=>'The active project and the languages used to edit content.'],
'settings_section_access'=>['ru'=>'Пользователи и API','kk'=>'Пайдаланушылар және API','en'=>'Users and API'],
'settings_section_access_hint'=>['ru'=>'Пользователи, API Explorer и управление ключами доступа.','kk'=>'Пайдаланушылар, API Explorer және қолжетімділік кілттерін басқару.','en'=>'Users, API Explorer, and access key management.'],
'settings_section_control'=>['ru'=>'Интеграции и контроль','kk'=>'Интеграциялар және бақылау','en'=>'Integrations and monitoring'],
'settings_section_control_hint'=>['ru'=>'Telegram-уведомления, журнал действий и диагностика установки.','kk'=>'Telegram хабарландырулары, әрекеттер журналы және орнату диагностикасы.','en'=>'Telegram notifications, the audit log, and installation diagnostics.'],
'settings_section_data'=>['ru'=>'Данные и безопасность','kk'=>'Деректер және қауіпсіздік','en'=>'Data and security'],
'settings_section_data_hint'=>['ru'=>'Резервные копии проекта и правила доступа CORS.','kk'=>'Жобаның сақтық көшірмелері және CORS қолжетімділік ережелері.','en'=>'Project backups and CORS access rules.'],
'settings_section_system'=>['ru'=>'Система','kk'=>'Жүйе','en'=>'System'],
'settings_section_system_hint'=>['ru'=>'База данных, режим отладки и обслуживание данных CMS.','kk'=>'Дерекқор, жөндеу режимі және CMS деректеріне қызмет көрсету.','en'=>'Database, debug mode, and CMS data maintenance.'],
'about_project'=>['ru'=>'О проекте','kk'=>'Жоба туралы','en'=>'About the project'],
'about_project_hint'=>['ru'=>'Автор, исходный код, используемые технологии и благодарности их создателям.','kk'=>'Автор, бастапқы код, қолданылатын технологиялар және олардың жасаушыларына алғыс.','en'=>'Author, source code, technologies used, and acknowledgements to their creators.'],
'author'=>['ru'=>'Автор','kk'=>'Автор','en'=>'Author'],
'repository'=>['ru'=>'Репозиторий','kk'=>'Репозиторий','en'=>'Repository'],
'open_repository'=>['ru'=>'Открыть GitHub','kk'=>'GitHub ашу','en'=>'Open GitHub'],
'technologies_used'=>['ru'=>'Технологии и благодарности','kk'=>'Технологиялар және алғыс','en'=>'Technologies and acknowledgements'],
'technologies_used_hint'=>['ru'=>'Ниже перечислены основные технологии, библиотеки, расширения и Web API, на которых работает Mini Headless CMS.','kk'=>'Төменде Mini Headless CMS жұмысында қолданылатын негізгі технологиялар, кітапханалар, кеңейтімдер және Web API көрсетілген.','en'=>'The main technologies, libraries, extensions, and Web APIs used by Mini Headless CMS are listed below.'],
'technology_backend'=>['ru'=>'Сервер и безопасность','kk'=>'Сервер және қауіпсіздік','en'=>'Server and security'],
'technology_data'=>['ru'=>'Данные и базы','kk'=>'Деректер және дерекқорлар','en'=>'Data and databases'],
'technology_frontend'=>['ru'=>'Интерфейс','kk'=>'Интерфейс','en'=>'Interface'],
'technology_browser'=>['ru'=>'JavaScript и Web API','kk'=>'JavaScript және Web API','en'=>'JavaScript and Web APIs'],
'technology_integrations'=>['ru'=>'Интеграции и протоколы','kk'=>'Интеграциялар және хаттамалар','en'=>'Integrations and protocols'],
'technology_notice'=>['ru'=>'Названия, товарные знаки и права на перечисленные технологии принадлежат их соответствующим авторам и правообладателям. Bootstrap и Bootstrap Icons используются на условиях лицензии MIT. Ссылки ведут на официальные сайты и документацию.','kk'=>'Көрсетілген технологиялардың атаулары, сауда белгілері және құқықтары олардың тиісті авторлары мен құқық иелеріне тиесілі. Bootstrap және Bootstrap Icons MIT лицензиясы бойынша қолданылады. Сілтемелер ресми сайттар мен құжаттамаға апарады.','en'=>'Names, trademarks, and rights to the listed technologies belong to their respective authors and owners. Bootstrap and Bootstrap Icons are used under the MIT License. Links point to official websites and documentation.'],
'collection_actions'=>['ru'=>'Убрать или удалить коллекцию','kk'=>'Коллекцияны алып тастау немесе жою','en'=>'Remove or delete collection'],
'collection_actions_hint'=>['ru'=>'Выберите действие для коллекции «%s» в разделе «%s».','kk'=>'«%s» коллекциясы үшін «%s» бөлімінде әрекетті таңдаңыз.','en'=>'Choose what to do with collection “%s” in section “%s”.'],
'remove_from_section_only'=>['ru'=>'Убрать только из этого раздела','kk'=>'Тек осы бөлімнен алып тастау','en'=>'Remove only from this section'],
'remove_from_section_only_hint'=>['ru'=>'Удалится только связь с текущим разделом. Коллекция, её поля, записи, файлы и связи с другими разделами останутся.','kk'=>'Тек ағымдағы бөліммен байланыс жойылады. Коллекция, оның өрістері, жазбалары, файлдары және басқа бөлімдермен байланыстары сақталады.','en'=>'Only the link to the current section will be removed. The collection, fields, entries, files, and links to other sections will remain.'],
'delete_collection_everywhere'=>['ru'=>'Удалить коллекцию полностью','kk'=>'Коллекцияны толық жою','en'=>'Delete collection completely'],
'delete_collection_everywhere_hint'=>['ru'=>'Коллекция будет удалена из всех разделов вместе с её полями и записями. После выбора откроется ещё одно окно с итоговой проверкой.','kk'=>'Коллекция барлық бөлімдерден өрістерімен және жазбаларымен бірге жойылады. Таңдағаннан кейін соңғы тексеру үшін тағы бір терезе ашылады.','en'=>'The collection will be removed from every section together with its fields and entries. A second confirmation dialog will open before deletion.'],
'user_actions'=>['ru'=>'Убрать из проекта или удалить','kk'=>'Жобадан алып тастау немесе жою','en'=>'Remove from project or delete'],
'user_actions_hint'=>['ru'=>'Выберите, нужно убрать пользователю доступ только к текущему проекту или удалить его учётную запись полностью.','kk'=>'Пайдаланушының тек ағымдағы жобаға қолжетімділігін алып тастауды немесе оның тіркелгісін толық жоюды таңдаңыз.','en'=>'Choose whether to remove access only to the current project or delete the user account completely.'],
'remove_user_from_project_only'=>['ru'=>'Убрать только из текущего проекта','kk'=>'Тек ағымдағы жобадан алып тастау','en'=>'Remove only from current project'],
'remove_user_from_project_only_hint'=>['ru'=>'Пользователь останется в CMS и сохранит доступ к другим проектам. Удалится только его доступ к текущему проекту.','kk'=>'Пайдаланушы CMS ішінде қалады және басқа жобаларға қолжетімділігін сақтайды. Тек ағымдағы жобаға қолжетімділігі жойылады.','en'=>'The user will remain in the CMS and keep access to other projects. Only access to the current project will be removed.'],
'delete_user_everywhere'=>['ru'=>'Удалить пользователя полностью','kk'=>'Пайдаланушыны толық жою','en'=>'Delete user completely'],
'delete_user_everywhere_hint'=>['ru'=>'Учётная запись и доступ ко всем проектам будут удалены. После выбора откроется дополнительное подтверждение.','kk'=>'Тіркелгі және барлық жобаларға қолжетімділік жойылады. Таңдағаннан кейін қосымша растау ашылады.','en'=>'The account and access to every project will be deleted. A second confirmation will open before deletion.'],
'user_removed_from_project'=>['ru'=>'Пользователь убран из текущего проекта','kk'=>'Пайдаланушы ағымдағы жобадан алынды','en'=>'User removed from the current project'],
'project_access_remove_hint'=>['ru'=>'Значение «Нет доступа» убирает пользователя только из выбранного проекта и не удаляет его учётную запись.','kk'=>'«Қолжетімсіз» мәні пайдаланушыны тек таңдалған жобадан алып тастайды және оның тіркелгісін жоймайды.','en'=>'“No access” removes the user only from the selected project and does not delete the account.'],
'language_hint'=>['ru'=>'Язык админ-панели сохраняется в cookie','kk'=>'Админ панель тілі cookie ішінде сақталады','en'=>'Admin language is saved in a cookie'],'theme_hint'=>['ru'=>'Тёмная тема включается iOS-переключателем','kk'=>'Қараңғы тақырып iOS ауыстырғышымен қосылады','en'=>'Dark mode is controlled by an iOS toggle'],'dark_mode'=>['ru'=>'Тёмный режим','kk'=>'Қараңғы режим','en'=>'Dark mode'],'current_theme'=>['ru'=>'Текущая тема','kk'=>'Қазіргі тақырып','en'=>'Current theme'],
'debug_mode'=>['ru'=>'Режим отладки','kk'=>'Жөндеу режимі','en'=>'Debug mode'],'debug_mode_hint'=>['ru'=>'Показывает подробные ошибки, предупреждения и stack trace средствами PHP. Используйте только во время разработки.','kk'=>'PHP құралдары арқылы толық қателерді, ескертулерді және stack trace көрсетеді. Тек әзірлеу кезінде пайдаланыңыз.','en'=>'Shows detailed errors, warnings, and stack traces using PHP itself. Enable only during development.'],'debug_mode_warning'=>['ru'=>'Не оставляйте режим отладки включённым на рабочем сайте: сообщения могут раскрыть пути к файлам, настройки и другие технические данные.','kk'=>'Жұмыс істеп тұрған сайтта жөндеу режимін қосулы қалдырмаңыз: хабарламалар файл жолдарын, баптауларды және басқа техникалық деректерді көрсетуі мүмкін.','en'=>'Do not leave debug mode enabled on a production site: messages may expose file paths, configuration, and other technical details.'],'debug_mode_saved'=>['ru'=>'Режим отладки сохранён','kk'=>'Жөндеу режимі сақталды','en'=>'Debug mode saved'],
'content_settings'=>['ru'=>'Настройки контента','kk'=>'Контент баптаулары','en'=>'Content settings'],'content_i18n_toggle'=>['ru'=>'Включить интернационализацию','kk'=>'Интернационализацияны қосу','en'=>'Enable internationalization'],'content_i18n_hint2'=>['ru'=>'Интернационализация применяется к значениям полей Text и Textarea внутри записей коллекций, а также к названию формы, названиям её полей и сообщению после успешной отправки. Поле «Текст без перевода» и остальные типы данных остаются общими.','kk'=>'Интернационализация коллекция жазбаларындағы Text және Textarea өрістерінің мәндеріне, сондай-ақ форма атауына, оның өрістерінің атауларына және сәтті жіберілгеннен кейінгі хабарламаға қолданылады. «Аударылмайтын мәтін» өрісі және қалған дерек түрлері ортақ болып қалады.','en'=>'Internationalization applies to Text and Textarea values inside collection entries, as well as the form name, its field labels, and the success message. Global text and the remaining data types stay shared across languages.'],'content_i18n_off_hint'=>['ru'=>'Выключено: выберите один основной язык контента. Все данные будут редактироваться без переводов.','kk'=>'Өшірулі: контенттің бір негізгі тілін таңдаңыз. Барлық деректер аудармасыз өңделеді.','en'=>'Disabled: choose one primary content language. All data is edited without translations.'],'content_i18n_on_hint'=>['ru'=>'Включено: отметьте нужные языки. Язык по умолчанию не требуется — поля каждого языка показываются отдельными спойлерами.','kk'=>'Қосулы: қажетті тілдерді белгілеңіз. Әдепкі тіл қажет емес — әр тілдің өрістері жеке спойлерлерде көрсетіледі.','en'=>'Enabled: select the required languages. No default language is needed; each language is shown in its own accordion section.'],'content_languages'=>['ru'=>'Языки контента','kk'=>'Контент тілдері','en'=>'Content languages'],'content_languages_hint'=>['ru'=>'Выбранные языки появятся отдельными спойлерами в переводимых полях записей и в настройках формы.','kk'=>'Таңдалған тілдер аударылатын жазба өрістерінде және форма баптауларында бөлек спойлерлер ретінде көрсетіледі.','en'=>'Selected languages appear as separate accordions in translatable entry fields and form settings.'],'translation_fields_hint'=>['ru'=>'Заполните данные для каждого языка. Первый язык автоматически заполняет ещё пустые переводы, после чего их можно изменить отдельно.','kk'=>'Әр тіл үшін деректерді толтырыңыз. Бірінші тіл бос аудармаларды автоматты толтырады, кейін оларды жеке өзгертуге болады.','en'=>'Fill in every language. The first language automatically fills still-empty translations, which can then be edited separately.'],'translation_filled'=>['ru'=>'Заполнено','kk'=>'Толтырылған','en'=>'Filled'],'translation_autofilled'=>['ru'=>'Автозаполнено','kk'=>'Автотолтырылған','en'=>'Auto-filled'],'translation_translated'=>['ru'=>'Переведено','kk'=>'Аударылған','en'=>'Translated'],'translation_needs_review'=>['ru'=>'Требует проверки','kk'=>'Тексеруді қажет етеді','en'=>'Needs review'],'translation_confirmed_hint'=>['ru'=>'Статус меняется на «Переведено», когда пользователь изменяет или подтверждает текст этого языка.','kk'=>'Пайдаланушы осы тілдің мәтінін өзгерткенде немесе растағанда мәртебе «Аударылған» болып өзгереді.','en'=>'The status changes to “Translated” when the user edits or confirms that language.'],'confirm_translation'=>['ru'=>'Подтвердить перевод','kk'=>'Аударманы растау','en'=>'Confirm translation'],'placeholder'=>['ru'=>'Placeholder','kk'=>'Placeholder','en'=>'Placeholder'],'field_hint'=>['ru'=>'Подсказка поля','kk'=>'Өріс кеңесі','en'=>'Field hint'],'choice_labels'=>['ru'=>'Подписи вариантов','kk'=>'Нұсқа белгілері','en'=>'Choice labels'],'choice_labels_hint'=>['ru'=>'По одной строке в формате значение = подпись. Значение остаётся техническим и одинаковым для всех языков.','kk'=>'Әр жолға мән = белгі пішімінде жазыңыз. Мән техникалық болып қалады және барлық тілдер үшін бірдей.','en'=>'One line per value in the format value = label. The value remains technical and identical in every language.'],'available_languages'=>['ru'=>'Доступные языки','kk'=>'Қолжетімді тілдер','en'=>'Available languages'],'translated_languages'=>['ru'=>'Переведённые языки','kk'=>'Аударылған тілдер','en'=>'Translated languages'],'form_i18n_hint'=>['ru'=>'Название формы и сообщение после успешной отправки хранятся отдельно для каждого языка. Описание, технический slug и остальные настройки формы остаются общими.','kk'=>'Форма атауы мен сәтті жіберілгеннен кейінгі хабарлама әр тіл үшін бөлек сақталады. Сипаттама, техникалық slug және қалған форма баптаулары ортақ қалады.','en'=>'The form name and success message are stored separately for every language. The description, technical slug, and remaining form settings stay shared.'],'field_i18n_hint'=>['ru'=>'Ключ, тип данных, обязательность и правила валидации остаются общими. Название поля хранится отдельно для каждого языка.','kk'=>'Кілт, дерек түрі, міндеттілік және валидация ережелері ортақ қалады. Өріс атауы әр тіл үшін бөлек сақталады.','en'=>'The key, data type, required flag, and validation rules stay shared. The field label is stored separately for every language.'],'form_field_translations'=>['ru'=>'Переводы названия поля','kk'=>'Өріс атауының аудармалары','en'=>'Field label translations'],'entry_i18n_hint'=>['ru'=>'Переводятся только поля типов Text и Textarea. Поле «Текст без перевода», служебное название, slug, статус и остальные типы данных являются общими для всех языков.','kk'=>'Тек Text және Textarea түріндегі өрістер аударылады. «Аударылмайтын мәтін» өрісі, қызметтік атау, slug, мәртебе және қалған деректер барлық тілдерге ортақ.','en'=>'Only Text and Textarea fields are translated. Global text, the internal title, slug, status, and all other data are shared across languages.'],'content_i18n_saved'=>['ru'=>'Настройки интернационализации сохранены','kk'=>'Интернационализация баптаулары сақталды','en'=>'Internationalization settings saved'],'enabled'=>['ru'=>'Включено','kk'=>'Қосулы','en'=>'Enabled'],'disabled'=>['ru'=>'Выключено','kk'=>'Өшірулі','en'=>'Disabled'],
'content_language'=>['ru'=>'Язык контента','kk'=>'Контент тілі','en'=>'Content language'],
'internationalization'=>['ru'=>'Интернационализация','kk'=>'Интернационализация','en'=>'Internationalization'],
'i18n_hint'=>['ru'=>'Заполните данные записи отдельно для каждого языка. API отдаёт нужный язык через параметр lang.', 'kk'=>'Жазба деректерін әр тіл үшін бөлек толтырыңыз. API қажетті тілді lang параметрі арқылы береді.', 'en'=>'Fill entry data separately for each language. API returns the requested language through the lang parameter.'],
'all_languages'=>['ru'=>'Все языки','kk'=>'Барлық тілдер','en'=>'All languages'],
'default_language'=>['ru'=>'Язык по умолчанию','kk'=>'Әдепкі тіл','en'=>'Default language']
,'developer'=>['ru'=>'Разработчик','kk'=>'Әзірлеуші','en'=>'Developer'],'viewer'=>['ru'=>'Наблюдатель','kk'=>'Көруші','en'=>'Viewer'],'api_private'=>['ru'=>'Этот API доступен только авторизованным пользователям','kk'=>'Бұл API тек авторизацияланған пайдаланушыларға қолжетімді','en'=>'This API is available only to authenticated users'],'too_many_attempts'=>['ru'=>'Слишком много попыток. Попробуй позже.','kk'=>'Әрекет тым көп. Кейінірек қайталап көріңіз.','en'=>'Too many attempts. Try again later.'],'password_latin'=>['ru'=>'Пароль должен быть от 10 до 72 символов. Можно использовать буквы, цифры и спецсимволы','kk'=>'Құпиясөз 10–72 таңба болуы керек. Әріптерді, сандарды және арнайы таңбаларды қолдануға болады','en'=>'Password must be 10–72 characters. Letters, numbers, and symbols are allowed'],'single'=>['ru'=>'Single','kk'=>'Single','en'=>'Single'],'multiple'=>['ru'=>'Multiple','kk'=>'Multiple','en'=>'Multiple'],'collection_type'=>['ru'=>'Тип коллекции','kk'=>'Коллекция түрі','en'=>'Collection type'],'collection_order'=>['ru'=>'Порядок коллекции','kk'=>'Коллекция реті','en'=>'Collection order'],'collections_hint'=>['ru'=>'Выберите коллекцию в контекстной панели. На планшете и телефоне список открывается в боковой панели.','kk'=>'Контекстік панельден коллекцияны таңдаңыз. Планшет пен телефонда тізім бүйірлік панельде ашылады.','en'=>'Choose a collection from the contextual panel. On tablet and mobile, the list opens in an offcanvas panel.'],'single_entry_limit'=>['ru'=>'Single-коллекция может иметь только одну запись','kk'=>'Single коллекцияда тек бір жазба болуы мүмкін','en'=>'Single collection can have only one entry'],'collection_type_locked'=>['ru'=>'Тип коллекции нельзя менять после создания','kk'=>'Коллекция түрін жасалғаннан кейін өзгертуге болмайды','en'=>'Collection type cannot be changed after creation'],
'relation'=>['ru'=>'Связь','kk'=>'Байланыс','en'=>'Relation'],'nested_relation'=>['ru'=>'Связь с вложенной коллекцией','kk'=>'Кірістірілген коллекциямен байланыс','en'=>'Nested collection relation'],'target_collection'=>['ru'=>'Связанная коллекция','kk'=>'Байланысқан коллекция','en'=>'Target collection'],'target_nested_collection'=>['ru'=>'Связанная вложенная коллекция','kk'=>'Байланысқан кірістірілген коллекция','en'=>'Target nested collection'],'relation_mode'=>['ru'=>'Тип связи','kk'=>'Байланыс түрі','en'=>'Relation mode'],'relation_single'=>['ru'=>'Одна запись','kk'=>'Бір жазба','en'=>'Single entry'],'relation_multiple'=>['ru'=>'Несколько записей','kk'=>'Бірнеше жазба','en'=>'Multiple entries'],'select_entry'=>['ru'=>'Выбери запись','kk'=>'Жазбаны таңдаңыз','en'=>'Select entry'],'no_relation_entries'=>['ru'=>'В связанной коллекции пока нет записей','kk'=>'Байланысқан коллекцияда әзірге жазба жоқ','en'=>'Target collection has no entries yet'],'no_nested_relation_entries'=>['ru'=>'Во вложенной коллекции пока нет записей','kk'=>'Кірістірілген коллекцияда әзірге жазба жоқ','en'=>'Target nested collection has no entries yet'],'relation_target_required'=>['ru'=>'Для поля relation нужно выбрать связанную коллекцию','kk'=>'Relation өрісі үшін байланысқан коллекцияны таңдау керек','en'=>'Relation field requires a target collection'],'nested_relation_target_required'=>['ru'=>'Нужно выбрать вложенную коллекцию внутри того же родителя','kk'=>'Сол бір ата-ананың ішіндегі кірістірілген коллекцияны таңдау керек','en'=>'Choose a nested collection inside the same parent'],
'nested_relation_parent_only'=>['ru'=>'Показываются только записи этой вложенной коллекции внутри текущей родительской записи.','kk'=>'Тек ағымдағы ата-ана жазбасының ішіндегі осы кірістірілген коллекция жазбалары көрсетіледі.','en'=>'Only entries from this nested collection inside the current parent entry are shown.'],
'nested_relation_save_parent_first'=>['ru'=>'Сначала сохраните родительскую запись. После этого здесь появятся записи её вложенной коллекции.','kk'=>'Алдымен ата-ана жазбасын сақтаңыз. Содан кейін оның кірістірілген коллекция жазбалары осында көрсетіледі.','en'=>'Save the parent entry first. Its nested collection entries will appear here afterwards.'],'relation_invalid_entry'=>['ru'=>'Связанная запись не принадлежит выбранной коллекции','kk'=>'Байланысқан жазба таңдалған коллекцияға тиесілі емес','en'=>'Related entry does not belong to the selected collection'],'populate'=>['ru'=>'Раскрывать связи','kk'=>'Байланыстарды ашу','en'=>'Populate relations'],'related_collections'=>['ru'=>'Связанные коллекции','kk'=>'Байланысқан коллекциялар','en'=>'Related collections'],'related_collections_hint'=>['ru'=>'Уникальные глобальные коллекции, используемые полями типа «Связь».','kk'=>'«Байланыс» түріндегі өрістер қолданатын бірегей жаһандық коллекциялар.','en'=>'Unique global collections used by relation fields.'],'related_nested_collections'=>['ru'=>'Связанные вложенные коллекции','kk'=>'Байланысқан кірістірілген коллекциялар','en'=>'Related nested collections'],'related_nested_collections_hint'=>['ru'=>'Уникальные вложенные коллекции, используемые полями типа «Связь с вложенной коллекцией».','kk'=>'«Кірістірілген коллекциямен байланыс» өрістері қолданатын бірегей кірістірілген коллекциялар.','en'=>'Unique nested collections used by nested relation fields.'],
'nested_collections'=>['ru'=>'Вложенные коллекции','kk'=>'Кірістірілген коллекциялар','en'=>'Nested collections'],'nested_collections_hint'=>['ru'=>'Локальные коллекции принадлежат родителю, скрыты из общего списка и удаляются вместе с ним.','kk'=>'Жергілікті коллекциялар ата-анаға тиесілі, жалпы тізімде жасырылған және онымен бірге жойылады.','en'=>'Local collections belong to their parent, stay hidden from the global list, and are deleted with it.'],'new_nested_collection'=>['ru'=>'Создать вложенную коллекцию','kk'=>'Кірістірілген коллекция жасау','en'=>'Create nested collection'],'nested_collection'=>['ru'=>'Вложенная коллекция','kk'=>'Кірістірілген коллекция','en'=>'Nested collection'],'parent_collection'=>['ru'=>'Родительская коллекция','kk'=>'Ата-ана коллекция','en'=>'Parent collection'],'parent_entry'=>['ru'=>'Родительская запись','kk'=>'Ата-ана жазба','en'=>'Parent entry'],'choose_parent_entry'=>['ru'=>'Выберите родительскую запись','kk'=>'Ата-ана жазбаны таңдаңыз','en'=>'Choose a parent entry'],'choose_parent_entry_hint'=>['ru'=>'Эта вложенная коллекция хранит отдельные записи для каждой записи родителя.','kk'=>'Бұл кірістірілген коллекция әр ата-ана жазбасы үшін бөлек жазбаларды сақтайды.','en'=>'This nested collection stores separate records for every parent entry.'],'nested_requires_parent_entry'=>['ru'=>'Сначала создайте или выберите родительскую запись.','kk'=>'Алдымен ата-ана жазбасын жасаңыз немесе таңдаңыз.','en'=>'Create or choose a parent entry first.'],'nested_depth_limit'=>['ru'=>'Разрешён только один уровень вложенных коллекций.','kk'=>'Кірістірілген коллекциялардың тек бір деңгейіне рұқсат етіледі.','en'=>'Only one level of nested collections is allowed.'],'nested_collection_created'=>['ru'=>'Вложенная коллекция создана','kk'=>'Кірістірілген коллекция жасалды','en'=>'Nested collection created'],'nested_entries'=>['ru'=>'Вложенные записи','kk'=>'Кірістірілген жазбалар','en'=>'Nested entries'],
'projects'=>['ru'=>'Проекты','kk'=>'Жобалар','en'=>'Projects'],
'project'=>['ru'=>'Проект','kk'=>'Жоба','en'=>'Project'],
'new_project'=>['ru'=>'Новый проект','kk'=>'Жаңа жоба','en'=>'New project'],
'edit_project'=>['ru'=>'Редактировать проект','kk'=>'Жобаны өзгерту','en'=>'Edit project'],
'delete_project'=>['ru'=>'Удалить проект','kk'=>'Жобаны жою','en'=>'Delete project'],
'delete_project_q'=>['ru'=>'Удалить проект вместе с его коллекциями, разделами контента и записями?','kk'=>'Жобаны коллекцияларымен, контент бөлімдерімен және жазбаларымен бірге жоясыз ба?','en'=>'Delete the project with its collections, content sections, and entries?'],
'project_saved'=>['ru'=>'Проект сохранён','kk'=>'Жоба сақталды','en'=>'Project saved'],
'project_deleted'=>['ru'=>'Проект удалён','kk'=>'Жоба жойылды','en'=>'Project deleted'],
'project_switched'=>['ru'=>'Проект переключён','kk'=>'Жоба ауыстырылды','en'=>'Project switched'],
'projects_hint'=>['ru'=>'Workspace для отдельной CMS внутри одной базы данных','kk'=>'Бір дерекқор ішіндегі жеке CMS workspace','en'=>'Workspace for a separate CMS inside one database'],
'cannot_delete_last_project'=>['ru'=>'Нельзя удалить последний проект','kk'=>'Соңғы жобаны жоюға болмайды','en'=>'Cannot delete the last project'],
'cannot_delete_active_project'=>['ru'=>'Сначала переключитесь на другой проект','kk'=>'Алдымен басқа жобаға ауысыңыз','en'=>'Switch to another project first'],
'manage_collections'=>['ru'=>'Управление коллекциями','kk'=>'Коллекцияларды басқару','en'=>'Manage collections'],'save_collections'=>['ru'=>'Сохранить коллекции','kk'=>'Коллекцияларды сақтау','en'=>'Save collections'],'selected_collections'=>['ru'=>'Выбранные коллекции','kk'=>'Таңдалған коллекциялар','en'=>'Selected collections'],'all_collections'=>['ru'=>'Все коллекции','kk'=>'Барлық коллекциялар','en'=>'All collections'],'go_to_collection'=>['ru'=>'Перейти в коллекцию','kk'=>'Коллекцияға өту','en'=>'Go to collection'],'search'=>['ru'=>'Поиск','kk'=>'Іздеу','en'=>'Search'],'reset'=>['ru'=>'Сбросить','kk'=>'Тазарту','en'=>'Reset'],'no_results'=>['ru'=>'Ничего не найдено','kk'=>'Ештеңе табылмады','en'=>'No results'],'sort_asc'=>['ru'=>'Сортировать по возрастанию','kk'=>'Өсу ретімен сұрыптау','en'=>'Sort ascending'],'sort_desc'=>['ru'=>'Сортировать по убыванию','kk'=>'Кему ретімен сұрыптау','en'=>'Sort descending'],'sort_asc_short'=>['ru'=>'По возрастанию','kk'=>'Өсу бойынша','en'=>'Ascending'],'sort_desc_short'=>['ru'=>'По убыванию','kk'=>'Кему бойынша','en'=>'Descending'],'sort_by'=>['ru'=>'Сортировка','kk'=>'Сұрыптау','en'=>'Sort by'],'collections_found'=>['ru'=>'Найдено коллекций: %d','kk'=>'Табылған коллекциялар: %d','en'=>'Collections found: %d'],
'forms_found'=>['ru'=>'Найдено форм: %d','kk'=>'Табылған формалар: %d','en'=>'Forms found: %d'],
'submissions_found'=>['ru'=>'Найдено заявок: %d','kk'=>'Табылған өтінімдер: %d','en'=>'Submissions found: %d'],
'collection_preset'=>['ru'=>'Пресет коллекции','kk'=>'Коллекция пресеті','en'=>'Collection preset'],'preset_blank'=>['ru'=>'Пустая','kk'=>'Бос','en'=>'Blank'],'preset_page'=>['ru'=>'Страница','kk'=>'Бет','en'=>'Page'],'preset_blog'=>['ru'=>'Блог','kk'=>'Блог','en'=>'Blog'],'preset_product'=>['ru'=>'Товар','kk'=>'Тауар','en'=>'Product'],'preset_faq'=>['ru'=>'FAQ','kk'=>'FAQ','en'=>'FAQ'],'preset_contact'=>['ru'=>'Контакты','kk'=>'Байланыс','en'=>'Contacts'],
'field_preset'=>['ru'=>'Быстрое поле','kk'=>'Жылдам өріс','en'=>'Quick field'],'field_preset_hint'=>['ru'=>'Выбери пресет, чтобы быстро заполнить label/key/type','kk'=>'Label/key/type тез толтыру үшін пресет таңдаңыз','en'=>'Choose a preset to quickly fill label/key/type'],'custom'=>['ru'=>'Своё','kk'=>'Өзім','en'=>'Custom'],
'clone_collection'=>['ru'=>'Клонировать коллекцию','kk'=>'Коллекцияны клондау','en'=>'Clone collection'],'collection_cloned'=>['ru'=>'Коллекция склонирована','kk'=>'Коллекция клонданды','en'=>'Collection cloned'],'export_schema'=>['ru'=>'Экспорт схемы','kk'=>'Схеманы экспорттау','en'=>'Export schema'],'import_schema'=>['ru'=>'Импорт схемы','kk'=>'Схеманы импорттау','en'=>'Import schema'],'schema_imported'=>['ru'=>'Схема импортирована','kk'=>'Схема импортталды','en'=>'Schema imported'],'invalid_schema'=>['ru'=>'Некорректная схема','kk'=>'Схема дұрыс емес','en'=>'Invalid schema'],'relation_import_warning'=>['ru'=>'Некоторые relation-поля были импортированы как text, потому что связанная коллекция не найдена.','kk'=>'Кейбір relation өрістері text ретінде импортталды, себебі байланысқан коллекция табылмады.','en'=>'Some relation fields were imported as text because the target collection was not found.'],'field_schema_locked'=>['ru'=>'Key, type и настройки relation заблокированы после создания поля.','kk'=>'Өріс жасалғаннан кейін key, type және relation баптаулары бұғатталады.','en'=>'Key, type, and relation settings are locked after field creation.'],
'required_missing'=>['ru'=>'Заполните обязательное поле','kk'=>'Міндетті өрісті толтырыңыз','en'=>'Fill required field'],'relation_auto_all'=>['ru'=>'Автоматически всегда показывать все','kk'=>'Барлығын әрқашан автоматты түрде көрсету','en'=>'Always show all automatically'],'relation_auto_all_hint'=>['ru'=>'Включено по умолчанию. Будут использоваться все текущие и новые записи связанной коллекции. Выключите, чтобы выбрать записи вручную.','kk'=>'Әдепкі бойынша қосулы. Байланысқан коллекцияның барлық қазіргі және жаңа жазбалары қолданылады. Қолмен таңдау үшін өшіріңіз.','en'=>'Enabled by default. All current and future entries from the related collection are used. Turn it off to choose entries manually.'],'relation_order_hint'=>['ru'=>'При ручном выборе отмеченные связи идут первыми. Кнопками вверх/вниз можно менять порядок.','kk'=>'Қолмен таңдағанда белгіленген байланыстар жоғары тұрады. Жоғары/төмен батырмаларымен ретін өзгертуге болады.','en'=>'With manual selection, selected relations stay first. Use up/down buttons to change order.'],'move_up'=>['ru'=>'Выше','kk'=>'Жоғары','en'=>'Move up'],'move_down'=>['ru'=>'Ниже','kk'=>'Төмен','en'=>'Move down'],

'content_sections'=>['ru'=>'Разделы контента','kk'=>'Контент бөлімдері','en'=>'Content sections'],
'content_nav'=>['ru'=>'Контент','kk'=>'Контент','en'=>'Content'],
'without_section'=>['ru'=>'Без раздела','kk'=>'Бөлімсіз','en'=>'Without a section'],
'section_filter'=>['ru'=>'Раздел','kk'=>'Бөлім','en'=>'Section'],
'type_all'=>['ru'=>'Тип: все','kk'=>'Түрі: барлығы','en'=>'Type: all'],
'section_all'=>['ru'=>'Раздел: все','kk'=>'Бөлім: барлығы','en'=>'Section: all'],
'add_collection'=>['ru'=>'Добавить коллекцию','kk'=>'Коллекция қосу','en'=>'Add collection'],
'add_existing_collection'=>['ru'=>'Добавить существующую коллекцию','kk'=>'Бар коллекцияны қосу','en'=>'Add an existing collection'],
'create_new_collection'=>['ru'=>'Создать новую коллекцию','kk'=>'Жаңа коллекция жасау','en'=>'Create a new collection'],
'add_to_section'=>['ru'=>'Добавить в раздел','kk'=>'Бөлімге қосу','en'=>'Add to section'],
'remove_from_section'=>['ru'=>'Убрать из раздела','kk'=>'Бөлімнен алып тастау','en'=>'Remove from section'],
'remove_from_section_question'=>['ru'=>'Убрать коллекцию «%s» из раздела «%s»?','kk'=>'«%s» коллекциясын «%s» бөлімінен алып тастау керек пе?','en'=>'Remove collection “%s” from section “%s”?'],
'remove_from_section_hint'=>['ru'=>'Коллекция, её записи, поля и файлы останутся в CMS. Будет удалена только связь с разделом.','kk'=>'Коллекция, оның жазбалары, өрістері және файлдары CMS ішінде қалады. Тек бөліммен байланыс жойылады.','en'=>'The collection, its entries, fields, and files will remain in the CMS. Only the section link will be removed.'],
'sections_used'=>['ru'=>'Разделы, в которых используется коллекция','kk'=>'Коллекция қолданылатын бөлімдер','en'=>'Sections using this collection'],
'section_optional'=>['ru'=>'Добавить в раздел: необязательно','kk'=>'Бөлімге қосу: міндетті емес','en'=>'Add to section: optional'],
'section_shortcut_hint'=>['ru'=>'Разделы работают как подборки или ярлыки. Одна коллекция может использоваться в нескольких разделах.','kk'=>'Бөлімдер топтама немесе жарлық ретінде жұмыс істейді. Бір коллекция бірнеше бөлімде қолданыла алады.','en'=>'Sections work like collections of shortcuts. One collection can be used in several sections.'],
'collection_independent_hint'=>['ru'=>'Коллекции — самостоятельные источники данных. Раздел создавать заранее не нужно.','kk'=>'Коллекциялар — дербес дереккөздер. Бөлімді алдын ала жасау қажет емес.','en'=>'Collections are independent data sources. You do not need to create a section first.'],
'section_collections'=>['ru'=>'Коллекции раздела','kk'=>'Бөлім коллекциялары','en'=>'Section collections'],
'entry_count'=>['ru'=>'Записей','kk'=>'Жазбалар','en'=>'Entries'],
'field_count'=>['ru'=>'Полей','kk'=>'Өрістер','en'=>'Fields'],
'section_count'=>['ru'=>'Связанных разделов','kk'=>'Байланысқан бөлімдер','en'=>'Linked sections'],
'collection_files_note'=>['ru'=>'Связанные файлы не удаляются автоматически и после удаления коллекции могут стать неиспользуемыми.','kk'=>'Байланысқан файлдар автоматты түрде жойылмайды және коллекция жойылғаннан кейін қолданылмай қалуы мүмкін.','en'=>'Referenced files are not deleted automatically and may become unused after the collection is deleted.'],
'collection_delete_irreversible'=>['ru'=>'Коллекция, её поля, записи и связи со всеми разделами будут удалены без возможности восстановления.','kk'=>'Коллекция, оның өрістері, жазбалары және барлық бөлімдермен байланыстары қалпына келтірусіз жойылады.','en'=>'The collection, its fields, entries, and links to all sections will be deleted permanently.'],
'no_available_collections'=>['ru'=>'Все коллекции проекта уже добавлены в этот раздел.','kk'=>'Жобаның барлық коллекциялары бұл бөлімге қосылған.','en'=>'All project collections are already in this section.'],
'collection_linked'=>['ru'=>'Коллекция добавлена в раздел','kk'=>'Коллекция бөлімге қосылды','en'=>'Collection added to section'],
'collection_unlinked'=>['ru'=>'Коллекция убрана из раздела','kk'=>'Коллекция бөлімнен алынды','en'=>'Collection removed from section'],
'open_entries'=>['ru'=>'Открыть записи','kk'=>'Жазбаларды ашу','en'=>'Open entries'],
'collection_settings'=>['ru'=>'Настройки коллекции','kk'=>'Коллекция баптаулары','en'=>'Collection settings'],
'drag_collection_to_section'=>['ru'=>'Коллекцию можно перетащить на раздел в панели навигации, чтобы создать связь.','kk'=>'Байланыс жасау үшін коллекцияны навигация панеліндегі бөлімге сүйреп апаруға болады.','en'=>'Drag a collection onto a section in the navigation panel to create a link.'],
'sort_name'=>['ru'=>'По названию','kk'=>'Атауы бойынша','en'=>'By name'],
'sort_updated'=>['ru'=>'По изменению','kk'=>'Өзгертілуі бойынша','en'=>'By updated date'],
'sort_entries'=>['ru'=>'По количеству записей','kk'=>'Жазбалар саны бойынша','en'=>'By entry count'],
'sort_manual'=>['ru'=>'Ручной порядок','kk'=>'Қолмен реттеу','en'=>'Manual order'],
'sort_collections_count'=>['ru'=>'По количеству коллекций','kk'=>'Коллекциялар саны бойынша','en'=>'By collection count'],
'all_access_modes'=>['ru'=>'Доступ: все','kk'=>'Қолжетімділік: барлығы','en'=>'Access: all'],
'sections_found'=>['ru'=>'Найдено разделов: %d','kk'=>'Табылған бөлімдер: %d','en'=>'Sections found: %d'],
'active_project'=>['ru'=>'Активный проект','kk'=>'Белсенді жоба','en'=>'Active project'],
'overview'=>['ru'=>'Обзор','kk'=>'Шолу','en'=>'Overview'],
'dashboard'=>['ru'=>'Панель проекта','kk'=>'Жоба панелі','en'=>'Project dashboard'],
'stat_collections'=>['ru'=>'Коллекции','kk'=>'Коллекциялар','en'=>'Collections'],
'stat_entries'=>['ru'=>'Записи','kk'=>'Жазбалар','en'=>'Entries'],
'stat_published'=>['ru'=>'Опубликовано','kk'=>'Жарияланған','en'=>'Published'],
'stat_files'=>['ru'=>'Файлы','kk'=>'Файлдар','en'=>'Files'],
'recent_entries'=>['ru'=>'Недавние записи','kk'=>'Соңғы жазбалар','en'=>'Recent entries'],
'favorite_collections'=>['ru'=>'Избранные коллекции','kk'=>'Таңдаулы коллекциялар','en'=>'Favorite collections'],
'favorite'=>['ru'=>'В избранное','kk'=>'Таңдаулыға','en'=>'Favorite'],
'unfavorite'=>['ru'=>'Убрать из избранного','kk'=>'Таңдаулыдан алу','en'=>'Remove favorite'],
'no_recent'=>['ru'=>'Недавних записей пока нет','kk'=>'Соңғы жазбалар жоқ','en'=>'No recent entries yet'],
'no_favorites'=>['ru'=>'Добавьте часто используемые коллекции в избранное','kk'=>'Жиі қолданылатын коллекцияларды таңдаулыға қосыңыз','en'=>'Add frequently used collections to favorites'],
'dashboard_pulse'=>['ru'=>'Пульс проекта','kk'=>'Жоба пульсі','en'=>'Project pulse'],
'current_date'=>['ru'=>'Дата','kk'=>'Күні','en'=>'Date'],
'current_time'=>['ru'=>'Время','kk'=>'Уақыты','en'=>'Time'],
'project_slug'=>['ru'=>'Slug проекта','kk'=>'Жоба slug-ы','en'=>'Project slug'],
'project_created_at'=>['ru'=>'Создан проект','kk'=>'Жоба жасалды','en'=>'Project created'],
'project_updated_at'=>['ru'=>'Обновлён проект','kk'=>'Жоба жаңартылды','en'=>'Project updated'],
'project_age'=>['ru'=>'Возраст проекта','kk'=>'Жоба жасы','en'=>'Project age'],
'quick_actions'=>['ru'=>'Быстрые действия','kk'=>'Жылдам әрекеттер','en'=>'Quick actions'],
'open_sections'=>['ru'=>'Открыть разделы','kk'=>'Бөлімдерді ашу','en'=>'Open sections'],
'open_forms'=>['ru'=>'Открыть формы','kk'=>'Формаларды ашу','en'=>'Open forms'],
'open_files'=>['ru'=>'Открыть файлы','kk'=>'Файлдарды ашу','en'=>'Open files'],
'open_settings'=>['ru'=>'Открыть настройки','kk'=>'Баптауларды ашу','en'=>'Open settings'],
'stat_sections'=>['ru'=>'Разделы','kk'=>'Бөлімдер','en'=>'Sections'],
'stat_forms'=>['ru'=>'Формы','kk'=>'Формалар','en'=>'Forms'],
'stat_submissions'=>['ru'=>'Заявки','kk'=>'Өтінімдер','en'=>'Submissions'],
'stat_storage'=>['ru'=>'Хранилище','kk'=>'Сақтау орны','en'=>'Storage'],
'content_health'=>['ru'=>'Состояние контента','kk'=>'Контент күйі','en'=>'Content health'],
'key_metrics'=>['ru'=>'Ключевые показатели','kk'=>'Негізгі көрсеткіштер','en'=>'Key metrics'],
'key_metrics_hint'=>['ru'=>'Краткая сводка по публикации, наполнению коллекций и обработке заявок.','kk'=>'Жариялау, коллекцияларды толтыру және өтінімдерді өңдеу бойынша қысқаша жиынтық.','en'=>'A compact summary of publishing, collection coverage, and submission handling.'],
'publish_rate'=>['ru'=>'Процент публикации','kk'=>'Жариялау пайызы','en'=>'Publish rate'],
'unpublished_entries'=>['ru'=>'Черновики','kk'=>'Жобалар','en'=>'Draft entries'],
'collections_with_entries'=>['ru'=>'Коллекции с записями','kk'=>'Жазбалары бар коллекциялар','en'=>'Collections with entries'],
'favorite_coverage'=>['ru'=>'Покрытие избранным','kk'=>'Таңдаулы қамтуы','en'=>'Favorite coverage'],
'structure_breakdown'=>['ru'=>'Структура проекта','kk'=>'Жоба құрылымы','en'=>'Project structure'],
'collection_types'=>['ru'=>'Типы коллекций','kk'=>'Коллекция түрлері','en'=>'Collection types'],
'access_breakdown'=>['ru'=>'Доступ к ресурсам','kk'=>'Ресурстарға қолжетімділік','en'=>'Resource access'],
'single_collections'=>['ru'=>'Single коллекции','kk'=>'Single коллекциялар','en'=>'Single collections'],
'multiple_collections'=>['ru'=>'Multiple коллекции','kk'=>'Multiple коллекциялар','en'=>'Multiple collections'],
'public_resources'=>['ru'=>'Публичные ресурсы','kk'=>'Жария ресурстар','en'=>'Public resources'],
'private_resources'=>['ru'=>'Приватные ресурсы','kk'=>'Жеке ресурстар','en'=>'Private resources'],
'active_forms_total'=>['ru'=>'Активные формы','kk'=>'Белсенді формалар','en'=>'Active forms'],
'inactive_forms_total'=>['ru'=>'Отключённые формы','kk'=>'Өшірілген формалар','en'=>'Inactive forms'],
'form_activity'=>['ru'=>'Активность форм','kk'=>'Формалар белсенділігі','en'=>'Form activity'],
'recent_activity'=>['ru'=>'Последняя активность','kk'=>'Соңғы белсенділік','en'=>'Recent activity'],
'no_activity'=>['ru'=>'Журнал действий пока пуст','kk'=>'Әрекеттер журналы әзірге бос','en'=>'The activity log is empty'],
'top_collections'=>['ru'=>'Топ коллекций','kk'=>'Үздік коллекциялар','en'=>'Top collections'],
'no_collections_yet'=>['ru'=>'Коллекции пока не заполнены','kk'=>'Коллекциялар әлі толтырылмаған','en'=>'Collections are not populated yet'],
'storage_usage'=>['ru'=>'Использование хранилища','kk'=>'Сақтауды пайдалану','en'=>'Storage usage'],
'resource_mix'=>['ru'=>'Сводка ресурсов','kk'=>'Ресурстар жиыны','en'=>'Resource mix'],
'favorite_count'=>['ru'=>'Избранных коллекций','kk'=>'Таңдаулы коллекциялар','en'=>'Favorite collections'],
'server_timezone'=>['ru'=>'Часовой пояс сервера','kk'=>'Сервердің уақыт белдеуі','en'=>'Server timezone'],
'dashboard_tip'=>['ru'=>'Обзор помогает быстро понять, насколько проект заполнен, опубликован и требует ли модерации форм.','kk'=>'Шолу жоба қаншалықты толтырылғанын, жарияланғанын және формаларға модерация керек пе екенін жылдам көрсетеді.','en'=>'Overview helps you quickly understand how complete the project is, how much is published, and whether forms need moderation.'],
'project_summary'=>['ru'=>'Сводка проекта','kk'=>'Жоба жиынтығы','en'=>'Project summary'],
'project_summary_hint'=>['ru'=>'Контент, формы, структура и файлы в одном рабочем экране.','kk'=>'Контент, формалар, құрылым және файлдар бір жұмыс экранында.','en'=>'Content, forms, structure, and files in one workspace.'],
'live_status'=>['ru'=>'Состояние сейчас','kk'=>'Ағымдағы күй','en'=>'Live status'],
'analytics'=>['ru'=>'Аналитика','kk'=>'Аналитика','en'=>'Analytics'],
'content_overview'=>['ru'=>'Обзор контента','kk'=>'Контент шолуы','en'=>'Content overview'],
'content_overview_hint'=>['ru'=>'Публикация, заполненность и готовность контента.','kk'=>'Контенттің жариялануы, толтырылуы және дайындығы.','en'=>'Publishing, completeness, and content readiness.'],
'attention_center'=>['ru'=>'Требует внимания','kk'=>'Назар аудару керек','en'=>'Needs attention'],
'attention_center_hint'=>['ru'=>'Задачи, которые стоит проверить в первую очередь.','kk'=>'Алдымен тексеру керек тапсырмалар.','en'=>'Items worth checking first.'],
'nothing_requires_attention'=>['ru'=>'Сейчас критичных задач нет','kk'=>'Қазір маңызды тапсырмалар жоқ','en'=>'Nothing critical needs attention'],
'project_composition'=>['ru'=>'Состав проекта','kk'=>'Жоба құрамы','en'=>'Project composition'],
'project_composition_hint'=>['ru'=>'Распределение ресурсов текущего проекта.','kk'=>'Ағымдағы жоба ресурстарының бөлінуі.','en'=>'Resource distribution for the current project.'],
'form_overview'=>['ru'=>'Обзор форм','kk'=>'Формалар шолуы','en'=>'Forms overview'],
'form_overview_hint'=>['ru'=>'Статусы заявок и активность форм.','kk'=>'Өтінім мәртебелері және формалар белсенділігі.','en'=>'Submission statuses and form activity.'],
'latest_content'=>['ru'=>'Последний контент','kk'=>'Соңғы контент','en'=>'Latest content'],
'latest_content_hint'=>['ru'=>'Недавно открытые и изменённые записи.','kk'=>'Жақында ашылған және өзгертілген жазбалар.','en'=>'Recently opened and changed entries.'],
'favorites_hint'=>['ru'=>'Быстрый доступ к важным коллекциям.','kk'=>'Маңызды коллекцияларға жылдам қолжетімділік.','en'=>'Quick access to important collections.'],
'activity_hint'=>['ru'=>'Последние действия пользователей в проекте.','kk'=>'Жобадағы пайдаланушылардың соңғы әрекеттері.','en'=>'Latest user actions in the project.'],
'view_all'=>['ru'=>'Показать все','kk'=>'Барлығын көрсету','en'=>'View all'],
'project_info'=>['ru'=>'О проекте','kk'=>'Жоба туралы','en'=>'Project info'],
'updated_now'=>['ru'=>'Данные рассчитаны сейчас','kk'=>'Деректер қазір есептелді','en'=>'Calculated just now'],
'content_ready'=>['ru'=>'Контент готов','kk'=>'Контент дайын','en'=>'Content ready'],
'content_in_progress'=>['ru'=>'Контент в работе','kk'=>'Контент өңделуде','en'=>'Content in progress'],
'open_queue'=>['ru'=>'Открыть очередь','kk'=>'Кезекті ашу','en'=>'Open queue'],
'open_drafts'=>['ru'=>'Открыть черновики','kk'=>'Жобаларды ашу','en'=>'Open drafts'],
'configure_favorites'=>['ru'=>'Добавить избранное','kk'=>'Таңдаулы қосу','en'=>'Add favorites'],
'compact_status'=>['ru'=>'Статус проекта','kk'=>'Жоба күйі','en'=>'Project status'],
'items_total'=>['ru'=>'Всего элементов','kk'=>'Барлық элементтер','en'=>'Total items'],
'readiness'=>['ru'=>'Готовность','kk'=>'Дайындық','en'=>'Readiness'],
'needs_attention'=>['ru'=>'Требует внимания','kk'=>'Назар аударуды қажет етеді','en'=>'Needs attention'],
'healthy'=>['ru'=>'В порядке','kk'=>'Қалыпты','en'=>'Healthy'],
'entry_share'=>['ru'=>'Доля записей','kk'=>'Жазба үлесі','en'=>'Entry share'],
'last_change'=>['ru'=>'Последнее изменение','kk'=>'Соңғы өзгеріс','en'=>'Last change'],
'moderation_queue'=>['ru'=>'Очередь модерации','kk'=>'Модерация кезегі','en'=>'Moderation queue'],
'project_resources_hint'=>['ru'=>'Сводка по контенту, структуре, формам и файлам текущего проекта.','kk'=>'Ағымдағы жобаның контенті, құрылымы, формалары мен файлдары бойынша жиынтық.','en'=>'A summary of content, structure, forms, and files in the current project.'],

'create_first_section'=>['ru'=>'Создать первый раздел контента','kk'=>'Алғашқы контент бөлімін жасаңыз','en'=>'Create the first content section'],
'create_first_collection'=>['ru'=>'Создать первую коллекцию','kk'=>'Алғашқы коллекцияны жасаңыз','en'=>'Create the first collection'],
'create_first_entry'=>['ru'=>'Создать первую запись','kk'=>'Алғашқы жазбаны жасаңыз','en'=>'Create the first entry'],
'create_first_field'=>['ru'=>'Добавить первое поле','kk'=>'Алғашқы өрісті қосыңыз','en'=>'Add the first field'],
'upload_first_file'=>['ru'=>'Загрузите файл через поле записи','kk'=>'Файлды жазба өрісі арқылы жүктеңіз','en'=>'Upload a file through an entry field'],
'copy_endpoint'=>['ru'=>'Копировать endpoint','kk'=>'Endpoint көшіру','en'=>'Copy endpoint'],
'copied'=>['ru'=>'Скопировано','kk'=>'Көшірілді','en'=>'Copied'],
'default_content_language'=>['ru'=>'Язык контента по умолчанию','kk'=>'Әдепкі контент тілі','en'=>'Default content language'],
'language_has_data'=>['ru'=>'В этом языке уже есть данные','kk'=>'Бұл тілде деректер бар','en'=>'This language already contains data'],
'disable_language_warning'=>['ru'=>'В отключаемых языках есть данные. Они останутся в базе, но будут скрыты из редактора. Продолжить?','kk'=>'Өшірілетін тілдерде деректер бар. Олар базада қалады, бірақ редактордан жасырылады. Жалғастыру керек пе?','en'=>'Some disabled languages contain data. They will remain in the database but be hidden from the editor. Continue?'],
'unsaved_changes'=>['ru'=>'Есть несохранённые изменения','kk'=>'Сақталмаған өзгерістер бар','en'=>'You have unsaved changes'],
'autosave'=>['ru'=>'Автосохранение','kk'=>'Автосақтау','en'=>'Autosave'],
'autosaved'=>['ru'=>'Черновик сохранён','kk'=>'Жоба сақталды','en'=>'Draft saved'],
'autosave_failed'=>['ru'=>'Не удалось сохранить черновик','kk'=>'Жобаны сақтау мүмкін болмады','en'=>'Draft could not be saved'],
'restored_draft'=>['ru'=>'Восстановлен автосохранённый черновик','kk'=>'Автосақталған жоба қалпына келтірілді','en'=>'Autosaved draft restored'],
'clear_entry_form'=>['ru'=>'Очистить форму','kk'=>'Форманы тазарту','en'=>'Clear form'],
'clear_entry_form_q'=>['ru'=>'Очистить все введённые данные и удалить автосохранённый черновик? Сохранённая запись не изменится, пока вы не нажмёте «Сохранить».','kk'=>'Барлық енгізілген деректерді тазалап, автосақталған жобаны жою керек пе? «Сақтау» батырмасын басқанға дейін сақталған жазба өзгермейді.','en'=>'Clear all entered data and delete the autosaved draft? The saved entry will not change until you press Save.'],
'entry_form_cleared'=>['ru'=>'Форма очищена. Автосохранённый черновик удалён.','kk'=>'Форма тазартылды. Автосақталған жоба жойылды.','en'=>'The form was cleared and the autosaved draft was deleted.'],
'json_preview'=>['ru'=>'Предпросмотр JSON','kk'=>'JSON алдын ала қарау','en'=>'JSON preview'],
'history'=>['ru'=>'История изменений','kk'=>'Өзгерістер тарихы','en'=>'Change history'],
'no_history'=>['ru'=>'История пока пуста','kk'=>'Тарих әзірге бос','en'=>'No history yet'],
'restore_version'=>['ru'=>'Восстановить версию','kk'=>'Нұсқаны қалпына келтіру','en'=>'Restore version'],
'version_restored'=>['ru'=>'Версия восстановлена','kk'=>'Нұсқа қалпына келтірілді','en'=>'Version restored'],
'api_explorer'=>['ru'=>'API Explorer','kk'=>'API Explorer','en'=>'API Explorer'],
'send_request'=>['ru'=>'Выполнить запрос','kk'=>'Сұранысты орындау','en'=>'Send request'],
'response'=>['ru'=>'Ответ','kk'=>'Жауап','en'=>'Response'],
'file_trash'=>['ru'=>'Корзина файлов','kk'=>'Файлдар себеті','en'=>'File trash'],
'move_to_trash'=>['ru'=>'Переместить в корзину','kk'=>'Себетке жылжыту','en'=>'Move to trash'],
'restore'=>['ru'=>'Восстановить','kk'=>'Қалпына келтіру','en'=>'Restore'],
'delete_forever'=>['ru'=>'Удалить навсегда','kk'=>'Біржола жою','en'=>'Delete forever'],
'cleanup_preview'=>['ru'=>'Будут перемещены в корзину следующие неиспользуемые файлы','kk'=>'Келесі қолданылмайтын файлдар себетке жылжытылады','en'=>'The following unused files will be moved to trash'],
'cleanup_total'=>['ru'=>'Общий размер','kk'=>'Жалпы өлшем','en'=>'Total size'],
'cleanup_consequence'=>['ru'=>'Файлы останутся физически на диске и смогут быть восстановлены из корзины.','kk'=>'Файлдар дискіде қалады және себеттен қалпына келтіріледі.','en'=>'Files will remain on disk and can be restored from trash.'],
'role_capabilities'=>['ru'=>'Возможности ролей','kk'=>'Рөл мүмкіндіктері','en'=>'Role capabilities'],
'role_admin_desc'=>['ru'=>'Полный доступ ко всем разделам, пользователям и базе данных.','kk'=>'Барлық бөлімдерге, пайдаланушыларға және базаға толық қолжетімділік.','en'=>'Full access to all sections, users, and database settings.'],
'role_developer_desc'=>['ru'=>'Коллекции, поля, связи, импорт/экспорт схемы и API Explorer. Записи открываются только для просмотра.','kk'=>'Коллекциялар, өрістер, байланыстар, схеманы импорттау/экспорттау және API Explorer. Жазбалар тек көру үшін ашылады.','en'=>'Collections, fields, relations, schema import/export, and API Explorer. Entries are read-only.'],
'role_editor_desc'=>['ru'=>'Записи, разделы контента и файлы без изменения схемы.','kk'=>'Схеманы өзгертпей жазбалар, контент бөлімдері және файлдар.','en'=>'Entries, content sections, and files without schema changes.'],
'role_viewer_desc'=>['ru'=>'Только просмотр опубликованного и внутреннего контента.','kk'=>'Тек жарияланған және ішкі контентті көру.','en'=>'Read-only access to content.'],
'drag_to_sort'=>['ru'=>'Перетащите для сортировки','kk'=>'Сұрыптау үшін сүйреңіз','en'=>'Drag to sort'],
'sort_saved'=>['ru'=>'Порядок сохранён','kk'=>'Рет сақталды','en'=>'Order saved'],
'preset_preview'=>['ru'=>'Предпросмотр пресета','kk'=>'Пресетті алдын ала қарау','en'=>'Preset preview'],
'collection_sidebar_hint'=>['ru'=>'Поиск, избранное и быстрый переход между коллекциями','kk'=>'Іздеу, таңдаулы және коллекциялар арасында жылдам ауысу','en'=>'Search, favorites, and quick collection switching'],
'pagination_prev'=>['ru'=>'Назад','kk'=>'Артқа','en'=>'Previous'],
'pagination_next'=>['ru'=>'Далее','kk'=>'Келесі','en'=>'Next'],
'per_page'=>['ru'=>'На странице','kk'=>'Бетте','en'=>'Per page'],
'no_search_results'=>['ru'=>'По вашему запросу ничего не найдено','kk'=>'Сұраныс бойынша ештеңе табылмады','en'=>'No results match your search'],
'delete_confirm'=>['ru'=>'Подтвердите удаление','kk'=>'Жоюды растаңыз','en'=>'Confirm deletion'],
'delete_irreversible'=>['ru'=>'Это действие нельзя отменить.','kk'=>'Бұл әрекетті кері қайтару мүмкін емес.','en'=>'This action cannot be undone.'],
'open_editor'=>['ru'=>'Открыть редактор','kk'=>'Редакторды ашу','en'=>'Open editor'],
'preview'=>['ru'=>'Предпросмотр','kk'=>'Алдын ала қарау','en'=>'Preview'],
'files_not_autosaved'=>['ru'=>'Файлы не входят в автосохранение и сохраняются только основной кнопкой «Сохранить».','kk'=>'Файлдар автосақтауға кірмейді және тек негізгі «Сақтау» батырмасымен сақталады.','en'=>'Files are not included in autosave and are uploaded only with the main Save action.'],
'all_statuses'=>['ru'=>'Все статусы','kk'=>'Барлық статустар','en'=>'All statuses'],
'last_author'=>['ru'=>'Автор изменения','kk'=>'Өзгеріс авторы','en'=>'Changed by'],
'changes'=>['ru'=>'Изменения','kk'=>'Өзгерістер','en'=>'Changes'],
'created_entry'=>['ru'=>'Создана запись','kk'=>'Жазба жасалды','en'=>'Entry created'],
'changed_fields'=>['ru'=>'Поля','kk'=>'Өрістер','en'=>'Fields'],
'maintenance'=>['ru'=>'Обслуживание данных','kk'=>'Деректерге қызмет көрсету','en'=>'Data maintenance'],
'maintenance_hint'=>['ru'=>'Удаляет автосохранённые черновики старше 30 дней и версии, потерявшие запись или коллекцию.','kk'=>'30 күннен ескі автосақталған жобаларды және жазбасы не коллекциясы жоқ нұсқаларды жояды.','en'=>'Removes autosaved drafts older than 30 days and versions whose entry or collection no longer exists.'],
'maintenance_done'=>['ru'=>'Очистка завершена. Черновики: %d, версии: %d.','kk'=>'Тазалау аяқталды. Жобалар: %d, нұсқалар: %d.','en'=>'Cleanup complete. Drafts: %d, versions: %d.'],
'disable_language_title'=>['ru'=>'Отключить язык с данными?','kk'=>'Деректері бар тілді өшіру керек пе?','en'=>'Disable a language that contains data?'],
'disable_language_confirm'=>['ru'=>'Отключить выбранные языки','kk'=>'Таңдалған тілдерді өшіру','en'=>'Disable selected languages'],
'readonly'=>['ru'=>'Только просмотр','kk'=>'Тек қарау','en'=>'Read only'],
'view_entry'=>['ru'=>'Просмотр записи','kk'=>'Жазбаны қарау','en'=>'View entry'],
'project_switch'=>['ru'=>'Переключить проект','kk'=>'Жобаны ауыстыру','en'=>'Switch project'],
'global_orphan'=>['ru'=>'Не привязан к проекту','kk'=>'Жобаға байланыстырылмаған','en'=>'Not assigned to a project'],
'cleanup_project_only'=>['ru'=>'Будут перемещены в корзину только неиспользуемые файлы текущего проекта. Физические файлы без записи в базе показаны отдельно и автоматически не удаляются.','kk'=>'Себетке тек ағымдағы жобаның қолданылмайтын файлдары жіберіледі. Базада жазбасы жоқ физикалық файлдар бөлек көрсетіледі және автоматты түрде жойылмайды.','en'=>'Only unused files belonging to the active project will be moved to Trash. Physical files with no database record are shown separately and are not deleted automatically.'],
'version_snapshot'=>['ru'=>'Снимок версии','kk'=>'Нұсқа көшірмесі','en'=>'Version snapshot'],
'keyboard_shortcuts'=>['ru'=>'Ctrl+S — сохранить, / — поиск','kk'=>'Ctrl+S — сақтау, / — іздеу','en'=>'Ctrl+S to save, / to search'],
'project_files_to_global_trash'=>['ru'=>'Файлы проекта будут перемещены в глобальную корзину. Администратор сможет восстановить их в активный проект или удалить навсегда.','kk'=>'Жоба файлдары жаһандық себетке көшіріледі. Әкімші оларды белсенді жобаға қалпына келтіре немесе біржола жоя алады.','en'=>'Project files will be moved to the global trash. An administrator can restore them into the active project or delete them permanently.'],
'global_file_trash'=>['ru'=>'Глобальная корзина','kk'=>'Жаһандық себет','en'=>'Global trash'],
'global_orphan_files'=>['ru'=>'Глобальные orphan-файлы','kk'=>'Жаһандық orphan-файлдар','en'=>'Global orphan files'],
'global_orphan_hint'=>['ru'=>'Файлы существуют на диске, но отсутствуют в базе. Они не удаляются очисткой проекта.','kk'=>'Файлдар дискіде бар, бірақ базада жоқ. Олар жоба тазалауымен жойылмайды.','en'=>'Files exist on disk but have no database record. Project cleanup never removes them.'],
'origin_project'=>['ru'=>'Исходный проект','kk'=>'Бастапқы жоба','en'=>'Origin project'],
'restore_to_active_project'=>['ru'=>'Восстановить в активный проект','kk'=>'Белсенді жобаға қалпына келтіру','en'=>'Restore into active project'],
'assign_to_project'=>['ru'=>'Назначить проекту','kk'=>'Жобаға тағайындау','en'=>'Assign to project'],
'file_saved'=>['ru'=>'Файл зарегистрирован','kk'=>'Файл тіркелді','en'=>'File registered'],
'file_missing'=>['ru'=>'Физический файл отсутствует на диске.','kk'=>'Физикалық файл дискіде жоқ.','en'=>'The physical file is missing from disk.'],
'last_language_locked'=>['ru'=>'Нельзя отключить последний активный язык контента.','kk'=>'Соңғы белсенді контент тілін өшіруге болмайды.','en'=>'The last active content language cannot be disabled.'],
'choose_new_default_language'=>['ru'=>'Выберите новый основной язык из активных языков.','kk'=>'Белсенді тілдерден жаңа негізгі тілді таңдаңыз.','en'=>'Choose a new default language from the active languages.'],
'html_source'=>['ru'=>'Исходный код','kk'=>'Бастапқы код','en'=>'Source'],
'html_preview'=>['ru'=>'Предпросмотр','kk'=>'Алдын ала қарау','en'=>'Preview'],
'version_before_change'=>['ru'=>'Версия до изменения','kk'=>'Өзгеріске дейінгі нұсқа','en'=>'Version before change'],
'restore_this_version'=>['ru'=>'Восстановить эту версию','kk'=>'Осы нұсқаны қалпына келтіру','en'=>'Restore this version'],
'forms'=>['ru'=>'Формы','kk'=>'Формалар','en'=>'Forms'],
'form'=>['ru'=>'Форма','kk'=>'Форма','en'=>'Form'],
'new_form'=>['ru'=>'Создать форму','kk'=>'Форма жасау','en'=>'Create form'],
'edit_form'=>['ru'=>'Редактировать форму','kk'=>'Форманы өзгерту','en'=>'Edit form'],
'form_saved'=>['ru'=>'Форма сохранена','kk'=>'Форма сақталды','en'=>'Form saved'],
'form_deleted'=>['ru'=>'Форма удалена','kk'=>'Форма жойылды','en'=>'Form deleted'],
'form_delete_q'=>['ru'=>'Удалить форму вместе со всеми полученными заявками?','kk'=>'Форманы барлық алынған өтінімдермен бірге жою керек пе?','en'=>'Delete the form together with all received submissions?'],
'form_endpoint_hint'=>['ru'=>'Сайт отправляет POST-запрос на этот endpoint. Поддерживаются обычные HTML-формы и JSON.','kk'=>'Сайт осы endpoint мекенжайына POST сұрауын жібереді. Қарапайым HTML формалары мен JSON қолдау көрсетіледі.','en'=>'The site sends a POST request to this endpoint. Standard HTML forms and JSON are supported.'],
'form_success_message'=>['ru'=>'Сообщение после успешной отправки','kk'=>'Сәтті жіберілгеннен кейінгі хабарлама','en'=>'Success message'],
'form_default_success'=>['ru'=>'Спасибо! Форма успешно отправлена.','kk'=>'Рақмет! Форма сәтті жіберілді.','en'=>'Thank you! The form was submitted successfully.'],
'form_inactive'=>['ru'=>'Форма отключена','kk'=>'Форма өшірілген','en'=>'Form is inactive'],
'form_not_found'=>['ru'=>'Форма не найдена','kk'=>'Форма табылмады','en'=>'Form not found'],
'form_payload_empty'=>['ru'=>'Форма не содержит данных','kk'=>'Формада деректер жоқ','en'=>'The form contains no data'],
'form_payload_too_large'=>['ru'=>'Данные формы слишком большие','kk'=>'Форма деректері тым үлкен','en'=>'Form payload is too large'],
'form_rate_limited'=>['ru'=>'Слишком много отправок. Попробуйте позже.','kk'=>'Жіберулер тым көп. Кейінірек қайталап көріңіз.','en'=>'Too many submissions. Try again later.'],
'form_submissions'=>['ru'=>'Заявки','kk'=>'Өтінімдер','en'=>'Submissions'],
'form_submission'=>['ru'=>'Заявка','kk'=>'Өтінім','en'=>'Submission'],
'form_submission_received'=>['ru'=>'Заявка принята','kk'=>'Өтінім қабылданды','en'=>'Submission received'],
'form_submission_deleted'=>['ru'=>'Заявка удалена','kk'=>'Өтінім жойылды','en'=>'Submission deleted'],
'form_submission_status_saved'=>['ru'=>'Статус заявки обновлён','kk'=>'Өтінім мәртебесі жаңартылды','en'=>'Submission status updated'],
'form_submissions_bulk_status_saved'=>['ru'=>'Статус обновлён у заявок: %d','kk'=>'Өтінімдердің мәртебесі жаңартылды: %d','en'=>'Submission status updated for: %d'],
'form_submissions_bulk_deleted'=>['ru'=>'Удалено заявок: %d','kk'=>'Жойылған өтінімдер: %d','en'=>'Submissions deleted: %d'],
'form_submissions_select_required'=>['ru'=>'Выберите хотя бы одну заявку','kk'=>'Кемінде бір өтінімді таңдаңыз','en'=>'Select at least one submission'],
'form_submissions_bulk_delete_q'=>['ru'=>'Удалить выбранные заявки без возможности восстановления?','kk'=>'Таңдалған өтінімдерді қалпына келтіру мүмкіндігінсіз жою керек пе?','en'=>'Delete the selected submissions permanently?'],
'selected_items'=>['ru'=>'Выбрано','kk'=>'Таңдалды','en'=>'Selected'],
'select_all'=>['ru'=>'Выбрать все','kk'=>'Барлығын таңдау','en'=>'Select all'],
'select_submission'=>['ru'=>'Выбрать заявку','kk'=>'Өтінімді таңдау','en'=>'Select submission'],
'form_submission_delete_q'=>['ru'=>'Удалить эту заявку без возможности восстановления?','kk'=>'Бұл өтінімді қалпына келтіру мүмкіндігінсіз жою керек пе?','en'=>'Delete this submission permanently?'],
'new_status'=>['ru'=>'Новая','kk'=>'Жаңа','en'=>'New'],
'read_status'=>['ru'=>'Прочитана','kk'=>'Оқылған','en'=>'Read'],
'spam_status'=>['ru'=>'Спам','kk'=>'Спам','en'=>'Spam'],
'all_forms'=>['ru'=>'Все формы','kk'=>'Барлық формалар','en'=>'All forms'],
'no_forms'=>['ru'=>'Форм пока нет','kk'=>'Формалар әзірге жоқ','en'=>'No forms yet'],
'no_form_submissions'=>['ru'=>'Заявок пока нет','kk'=>'Өтінімдер әзірге жоқ','en'=>'No submissions yet'],
'create_first_form'=>['ru'=>'Создайте форму и подключите её endpoint к сайту','kk'=>'Форма жасап, оның endpoint мекенжайын сайтқа қосыңыз','en'=>'Create a form and connect its endpoint to your site'],
'last_submission'=>['ru'=>'Последняя заявка','kk'=>'Соңғы өтінім','en'=>'Last submission'],
'referrer'=>['ru'=>'Страница отправки','kk'=>'Жіберілген бет','en'=>'Referrer'],
'user_agent'=>['ru'=>'Устройство','kk'=>'Құрылғы','en'=>'User agent'],
'payload'=>['ru'=>'Данные формы','kk'=>'Форма деректері','en'=>'Form data'],
'mark_read'=>['ru'=>'Отметить прочитанной','kk'=>'Оқылған деп белгілеу','en'=>'Mark as read'],
'mark_new'=>['ru'=>'Вернуть в новые','kk'=>'Жаңаға қайтару','en'=>'Mark as new'],
'mark_spam'=>['ru'=>'Отметить как спам','kk'=>'Спам деп белгілеу','en'=>'Mark as spam'],
'form_public_endpoint'=>['ru'=>'Публичный endpoint','kk'=>'Ашық endpoint','en'=>'Public endpoint'],
'form_method_note'=>['ru'=>'Метод: POST · Формат: application/x-www-form-urlencoded, multipart/form-data или application/json. Файлы пока не принимаются.','kk'=>'Әдіс: POST · Пішім: application/x-www-form-urlencoded, multipart/form-data немесе application/json. Файлдар әзірге қабылданбайды.','en'=>'Method: POST · Format: application/x-www-form-urlencoded, multipart/form-data, or application/json. File uploads are not accepted yet.'],
'form_files_ignored'=>['ru'=>'Загруженные файлы не обрабатываются этим endpoint.','kk'=>'Жүктелген файлдар бұл endpoint арқылы өңделмейді.','en'=>'Uploaded files are not processed by this endpoint.'],
'form_fields'=>['ru'=>'Поля формы','kk'=>'Форма өрістері','en'=>'Form fields'],
'form_fields_hint'=>['ru'=>'Укажите ключи, которые сайт будет отправлять. Типы и обязательность проверяются сервером.','kk'=>'Сайт жіберетін кілттерді көрсетіңіз. Түрі мен міндеттілігі серверде тексеріледі.','en'=>'Define the keys the site will submit. Types and required fields are validated by the server.'],
'form_field_label'=>['ru'=>'Название поля','kk'=>'Өріс атауы','en'=>'Field label'],
'form_field_key'=>['ru'=>'Ключ','kk'=>'Кілт','en'=>'Key'],
'form_field_type'=>['ru'=>'Тип данных','kk'=>'Дерек түрі','en'=>'Data type'],
'add_form_field'=>['ru'=>'Добавить поле','kk'=>'Өріс қосу','en'=>'Add field'],
'remove_form_field'=>['ru'=>'Убрать поле','kk'=>'Өрісті алып тастау','en'=>'Remove field'],
'form_fields_required'=>['ru'=>'Добавьте хотя бы одно поле формы.','kk'=>'Кемінде бір форма өрісін қосыңыз.','en'=>'Add at least one form field.'],
'duplicate_form_field_key'=>['ru'=>'Ключи полей формы не должны повторяться.','kk'=>'Форма өрістерінің кілттері қайталанбауы керек.','en'=>'Form field keys must be unique.'],
'invalid_form_field'=>['ru'=>'Проверьте название, ключ и тип поля формы.','kk'=>'Форма өрісінің атауын, кілтін және түрін тексеріңіз.','en'=>'Check the form field label, key, and type.'],
'form_schema_history_note'=>['ru'=>'Изменение или удаление поля не меняет уже полученные заявки.','kk'=>'Өрісті өзгерту немесе жою бұрын алынған өтінімдерді өзгертпейді.','en'=>'Changing or removing a field does not alter existing submissions.'],
'form_required_field'=>['ru'=>'Обязательное поле не заполнено: %s','kk'=>'Міндетті өріс толтырылмаған: %s','en'=>'Required field is missing: %s'],
'form_invalid_field_value'=>['ru'=>'Некорректное значение поля: %s','kk'=>'Өріс мәні дұрыс емес: %s','en'=>'Invalid value for field: %s'],
'form_unknown_field'=>['ru'=>'Форма содержит неизвестное поле: %s','kk'=>'Формада белгісіз өріс бар: %s','en'=>'The form contains an unknown field: %s'],
'form_invalid_json_body'=>['ru'=>'Тело JSON должно быть корректным объектом','kk'=>'JSON денесі дұрыс объект болуы керек','en'=>'The JSON body must be a valid object'],
'form_value_too_long'=>['ru'=>'Значение поля слишком длинное: %s','kk'=>'Өріс мәні тым ұзын: %s','en'=>'The field value is too long: %s'],
'form_expected_fields'=>['ru'=>'Ожидаемые поля','kk'=>'Күтілетін өрістер','en'=>'Expected fields'],
'form_field_count'=>['ru'=>'Полей','kk'=>'Өрістер','en'=>'Fields'],
'submission_summary'=>['ru'=>'Кратко','kk'=>'Қысқаша','en'=>'Summary'],
'submission_total'=>['ru'=>'Всего заявок','kk'=>'Барлық өтінімдер','en'=>'Total submissions'],
'submission_new_count'=>['ru'=>'Новых','kk'=>'Жаңа','en'=>'New'],
'submission_read_count'=>['ru'=>'Прочитанных','kk'=>'Оқылған','en'=>'Read'],
'submission_spam_count'=>['ru'=>'Спам','kk'=>'Спам','en'=>'Spam'],
'submission_storage'=>['ru'=>'Объём данных','kk'=>'Деректер көлемі','en'=>'Data size'],
'fields_in_submission'=>['ru'=>'полей','kk'=>'өріс','en'=>'fields'],
'more_fields'=>['ru'=>'ещё %s','kk'=>'тағы %s','en'=>'%s more'],
'per_page'=>['ru'=>'На странице','kk'=>'Бетте','en'=>'Per page'],
'date_from'=>['ru'=>'Дата от','kk'=>'Күннен бастап','en'=>'Date from'],
'date_to'=>['ru'=>'Дата до','kk'=>'Күнге дейін','en'=>'Date to'],
'form_retention'=>['ru'=>'Хранение заявок','kk'=>'Өтінімдерді сақтау','en'=>'Submission retention'],
'form_retention_none'=>['ru'=>'Не удалять автоматически','kk'=>'Автоматты түрде жоймау','en'=>'Never delete automatically'],
'form_retention_30'=>['ru'=>'30 дней','kk'=>'30 күн','en'=>'30 days'],
'form_retention_90'=>['ru'=>'90 дней','kk'=>'90 күн','en'=>'90 days'],
'form_retention_180'=>['ru'=>'180 дней','kk'=>'180 күн','en'=>'180 days'],
'form_retention_365'=>['ru'=>'365 дней','kk'=>'365 күн','en'=>'365 days'],
'form_retention_hint'=>['ru'=>'Прочитанные заявки и спам старше выбранного срока удаляются автоматически. Новые заявки не удаляются.','kk'=>'Таңдалған мерзімнен ескі оқылған өтінімдер мен спам автоматты түрде жойылады. Жаңа өтінімдер жойылмайды.','en'=>'Read submissions and spam older than the selected period are deleted automatically. New submissions are never auto-deleted.'],
'form_table_scalable_hint'=>['ru'=>'Показывается компактная сводка. Полные данные открываются по нажатию. Список загружается постранично.','kk'=>'Ықшам қорытынды көрсетіледі. Толық деректер басқанда ашылады. Тізім беттермен жүктеледі.','en'=>'A compact summary is shown. Open a row to view all data. The list is loaded page by page.'],
'apply'=>['ru'=>'Применить','kk'=>'Қолдану','en'=>'Apply'],
'days'=>['ru'=>'дней','kk'=>'күн','en'=>'days'],
'message'=>['ru'=>'Сообщение','kk'=>'Хабарлама','en'=>'Message'],
'type_text'=>['ru'=>'Текст (переводимый)','kk'=>'Мәтін (аударылатын)','en'=>'Text (translatable)'],
'type_text_global'=>['ru'=>'Текст без перевода','kk'=>'Аударылмайтын мәтін','en'=>'Global text'],
'type_ul_list'=>['ru'=>'Маркированный список · общий','kk'=>'Маркерленген тізім · ортақ','en'=>'Unordered list · global'],
'type_ol_list'=>['ru'=>'Нумерованный список · общий','kk'=>'Нөмірленген тізім · ортақ','en'=>'Ordered list · global'],
'type_ul_list_i18n'=>['ru'=>'Маркированный список · переводимый','kk'=>'Маркерленген тізім · аударылатын','en'=>'Unordered list · translatable'],
'type_ol_list_i18n'=>['ru'=>'Нумерованный список · переводимый','kk'=>'Нөмірленген тізім · аударылатын','en'=>'Ordered list · translatable'],
'list_items_hint'=>['ru'=>'Каждый пункт хранится отдельным элементом массива. Порядок меняется стрелками.','kk'=>'Әр тармақ массивтің жеке элементі ретінде сақталады. Реті көрсеткілермен өзгереді.','en'=>'Each item is stored as a separate array element. Use the arrows to reorder items.'],
'add_list_item'=>['ru'=>'Добавить пункт','kk'=>'Тармақ қосу','en'=>'Add item'],
'list_item'=>['ru'=>'Пункт списка','kk'=>'Тізім тармағы','en'=>'List item'],
'type_textarea'=>['ru'=>'Большой текст','kk'=>'Үлкен мәтін','en'=>'Long text'],
'type_email'=>['ru'=>'Email','kk'=>'Email','en'=>'Email'],
'type_tel'=>['ru'=>'Телефон','kk'=>'Телефон','en'=>'Phone'],
'type_number'=>['ru'=>'Число','kk'=>'Сан','en'=>'Number'],
'type_integer'=>['ru'=>'Целое число','kk'=>'Бүтін сан','en'=>'Integer'],
'type_boolean'=>['ru'=>'Да / Нет','kk'=>'Иә / Жоқ','en'=>'Boolean'],
'type_date'=>['ru'=>'Дата','kk'=>'Күн','en'=>'Date'],
'type_datetime'=>['ru'=>'Дата и время','kk'=>'Күн және уақыт','en'=>'Date and time'],
'type_url'=>['ru'=>'URL','kk'=>'URL','en'=>'URL'],
'type_json'=>['ru'=>'JSON','kk'=>'JSON','en'=>'JSON'],
'access_mode'=>['ru'=>'Доступ','kk'=>'Қолжетімділік','en'=>'Access'],
'access_public'=>['ru'=>'Публичный','kk'=>'Ашық','en'=>'Public'],
'access_private'=>['ru'=>'Приватный','kk'=>'Жабық','en'=>'Private'],
'access_public_hint'=>['ru'=>'Endpoint доступен без ключа. Отдаются только опубликованные записи.','kk'=>'Endpoint кілтсіз қолжетімді. Тек жарияланған жазбалар беріледі.','en'=>'The endpoint is available without a key. Only published entries are returned.'],
'access_private_hint'=>['ru'=>'Запросы принимаются только с API-ключом. Ключ передаётся через X-API-Key или Authorization: Bearer.','kk'=>'Сұраулар тек API кілтімен қабылданады. Кілт X-API-Key немесе Authorization: Bearer арқылы жіберіледі.','en'=>'Requests require an API key. Send it with X-API-Key or Authorization: Bearer.'],
'api_key'=>['ru'=>'API-ключ','kk'=>'API кілті','en'=>'API key'],
'generate_api_key'=>['ru'=>'Сгенерировать новый ключ','kk'=>'Жаңа кілт жасау','en'=>'Generate new key'],
'copy_api_key'=>['ru'=>'Копировать ключ','kk'=>'Кілтті көшіру','en'=>'Copy key'],
'api_key_auto_hint'=>['ru'=>'При выборе приватного доступа ключ создаётся автоматически, если его ещё нет. После изменения нажмите «Сохранить».','kk'=>'Жабық қолжетімділік таңдалса, кілт жоқ болған жағдайда автоматты түрде жасалады. Өзгерткеннен кейін «Сақтау» түймесін басыңыз.','en'=>'When private access is selected, a key is generated automatically if missing. Save the resource after changing it.'],
'api_key_required'=>['ru'=>'Для доступа нужен API-ключ','kk'=>'Қолжетімділік үшін API кілті қажет','en'=>'An API key is required'],
'api_key_invalid'=>['ru'=>'API-ключ отсутствует или недействителен','kk'=>'API кілті жоқ немесе жарамсыз','en'=>'The API key is missing or invalid'],
'api_key_saved'=>['ru'=>'API-ключ сохранён','kk'=>'API кілті сақталды','en'=>'API key saved'],
'private_relations_hidden'=>['ru'=>'Связи с приватными коллекциями не раскрываются без их ключа.','kk'=>'Жабық коллекциялармен байланыстар олардың кілтінсіз ашылмайды.','en'=>'Relations to private collections are not populated without their key.'],
'backups'=>['ru'=>'Резервные копии','kk'=>'Сақтық көшірмелер','en'=>'Backups'],
'backup_project'=>['ru'=>'Скачать резервную копию проекта','kk'=>'Жобаның сақтық көшірмесін жүктеу','en'=>'Download project backup'],
'restore_backup'=>['ru'=>'Восстановить резервную копию','kk'=>'Сақтық көшірмені қалпына келтіру','en'=>'Restore backup'],
'backup_hint'=>['ru'=>'Архив содержит структуру, записи, формы, заявки и файлы текущего проекта. Приватные ключи в архив не включаются.','kk'=>'Мұрағат ағымдағы жобаның құрылымын, жазбаларын, формаларын, өтінімдерін және файлдарын қамтиды. Жабық кілттер мұрағатқа кірмейді.','en'=>'The archive contains the current project schema, entries, forms, submissions, and files. Private keys are not included.'],
'backup_created'=>['ru'=>'Резервная копия создана','kk'=>'Сақтық көшірме жасалды','en'=>'Backup created'],
'backup_restored'=>['ru'=>'Резервная копия восстановлена как новый проект','kk'=>'Сақтық көшірме жаңа жоба ретінде қалпына келтірілді','en'=>'Backup restored as a new project'],
'backup_invalid'=>['ru'=>'Некорректная или повреждённая резервная копия','kk'=>'Сақтық көшірме қате немесе бүлінген','en'=>'The backup is invalid or damaged'],
'zip_required'=>['ru'=>'Для резервных копий требуется расширение PHP ZipArchive','kk'=>'Сақтық көшірмелер үшін PHP ZipArchive кеңейтімі қажет','en'=>'PHP ZipArchive is required for backups'],
'audit_log'=>['ru'=>'Журнал действий','kk'=>'Әрекеттер журналы','en'=>'Audit log'],
'audit_hint'=>['ru'=>'Кто, когда и что изменил в CMS.','kk'=>'CMS ішінде кім, қашан және нені өзгерткенін көрсетеді.','en'=>'See who changed what and when.'],
'audit_action'=>['ru'=>'Действие','kk'=>'Әрекет','en'=>'Action'],
'audit_entity'=>['ru'=>'Объект','kk'=>'Нысан','en'=>'Entity'],
'audit_user'=>['ru'=>'Пользователь','kk'=>'Пайдаланушы','en'=>'User'],
'audit_ip'=>['ru'=>'IP','kk'=>'IP','en'=>'IP'],
'diagnostics'=>['ru'=>'Диагностика установки','kk'=>'Орнатуды диагностикалау','en'=>'Installation diagnostics'],
'diagnostics_hint'=>['ru'=>'Проверка PHP, расширений, прав записи, HTTPS и конфигурации сервера.','kk'=>'PHP, кеңейтімдер, жазу құқықтары, HTTPS және сервер конфигурациясын тексеру.','en'=>'Check PHP, extensions, write permissions, HTTPS, and server configuration.'],
'diagnostic_ok'=>['ru'=>'Готово','kk'=>'Дайын','en'=>'Ready'],
'diagnostic_warning'=>['ru'=>'Предупреждение','kk'=>'Ескерту','en'=>'Warning'],
'diagnostic_error'=>['ru'=>'Ошибка','kk'=>'Қате','en'=>'Error'],
'field_rules'=>['ru'=>'Правила валидации','kk'=>'Валидация ережелері','en'=>'Validation rules'],
'min_length'=>['ru'=>'Минимальная длина','kk'=>'Ең аз ұзындық','en'=>'Minimum length'],
'max_length'=>['ru'=>'Максимальная длина','kk'=>'Ең көп ұзындық','en'=>'Maximum length'],
'min_value'=>['ru'=>'Минимальное значение','kk'=>'Ең аз мән','en'=>'Minimum value'],
'max_value'=>['ru'=>'Максимальное значение','kk'=>'Ең көп мән','en'=>'Maximum value'],
'pattern_regex'=>['ru'=>'Регулярное выражение','kk'=>'Тұрақты өрнек','en'=>'Regular expression'],
'default_value'=>['ru'=>'Значение по умолчанию','kk'=>'Әдепкі мән','en'=>'Default value'],
'unique_value'=>['ru'=>'Уникальное значение','kk'=>'Бірегей мән','en'=>'Unique value'],
'allowed_values'=>['ru'=>'Допустимые значения','kk'=>'Рұқсат етілген мәндер','en'=>'Allowed values'],
'allowed_values_hint'=>['ru'=>'По одному значению на строку. Пусто — разрешены любые значения.','kk'=>'Әр жолға бір мән. Бос болса — барлық мәндерге рұқсат.','en'=>'One value per line. Leave empty to allow any value.'],
'validation_failed_field'=>['ru'=>'Поле не прошло проверку: %s','kk'=>'Өріс тексеруден өтпеді: %s','en'=>'Field validation failed: %s'],
'unique_failed_field'=>['ru'=>'Значение поля должно быть уникальным: %s','kk'=>'Өріс мәні бірегей болуы керек: %s','en'=>'Field value must be unique: %s'],
'api_management'=>['ru'=>'Управление API-ключами','kk'=>'API кілттерін басқару','en'=>'API key management'],
'api_keys'=>['ru'=>'API-ключи','kk'=>'API кілттері','en'=>'API keys'],
'api_key_name'=>['ru'=>'Название ключа','kk'=>'Кілт атауы','en'=>'Key name'],
'api_key_expires'=>['ru'=>'Срок действия','kk'=>'Жарамдылық мерзімі','en'=>'Expires at'],
'api_key_last_used'=>['ru'=>'Последнее использование','kk'=>'Соңғы қолданылуы','en'=>'Last used'],
'api_key_create'=>['ru'=>'Создать ключ','kk'=>'Кілт жасау','en'=>'Create key'],
'api_key_revoke'=>['ru'=>'Отозвать ключ','kk'=>'Кілтті қайтарып алу','en'=>'Revoke key'],
'api_key_revoke_q'=>['ru'=>'Отозвать этот API-ключ? Запросы с ним сразу перестанут работать.','kk'=>'Бұл API кілтін қайтарып алу керек пе? Онымен сұраулар бірден тоқтайды.','en'=>'Revoke this API key? Requests using it will stop immediately.'],
'api_key_created_once'=>['ru'=>'Новый ключ показан только один раз. Скопируйте его сейчас.','kk'=>'Жаңа кілт тек бір рет көрсетіледі. Қазір көшіріп алыңыз.','en'=>'The new key is shown only once. Copy it now.'],
'api_key_revoked'=>['ru'=>'API-ключ отозван','kk'=>'API кілті қайтарылды','en'=>'API key revoked'],
'api_key_expired'=>['ru'=>'Истёк','kk'=>'Мерзімі аяқталды','en'=>'Expired'],
'api_key_active'=>['ru'=>'Активен','kk'=>'Белсенді','en'=>'Active'],
'api_key_save_resource_first'=>['ru'=>'Сначала сохраните ресурс, затем создайте именованные ключи в разделе API-ключов.','kk'=>'Алдымен ресурсты сақтап, кейін API кілттері бөлімінде атаулы кілттер жасаңыз.','en'=>'Save the resource first, then create named keys in API key management.'],
'cors_origins'=>['ru'=>'Разрешённые домены CORS','kk'=>'Рұқсат етілген CORS домендері','en'=>'Allowed CORS origins'],
'cors_origins_hint'=>['ru'=>'Один Origin на строку, например https://site.kz. Если поле пустое или указано *, разрешены все домены.','kk'=>'Әр жолға бір Origin, мысалы https://site.kz. Өріс бос немесе * болса, барлық домендерге рұқсат.','en'=>'One Origin per line, for example https://site.kz. Leave empty or use * to allow all origins.'],
'cors_denied'=>['ru'=>'Этот домен не разрешён политикой CORS','kk'=>'Бұл доменге CORS саясаты рұқсат бермейді','en'=>'This origin is not allowed by CORS policy'],
'cors_invalid'=>['ru'=>'Укажите корректные Origin-адреса CORS: один адрес вида https://site.kz на строку или *','kk'=>'CORS үшін дұрыс Origin мекенжайларын көрсетіңіз: әр жолға https://site.kz түріндегі бір мекенжай немесе *','en'=>'Enter valid CORS origins: one address such as https://site.kz per line, or *'],
'cors_settings'=>['ru'=>'Настройки CORS','kk'=>'CORS баптаулары','en'=>'CORS settings'],
'cors_settings_hint'=>['ru'=>'Укажите домены, которым разрешено обращаться к API и публичным формам текущего проекта. Настройка применяется ко всем коллекциям, разделам и формам проекта.','kk'=>'Ағымдағы жобаның API және ашық формаларына сұрау жіберуге рұқсат етілген домендерді көрсетіңіз. Баптау жобаның барлық коллекцияларына, бөлімдеріне және формаларына қолданылады.','en'=>'Specify the domains allowed to access the current project API and public forms. This setting applies to every collection, section, and form in the project.'],
'cors_default_all'=>['ru'=>'Если поле пустое, автоматически используется * — доступ разрешён всем доменам.','kk'=>'Өріс бос болса, автоматты түрде * қолданылады — барлық домендерге рұқсат беріледі.','en'=>'When the field is empty, * is used automatically and all origins are allowed.'],
'cors_saved'=>['ru'=>'Настройки CORS сохранены','kk'=>'CORS баптаулары сақталды','en'=>'CORS settings saved'],
'form_notifications'=>['ru'=>'Уведомления','kk'=>'Хабарландырулар','en'=>'Notifications'],
'notify_email'=>['ru'=>'Email для уведомлений','kk'=>'Хабарландыру email-ы','en'=>'Notification email'],
'webhook_url'=>['ru'=>'Webhook URL','kk'=>'Webhook URL','en'=>'Webhook URL'],
'webhook_secret'=>['ru'=>'Секрет подписи webhook','kk'=>'Webhook қолтаңба құпиясы','en'=>'Webhook signing secret'],
'webhook_hint'=>['ru'=>'CMS отправляет JSON и подпись X-CMS-Signature. Ошибка доставки не мешает принять заявку.','kk'=>'CMS JSON және X-CMS-Signature қолтаңбасын жібереді. Жеткізу қатесі өтінімді қабылдауға кедергі болмайды.','en'=>'The CMS sends JSON with an X-CMS-Signature header. Delivery failures do not reject the submission.'],
'telegram_bot'=>['ru'=>'Telegram-бот','kk'=>'Telegram бот','en'=>'Telegram bot'],
'telegram_bot_hint'=>['ru'=>'Отправляет в Telegram уведомление сразу после получения новой заявки из формы текущего проекта.','kk'=>'Ағымдағы жоба формасынан жаңа өтінім түскен кезде Telegram-ға бірден хабарлама жібереді.','en'=>'Sends a Telegram notification as soon as a new form submission is received for the current project.'],
'telegram_configurator'=>['ru'=>'Конфигуратор Telegram-бота','kk'=>'Telegram бот конфигураторы','en'=>'Telegram bot configurator'],
'telegram_open_configurator'=>['ru'=>'Открыть конфигуратор','kk'=>'Конфигураторды ашу','en'=>'Open configurator'],
'telegram_bot_token'=>['ru'=>'Токен бота','kk'=>'Бот токені','en'=>'Bot token'],
'telegram_chat_id'=>['ru'=>'Chat ID','kk'=>'Chat ID','en'=>'Chat ID'],
'telegram_chat_id_hint'=>['ru'=>'Скопируйте Chat ID из сообщения бота и вставьте его сюда. Можно указать ID личного чата, группы или @username канала.','kk'=>'Бот хабарламасындағы Chat ID мәнін көшіріп, осында енгізіңіз. Жеке чаттың, топтың ID-сін немесе арнаның @username мәнін көрсетуге болады.','en'=>'Copy the Chat ID from the bot message and paste it here. You can use a private chat ID, group ID, or channel @username.'],
'telegram_token_keep'=>['ru'=>'Оставьте пустым, чтобы сохранить текущий токен','kk'=>'Ағымдағы токенді сақтау үшін бос қалдырыңыз','en'=>'Leave blank to keep the current token'],
'telegram_token_saved'=>['ru'=>'Токен сохранён','kk'=>'Токен сақталды','en'=>'Token saved'],
'telegram_settings_saved'=>['ru'=>'Подключение Telegram сохранено','kk'=>'Telegram қосылымы сақталды','en'=>'Telegram connection saved'],
'telegram_test'=>['ru'=>'Отправить тест','kk'=>'Тест жіберу','en'=>'Send test'],
'telegram_test_sent'=>['ru'=>'Тестовое сообщение отправлено в Telegram','kk'=>'Telegram-ға тест хабарламасы жіберілді','en'=>'Test message sent to Telegram'],
'telegram_test_message'=>['ru'=>'Telegram-бот Mini Headless CMS подключён и готов отправлять новые заявки.','kk'=>'Mini Headless CMS Telegram боты қосылды және жаңа өтінімдерді жіберуге дайын.','en'=>'The Mini Headless CMS Telegram bot is connected and ready to send new submissions.'],
'telegram_invalid_token'=>['ru'=>'Укажите корректный токен Telegram-бота','kk'=>'Telegram боттың дұрыс токенін көрсетіңіз','en'=>'Enter a valid Telegram bot token'],
'telegram_invalid_chat_id'=>['ru'=>'Укажите корректный Telegram Chat ID','kk'=>'Дұрыс Telegram Chat ID көрсетіңіз','en'=>'Enter a valid Telegram Chat ID'],
'telegram_not_configured'=>['ru'=>'Сначала сохраните токен бота и Chat ID','kk'=>'Алдымен бот токенін және Chat ID сақтаңыз','en'=>'Save the bot token and Chat ID first'],
'telegram_delivery_failed'=>['ru'=>'Telegram не принял сообщение','kk'=>'Telegram хабарламаны қабылдамады','en'=>'Telegram did not accept the message'],
'telegram_new_submission'=>['ru'=>'Новая заявка','kk'=>'Жаңа өтінім','en'=>'New submission'],
'telegram_verify_bot'=>['ru'=>'Проверить бота','kk'=>'Ботты тексеру','en'=>'Verify bot'],
'telegram_bot_verified'=>['ru'=>'Бот проверен, токен сохранён','kk'=>'Бот тексерілді, токен сақталды','en'=>'Bot verified and token saved'],
'telegram_bot_not_verified'=>['ru'=>'Сначала вставьте токен и проверьте бота','kk'=>'Алдымен токенді енгізіп, ботты тексеріңіз','en'=>'Enter the token and verify the bot first'],
'telegram_open_bot'=>['ru'=>'Открыть бота и ждать','kk'=>'Ботты ашу және күту','en'=>'Open bot and wait'],
'telegram_get_chat_id'=>['ru'=>'Получить Chat ID','kk'=>'Chat ID алу','en'=>'Get Chat ID'],
'telegram_chat_id_setup_title'=>['ru'=>'Получение Chat ID','kk'=>'Chat ID алу','en'=>'Get your Chat ID'],
'telegram_chat_id_setup_hint'=>['ru'=>'Нажмите «Открыть бота и ждать», затем отправьте боту любое сообщение. Для группы отправьте /chatid. Пока модальное окно открыто, CMS найдёт сообщение, бот ответит вашим Chat ID, а значение автоматически подставится ниже.','kk'=>'«Ботты ашу және күту» түймесін басып, ботқа кез келген хабарлама жіберіңіз. Топ үшін /chatid жіберіңіз. Модаль терезе ашық тұрғанда CMS хабарламаны тауып, бот Chat ID жібереді және мән төменде автоматты түрде толтырылады.','en'=>'Click “Open bot and wait”, then send the bot any message. In a group, send /chatid. While the modal stays open, the CMS will find the message, the bot will reply with your Chat ID, and the value will be filled below automatically.'],
'telegram_wait_chat_id'=>['ru'=>'Ждать сообщение','kk'=>'Хабарламаны күту','en'=>'Wait for message'],
'telegram_waiting_message'=>['ru'=>'Ожидаем сообщение от бота…','kk'=>'Боттан хабарлама күтілуде…','en'=>'Waiting for a bot message…'],
'telegram_no_messages'=>['ru'=>'Сообщение пока не найдено. Отправьте боту сообщение и попробуйте ещё раз.','kk'=>'Хабарлама әзірге табылмады. Ботқа хабарлама жіберіп, қайта көріңіз.','en'=>'No message found yet. Send a message to the bot and try again.'],
'telegram_chat_id_sent'=>['ru'=>'Готово: бот отправил Chat ID в Telegram. Скопируйте его и вставьте в поле ниже.','kk'=>'Дайын: бот Chat ID мәнін Telegram-ға жіберді. Оны көшіріп, төмендегі өріске енгізіңіз.','en'=>'Done: the bot sent the Chat ID in Telegram. Copy it into the field below.'],
'telegram_chat_id_reply_title'=>['ru'=>'Ваш Chat ID','kk'=>'Сіздің Chat ID','en'=>'Your Chat ID'],
'telegram_chat_id_reply_copy'=>['ru'=>'Скопируйте это значение и вставьте его в настройки Mini Headless CMS.','kk'=>'Осы мәнді көшіріп, Mini Headless CMS баптауларына енгізіңіз.','en'=>'Copy this value and paste it into the Mini Headless CMS settings.'],
'telegram_chat_name'=>['ru'=>'Чат','kk'=>'Чат','en'=>'Chat'],
'telegram_chat_type'=>['ru'=>'Тип','kk'=>'Түрі','en'=>'Type'],
'telegram_private_chat'=>['ru'=>'Личный чат','kk'=>'Жеке чат','en'=>'Private chat'],
'telegram_group_chat'=>['ru'=>'Группа','kk'=>'Топ','en'=>'Group'],
'telegram_supergroup_chat'=>['ru'=>'Супергруппа','kk'=>'Супертоп','en'=>'Supergroup'],
'telegram_channel_chat'=>['ru'=>'Канал','kk'=>'Арна','en'=>'Channel'],
'telegram_webhook_active'=>['ru'=>'У этого бота уже подключён webhook. Получение Chat ID через getUpdates недоступно, пока webhook не будет удалён.','kk'=>'Бұл ботта webhook қосылған. Webhook жойылмайынша getUpdates арқылы Chat ID алу мүмкін емес.','en'=>'This bot already has a webhook. Chat ID discovery through getUpdates is unavailable until the webhook is removed.'],
'telegram_messages_processed'=>['ru'=>'Обработано сообщений: %d','kk'=>'Өңделген хабарламалар: %d','en'=>'Messages processed: %d'],
'export_csv'=>['ru'=>'Экспорт CSV','kk'=>'CSV экспорт','en'=>'Export CSV'],
'export_json'=>['ru'=>'Экспорт JSON','kk'=>'JSON экспорт','en'=>'Export JSON'],
'project_access'=>['ru'=>'Доступ к проектам','kk'=>'Жобаларға қолжетімділік','en'=>'Project access'],
'project_role'=>['ru'=>'Роль в проекте','kk'=>'Жобадағы рөл','en'=>'Project role'],
'no_project_access'=>['ru'=>'У вас нет доступа ни к одному проекту','kk'=>'Сізде ешбір жобаға қолжетімділік жоқ','en'=>'You do not have access to any project'],
'api_pagination_hint'=>['ru'=>'Поддерживаются page, limit, sort, q, filter[field] и fields. Максимальный limit — 100.','kk'=>'page, limit, sort, q, filter[field] және fields параметрлері қолдау көрсетіледі. Ең үлкен limit — 100.','en'=>'Supports page, limit, sort, q, filter[field], and fields. Maximum limit is 100.'],
];
$l=lang();return $t[$k][$l]??$t[$k]['ru']??$k;}

/* CORE */
function sv($v){return is_array($v)||is_object($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT):(string)$v;}
function h($v){return htmlspecialchars(sv($v),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function ok(){return !empty($_SESSION['uid']);}
function now(){return date('Y-m-d H:i:s');}
function slug($s){
    $s=mb_strtolower(trim((string)$s));
    static $map=null;
    if($map===null)$map=[
        'а'=>'a','ә'=>'a','б'=>'b','в'=>'v','г'=>'g','ғ'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','қ'=>'q','л'=>'l','м'=>'m','н'=>'n','ң'=>'n','о'=>'o','ө'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ұ'=>'u','ү'=>'u','ф'=>'f','х'=>'h','һ'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sh','ъ'=>'','ы'=>'y','і'=>'i','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'і'=>'i','ї'=>'i','є'=>'e','ґ'=>'g','ў'=>'u','ʻ'=>'','’'=>'','`'=>'','´'=>''
    ];
    $s=strtr($s,$map);
    $s=preg_replace('/[^a-z0-9_-]+/u','-',$s);
    $s=trim(preg_replace('/-+/','-',$s),'-');
    return $s!==''?$s:'item';
}
function csrf(){if(empty($_SESSION['_csrf']))$_SESSION['_csrf']=bin2hex(random_bytes(16));return $_SESSION['_csrf'];}
function chk(){if(($_POST['_csrf']??'')!==($_SESSION['_csrf']??''))exit('CSRF');}
function flash($m=null,$type='success'){
    if($m!==null){$_SESSION['_f']=['message'=>(string)$m,'type'=>in_array($type,['success','warning','danger','info'],true)?$type:'info'];return;}
    $m=$_SESSION['_f']??null;unset($_SESSION['_f']);
    if(is_string($m)&&$m!=='')return ['message'=>$m,'type'=>'info'];
    return is_array($m)?$m:null;
}
function flash_html($f){
    if(!$f)return '';
    $type=in_array($f['type']??'info',['success','warning','danger','info'],true)?$f['type']:'info';
    return '<div class="alert alert-'.h($type).' rounded-4 border-0">'.h($f['message']??'').'</div>';
}

function old_store(array $data,string $modal=''){
    unset($data['_csrf']);
    foreach(['p','password','mysql_password','webhook_secret','telegram_bot_token'] as $k)if(isset($data[$k]))$data[$k]='';
    $_SESSION['_old_input']=$data;
    if($modal!=='')$_SESSION['_old_modal']=$modal;
}
function old_all(){return is_array($_SESSION['_old_input']??null)?$_SESSION['_old_input']:[];}
function old_value(string $name,$default=''){
    $data=old_all();
    $keys=[];preg_match_all('/(^[^\[]+)|\[([^\]]*)\]/',$name,$m);
    foreach($m[0] as $i=>$raw){$k=$m[1][$i]!==''?$m[1][$i]:$m[2][$i];if($k!=='')$keys[]=$k;}
    if(!$keys)return $default;$v=$data;
    foreach($keys as $k){if(!is_array($v)||!array_key_exists($k,$v))return $default;$v=$v[$k];}
    return $v;
}
function old_modal(){return (string)($_SESSION['_old_modal']??'');}
function old_clear(){unset($_SESSION['_old_input'],$_SESSION['_old_modal']);}
function request_return(){return clean_url((string)($_SERVER['HTTP_REFERER']??'./'));}

function clean_url($u){$p=parse_url((string)$u);$q=[];if(!empty($p['query']))parse_str($p['query'],$q);unset($q['lang']);$path=$p['path']??'./';if($path===''||$path===basename(__FILE__))$path='./';return $path.($q?'?'.http_build_query($q):'');}
function stable_return_url($u,array $drop=[]){
    $p=parse_url((string)$u);$q=[];if(!empty($p['query']))parse_str($p['query'],$q);
    $transient=['lang','edit_col','new_col','project_edit','form_edit','gid','uid','fid'];
    foreach(array_values(array_unique(array_merge($transient,$drop))) as $key)unset($q[$key]);
    $path=$p['path']??'./';if($path===''||$path===basename(__FILE__))$path='./';
    return $path.($q?'?'.http_build_query($q):'');
}
function go($u){header('Location: '.clean_url((string)$u));exit;}
function U(array $p=[]):string{if(isset($p['api'])&&!isset($p['project'])&&cfg_exists()&&!setup_required()){try{$pr=current_project();if($pr)$p['project']=$pr['s'];}catch(Throwable $e){}}return './'.($p?'?'.http_build_query($p):'');}
function J($x,$c=200){http_response_code($c);header('Content-Type:application/json;charset=utf-8');echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;}

/* APP CONFIG */
function cfg_path(){return ConfigStore::path();}
function cfg_exists(){return ConfigStore::exists();}
function cfg_read(){return ConfigStore::read();}
function cfg_write(array $c){return ConfigStore::write($c);}
function cfg_cache_reset(){ConfigStore::reload();}
function cfg_reset(){ConfigStore::reset();}
function cfg_update(callable $fn){return ConfigStore::update($fn);}
function cfg_setting($k,$d=null){return ConfigStore::get('settings.'.(string)$k,$d);}
function debug_enabled(){return (bool)cfg_setting('debug_mode',false);}
function apply_debug_mode(?bool $enabled=null){
    $enabled=$enabled??debug_enabled();
    error_reporting(E_ALL);
    ini_set('display_errors',$enabled?'1':'0');
    ini_set('display_startup_errors',$enabled?'1':'0');
    ini_set('html_errors',$enabled?'1':'0');
    ini_set('log_errors','1');
    ini_set('zend.exception_ignore_args',$enabled?'0':'1');
    return $enabled;
}
function content_i18n_enabled(){return (bool)cfg_setting('content_i18n',true);}
function configured_content_langs(){$v=cfg_setting('content_langs',['ru','kk','en']);if(!is_array($v))$v=['ru','kk','en'];$v=array_values(array_unique(array_intersect($v,array_keys(CONTENT_LANGS))));return $v?:['ru','kk','en'];}
function configured_default_content_lang(){$v=(string)cfg_setting('content_default_lang','ru');return array_key_exists($v,CONTENT_LANGS)?$v:'ru';}
function content_langs(){return content_i18n_enabled()?configured_content_langs():[configured_default_content_lang()];}
function default_content_lang(){
    $v=configured_default_content_lang();if(!content_i18n_enabled())return $v;$langs=configured_content_langs();
    return in_array($v,$langs,true)?$v:($langs[0]??'ru');
}
function resource_base_value(array $row,string $key):string{return trim((string)($row[$key.'_base']??$row[$key]??''));}
function resource_i18n_map(?array $row,bool $fillActive=true):array{
    $row=$row?:[];$raw=json_decode((string)($row['i18n']??''),true);$out=[];
    if(is_array($raw))foreach($raw as $code=>$values){if(!array_key_exists((string)$code,CONTENT_LANGS)||!is_array($values))continue;$out[(string)$code]=['n'=>trim((string)($values['n']??'')),'d'=>trim((string)($values['d']??'')),'_translated'=>!empty($values['_translated'])];}
    if($fillActive){$baseN=resource_base_value($row,'n');$baseD=resource_base_value($row,'d');$primary=default_content_lang();foreach(content_langs() as $code){$name=trim((string)($out[$code]['n']??''));$description=trim((string)($out[$code]['d']??''));$out[$code]=['n'=>$name!==''?$name:$baseN,'d'=>$description!==''?$description:$baseD,'_translated'=>array_key_exists($code,$out)?!empty($out[$code]['_translated']):$code===$primary];}}
    return $out;
}
function resource_display_lang(?string $requested=null):string{
    if(!content_i18n_enabled())return default_content_lang();$langs=content_langs();$candidate=$requested;
    if($candidate===null||$candidate==='')$candidate=(string)($_GET['lang']??($_GET['locale']??lang()));
    return in_array($candidate,$langs,true)?$candidate:default_content_lang();
}
function resource_text(array $row,string $field,?string $requested=null):string{return resource_base_value($row,$field);}
function resource_translation_state(array $row,string $lang):bool{return true;}
function localize_resource_row($row,?string $requested=null){if(!is_array($row))return $row;$row['n_base']=$row['n']??'';$row['d_base']=$row['d']??'';$row['n']=resource_base_value($row,'n');$row['d']=resource_base_value($row,'d');return $row;}
function localize_resource_rows(array $rows,?string $requested=null):array{return array_map(fn($row)=>localize_resource_row($row,$requested),$rows);}
function resource_post_values(?array $existing=null):array{
    $existing=$existing?:[];
    $name=trim((string)($_POST['n']??resource_base_value($existing,'n')));
    $description=trim((string)($_POST['d']??resource_base_value($existing,'d')));
    if($name==='')throw new Exception(T('name_required'));
    return [$name,$description,'{}',[]];
}
function translation_badge(bool $translated):string{return '<span class="badge '.($translated?'text-bg-success':'text-bg-warning').' js-i18n-state" data-translated-label="'.h(T('translation_translated')).'" data-auto-label="'.h(T('translation_autofilled')).'">'.h(T($translated?'translation_translated':'translation_autofilled')).'</span>';}
function resource_i18n_fields(?array $row,string $scope):string{
    $row=$row?:[];
    return inp('n',T('name'),resource_base_value($row,'n'),'text',['required'=>true,'data-slug-source'=>'s'])
        .area('d',T('description'),resource_base_value($row,'d'),['rows'=>3]);
}
function db_cfg(){return cfg_read()['db']??[];}
function db_driver(){return db_cfg()['driver']??DB;}
function sqlite_file(){return db_cfg()['sqlite_path']??SQLITE;}
function mysql_cfg(){return db_cfg()['mysql']??['host'=>'localhost','database'=>'cms','user'=>'root','password'=>'','charset'=>'utf8mb4'];}
function mysql_dsn_from(array $m){$host=$m['host']??'localhost';$db=$m['database']??'cms';$ch=$m['charset']??'utf8mb4';return 'mysql:host='.$host.';dbname='.$db.';charset='.$ch;}
function setup_required(){return !cfg_exists()||!in_array(db_driver(),['sqlite','mysql'],true);}
function D():PDO{return Database::pdo();}
function q($sql,$p=[]){return Database::query((string)$sql,(array)$p);}
function all($sql,$p=[]){return Database::all((string)$sql,(array)$p);}
function one($sql,$p=[]){return Database::one((string)$sql,(array)$p);}
function scalar($sql,$p=[]){return Database::scalar((string)$sql,(array)$p);}
function run($sql,$p=[]){return Database::insert((string)$sql,(array)$p);}


/* SETUP */
function setup_action(){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||($_POST['_a']??'')!=='setup_db')return;
    chk();
    $uiLang=(string)($_POST['ui_language']??'ru');if(!isset(LANGS[$uiLang]))$uiLang='ru';
    $uiTheme=(string)($_POST['ui_theme']??'light');if(!isset(THEMES[$uiTheme]))$uiTheme='light';
    set_lang($uiLang);set_theme($uiTheme);
    $driver=($_POST['driver']??'sqlite')==='mysql'?'mysql':'sqlite';
    $i18n=!empty($_POST['content_i18n']);
    $defaultLang=(string)($_POST['content_default_lang']??$uiLang);if(!array_key_exists($defaultLang,CONTENT_LANGS))$defaultLang='ru';
    $postedLangs=is_array($_POST['content_langs']??null)?$_POST['content_langs']:[];
    $contentLangs=array_values(array_unique(array_intersect(array_map('strval',$postedLangs),array_keys(CONTENT_LANGS))));
    if($i18n){if(!$contentLangs){old_store($_POST);flash(T('choose_at_least_one_language'),'danger');go('./');}$defaultLang=$contentLangs[0];}
    else $contentLangs=[$defaultLang];
    $settings=['ui_language'=>$uiLang,'ui_theme'=>$uiTheme,'content_i18n'=>$i18n,'content_langs'=>$contentLangs,'content_default_lang'=>$defaultLang];
    try{
        if($driver==='sqlite'){
            if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('pdo_sqlite is disabled');
            $file=SQLITE;
            if(!is_dir(dirname($file))&&!mkdir(dirname($file),0775,true))throw new RuntimeException('storage is not writable');if(!is_writable(dirname($file)))throw new RuntimeException('storage is not writable');
            cfg_write(['db'=>['driver'=>'sqlite','sqlite_path'=>$file],'settings'=>$settings,'created_at'=>now()]);
            old_clear();flash(T('db_saved'));
            go('./');
        }
        $m=['host'=>trim((string)($_POST['mysql_host']??'localhost')),'database'=>trim((string)($_POST['mysql_database']??'')),'user'=>trim((string)($_POST['mysql_user']??'root')),'password'=>(string)($_POST['mysql_password']??''),'charset'=>'utf8mb4'];
        if($m['database']==='')throw new Exception(T('database').' required');
        if(!extension_loaded('pdo_mysql'))throw new RuntimeException('pdo_mysql is disabled');
        $o=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
        new PDO(mysql_dsn_from($m),$m['user'],$m['password'],$o);
        cfg_write(['db'=>['driver'=>'mysql','mysql'=>$m],'settings'=>$settings,'created_at'=>now()]);
        old_clear();flash(T('db_saved'));
        go('./');
    }catch(Throwable $e){old_store($_POST);flash($e->getMessage(),'danger');go('./');}
}
function first_user_required(){if(!cfg_exists()||setup_required())return false;if((bool)cfg_setting('initialized',false))return false;$empty=(int)scalar('SELECT COUNT(*) FROM users')===0;if(!$empty)cfg_update(function(&$c){$c['settings']['initialized']=true;});return $empty;}

/* DB */
function migrate_schema(){
    uploads();
    if(db_driver()==='sqlite'){
        D()->exec("CREATE TABLE IF NOT EXISTS p(id INTEGER PRIMARY KEY AUTOINCREMENT,n TEXT NOT NULL,s TEXT NOT NULL UNIQUE,d TEXT,o INTEGER DEFAULT 0,ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS c(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,parent_cid INTEGER,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,i18n TEXT,m TEXT DEFAULT 'multiple',o INTEGER DEFAULT 0,access_mode TEXT DEFAULT 'public',api_key_hash TEXT,api_key_enc TEXT,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS f(id INTEGER PRIMARY KEY AUTOINCREMENT,cid INTEGER NOT NULL,l TEXT NOT NULL,k TEXT NOT NULL,t TEXT NOT NULL,x TEXT,r INTEGER DEFAULT 0,o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(cid,k),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS e(id INTEGER PRIMARY KEY AUTOINCREMENT,cid INTEGER NOT NULL,parent_eid INTEGER,uid INTEGER,t TEXT NOT NULL,s TEXT NOT NULL,st TEXT DEFAULT 'draft',j TEXT NOT NULL,ca TEXT,ua TEXT,UNIQUE(cid,s),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS files(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,onm TEXT NOT NULL,fn TEXT NOT NULL UNIQUE,p TEXT NOT NULL,u TEXT NOT NULL,mime TEXT,ext TEXT,sz INTEGER DEFAULT 0,st TEXT DEFAULT 'active',ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY AUTOINCREMENT,l TEXT NOT NULL UNIQUE,p TEXT NOT NULL,n TEXT,role TEXT DEFAULT 'admin',st TEXT DEFAULT 'active',ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS g(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,i18n TEXT,o INTEGER DEFAULT 0,access_mode TEXT DEFAULT 'public',api_key_hash TEXT,api_key_enc TEXT,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS gc(id INTEGER PRIMARY KEY AUTOINCREMENT,gid INTEGER NOT NULL,cid INTEGER NOT NULL,o INTEGER DEFAULT 0,UNIQUE(gid,cid),FOREIGN KEY(gid) REFERENCES g(id) ON DELETE CASCADE,FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS entry_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,eid INTEGER NOT NULL,cid INTEGER NOT NULL,uid INTEGER,t TEXT,s TEXT,st TEXT,j TEXT,changes TEXT,ca TEXT);
        CREATE TABLE IF NOT EXISTS entry_drafts(id INTEGER PRIMARY KEY AUTOINCREMENT,uid INTEGER NOT NULL,cid INTEGER NOT NULL,eid INTEGER NOT NULL DEFAULT 0,lang TEXT NOT NULL,payload TEXT NOT NULL,ua TEXT,UNIQUE(uid,cid,eid,lang));
        CREATE TABLE IF NOT EXISTS favorites(id INTEGER PRIMARY KEY AUTOINCREMENT,uid INTEGER NOT NULL,cid INTEGER NOT NULL,ca TEXT,UNIQUE(uid,cid));
        CREATE TABLE IF NOT EXISTS forms(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,i18n TEXT,st TEXT DEFAULT 'active',success_message TEXT,o INTEGER DEFAULT 0,retention_days INTEGER DEFAULT 0,access_mode TEXT DEFAULT 'public',api_key_hash TEXT,api_key_enc TEXT,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS form_fields(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,fid INTEGER NOT NULL,l TEXT NOT NULL,k TEXT NOT NULL,t TEXT NOT NULL,r INTEGER DEFAULT 0,o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(fid,k),FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS form_submissions(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,fid INTEGER NOT NULL,st TEXT DEFAULT 'new',j TEXT NOT NULL,ip TEXT,agent TEXT,ref TEXT,ca TEXT,ua TEXT,FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE);");
    }else{
        D()->exec("CREATE TABLE IF NOT EXISTS p(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL UNIQUE,d TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS c(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,parent_cid INT UNSIGNED NULL,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,i18n MEDIUMTEXT,m VARCHAR(40) DEFAULT 'multiple',o INT DEFAULT 0,access_mode VARCHAR(20) DEFAULT 'public',api_key_hash VARCHAR(64),api_key_enc TEXT,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_collection_slug(pid,s))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS f(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cid INT UNSIGNED NOT NULL,l VARCHAR(255) NOT NULL,k VARCHAR(160) NOT NULL,t VARCHAR(40) NOT NULL,x MEDIUMTEXT,r TINYINT DEFAULT 0,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE(cid,k),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS e(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cid INT UNSIGNED NOT NULL,parent_eid INT UNSIGNED NULL,uid INT UNSIGNED,t VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,st VARCHAR(40) DEFAULT 'draft',j MEDIUMTEXT NOT NULL,ca DATETIME,ua DATETIME,UNIQUE(cid,s),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS files(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,onm VARCHAR(255) NOT NULL,fn VARCHAR(255) NOT NULL UNIQUE,p VARCHAR(255) NOT NULL,u VARCHAR(255) NOT NULL,mime VARCHAR(120),ext VARCHAR(20),sz BIGINT DEFAULT 0,st VARCHAR(40) DEFAULT 'active',ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,l VARCHAR(120) NOT NULL UNIQUE,p VARCHAR(255) NOT NULL,n VARCHAR(255),role VARCHAR(40) DEFAULT 'admin',st VARCHAR(40) DEFAULT 'active',ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS g(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,i18n MEDIUMTEXT,o INT DEFAULT 0,access_mode VARCHAR(20) DEFAULT 'public',api_key_hash VARCHAR(64),api_key_enc TEXT,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_group_slug(pid,s))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS gc(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,gid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,o INT DEFAULT 0,UNIQUE(gid,cid),FOREIGN KEY(gid) REFERENCES g(id) ON DELETE CASCADE,FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS entry_versions(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,eid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,uid INT UNSIGNED,t VARCHAR(255),s VARCHAR(160),st VARCHAR(40),j MEDIUMTEXT,changes MEDIUMTEXT,ca DATETIME,INDEX(eid),INDEX(cid))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS entry_drafts(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,eid INT UNSIGNED NOT NULL DEFAULT 0,lang VARCHAR(20) NOT NULL,payload MEDIUMTEXT NOT NULL,ua DATETIME,UNIQUE KEY unique_entry_draft(uid,cid,eid,lang))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS favorites(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,ca DATETIME,UNIQUE KEY unique_favorite(uid,cid))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS forms(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,i18n MEDIUMTEXT,st VARCHAR(40) DEFAULT 'active',success_message TEXT,o INT DEFAULT 0,retention_days INT DEFAULT 0,access_mode VARCHAR(20) DEFAULT 'public',api_key_hash VARCHAR(64),api_key_enc TEXT,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_form_slug(pid,s),INDEX(pid),INDEX(st))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS form_fields(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,fid INT UNSIGNED NOT NULL,l VARCHAR(255) NOT NULL,k VARCHAR(160) NOT NULL,t VARCHAR(40) NOT NULL,r TINYINT DEFAULT 0,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE KEY unique_form_field_key(fid,k),INDEX form_fields_project(pid,fid),FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS form_submissions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,fid INT UNSIGNED NOT NULL,st VARCHAR(40) DEFAULT 'new',j MEDIUMTEXT NOT NULL,ip VARCHAR(64),agent TEXT,ref TEXT,ca DATETIME,ua DATETIME,INDEX form_project_created(pid,fid,ca),INDEX form_status(fid,st),FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    ensure_users_compat();ensure_files_compat();ensure_collection_compat();ensure_fields_compat();ensure_entries_compat();ensure_versions_compat();ensure_projects_compat();ensure_groups_compat();ensure_forms_access_compat();ensure_advanced_tables();ensure_storage_protection();seed_users();seed_project_memberships();migrate_legacy_api_keys();
    if((int)D()->query('SELECT COUNT(*) FROM c')->fetchColumn())return;
    $n=now();$pid=current_project_id();
    $cid=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$pid,'Pages','pages','Headless pages','multiple',0,$n,$n]);
    add_default_fields($cid);
    run('INSERT INTO e(cid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$cid,null,'Home','home','published',json_encode(['content'=>'<h1>Hello</h1><p>Данные идут из headless CMS.</p>','meta_description'=>'Главная страница'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$n,$n]);
}

function db_table_exists(string $table):bool{
    if(!preg_match('/^[a-z_][a-z0-9_]*$/i',$table))return false;
    if(db_driver()==='sqlite')return (bool)one("SELECT name FROM sqlite_master WHERE type='table' AND name=?",[$table]);
    return (bool)one('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1',[$table]);
}
function nested_schema_ready():bool{
    try{
        if(!db_table_exists('c')||!db_table_exists('e'))return false;
        table_cols('__reset__');
        return has_col('c','parent_cid')&&has_col('e','parent_eid');
    }catch(Throwable $e){return false;}
}
function schema_needs_migration():bool{
    if((int)cfg_setting('schema_version',0)<APP_SCHEMA_VERSION)return true;
    if(db_driver()==='sqlite'&&!is_file(sqlite_file()))return true;
    return !nested_schema_ready();
}
function boot():void{
    uploads();
    if(schema_needs_migration()){
        try{
            migrate_schema();
            table_cols('__reset__');
            if(!nested_schema_ready())throw new RuntimeException('Nested collections schema migration is incomplete');
            ensure_performance_indexes();
            cfg_update(function(&$c){$c['settings']['schema_version']=APP_SCHEMA_VERSION;$c['settings']['schema_migrated_at']=now();});
            ResponseCache::invalidate();
        }catch(Throwable $e){
            error_log('Mini CMS schema migration failed: '.$e->getMessage());
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            $message=debug_enabled()?$e->getMessage():'Проверьте права базы данных и журнал ошибок PHP.';
            echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ошибка обновления CMS</title><style>body{font-family:system-ui,sans-serif;background:#f5f5f7;margin:0;padding:32px;color:#1d1d1f}.box{max-width:760px;margin:8vh auto;background:#fff;border:1px solid #e5e5e7;border-radius:24px;padding:32px}.code{padding:14px 16px;background:#f5f5f7;border-radius:14px;overflow-wrap:anywhere}</style></head><body><main class="box"><h1>Не удалось обновить структуру базы данных</h1><p>CMS остановила запуск, чтобы не повредить существующие данные.</p><div class="code">'.h($message).'</div><p>Верните предыдущий PHP-файл либо включите режим отладки и повторите открытие страницы.</p></main></body></html>';
            exit;
        }
    }elseif(!is_file(dirname(cfg_path()).'/.htaccess')||!is_file(dirname(cfg_path()).'/index.html'))ensure_storage_protection();
}



function ensure_advanced_tables(){
    $sqlite=db_driver()==='sqlite';
    if($sqlite){
        D()->exec("CREATE TABLE IF NOT EXISTS audit_log(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,uid INTEGER,action TEXT NOT NULL,entity TEXT,entity_id INTEGER,summary TEXT,meta TEXT,ip TEXT,agent TEXT,ca TEXT);
        CREATE TABLE IF NOT EXISTS api_keys(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,resource_type TEXT NOT NULL,resource_id INTEGER NOT NULL,name TEXT NOT NULL,key_hash TEXT NOT NULL,prefix TEXT,last4 TEXT,st TEXT DEFAULT 'active',expires_at TEXT,last_used_at TEXT,created_by INTEGER,ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS user_projects(id INTEGER PRIMARY KEY AUTOINCREMENT,uid INTEGER NOT NULL,pid INTEGER NOT NULL,role TEXT NOT NULL,ca TEXT,ua TEXT,UNIQUE(uid,pid));");
        D()->exec('CREATE INDEX IF NOT EXISTS idx_audit_project_created ON audit_log(pid,ca)');
        D()->exec('CREATE INDEX IF NOT EXISTS idx_api_keys_resource ON api_keys(pid,resource_type,resource_id,st)');
        D()->exec('CREATE INDEX IF NOT EXISTS idx_user_projects_user ON user_projects(uid,pid)');
    }else{
        D()->exec("CREATE TABLE IF NOT EXISTS audit_log(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NULL,uid INT UNSIGNED NULL,action VARCHAR(80) NOT NULL,entity VARCHAR(80),entity_id BIGINT NULL,summary TEXT,meta MEDIUMTEXT,ip VARCHAR(64),agent TEXT,ca DATETIME,INDEX audit_project_created(pid,ca),INDEX audit_user(uid))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS api_keys(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,resource_type VARCHAR(30) NOT NULL,resource_id BIGINT UNSIGNED NOT NULL,name VARCHAR(160) NOT NULL,key_hash VARCHAR(64) NOT NULL,prefix VARCHAR(24),last4 VARCHAR(8),st VARCHAR(20) DEFAULT 'active',expires_at DATETIME NULL,last_used_at DATETIME NULL,created_by INT UNSIGNED NULL,ca DATETIME,ua DATETIME,INDEX api_keys_resource(pid,resource_type,resource_id,st),INDEX api_keys_hash(key_hash))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS user_projects(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uid INT UNSIGNED NOT NULL,pid INT UNSIGNED NOT NULL,role VARCHAR(40) NOT NULL,ca DATETIME,ua DATETIME,UNIQUE KEY user_project_unique(uid,pid),INDEX user_projects_user(uid,pid))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    foreach(['c','g','forms'] as $table){
        if(!has_col($table,'cors_origins'))D()->exec($sqlite?"ALTER TABLE $table ADD COLUMN cors_origins TEXT DEFAULT '*'":"ALTER TABLE `$table` ADD COLUMN cors_origins TEXT");
        q("UPDATE $table SET cors_origins='*' WHERE cors_origins IS NULL OR TRIM(cors_origins)=''");
    }
    if(!has_col('forms','notify_email'))D()->exec($sqlite?"ALTER TABLE forms ADD COLUMN notify_email TEXT":"ALTER TABLE forms ADD COLUMN notify_email VARCHAR(255)");
    if(!has_col('forms','webhook_url'))D()->exec($sqlite?"ALTER TABLE forms ADD COLUMN webhook_url TEXT":"ALTER TABLE forms ADD COLUMN webhook_url TEXT");
    if(!has_col('forms','webhook_secret'))D()->exec($sqlite?"ALTER TABLE forms ADD COLUMN webhook_secret TEXT":"ALTER TABLE forms ADD COLUMN webhook_secret TEXT");
    if(!has_col('form_fields','x'))D()->exec($sqlite?"ALTER TABLE form_fields ADD COLUMN x TEXT":"ALTER TABLE form_fields ADD COLUMN x MEDIUMTEXT");
}
function ensure_storage_protection(){
    $dir=dirname(cfg_path());if(!is_dir($dir))@mkdir($dir,0775,true);
    $ht=$dir.'/.htaccess';if(!is_file($ht))@file_put_contents($ht,"Require all denied\nDeny from all\nOptions -Indexes\n",LOCK_EX);
    $idx=$dir.'/index.html';if(!is_file($idx))@file_put_contents($idx,'',LOCK_EX);
}
function seed_project_memberships(){
    if((int)q('SELECT COUNT(*) FROM user_projects')->fetchColumn()>0)return;
    $tm=now();foreach(all('SELECT id,role FROM users') as $u){if(($u['role']??'viewer')==='admin')continue;foreach(all('SELECT id FROM p') as $pr)run('INSERT INTO user_projects(uid,pid,role,ca,ua)VALUES(?,?,?,?,?)',[(int)$u['id'],(int)$pr['id'],in_array($u['role'],['developer','editor','viewer'],true)?$u['role']:'viewer',$tm,$tm]);}
}
function migrate_legacy_api_keys(){
    foreach(['collection'=>'c','group'=>'g','form'=>'forms'] as $type=>$table){
        foreach(all("SELECT id,pid,api_key_hash,api_key_enc FROM $table WHERE api_key_hash IS NOT NULL AND api_key_hash<>''") as $r){
            if(!one('SELECT id FROM api_keys WHERE pid=? AND resource_type=? AND resource_id=? AND key_hash=?',[(int)$r['pid'],$type,(int)$r['id'],$r['api_key_hash']])){$plain=api_key_decrypt((string)($r['api_key_enc']??''));$prefix=$plain!==''?substr($plain,0,12):'legacy';$last4=$plain!==''?substr($plain,-4):'';$tm=now();run('INSERT INTO api_keys(pid,resource_type,resource_id,name,key_hash,prefix,last4,st,created_by,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[(int)$r['pid'],$type,(int)$r['id'],'Legacy key',$r['api_key_hash'],$prefix,$last4,'active',null,$tm,$tm]);}
            q("UPDATE $table SET api_key_hash='',api_key_enc='' WHERE id=?",[(int)$r['id']]);
        }
    }
}

function db_index_exists(string $table,string $name):bool{
    if(!preg_match('/^[a-z_][a-z0-9_]*$/i',$table.$name))return true;
    if(db_driver()==='sqlite')return (bool)one("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?",[$table,$name]);
    return (bool)one('SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=? LIMIT 1',[$table,$name]);
}
function ensure_index(string $table,string $name,string $columns):void{
    if(db_index_exists($table,$name))return;
    $safeColumns=implode(',',array_map(fn($column)=>'`'.trim($column,' `').'`',explode(',',$columns)));
    D()->exec('CREATE INDEX `'.$name.'` ON `'.$table.'` ('.$safeColumns.')');
}
function ensure_performance_indexes():void{
    $indexes=[
        ['p','idx_p_order','o,id'],['c','idx_c_pid_order','pid,o,id'],['c','idx_c_pid_updated','pid,ua,id'],['c','idx_c_parent_order','pid,parent_cid,o,id'],
        ['f','idx_f_cid_order','cid,o,id'],['e','idx_e_cid_status_id','cid,st,id'],['e','idx_e_cid_updated','cid,ua,id'],['e','idx_e_parent','cid,parent_eid,id'],['e','idx_e_uid','uid'],
        ['g','idx_g_pid_order','pid,o,id'],['gc','idx_gc_gid_order','gid,o,id'],['gc','idx_gc_cid','cid,gid'],
        ['files','idx_files_pid_status','pid,st,id'],['files','idx_files_pid_created','pid,ca,id'],
        ['entry_versions','idx_versions_eid_id','eid,id'],['entry_drafts','idx_drafts_updated','ua'],
        ['forms','idx_forms_pid_order','pid,o,id'],['form_fields','idx_form_fields_order','fid,o,id'],
        ['form_submissions','idx_submissions_pid_form_status','pid,fid,st,id'],['form_submissions','idx_submissions_pid_form_created','pid,fid,ca,id']
    ];
    foreach($indexes as [$table,$name,$columns])try{ensure_index($table,$name,$columns);}catch(Throwable $e){if(debug_enabled())error_log('Index '.$name.': '.$e->getMessage());}
}

function table_cols($t){static $c=[];if($t==='__reset__'){return $c=[];}if(isset($c[$t]))return $c[$t];$rows=db_driver()==='sqlite'?all('PRAGMA table_info('.$t.')'):all('SHOW COLUMNS FROM `'.$t.'`');return $c[$t]=array_map(fn($r)=>$r['name']??$r['Field']??'', $rows);} 
function has_col($t,$col){return in_array($col,table_cols($t),true);} 
function add_col($col,$def){if(!has_col('users',$col)){D()->exec('ALTER TABLE users ADD COLUMN '.$col.' '.$def);table_cols('__reset__');}}
function ensure_users_compat(){
    if(!has_col('users','l'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE users ADD COLUMN l TEXT':'ALTER TABLE users ADD COLUMN l VARCHAR(160)');
    if(!has_col('users','p'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE users ADD COLUMN p TEXT':'ALTER TABLE users ADD COLUMN p VARCHAR(255)');
    if(!has_col('users','n'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE users ADD COLUMN n TEXT':'ALTER TABLE users ADD COLUMN n VARCHAR(255)');
    if(!has_col('users','role'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'editor'":"ALTER TABLE users ADD COLUMN role VARCHAR(40) DEFAULT 'editor'");
    if(!has_col('users','st'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE users ADD COLUMN st TEXT DEFAULT 'active'":"ALTER TABLE users ADD COLUMN st VARCHAR(40) DEFAULT 'active'");
    if(!has_col('users','ca'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE users ADD COLUMN ca TEXT':'ALTER TABLE users ADD COLUMN ca DATETIME');
    if(!has_col('users','ua'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE users ADD COLUMN ua TEXT':'ALTER TABLE users ADD COLUMN ua DATETIME');
    $cols=table_cols('users');
    if(in_array('u',$cols,true))run("UPDATE users SET l=u WHERE (l IS NULL OR l='') AND u IS NOT NULL");
    if(in_array('pass',$cols,true))run("UPDATE users SET p=pass WHERE (p IS NULL OR p='') AND pass IS NOT NULL");
    if(in_array('name',$cols,true))run("UPDATE users SET n=name WHERE (n IS NULL OR n='') AND name IS NOT NULL");
    if(in_array('active',$cols,true))run("UPDATE users SET st=CASE WHEN active=1 THEN 'active' ELSE 'inactive' END WHERE st IS NULL OR st=''");
    run("UPDATE users SET role='editor' WHERE role IS NULL OR role=''");
    run("UPDATE users SET st='active' WHERE st IS NULL OR st=''");
}
function seed_users(){
    if((int)D()->query('SELECT COUNT(*) FROM users')->fetchColumn()>0)return;
    $cfg=cfg_read();
    if(empty($cfg['first_user']))return;
    $first=$cfg['first_user'];
    $login=trim((string)($first['login']??LOGIN))?:LOGIN;
    $name=trim((string)($first['name']??'Administrator'))?:$login;
    $hash=(string)($first['password_hash']??'');
    if($hash===''||!str_starts_with($hash,'$'))return;
    $n=now();
    run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$login,$hash,$name,'admin','active',$n,$n]);
    unset($cfg['first_user']);cfg_write($cfg);
}
function ensure_collection_compat(){
    $cols=table_cols('c');
    if(!in_array('m',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN m TEXT DEFAULT 'multiple'":"ALTER TABLE c ADD COLUMN m VARCHAR(40) DEFAULT 'multiple'");
    if(!in_array('o',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN o INTEGER DEFAULT 0":"ALTER TABLE c ADD COLUMN o INT DEFAULT 0");
    if(!in_array('access_mode',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN access_mode TEXT DEFAULT 'public'":"ALTER TABLE c ADD COLUMN access_mode VARCHAR(20) DEFAULT 'public'");
    if(!in_array('api_key_hash',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN api_key_hash TEXT":"ALTER TABLE c ADD COLUMN api_key_hash VARCHAR(64)");
    if(!in_array('api_key_enc',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN api_key_enc TEXT":"ALTER TABLE c ADD COLUMN api_key_enc TEXT");
    if(!in_array('i18n',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN i18n TEXT":"ALTER TABLE c ADD COLUMN i18n MEDIUMTEXT");
    if(!in_array('parent_cid',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN parent_cid INTEGER":"ALTER TABLE c ADD COLUMN parent_cid INT UNSIGNED NULL");
    table_cols('__reset__');
    run("UPDATE c SET m='multiple' WHERE m IS NULL OR m=''");
    run("UPDATE c SET o=0 WHERE o IS NULL");
    run("UPDATE c SET access_mode='public' WHERE access_mode IS NULL OR access_mode NOT IN ('public','private')");
}
function ensure_groups_compat(){
    $cols=table_cols('g');
    if(!in_array('o',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE g ADD COLUMN o INTEGER DEFAULT 0":"ALTER TABLE g ADD COLUMN o INT DEFAULT 0");
    if(!in_array('access_mode',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE g ADD COLUMN access_mode TEXT DEFAULT 'public'":"ALTER TABLE g ADD COLUMN access_mode VARCHAR(20) DEFAULT 'public'");
    if(!in_array('api_key_hash',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE g ADD COLUMN api_key_hash TEXT":"ALTER TABLE g ADD COLUMN api_key_hash VARCHAR(64)");
    if(!in_array('api_key_enc',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE g ADD COLUMN api_key_enc TEXT":"ALTER TABLE g ADD COLUMN api_key_enc TEXT");
    if(!in_array('i18n',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE g ADD COLUMN i18n TEXT":"ALTER TABLE g ADD COLUMN i18n MEDIUMTEXT");
    run("UPDATE g SET o=0 WHERE o IS NULL");
    run("UPDATE g SET access_mode='public' WHERE access_mode IS NULL OR access_mode NOT IN ('public','private')");
}
function ensure_forms_access_compat(){
    $cols=table_cols('forms');
    if(!in_array('i18n',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE forms ADD COLUMN i18n TEXT":"ALTER TABLE forms ADD COLUMN i18n MEDIUMTEXT");
    if(!in_array('access_mode',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE forms ADD COLUMN access_mode TEXT DEFAULT 'public'":"ALTER TABLE forms ADD COLUMN access_mode VARCHAR(20) DEFAULT 'public'");
    if(!in_array('api_key_hash',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE forms ADD COLUMN api_key_hash TEXT":"ALTER TABLE forms ADD COLUMN api_key_hash VARCHAR(64)");
    if(!in_array('api_key_enc',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE forms ADD COLUMN api_key_enc TEXT":"ALTER TABLE forms ADD COLUMN api_key_enc TEXT");
    if(!in_array('retention_days',$cols,true))D()->exec(db_driver()==='sqlite'?"ALTER TABLE forms ADD COLUMN retention_days INTEGER DEFAULT 0":"ALTER TABLE forms ADD COLUMN retention_days INT DEFAULT 0");
    run("UPDATE forms SET access_mode='public' WHERE access_mode IS NULL OR access_mode NOT IN ('public','private')");
    run("UPDATE forms SET retention_days=0 WHERE retention_days IS NULL OR retention_days NOT IN (0,30,90,180,365)");
    if(db_driver()==='sqlite'){
        D()->exec('CREATE INDEX IF NOT EXISTS idx_form_submissions_project_created ON form_submissions(pid,fid,ca)');
        D()->exec('CREATE INDEX IF NOT EXISTS idx_form_submissions_status ON form_submissions(pid,fid,st)');
    }
}
function ensure_fields_compat(){
    if(!has_col('f','x'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE f ADD COLUMN x TEXT':'ALTER TABLE f ADD COLUMN x MEDIUMTEXT');
}
function ensure_entries_compat(){
    if(!has_col('e','uid')){D()->exec(db_driver()==='sqlite'?'ALTER TABLE e ADD COLUMN uid INTEGER':'ALTER TABLE e ADD COLUMN uid INT UNSIGNED');table_cols('__reset__');}
    if(!has_col('e','parent_eid')){D()->exec(db_driver()==='sqlite'?'ALTER TABLE e ADD COLUMN parent_eid INTEGER':'ALTER TABLE e ADD COLUMN parent_eid INT UNSIGNED NULL');table_cols('__reset__');}
}
function ensure_versions_compat(){
    if(!has_col('entry_versions','changes'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE entry_versions ADD COLUMN changes TEXT':'ALTER TABLE entry_versions ADD COLUMN changes MEDIUMTEXT');
}
function ensure_files_compat(){
    if(!has_col('files','onm'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN onm TEXT':'ALTER TABLE files ADD COLUMN onm VARCHAR(255)');
    if(!has_col('files','fn'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN fn TEXT':'ALTER TABLE files ADD COLUMN fn VARCHAR(255)');
    if(!has_col('files','p'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN p TEXT':'ALTER TABLE files ADD COLUMN p VARCHAR(255)');
    if(!has_col('files','u'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN u TEXT':'ALTER TABLE files ADD COLUMN u VARCHAR(255)');
    if(!has_col('files','sz'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN sz INTEGER DEFAULT 0':'ALTER TABLE files ADD COLUMN sz BIGINT DEFAULT 0');
    if(!has_col('files','st'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE files ADD COLUMN st TEXT DEFAULT 'active'":"ALTER TABLE files ADD COLUMN st VARCHAR(40) DEFAULT 'active'");
    if(!has_col('files','opid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN opid INTEGER':'ALTER TABLE files ADD COLUMN opid INT UNSIGNED');
    if(!has_col('files','opn'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN opn TEXT':'ALTER TABLE files ADD COLUMN opn VARCHAR(255)');
    if(!has_col('files','reason'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN reason TEXT':'ALTER TABLE files ADD COLUMN reason VARCHAR(80)');
    $cols=table_cols('files');
    if(in_array('original_name',$cols,true))run("UPDATE files SET onm=original_name WHERE (onm IS NULL OR onm='') AND original_name IS NOT NULL");
    if(in_array('file_name',$cols,true))run("UPDATE files SET fn=file_name WHERE (fn IS NULL OR fn='') AND file_name IS NOT NULL");
    if(in_array('path',$cols,true))run("UPDATE files SET p=path WHERE (p IS NULL OR p='') AND path IS NOT NULL");
    if(in_array('url',$cols,true))run("UPDATE files SET u=url WHERE (u IS NULL OR u='') AND url IS NOT NULL");
    if(in_array('size',$cols,true))run("UPDATE files SET sz=size WHERE (sz IS NULL OR sz=0) AND size IS NOT NULL");
    if(in_array('status',$cols,true))run("UPDATE files SET st=status WHERE (st IS NULL OR st='') AND status IS NOT NULL");
    run("UPDATE files SET st='active' WHERE st IS NULL OR st=''");
}

function preset_field_sets(){return [
    'blank'=>[],
    'page'=>[
        ['Content','content','html',1,10],
        ['Meta description','meta_description','textarea',0,20],
    ],
    'blog'=>[
        ['Cover image','cover_image','image',0,10],
        ['Excerpt','excerpt','textarea',0,20],
        ['Content','content','html',1,30],
        ['Published date','published_date','date',0,40],
    ],
    'product'=>[
        ['Image','image','image',0,10],
        ['Price','price','number',0,20],
        ['Description','description','html',1,30],
        ['In stock','in_stock','bool',0,40],
    ],
    'faq'=>[
        ['Question','question','text',1,10],
        ['Answer','answer','textarea',1,20],
    ],
    'contact'=>[
        ['Phone','phone','text',0,10],
        ['Email','email','text',0,20],
        ['Address','address','textarea',0,30],
        ['Map URL','map_url','url',0,40],
    ],
];}
function collection_preset_options(){return ['page'=>T('preset_page'),'blank'=>T('preset_blank'),'blog'=>T('preset_blog'),'product'=>T('preset_product'),'faq'=>T('preset_faq'),'contact'=>T('preset_contact')];}
function add_field_if_missing($cid,$label,$key,$type='text',$required=0,$order=0,$options=null){$cid=(int)$cid;$key=str_replace('-','_',slug($key));if(!$cid||!$key||one('SELECT id FROM f WHERE cid=? AND k=?',[$cid,$key]))return;$n=now();$x=$options===null?null:json_encode($options,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$label,$key,$type,$x,(int)$required,(int)$order,$n,$n]);}
function add_preset_fields($cid,$preset='page'){$sets=preset_field_sets();$preset=isset($sets[$preset])?$preset:'page';foreach($sets[$preset] as $f)add_field_if_missing($cid,$f[0],$f[1],$f[2],$f[3],$f[4]);}
function add_default_fields($cid){add_preset_fields($cid,'page');}
function ensure_projects_compat(){
    $sqlite=db_driver()==='sqlite';
    if($sqlite)D()->exec("CREATE TABLE IF NOT EXISTS p(id INTEGER PRIMARY KEY AUTOINCREMENT,n TEXT NOT NULL,s TEXT NOT NULL UNIQUE,d TEXT,o INTEGER DEFAULT 0,cors_origins TEXT DEFAULT '*',ca TEXT,ua TEXT)");
    else D()->exec("CREATE TABLE IF NOT EXISTS p(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL UNIQUE,d TEXT,o INT DEFAULT 0,cors_origins TEXT,ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if(!has_col('p','cors_origins'))D()->exec($sqlite?"ALTER TABLE p ADD COLUMN cors_origins TEXT DEFAULT '*'":"ALTER TABLE p ADD COLUMN cors_origins TEXT");
    $n=now();if(!(int)D()->query('SELECT COUNT(*) FROM p')->fetchColumn())run('INSERT INTO p(n,s,d,o,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?)',['Default','default','Default workspace',0,'*',$n,$n]);
    q("UPDATE p SET cors_origins='*' WHERE cors_origins IS NULL OR TRIM(cors_origins)=''");
    $pid=(int)D()->query('SELECT id FROM p ORDER BY o,id LIMIT 1')->fetchColumn();
    if(!has_col('c','pid'))D()->exec($sqlite?'ALTER TABLE c ADD COLUMN pid INTEGER':'ALTER TABLE c ADD COLUMN pid INT UNSIGNED');
    if(!has_col('g','pid'))D()->exec($sqlite?'ALTER TABLE g ADD COLUMN pid INTEGER':'ALTER TABLE g ADD COLUMN pid INT UNSIGNED');
    if(!has_col('files','pid'))D()->exec($sqlite?'ALTER TABLE files ADD COLUMN pid INTEGER':'ALTER TABLE files ADD COLUMN pid INT UNSIGNED');
    run('UPDATE c SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);run('UPDATE g SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);run("UPDATE files SET pid=? WHERE (pid IS NULL OR pid=0) AND COALESCE(st,'active')!='global_trash'",[$pid]);
}
function all_projects(){return RequestCache::remember('projects:all',fn()=>all('SELECT * FROM p ORDER BY o,n,id'));}
function projects(){if(!ok()||global_role()==='admin')return all_projects();return all('SELECT p.* FROM p JOIN user_projects up ON up.pid=p.id WHERE up.uid=? ORDER BY p.o,p.n,p.id',[current_user_id()]);}
function project_membership_role($uid,$pid){$uid=(int)$uid;$pid=(int)$pid;if(!$uid||!$pid)return null;$u=user_row($uid);if(($u['role']??'viewer')==='admin')return 'admin';$role=q('SELECT role FROM user_projects WHERE uid=? AND pid=?',[$uid,$pid])->fetchColumn();return in_array($role,['developer','editor','viewer'],true)?$role:null;}
function project_access_allowed($pid,$uid=null){$uid=$uid===null?current_user_id():(int)$uid;if(!$uid)return false;return project_membership_role($uid,(int)$pid)!==null;}
function user_project_memberships($uid){$out=[];foreach(all('SELECT pid,role FROM user_projects WHERE uid=?',[(int)$uid]) as $r)$out[(int)$r['pid']]=$r['role'];return $out;}
function sync_user_project_memberships($uid,array $roles){$uid=(int)$uid;$u=user_row($uid);q('DELETE FROM user_projects WHERE uid=?',[$uid]);if(($u['role']??'viewer')==='admin')return;$tm=now();foreach($roles as $pid=>$role){$pid=(int)$pid;if(!$pid||!project($pid)||!in_array($role,['developer','editor','viewer'],true))continue;run('INSERT INTO user_projects(uid,pid,role,ca,ua)VALUES(?,?,?,?,?)',[$uid,$pid,$role,$tm,$tm]);}}

function project($id){$id=(int)$id;return $id?RequestCache::remember('project:id:'.$id,fn()=>one('SELECT * FROM p WHERE id=?',[$id])):null;}
function project_by_slug($s){$s=slug($s);return RequestCache::remember('project:slug:'.$s,fn()=>one('SELECT * FROM p WHERE s=?',[$s]));}
function default_project_id(){return (int)RequestCache::remember('project:default-id',fn()=>scalar('SELECT id FROM p ORDER BY o,id LIMIT 1'));}
function current_project_id(){static $pid=null;if($pid!==null)return $pid;$id=(int)($_SESSION['_pid']??0);if(!ok()){$id=$id&&project($id)?$id:default_project_id();}elseif(!$id||!project($id)||!project_access_allowed($id)){$rows=projects();$id=(int)($rows[0]['id']??0);}$_SESSION['_pid']=$id;return $pid=$id;}
function current_project(){return project(current_project_id());}
function api_project_id(){if(isset($_GET['project'])||isset($_GET['p'])){$p=project_by_slug($_GET['project']??$_GET['p']);return $p?(int)$p['id']:0;}return default_project_id();}
function seed_default_group($pid=null){return;}
function cols($pid=null,$includeNested=false){$pid=$pid?:current_project_id();$lang=resource_display_lang();$scope=$includeNested?'all':'global';return RequestCache::remember('cols:'.$pid.':'.$lang.':'.$scope,function()use($pid,$lang,$includeNested){$sql='SELECT * FROM c WHERE pid=?'.($includeNested?'':' AND parent_cid IS NULL').' ORDER BY o,n,id';return localize_resource_rows(all($sql,[$pid]),$lang);});}
function col($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();$lang=resource_display_lang();return $id?RequestCache::remember('col:'.$pid.':'.$id.':'.$lang,fn()=>localize_resource_row(one('SELECT * FROM c WHERE id=? AND pid=?',[$id,$pid]),$lang)):null;}
function col_by_slug($s,$pid=null){$s=slug($s);$pid=$pid?:current_project_id();$lang=resource_display_lang();return RequestCache::remember('colslug:'.$pid.':'.$s.':'.$lang,fn()=>localize_resource_row(one('SELECT * FROM c WHERE s=? AND pid=?',[$s,$pid]),$lang));}
function collection_is_nested($c):bool{return is_array($c)&&(int)($c['parent_cid']??0)>0;}
function collection_parent($c,$pid=null){$parentId=(int)($c['parent_cid']??0);$pid=$pid?:((int)($c['pid']??0)?:current_project_id());return $parentId?col($parentId,$pid):null;}
function collection_effective_access(array $c):string{$parent=collection_is_nested($c)?collection_parent($c,(int)($c['pid']??0)):null;return $parent?api_access_mode($parent):api_access_mode($c);}
function nested_cols($parentCid,$pid=null){$parentCid=(int)$parentCid;$pid=$pid?:current_project_id();$lang=resource_display_lang();return $parentCid?RequestCache::remember('nested-cols:'.$pid.':'.$parentCid.':'.$lang,fn()=>localize_resource_rows(all('SELECT c.*,(SELECT COUNT(*) FROM e WHERE e.cid=c.id) AS entry_count FROM c WHERE c.pid=? AND c.parent_cid=? ORDER BY c.o,c.n,c.id',[$pid,$parentCid]),$lang)):[];}
function nested_collection_count($parentCid,$pid=null){$pid=$pid?:current_project_id();return (int)scalar('SELECT COUNT(*) FROM c WHERE pid=? AND parent_cid=?',[$pid,(int)$parentCid]);}
function nested_parent_entry_id(array $nested,?int $requested=null):int{
    if(!collection_is_nested($nested))return 0;$parent=collection_parent($nested);if(!$parent)return 0;
    $id=$requested??(int)($_GET['parent_entry']??0);
    if($id){$row=one('SELECT id FROM e WHERE id=? AND cid=?',[$id,(int)$parent['id']]);return $row?(int)$row['id']:0;}
    if(collection_mode($parent)==='single')return (int)(scalar('SELECT id FROM e WHERE cid=? ORDER BY id LIMIT 1',[(int)$parent['id']])?:0);
    return 0;
}
function nested_parent_entry(array $nested,?int $requested=null){$id=nested_parent_entry_id($nested,$requested);return $id?one('SELECT * FROM e WHERE id=?',[$id]):null;}
function nested_collection_url(array $nested,int $parentEntryId=0,array $extra=[]):string{$params=['c'=>(int)$nested['id']];if($parentEntryId)$params['parent_entry']=$parentEntryId;return U($params+$extra);}
function nested_entry_belongs(array $nested,int $parentEntryId):bool{$parent=collection_parent($nested);return $parentEntryId>0&&$parent&&(bool)one('SELECT id FROM e WHERE id=? AND cid=?',[$parentEntryId,(int)$parent['id']]);}
function assert_nested_parent_entry(array $nested,int $parentEntryId):int{if(!collection_is_nested($nested))return 0;if(!nested_entry_belongs($nested,$parentEntryId))throw new Exception(T('nested_requires_parent_entry'));return $parentEntryId;}
function collection_depth(array $c):int{return collection_is_nested($c)?1:0;}
function groups($pid=null){$pid=$pid?:current_project_id();$lang=resource_display_lang();return RequestCache::remember('groups:'.$pid.':'.$lang,fn()=>localize_resource_rows(all('SELECT * FROM g WHERE pid=? ORDER BY o,n,id',[$pid]),$lang));}
function group_row($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();$lang=resource_display_lang();return $id?RequestCache::remember('group:'.$pid.':'.$id.':'.$lang,fn()=>localize_resource_row(one('SELECT * FROM g WHERE id=? AND pid=?',[$id,$pid]),$lang)):null;}
function group_by_slug($s,$pid=null){$s=slug($s);$pid=$pid?:current_project_id();$lang=resource_display_lang();return RequestCache::remember('groupslug:'.$pid.':'.$s.':'.$lang,fn()=>localize_resource_row(one('SELECT * FROM g WHERE s=? AND pid=?',[$s,$pid]),$lang));}
function group_col_ids($gid,$pid=null){$gid=(int)$gid;$pid=$pid?:current_project_id();return RequestCache::remember('group-col-ids:'.$pid.':'.$gid,fn()=>array_map('intval',array_column(all('SELECT gc.cid FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.gid=? AND g.pid=? AND c.pid=? ORDER BY gc.o,gc.id',[$gid,$pid,$pid]),'cid')));}
function group_cols($gid,$pid=null){$gid=(int)$gid;$pid=$pid?:current_project_id();$lang=resource_display_lang();return RequestCache::remember('group-cols:'.$pid.':'.$gid.':'.$lang,fn()=>localize_resource_rows(all('SELECT c.*,gc.o AS group_order FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.gid=? AND g.pid=? AND c.pid=? ORDER BY gc.o,c.o,c.n,gc.id',[$gid,$pid,$pid]),$lang));}
function collection_groups($cid,$pid=null){$cid=(int)$cid;$pid=$pid?:current_project_id();if(!$cid||!col($cid,$pid))return [];return localize_resource_rows(all('SELECT g.* FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.cid=? AND g.pid=? AND c.pid=? ORDER BY g.o,g.n,g.id',[$cid,$pid,$pid]));}
function collection_group_ids($cid,$pid=null){return array_map('intval',array_column(collection_groups((int)$cid,$pid),'id'));}
function ungrouped_cols($pid=null){$pid=$pid?:current_project_id();return localize_resource_rows(all('SELECT c.* FROM c WHERE c.pid=? AND c.parent_cid IS NULL AND NOT EXISTS(SELECT 1 FROM gc JOIN g ON g.id=gc.gid WHERE gc.cid=c.id AND g.pid=?) ORDER BY c.o,c.n,c.id',[$pid,$pid]));}
function ungrouped_count($pid=null){$pid=$pid?:current_project_id();return (int)q('SELECT COUNT(*) FROM c WHERE c.pid=? AND c.parent_cid IS NULL AND NOT EXISTS(SELECT 1 FROM gc JOIN g ON g.id=gc.gid WHERE gc.cid=c.id AND g.pid=?)',[$pid,$pid])->fetchColumn();}
function group_collection_count($gid,$pid=null){$gid=(int)$gid;$pid=$pid?:current_project_id();return (int)q('SELECT COUNT(*) FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.gid=? AND g.pid=? AND c.pid=?',[$gid,$pid,$pid])->fetchColumn();}
function link_collection_to_group($gid,$cid,$order=null){$g=assert_group((int)$gid);$c=assert_collection((int)$cid);if(collection_is_nested($c))throw new Exception(T('access_denied'));if((int)$g['pid']!==(int)$c['pid'])throw new Exception(T('access_denied'));if(one('SELECT id FROM gc WHERE gid=? AND cid=?',[(int)$g['id'],(int)$c['id']]))return false;if($order===null)$order=(int)q('SELECT COALESCE(MAX(o),0)+10 FROM gc WHERE gid=?',[(int)$g['id']])->fetchColumn();run('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[(int)$g['id'],(int)$c['id'],(int)$order]);return true;}
function unlink_collection_from_group($gid,$cid){$g=assert_group((int)$gid);$c=assert_collection((int)$cid);if((int)$g['pid']!==(int)$c['pid'])throw new Exception(T('access_denied'));q('DELETE FROM gc WHERE gid=? AND cid=?',[(int)$g['id'],(int)$c['id']]);}
function sync_collection_groups($cid,array $gids){$c=assert_collection((int)$cid);if(collection_is_nested($c))throw new Exception(T('access_denied'));$valid=[];foreach(array_values(array_unique(array_filter(array_map('intval',$gids)))) as $gid){$g=group_row($gid);if(!$g||(int)$g['pid']!==(int)$c['pid'])throw new Exception(T('access_denied'));$valid[]=$gid;}$pdo=D();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{q('DELETE FROM gc WHERE cid=?',[(int)$c['id']]);foreach($valid as $gid)link_collection_to_group($gid,(int)$c['id']);if($own)$pdo->commit();}catch(Throwable $tx){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $tx;}}
function unique_group_slug($base,$pid=null,$ignoreId=0){$pid=$pid?:current_project_id();$base=slug($base);$try=$base;$i=2;while($r=one('SELECT id FROM g WHERE pid=? AND s=?',[$pid,$try])){if($ignoreId&&(int)$r['id']===(int)$ignoreId)break;$try=$base.'-'.$i++;}return $try;}
function collection_file_stats($cid){$c=assert_collection((int)$cid);$ids=[];$walk=function($v)use(&$walk,&$ids){if(!is_array($v))return;if(!empty($v['file_id']))$ids[(int)$v['file_id']]=true;if(!empty($v['id'])&&!empty($v['file']))$ids[(int)$v['id']]=true;foreach($v as $vv)$walk($vv);};foreach(all('SELECT j FROM e WHERE cid=?',[(int)$c['id']]) as $row){$x=json_decode((string)($row['j']??'{}'),true);if(is_array($x))$walk($x);}if(!$ids)return ['count'=>0,'size'=>0];$count=0;$size=0;foreach(all("SELECT id,sz FROM files WHERE pid=? AND st!='deleted'",[current_project_id()]) as $f)if(isset($ids[(int)$f['id']])){$count++;$size+=(int)$f['sz'];}return ['count'=>$count,'size'=>$size];}
function collection_delete_stats($cid){$c=assert_collection((int)$cid);$files=collection_file_stats((int)$c['id']);return ['entries'=>(int)q('SELECT COUNT(*) FROM e WHERE cid=?',[(int)$c['id']])->fetchColumn(),'fields'=>(int)q('SELECT COUNT(*) FROM f WHERE cid=?',[(int)$c['id']])->fetchColumn(),'sections'=>(int)q('SELECT COUNT(*) FROM gc WHERE cid=?',[(int)$c['id']])->fetchColumn(),'nested'=>(int)q('SELECT COUNT(*) FROM c WHERE pid=? AND parent_cid=?',[current_project_id(),(int)$c['id']])->fetchColumn(),'files'=>$files['count'],'file_size'=>$files['size']];}
function collection_delete_message($c){$st=collection_delete_stats((int)$c['id']);return T('collection_delete_irreversible')."\n\n".T('entry_count').': '.$st['entries']."\n".T('field_count').': '.$st['fields']."\n".T('section_count').': '.$st['sections']."\n".T('nested_collections').': '.$st['nested']."\n".T('files').': '.$st['files'].' · '.fmt_size($st['file_size'])."\n\n".T('collection_files_note');}
function delete_collection_tree(int $collectionId,int $pid):array{
    $ids=array_map('intval',array_column(all('SELECT id FROM c WHERE pid=? AND parent_cid=? ORDER BY id',[$pid,$collectionId]),'id'));$ids[]=$collectionId;$ids=array_values(array_unique($ids));
    foreach($ids as $cid){q('DELETE FROM entry_drafts WHERE cid=?',[$cid]);q('DELETE FROM entry_versions WHERE cid=?',[$cid]);q('DELETE FROM gc WHERE cid=?',[$cid]);q('DELETE FROM favorites WHERE cid=?',[$cid]);q("DELETE FROM api_keys WHERE pid=? AND resource_type='collection' AND resource_id=?",[$pid,$cid]);}
    $marks=implode(',',array_fill(0,count($ids),'?'));q("DELETE FROM c WHERE pid=? AND id IN ($marks)",array_merge([$pid],$ids));return $ids;
}
function delete_nested_entries_for_parent(int $parentEntryId):array{
    $rows=all('SELECT e.id,e.cid FROM e JOIN c ON c.id=e.cid WHERE e.parent_eid=? AND c.parent_cid IS NOT NULL',[$parentEntryId]);$ids=[];
    foreach($rows as $row){$eid=(int)$row['id'];$cid=(int)$row['cid'];q('DELETE FROM entry_drafts WHERE eid=? AND cid=?',[$eid,$cid]);q('DELETE FROM entry_versions WHERE eid=? AND cid=?',[$eid,$cid]);$ids[]=$eid;}
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));q("DELETE FROM e WHERE id IN ($marks)",$ids);}return $ids;
}
function collection_sections_badges($cid,$linked=true){$gs=collection_groups((int)$cid);if(!$gs)return '<span class="badge text-bg-light">'.h(T('without_section')).'</span>';$h='';foreach($gs as $g)$h.='<a class="badge text-bg-light" href="'.h(U(['group'=>(int)$g['id']])).'">'.h($g['n']).'</a>';return $h;}
function fields($cid,$pid=null){$cid=(int)$cid;$pid=$pid?:current_project_id();if(!$cid||!col($cid,$pid))return [];return RequestCache::remember('fields:'.$pid.':'.$cid,function()use($cid,$pid){$rows=all('SELECT f.* FROM f JOIN c ON c.id=f.cid WHERE f.cid=? AND c.pid=? ORDER BY f.o,f.id',[$cid,$pid]);if(!$rows){add_default_fields($cid);$rows=all('SELECT f.* FROM f JOIN c ON c.id=f.cid WHERE f.cid=? AND c.pid=? ORDER BY f.o,f.id',[$cid,$pid]);}return $rows;});}
function field($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?RequestCache::remember('field:'.$pid.':'.$id,fn()=>one('SELECT f.* FROM f JOIN c ON c.id=f.cid WHERE f.id=? AND c.pid=?',[$id,$pid])):null;}
function entry($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?RequestCache::remember('entry:'.$pid.':'.$id,fn()=>one('SELECT e.* FROM e JOIN c ON c.id=e.cid WHERE e.id=? AND c.pid=?',[$id,$pid])):null;}
function collection_project_id($cid){$cid=(int)$cid;return $cid?(int)RequestCache::remember('collection-pid:'.$cid,fn()=>scalar('SELECT pid FROM c WHERE id=?',[$cid])?:0):0;}
function version_row($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT v.* FROM entry_versions v JOIN c ON c.id=v.cid JOIN e ON e.id=v.eid AND e.cid=v.cid WHERE v.id=? AND c.pid=?',[$id,$pid]):null;}
function assert_collection($cid){$c=col((int)$cid);if(!$c)throw new Exception(T('access_denied'));return $c;}
function assert_entry($eid,$cid=0){$e=entry((int)$eid);if(!$e||($cid&&(int)$e['cid']!==(int)$cid))throw new Exception(T('access_denied'));return $e;}
function assert_field($fid,$cid=0){$f=field((int)$fid);if(!$f||($cid&&(int)$f['cid']!==(int)$cid))throw new Exception(T('access_denied'));return $f;}
function assert_group($gid){$g=group_row((int)$gid);if(!$g)throw new Exception(T('access_denied'));return $g;}
function assert_file($id){$f=file_by_id((int)$id);if(!$f)throw new Exception(T('access_denied'));return $f;}

function single_entry($c,$create=false,$parentEid=0){
    $cid=(int)($c['id']??0);
    if(!$cid)return null;
    $nested=collection_is_nested($c);
    $parentEid=$nested?(int)$parentEid:0;
    if($nested&&!$parentEid)return null;
    $e=$nested
        ? one('SELECT * FROM e WHERE cid=? AND parent_eid=? ORDER BY id LIMIT 1',[$cid,$parentEid])
        : one('SELECT * FROM e WHERE cid=? AND (parent_eid IS NULL OR parent_eid=0) ORDER BY id LIMIT 1',[$cid]);
    if($e||!$create)return $e;
    $tm=now();
    $title=trim((string)($c['n']??'Single'))?:'Single';
    $slugBase=$nested?((string)($c['s']??$title).'-'.$parentEid):(string)($c['s']??$title);
    $slug=unique_entry_slug($slugBase,$cid);
    $id=run('INSERT INTO e(cid,parent_eid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$parentEid?:null,ok()?current_user_id():null,$title,$slug,'draft','{}',$tm,$tm]);
    return entry($id);
}
function data($e){$j=json_decode($e['j']??'{}',true);return is_array($j)?$j:[];}
function content_lang($v=null){$langs=content_langs();$v=$v??($_GET['cl']??($_COOKIE['cms_content_lang']??default_content_lang()));$v=in_array($v,$langs,true)?$v:default_content_lang();$_COOKIE['cms_content_lang']=$v;setcookie('cms_content_lang',$v,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $v;}
function is_i18n($d){return is_array($d)&&!empty($d['_i18n']);}
function field_is_list_type(string $type):bool{return in_array($type,['ul_list','ol_list','ul_list_i18n','ol_list_i18n'],true);}
function field_is_relation_type(string $type):bool{return in_array($type,['relation','nested_relation'],true);}
function field_list_tag(string $type):string{return str_starts_with($type,'ol_list')?'ol':'ul';}
function field_is_translatable(array $field):bool{return in_array((string)($field['t']??''),['text','textarea','ul_list_i18n','ol_list_i18n'],true);}
function entry_data_subset(int $cid,array $data,bool $translated):array{$out=[];foreach(fields($cid) as $f){if(field_is_translatable($f)!==$translated)continue;$k=(string)$f['k'];if(array_key_exists($k,$data))$out[$k]=$data[$k];}return $out;}
function i18n_pack($d,$title=''){
    $langs=content_langs();$primary=default_content_lang();
    if(is_i18n($d)){
        if(!is_array($d['_translated']??null))$d['_translated']=[];
        $seedData=[];foreach($langs as $code)if(!empty($d[$code])&&is_array($d[$code])){$seedData=$d[$code];break;}
        if(!$seedData){foreach($d as $key=>$value)if(!str_starts_with((string)$key,'_')&&is_array($value)){$seedData=$value;break;}}
        foreach($langs as $code){if(!isset($d[$code])||!is_array($d[$code])||!$d[$code])$d[$code]=$seedData;if(!array_key_exists($code,$d['_translated']))$d['_translated'][$code]=$code===$primary;}
        unset($d['_titles']);$d['_i18n']=true;return $d;
    }
    $base=is_array($d)?$d:[];$x=['_i18n'=>true,'_translated'=>[]];foreach($langs as $code){$x[$code]=$base;$x['_translated'][$code]=$code===$primary;}return $x;
}
function i18n_of($e){return i18n_pack(data($e),(string)($e['t']??''));}
function entry_title_map(array $e):array{$title=trim((string)($e['t']??''));return array_fill_keys(content_langs(),$title);}
function entry_title(array $e,?string $lang=null):string{return trim((string)($e['t']??''));}
function entry_translated_map(array $e):array{$d=i18n_of($e);$raw=is_array($d['_translated']??null)?$d['_translated']:[];$out=[];foreach(content_langs() as $code)$out[$code]=!empty($raw[$code]);return $out;}
function entry_available_languages(array $e):array{$d=i18n_of($e);$out=[];foreach(content_langs() as $code)if(is_array($d[$code]??null))$out[]=$code;return $out;}
function data_lang($e,$l=null,$fallback=true){$d=data($e);$l=$l?:default_content_lang();if(!is_i18n($d))return is_array($d)?$d:[];$base=default_content_lang();$x=is_array($d[$l]??null)?$d[$l]:[];if($fallback&&$l!==$base)$x=array_replace(is_array($d[$base]??null)?$d[$base]:[],$x);return is_array($x)?$x:[];}
function i18n_out($e,$populate=false){
    $cid=(int)($e['cid']??0);$translated=entry_translated_map($e);$out=[];
    foreach(content_langs() as $code){$local=entry_data_subset($cid,data_lang($e,$code,true),true);$out[$code]=['data'=>resolve_entry_data($cid,$local,$code,$populate,$e,false),'translated'=>!empty($translated[$code])];}
    return $out;
}
function api_populate(){
    $raw=trim((string)($_GET['populate']??''));$lower=strtolower($raw);
    if($raw===''||in_array($lower,['0','false','no','off','none'],true))return false;
    if(in_array($lower,['1','true','yes','on','all','*'],true))return true;
    $fields=[];foreach(preg_split('/[,\s]+/u',$raw)?:[] as $key){$key=trim((string)$key);if($key!==''&&preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]{0,119}$/',$key))$fields[$key]=true;if(count($fields)>=50)break;}
    return $fields?array_keys($fields):false;
}
function api_populate_enabled($populate):bool{return $populate===true||(is_array($populate)&&$populate!==[]);}
function api_should_populate($populate,string $key):bool{return $populate===true||(is_array($populate)&&in_array($key,$populate,true));}
function api_populate_response($populate){return $populate===true?true:($populate===false?false:array_values((array)$populate));}
function api_content_lang(){
    $requested=strtolower(trim((string)($_GET['lang']??($_GET['locale']??''))));
    if(!content_i18n_enabled())return default_content_lang();
    if($requested===''||$requested==='default')return default_content_lang();
    if($requested==='all'||$requested==='*')return null;
    $supported=content_langs();if(!in_array($requested,$supported,true))api_error('unsupported_language',400,'Unsupported content language.',['requested'=>$requested,'supported'=>$supported]);
    return $requested;
}
function api_response_lang($lang){return $lang===null?'all':(string)$lang;}
function api_language_context($lang):array{return ['lang'=>api_response_lang($lang),'i18n'=>content_i18n_enabled(),'default_lang'=>default_content_lang(),'languages'=>content_langs()];}
function api_project_meta(?array $project):?array{return $project?['id'=>(int)$project['id'],'name'=>(string)$project['n'],'slug'=>(string)$project['s']]:null;}
function normalize_json_value($v){if(is_array($v))return $v;$v=trim((string)$v);if($v==='')return null;$x=json_decode($v,true);return json_last_error()===JSON_ERROR_NONE?$x:$v;}


function favorite_ids(){if(!ok())return [];return RequestCache::remember('favorites:'.current_user_id(),fn()=>array_map('intval',array_column(all('SELECT cid FROM favorites WHERE uid=?',[current_user_id()]),'cid')));}
function is_favorite($cid){return in_array((int)$cid,favorite_ids(),true);}
function recent_entries_get(){
    $rows=$_SESSION['_recent_entries']??[];return is_array($rows)?array_slice($rows,0,8):[];
}
function recent_entry_add($e){
    if(!$e)return;$item=['id'=>(int)$e['id'],'cid'=>(int)$e['cid'],'title'=>$e['t'],'slug'=>$e['s'],'time'=>time()];
    $rows=array_values(array_filter(recent_entries_get(),fn($x)=>(int)($x['id']??0)!==(int)$e['id']));array_unshift($rows,$item);$_SESSION['_recent_entries']=array_slice($rows,0,8);
}
function dashboard_metrics($pid=null):array{
    $pid=$pid?:current_project_id();
    return RequestCache::remember('dashboard-metrics:'.$pid,function()use($pid){
        $sql="SELECT
        (SELECT COUNT(*) FROM c WHERE pid=? AND parent_cid IS NULL) collections,
        (SELECT COUNT(*) FROM e JOIN c ec ON ec.id=e.cid WHERE ec.pid=?) entries,
        (SELECT COUNT(*) FROM e JOIN c pc ON pc.id=e.cid WHERE pc.pid=? AND e.st='published') published,
        (SELECT COUNT(*) FROM files WHERE pid=? AND st='active') files,
        (SELECT COUNT(*) FROM g WHERE pid=?) sections,
        (SELECT COUNT(*) FROM forms WHERE pid=?) forms,
        (SELECT COUNT(*) FROM form_submissions WHERE pid=?) submissions,
        (SELECT COALESCE(SUM(sz),0) FROM files WHERE pid=? AND st='active') file_bytes,
        (SELECT COUNT(*) FROM c WHERE pid=? AND parent_cid IS NULL AND access_mode='private') private_collections,
        (SELECT COUNT(*) FROM g WHERE pid=? AND access_mode='private') private_sections,
        (SELECT COUNT(*) FROM forms WHERE pid=? AND access_mode='private') private_forms,
        (SELECT COUNT(*) FROM forms WHERE pid=? AND st='active') active_forms,
        (SELECT COUNT(*) FROM form_submissions WHERE pid=? AND st='new') submission_new,
        (SELECT COUNT(*) FROM form_submissions WHERE pid=? AND st='read') submission_read,
        (SELECT COUNT(*) FROM form_submissions WHERE pid=? AND st='spam') submission_spam,
        (SELECT COUNT(*) FROM c WHERE pid=? AND parent_cid IS NULL AND EXISTS(SELECT 1 FROM e WHERE e.cid=c.id)) collections_with_entries";
        $row=one($sql,array_fill(0,16,$pid))?:[];foreach($row as $key=>$value)$row[$key]=(int)$value;return $row;
    });
}
function project_stats(){return array_intersect_key(dashboard_metrics(),array_flip(['collections','entries','published','files']));}
function entry_snapshot($e,$uid=null,$changes=[]){
    if(!$e)return;
    $json=is_string($changes)?$changes:json_encode(array_values(array_filter((array)$changes)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    run('INSERT INTO entry_versions(eid,cid,uid,t,s,st,j,changes,ca)VALUES(?,?,?,?,?,?,?,?,?)',[(int)$e['id'],(int)$e['cid'],$uid??current_user_id(),$e['t'],$e['s'],$e['st'],$e['j'],$json,now()]);
}
function change_value_preview($v){
    if($v===null||$v==='')return '—';
    if(is_bool($v))return T($v?'yes':'no');
    if(is_array($v))$v=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $v=trim(preg_replace('/\s+/u',' ',strip_tags((string)$v)));
    return mb_strlen($v)>70?mb_substr($v,0,67).'…':$v;
}
function entry_change_summary($old,array $new,$lang=null){
    if(!$old)return [T('created_entry')];
    $changes=[];
    $add=function($label,$from,$to)use(&$changes){$changes[]=$label.': '.change_value_preview($from).' → '.change_value_preview($to);};
    if((string)$old['t']!==(string)($new['t']??''))$add(T('title'),$old['t'],$new['t']??'');
    if((string)$old['s']!==(string)($new['s']??''))$add(T('slug'),$old['s'],$new['s']??'');
    if((string)$old['st']!==(string)($new['st']??''))$add(T('status'),T($old['st']==='published'?'published':'draft'),T(($new['st']??'draft')==='published'?'published':'draft'));
    $a=json_decode((string)($old['j']??'{}'),true);$b=json_decode((string)($new['j']??'{}'),true);$a=is_array($a)?$a:[];$b=is_array($b)?$b:[];
    if(content_i18n_enabled()&&$lang!==null){$a=is_i18n($a)?($a[$lang]??[]):$a;$b=is_i18n($b)?($b[$lang]??[]):$b;}
    $labels=[];foreach(fields((int)$old['cid']) as $f)$labels[$f['k']]=$f['l'];
    $keys=array_unique(array_merge(array_keys($a),array_keys($b)));
    foreach($keys as $k){if($k==='_i18n'||($a[$k]??null)===($b[$k]??null))continue;$add($labels[$k]??$k,$a[$k]??null,$b[$k]??null);}
    return $changes?:[T('version_snapshot')];
}
function version_changes($v){$x=json_decode((string)($v['changes']??''),true);return is_array($x)&&$x?$x:[T('version_snapshot')];}
function exec_count($sql,$params=[]){$st=q($sql,$params);return (int)$st->rowCount();}
function cleanup_maintenance($days=AUTOSAVE_RETENTION_DAYS){
    $cut=date('Y-m-d H:i:s',time()-max(1,(int)$days)*86400);
    $drafts=exec_count('DELETE FROM entry_drafts WHERE ua<? OR NOT EXISTS(SELECT 1 FROM c WHERE c.id=entry_drafts.cid) OR (eid>0 AND NOT EXISTS(SELECT 1 FROM e WHERE e.id=entry_drafts.eid AND e.cid=entry_drafts.cid))',[$cut]);
    $versions=exec_count('DELETE FROM entry_versions WHERE NOT EXISTS(SELECT 1 FROM e WHERE e.id=entry_versions.eid AND e.cid=entry_versions.cid) OR NOT EXISTS(SELECT 1 FROM c WHERE c.id=entry_versions.cid)');
    $submissions=0;foreach(all('SELECT id,pid,retention_days FROM forms WHERE retention_days>0') as $form)$submissions+=form_submission_retention_cleanup($form,(int)$form['pid']);
    return ['drafts'=>$drafts,'versions'=>$versions,'submissions'=>$submissions];
}
function maintenance_maybe(){
    $last=(int)cfg_setting('maintenance_last',0);if($last&&time()-$last<MAINTENANCE_INTERVAL)return;
    cleanup_maintenance();ResponseCache::cleanup(86400);cfg_update(function(&$c){$c['settings']['maintenance_last']=time();});
}

function entry_versions($eid,$limit=30,$pid=null){$pid=$pid?:current_project_id();return all('SELECT v.*,u.n AS user_name,u.l AS user_login FROM entry_versions v JOIN c ON c.id=v.cid LEFT JOIN users u ON u.id=v.uid WHERE v.eid=? AND c.pid=? ORDER BY v.id DESC LIMIT '.max(1,(int)$limit),[(int)$eid,$pid]);}
function entry_draft_get($uid,$cid,$eid,$lang,$pid=null){$pid=$pid?:current_project_id();$r=one('SELECT d.* FROM entry_drafts d JOIN c ON c.id=d.cid WHERE d.uid=? AND d.cid=? AND d.eid=? AND d.lang=? AND c.pid=?',[(int)$uid,(int)$cid,(int)$eid,(string)$lang,$pid]);if(!$r)return null;$p=json_decode($r['payload']??'{}',true);return is_array($p)?['data'=>$p,'updated_at'=>$r['ua']]:null;}
function entry_draft_save($uid,$cid,$eid,$lang,array $payload){
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$tm=now();
    if(db_driver()==='sqlite')q('INSERT INTO entry_drafts(uid,cid,eid,lang,payload,ua)VALUES(?,?,?,?,?,?) ON CONFLICT(uid,cid,eid,lang) DO UPDATE SET payload=excluded.payload,ua=excluded.ua',[(int)$uid,(int)$cid,(int)$eid,(string)$lang,$json,$tm]);
    else q('INSERT INTO entry_drafts(uid,cid,eid,lang,payload,ua)VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),ua=VALUES(ua)',[(int)$uid,(int)$cid,(int)$eid,(string)$lang,$json,$tm]);
    return $tm;
}
function entry_draft_delete($uid,$cid,$eid,$lang){run('DELETE FROM entry_drafts WHERE uid=? AND cid=? AND eid=? AND lang=?',[(int)$uid,(int)$cid,(int)$eid,(string)$lang]);}
function content_language_usage(){
    $counts=array_fill_keys(array_keys(CONTENT_LANGS),0);
    foreach(all('SELECT j FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[current_project_id()]) as $r){
        $d=json_decode($r['j']??'{}',true);
        if(!is_array($d)||empty($d['_i18n']))continue;
        foreach($counts as $l=>$n)if(!empty($d[$l])&&is_array($d[$l])&&array_filter($d[$l],fn($v)=>$v!==''&&$v!==null&&$v!==[]))$counts[$l]++;
    }
    return $counts;
}
function migrate_content_languages(bool $enabled,array $langs,string $default):void{
    $pid=current_project_id();$tm=now();
    foreach(all('SELECT e.* FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[$pid]) as $entry){
        $cid=(int)$entry['cid'];$raw=data($entry);$pack=i18n_pack($raw,(string)$entry['t']);$seedData=is_array($pack[$default]??null)?$pack[$default]:[];
        $global=[];$localSeed=[];
        foreach(fields($cid) as $field){$key=(string)$field['k'];if(field_is_translatable($field)){if(array_key_exists($key,$seedData))$localSeed[$key]=$seedData[$key];}elseif(array_key_exists($key,$seedData))$global[$key]=$seedData[$key];}
        foreach($langs as $code){$current=is_array($pack[$code]??null)?$pack[$code]:[];$local=[];foreach(fields($cid) as $field){if(!field_is_translatable($field))continue;$key=(string)$field['k'];if(array_key_exists($key,$current))$local[$key]=$current[$key];elseif(array_key_exists($key,$localSeed))$local[$key]=$localSeed[$key];}$pack[$code]=array_replace($global,$local);if(!array_key_exists($code,$pack['_translated']))$pack['_translated'][$code]=$code===$default;}
        unset($pack['_titles']);q('UPDATE e SET j=?,ua=? WHERE id=?',[json_encode($pack,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$tm,(int)$entry['id']]);
    }
}
function pagination_meta($total,$page,$per){$pages=max(1,(int)ceil($total/max(1,$per)));$page=max(1,min($page,$pages));return ['total'=>$total,'page'=>$page,'per'=>$per,'pages'=>$pages,'offset'=>($page-1)*$per];}
function request_sort($allowed,$default){$s=(string)($_GET['sort']??$default);return in_array($s,$allowed,true)?$s:$default;}
function request_dir(){return strtolower((string)($_GET['dir']??'desc'))==='asc'?'asc':'desc';}
function query_link(array $changes){$q=$_GET;foreach($changes as $k=>$v){if($v===null)unset($q[$k]);else $q[$k]=$v;}return U($q);}
function sort_link($label,$key,$current,$dir){$next=$current===$key&&$dir==='asc'?'desc':'asc';$ico=$current===$key?($dir==='asc'?'chevron-up':'chevron-down'):'chevron-expand';return '<a class="text-reset d-inline-flex gap-1 align-items-center" href="'.h(query_link(['sort'=>$key,'dir'=>$next,'page'=>1])).'">'.h($label).icon($ico).'</a>';}
function pager_html($m){if(($m['pages']??1)<=1)return '';$h='<nav class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" aria-label="Pagination"><div class="text-muted small">'.h(($m['offset']+1).'–'.min($m['offset']+$m['per'],$m['total']).' / '.$m['total']).'</div><div class="d-flex gap-2">';$h.='<a class="btn btn-outline-dark '.($m['page']<=1?'disabled':'').'" href="'.h(query_link(['page'=>max(1,$m['page']-1)])).'">'.icon('chevron-left').' '.h(T('pagination_prev')).'</a>';$h.='<a class="btn btn-outline-dark '.($m['page']>=$m['pages']?'disabled':'').'" href="'.h(query_link(['page'=>min($m['pages'],$m['page']+1)])).'">'.h(T('pagination_next')).' '.icon('chevron-right').'</a></div></nav>';return $h;}
function select_inline($name,array $opts,$cur=''){ $h='<select class="form-select w-auto" name="'.h($name).'">';foreach($opts as $k=>$v)$h.='<option value="'.h($k).'" '.((string)$cur===(string)$k?'selected':'').'>'.h($v).'</option>';return $h.'</select>';}
function server_search_form($placeholder=null){
    $qv=(string)($_GET['q']??'');$hidden='';foreach($_GET as $k=>$v){if(in_array($k,['q','page','sort','dir'],true)||is_array($v))continue;$hidden.='<input type="hidden" name="'.h($k).'" value="'.h($v).'">';}
    return '<form class="d-flex gap-2 mb-3 js-search-form server-search" method="get">'.$hidden.'<input class="form-control" type="search" name="q" value="'.h($qv).'" placeholder="'.h($placeholder?:T('search')).'" aria-label="'.h(T('search')).'"><button class="btn btn-dark" aria-label="'.h(T('search')).'">'.icon('search').'</button>'.($qv!==''?'<a class="btn btn-outline-dark" href="'.h(query_link(['q'=>null,'page'=>1])).'">'.icon('x-lg').' '.h(T('reset')).'</a>':'').'</form>';
}
function entry_filter_form($status=''){
    $qv=(string)($_GET['q']??'');$hidden='';foreach($_GET as $k=>$v){if(in_array($k,['q','page','status','sort','dir'],true)||is_array($v))continue;$hidden.='<input type="hidden" name="'.h($k).'" value="'.h($v).'">';}
    $opts=[''=>T('all_statuses'),'draft'=>T('draft'),'published'=>T('published')];
    $select='<select class="form-select" name="status" onchange="this.form.submit()" aria-label="'.h(T('status')).'">';foreach($opts as $k=>$v)$select.='<option value="'.h($k).'" '.($status===$k?'selected':'').'>'.h($v).'</option>';$select.='</select>';
    return '<form class="row g-2 mb-3 align-items-center js-search-form" method="get">'.$hidden.'<div class="col-12 col-lg"><input class="form-control" type="search" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"></div><div class="col-8 col-lg-3">'.$select.'</div><div class="col-4 col-lg-auto"><button class="btn btn-secondary w-100" aria-label="'.h(T('search')).'">'.icon('search').'</button></div>'.(($qv!==''||$status!=='')?'<div class="col-12 col-lg-auto"><a class="btn btn-light w-100" href="'.h(query_link(['q'=>null,'status'=>null,'page'=>1])).'">'.icon('x-lg').' '.h(T('reset')).'</a></div>':'').'</form>';
}
function endpoint_copy_button($url,$label=null){return '<button type="button" class="btn btn-outline-dark js-copy" data-copy="'.h($url).'" aria-label="'.h(T('copy_endpoint')).'">'.icon('copy').' '.h($label?:T('copy_endpoint')).'</button>';}
function empty_state($title,$text,$cta=''){return '<div class="ios-surface p-5 text-center"><div class="display-6 mb-3 text-muted">'.icon('inbox').'</div><h2 class="h4">'.h($title).'</h2><p class="text-muted">'.h($text).'</p>'.$cta.'</div>';}
function universal_delete_button($label,$action,array $payload,$title='',$message='',$danger=true,$class='dropdown-item',$iconName='trash3',$confirmLabel=''){
    $confirmLabel=$confirmLabel?:T('delete');
    $iconOnly=str_contains($class,'btn-icon');
    $dangerClass=($danger&&str_contains($class,'dropdown-item'))?' text-danger':'';
    $content=icon($iconName).($iconOnly?'<span class="visually-hidden">'.h($label).'</span>':' '.h($label));
    $button='<button type="button" class="'.$class.$dangerClass.' js-delete-trigger" aria-label="'.h($label).'" title="'.h($label).'" data-delete-action="'.h($action).'" data-delete-payload="'.h(json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'" data-delete-title="'.h($title?:T('delete_confirm')).'" data-delete-message="'.h($message?:T('delete_irreversible')).'" data-delete-confirm="'.h($confirmLabel).'" data-delete-icon="'.h($iconName).'">'.$content.'</button>';
    return str_contains($class,'dropdown-item')?'<li>'.$button.'</li>':$button;
}
function universal_delete_modal(){return '<div class="modal fade" id="universalDeleteModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content" id="universalDeleteForm">'.token().'<input type="hidden" name="_a" id="universalDeleteAction"><div id="universalDeleteFields"></div><div class="modal-header"><h5 class="modal-title" id="universalDeleteTitle">'.h(T('delete_confirm')).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body"><div id="universalDeleteMessage" class="mb-0 text-preline"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger" id="universalDeleteConfirm"><span id="universalDeleteConfirmIcon">'.icon('trash3').'</span> <span id="universalDeleteConfirmLabel">'.h(T('delete')).'</span></button></div></form></div></div>';}
function collection_manage_buttons($c,$editMode='link',$labels=false,$includeDelete=true){
    if(!can_schema()||!is_array($c)||empty($c['id']))return '';
    $cid=(int)$c['id'];
    if($labels){
        $editClass='btn btn-secondary';
        $deleteClass='btn btn-outline-danger';
        $editContent=icon('pencil').' '.h(T('edit_collection'));
    }else{
        $editClass='btn btn-light btn-icon';
        $deleteClass='btn btn-outline-danger btn-icon';
        $editContent=icon('pencil').'<span class="visually-hidden">'.h(T('edit_collection')).'</span>';
    }
    $edit=$editMode==='modal'
        ?'<button type="button" class="'.$editClass.'" data-bs-toggle="modal" data-bs-target="#collectionEditModal" aria-label="'.h(T('edit_collection')).'" title="'.h(T('edit_collection')).'">'.$editContent.'</button>'
        :'<a class="'.$editClass.'" href="'.h(U(['collections'=>1,'edit_col'=>$cid])).'" aria-label="'.h(T('edit_collection')).'" title="'.h(T('edit_collection')).'">'.$editContent.'</a>';
    $delete=$includeDelete?universal_delete_button(T('delete_collection'),'del_col',['id'=>$cid],T('delete_collection'),collection_delete_message($c),true,$deleteClass,'trash3',T('delete_collection')):'';
    return '<div class="d-inline-flex align-items-center gap-1 flex-shrink-0">'.$edit.$delete.'</div>';
}
function collection_section_action_id($gid,$cid){return 'collectionSectionAction'.(int)$gid.'_'.(int)$cid;}
function collection_section_action_trigger($g,$c,$labels=false,$dismissOffcanvas=false){
    if(!can_schema()||!is_array($g)||!is_array($c)||empty($g['id'])||empty($c['id']))return '';
    $mid=collection_section_action_id((int)$g['id'],(int)$c['id']);
    $class=$labels?'btn btn-outline-danger btn-sm':'btn btn-outline-danger btn-icon';
    $content=icon('folder-minus').($labels?' '.h(T('collection_actions')):'<span class="visually-hidden">'.h(T('collection_actions')).'</span>');
    return '<button type="button" class="'.$class.'" data-bs-toggle="modal" data-bs-target="#'.h($mid).'" '.($dismissOffcanvas?'data-bs-dismiss="offcanvas" ':'').'aria-label="'.h(T('collection_actions')).'" title="'.h(T('collection_actions')).'">'.$content.'</button>';
}
function collection_section_action_modals_all(){
    if(!can_schema())return '';
    $return=stable_return_url($_SERVER['REQUEST_URI']??U(['collections'=>1]));$h='';
    foreach(groups() as $g)foreach(group_cols((int)$g['id']) as $c)$h.=collection_section_action_modal($g,$c,collection_section_action_id((int)$g['id'],(int)$c['id']),$return);
    return $h;
}

function group_manage_buttons($g,$editMode='link',$labels=false){
    if(!can_schema()||!is_array($g)||empty($g['id']))return '';
    $gid=(int)$g['id'];
    if($labels){
        $editClass='btn btn-secondary';
        $deleteClass='btn btn-outline-danger';
        $editContent=icon('pencil').' '.h(T('edit_group'));
    }else{
        $editClass='btn btn-light btn-icon';
        $deleteClass='btn btn-outline-danger btn-icon';
        $editContent=icon('pencil').'<span class="visually-hidden">'.h(T('edit_group')).'</span>';
    }
    $edit=$editMode==='modal'
        ?'<button type="button" class="'.$editClass.'" data-bs-toggle="modal" data-bs-target="#groupModal" aria-label="'.h(T('edit_group')).'" title="'.h(T('edit_group')).'">'.$editContent.'</button>'
        :'<a class="'.$editClass.'" href="'.h(U(['groups'=>1,'gid'=>$gid])).'" aria-label="'.h(T('edit_group')).'" title="'.h(T('edit_group')).'">'.$editContent.'</a>';
    $message=T('delete_group_q')."\n\n".T('collections').': '.group_collection_count($gid);
    $delete=universal_delete_button(T('delete_group'),'del_group',['id'=>$gid],T('delete_group'),$message,true,$deleteClass,'trash3',T('delete_group'));
    return '<div class="d-inline-flex align-items-center gap-1 flex-shrink-0">'.$edit.$delete.'</div>';
}

function language_disable_modal(){return '<div class="modal fade" id="languageDisableModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h(T('disable_language_title')).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body"><p>'.h(T('disable_language_warning')).'</p><div class="alert alert-warning mb-0" id="languageDisableList"></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button type="button" class="btn btn-danger" id="languageDisableConfirm">'.h(T('disable_language_confirm')).'</button></div></div></div></div>';}


/* FILES */
function uploads(){if(!is_dir(UPLOAD_DIR))@mkdir(UPLOAD_DIR,0775,true);return UPLOAD_DIR;}
function clean_ext($name){return strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));}
function file_url($name){return UPLOAD_URL.'/'.rawurlencode((string)$name);}
function mime_of($path){return function_exists('mime_content_type')&&is_file($path)?(mime_content_type($path)?:null):null;}
function file_out($r){return $r?['id'=>(int)$r['id'],'file_id'=>(int)$r['id'],'name'=>$r['onm'],'file'=>$r['fn'],'url'=>$r['u'],'size'=>(int)$r['sz'],'mime'=>$r['mime'],'ext'=>$r['ext'],'status'=>$r['st'],'origin_project_id'=>isset($r['opid'])?(int)$r['opid']:null,'origin_project_name'=>$r['opn']??null,'reason'=>$r['reason']??null,'created_at'=>$r['ca'],'updated_at'=>$r['ua']]:null;}
function file_cache_key($id,$pid){return 'file:'.(int)$pid.':'.(int)$id;}
function file_by_id($id,$pid=null){$id=(int)$id;$pid=(int)($pid?:current_project_id());return $id&&$pid?RequestCache::remember(file_cache_key($id,$pid),fn()=>one('SELECT * FROM files WHERE id=? AND pid=?',[$id,$pid])):null;}
function global_trash_file($id){return is_admin_user()?one("SELECT * FROM files WHERE id=? AND st='global_trash' AND pid IS NULL",[(int)$id]):null;}
function project_file_stats($pid){$r=one("SELECT COUNT(*) AS cnt,COALESCE(SUM(sz),0) AS total FROM files WHERE pid=? AND st!='deleted'",[(int)$pid]);return ['count'=>(int)($r['cnt']??0),'size'=>(int)($r['total']??0)];}
function file_from_value($v,$pid=null){$pid=$pid?:current_project_id();if(is_numeric($v))return file_out(file_by_id((int)$v,$pid));if(!is_array($v))return null;if(!empty($v['file_id']))return file_out(file_by_id((int)$v['file_id'],$pid))?:null;if(!empty($v['id']))return file_out(file_by_id((int)$v['id'],$pid))?:null;return !empty($v['file'])?$v:null;}
function save_file_row($orig,$name,$size,$ext,$mime){$n=now();return run('INSERT INTO files(pid,onm,fn,p,u,mime,ext,sz,st,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),$orig,$name,UPLOAD_URL.'/'.$name,file_url($name),$mime,$ext,$size,'active',$n,$n]);}
function nested_value($value,array $path,$default=null){foreach($path as $key){if(!is_array($value)||!array_key_exists($key,$value))return $default;$value=$value[$key];}return $value;}
function upload_text(string $ru,string $kk,string $en):string{return match(lang()){'kk'=>$kk,'en'=>$en,default=>$ru};}
function upload_capture_warning(callable $callback,?string &$warning=null):mixed{
    $warning=null;
    set_error_handler(static function(int $severity,string $message)use(&$warning):bool{$warning=$message;return true;});
    try{return $callback();}finally{restore_error_handler();}
}
function upload_warning_safe(?string $warning):string{
    $warning=trim((string)$warning);if($warning==='')return '';
    if(!debug_enabled()){
        $replace=[__DIR__=>'[CMS]'];$tmp=sys_get_temp_dir();if($tmp!=='')$replace[$tmp]='[TMP]';
        $warning=strtr($warning,$replace);
    }
    return $warning;
}
function upload_context(array $extra=[]):string{
    $ctx=array_merge([
        'upload_max_filesize'=>(string)ini_get('upload_max_filesize'),
        'post_max_size'=>(string)ini_get('post_max_size'),
        'CMS_limit'=>fmt_size(UPLOAD_MAX),
        'upload_tmp_dir'=>(string)(ini_get('upload_tmp_dir')?:sys_get_temp_dir()),
        'open_basedir'=>(string)(ini_get('open_basedir')?:'off'),
    ],$extra);
    $parts=[];foreach($ctx as $key=>$value)if($value!==null&&$value!=='')$parts[]=$key.'='.$value;
    return $parts?' ['.implode('; ',$parts).']':'';
}
function upload_error_by_code(int $code,string $filename=''):string{
    $file=$filename!==''?' «'.basename($filename).'»':'';
    $reason=match($code){
        UPLOAD_ERR_INI_SIZE=>upload_text('Размер файла превышает серверный лимит upload_max_filesize = '.ini_get('upload_max_filesize').'. PHP остановил загрузку до запуска CMS.','Файл өлшемі сервердің upload_max_filesize = '.ini_get('upload_max_filesize').' шегінен асады. PHP жүктеуді CMS іске қосылғанға дейін тоқтатты.','The file exceeds the server upload_max_filesize limit of '.ini_get('upload_max_filesize').'. PHP stopped the upload before the CMS ran.'),
        UPLOAD_ERR_FORM_SIZE=>upload_text('Размер файла превышает ограничение MAX_FILE_SIZE, переданное HTML-формой.','Файл өлшемі HTML форма жіберген MAX_FILE_SIZE шегінен асады.','The file exceeds the MAX_FILE_SIZE value sent by the HTML form.'),
        UPLOAD_ERR_PARTIAL=>upload_text('Файл был передан только частично. Обычно это обрыв соединения, тайм-аут или ограничение прокси/хостинга.','Файл тек ішінара жіберілді. Әдетте бұл байланыс үзілуі, тайм-аут немесе прокси/хостинг шектеуі.','The file was only partially uploaded. This usually means a dropped connection, timeout, or hosting/proxy limit.'),
        UPLOAD_ERR_NO_TMP_DIR=>upload_text('На сервере отсутствует временная папка для загрузок. Проверьте директиву upload_tmp_dir и системную временную директорию.','Серверде жүктеулерге арналған уақытша бума жоқ. upload_tmp_dir және жүйелік уақытша буманы тексеріңіз.','The server has no temporary upload directory. Check upload_tmp_dir and the system temporary directory.'),
        UPLOAD_ERR_CANT_WRITE=>upload_text('PHP не смог записать временный файл на диск. Возможны недостаточные права, отсутствие места или ограничение файловой системы.','PHP уақытша файлды дискіге жаза алмады. Құқықтар жеткіліксіз, бос орын жоқ немесе файлдық жүйе шектелген болуы мүмкін.','PHP could not write the temporary file to disk. Possible causes are permissions, no free space, or a filesystem restriction.'),
        UPLOAD_ERR_EXTENSION=>upload_text('Загрузка остановлена расширением PHP или защитным модулем хостинга. Проверьте журнал PHP и настройки расширений.','Жүктеуді PHP кеңейтімі немесе хостингтің қорғау модулі тоқтатты. PHP журналын және кеңейтім баптауларын тексеріңіз.','A PHP extension or hosting security module stopped the upload. Check the PHP log and extension settings.'),
        default=>upload_text('PHP вернул неизвестный код ошибки загрузки: '.$code.'.','PHP белгісіз жүктеу қатесінің кодын қайтарды: '.$code.'.','PHP returned an unknown upload error code: '.$code.'.'),
    };
    return T('upload_error_details').$file.': '.$reason.upload_context(['php_upload_error'=>$code]);
}
function upload_request_limit_error():?string{
    $length=(int)($_SERVER['CONTENT_LENGTH']??0);$limit=ini_bytes((string)ini_get('post_max_size'));
    if($length<=0||$limit<=0||$length<=$limit)return null;
    return upload_text(
        'Размер всего POST-запроса '.fmt_size($length).' превышает post_max_size = '.ini_get('post_max_size').'. PHP отбросил файл и поля формы ещё до запуска CMS.',
        'POST сұрауының толық өлшемі '.fmt_size($length).' post_max_size = '.ini_get('post_max_size').' шегінен асады. PHP файл мен форма өрістерін CMS іске қосылғанға дейін алып тастады.',
        'The complete POST request size of '.fmt_size($length).' exceeds post_max_size = '.ini_get('post_max_size').'. PHP discarded the file and form fields before the CMS ran.'
    ).upload_context(['content_length'=>fmt_size($length)]);
}
function upload_directory_ready(int $requiredBytes=0):string{
    $dir=UPLOAD_DIR;
    if(file_exists($dir)&&!is_dir($dir))throw new RuntimeException(upload_text('Путь uploads существует, но это не папка. Удалите или переименуйте этот файл.','uploads жолы бар, бірақ ол бума емес. Бұл файлды жойыңыз немесе атауын өзгертіңіз.','The uploads path exists but is not a directory. Remove or rename that file.').upload_context(['uploads'=>'[CMS]/uploads']));
    if(!is_dir($dir)){
        $warning=null;$created=upload_capture_warning(static fn()=>mkdir($dir,0775,true),$warning);clearstatcache(true,$dir);
        if(!$created&&!is_dir($dir))throw new RuntimeException(upload_text('Не удалось создать папку uploads. Проверьте права родительской папки.','uploads бумасын жасау мүмкін болмады. Негізгі буманың құқықтарын тексеріңіз.','Could not create the uploads directory. Check the parent directory permissions.').($warning?' Причина PHP: '.upload_warning_safe($warning):'').upload_context(['uploads'=>'[CMS]/uploads']));
    }
    if(!is_writable($dir))throw new RuntimeException(upload_text('Папка uploads существует, но PHP не имеет права записи в неё. Выдайте веб-серверу право записи.','uploads бумасы бар, бірақ PHP оған жаза алмайды. Веб-серверге жазу құқығын беріңіз.','The uploads directory exists, but PHP cannot write to it. Grant write permission to the web server user.').upload_context(['uploads'=>'[CMS]/uploads','permissions'=>substr(sprintf('%o',(int)@fileperms($dir)),-4)]));
    $probe=$dir.'/cms_write_test_'.bin2hex(random_bytes(4)).'.tmp';$warning=null;$written=upload_capture_warning(static fn()=>file_put_contents($probe,'ok',LOCK_EX),$warning);
    if($written===false){@unlink($probe);throw new RuntimeException(upload_text('Проверка записи в uploads не прошла. is_writable() сообщает доступ, но реальная запись запрещена хостингом, ACL или open_basedir.','uploads ішіне жазу тексерісі өтпеді. is_writable() рұқсат бар дейді, бірақ нақты жазуды хостинг, ACL немесе open_basedir тыйым салады.','The uploads write test failed. is_writable() reports access, but the actual write is blocked by hosting, ACL, or open_basedir.').($warning?' Причина PHP: '.upload_warning_safe($warning):'').upload_context(['uploads'=>'[CMS]/uploads']));
    }
    @unlink($probe);
    $free=@disk_free_space($dir);if($requiredBytes>0&&is_float($free)&&$free<$requiredBytes)throw new RuntimeException(upload_text('На диске недостаточно свободного места для файла.','Дискіде файлға жеткілікті бос орын жоқ.','There is not enough free disk space for the file.').upload_context(['required'=>fmt_size($requiredBytes),'free'=>fmt_size((int)$free)]));
    return $dir;
}
function upload_value($key,$type='file',$lang=null){
    $path=$lang===null?[(string)$key]:[(string)$lang,(string)$key];
    $name=(string)nested_value($_FILES['u']['name']??[],$path,'');
    $err=(int)nested_value($_FILES['u']['error']??[],$path,UPLOAD_ERR_NO_FILE);
    if($err===UPLOAD_ERR_NO_FILE)return null;
    if($err!==UPLOAD_ERR_OK)throw new RuntimeException(upload_error_by_code($err,$name));
    $orig=basename($name);if($orig==='')throw new RuntimeException(upload_text('PHP сообщил об успешной загрузке, но имя файла отсутствует.','PHP жүктеу сәтті деді, бірақ файл атауы жоқ.','PHP reported a successful upload, but the filename is missing.').upload_context(['php_upload_error'=>$err]));
    $size=(int)nested_value($_FILES['u']['size']??[],$path,0);
    if($size>UPLOAD_MAX)throw new RuntimeException(upload_text('Файл «'.$orig.'» имеет размер '.fmt_size($size).', а лимит CMS составляет '.fmt_size(UPLOAD_MAX).'.','«'.$orig.'» файлының өлшемі '.fmt_size($size).', ал CMS шегі '.fmt_size(UPLOAD_MAX).'.','The file “'.$orig.'” is '.fmt_size($size).', while the CMS limit is '.fmt_size(UPLOAD_MAX).'.').upload_context());
    $tmp=(string)nested_value($_FILES['u']['tmp_name']??[],$path,'');
    if($tmp===''||!is_file($tmp))throw new RuntimeException(upload_text('PHP сообщил об успешной загрузке «'.$orig.'», но временный файл отсутствует. Возможна очистка временной папки, ошибка upload_tmp_dir или ограничение хостинга.','PHP «'.$orig.'» жүктелді деді, бірақ уақытша файл жоқ. Уақытша бума тазаланған, upload_tmp_dir қатесі немесе хостинг шектеуі болуы мүмкін.','PHP reported that “'.$orig.'” uploaded successfully, but the temporary file is missing. The temporary directory may have been cleaned, misconfigured, or restricted by hosting.').upload_context(['tmp_exists'=>'no']));
    if(!is_uploaded_file($tmp))throw new RuntimeException(upload_text('Временный файл «'.$orig.'» не признан PHP как HTTP-загрузка. Запрос мог быть изменён прокси, WAF или конфигурацией сервера.','«'.$orig.'» уақытша файлын PHP HTTP жүктеуі деп танымады. Сұрауды прокси, WAF немесе сервер баптауы өзгерткен болуы мүмкін.','The temporary file for “'.$orig.'” is not recognized by PHP as an HTTP upload. A proxy, WAF, or server configuration may have altered the request.').upload_context(['is_uploaded_file'=>'false']));
    $ext=clean_ext($orig);$allowed=$type==='image'?IMAGE_EXT:FILE_EXT;
    if($ext===''||!in_array($ext,$allowed,true))throw new RuntimeException(upload_text('Расширение файла «'.$orig.'» не разрешено. Получено: '.($ext!==''?$ext:'без расширения').'. Разрешено: '.implode(', ',$allowed).'.','«'.$orig.'» файл кеңейтіміне рұқсат жоқ. Алынғаны: '.($ext!==''?$ext:'кеңейтімсіз').'. Рұқсат етілгені: '.implode(', ',$allowed).'.','The extension of “'.$orig.'” is not allowed. Received: '.($ext!==''?$ext:'no extension').'. Allowed: '.implode(', ',$allowed).'.'));
    if($type==='image'){
        $warning=null;$info=upload_capture_warning(static fn()=>getimagesize($tmp),$warning);
        if($info===false)throw new RuntimeException(upload_text('Файл «'.$orig.'» имеет допустимое расширение, но его содержимое не является корректным изображением или файл повреждён.','«'.$orig.'» кеңейтімі рұқсат етілген, бірақ мазмұны дұрыс сурет емес немесе файл бүлінген.','“'.$orig.'” has an allowed extension, but its content is not a valid image or the file is corrupted.').($warning?' Причина PHP: '.upload_warning_safe($warning):''));
    }
    $dir=upload_directory_ready($size);$base=slug(pathinfo($orig,PATHINFO_FILENAME));if($base==='')$base='file';
    $stored=date('Ymd_His').'_'.bin2hex(random_bytes(4)).'_'.$base.'.'.$ext;$to=$dir.'/'.$stored;
    if(!function_exists('move_uploaded_file'))throw new RuntimeException(upload_text('Функция PHP move_uploaded_file недоступна или отключена на хостинге.','PHP move_uploaded_file функциясы қолжетімсіз немесе хостингте өшірілген.','The PHP move_uploaded_file function is unavailable or disabled by the hosting provider.').upload_context());
    $warning=null;$moved=upload_capture_warning(static fn()=>move_uploaded_file($tmp,$to),$warning);
    if(!$moved||!is_file($to))throw new RuntimeException(upload_text('Не удалось переместить временный файл «'.$orig.'» в папку uploads. Проверьте права, open_basedir, свободное место и ограничения хостинга.','«'.$orig.'» уақытша файлын uploads бумасына жылжыту мүмкін болмады. Құқықтарды, open_basedir, бос орынды және хостинг шектеулерін тексеріңіз.','Could not move the temporary file “'.$orig.'” into the uploads directory. Check permissions, open_basedir, free space, and hosting restrictions.').($warning?' Причина PHP: '.upload_warning_safe($warning):'').upload_context(['destination'=>'[CMS]/uploads/'.$stored]));
    @chmod($to,0644);
    try{$fileId=save_file_row($orig,$stored,$size,$ext,mime_of($to));}
    catch(Throwable $e){@unlink($to);throw new RuntimeException(upload_text('Файл был записан в uploads, но CMS не смогла сохранить его данные в базе. Файл удалён, чтобы не оставить мусор. Причина базы: ','Файл uploads бумасына жазылды, бірақ CMS оның деректерін базаға сақтай алмады. Артық файл қалдырмау үшін файл жойылды. База себебі: ','The file was written to uploads, but the CMS could not save its metadata to the database. The file was removed to avoid an orphan. Database reason: ').$e->getMessage(),0,$e);}
    return ['file_id'=>$fileId];
}
function used_file_ids_names($pid=null){$pid=$pid?:current_project_id();$ids=[];$names=[];foreach(all('SELECT e.j FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[$pid]) as $row){$x=json_decode($row['j']??'{}',true);if(!is_array($x))continue;$walk=function($v)use(&$walk,&$ids,&$names){if(!is_array($v))return;if(!empty($v['file_id']))$ids[(int)$v['file_id']]=true;if(!empty($v['id'])&&!empty($v['file']))$ids[(int)$v['id']]=true;if(!empty($v['file'])&&is_string($v['file']))$names[basename($v['file'])]=true;foreach($v as $vv)$walk($vv);};$walk($x);}return [$ids,$names];}
function resolve_files($v,$pid=null){$pid=$pid?:current_project_id();if(!is_array($v))return $v;if(isset($v['file_id'])&&count($v)<=2){$f=file_out(file_by_id((int)$v['file_id'],$pid));return $f?:null;}foreach($v as $k=>$vv)$v[$k]=resolve_files($vv,$pid);return $v;}
function field_options($f){$x=json_decode($f['x']??'{}',true);return is_array($x)?$x:[];}
function field_i18n_map(array $f,bool $fillActive=true):array{
    $opt=field_options($f);$raw=is_array($opt['_i18n']??null)?$opt['_i18n']:[];$out=[];
    foreach($raw as $code=>$values)if(array_key_exists((string)$code,CONTENT_LANGS)&&is_array($values))$out[(string)$code]=['l'=>trim((string)($values['l']??'')),'placeholder'=>trim((string)($values['placeholder']??'')),'hint'=>trim((string)($values['hint']??'')),'choice_labels'=>is_array($values['choice_labels']??null)?$values['choice_labels']:[],'_translated'=>!empty($values['_translated'])];
    if($fillActive){$base=trim((string)($f['l']??''));$primary=default_content_lang();foreach(content_langs() as $code){$v=$out[$code]??[];$out[$code]=['l'=>trim((string)($v['l']??''))?:$base,'placeholder'=>trim((string)($v['placeholder']??'')),'hint'=>trim((string)($v['hint']??'')),'choice_labels'=>is_array($v['choice_labels']??null)?$v['choice_labels']:[],'_translated'=>array_key_exists($code,$out)?!empty($v['_translated']):$code===$primary];}}
    return $out;
}
function field_text(array $f,string $key,?string $lang=null):string{
    if($key==='l')return trim((string)($f['l']??$f['k']??''));
    $opt=field_options($f);$value=$opt[$key]??'';return is_scalar($value)?trim((string)$value):'';
}
function field_choice_labels(array $f,?string $lang=null):array{$labels=field_options($f)['choice_labels']??[];return is_array($labels)?$labels:[];}
function parse_choice_labels($raw):array{$out=[];foreach(preg_split('/\R/u',trim((string)$raw))?:[] as $line){$line=trim($line);if($line==='')continue;$parts=preg_split('/\s*=\s*/u',$line,2);$value=trim((string)($parts[0]??''));$label=trim((string)($parts[1]??$value));if($value!=='')$out[$value]=$label;}return $out;}
function choice_labels_text(array $labels):string{$rows=[];foreach($labels as $value=>$label)$rows[]=$value.' = '.$label;return implode("\n",$rows);}
function field_i18n_from_post(array $existing=[],$source=null):array{
    $existingMap=$existing?field_i18n_map($existing,false):[];$posted=$source===null?($_POST['field_i18n']??[]):$source;if(!is_array($posted))$posted=[];$langs=content_langs();$primary=$langs[0]??default_content_lang();$seedLabel='';
    foreach($langs as $code){$v=is_array($posted[$code]??null)?$posted[$code]:[];$label=trim((string)($v['l']??''));if($seedLabel===''&&$label!=='')$seedLabel=$label;}
    if($seedLabel==='')$seedLabel=trim((string)($existing['l']??''));if($seedLabel==='')throw new Exception(T('field_required'));
    $out=$existingMap;foreach($langs as $code){$v=is_array($posted[$code]??null)?$posted[$code]:[];$label=trim((string)($v['l']??''));$out[$code]=['l'=>$label!==''?$label:$seedLabel,'placeholder'=>trim((string)($v['placeholder']??'')),'hint'=>trim((string)($v['hint']??'')),'choice_labels'=>parse_choice_labels($v['choice_labels']??''),'_translated'=>!empty($v['_translated'])||$code===$primary];}return $out;
}
function merge_field_i18n_options(array $options,array $map):array{$options['_i18n']=$map;return $options;}
function nested_relation_scope_parent_cid(array $sourceCollection):int{return collection_is_nested($sourceCollection)?(int)($sourceCollection['parent_cid']??0):(int)($sourceCollection['id']??0);}
function nested_relation_target_allowed(array $sourceCollection,array $targetCollection):bool{return collection_is_nested($targetCollection)&&(int)($targetCollection['parent_cid']??0)===nested_relation_scope_parent_cid($sourceCollection)&&(int)($targetCollection['id']??0)!==(int)($sourceCollection['id']??0);}
function nested_relation_parent_entry_id(array $sourceCollection,int $sourceEntryId=0):int{
    if(collection_is_nested($sourceCollection)){
        if($sourceEntryId>0){$sourceEntry=entry($sourceEntryId);if($sourceEntry&&(int)($sourceEntry['cid']??0)===(int)$sourceCollection['id'])return (int)($sourceEntry['parent_eid']??0);}
        return (int)($_POST['parent_eid']??($_GET['parent_entry']??0));
    }
    return max(0,$sourceEntryId);
}
function nested_relation_parent_entry_from_row(array $sourceCollection,array $sourceEntry):int{return collection_is_nested($sourceCollection)?(int)($sourceEntry['parent_eid']??0):(int)($sourceEntry['id']??0);}
function field_options_from_post($t,$cid){
    $rules=validation_rules_from_array($_POST,true);$opt=$rules;$sourceCollection=$cid?col((int)$cid):null;
    if($t==='relation'){
        $target=(int)($_POST['rel_cid']??0);$mode=($_POST['rel_mode']??'single')==='multiple'?'multiple':'single';$targetCollection=$target?col($target):null;
        if(!$targetCollection||collection_is_nested($targetCollection))throw new Exception(T('relation_target_required'));
        $opt['target_collection_id']=$target;$opt['mode']=$mode;
    }
    if($t==='nested_relation'){
        $target=(int)($_POST['nested_rel_cid']??0);$mode=($_POST['nested_rel_mode']??'single')==='multiple'?'multiple':'single';$targetCollection=$target?col($target):null;
        if(!$sourceCollection||!$targetCollection||!nested_relation_target_allowed($sourceCollection,$targetCollection))throw new Exception(T('nested_relation_target_required'));
        $opt['target_collection_id']=$target;$opt['mode']=$mode;
    }
    return $opt?json_encode($opt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
}
function relation_target_options($currentCid=0){$opts=[''=>T('select_entry')];foreach(cols() as $c){if((int)$c['id']===(int)$currentCid)continue;$opts[(int)$c['id']]=$c['n'].' · '.$c['s'];}return $opts;}
function nested_relation_target_options($currentCid=0){
    $opts=[''=>T('select_entry')];$source=$currentCid?col((int)$currentCid):null;if(!$source)return $opts;$parentCid=nested_relation_scope_parent_cid($source);if(!$parentCid)return $opts;
    foreach(all('SELECT * FROM c WHERE pid=? AND parent_cid=? AND id!=? ORDER BY o,n,id',[current_project_id(),$parentCid,(int)$currentCid]) as $c){$c=localize_resource_row($c,resource_display_lang());$opts[(int)$c['id']]=(string)$c['n'].' · '.(string)$c['s'];}
    return $opts;
}
function relation_target_matches_type(array $field,array $target):bool{
    $type=(string)($field['t']??'relation');if($type!=='nested_relation')return !collection_is_nested($target);$source=col((int)($field['cid']??0));return $source?nested_relation_target_allowed($source,$target):false;
}
function relation_entries($targetCid,$lang=null,$sourceCid=0,$sourceEntryId=0){
    $targetCid=(int)$targetCid;$target=$targetCid?col($targetCid):null;if(!$target)return [];$nested=collection_is_nested($target);
    if($nested){$source=$sourceCid?col((int)$sourceCid):null;if(!$source||!nested_relation_target_allowed($source,$target))return [];$parentEntryId=nested_relation_parent_entry_id($source,(int)$sourceEntryId);if(!$parentEntryId)return [];$rows=all('SELECT e.* FROM e WHERE e.cid=? AND e.parent_eid=? ORDER BY e.t,e.id',[$targetCid,$parentEntryId]);}
    else $rows=all('SELECT * FROM e WHERE cid=? ORDER BY t,id',[$targetCid]);
    foreach($rows as &$row)$row['t']=entry_title($row,$lang?:resource_display_lang());unset($row);
    usort($rows,fn($a,$b)=>strnatcasecmp((string)$a['t'],(string)$b['t'])?:((int)$a['id']<=>(int)$b['id']));return $rows;
}
function relation_status_label($e){return ($e['st']??'draft')==='published'?T('published'):T('draft');}
function relation_entry_display_label(array $e,?string $lang=null):string{$label=entry_title($e,$lang?:resource_display_lang());$parent=trim((string)($e['parent_title']??''));return $parent!==''?$parent.' → '.$label:$label;}
function relation_option_label($e){return relation_entry_display_label($e,resource_display_lang()).' · '.$e['s'].' · '.relation_status_label($e);}
function relation_entry_cache_key($pid,$target,$id,$publicOnly=true,$parentEntryId=0){return 'relation-entry:'.(int)$pid.':'.(int)$target.':'.(int)$id.':'.($publicOnly?'1':'0').':'.(int)$parentEntryId;}
function relation_entry_for_field($f,$id,$publicOnly=true,int $expectedParentEntryId=0){
    $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$id=(int)$id;$pid=collection_project_id((int)($f['cid']??0));
    if(!$target||!$id||!$pid||!($targetCollection=col($target,$pid))||!relation_target_matches_type($f,$targetCollection))return null;
    $accessCollection=collection_is_nested($targetCollection)?collection_parent($targetCollection,$pid):$targetCollection;if(!$accessCollection)return null;if($publicOnly&&!api_private_collection_allowed($accessCollection))return null;if(collection_is_nested($targetCollection)&&$expectedParentEntryId<=0)return null;
    $key=relation_entry_cache_key($pid,$target,$id,$publicOnly,$expectedParentEntryId);
    return RequestCache::remember($key,function()use($id,$target,$pid,$publicOnly,$targetCollection,$expectedParentEntryId){
        if(collection_is_nested($targetCollection)){$sql='SELECT e.* FROM e JOIN c ON c.id=e.cid JOIN e pe ON pe.id=e.parent_eid AND pe.cid=c.parent_cid WHERE e.id=? AND e.cid=? AND c.pid=? AND e.parent_eid=?'.($publicOnly?" AND e.st='published' AND pe.st='published'":'').' LIMIT 1';return one($sql,[$id,$target,$pid,$expectedParentEntryId]);}
        $sql='SELECT e.* FROM e JOIN c ON c.id=e.cid WHERE e.id=? AND e.cid=? AND c.pid=?'.($publicOnly?" AND e.st='published'":'').' LIMIT 1';return one($sql,[$id,$target,$pid]);
    });
}
function relation_value_is_all($value):bool{return is_array($value)&&!empty($value['_all']);}
function relation_value_has_manual_ids($value):bool{
    if(relation_value_is_all($value))return false;
    foreach((array)$value as $id)if((int)$id>0)return true;
    return false;
}
function relation_all_marker():array{return ['_all'=>true];}
function relation_field_is_multiple(array $field):bool{return (field_options($field)['mode']??'single')==='multiple';}
function relation_request_mode_present(string $key):bool{
    $present=$_POST['relation_all_present']??null;
    return is_array($present)&&array_key_exists($key,$present);
}
function relation_request_auto_all(array $field):bool{
    if(!relation_field_is_multiple($field))return false;
    $key=(string)($field['k']??'');
    if($key===''||!relation_request_mode_present($key))return true;
    return !empty($_POST['relation_all'][$key]);
}
function relation_draft_apply_modes(array $data,int $cid):array{
    foreach(fields($cid) as $field){
        if(!field_is_relation_type((string)($field['t']??''))||!relation_field_is_multiple($field))continue;
        $key=(string)($field['k']??'');if($key===''||!relation_request_mode_present($key))continue;
        if(relation_request_auto_all($field)){$data[$key]=relation_all_marker();continue;}
        $raw=$data[$key]??[];if(relation_value_is_all($raw))$raw=[];$data[$key]=array_values(array_unique(array_filter(array_map('intval',(array)$raw))));
    }
    return $data;
}
function relation_value_ids(array $field,$value,bool $publicOnly=true,?array $sourceEntry=null):array{
    if(!relation_value_is_all($value))return array_values(array_unique(array_filter(array_map('intval',is_array($value)?$value:[$value]))));
    $opt=field_options($field);$target=(int)($opt['target_collection_id']??0);$targetCollection=$target?col($target):null;if(!$targetCollection||!relation_target_matches_type($field,$targetCollection))return [];
    if(collection_is_nested($targetCollection)){
        $sourceCollection=col((int)($field['cid']??0));if(!$sourceCollection||!$sourceEntry)return [];$parentEntryId=nested_relation_parent_entry_from_row($sourceCollection,$sourceEntry);if(!$parentEntryId)return [];
        $sql='SELECT e.id FROM e JOIN e pe ON pe.id=e.parent_eid WHERE e.cid=? AND e.parent_eid=?'.($publicOnly?" AND e.st='published' AND pe.st='published'":'').' ORDER BY e.t,e.id';$params=[$target,$parentEntryId];
    }else{$sql='SELECT id FROM e WHERE cid=?'.($publicOnly?" AND st='published'":'').' ORDER BY t,id';$params=[$target];}
    return array_map('intval',array_column(all($sql,$params),'id'));
}
function validate_relation_value($f,$v,int $sourceEntryId=0,bool $all=false){
    $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$multi=($opt['mode']??'single')==='multiple';$targetCollection=$target?col($target):null;
    if(!$targetCollection||!relation_target_matches_type($f,$targetCollection))throw new Exception(T(($f['t']??'relation')==='nested_relation'?'nested_relation_target_required':'relation_target_required'));
    if($multi&&$all){if(collection_is_nested($targetCollection)){$sourceCollection=col((int)($f['cid']??0));$parentEntryId=$sourceCollection?nested_relation_parent_entry_id($sourceCollection,$sourceEntryId):0;if(!$parentEntryId)throw new Exception(T('nested_relation_save_parent_first'));}return relation_all_marker();}
    if(relation_value_is_all($v))$v=[];$ids=$multi?array_values(array_unique(array_filter(array_map('intval',(array)$v)))):(((int)$v)?[(int)$v]:[]);$unique=array_values(array_unique($ids));
    if($unique){$marks=implode(',',array_fill(0,count($unique),'?'));if(collection_is_nested($targetCollection)){$sourceCollection=col((int)($f['cid']??0));$parentEntryId=$sourceCollection?nested_relation_parent_entry_id($sourceCollection,$sourceEntryId):0;if(!$parentEntryId)throw new Exception(T('nested_relation_save_parent_first'));$found=array_map('intval',array_column(all("SELECT id FROM e WHERE cid=? AND parent_eid=? AND id IN ($marks)",array_merge([$target,$parentEntryId],$unique)),'id'));}else{$found=array_map('intval',array_column(all("SELECT id FROM e WHERE cid=? AND id IN ($marks)",array_merge([$target],$unique)),'id'));}if(count(array_unique($found))!==count($unique))throw new Exception(T('relation_invalid_entry'));}
    return $multi?$ids:($ids[0]??null);
}
function collect_file_ids($value,array &$ids):void{
    if(!is_array($value))return;if(isset($value['file_id'])&&is_numeric($value['file_id']))$ids[(int)$value['file_id']]=true;
    foreach($value as $child)if(is_array($child))collect_file_ids($child,$ids);
}
function preload_files_for_entries(array $rows,int $pid):void{
    if(!$rows||!$pid)return;$ids=[];foreach($rows as $row)collect_file_ids(data($row),$ids);if(!$ids)return;$missing=[];
    foreach(array_keys($ids) as $id){$key=file_cache_key($id,$pid);if(!RequestCache::has($key)){$missing[]=(int)$id;RequestCache::set($key,null);}}
    foreach(array_chunk($missing,500) as $chunk){if(!$chunk)continue;$marks=implode(',',array_fill(0,count($chunk),'?'));foreach(all("SELECT * FROM files WHERE pid=? AND id IN ($marks)",array_merge([$pid],$chunk)) as $file)RequestCache::set(file_cache_key((int)$file['id'],$pid),$file);}
}
function relation_values_from_entry(array $entry,string $key,?array $field=null):array{
    $raw=data($entry);$maps=[];if(is_i18n($raw)){foreach(content_langs() as $code)if(is_array($raw[$code]??null))$maps[]=$raw[$code];}else $maps[]=$raw;$ids=[];$found=false;
    foreach($maps as $map){if(!array_key_exists($key,$map))continue;$found=true;$value=$map[$key];$values=$field?relation_value_ids($field,$value,true,$entry):(is_array($value)?$value:[$value]);foreach($values as $id)if((int)$id>0)$ids[(int)$id]=true;}
    if(!$found&&$field&&relation_field_is_multiple($field)){foreach(relation_value_ids($field,relation_all_marker(),true,$entry) as $id)if((int)$id>0)$ids[(int)$id]=true;}
    return array_keys($ids);
}
function preload_relations_for_entries(array $rows,int $cid,int $pid,$populate=true):array{
    if(!$rows||!$cid||!$pid||!api_populate_enabled($populate))return [];$targets=[];$sourceCollection=col($cid,$pid);if(!$sourceCollection)return [];
    foreach(fields($cid,$pid) as $field){
        if(!field_is_relation_type((string)($field['t']??'')))continue;$fieldKey=(string)$field['k'];if(!api_should_populate($populate,$fieldKey))continue;
        $opt=field_options($field);$target=(int)($opt['target_collection_id']??0);$targetCollection=$target?col($target,$pid):null;if(!$targetCollection||!relation_target_matches_type($field,$targetCollection))continue;
        $accessCollection=collection_is_nested($targetCollection)?collection_parent($targetCollection,$pid):$targetCollection;if(!$accessCollection||!api_private_collection_allowed($accessCollection))continue;
        foreach($rows as $entry){$parentEntryId=collection_is_nested($targetCollection)?nested_relation_parent_entry_from_row($sourceCollection,$entry):0;if(collection_is_nested($targetCollection)&&!$parentEntryId)continue;foreach(relation_values_from_entry($entry,$fieldKey,$field) as $id)$targets[$target][$parentEntryId][(int)$id]=true;}
    }
    $related=[];
    foreach($targets as $target=>$parentGroups){$targetCollection=col((int)$target,$pid);if(!$targetCollection)continue;
        foreach($parentGroups as $parentEntryId=>$idMap){$missing=[];foreach(array_keys($idMap) as $id){$key=relation_entry_cache_key($pid,(int)$target,(int)$id,true,(int)$parentEntryId);if(RequestCache::has($key)){if($cached=RequestCache::get($key))$related[]=$cached;continue;}$missing[]=(int)$id;RequestCache::set($key,null);}foreach(array_chunk($missing,500) as $chunk){if(!$chunk)continue;$marks=implode(',',array_fill(0,count($chunk),'?'));if(collection_is_nested($targetCollection)){$params=array_merge([(int)$target,$pid,(int)$parentEntryId],$chunk);$sql="SELECT e.* FROM e JOIN c ON c.id=e.cid JOIN e pe ON pe.id=e.parent_eid AND pe.cid=c.parent_cid WHERE e.cid=? AND c.pid=? AND e.parent_eid=? AND e.st='published' AND pe.st='published' AND e.id IN ($marks)";}else{$params=array_merge([(int)$target,$pid],$chunk);$sql="SELECT e.* FROM e JOIN c ON c.id=e.cid WHERE e.cid=? AND c.pid=? AND e.st='published' AND e.id IN ($marks)";}foreach(all($sql,$params) as $entry){RequestCache::set(relation_entry_cache_key($pid,(int)$target,(int)$entry['id'],true,(int)$parentEntryId),$entry);$related[]=$entry;}}}
    }
    return $related;
}
function preload_entry_dependencies(array $rows,int $cid,$populate=false):void{
    if(!$rows||!$cid)return;$pid=collection_project_id($cid);if(!$pid)return;$pending=[];$token=$populate===true?'*':($populate===false?'0':implode(',',array_values((array)$populate)));
    foreach($rows as $row){$key='entry-deps:'.(int)($row['id']??0).':'.(string)($row['ua']??'').':'.hash('sha1',$token);if(!RequestCache::has($key)){$pending[]=$row;RequestCache::set($key,true);}}
    if(!$pending)return;preload_files_for_entries($pending,$pid);if(api_populate_enabled($populate)){$related=preload_relations_for_entries($pending,$cid,$pid,$populate);if($related)preload_files_for_entries($related,$pid);}
}
function relation_entry_out($e,$l=null){
    if(!$e)return null;
    $pid=collection_project_id((int)($e['cid']??0));preload_files_for_entries([$e],$pid);$lang=$l&&in_array($l,content_langs(),true)?$l:default_content_lang();$raw=data($e);$entryData=is_i18n($raw)?data_lang($e,$lang,true):$raw;$collection=$pid?col((int)($e['cid']??0),$pid):null;
    $out=['id'=>(int)$e['id'],'title'=>entry_title($e,$lang),'slug'=>$e['s'],'status'=>$e['st'],'lang'=>$lang,'collection'=>$collection?['id'=>(int)$collection['id'],'name'=>resource_text($collection,'n',$lang),'slug'=>$collection['s'],'scope'=>collection_is_nested($collection)?'nested':'global']:null,'data'=>resolve_files($entryData,$pid),'created_at'=>$e['ca'],'updated_at'=>$e['ua']];
    if($collection&&collection_is_nested($collection)&&!empty($e['parent_eid'])){$parentEntry=one('SELECT * FROM e WHERE id=? AND cid=?',[(int)$e['parent_eid'],(int)$collection['parent_cid']]);$parentCollection=collection_parent($collection,$pid);if($parentEntry)$out['parent_entry']=['id'=>(int)$parentEntry['id'],'title'=>entry_title($parentEntry,$lang),'slug'=>$parentEntry['s'],'collection'=>$parentCollection?['id'=>(int)$parentCollection['id'],'name'=>(string)$parentCollection['n'],'slug'=>(string)$parentCollection['s']]:null];}
    return $out;
}
function relation_value_out($f,$v,$l=null,?array $sourceEntry=null){
    $opt=field_options($f);$mode=($opt['mode']??'single')==='multiple'?'multiple':'single';$sourceCollection=col((int)($f['cid']??0));$expectedParentEntryId=$sourceCollection&&$sourceEntry?nested_relation_parent_entry_from_row($sourceCollection,$sourceEntry):0;
    if($mode==='multiple'){$ids=relation_value_ids($f,$v,true,$sourceEntry);$out=[];foreach($ids as $id){$e=relation_entry_for_field($f,(int)$id,true,$expectedParentEntryId);if($e)$out[]=relation_entry_out($e,$l);}return $out;}
    $id=is_array($v)?(int)($v[0]??0):(int)$v;$e=relation_entry_for_field($f,$id,true,$expectedParentEntryId);return $e?relation_entry_out($e,$l):null;
}
function resolve_entry_data($cid,$data,$l=null,$populate=false,?array $sourceEntry=null,bool $implicitRelationDefaults=true){
    if(!is_array($data))return $data;
    if(is_i18n($data)){$lang=$l&&in_array($l,content_langs(),true)?$l:default_content_lang();$base=default_content_lang();$selected=is_array($data[$lang]??null)?$data[$lang]:[];if($lang!==$base)$selected=array_replace(is_array($data[$base]??null)?$data[$base]:[],$selected);$data=$selected;$l=$lang;}
    $pid=collection_project_id((int)$cid);$data=resolve_files($data,$pid?:current_project_id());if(!$cid)return $data;$relationFields=[];
    foreach(fields((int)$cid,$pid?:current_project_id()) as $f){
        if(!field_is_relation_type((string)($f['t']??'')))continue;
        $relationFields[]=$f;$k=(string)$f['k'];
        if($implicitRelationDefaults&&!array_key_exists($k,$data)&&relation_field_is_multiple($f))$data[$k]=relation_all_marker();
        if(array_key_exists($k,$data)&&relation_value_is_all($data[$k]))$data[$k]=relation_value_ids($f,$data[$k],true,$sourceEntry);
    }
    if(!api_populate_enabled($populate))return $data;foreach($relationFields as $f){$k=(string)$f['k'];if(api_should_populate($populate,$k)&&array_key_exists($k,$data))$data[$k]=relation_value_out($f,$data[$k],$l,$sourceEntry);}return $data;
}
function legacy_file_project_map(){
    $map=[];foreach(all('SELECT e.j,c.pid,p.n AS project_name FROM e JOIN c ON c.id=e.cid LEFT JOIN p ON p.id=c.pid') as $row){$json=(string)($row['j']??'');if($json==='')continue;preg_match_all('~\"file\"\s*:\s*\"([^\"]+)\"~u',$json,$m);foreach($m[1]??[] as $name){$fn=basename(stripslashes($name));if($fn==='')continue;$map[$fn][(int)$row['pid']]=$row['project_name']??('#'.$row['pid']);}}return $map;
}
function physical_orphan_files(){
    if(!is_admin_user())return [];
    $registered=array_fill_keys(array_column(all("SELECT fn FROM files WHERE st!='deleted'"),'fn'),true);$possible=legacy_file_project_map();$out=[];
    if(is_dir(UPLOAD_DIR))foreach(scandir(UPLOAD_DIR)?:[] as $fn){
        if($fn==='.'||$fn==='..'||!is_file(UPLOAD_DIR.'/'.$fn)||isset($registered[$fn]))continue;
        $path=UPLOAD_DIR.'/'.$fn;$out[]=['id'=>null,'file_id'=>null,'name'=>$fn,'file'=>$fn,'url'=>file_url($fn),'path'=>$path,'size'=>(int)filesize($path),'mime'=>mime_of($path),'ext'=>clean_ext($fn),'status'=>'orphan','created_at'=>null,'updated_at'=>date('Y-m-d H:i:s',filemtime($path)),'used'=>false,'project_id'=>null,'possible_projects'=>array_values($possible[$fn]??[]),'global_orphan'=>true];
    }
    usort($out,fn($a,$b)=>strcmp((string)$b['updated_at'],(string)$a['updated_at']));return $out;
}
function list_files($mode='active',$pid=null){
    if($mode==='global_trash'){
        if(!is_admin_user())return [];$out=[];
        foreach(all("SELECT * FROM files WHERE st='global_trash' AND pid IS NULL ORDER BY id DESC") as $r){$x=file_out($r);$x['used']=false;$x['project_id']=null;$x['global_orphan']=false;$out[]=$x;}return $out;
    }
    if($mode==='orphans')return physical_orphan_files();
    $pid=$pid?:current_project_id();[$ids,$names]=used_file_ids_names($pid);$where=$mode==='trash'?"st='trash'":"st='active'";$rows=all("SELECT * FROM files WHERE $where AND pid=? ORDER BY id DESC",[$pid]);$out=[];
    foreach($rows as $r){$used=isset($ids[(int)$r['id']])||isset($names[$r['fn']]);$x=file_out($r);$x['used']=$used;$x['project_id']=$pid;$x['global_orphan']=false;$out[]=$x;}
    if($mode==='unused'){$out=[];foreach(all("SELECT * FROM files WHERE st='active' AND pid=? ORDER BY id DESC",[$pid]) as $r){$used=isset($ids[(int)$r['id']])||isset($names[$r['fn']]);if(!$used){$x=file_out($r);$x['used']=false;$x['project_id']=$pid;$x['global_orphan']=false;$out[]=$x;}}}
    return $out;
}
function clean_files(){
    $moved=0;foreach(list_files('unused',current_project_id()) as $f){if(empty($f['id']))continue;$row=file_by_id((int)$f['id']);if(!$row)continue;q("UPDATE files SET st='trash',reason='unused',ua=? WHERE id=? AND pid=?",[now(),(int)$row['id'],current_project_id()]);$moved++;}return $moved;
}
function outEntry($e,$l=null,$populate=false){
    $cid=(int)($e['cid']??0);preload_entry_dependencies([$e],$cid,$populate);$default=default_content_lang();$lang=$l===null?null:(in_array((string)$l,content_langs(),true)?(string)$l:$default);$translated=entry_translated_map($e);$base=['id'=>(int)$e['id'],'title'=>entry_title($e,$lang?:$default),'slug'=>$e['s'],'status'=>$e['st'],'created_at'=>$e['ca'],'updated_at'=>$e['ua']];if(!empty($e['parent_eid']))$base['parent_entry']=(int)$e['parent_eid'];
    if($lang===null&&content_i18n_enabled()){
        $base['lang']='all';$base['i18n']=true;$base['default_lang']=$default;$base['languages']=content_langs();$base['translated_languages']=array_values(array_keys(array_filter($translated)));$base['title']=entry_title($e,$default);
        $global=entry_data_subset($cid,data_lang($e,$default,true),false);$base['data']=resolve_entry_data($cid,$global,$default,$populate,$e);$base['translations']=i18n_out($e,$populate);return $base;
    }
    $resolved=$lang?:$default;$raw=data($e);$entryData=is_i18n($raw)?data_lang($e,$resolved,true):$raw;$base['title']=entry_title($e,$resolved);$base['lang']=$resolved;$base['i18n']=content_i18n_enabled();if(content_i18n_enabled())$base['translated']=!empty($translated[$resolved]);$base['data']=resolve_entry_data($cid,$entryData,$resolved,$populate,$e);return $base;
}
function outField($f,$l=null,$pid=null){return api_field_out((array)$f,$l,$pid);}
function unique_collection_slug($base,$pid=null,$ignore=0){$pid=$pid?:current_project_id();$base=slug($base);$s=$base?:'collection';$i=2;while(one('SELECT id FROM c WHERE pid=? AND s=? AND id!=?',[$pid,$s,(int)$ignore]))$s=$base.'-'.$i++;return $s;}
function unique_entry_slug($base,$cid,$ignore=0){$cid=(int)$cid;$base=slug($base);$s=$base?:'entry';$i=2;while(one('SELECT id FROM e WHERE cid=? AND s=? AND id!=?',[$cid,$s,(int)$ignore]))$s=$base.'-'.$i++;return $s;}
function export_collection_schema_array($c){
    $fields=[];foreach(fields((int)$c['id']) as $f){$opt=field_options($f);unset($opt['_i18n']);if(field_is_relation_type((string)($f['t']??''))&&!empty($opt['target_collection_id'])){$tc=col((int)$opt['target_collection_id']);if($tc)$opt['target_collection_slug']=$tc['s'];}$fields[]=['label'=>$f['l'],'key'=>$f['k'],'type'=>$f['t'],'required'=>(bool)$f['r'],'order'=>(int)$f['o'],'options'=>$opt];}
    return ['schema'=>'mini-headless-cms.collection','version'=>3,'collection'=>['name'=>resource_base_value($c,'n'),'slug'=>$c['s'],'description'=>resource_base_value($c,'d'),'type'=>collection_mode($c),'order'=>(int)($c['o']??0),'access'=>api_access_mode($c)],'fields'=>$fields];
}
function import_collection_schema_array($schema,&$warnings=[]){
    $warnings=[];if(!is_array($schema)||($schema['schema']??'')!=='mini-headless-cms.collection')throw new Exception(T('invalid_schema'));$c=$schema['collection']??[];$fields=$schema['fields']??[];if(!is_array($c)||!is_array($fields))throw new Exception(T('invalid_schema'));
    $n=trim((string)($c['name']??'Imported collection'));$s=unique_collection_slug($c['slug']??$n);$m=($c['type']??'multiple')==='single'?'single':'multiple';$d=trim((string)($c['description']??''));$o=(int)($c['order']??0);$access=(($c['access']??'public')==='private')?'private':'public';$cors='*';$tm=now();
    $cid=run('INSERT INTO c(pid,n,s,d,i18n,m,o,access_mode,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),$n,$s,$d,'{}',$m,$o,$access,$cors,$tm,$tm]);
    foreach($fields as $f){if(!is_array($f))continue;$key=str_replace('-','_',slug($f['key']??($f['label']??'')));$label=trim((string)($f['label']??$key));$type=(string)($f['type']??'text');if(!$key||!$label)continue;if(!in_array($type,['text','text_global','textarea','ul_list','ol_list','ul_list_i18n','ol_list_i18n','html','email','tel','number','integer','date','datetime','bool','url','image','file','json','relation','nested_relation'],true))$type='text';$opt=$f['options']??[];if(!is_array($opt))$opt=[];unset($opt['_i18n']);if(field_is_relation_type($type)){$target=0;$tc=null;if(!empty($opt['target_collection_slug'])){$tc=col_by_slug((string)$opt['target_collection_slug']);if($tc)$target=(int)$tc['id'];}elseif(!empty($opt['target_collection_id'])){$tc=col((int)$opt['target_collection_id']);if($tc)$target=(int)$tc['id'];}$sourceCollection=col($cid);$validTarget=$tc&&($type==='nested_relation'?($sourceCollection&&nested_relation_target_allowed($sourceCollection,$tc)):!collection_is_nested($tc));if(!$target||!$validTarget){$type='text';$opt=[];$warnings['relation_target_missing']=true;}else{$opt=['target_collection_id'=>$target,'mode'=>(($opt['mode']??'single')==='multiple'?'multiple':'single')];}}$x=$opt?json_encode($opt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$label,$key,$type,$x,!empty($f['required'])?1:0,(int)($f['order']??0),$tm,$tm]);}
    ensure_default_api_key('collection',$cid);audit_event('collection.import','collection',$cid,$n);return $cid;
}
function clone_collection_schema($cid){
    $c=col((int)$cid);if(!$c)throw new Exception(T('access_denied'));$tm=now();$name=resource_base_value($c,'n').' Copy';$slug=unique_collection_slug($c['s'].'-copy');$access=api_access_mode($c);$new=run('INSERT INTO c(pid,parent_cid,n,s,d,i18n,m,o,access_mode,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),(int)($c['parent_cid']??0)?:null,$name,$slug,resource_base_value($c,'d'),'{}',collection_mode($c),(int)($c['o']??0)+1,$access,'*',$tm,$tm]);foreach(fields((int)$c['id']) as $f){$opt=field_options($f);unset($opt['_i18n']);$x=$opt?json_encode($opt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$new,$f['l'],$f['k'],$f['t'],$x,(int)$f['r'],(int)$f['o'],$tm,$tm]);}if(!collection_is_nested($c))ensure_default_api_key('collection',$new);return $new;
}
function field_value_empty($f,$v){$t=$f['t']??'text';if($t==='bool')return empty($v);if($t==='file'||$t==='image')return empty($v);if(field_is_list_type((string)$t))return count(array_filter(array_map(static fn($item)=>trim((string)$item),(array)$v),static fn($item)=>$item!==''))===0;if(field_is_relation_type((string)$t)){$opt=field_options($f);$multi=($opt['mode']??'single')==='multiple';if($multi&&relation_value_is_all($v))return false;return $multi?!count(array_filter(array_map('intval',(array)$v))):!(int)$v;}if($t==='json')return $v===null||$v===''||$v===[];return trim((string)$v)==='';}
function validate_required_value($f,$v){if(!empty($f['r'])&&field_value_empty($f,$v))throw new Exception(T('required_missing').': '.$f['l']);}
function normalize_list_value($value):array{
    $items=is_array($value)?$value:(preg_split('/\R/u',(string)$value)?:[]);$out=[];
    foreach($items as $item){if(is_array($item))continue;$item=trim((string)$item);if($item==='')continue;if(mb_strlen($item)>5000)$item=mb_substr($item,0,5000);$out[]=$item;if(count($out)>=500)break;}
    return array_values($out);
}
function normalize_collection_field_value(array $f,$v){
    $t=(string)($f['t']??'text');if($t==='json')return normalize_json_value($v);if(field_is_list_type($t))return normalize_list_value($v);if(is_array($v))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));$v=trim((string)$v);if($v==='')return '';
    if($t==='number'){if(!is_numeric($v))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));return (float)$v;}
    if($t==='integer'){if(filter_var($v,FILTER_VALIDATE_INT)===false)throw new Exception(sprintf(T('validation_failed_field'),$f['l']));return (int)$v;}
    if($t==='email'&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));
    if($t==='tel'){if(!preg_match('/^\+?[0-9\s().\-]{5,80}$/u',$v))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));$digits=preg_replace('/\D+/u','',$v);if(strlen((string)$digits)<5||strlen((string)$digits)>20)throw new Exception(sprintf(T('validation_failed_field'),$f['l']));}
    if($t==='url'){if(!filter_var($v,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($v,PHP_URL_SCHEME)),['http','https'],true))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));}
    if($t==='date'&&!form_valid_date($v))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));
    if($t==='datetime'&&!form_valid_datetime($v))throw new Exception(sprintf(T('validation_failed_field'),$f['l']));
    return $v;
}
function collection_mode($c){return (($c['m']??'multiple')==='single')?'single':'multiple';}
function collection_entries_out($c,$l=null,$populate=false){$rows=all("SELECT * FROM e WHERE cid=? AND st='published' ORDER BY id DESC",[$c['id']]);preload_entry_dependencies($rows,(int)$c['id'],$populate);if(collection_mode($c)==='single')return isset($rows[0])?outEntry($rows[0],$l,$populate):null;return array_map(fn($e)=>outEntry($e,$l,$populate),$rows);}
function outGroup($g,$l=null,$withFields=false,$populate=false,$allowPrivate=false){
    $pid=(int)($g['pid']??0);$items=[];$by=[];foreach(group_cols((int)$g['id'],$pid) as $c){if(api_access_mode($c)==='private'&&!$allowPrivate)continue;$payload=api_collection_entries_payload($c,$l,$populate);$x=api_collection_meta($c,$l);$x['data']=$payload['data'];$x['meta']=$payload['meta'];if($withFields)$x['fields']=array_map(fn($f)=>outField($f,$l,$pid),fields((int)$c['id'],$pid));$items[]=$x;$by[$c['s']]=count($items)-1;}
    $pr=project($pid);$out=api_group_meta($g,$l);$out['project']=api_project_meta($pr);$out['lang']=api_response_lang($l);$out['populate']=api_populate_response($populate);$out['collections']=$items;if(in_array(strtolower((string)($_GET['by_slug']??'')),['1','true','yes','on'],true))$out['by_slug']=$by;return $out;
}
function users(){return RequestCache::remember('users:list',fn()=>all('SELECT id,l,n,role,st,ca,ua FROM users ORDER BY id DESC'));}
function user_row($id){$id=(int)$id;return $id?RequestCache::remember('user:'.$id,fn()=>one('SELECT * FROM users WHERE id=?',[$id])):null;}
function current_user_id(){return (int)($_SESSION['uid']??0);}
function current_user(){return current_user_id()?user_row(current_user_id()):null;}
function global_role(){static $r=null;if($r!==null)return $r;$u=current_user();$role=$u['role']??'viewer';return $r=in_array($role,['admin','developer','editor','viewer'],true)?$role:'viewer';}
function current_role(){static $cache=[];$pid=current_project_id();if(isset($cache[$pid]))return $cache[$pid];if(global_role()==='admin')return $cache[$pid]='admin';$role=project_membership_role(current_user_id(),$pid);return $cache[$pid]=$role?:'viewer';}
function role_is($roles){return in_array(current_role(),(array)$roles,true);}
function is_admin_user(){return role_is('admin');}
function can_schema(){return role_is(['admin','developer']);}
function can_settings(){return role_is(['admin','developer']);}
function can_api(){return role_is(['admin','developer']);}
function can_entries(){return role_is(['admin','editor']);}
function can_files(){return role_is(['admin','editor']);}

function can_view_entries(){return role_is(['admin','developer','editor','viewer']);}
function can_forms(){return role_is(['admin','developer','editor']);}
function can_manage_forms(){return role_is(['admin','developer']);}
function can_view_form_submissions(){return role_is(['admin','editor']);}
function can_manage_form_submissions(){return role_is(['admin','editor']);}
function role_description($role){return T('role_'.$role.'_desc');}


/* AUDIT, DIAGNOSTICS, PROJECT SECURITY */
function audit_event(string $action,string $entity='',int $entityId=0,string $summary='',array $meta=[],$pid=null,$uid=null){
    try{$pid=$pid===null?(current_project_id()?:null):($pid?:null);$uid=$uid===null?(current_user_id()?:null):($uid?:null);$json=$meta?json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;run('INSERT INTO audit_log(pid,uid,action,entity,entity_id,summary,meta,ip,agent,ca)VALUES(?,?,?,?,?,?,?,?,?,?)',[$pid,$uid,$action,$entity,$entityId?:null,$summary,$json,client_ip(),mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000),now()]);}catch(Throwable $e){}
}
function auditPage(){
    require_perm(is_admin_user());$qv=trim((string)($_GET['q']??''));$action=trim((string)($_GET['action']??''));$page=max(1,(int)($_GET['page']??1));$per=50;$where='1=1';$params=[];
    if($qv!==''){$where.=' AND (a.summary LIKE ? OR a.entity LIKE ? OR a.action LIKE ? OR u.l LIKE ? OR u.n LIKE ? OR a.ip LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like,$like,$like,$like);}if($action!==''){$where.=' AND a.action=?';$params[]=$action;}
    $total=(int)q('SELECT COUNT(*) FROM audit_log a LEFT JOIN users u ON u.id=a.uid WHERE '.$where,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$rows=all('SELECT a.*,u.l AS user_login,u.n AS user_name,p.n AS project_name FROM audit_log a LEFT JOIN users u ON u.id=a.uid LEFT JOIN p ON p.id=a.pid WHERE '.$where.' ORDER BY a.id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);
    $actions=[''=>'—'];foreach(all('SELECT DISTINCT action FROM audit_log ORDER BY action') as $r)$actions[$r['action']]=$r['action'];$tools='<form class="row g-2 mb-3" method="get"><input type="hidden" name="audit" value="1"><div class="col-12 col-lg"><input class="form-control" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'"></div><div class="col-12 col-md-4">'.select_inline('action',$actions,$action).'</div><div class="col-auto"><button class="btn btn-secondary">'.icon('search').' '.h(T('apply')).'</button></div></form>';
    $h=page_head(T('audit_log'),h(T('audit_hint')));$table='<table class="table table-hover align-middle mb-0"><thead><tr><th>'.h(T('created')).'</th><th>'.h(T('audit_user')).'</th><th>'.h(T('audit_action')).'</th><th>'.h(T('audit_entity')).'</th><th>'.h(T('description')).'</th><th>'.h(T('audit_ip')).'</th></tr></thead><tbody>';
    foreach($rows as $r){$who=$r['user_name']?:$r['user_login']?:'system';$entity=trim(($r['entity']??'').' '.(!empty($r['entity_id'])?'#'.$r['entity_id']:''));$table.='<tr><td class="text-nowrap">'.h($r['ca']).'</td><td>'.h($who).'</td><td><code>'.h($r['action']).'</code></td><td>'.h($entity?:'—').'<small class="d-block text-muted">'.h($r['project_name']??'').'</small></td><td>'.h($r['summary']?:'—').'</td><td>'.h($r['ip']?:'—').'</td></tr>';}$table.='</tbody></table>';return $h.table_wrap($table,$tools,pager_html($m));
}
function ini_bytes($value){$value=trim((string)$value);if($value==='')return 0;$last=strtolower(substr($value,-1));$n=(float)$value;return (int)match($last){'g'=>$n*1073741824,'m'=>$n*1048576,'k'=>$n*1024,default=>$n};}
function diagnostic_item($name,$status,$detail){return ['name'=>$name,'status'=>$status,'detail'=>$detail];}
function diagnostics_report(){
    $items=[];$items[]=diagnostic_item('PHP >= 8.1',version_compare(PHP_VERSION,'8.1.0','>=')?'ok':'error',PHP_VERSION);
    foreach(['pdo','mbstring','json','openssl','fileinfo'] as $ext)$items[]=diagnostic_item('PHP extension: '.$ext,extension_loaded($ext)?'ok':'error',extension_loaded($ext)?'loaded':'missing');
    $driverExt=db_driver()==='sqlite'?'pdo_sqlite':'pdo_mysql';$items[]=diagnostic_item('Database driver: '.$driverExt,extension_loaded($driverExt)?'ok':'error',db_driver());
    $items[]=diagnostic_item('ZipArchive',class_exists('ZipArchive')?'ok':'warning',class_exists('ZipArchive')?'available':T('zip_required'));
    foreach([dirname(cfg_path())=>'storage',UPLOAD_DIR=>'uploads'] as $dir=>$label)$items[]=diagnostic_item($label.' writable',is_dir($dir)&&is_writable($dir)?'ok':'error',$dir);
    $items[]=diagnostic_item('config.json',cfg_exists()?'ok':'error',cfg_path());
    $secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$items[]=diagnostic_item('HTTPS',$secure?'ok':'warning',$secure?'enabled':'disabled');
    $storageInside=str_starts_with(realpath(dirname(cfg_path()))?:dirname(cfg_path()),realpath($_SERVER['DOCUMENT_ROOT']??'')?:'__none__');$protected=is_file(dirname(cfg_path()).'/.htaccess');$items[]=diagnostic_item('Storage web protection',(!$storageInside||$protected)?'ok':'warning',$storageInside?($protected?'.htaccess created':'storage is under document root'):'outside document root');
    $items[]=diagnostic_item('upload_max_filesize',ini_bytes(ini_get('upload_max_filesize'))>=UPLOAD_MAX?'ok':'warning',(string)ini_get('upload_max_filesize'));
    $items[]=diagnostic_item('post_max_size',ini_bytes(ini_get('post_max_size'))>=UPLOAD_MAX?'ok':'warning',(string)ini_get('post_max_size'));
    $items[]=diagnostic_item('mail()',function_exists('mail')?'ok':'warning',function_exists('mail')?'available':'unavailable');
    return $items;
}
function diagnosticsPage(){require_perm(is_admin_user());$rows=diagnostics_report();$h=page_head(T('diagnostics'),h(T('diagnostics_hint')));$table='<table class="table align-middle mb-0"><thead><tr><th>Check</th><th>'.h(T('status')).'</th><th>'.h(T('description')).'</th></tr></thead><tbody>';foreach($rows as $r){$cls=$r['status']==='ok'?'success':($r['status']==='warning'?'warning':'danger');$label=$r['status']==='ok'?T('diagnostic_ok'):($r['status']==='warning'?T('diagnostic_warning'):T('diagnostic_error'));$table.='<tr><td class="fw-semibold">'.h($r['name']).'</td><td><span class="badge text-bg-'.$cls.'">'.h($label).'</span></td><td><code>'.h($r['detail']).'</code></td></tr>';}$table.='</tbody></table>';return $h.table_wrap($table);}
function setup_diagnostics_report(){
    $items=[];$items[]=diagnostic_item('PHP >= 8.1',version_compare(PHP_VERSION,'8.1.0','>=')?'ok':'error',PHP_VERSION);
    foreach(['PDO','mbstring','json','openssl','fileinfo'] as $ext)$items[]=diagnostic_item('PHP extension: '.$ext,extension_loaded($ext)?'ok':'error',extension_loaded($ext)?'loaded':'missing');
    $items[]=diagnostic_item('PDO SQLite',extension_loaded('pdo_sqlite')?'ok':'warning',extension_loaded('pdo_sqlite')?'available':'missing');$items[]=diagnostic_item('PDO MySQL',extension_loaded('pdo_mysql')?'ok':'warning',extension_loaded('pdo_mysql')?'available':'missing');$items[]=diagnostic_item('ZipArchive',class_exists('ZipArchive')?'ok':'warning',class_exists('ZipArchive')?'available':T('zip_required'));
    foreach([dirname(SQLITE)=>'storage',UPLOAD_DIR=>'uploads'] as $dir=>$label){if(!is_dir($dir))@mkdir($dir,0775,true);$items[]=diagnostic_item($label.' writable',is_dir($dir)&&is_writable($dir)?'ok':'error',$dir);}
    return $items;
}
function diagnostics_compact_html(array $rows){$h='<div class="ios-surface p-3 mt-4"><div class="d-flex align-items-center gap-2 mb-3"><span class="btn btn-light btn-icon disabled">'.icon('heart-pulse').'</span><div><div class="fw-semibold">'.h(T('diagnostics')).'</div><div class="small text-muted">'.h(T('diagnostics_hint')).'</div></div></div><div class="d-grid gap-2">';foreach($rows as $r){$cls=$r['status']==='ok'?'success':($r['status']==='warning'?'warning':'danger');$h.='<div class="d-flex justify-content-between align-items-center gap-3 border rounded-3 p-2"><span class="small">'.h($r['name']).'</span><span class="badge text-bg-'.$cls.'">'.h($r['detail']).'</span></div>';}$h.='</div></div>';return $h;}

/* BACKUP AND RESTORE */
function unique_project_slug(string $base):string{$base=slug($base);$try=$base?:'restored-project';$i=2;while(project_by_slug($try))$try=$base.'-'.$i++;return $try;}
function backup_project_manifest(int $pid):array{
    $pr=project($pid);if(!$pr)throw new Exception(T('access_denied'));$c=all('SELECT * FROM c WHERE pid=? ORDER BY id',[$pid]);$cids=array_map('intval',array_column($c,'id'));$g=all('SELECT * FROM g WHERE pid=? ORDER BY id',[$pid]);$gids=array_map('intval',array_column($g,'id'));$forms=all('SELECT * FROM forms WHERE pid=? ORDER BY id',[$pid]);$fids=array_map('intval',array_column($forms,'id'));
    $in=function(array $ids){return $ids?implode(',',array_fill(0,count($ids),'?')):'NULL';};
    $fields=$cids?all('SELECT * FROM f WHERE cid IN ('.$in($cids).') ORDER BY id',$cids):[];$entries=$cids?all('SELECT * FROM e WHERE cid IN ('.$in($cids).') ORDER BY id',$cids):[];$links=$gids?all('SELECT * FROM gc WHERE gid IN ('.$in($gids).') ORDER BY id',$gids):[];$formFields=$fids?all('SELECT * FROM form_fields WHERE fid IN ('.$in($fids).') ORDER BY id',$fids):[];$subs=$fids?all('SELECT * FROM form_submissions WHERE fid IN ('.$in($fids).') ORDER BY id',$fids):[];$files=all("SELECT * FROM files WHERE pid=? AND st!='deleted' ORDER BY id",[$pid]);
    foreach($c as &$r){unset($r['api_key_hash'],$r['api_key_enc']);}$r=null;foreach($g as &$r){unset($r['api_key_hash'],$r['api_key_enc']);}$r=null;foreach($forms as &$r){unset($r['api_key_hash'],$r['api_key_enc']);}$r=null;
    return ['schema'=>'mini-headless-cms.project-backup','version'=>1,'created_at'=>now(),'app'=>APP,'project'=>$pr,'collections'=>$c,'fields'=>$fields,'entries'=>$entries,'groups'=>$g,'group_collections'=>$links,'forms'=>$forms,'form_fields'=>$formFields,'form_submissions'=>$subs,'files'=>$files];
}
function backup_project_download(int $pid):never{
    if(!class_exists('ZipArchive'))throw new Exception(T('zip_required'));$manifest=backup_project_manifest($pid);$pr=$manifest['project'];$tmp=tempnam(sys_get_temp_dir(),'cms_backup_');if($tmp===false)throw new Exception(T('backup_invalid'));@unlink($tmp);$zip=new ZipArchive();if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){@unlink($tmp);throw new Exception(T('backup_invalid'));}$zip->addFromString('backup.json',json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));foreach($manifest['files'] as $f){$fn=basename((string)($f['fn']??''));$path=UPLOAD_DIR.'/'.$fn;if($fn!==''&&is_file($path))$zip->addFile($path,'files/'.$fn);}$zip->close();audit_event('backup.download','project',$pid,T('backup_created'),['files'=>count($manifest['files'])]);$name='cms-backup-'.slug($pr['s']).'-'.date('Ymd-His').'.zip';header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
}
function remap_file_refs($v,array $fileMap){if(!is_array($v))return $v;if(array_key_exists('file_id',$v)&&is_numeric($v['file_id'])){$mapped=(int)($fileMap[(int)$v['file_id']]??0);if(!$mapped&&count($v)<=2)return null;$v['file_id']=$mapped?:null;}foreach($v as $k=>$vv)$v[$k]=remap_file_refs($vv,$fileMap);return $v;}
function remap_entry_relations(array $data,array $fieldDefs,array $entryMap,array $fileMap):array{
    $process=function(array $vals)use($fieldDefs,$entryMap,$fileMap){$vals=remap_file_refs($vals,$fileMap);foreach($fieldDefs as $def){$key=$def['k'];if(!array_key_exists($key,$vals)||!field_is_relation_type((string)$def['t']))continue;$opt=json_decode((string)($def['x']??'{}'),true)?:[];$multi=($opt['mode']??'single')==='multiple';if($multi){if(relation_value_is_all($vals[$key]))continue;$vals[$key]=array_values(array_filter(array_map(fn($id)=>$entryMap[(int)$id]??null,(array)$vals[$key])));}else $vals[$key]=$entryMap[(int)$vals[$key]]??null;}return $vals;};
    if(is_i18n($data)){foreach($data as $lang=>$vals)if($lang!=='_i18n'&&is_array($vals))$data[$lang]=$process($vals);return $data;}return $process($data);
}
function restore_project_backup(string $path):int{
    if(!class_exists('ZipArchive'))throw new Exception(T('zip_required'));$zip=new ZipArchive();if($zip->open($path)!==true)throw new Exception(T('backup_invalid'));$raw=$zip->getFromName('backup.json');if(!is_string($raw)){$zip->close();throw new Exception(T('backup_invalid'));}$m=json_decode($raw,true);if(!is_array($m)||($m['schema']??'')!=='mini-headless-cms.project-backup'||(int)($m['version']??0)!==1){$zip->close();throw new Exception(T('backup_invalid'));}
    $src=$m['project']??[];$name=trim((string)($src['n']??'Restored project')).' (restored)';$slug=unique_project_slug((string)($src['s']??$name).'-restored');$tm=now();$createdFiles=[];$pdo=D();$pdo->beginTransaction();try{
        $pid=run('INSERT INTO p(n,s,d,o,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?)',[$name,$slug,(string)($src['d']??''),(int)($src['o']??0),cors_origins_normalize($src['cors_origins']??'*'),$tm,$tm]);$fileMap=[];
        foreach((array)($m['files']??[]) as $f){if(!is_array($f))continue;$old=(int)($f['id']??0);$orig=(string)($f['onm']??$f['fn']??'file');$oldFn=basename((string)($f['fn']??''));$ext=clean_ext($oldFn?:$orig);$newFn=date('Ymd_His').'_'.bin2hex(random_bytes(5)).'_'.slug(pathinfo($orig,PATHINFO_FILENAME)).($ext!==''?'.'.$ext:'');$content=$oldFn!==''?$zip->getFromName('files/'.$oldFn):false;if(!is_string($content)){if($old)$fileMap[$old]=0;continue;}$target=uploads().'/'.$newFn;if(file_put_contents($target,$content,LOCK_EX)===false)throw new RuntimeException(T('upload_error'));$createdFiles[]=$target;$size=strlen($content);$newId=run('INSERT INTO files(pid,onm,fn,p,u,mime,ext,sz,st,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$pid,$orig,$newFn,UPLOAD_URL.'/'.$newFn,file_url($newFn),(string)($f['mime']??''),$ext,$size,in_array(($f['st']??'active'),['active','trash'],true)?$f['st']:'active',$tm,$tm]);if($old)$fileMap[$old]=$newId;}
        $collectionMap=[];$collectionParents=[];foreach((array)($m['collections']??[]) as $r){if(!is_array($r))continue;$old=(int)($r['id']??0);$new=run('INSERT INTO c(pid,parent_cid,n,s,d,i18n,m,o,access_mode,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$pid,null,(string)$r['n'],unique_collection_slug_for_pid((string)$r['s'],$pid),(string)($r['d']??''),(string)($r['i18n']??''),($r['m']??'multiple')==='single'?'single':'multiple',(int)($r['o']??0),api_access_mode($r),cors_origins_normalize($r['cors_origins']??'*'),$tm,$tm]);$collectionMap[$old]=$new;$collectionParents[$old]=(int)($r['parent_cid']??0);}foreach($collectionParents as $old=>$oldParent)if($oldParent&&isset($collectionMap[$old],$collectionMap[$oldParent]))q('UPDATE c SET parent_cid=? WHERE id=?',[$collectionMap[$oldParent],$collectionMap[$old]]);
        $fieldMap=[];foreach((array)($m['fields']??[]) as $r){if(!is_array($r)||empty($collectionMap[(int)($r['cid']??0)]))continue;$old=(int)($r['id']??0);$opt=json_decode((string)($r['x']??'{}'),true)?:[];if(!empty($opt['target_collection_id']))$opt['target_collection_id']=$collectionMap[(int)$opt['target_collection_id']]??0;$x=$opt?json_encode($opt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;$new=run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$collectionMap[(int)$r['cid']],(string)$r['l'],(string)$r['k'],(string)$r['t'],$x,(int)($r['r']??0),(int)($r['o']??0),$tm,$tm]);$fieldMap[$old]=$new;}
        $entryMap=[];$entryRows=[];foreach((array)($m['entries']??[]) as $r){if(!is_array($r)||empty($collectionMap[(int)($r['cid']??0)]))continue;$oldEntry=(int)($r['id']??0);$newCid=$collectionMap[(int)$r['cid']];$new=run('INSERT INTO e(cid,parent_eid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$newCid,null,current_user_id(),(string)$r['t'],unique_entry_slug((string)$r['s'],$newCid),(string)($r['st']??'draft'),'{}',(string)($r['ca']??$tm),(string)($r['ua']??$tm)]);$entryMap[$oldEntry]=$new;$entryRows[]=['new'=>$new,'old_id'=>$oldEntry,'old_parent'=>(int)($r['parent_eid']??0),'old_cid'=>(int)$r['cid'],'json'=>(string)($r['j']??'{}')];}foreach($entryRows as $er)if($er['old_parent']&&isset($entryMap[$er['old_parent']]))q('UPDATE e SET parent_eid=? WHERE id=?',[$entryMap[$er['old_parent']],$er['new']]);
        foreach($entryRows as $er){$d=json_decode($er['json'],true);if(!is_array($d))$d=[];$defs=[];foreach((array)($m['fields']??[]) as $fd)if((int)($fd['cid']??0)===$er['old_cid']){$copy=$fd;$opt=json_decode((string)($copy['x']??'{}'),true)?:[];if(!empty($opt['target_collection_id']))$opt['target_collection_id']=$collectionMap[(int)$opt['target_collection_id']]??0;$copy['x']=json_encode($opt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$defs[]=$copy;}$d=remap_entry_relations($d,$defs,$entryMap,$fileMap);q('UPDATE e SET j=? WHERE id=?',[json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$er['new']]);}
        $groupMap=[];foreach((array)($m['groups']??[]) as $r){if(!is_array($r))continue;$old=(int)($r['id']??0);$new=run('INSERT INTO g(pid,n,s,d,i18n,o,access_mode,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?)',[$pid,(string)$r['n'],unique_group_slug_for_pid((string)$r['s'],$pid),(string)($r['d']??''),(string)($r['i18n']??''),(int)($r['o']??0),api_access_mode($r),cors_origins_normalize($r['cors_origins']??'*'),$tm,$tm]);$groupMap[$old]=$new;}
        foreach((array)($m['group_collections']??[]) as $r)if(isset($groupMap[(int)($r['gid']??0)],$collectionMap[(int)($r['cid']??0)]))run('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[$groupMap[(int)$r['gid']],$collectionMap[(int)$r['cid']],(int)($r['o']??0)]);
        $formMap=[];foreach((array)($m['forms']??[]) as $r){if(!is_array($r))continue;$old=(int)($r['id']??0);$new=run('INSERT INTO forms(pid,n,s,d,i18n,st,success_message,o,retention_days,access_mode,cors_origins,notify_email,webhook_url,webhook_secret,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$pid,(string)$r['n'],unique_form_slug((string)$r['s'],$pid,0),(string)($r['d']??''),(string)($r['i18n']??'{}'),(string)($r['st']??'active'),(string)($r['success_message']??''),(int)($r['o']??0),(int)($r['retention_days']??0),api_access_mode($r),cors_origins_normalize($r['cors_origins']??'*'),(string)($r['notify_email']??''),(string)($r['webhook_url']??''),(string)($r['webhook_secret']??''),$tm,$tm]);$formMap[$old]=$new;}
        foreach((array)($m['form_fields']??[]) as $r)if(isset($formMap[(int)($r['fid']??0)]))run('INSERT INTO form_fields(pid,fid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?)',[$pid,$formMap[(int)$r['fid']],(string)$r['l'],(string)$r['k'],(string)$r['t'],$r['x']??null,(int)($r['r']??0),(int)($r['o']??0),$tm,$tm]);
        foreach((array)($m['form_submissions']??[]) as $r)if(isset($formMap[(int)($r['fid']??0)]))run('INSERT INTO form_submissions(pid,fid,st,j,ip,agent,ref,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$pid,$formMap[(int)$r['fid']],(string)($r['st']??'new'),(string)($r['j']??'{}'),(string)($r['ip']??''),(string)($r['agent']??''),(string)($r['ref']??''),(string)($r['ca']??$tm),(string)($r['ua']??$tm)]);
        if(global_role()!=='admin')run('INSERT INTO user_projects(uid,pid,role,ca,ua)VALUES(?,?,?,?,?)',[current_user_id(),$pid,current_role(),$tm,$tm]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($createdFiles as $created)if(is_file($created))@unlink($created);$zip->close();throw $e;}$zip->close();audit_event('backup.restore','project',$pid,T('backup_restored'),['source'=>$src['s']??''], $pid);return $pid;
}
function unique_collection_slug_for_pid($base,$pid){$base=slug($base);$s=$base?:'collection';$i=2;while(one('SELECT id FROM c WHERE pid=? AND s=?',[(int)$pid,$s]))$s=$base.'-'.$i++;return $s;}
function unique_group_slug_for_pid($base,$pid){$base=slug($base);$s=$base?:'group';$i=2;while(one('SELECT id FROM g WHERE pid=? AND s=?',[(int)$pid,$s]))$s=$base.'-'.$i++;return $s;}


function require_perm($ok){if(!$ok)throw new Exception(T('access_denied'));}
function api_require($ok){if(!$ok)api_error('auth_required',403,T('api_private'));}
function valid_password($p){return is_string($p)&&mb_strlen($p)>=10&&mb_strlen($p)<=72;}
function pass_ok($hash,$pass){return is_string($hash)&&$hash!==''&&password_verify((string)$pass,$hash);}
function client_ip(){return (string)($_SERVER['REMOTE_ADDR']??'local');}
function attempts_all(){if(!is_file(LOGIN_ATTEMPTS_FILE))return []; $x=json_decode((string)file_get_contents(LOGIN_ATTEMPTS_FILE),true);return is_array($x)?$x:[];}
function attempts_save($x){if(!is_dir(dirname(LOGIN_ATTEMPTS_FILE)))mkdir(dirname(LOGIN_ATTEMPTS_FILE),0775,true);file_put_contents(LOGIN_ATTEMPTS_FILE,json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function attempt_key($login){return sha1(client_ip().'|'.mb_strtolower(trim((string)$login)));}
function login_blocked($login){$a=attempts_all();$k=attempt_key($login);$r=$a[$k]??null;if(!$r)return false;if(($r['until']??0)>time())return true;if(($r['until']??0)>0){unset($a[$k]);attempts_save($a);}return false;}
function login_fail($login){$a=attempts_all();$k=attempt_key($login);$r=$a[$k]??['count'=>0,'until'=>0];$r['count']=(int)($r['count']??0)+1;$r['last']=time();if($r['count']>=LOGIN_MAX_FAILS)$r['until']=time()+LOGIN_BLOCK_SECONDS;$a[$k]=$r;attempts_save($a);}
function login_success($login){$a=attempts_all();$k=attempt_key($login);if(isset($a[$k])){unset($a[$k]);attempts_save($a);}}


/* FORMS */
function form_i18n_map(?array $form,bool $fillActive=true):array{
    $form=$form?:[];$raw=json_decode((string)($form['i18n']??''),true);$out=[];
    if(is_array($raw))foreach($raw as $code=>$values){
        if(!array_key_exists((string)$code,CONTENT_LANGS)||!is_array($values))continue;
        $out[(string)$code]=[
            'n'=>trim((string)($values['n']??'')),
            'd'=>trim((string)($values['d']??'')),
            'success_message'=>trim((string)($values['success_message']??'')),
            '_translated'=>!empty($values['_translated'])
        ];
    }
    if($fillActive){
        $baseN=trim((string)($form['n_base']??$form['n']??''));
        $baseD=trim((string)($form['d_base']??$form['d']??''));
        $baseM=trim((string)($form['success_message_base']??$form['success_message']??T('form_default_success')));
        if($baseM==='')$baseM=T('form_default_success');
        $primary=default_content_lang();
        foreach(content_langs() as $code){
            $exists=array_key_exists($code,$out);$v=$out[$code]??[];
            $out[$code]=[
                'n'=>trim((string)($v['n']??''))?:$baseN,
                'd'=>$baseD,
                'success_message'=>trim((string)($v['success_message']??''))?:$baseM,
                '_translated'=>$exists?!empty($v['_translated']):$code===$primary
            ];
        }
    }
    return $out;
}
function form_text(array $form,string $key,?string $lang=null):string{
    $baseKey=$key.'_base';$base=trim((string)($form[$baseKey]??$form[$key]??''));
    if(!content_i18n_enabled()||!in_array($key,['n','success_message'],true))return $base;
    $lang=$lang?:resource_display_lang();$map=form_i18n_map($form,false);$primary=default_content_lang();
    $value=trim((string)($map[$lang][$key]??''));if($value!=='')return $value;
    $fallback=trim((string)($map[$primary][$key]??''));return $fallback!==''?$fallback:$base;
}
function localize_form_row($form,?string $lang=null){
    if(!is_array($form))return $form;
    $form['n_base']=$form['n']??'';$form['d_base']=$form['d']??'';$form['success_message_base']=$form['success_message']??'';
    $lang=$lang?:resource_display_lang();$form['n']=form_text($form,'n',$lang);$form['d']=trim((string)$form['d_base']);$form['success_message']=form_text($form,'success_message',$lang);
    return $form;
}
function form_post_values(?array $existing=null):array{
    $existing=$existing?:[];$d=trim((string)($_POST['d']??($existing['d_base']??$existing['d']??'')));$langs=content_langs();$primary=default_content_lang();if(!in_array($primary,$langs,true))$primary=$langs[0]??default_content_lang();$existingMap=form_i18n_map($existing,false);
    if(!content_i18n_enabled()||count($langs)<=1){
        $n=trim((string)($_POST['n']??($existing['n_base']??$existing['n']??'')));$msg=trim((string)($_POST['success_message']??($existing['success_message_base']??$existing['success_message']??T('form_default_success'))));
        if($n==='')throw new Exception(T('name_required'));if($msg==='')$msg=T('form_default_success');
        $code=$langs[0]??$primary;$existingMap[$code]=['n'=>$n,'d'=>$d,'success_message'=>$msg,'_translated'=>true];
        $json=json_encode($existingMap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false)$json='{}';
        return [$n,$d,$msg,$json,$existingMap];
    }
    $posted=is_array($_POST['form_i18n']??null)?$_POST['form_i18n']:[];$seedName='';$seedMessage='';
    foreach($langs as $code){$v=is_array($posted[$code]??null)?$posted[$code]:[];$candidate=trim((string)($v['n']??''));if($seedName===''&&$candidate!=='')$seedName=$candidate;$candidate=trim((string)($v['success_message']??''));if($seedMessage===''&&$candidate!=='')$seedMessage=$candidate;}
    if($seedName==='')$seedName=trim((string)($existing['n_base']??$existing['n']??''));if($seedName==='')throw new Exception(T('name_required'));
    if($seedMessage==='')$seedMessage=trim((string)($existing['success_message_base']??$existing['success_message']??T('form_default_success')));if($seedMessage==='')$seedMessage=T('form_default_success');
    $map=$existingMap;
    foreach($langs as $code){
        $v=is_array($posted[$code]??null)?$posted[$code]:[];$n=trim((string)($v['n']??''));$msg=trim((string)($v['success_message']??''));
        $map[$code]=['n'=>$n!==''?$n:$seedName,'d'=>$d,'success_message'=>$msg!==''?$msg:$seedMessage,'_translated'=>!empty($v['_translated'])||$code===$primary];
    }
    $n=(string)$map[$primary]['n'];$msg=(string)$map[$primary]['success_message'];$json=json_encode($map,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($json===false)$json='{}';
    return [$n,$d,$msg,$json,$map];
}
function form_i18n_fields(?array $form,string $scope):string{
    $form=$form?:[];$langs=content_langs();
    if(!content_i18n_enabled()||count($langs)<=1)return '<div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$form['n_base']??$form['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div></div>'.area('success_message',T('form_success_message'),$form['success_message_base']??$form['success_message']??T('form_default_success'),['rows'=>'2']);
    $primary=default_content_lang();if(!in_array($primary,$langs,true))$primary=$langs[0];$map=form_i18n_map($form,true);$accordion='formI18n'.preg_replace('/[^a-zA-Z0-9_-]/','',(string)$scope);$items='';
    foreach($langs as $index=>$code){
        $v=$map[$code]??[];$isPrimary=$code===$primary;$translated=$isPrimary||!empty($v['_translated']);$heading=$accordion.'Heading'.$index;$collapse=$accordion.'Collapse'.$index;$show=$index===0;
        $name='form_i18n['.$code.'][n]';$message='form_i18n['.$code.'][success_message]';
        $fields='<input type="hidden" class="js-entry-translated" name="form_i18n['.h($code).'][_translated]" value="'.($translated?'1':'0').'">';
        $fields.='<div class="row g-3"><div class="col-12">'.inp($name,T('name'),$v['n']??'','text',['required'=>$isPrimary,'data-slug-source'=>$isPrimary?'s':null,'data-form-i18n-key'=>'n','data-form-autofill'=>$translated?'0':'1']).'</div></div>';
        $fields.=area($message,T('form_success_message'),$v['success_message']??T('form_default_success'),['rows'=>'2','data-form-i18n-key'=>'success_message','data-form-autofill'=>$translated?'0':'1']);
        $confirm=$isPrimary?'':'<button type="button" class="btn btn-sm btn-light js-entry-confirm-translation">'.icon('check2-circle').' '.h(T('confirm_translation')).'</button>';
        $items.='<div class="accordion-item js-entry-language" data-entry-primary="'.($isPrimary?'1':'0').'" data-translated="'.($translated?'1':'0').'"><h2 class="accordion-header" id="'.h($heading).'"><button class="accordion-button '.($show?'':'collapsed').'" type="button" data-bs-toggle="collapse" data-bs-target="#'.h($collapse).'" aria-expanded="'.($show?'true':'false').'" aria-controls="'.h($collapse).'"><span class="fw-semibold">'.h(CONTENT_LANGS[$code]??$code).'</span><code class="ms-2">'.h($code).'</code><span class="ms-auto me-2">'.translation_badge($translated).'</span></button></h2><div id="'.h($collapse).'" class="accordion-collapse collapse '.($show?'show':'').'" aria-labelledby="'.h($heading).'" data-bs-parent="#'.h($accordion).'"><div class="accordion-body">'.$fields.'<div class="d-flex justify-content-end">'.$confirm.'</div></div></div></div>';
    }
    return '<div class="alert alert-light border rounded-4 small">'.icon('translate').' '.h(T('form_i18n_hint')).'</div><div class="accordion mb-3 js-form-translations" id="'.h($accordion).'">'.$items.'</div>';
}
function forms_all(){$pid=current_project_id();$lang=resource_display_lang();return RequestCache::remember('forms:'.$pid.':'.$lang,fn()=>array_map(fn($row)=>localize_form_row($row,$lang),all("SELECT f.*,COALESCE(ff.field_count,0) AS field_count,COALESCE(fs.submission_count,0) AS submission_count,fs.last_submission FROM forms f LEFT JOIN (SELECT pid,fid,COUNT(*) field_count FROM form_fields GROUP BY pid,fid) ff ON ff.pid=f.pid AND ff.fid=f.id LEFT JOIN (SELECT pid,fid,COUNT(*) submission_count,MAX(ca) last_submission FROM form_submissions GROUP BY pid,fid) fs ON fs.pid=f.pid AND fs.fid=f.id WHERE f.pid=? ORDER BY f.o,f.n,f.id",[$pid])));}
function form_row($id){$id=(int)$id;$pid=current_project_id();$lang=resource_display_lang();return $id?RequestCache::remember('form:'.$pid.':'.$id.':'.$lang,fn()=>localize_form_row(one('SELECT * FROM forms WHERE id=? AND pid=?',[$id,$pid]),$lang)):null;}
function assert_form($id){$f=form_row((int)$id);if(!$f)throw new Exception(T('access_denied'));return $f;}
function form_field_types(){return ['text'=>T('type_text'),'textarea'=>T('type_textarea'),'email'=>T('type_email'),'tel'=>T('type_tel'),'number'=>T('type_number'),'integer'=>T('type_integer'),'boolean'=>T('type_boolean'),'date'=>T('type_date'),'datetime'=>T('type_datetime'),'url'=>T('type_url'),'json'=>T('type_json')];}
function form_fields_all($fid,$pid=null){$fid=(int)$fid;$pid=$pid===null?current_project_id():(int)$pid;return RequestCache::remember('form-fields:'.$pid.':'.$fid,fn()=>all('SELECT * FROM form_fields WHERE fid=? AND pid=? ORDER BY o,id',[$fid,$pid]));}
function form_field_text(array $field,?string $lang=null):string{
    $base=trim((string)($field['l']??$field['k']??''));if(!content_i18n_enabled())return $base;$lang=$lang?:resource_display_lang();$map=field_i18n_map($field,false);$primary=default_content_lang();
    $value=trim((string)($map[$lang]['l']??''));if($value!=='')return $value;$fallback=trim((string)($map[$primary]['l']??''));return $fallback!==''?$fallback:$base;
}
function form_field_labels_map($fid,$pid=null,$lang=null){$map=[];foreach(form_fields_all((int)$fid,$pid) as $field){$key=(string)($field['k']??'');$label=form_field_text($field,$lang?:resource_display_lang());if($key!=='')$map[$key]=$label!==''?$label:$key;}return $map;}
function form_field_display_name($key,array $labels=[]){$key=(string)$key;$label=trim((string)($labels[$key]??''));return $label!==''?$label:$key;}
function form_field_key_normalize($value){$key=str_replace('-','_',slug((string)$value));$key=preg_replace('/_+/','_',$key);$key=trim((string)$key,'_');if($key===''||$key==='item')$key='field';if(preg_match('/^[0-9]/',$key))$key='field_'.$key;return mb_substr($key,0,120);}
function validation_rules_from_array(array $src,bool $allowUnique=true){
    $r=[];foreach(['min_length','max_length'] as $k){$v=trim((string)($src[$k]??''));if($v!==''&&ctype_digit($v))$r[$k]=min(100000,max(0,(int)$v));}
    foreach(['min','max'] as $k){$v=trim((string)($src[$k]??''));if($v!==''&&is_numeric($v))$r[$k]=(float)$v;}
    $regex=trim((string)($src['regex']??''));if($regex!==''){$test=$regex;if(@preg_match($test,'')===false)$test='~'.str_replace('~','\\~',$regex).'~u';if(@preg_match($test,'')===false)throw new Exception(T('pattern_regex'));$r['regex']=$test;}
    $default=$src['default']??'';if(is_scalar($default)&&trim((string)$default)!=='')$r['default']=trim((string)$default);
    $choicesRaw=(string)($src['choices']??'');$choices=[];foreach(preg_split('/[\r\n]+/',$choicesRaw)?:[] as $v){$v=trim($v);if($v!=='')$choices[$v]=true;}if($choices)$r['choices']=array_keys($choices);
    if($allowUnique&&!empty($src['unique']))$r['unique']=true;
    if(isset($r['min_length'],$r['max_length'])&&$r['min_length']>$r['max_length'])throw new Exception(T('field_rules'));
    if(isset($r['min'],$r['max'])&&$r['min']>$r['max'])throw new Exception(T('field_rules'));
    return $r;
}
function validation_rules_from_options(array $options){$keys=['min_length','max_length','min','max','regex','default','choices','unique'];$out=[];foreach($keys as $k)if(array_key_exists($k,$options))$out[$k]=$options[$k];return $out;}
function field_rules($f){return validation_rules_from_options(field_options($f));}
function value_compare_token($v){return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
function entry_has_field_value(array $entry,string $key,$value){$d=data($entry);$token=value_compare_token($value);if(is_i18n($d)){foreach($d as $lang=>$vals)if($lang!=='_i18n'&&is_array($vals)&&array_key_exists($key,$vals)&&value_compare_token($vals[$key])===$token)return true;return false;}return array_key_exists($key,$d)&&value_compare_token($d[$key])===$token;}
function validate_field_rules(array $f,$value,int $entryId=0){
    $rules=field_rules($f);if(!$rules||field_value_empty($f,$value))return $value;$label=$f['l']??$f['k'];
    $values=field_is_list_type((string)($f['t']??''))?(array)$value:[$value];
    foreach($values as $item){if(!is_scalar($item))continue;$str=(string)$item;$len=mb_strlen($str);if(isset($rules['min_length'])&&$len<(int)$rules['min_length'])throw new Exception(sprintf(T('validation_failed_field'),$label));if(isset($rules['max_length'])&&$len>(int)$rules['max_length'])throw new Exception(sprintf(T('validation_failed_field'),$label));if(!empty($rules['regex'])&&@preg_match((string)$rules['regex'],$str)!==1)throw new Exception(sprintf(T('validation_failed_field'),$label));if(!empty($rules['choices'])&&!in_array($str,array_map('strval',(array)$rules['choices']),true))throw new Exception(sprintf(T('validation_failed_field'),$label));}
    if(is_numeric($value)){if(isset($rules['min'])&&(float)$value<(float)$rules['min'])throw new Exception(sprintf(T('validation_failed_field'),$label));if(isset($rules['max'])&&(float)$value>(float)$rules['max'])throw new Exception(sprintf(T('validation_failed_field'),$label));}
    if(!empty($rules['unique'])){foreach(all('SELECT * FROM e WHERE cid=? AND id<>?',[(int)$f['cid'],$entryId]) as $e)if(entry_has_field_value($e,(string)$f['k'],$value))throw new Exception(sprintf(T('unique_failed_field'),$label));}
    return $value;
}
function validate_form_rules(array $field,$value){$rules=validation_rules_from_options(json_decode((string)($field['x']??'{}'),true)?:[]);if(!$rules||form_value_empty($value))return $value;$label=$field['l']??$field['k'];if(is_scalar($value)){$str=(string)$value;$len=mb_strlen($str);if(isset($rules['min_length'])&&$len<(int)$rules['min_length'])form_validation_fail($field,'form_invalid_field_value','min_length');if(isset($rules['max_length'])&&$len>(int)$rules['max_length'])form_validation_fail($field,'form_value_too_long','max_length');if(!empty($rules['regex'])&&@preg_match((string)$rules['regex'],$str)!==1)form_validation_fail($field,'form_invalid_field_value','regex');if(!empty($rules['choices'])&&!in_array($str,array_map('strval',(array)$rules['choices']),true))form_validation_fail($field,'form_invalid_field_value','choice');}if(is_numeric($value)){if(isset($rules['min'])&&(float)$value<(float)$rules['min'])form_validation_fail($field,'form_invalid_field_value','min');if(isset($rules['max'])&&(float)$value>(float)$rules['max'])form_validation_fail($field,'form_invalid_field_value','max');}return $value;}

function form_fields_from_post(){
    $rows=$_POST['form_fields']??[];if(!is_array($rows))$rows=[];$types=form_field_types();$out=[];$seen=[];$pos=10;$langs=content_langs();$multi=content_i18n_enabled()&&count($langs)>1;$primary=default_content_lang();if(!in_array($primary,$langs,true))$primary=$langs[0]??default_content_lang();$pid=current_project_id();$formId=(int)($_POST['id']??0);
    foreach($rows as $row){
        if(!is_array($row))continue;$rawKey=trim((string)($row['k']??''));$legacyLabel=trim((string)($row['l']??''));$postedI18n=is_array($row['i18n']??null)?$row['i18n']:[];$seedLabel=$legacyLabel;
        if($multi)foreach($langs as $code){$v=is_array($postedI18n[$code]??null)?$postedI18n[$code]:[];$candidate=trim((string)($v['l']??''));if($seedLabel===''&&$candidate!=='')$seedLabel=$candidate;}
        $type=(string)($row['t']??'text');$required=!empty($row['r'])?1:0;$order=(int)($row['o']??$pos);if($seedLabel===''&&$rawKey==='')continue;$key=form_field_key_normalize($rawKey!==''?$rawKey:$seedLabel);
        if($seedLabel===''||!isset($types[$type])||!preg_match('/^[a-z][a-z0-9_]*$/',$key))throw new Exception(T('invalid_form_field'));if(isset($seen[$key]))throw new Exception(T('duplicate_form_field_key'));$seen[$key]=1;
        $rules=validation_rules_from_array($row,false);unset($rules['_i18n']);$sourceId=(int)($row['source_id']??0);$existing=$sourceId&&$formId?one('SELECT * FROM form_fields WHERE id=? AND pid=? AND fid=?',[$sourceId,$pid,$formId]):null;$i18n=$existing?field_i18n_map($existing,false):[];$label=$seedLabel;
        if($multi){
            foreach($langs as $code){$v=is_array($postedI18n[$code]??null)?$postedI18n[$code]:[];$value=trim((string)($v['l']??''));$i18n[$code]=['l'=>$value!==''?$value:$seedLabel,'placeholder'=>'','hint'=>'','choice_labels'=>[],'_translated'=>$code===$primary||$value!==''];}
            $label=trim((string)($i18n[$primary]['l']??''))?:$seedLabel;
        }
        if($i18n)$rules['_i18n']=$i18n;$out[]=['l'=>$label,'k'=>$key,'t'=>$type,'r'=>$required,'o'=>$order,'x'=>$rules?json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE):null];$pos+=10;
    }
    if(!$out)throw new Exception(T('form_fields_required'));usort($out,fn($a,$b)=>$a['o']<=>$b['o']);return $out;
}
function sync_form_fields($fid,$pid,array $defs){q('DELETE FROM form_fields WHERE fid=? AND pid=?',[(int)$fid,(int)$pid]);$tm=now();$o=10;foreach($defs as $f){run('INSERT INTO form_fields(pid,fid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?)',[(int)$pid,(int)$fid,$f['l'],$f['k'],$f['t'],$f['x']??null,(int)$f['r'],$o,$tm,$tm]);$o+=10;}}
function form_submission_row($id){return one('SELECT s.*,f.n AS form_name,f.s AS form_slug FROM form_submissions s JOIN forms f ON f.id=s.fid AND f.pid=s.pid WHERE s.id=? AND s.pid=?',[(int)$id,current_project_id()]);}
function assert_form_submission($id){$s=form_submission_row((int)$id);if(!$s)throw new Exception(T('access_denied'));return $s;}
function form_submission_retention_cleanup(array $form,$pid=null){
    $pid=$pid===null?(int)($form['pid']??current_project_id()):(int)$pid;$days=(int)($form['retention_days']??0);if(!in_array($days,[30,90,180,365],true))return 0;$cut=date('Y-m-d H:i:s',time()-$days*86400);return exec_count("DELETE FROM form_submissions WHERE pid=? AND fid=? AND st IN ('read','spam') AND ca<?",[$pid,(int)$form['id'],$cut]);
}
function form_submission_stats($fid,$pid=null){
    $pid=$pid===null?current_project_id():(int)$pid;$r=one("SELECT COUNT(*) AS total,SUM(CASE WHEN st='new' THEN 1 ELSE 0 END) AS new_count,SUM(CASE WHEN st='read' THEN 1 ELSE 0 END) AS read_count,SUM(CASE WHEN st='spam' THEN 1 ELSE 0 END) AS spam_count,COALESCE(SUM(LENGTH(j)),0) AS bytes FROM form_submissions WHERE fid=? AND pid=?",[(int)$fid,$pid]);return ['total'=>(int)($r['total']??0),'new'=>(int)($r['new_count']??0),'read'=>(int)($r['read_count']??0),'spam'=>(int)($r['spam_count']??0),'bytes'=>(int)($r['bytes']??0)];
}
function form_submission_scalar($value){if(is_bool($value))return $value?'Да':'Нет';if(is_array($value))return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);return trim((string)$value);}
function form_submission_summary_data(array $data){
    $priority=['name','full_name','fullname','fio','email','phone','tel','telephone'];$picked=[];$used=[];foreach($priority as $key)if(array_key_exists($key,$data)&&!is_array($data[$key])){$v=form_submission_scalar($data[$key]);if($v!==''){$picked[$key]=$v;$used[$key]=1;}if(count($picked)>=2)break;}if(count($picked)<2)foreach($data as $k=>$v){if(isset($used[$k])||is_array($v))continue;$sv=form_submission_scalar($v);if($sv==='')continue;$picked[(string)$k]=$sv;if(count($picked)>=2)break;}return $picked;
}
function form_submission_summary_html(array $data,array $labels=[]){
    $picked=form_submission_summary_data($data);if(!$picked)return '<span class="text-muted">—</span>';$parts=[];foreach($picked as $k=>$v){$name=form_field_display_name($k,$labels);$parts[]='<span class="text-truncate d-block"><span class="text-muted">'.h($name).':</span> '.h(mb_substr($v,0,90)).'</span>';}$more=max(0,count($data)-count($picked));if($more)$parts[]='<span class="badge text-bg-light mt-1">+'.h((string)$more).' '.h(T('fields_in_submission')).'</span>';return implode('',$parts);
}
function form_referrer_compact($ref){$ref=trim((string)$ref);if($ref==='')return '—';$host=parse_url($ref,PHP_URL_HOST);$path=parse_url($ref,PHP_URL_PATH);$label=($host?:'').($path?:'');return $label!==''?mb_substr($label,0,100):mb_substr($ref,0,100);}
function unique_form_slug($value,$pid,$ignore=0){$base=slug($value);$try=$base;$i=2;while(one('SELECT id FROM forms WHERE pid=? AND s=? AND id<>?',[(int)$pid,$try,(int)$ignore]))$try=$base.'-'.$i++;return $try;}
function form_endpoint($f,$projectSlug=null){
    if($projectSlug===null){$pr=current_project();$projectSlug=$pr['s']??'';}$query=http_build_query(['form'=>$f['s'],'project'=>$projectSlug]);$host=preg_replace('/[^a-z0-9.\-:\[\]]/i','',(string)($_SERVER['HTTP_HOST']??''));
    if($host==='')return './?'.$query;$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$scheme=$https?'https':'http';$script=(string)($_SERVER['SCRIPT_NAME']??'/index.php');return $scheme.'://'.$host.$script.'?'.$query;
}
function form_endpoint_lang($f,$projectSlug=null,?string $lang=null):string{
    $endpoint=form_endpoint($f,$projectSlug);$lang=$lang?:default_content_lang();
    return $endpoint.(str_contains($endpoint,'?')?'&':'?').http_build_query(['lang'=>$lang],'','&',PHP_QUERY_RFC3986);
}
function form_status_badge($status){$map=['active'=>'success','inactive'=>'secondary','new'=>'primary','read'=>'success','spam'=>'warning'];$key=$status==='new'?'new_status':($status==='read'?'read_status':($status==='spam'?'spam_status':$status));return '<span class="badge text-bg-'.h($map[$status]??'secondary').'">'.h(T($key)).'</span>';}
function form_project_id_public(){if(isset($_GET['project'])||isset($_GET['p'])){$p=project_by_slug($_GET['project']??$_GET['p']);return $p?(int)$p['id']:0;}return default_project_id();}
class FormValidationException extends RuntimeException{
    public string $fieldKey;
    public string $fieldType;
    public string $validationCode;
    public function __construct(string $message,string $fieldKey='',string $fieldType='',string $validationCode='invalid_value'){
        parent::__construct($message);$this->fieldKey=$fieldKey;$this->fieldType=$fieldType;$this->validationCode=$validationCode;
    }
}
function form_validation_fail(array $field,string $messageKey='form_invalid_field_value',string $code='invalid_value'){
    $key=(string)($field['k']??'');$type=(string)($field['t']??'');$label=trim((string)($field['l']??''));if($label==='')$label=$key!==''?$key:T('field');
    throw new FormValidationException(sprintf(T($messageKey),$label),$key,$type,$code);
}
function form_array_is_list(array $value){$i=0;foreach($value as $key=>$_){if($key!==$i++)return false;}return true;}
function form_has_uploaded_file($files){
    if(!is_array($files))return false;
    if(array_key_exists('error',$files)){
        $errors=$files['error'];if(is_array($errors)){foreach($errors as $error)if((int)$error!==UPLOAD_ERR_NO_FILE)return true;return false;}
        return (int)$errors!==UPLOAD_ERR_NO_FILE;
    }
    foreach($files as $value)if(form_has_uploaded_file($value))return true;return false;
}
function clean_form_value($value,$depth=0){if($depth>5)return null;if(is_array($value)){$out=[];$count=0;foreach($value as $k=>$v){if($count++>=100)break;$key=is_int($k)?$k:mb_substr(trim((string)$k),0,120);$out[$key]=clean_form_value($v,$depth+1);}return $out;}if(is_bool($value)||is_int($value)||is_float($value)||$value===null)return $value;return mb_substr(trim((string)$value),0,5000);}
function form_value_empty($value){return $value===null||$value===''||(is_array($value)&&count($value)===0);}
function sanitize_form_json_value($value,array $field,int $depth=0){
    if($depth>8)form_validation_fail($field,'form_invalid_field_value','json_depth');
    if(is_array($value)){
        if(count($value)>200)form_validation_fail($field,'form_invalid_field_value','json_items');
        $out=[];foreach($value as $key=>$item){
            if(!is_int($key)){$key=trim((string)$key);if($key===''||mb_strlen($key)>120)form_validation_fail($field,'form_invalid_field_value','json_key');}
            $out[$key]=sanitize_form_json_value($item,$field,$depth+1);
        }return $out;
    }
    if(is_string($value)){if(mb_strlen($value)>12000)form_validation_fail($field,'form_value_too_long','too_long');return trim($value);}
    if(is_float($value)&&(!is_finite($value)))form_validation_fail($field,'form_invalid_field_value','invalid_number');
    if(is_bool($value)||is_int($value)||is_float($value)||$value===null)return $value;
    form_validation_fail($field,'form_invalid_field_value','invalid_json');
}
function form_field_max_length(string $type){return match($type){'textarea'=>12000,'email'=>254,'tel'=>80,'url'=>2048,'date'=>10,'datetime'=>19,'number'=>128,'integer'=>64,'text'=>5000,default=>5000};}
function form_valid_date(string $value){
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$value,$m))return false;
    return checkdate((int)$m[2],(int)$m[3],(int)$m[1]);
}
function form_valid_datetime(string $value){
    if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/',$value,$m))return false;
    if(!checkdate((int)$m[2],(int)$m[3],(int)$m[1]))return false;
    $second=isset($m[6])&&$m[6]!==''?(int)$m[6]:0;return (int)$m[4]<=23&&(int)$m[5]<=59&&$second<=59;
}
function normalize_form_field_value(array $field,$value){
    $type=(string)($field['t']??'');$allowed=form_field_types();if(!isset($allowed[$type]))form_validation_fail($field,'form_invalid_field_value','unknown_type');
    if($type==='boolean'){
        if($value===null||$value==='')return false;
        if(is_bool($value))return $value;
        if(is_int($value)||is_float($value)){if($value===1||$value===1.0)return true;if($value===0||$value===0.0)return false;form_validation_fail($field,'form_invalid_field_value','invalid_boolean');}
        if(is_array($value)||is_object($value))form_validation_fail($field,'form_invalid_field_value','invalid_boolean');
        $v=mb_strtolower(trim((string)$value));
        if(in_array($v,['1','true','yes','on','да','иә'],true))return true;
        if(in_array($v,['0','false','no','off','нет','жоқ'],true))return false;
        form_validation_fail($field,'form_invalid_field_value','invalid_boolean');
    }
    if($value===null)return null;
    if($type==='json'){
        if(is_array($value))return sanitize_form_json_value($value,$field);
        if(is_bool($value)||is_int($value)||is_float($value))return sanitize_form_json_value($value,$field);
        if(!is_string($value))form_validation_fail($field,'form_invalid_field_value','invalid_json');
        if(mb_strlen($value)>65535)form_validation_fail($field,'form_value_too_long','too_long');
        try{$decoded=json_decode($value,true,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){form_validation_fail($field,'form_invalid_field_value','invalid_json');}
        return sanitize_form_json_value($decoded,$field);
    }
    if(is_array($value)||is_object($value))form_validation_fail($field,'form_invalid_field_value','invalid_scalar');
    if(is_bool($value))$value=$value?'1':'0';
    $v=trim((string)$value);if($v==='')return '';
    $max=form_field_max_length($type);if(mb_strlen($v)>$max)form_validation_fail($field,'form_value_too_long','too_long');
    if($type==='email'&&!filter_var($v,FILTER_VALIDATE_EMAIL))form_validation_fail($field);
    if($type==='tel'){
        if(!preg_match('/^\+?[0-9\s().\-]{5,80}$/u',$v))form_validation_fail($field);
        $digits=preg_replace('/\D+/u','',$v);$count=strlen((string)$digits);if($count<5||$count>20)form_validation_fail($field);
    }
    if($type==='url'){
        if(!filter_var($v,FILTER_VALIDATE_URL))form_validation_fail($field);
        $scheme=mb_strtolower((string)parse_url($v,PHP_URL_SCHEME));$host=(string)parse_url($v,PHP_URL_HOST);if(!in_array($scheme,['http','https'],true)||$host==='')form_validation_fail($field);
    }
    if($type==='number'){
        if(!preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?$/',$v)||!is_numeric($v))form_validation_fail($field);
        $number=(float)$v;if(!is_finite($number))form_validation_fail($field);return $number;
    }
    if($type==='integer'){
        if(!preg_match('/^[+-]?\d+$/',$v)||filter_var($v,FILTER_VALIDATE_INT)===false)form_validation_fail($field);return (int)$v;
    }
    if($type==='date'&&!form_valid_date($v))form_validation_fail($field);
    if($type==='datetime'&&!form_valid_datetime($v))form_validation_fail($field);
    return $v;
}
function validate_public_form_payload(array $form,array $payload,int $pid,?string $lang=null){
    $defs=form_fields_all((int)$form['id'],$pid);if(!$defs)throw new FormValidationException(T('form_fields_required'),'','','schema_missing');$lang=$lang?:default_content_lang();
    $allowed=[];foreach($defs as $field){$field['l']=form_field_text($field,$lang)?:($field['l']??$field['k']??'');$allowed[(string)$field['k']]=$field;}
    foreach($payload as $key=>$_){$key=(string)$key;if(!array_key_exists($key,$allowed))throw new FormValidationException(sprintf(T('form_unknown_field'),$key),$key,'','unknown_field');}
    $out=[];foreach($allowed as $field){
        $key=(string)$field['k'];$exists=array_key_exists($key,$payload);$opts=json_decode((string)($field['x']??'{}'),true)?:[];$raw=$exists?$payload[$key]:(array_key_exists('default',$opts)?$opts['default']:(($field['t']??'')==='boolean'?false:null));$value=validate_form_rules($field,normalize_form_field_value($field,$raw));
        if(!empty($field['r'])&&((($field['t']??'')==='boolean'&&$value!==true)||form_value_empty($value)))throw new FormValidationException(sprintf(T('form_required_field'),$field['l']),$key,(string)$field['t'],'required');
        $out[$key]=$value;
    }return $out;
}
function public_form_payload(){
    if(form_has_uploaded_file($_FILES??[]))throw new FormValidationException(T('form_files_ignored'),'file','file','files_not_allowed');
    $ct=strtolower((string)($_SERVER['CONTENT_TYPE']??''));$raw=[];
    if(str_contains($ct,'application/json')){
        $body=file_get_contents('php://input',false,null,0,65537);if($body===false)$body='';if(strlen($body)>65535)throw new FormValidationException(T('form_payload_too_large'),'','','payload_too_large');
        try{$decoded=json_decode((string)$body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new FormValidationException(T('form_invalid_json_body'),'','','invalid_json_body');}
        $trimmed=trim((string)$body);if(!is_array($decoded)||(form_array_is_list($decoded)&&$trimmed!=='{}'))throw new FormValidationException(T('form_invalid_json_body'),'','','invalid_json_body');$raw=$decoded;
    }else{
        $raw=$_POST;if(!is_array($raw))$raw=[];$probe=json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($probe===false||strlen($probe)>65535)throw new FormValidationException(T('form_payload_too_large'),'','','payload_too_large');
    }
    $honeypot='';foreach(['_hp','_website'] as $honeypotKey)if(array_key_exists($honeypotKey,$raw)&&!is_array($raw[$honeypotKey])){$honeypot=trim((string)$raw[$honeypotKey]);if($honeypot!=='')break;}
    foreach(['_csrf','_a','_form','form','project','api_key','_api_key','_hp','_website'] as $k)unset($raw[$k]);
    return [$raw,'{}',$honeypot];
}
function form_notification_payload(array $form,int $submissionId,array $payload,string $status='new'){$pr=project((int)$form['pid']);return ['event'=>'form.submitted','submission_id'=>$submissionId,'status'=>$status,'project'=>['id'=>(int)($pr['id']??0),'name'=>$pr['n']??'','slug'=>$pr['s']??''],'form'=>['id'=>(int)$form['id'],'name'=>$form['n'],'slug'=>$form['s']],'data'=>$payload,'received_at'=>now()];}
function dispatch_form_notifications(array $form,int $submissionId,array $payload,string $status='new'){
    $event=form_notification_payload($form,$submissionId,$payload,$status);$json=json_encode($event,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$email=trim((string)($form['notify_email']??''));
    if($email!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)&&function_exists('mail')){$subject='['.APP.'] '.$form['n'].' #'.$submissionId;$lines=[];foreach($payload as $k=>$v)$lines[]=$k.': '.(is_scalar($v)?(string)$v:json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$headers="Content-Type: text/plain; charset=UTF-8\r\n";@mail($email,$subject,implode("\n",$lines),$headers);}
    $url=trim((string)($form['webhook_url']??''));if($url!==''&&filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true)&&$json!==false){$secret=(string)($form['webhook_secret']??'');$signature='sha256='.hash_hmac('sha256',$json,$secret);$headers=['Content-Type: application/json','Accept: application/json','X-CMS-Event: form.submitted','X-CMS-Signature: '.$signature];try{if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$json,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);curl_exec($ch);curl_close($ch);}else{$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$json,'timeout'=>4,'ignore_errors'=>true]]);@file_get_contents($url,false,$ctx);}}catch(Throwable $e){audit_event('form.webhook_failed','form',(int)$form['id'],$e->getMessage(),[],(int)$form['pid'],null);}}
    if($status==='new')telegram_notify_submission($form,$submissionId,$payload);
}
function form_export_filters(int $fid,int $pid){$where='s.pid=? AND s.fid=?';$params=[$pid,$fid];$status=in_array((string)($_GET['status']??''),['new','read','spam'],true)?(string)$_GET['status']:'';$dateFrom=trim((string)($_GET['date_from']??''));$dateTo=trim((string)($_GET['date_to']??''));$qv=trim((string)($_GET['q']??''));if($status!==''){$where.=' AND s.st=?';$params[]=$status;}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)){$where.=' AND s.ca>=?';$params[]=$dateFrom.' 00:00:00';}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)){$where.=' AND s.ca<=?';$params[]=$dateTo.' 23:59:59';}if($qv!==''){$where.=' AND (s.j LIKE ? OR s.ip LIKE ? OR s.ref LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}return [$where,$params];}
function form_submissions_export_endpoint(){
    if(!isset($_GET['form_export']))return false;if(!ok()||!can_view_form_submissions()){http_response_code(403);exit;}$fid=(int)$_GET['form_export'];$f=assert_form($fid);$format=($_GET['format']??'csv')==='json'?'json':'csv';[$where,$params]=form_export_filters($fid,current_project_id());$rows=all('SELECT * FROM form_submissions s WHERE '.$where.' ORDER BY s.id DESC',$params);$labels=form_field_labels_map($fid);$allKeys=[];foreach(form_fields_all($fid) as $ff)$allKeys[]=$ff['k'];foreach($rows as $r){$d=json_decode((string)$r['j'],true);if(is_array($d))foreach(array_keys($d) as $k)if(!in_array($k,$allKeys,true))$allKeys[]=$k;}
    $base=slug($f['s']).'-submissions-'.date('Ymd-His');audit_event('form.export','form',$fid,strtoupper($format).' · '.count($rows));
    if($format==='json'){header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$base.'.json"');$out=[];foreach($rows as $r){$d=json_decode((string)$r['j'],true);$out[]=['id'=>(int)$r['id'],'status'=>$r['st'],'data'=>is_array($d)?$d:[],'ip'=>$r['ip'],'user_agent'=>$r['agent'],'referrer'=>$r['ref'],'created_at'=>$r['ca']];}echo json_encode(['form'=>['id'=>$fid,'name'=>$f['n'],'slug'=>$f['s']],'exported_at'=>now(),'data'=>$out],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;}
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$base.'.csv"');echo "\xEF\xBB\xBF";$fh=fopen('php://output','w');$head=['id','status','created_at','ip','referrer'];foreach($allKeys as $k)$head[]=form_field_display_name($k,$labels);fputcsv($fh,$head,';');foreach($rows as $r){$d=json_decode((string)$r['j'],true);if(!is_array($d))$d=[];$line=[(int)$r['id'],$r['st'],$r['ca'],$r['ip'],$r['ref']];foreach($allKeys as $k)$line[]=is_scalar($d[$k]??null)?(string)($d[$k]??''):json_encode($d[$k]??null,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);fputcsv($fh,$line,';');}fclose($fh);exit;
}

function public_form_endpoint(){
    if(!array_key_exists('form',$_GET))return false;
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));if(!in_array($method,['GET','HEAD','POST','OPTIONS'],true)){header('Allow: GET, HEAD, POST, OPTIONS');api_error('method_not_allowed',405,'Method not allowed.');}
    $pid=form_project_id_public();if(!$pid)api_error('project_not_found',404,'Project not found.');
    $slugValue=trim((string)($_GET['form']??''));if($slugValue==='')$slugValue=trim((string)($_POST['_form']??$_POST['form']??''));if($slugValue==='')api_error('form_required',400,T('form_not_found'));$slugValue=slug($slugValue);
    $f=one("SELECT * FROM forms WHERE pid=? AND s=?",[$pid,$slugValue]);if(!$f)api_error('form_not_found',404,T('form_not_found'));apply_resource_cors($f);if($method==='OPTIONS'){http_response_code(204);exit;}
    if(($f['st']??'inactive')!=='active')api_error('form_inactive',410,T('form_inactive'));require_resource_api_access($f,'form');$project=project($pid);$lang=api_content_lang();$displayLang=$lang?:default_content_lang();
    if(in_array($method,['GET','HEAD'],true)){
        $private=api_access_mode($f)==='private';if(!$private){$cacheKey=ResponseCache::key('form:schema:'.$pid.':'.(int)$f['id']);if(ResponseCache::serve($cacheKey,API_CACHE_TTL))exit;$GLOBALS['_api_response_cache_key']=$cacheKey;}
        $schema=array_map(fn($field)=>api_form_field_out($field,$lang),form_fields_all((int)$f['id'],$pid));$payload=['ok'=>true,'api_version'=>API_VERSION,'project'=>api_project_meta($project)]+api_language_context($lang);$payload['form']=api_form_meta($f,$lang);$payload['form']['fields']=$schema;$payload['endpoint']=form_endpoint_lang($f,$project['s']??'',$displayLang);$payload['method']='POST';$payload['accept']=['application/x-www-form-urlencoded','multipart/form-data','application/json'];$payload['files']=false;$fieldLast=scalar('SELECT MAX(ua) FROM form_fields WHERE fid=? AND pid=?',[(int)$f['id'],$pid]);J_api($payload,200,API_CACHE_TTL,api_latest_modified($f['ua']??$f['ca'],$fieldLast),$private);
    }
    try{[$payload,$json,$honeypot]=public_form_payload();$payload=validate_public_form_payload($f,$payload,$pid,$displayLang);$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)$json='{}';}
    catch(FormValidationException $e){$details=['validation_code'=>$e->validationCode];if($e->fieldKey!=='')$details['field']=$e->fieldKey;if($e->fieldType!=='')$details['expected_type']=$e->fieldType;api_error('validation_failed',422,$e->getMessage(),$details);}
    catch(Throwable $e){api_error('validation_failed',422,$e->getMessage());}
    if(!$payload&&$honeypot==='')api_error('empty_payload',422,T('form_payload_empty'));
    form_submission_retention_cleanup($f,$pid);$ip=client_ip();$cutoff=date('Y-m-d H:i:s',time()-600);$recent=(int)q('SELECT COUNT(*) FROM form_submissions WHERE fid=? AND pid=? AND ip=? AND ca>=?',[(int)$f['id'],$pid,$ip,$cutoff])->fetchColumn();if($recent>=20){header('Retry-After: 600');api_error('rate_limited',429,T('form_rate_limited'),['retry_after'=>600]);}
    $status=$honeypot!==''?'spam':'new';$tm=now();$id=run('INSERT INTO form_submissions(pid,fid,st,j,ip,agent,ref,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$pid,(int)$f['id'],$status,$json,$ip,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000),mb_substr((string)($_SERVER['HTTP_REFERER']??''),0,2000),$tm,$tm]);
    dispatch_form_notifications($f,$id,$payload,$status);audit_event('form.submitted','form',(int)$f['id'],'Submission #'.$id,['submission_id'=>$id],$pid,null);$message=form_text($f,'success_message',$displayLang);if($message==='')$message=T('form_default_success');J_api(['ok'=>true,'api_version'=>API_VERSION,'project'=>api_project_meta($project),'lang'=>$displayLang,'submission_id'=>$id,'message'=>$message],201,0,null,true);
}



/* RESOURCE ACCESS */
function b64u_encode($v){return rtrim(strtr(base64_encode((string)$v),'+/','-_'),'=');}
function b64u_decode($v){$v=strtr((string)$v,'-_','+/');$v.=str_repeat('=',(4-strlen($v)%4)%4);$x=base64_decode($v,true);return $x===false?'':$x;}
function api_master_secret(){
    $cfg=cfg_read();$encoded=(string)($cfg['security']['api_master_secret']??'');$raw=$encoded!==''?b64u_decode($encoded):'';
    if(strlen($raw)!==32){$raw=random_bytes(32);$cfg['security']['api_master_secret']=b64u_encode($raw);cfg_write($cfg);}return $raw;
}
function api_key_generate(){return 'cms_'.b64u_encode(random_bytes(32));}
function api_key_hash_value($key){return hash('sha256',(string)$key);}
function api_key_encrypt($plain){
    $plain=(string)$plain;if($plain==='')return '';$key=api_master_secret();
    if(function_exists('openssl_encrypt')){$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'cms-api-key');if($cipher!==false)return 'v1.'.b64u_encode($iv.$tag.$cipher);}
    return 'v0.'.b64u_encode($plain);
}
function api_key_decrypt($encoded){
    $encoded=(string)$encoded;if($encoded==='')return '';
    if(str_starts_with($encoded,'v1.')&&function_exists('openssl_decrypt')){$raw=b64u_decode(substr($encoded,3));if(strlen($raw)<29)return '';$iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);$plain=openssl_decrypt($cipher,'aes-256-gcm',api_master_secret(),OPENSSL_RAW_DATA,$iv,$tag,'cms-api-key');return is_string($plain)?$plain:'';}
    if(str_starts_with($encoded,'v0.'))return b64u_decode(substr($encoded,3));return '';
}
function api_access_mode($row){return (($row['access_mode']??'public')==='private')?'private':'public';}
function api_key_plain($row){return api_key_decrypt((string)($row['api_key_enc']??''));}

function telegram_settings(?int $pid=null):array{
    $pid=$pid??current_project_id();$all=cfg_read()['integrations']['telegram']??[];$row=is_array($all)?($all[(string)$pid]??[]):[];if(!is_array($row))$row=[];
    return ['enabled'=>!empty($row['enabled']),'chat_id'=>trim((string)($row['chat_id']??'')),'token_enc'=>(string)($row['token_enc']??''),'bot_id'=>(int)($row['bot_id']??0),'bot_username'=>trim((string)($row['bot_username']??'')),'bot_name'=>trim((string)($row['bot_name']??'')),'last_update_id'=>(int)($row['last_update_id']??0)];
}
function telegram_token(?int $pid=null):string{return api_key_decrypt(telegram_settings($pid)['token_enc']);}
function telegram_token_valid(string $token):bool{return (bool)preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/D',$token);}
function telegram_chat_id_valid(string $chatId):bool{return (bool)(preg_match('/^-?\d{5,20}$/D',$chatId)||preg_match('/^@[A-Za-z0-9_]{5,}$/D',$chatId));}
function telegram_ready(?int $pid=null):bool{$s=telegram_settings($pid);return $s['enabled']&&telegram_token($pid)!==''&&telegram_chat_id_valid($s['chat_id']);}
function telegram_api_request(string $token,string $method,array $params=[]):array{
    if(!telegram_token_valid($token))throw new RuntimeException(T('telegram_invalid_token'));if(!preg_match('/^[A-Za-z][A-Za-z0-9_]+$/D',$method))throw new RuntimeException(T('telegram_delivery_failed'));
    $url='https://api.telegram.org/bot'.$token.'/'.$method;$body=http_build_query($params,'','&',PHP_QUERY_RFC3986);$raw=false;$http=0;$transport='';
    if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);$raw=curl_exec($ch);$transport=(string)curl_error($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);}else{$ctx=stream_context_create(['http'=>['method'=>'POST','header'=>"Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",'content'=>$body,'timeout'=>5,'ignore_errors'=>true]]);$raw=@file_get_contents($url,false,$ctx);if(isset($http_response_header[0])&&preg_match('/\s(\d{3})\s/',$http_response_header[0],$m))$http=(int)$m[1];}
    if(!is_string($raw)||$raw==='')throw new RuntimeException($transport!==''?$transport:T('telegram_delivery_failed'));$data=json_decode($raw,true);if(!is_array($data)||empty($data['ok'])){$description=trim((string)($data['description']??''));throw new RuntimeException($description!==''?$description:(T('telegram_delivery_failed').($http?' (HTTP '.$http.')':'')));}return is_array($data['result']??null)?$data['result']:[];
}
function telegram_send_message(string $token,string $chatId,string $text):array{
    if(!telegram_chat_id_valid($chatId))throw new RuntimeException(T('telegram_invalid_chat_id'));$text=mb_substr(trim($text),0,3900);if($text==='')throw new RuntimeException(T('telegram_delivery_failed'));return telegram_api_request($token,'sendMessage',['chat_id'=>$chatId,'text'=>$text,'disable_web_page_preview'=>'true']);
}
function telegram_bot_info(string $token):array{
    $bot=telegram_api_request($token,'getMe');if(empty($bot['id'])||empty($bot['is_bot']))throw new RuntimeException(T('telegram_invalid_token'));$username=trim((string)($bot['username']??''));$name=trim((string)($bot['first_name']??''));return ['id'=>(int)$bot['id'],'username'=>$username,'name'=>$name!==''?$name:($username!==''?'@'.$username:T('telegram_bot')),'url'=>$username!==''?'https://t.me/'.$username:''];
}
function telegram_store_bot(int $pid,string $token,array $bot,?int $lastUpdateId=null):void{
    cfg_update(function(&$c)use($pid,$token,$bot,$lastUpdateId){$row=$c['integrations']['telegram'][(string)$pid]??[];if(!is_array($row))$row=[];$same=(int)($row['bot_id']??0)===(int)$bot['id'];$row['token_enc']=api_key_encrypt($token);$row['bot_id']=(int)$bot['id'];$row['bot_username']=(string)$bot['username'];$row['bot_name']=(string)$bot['name'];$row['last_update_id']=$lastUpdateId!==null?max(0,$lastUpdateId):($same?(int)($row['last_update_id']??0):0);$row['updated_at']=now();$c['integrations']['telegram'][(string)$pid]=$row;});
}
function telegram_chat_type_label(string $type):string{return match($type){'private'=>T('telegram_private_chat'),'group'=>T('telegram_group_chat'),'supergroup'=>T('telegram_supergroup_chat'),'channel'=>T('telegram_channel_chat'),default=>$type!==''?$type:'—'};}
function telegram_chat_display_name(array $chat):string{
    $title=trim((string)($chat['title']??''));if($title!=='')return $title;$name=trim((string)(($chat['first_name']??'').' '.($chat['last_name']??'')));if($name!=='')return $name;$username=trim((string)($chat['username']??''));return $username!==''?'@'.$username:'—';
}
function telegram_chat_id_reply(array $chat):string{
    $chatId=(string)($chat['id']??'');$lines=['✅ '.T('telegram_chat_id_reply_title'),'',$chatId,'',T('telegram_chat_name').': '.telegram_chat_display_name($chat),T('telegram_chat_type').': '.telegram_chat_type_label((string)($chat['type']??'')),'',T('telegram_chat_id_reply_copy')];return implode("\n",$lines);
}
function telegram_process_chat_id_updates(string $token,int $pid):array{
    $bot=telegram_bot_info($token);$settings=telegram_settings($pid);$webhook=telegram_api_request($token,'getWebhookInfo');if(trim((string)($webhook['url']??''))!=='')throw new RuntimeException(T('telegram_webhook_active'));
    $sameBot=(int)$settings['bot_id']===(int)$bot['id'];$last=$sameBot?(int)$settings['last_update_id']:0;$params=['limit'=>100,'timeout'=>0,'allowed_updates'=>json_encode(['message'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];if($last>0)$params['offset']=$last+1;$updates=telegram_api_request($token,'getUpdates',$params);$maxUpdate=$last;$replied=0;$chatIds=[];$errors=[];
    foreach($updates as $update){
        if(!is_array($update))continue;$updateId=(int)($update['update_id']??0);if($updateId>$maxUpdate)$maxUpdate=$updateId;$message=$update['message']??null;if(!is_array($message))continue;$chat=$message['chat']??null;if(!is_array($chat)||empty($chat['id'])||!empty($message['from']['is_bot']))continue;
        $chatType=(string)($chat['type']??'');$messageText=trim((string)($message['text']??''));if($chatType!=='private'&&!preg_match('~^/chatid(?:@[A-Za-z0-9_]+)?(?:\s|$)~i',$messageText))continue;
        $chatId=(string)$chat['id'];if(!telegram_chat_id_valid($chatId))continue;try{telegram_send_message($token,$chatId,telegram_chat_id_reply($chat));$replied++;$chatIds[$chatId]=true;}catch(Throwable $e){$errors[]=$e->getMessage();}
    }
    cfg_update(function(&$c)use($pid,$token,$bot,$maxUpdate){$row=$c['integrations']['telegram'][(string)$pid]??[];if(!is_array($row))$row=[];$row['token_enc']=api_key_encrypt($token);$row['bot_id']=(int)$bot['id'];$row['bot_username']=(string)$bot['username'];$row['bot_name']=(string)$bot['name'];$row['last_update_id']=$maxUpdate;$row['updated_at']=now();$c['integrations']['telegram'][(string)$pid]=$row;});
    return ['replied'=>$replied,'chat_ids'=>array_keys($chatIds),'error'=>$errors[0]??'','bot'=>$bot];
}
function telegram_value_text($value):string{
    if(is_bool($value))return $value?T('yes'):T('no');if(is_array($value)||is_object($value))$value=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$value=preg_replace('/\s+/u',' ',trim((string)$value));return mb_substr($value,0,500);
}
function telegram_submission_message(array $form,int $submissionId,array $payload):string{
    $pid=(int)$form['pid'];$pr=project($pid);$labels=form_field_labels_map((int)$form['id'],$pid);$lines=['📩 '.T('telegram_new_submission'),'',''.T('project').': '.($pr['n']??('#'.$pid)),T('form').': '.($form['n']??('#'.(int)$form['id'])),T('form_submission').': #'.$submissionId,T('created').': '.now()];$shown=0;
    foreach($payload as $key=>$value){if($shown>=20){$lines[]='…';break;}$text=telegram_value_text($value);if($text==='')continue;$lines[]=(string)($labels[(string)$key]??$key).': '.$text;$shown++;}
    $ref=trim((string)($_SERVER['HTTP_REFERER']??''));if($ref!=='')$lines[]=T('referrer').': '.mb_substr($ref,0,500);return mb_substr(implode("\n",$lines),0,3900);
}
function telegram_notify_submission(array $form,int $submissionId,array $payload):void{
    $pid=(int)$form['pid'];if(!telegram_ready($pid))return;$settings=telegram_settings($pid);try{telegram_send_message(telegram_token($pid),$settings['chat_id'],telegram_submission_message($form,$submissionId,$payload));}catch(Throwable $e){audit_event('form.telegram_failed','form',(int)$form['id'],$e->getMessage(),['submission_id'=>$submissionId],$pid,null);}
}
function telegram_forget_project(int $pid):void{
    cfg_update(function(&$c)use($pid){if(isset($c['integrations']['telegram'][(string)$pid]))unset($c['integrations']['telegram'][(string)$pid]);if(empty($c['integrations']['telegram']))unset($c['integrations']['telegram']);if(empty($c['integrations']))unset($c['integrations']);});
}
function api_request_key(){
    $key=trim((string)($_SERVER['HTTP_X_API_KEY']??''));
    if($key===''){$auth=trim((string)($_SERVER['HTTP_AUTHORIZATION']??''));if(preg_match('/^Bearer\s+(.+)$/i',$auth,$m))$key=trim($m[1]);}
    return $key;
}
function resource_table(string $type){return match($type){'collection'=>'c','group'=>'g','form'=>'forms',default=>''};}
function resource_row(string $type,int $id,int $pid=0){$table=resource_table($type);if($table==='')return null;$pid=$pid?:current_project_id();return one("SELECT * FROM $table WHERE id=? AND pid=?",[$id,$pid]);}
function resource_name(string $type,int $id,int $pid=0){$r=resource_row($type,$id,$pid);return $r?($r['n'].' · '.$r['s']):($type.' #'.$id);}
function resource_options(){
    $out=[];foreach(['collection'=>cols(),'group'=>groups(),'form'=>forms_all()] as $type=>$rows)foreach($rows as $r)$out[$type.':'.(int)$r['id']]=T($type==='collection'?'collection':($type==='group'?'group':'form')).' · '.$r['n'].' · '.$r['s'];return $out;
}
function api_keys_for_resource(string $type,int $id,int $pid=0){$pid=$pid?:current_project_id();return all('SELECT * FROM api_keys WHERE pid=? AND resource_type=? AND resource_id=? ORDER BY id DESC',[$pid,$type,$id]);}
function api_key_matches_resource(string $type,array $row,$key=null){
    $key=$key===null?api_request_key():(string)$key;if($key==='')return false;$hash=api_key_hash_value($key);$pid=(int)($row['pid']??0);$id=(int)($row['id']??0);$now=now();
    $r=one("SELECT * FROM api_keys WHERE pid=? AND resource_type=? AND resource_id=? AND st='active' AND key_hash=? AND (expires_at IS NULL OR expires_at='' OR expires_at>?) LIMIT 1",[$pid,$type,$id,$hash,$now]);
    if($r){q('UPDATE api_keys SET last_used_at=?,ua=? WHERE id=?',[$now,$now,(int)$r['id']]);return true;}
    $legacy=(string)($row['api_key_hash']??'');return $legacy!==''&&hash_equals($legacy,$hash);
}
function api_key_matches($row,$key=null,$type='collection'){return api_key_matches_resource((string)$type,(array)$row,$key);}
function api_private_collection_allowed($c){if(api_access_mode($c)==='public')return true;$allowed=$GLOBALS['_api_allowed_private_collections']??[];return in_array((int)($c['id']??0),array_map('intval',(array)$allowed),true)||api_key_matches_resource('collection',$c);}
function require_resource_api_access($row,$type='collection'){if(api_access_mode($row)==='private'&&!api_key_matches_resource((string)$type,(array)$row)){header('WWW-Authenticate: Bearer realm="Mini Headless CMS"');api_error('api_key_required',401,T('api_key_invalid'));}}
function access_values_from_post($existing=null){
    $mode=(($_POST['access_mode']??'public')==='private')?'private':'public';$hash=(string)($existing['api_key_hash']??'');$enc=(string)($existing['api_key_enc']??'');return [$mode,$hash,$enc];
}
function cors_origin_canonical(string $origin):string{
    $origin=trim($origin);if($origin==='')return '';$parts=@parse_url($origin);if(!is_array($parts))return '';
    $scheme=strtolower((string)($parts['scheme']??''));$host=strtolower((string)($parts['host']??''));
    if(!in_array($scheme,['http','https'],true)||$host==='')return '';
    if(isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))return '';
    $path=(string)($parts['path']??'');if($path!==''&&$path!=='/')return '';
    if(!filter_var($host,FILTER_VALIDATE_IP)&&!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i',$host)&&$host!=='localhost')return '';
    $port=isset($parts['port'])?(int)$parts['port']:0;if($port<0||$port>65535)return '';
    return $scheme.'://'.$host.($port?':'.$port:'');
}
function cors_origins_normalize($value,bool $strict=false){
    $value=trim((string)$value);if($value===''||$value==='*')return '*';$parts=preg_split('/[\r\n,]+/',$value)?:[];$out=[];$invalid=false;
    foreach($parts as $origin){$origin=trim($origin);if($origin==='')continue;if($origin==='*')return '*';$canonical=cors_origin_canonical($origin);if($canonical===''){$invalid=true;continue;}$out[$canonical]=true;}
    if($strict&&($invalid||!$out))throw new Exception(T('cors_invalid'));return $out?implode("\n",array_keys($out)):'*';
}
function cors_origins_from_post($value){return cors_origins_normalize($value,true);}
function project_cors_origins(?int $pid=null):string{
    $pid=$pid?:current_project_id();$pr=$pid?project($pid):null;return cors_origins_normalize($pr['cors_origins']??'*');
}
function cors_origin_allowed(string $rules,string $origin):bool{
    $rules=cors_origins_normalize($rules);if($rules==='*')return true;$origin=cors_origin_canonical($origin);return $origin!==''&&in_array($origin,preg_split('/\R+/',$rules)?:[],true);
}
function apply_resource_cors(array $row){
    $pid=(int)($row['pid']??0);if(!$pid)$pid=current_project_id();$rules=project_cors_origins($pid);$origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));
    header('Vary: Origin');header('Access-Control-Allow-Methods: GET, HEAD, POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type, Accept, X-API-Key, Authorization');header('Access-Control-Max-Age: 600');
    if($rules==='*'){header('Access-Control-Allow-Origin: *');return;}
    if($origin==='')return;
    if(cors_origin_allowed($rules,$origin)){header('Access-Control-Allow-Origin: '.cors_origin_canonical($origin));return;}
    api_error('cors_denied',403,T('cors_denied'));
}
function create_managed_api_key(string $type,int $id,string $name='',string $expires=''){
    $row=resource_row($type,$id);if(!$row)throw new Exception(T('access_denied'));$plain=api_key_generate();$hash=api_key_hash_value($plain);$name=trim($name)?:'Default';$expires=trim($expires);if($expires!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$expires))$expires='';if($expires!=='')$expires.=' 23:59:59';$tm=now();run('INSERT INTO api_keys(pid,resource_type,resource_id,name,key_hash,prefix,last4,st,expires_at,created_by,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[(int)$row['pid'],$type,$id,mb_substr($name,0,160),$hash,substr($plain,0,12),substr($plain,-4),'active',$expires?:null,current_user_id(),$tm,$tm]);$_SESSION['_one_time_api_key']=['key'=>$plain,'resource'=>resource_name($type,$id),'name'=>$name];audit_event('api_key.create',$type,$id,T('api_key_create'),['name'=>$name,'expires_at'=>$expires]);return $plain;
}
function ensure_default_api_key(string $type,int $id){$row=resource_row($type,$id);if(!$row||api_access_mode($row)!=='private')return;if((int)q("SELECT COUNT(*) FROM api_keys WHERE pid=? AND resource_type=? AND resource_id=? AND st='active'",[(int)$row['pid'],$type,$id])->fetchColumn()>0)return;create_managed_api_key($type,$id,'Default','');}
function access_badge($row){$mode=is_array($row)&&collection_is_nested($row)?collection_effective_access($row):api_access_mode($row);$private=$mode==='private';return '<span class="badge '.($private?'text-bg-dark':'text-bg-success').'">'.icon($private?'lock-fill':'globe2').' '.h(T($private?'access_private':'access_public')).'</span>';}
function access_control_html($row=null,$scope='resource'){
    $mode=api_access_mode($row??[]);$id='access_'.preg_replace('/[^a-z0-9_-]/i','_',$scope).'_'.(int)($row['id']??0).'_'.substr(md5($scope),0,6);$opts=['public'=>T('access_public'),'private'=>T('access_private')];
    $keysLink=!empty($row['id'])?'<a class="btn btn-secondary w-100" href="'.h(U(['api_keys'=>1,'resource'=>$scope.':'.(int)$row['id']])).'">'.icon('key').' '.h(T('api_management')).'</a>':'<div class="form-text">'.h(T('api_key_save_resource_first')).'</div>';
    return '<div class="border rounded-4 p-3 mb-3 js-access-control">'.select_html('access_mode',T('access_mode'),$opts,$mode,['class'=>'form-select js-access-mode','id'=>$id.'_mode','data-no-old'=>true]).'<div class="small text-muted mb-3 js-access-hint" data-public="'.h(T('access_public_hint')).'" data-private="'.h(T('access_private_hint')).'"></div><div class="js-private-access '.($mode==='private'?'':'d-none').'">'.$keysLink.'</div></div>';
}
function apiKeysPage(){
    require_perm(can_api());$filter=trim((string)($_GET['resource']??''));$where='k.pid=?';$params=[current_project_id()];if($filter!==''&&preg_match('/^(collection|group|form):(\d+)$/',$filter,$m)){$where.=' AND k.resource_type=? AND k.resource_id=?';array_push($params,$m[1],(int)$m[2]);}
    $rows=all('SELECT k.*,u.l AS creator_login,u.n AS creator_name FROM api_keys k LEFT JOIN users u ON u.id=k.created_by WHERE '.$where.' ORDER BY k.id DESC',$params);$one=$_SESSION['_one_time_api_key']??null;unset($_SESSION['_one_time_api_key']);$opts=resource_options();$h=page_head(T('api_keys'),h(T('api_key_created_once')));
    if($one)$h.='<div class="alert alert-warning rounded-4"><div class="fw-semibold mb-2">'.h(T('api_key_created_once')).'</div><div class="small mb-2">'.h(($one['resource']??'').' · '.($one['name']??'')).'</div><div class="input-group"><input class="form-control font-monospace" readonly value="'.h($one['key']??'').'"><button class="btn btn-dark js-copy" type="button" data-copy="'.h($one['key']??'').'">'.icon('copy').' '.h(T('copy_api_key')).'</button></div></div>';
    $create='<form method="post" class="ios-surface p-4 mb-3">'.token().'<input type="hidden" name="_a" value="create_api_key"><div class="row g-3 align-items-end"><div class="col-12 col-lg-5">'.select_html('resource',T('audit_entity'),$opts,$filter,['required'=>true,'data-no-old'=>true]).'</div><div class="col-12 col-md-4 col-lg-3">'.inp('name',T('api_key_name'),'Frontend','text',['required'=>true]).'</div><div class="col-12 col-md-4 col-lg-2">'.inp('expires',T('api_key_expires'),'','date').'</div><div class="col-12 col-md-4 col-lg-2"><button class="btn btn-primary w-100">'.icon('key').' '.h(T('api_key_create')).'</button></div></div></form>';
    $table='<table class="table table-hover align-middle mb-0"><thead><tr><th>'.h(T('audit_entity')).'</th><th>'.h(T('api_key_name')).'</th><th>'.h(T('api_key')).'</th><th>'.h(T('status')).'</th><th>'.h(T('api_key_expires')).'</th><th>'.h(T('api_key_last_used')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $k){$expired=!empty($k['expires_at'])&&$k['expires_at']<now();$active=$k['st']==='active'&&!$expired;$revoke=$k['st']==='active'?universal_delete_button(T('api_key_revoke'),'revoke_api_key',['id'=>(int)$k['id']],T('api_key_revoke'),T('api_key_revoke_q'),true):'—';$table.='<tr><td>'.h(resource_name($k['resource_type'],(int)$k['resource_id'])).'</td><td class="fw-semibold">'.h($k['name']).'</td><td><code>'.h(($k['prefix']?:'cms_').'…'.($k['last4']?:'')).'</code></td><td><span class="badge '.($active?'text-bg-success':'text-bg-secondary').'">'.h($active?T('api_key_active'):($expired?T('api_key_expired'):T('api_key_revoked'))).'</span></td><td>'.h($k['expires_at']?:'—').'</td><td>'.h($k['last_used_at']?:'—').'</td><td class="text-end">'.$revoke.'</td></tr>';}$table.='</tbody></table>';return $h.$create.table_wrap($table);
}


/* API */
function J_api($x,$c=200,int $maxAge=60,$lastModified=null,$private=false){
    if(is_array($x)&&!array_key_exists('api_version',$x))$x=['api_version'=>API_VERSION]+$x;
    $pretty=debug_enabled()||in_array(strtolower((string)($_GET['pretty']??'')),['1','true','yes','on'],true);$flags=JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|($pretty?JSON_PRETTY_PRINT:0);
    $json=json_encode($x,$flags);if($json===false){$json='{"api_version":"'.API_VERSION.'","ok":false,"error":"json_encode_failed","message":"JSON encoding failed.","status":500}';$c=500;$private=true;}
    $etag='"'.hash('sha256',$json).'"';$lastHeader='';header('X-API-Version: '.API_VERSION);header('ETag: '.$etag);header('Vary: Accept-Encoding, Origin');header('Cache-Control: '.($private||$maxAge<=0?'private, no-store':'public, max-age='.$maxAge.', stale-while-revalidate=30'));
    if(is_array($x)&&isset($x['lang'])&&is_string($x['lang'])&&$x['lang']!==''&&$x['lang']!=='all')header('Content-Language: '.$x['lang']);
    if($lastModified){$ts=is_numeric($lastModified)?(int)$lastModified:strtotime((string)$lastModified);if($ts){$lastHeader=gmdate('D, d M Y H:i:s',$ts).' GMT';header('Last-Modified: '.$lastHeader);}}
    $ifNone=trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''));$ifModified=trim((string)($_SERVER['HTTP_IF_MODIFIED_SINCE']??''));if($ifNone===$etag||($lastHeader!==''&&$ifModified!==''&&strtotime($ifModified)!==false&&strtotime($ifModified)>=(int)strtotime($lastHeader))){http_response_code(304);exit;}
    $cacheKey=(string)($GLOBALS['_api_response_cache_key']??'');if($cacheKey!=='')ResponseCache::store($cacheKey,$json,(int)$c,$maxAge,$lastHeader,$private);
    http_response_code((int)$c);header('Content-Type: application/json; charset=utf-8');if(debug_enabled())header('X-CMS-Cache: MISS');if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!=='HEAD')echo $json;exit;
}
function api_error(string $code,int $status=400,string $message='',array $details=[]){$payload=['ok'=>false,'error'=>$code,'message'=>$message!==''?$message:str_replace('_',' ',$code),'status'=>$status];if($details)$payload['details']=$details;J_api($payload,$status,0,null,true);}
function api_require_method(array $allowed):void{$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));if(in_array($method,$allowed,true))return;header('Allow: '.implode(', ',$allowed));api_error('method_not_allowed',405,'Method not allowed.',['allowed'=>$allowed]);}
function api_include_schema(){return in_array(strtolower((string)($_GET['schema']??($_GET['include_schema']??''))),['1','true','yes','on'],true)||in_array(strtolower((string)($_GET['fields']??'')),['1','true','yes','on'],true);}
function api_projection_fields(){$raw=trim((string)($_GET['fields']??''));if($raw===''||in_array(strtolower($raw),['1','true','yes','on'],true))return [];return array_values(array_unique(array_filter(array_map('trim',explode(',',$raw)),fn($v)=>$v!=='')));}
function api_project_entry_fields(array $item,array $wanted){if(!$wanted)return $item;$out=[];$known=['id','title','slug','status','created_at','updated_at','parent_entry','lang','i18n','translated','default_lang','languages','translated_languages','translations','data'];foreach($wanted as $key){if(in_array($key,$known,true)&&array_key_exists($key,$item)){$out[$key]=$item[$key];continue;}$dataKey=str_starts_with($key,'data.')?substr($key,5):$key;$value=api_value_path(['data'=>$item['data']??[]],'data.'.$dataKey,$exists);if($exists){$cursor=&$out['data'];foreach(explode('.',$dataKey) as $segment){if(!isset($cursor)||!is_array($cursor))$cursor=[];$cursor=&$cursor[$segment];}$cursor=$value;unset($cursor);}}return $out;}
function api_scalar_equal($a,$b):bool{if(is_array($a)){foreach($a as $item)if(api_scalar_equal($item,$b))return true;return false;}if($a===null)return in_array(strtolower(trim((string)$b)),['','null'],true);if(is_bool($a))return in_array(strtolower((string)$b),$a?['1','true','yes','on']:['0','false','no','off'],true);if(is_numeric($a)&&is_numeric($b))return (float)$a==(float)$b;return mb_strtolower(trim((string)$a))===mb_strtolower(trim((string)$b));}
function api_value_path(array $item,string $key,?bool &$exists=null){$exists=true;$path=str_starts_with($key,'data.')?explode('.',substr($key,5)):explode('.',$key);$value=str_starts_with($key,'data.')?($item['data']??null):$item;if(str_starts_with($key,'data.')&&!array_key_exists('data',$item)){$exists=false;return null;}foreach($path as $segment){if($segment==='')continue;if(!is_array($value)||!array_key_exists($segment,$value)){$exists=false;return null;}$value=$value[$segment];}return $value;}
function api_filter_match(array $item,string $key,$wanted):bool{$exists=false;$actual=api_value_path($item,$key,$exists);if(!$exists)return false;$values=is_array($wanted)?$wanted:preg_split('/\s*,\s*/u',(string)$wanted);foreach($values?:[] as $value)if(api_scalar_equal($actual,$value))return true;return false;}
function api_latest_modified(...$values){$latest=0;$result=null;foreach($values as $value){if($value===null||$value==='')continue;$ts=is_numeric($value)?(int)$value:strtotime((string)$value);if($ts!==false&&$ts>$latest){$latest=$ts;$result=$value;}}return $result;}
function api_pagination_meta(int $total,int $page,int $limit,string $sort='',string $type='multiple'):array{$pages=$total>0?(int)ceil($total/$limit):0;$page=$pages>0?min(max(1,$page),$pages):1;$meta=['total'=>$total,'page'=>$page,'limit'=>$limit,'pages'=>$pages,'has_more'=>$pages>0&&$page<$pages,'next_page'=>$pages>0&&$page<$pages?$page+1:null,'prev_page'=>$page>1?$page-1:null,'type'=>$type];if($sort!=='')$meta['sort']=$sort;return $meta;}
function api_resource_translations(array $row):array{return [];}
function api_collection_meta(array $c,$lang):array{return ['id'=>(int)$c['id'],'name'=>resource_base_value($c,'n'),'slug'=>$c['s'],'description'=>resource_base_value($c,'d'),'type'=>collection_mode($c),'access'=>collection_effective_access($c),'order'=>(int)($c['group_order']??$c['o']??0)];}
function api_group_meta(array $g,$lang):array{return ['id'=>(int)$g['id'],'name'=>resource_base_value($g,'n'),'slug'=>$g['s'],'description'=>resource_base_value($g,'d'),'access'=>api_access_mode($g),'order'=>(int)($g['o']??0)];}
function api_form_meta(array $form,$lang):array{
    $displayLang=$lang===null?default_content_lang():(string)$lang;$out=['id'=>(int)$form['id'],'name'=>form_text($form,'n',$displayLang),'slug'=>$form['s'],'description'=>trim((string)($form['d_base']??$form['d']??'')),'success_message'=>form_text($form,'success_message',$displayLang),'access'=>api_access_mode($form),'status'=>$form['st']??'active'];
    if($lang===null&&content_i18n_enabled()){$translations=[];$map=form_i18n_map($form,true);foreach(content_langs() as $code){$v=$map[$code]??[];$translations[$code]=['name'=>(string)($v['n']??''),'success_message'=>(string)($v['success_message']??''),'translated'=>!empty($v['_translated'])];}$out['translations']=$translations;}
    return $out;
}
/* Nested collections are intentionally not exposed directly or attached automatically in API responses.
   They are available only through fields of type nested_relation and the normal populate parameter. */
function api_field_out(array $f,$lang=null,$pid=null):array{
    $out=['id'=>(int)($f['id']??0),'label'=>(string)($f['l']??$f['k']??''),'key'=>$f['k'],'type'=>$f['t'],'required'=>(bool)($f['r']??false),'order'=>(int)($f['o']??0)];
    $opt=field_options($f);$placeholder=field_text($f,'placeholder');$hint=field_text($f,'hint');$labels=field_choice_labels($f);if($placeholder!=='')$out['placeholder']=$placeholder;if($hint!=='')$out['hint']=$hint;if($labels)$out['choice_labels']=$labels;$rules=validation_rules_from_options($opt);if($rules)$out['rules']=$rules;if(array_key_exists('default',$opt))$out['default']=$opt['default'];
    if(field_is_relation_type((string)($f['t']??''))){
        $targetId=(int)($opt['target_collection_id']??0);$projectId=(int)($pid?:collection_project_id((int)($f['cid']??0)));$target=$targetId&&$projectId?col($targetId,$projectId):null;$parent=$target&&collection_is_nested($target)?collection_parent($target,$projectId):null;
        $out['relation']=['kind'=>(($f['t']??'relation')==='nested_relation'?'nested':'global'),'mode'=>(($opt['mode']??'single')==='multiple'?'multiple':'single'),'target_collection_id'=>$targetId,'target_collection_slug'=>$target['s']??null,'target_collection'=>$target?['id'=>(int)$target['id'],'name'=>resource_base_value($target,'n'),'slug'=>$target['s'],'scope'=>collection_is_nested($target)?'nested':'global','access'=>collection_effective_access($target),'parent_collection'=>$parent?['id'=>(int)$parent['id'],'name'=>(string)$parent['n'],'slug'=>(string)$parent['s']]:null]:null];
    }
    return $out;
}
function api_form_field_out(array $field,$lang=null):array{
    $out=api_field_out($field,$lang,(int)($field['pid']??0));$displayLang=$lang===null?default_content_lang():(string)$lang;$out['label']=form_field_text($field,$displayLang);
    if($lang===null&&content_i18n_enabled()){$translations=[];$map=field_i18n_map($field,true);foreach(content_langs() as $code){$v=$map[$code]??[];$translations[$code]=['label'=>(string)($v['l']??''),'translated'=>!empty($v['_translated'])];}$out['translations']=$translations;}
    return $out;
}
function api_array_payload(array $items):array{$q=mb_strtolower(trim((string)($_GET['q']??'')));if($q!=='')$items=array_values(array_filter($items,fn($item)=>str_contains(mb_strtolower(json_encode($item,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:''),$q)));$total=count($items);$limit=min(100,max(1,(int)($_GET['limit']??25)));$page=max(1,(int)($_GET['page']??1));$pages=$total>0?(int)ceil($total/$limit):0;if($pages>0&&$page>$pages)$page=$pages;$slice=array_slice($items,($page-1)*$limit,$limit);return ['data'=>$slice,'meta'=>api_pagination_meta($total,$page,$limit)];}
function api_collection_entries_payload(array $c,$lang=null,$populate=false){
    $sortRaw=trim((string)($_GET['sort']??'-id'));$desc=str_starts_with($sortRaw,'-');$sort=ltrim($sortRaw,'+-');$wanted=api_projection_fields();$query=trim((string)($_GET['q']??''));$filters=$_GET['filter']??[];$filters=is_array($filters)?$filters:[];$sqlSort=['id'=>'id','slug'=>'s','created_at'=>'ca','updated_at'=>'ua'];if(!content_i18n_enabled())$sqlSort['title']='t';$canSql=$query===''&&!$filters&&isset($sqlSort[$sort]);$cid=(int)$c['id'];$limit=min(100,max(1,(int)($_GET['limit']??25)));$page=max(1,(int)($_GET['page']??1));
    if($canSql){$order=$sqlSort[$sort].($desc?' DESC':' ASC').', id'.($desc?' DESC':' ASC');if(collection_mode($c)==='single'){$row=one("SELECT * FROM e WHERE cid=? AND st='published' ORDER BY $order LIMIT 1",[$cid]);if($row)preload_entry_dependencies([$row],$cid,$populate);$item=$row?api_project_entry_fields(outEntry($row,$lang,$populate),$wanted):null;return ['data'=>$item,'meta'=>['total'=>$row?1:0,'type'=>'single','sort'=>($desc?'-':'').$sort]];}$total=(int)scalar("SELECT COUNT(*) FROM e WHERE cid=? AND st='published'",[$cid]);$pages=$total>0?(int)ceil($total/$limit):0;if($pages>0&&$page>$pages)$page=$pages;$offset=($page-1)*$limit;$rows=all("SELECT * FROM e WHERE cid=? AND st='published' ORDER BY $order LIMIT $limit OFFSET $offset",[$cid]);preload_entry_dependencies($rows,$cid,$populate);$items=array_map(fn($row)=>outEntry($row,$lang,$populate),$rows);if($wanted)$items=array_map(fn($item)=>api_project_entry_fields($item,$wanted),$items);return ['data'=>$items,'meta'=>api_pagination_meta($total,$page,$limit,($desc?'-':'').$sort)];}
    $sourceRows=all("SELECT * FROM e WHERE cid=? AND st='published' ORDER BY id DESC",[$cid]);preload_entry_dependencies($sourceRows,$cid,$populate);$items=array_map(fn($e)=>outEntry($e,$lang,$populate),$sourceRows);$qv=mb_strtolower($query);if($qv!=='')$items=array_values(array_filter($items,fn($x)=>str_contains(mb_strtolower(json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:''),$qv)));foreach($filters as $k=>$v)$items=array_values(array_filter($items,fn($x)=>api_filter_match($x,(string)$k,$v)));$allowed=['id','title','slug','status','created_at','updated_at'];if(!in_array($sort,$allowed,true)&&!str_starts_with($sort,'data.'))$sort='id';usort($items,function($a,$b)use($sort,$desc){$ae=false;$be=false;$av=api_value_path($a,$sort,$ae);$bv=api_value_path($b,$sort,$be);if(!$ae&&$be)$cmp=-1;elseif($ae&&!$be)$cmp=1;elseif(is_numeric($av)&&is_numeric($bv))$cmp=(float)$av<=>(float)$bv;else $cmp=strnatcasecmp((string)$av,(string)$bv);return $desc?-$cmp:$cmp;});if(collection_mode($c)==='single')return ['data'=>isset($items[0])?api_project_entry_fields($items[0],$wanted):null,'meta'=>['total'=>count($items),'type'=>'single','sort'=>($desc?'-':'').$sort]];$total=count($items);$pages=$total>0?(int)ceil($total/$limit):0;if($pages>0&&$page>$pages)$page=$pages;$slice=array_slice($items,($page-1)*$limit,$limit);if($wanted)$slice=array_map(fn($x)=>api_project_entry_fields($x,$wanted),$slice);return ['data'=>$slice,'meta'=>api_pagination_meta($total,$page,$limit,($desc?'-':'').$sort)];
}
function api(){
    api_require_method(['GET','HEAD','OPTIONS']);$r=trim((string)($_GET['api']??'index'))?:'index';$pid=api_project_id();$pr=project($pid);if(!$pid||!$pr)api_error('project_not_found',404,'Project not found.');$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$corsResource=['pid'=>$pid];if($r==='group'&&trim((string)($_GET['g']??($_GET['s']??'')))!==''){$candidate=group_by_slug((string)($_GET['g']??$_GET['s']),$pid);if($candidate)$corsResource=$candidate;}elseif(in_array($r,['entries','entry','fields','schema'],true)&&trim((string)($_GET['c']??''))!==''){$candidate=col_by_slug((string)$_GET['c'],$pid);if($candidate)$corsResource=$candidate;}apply_resource_cors($corsResource);if($method==='OPTIONS'){http_response_code(204);exit;}
    if($r==='index'&&!ok()){$cacheKey=ResponseCache::key('api:index:'.$pid);if(ResponseCache::serve($cacheKey,300))exit;$GLOBALS['_api_response_cache_key']=$cacheKey;}
    if($r==='index'){$route=function(array $params)use($pr){$params=['project'=>$pr['s']]+$params;return '?'.http_build_query($params);};$routes=[];$firstCollection=one("SELECT * FROM c WHERE pid=? AND parent_cid IS NULL AND access_mode='public' ORDER BY o,n,id LIMIT 1",[$pid]);$firstGroup=one("SELECT * FROM g WHERE pid=? AND access_mode='public' ORDER BY o,n,id LIMIT 1",[$pid]);if($firstCollection){$routes[]=$route(['api'=>'entries','c'=>$firstCollection['s'],'lang'=>'default','page'=>1,'limit'=>25,'sort'=>'-id']);$routes[]=$route(['api'=>'entries','c'=>$firstCollection['s'],'lang'=>'default','populate'=>'all']);$firstEntry=one("SELECT s FROM e WHERE cid=? AND st='published' ORDER BY id LIMIT 1",[(int)$firstCollection['id']]);if($firstEntry)$routes[]=$route(['api'=>'entry','c'=>$firstCollection['s'],'s'=>$firstEntry['s'],'lang'=>'default','populate'=>'all']);}if($firstGroup)$routes[]=$route(['api'=>'group','g'=>$firstGroup['s'],'lang'=>'default','limit'=>25]);if(ok()&&can_api()){$routes[]=$route(['api'=>'collections','lang'=>'default']);$routes[]=$route(['api'=>'groups','lang'=>'default']);if($firstCollection){$routes[]=$route(['api'=>'fields','c'=>$firstCollection['s'],'lang'=>'default']);$routes[]=$route(['api'=>'schema','c'=>$firstCollection['s'],'lang'=>'all']);}}if(ok()&&can_files()){$routes[]=$route(['api'=>'files','page'=>1,'limit'=>25]);$routes[]=$route(['api'=>'files-trash','page'=>1,'limit'=>25]);}$x=['ok'=>true,'name'=>APP,'project'=>api_project_meta($pr),'content_i18n'=>content_i18n_enabled(),'default_lang'=>default_content_lang(),'content_languages'=>content_langs(),'public'=>'published_content_only','limits'=>['max_page_size'=>100,'relation_depth'=>1],'parameters'=>['lang'=>'default | language code | all','populate'=>'0 | all | relation_key[,relation_key]','fields'=>'title,slug,data.field','filter'=>'filter[field]=value','sort'=>'id, title, slug, created_at, updated_at, data.field'],'routes'=>$routes];J_api($x,200,300);}
    if($r==='files'||$r==='files-trash'){api_require(ok()&&can_files());$mode=$r==='files'?'active':'trash';$payload=api_array_payload(list_files($mode,$pid));J_api(['ok'=>true,'project'=>api_project_meta($pr),'data'=>$payload['data'],'meta'=>$payload['meta']],200,0,null,true);}
    if($r==='collections'||$r==='groups'){$lang=api_content_lang();api_require(ok()&&can_api());if($r==='collections'){$rows=array_map(fn($c)=>api_collection_meta($c,$lang),cols($pid));J_api(['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['data'=>$rows,'meta'=>['total'=>count($rows)]],200,0,null,true);}else{$rows=array_map(function($g)use($pid,$lang){$x=api_group_meta($g,$lang);$x['collections']=array_map(fn($c)=>api_collection_meta($c,$lang),group_cols((int)$g['id'],$pid));return $x;},groups($pid));J_api(['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['data'=>$rows,'meta'=>['total'=>count($rows)]],200,0,null,true);}}
    if($r==='group'){$slug=trim((string)($_GET['g']??($_GET['s']??'')));if($slug==='')api_error('group_required',400,'Group slug is required.');$g=group_by_slug($slug,$pid);if(!$g)api_error('group_not_found',404,'Group not found.');apply_resource_cors($g);require_resource_api_access($g,'group');$lang=api_content_lang();$populate=api_populate();$withFields=api_include_schema();if($withFields)api_require(ok()&&can_api());if($method==='GET'&&api_access_mode($g)==='public'&&!$withFields){$cacheKey=ResponseCache::key('api:group:'.$pid.':'.(int)$g['id']);if(ResponseCache::serve($cacheKey,API_CACHE_TTL))exit;$GLOBALS['_api_response_cache_key']=$cacheKey;}if(api_access_mode($g)==='private')$GLOBALS['_api_allowed_private_collections']=array_map('intval',array_column(group_cols((int)$g['id'],$pid),'id'));$out=outGroup($g,$lang,$withFields,$populate,api_access_mode($g)==='private');$groupCollectionsLast=scalar('SELECT MAX(c.ua) FROM gc JOIN c ON c.id=gc.cid WHERE gc.gid=? AND c.pid=?',[(int)$g['id'],$pid]);$groupEntriesLast=scalar("SELECT MAX(e.ua) FROM gc JOIN c ON c.id=gc.cid JOIN e ON e.cid=c.id WHERE gc.gid=? AND c.pid=? AND e.st='published'",[(int)$g['id'],$pid]);$last=api_latest_modified($g['ua']??$g['ca'],$groupCollectionsLast,$groupEntriesLast);J_api(['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['access'=>api_access_mode($g),'populate'=>api_populate_response($populate),'group'=>$out],200,60,$last,api_access_mode($g)==='private');}
    if(!in_array($r,['entries','entry','fields','schema'],true))api_error('api_not_found',404,'API endpoint not found.');$collectionSlug=trim((string)($_GET['c']??''));if($collectionSlug==='')api_error('collection_required',400,'Collection slug is required.');$c=col_by_slug($collectionSlug,$pid);if(!$c||collection_is_nested($c))api_error('collection_not_found',404,'Collection not found.');apply_resource_cors($c);if(in_array($r,['entries','entry'],true))require_resource_api_access($c,'collection');$privateSchema=in_array($r,['fields','schema'],true)||api_include_schema();if($privateSchema)api_require(ok()&&can_api());$lang=api_content_lang();$populate=api_populate();if($method==='GET'&&in_array($r,['entries','entry'],true)&&collection_effective_access($c)==='public'&&!$privateSchema){$cacheKey=ResponseCache::key('api:'.$r.':'.$pid.':'.(int)$c['id']);if(ResponseCache::serve($cacheKey,API_CACHE_TTL))exit;$GLOBALS['_api_response_cache_key']=$cacheKey;}$fs=$privateSchema?array_map(fn($f)=>api_field_out($f,$lang,$pid),fields((int)$c['id'],$pid)):[];
    if($r==='fields')J_api(['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['collection'=>api_collection_meta($c,$lang),'data'=>$fs,'meta'=>['total'=>count($fs)]],200,0,null,true);
    if($r==='schema')J_api(['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['collection'=>api_collection_meta($c,$lang),'fields'=>$fs],200,0,null,true);
    if($r==='entries'){$payload=api_collection_entries_payload($c,$lang,$populate);$x=['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['collection'=>api_collection_meta($c,$lang),'type'=>collection_mode($c),'access'=>collection_effective_access($c),'populate'=>api_populate_response($populate),'data'=>$payload['data'],'meta'=>$payload['meta']];if(api_include_schema())$x['schema']=$fs;$entryLast=q("SELECT MAX(ua) FROM e WHERE cid=? AND st='published'",[(int)$c['id']])->fetchColumn();$last=api_latest_modified($c['ua']??$c['ca'],$entryLast);J_api($x,200,60,$last,collection_effective_access($c)==='private');}
    $entrySlug=trim((string)($_GET['s']??''));if($entrySlug==='')api_error('entry_required',400,'Entry slug is required.');$e=one("SELECT * FROM e WHERE cid=? AND s=? AND st='published'",[$c['id'],slug($entrySlug)]);if(!$e)api_error('entry_not_found',404,'Entry not found.');$item=api_project_entry_fields(outEntry($e,$lang,$populate),api_projection_fields());$x=['ok'=>true,'project'=>api_project_meta($pr)]+api_language_context($lang)+['collection'=>api_collection_meta($c,$lang),'access'=>collection_effective_access($c),'populate'=>api_populate_response($populate),'data'=>$item];if(api_include_schema())$x['schema']=$fs;$last=api_latest_modified($c['ua']??$c['ca'],$e['ua']??$e['ca']);J_api($x,200,60,$last,collection_effective_access($c)==='private');
}

/* ACTIONS */

function action_modal_for($a){return match($a){'col'=>(!empty($_POST['id'])?'collectionEditModal':(!empty($_POST['parent_cid'])?'nestedCollectionModal':'collectionNewModal')),'field'=>'fieldModal','user'=>'userModal','group'=>'groupModal','project'=>'projectModal','form_def'=>'formModal','save_telegram_settings','test_telegram_settings'=>'telegramConfiguratorModal',default=>''};}
function request_entry_payload($cid,$includeFiles=true,$entryId=0){
    assert_collection((int)$cid);$cur=[];$posted=is_array($_POST['d']??null)?$_POST['d']:[];
    foreach(fields((int)$cid) as $f){
        $k=$f['k'];$ft=$f['t'];$opt=field_options($f);$v=array_key_exists($k,$posted)?$posted[$k]:($opt['default']??'');
        if($ft==='bool'){$cur[$k]=!empty($posted[$k]);validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);continue;}
        if(field_is_relation_type($ft)){
            $modePresent=relation_request_mode_present((string)$k);
            if(relation_field_is_multiple($f)&&$ft==='nested_relation'&&$entryId<=0&&!$modePresent)continue;
            $autoAll=relation_request_auto_all($f);$cur[$k]=validate_relation_value($f,$v,$entryId,$autoAll);validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);continue;
        }
        if($ft==='file'||$ft==='image'){
            $old=json_decode((string)($_POST['_file'][$k]??'null'),true);$safe=null;
            if(is_array($old)){
                if(!empty($old['file_id'])){$fr=file_by_id((int)$old['file_id']);if($fr)$safe=['file_id'=>(int)$fr['id']];}
                elseif(!empty($old['file'])){$fr=one('SELECT * FROM files WHERE pid=? AND fn=?',[current_project_id(),basename((string)$old['file'])]);if($fr)$safe=['file_id'=>(int)$fr['id']];}
            }
            $cur[$k]=!empty($_POST['_remove_file'][$k])?null:$safe;
            if($includeFiles&&($up=upload_value($k,$ft)))$cur[$k]=$up;
            validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);continue;
        }
        $cur[$k]=normalize_collection_field_value($f,$v);validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);
    }
    return $cur;
}

function request_entry_global_payload(int $cid,bool $includeFiles=true,int $entryId=0,array $seed=[]):array{
    assert_collection($cid);$posted=is_array($_POST['d']??null)?$_POST['d']:[];$cur=[];
    foreach(fields($cid) as $f){
        if(field_is_translatable($f))continue;
        $k=(string)$f['k'];$ft=(string)$f['t'];$opt=field_options($f);$has=array_key_exists($k,$posted);$v=$has?$posted[$k]:($seed[$k]??($opt['default']??''));
        if($ft==='bool'){$cur[$k]=$has?!empty($v):false;validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);continue;}
        if(field_is_relation_type($ft)){
            $modePresent=relation_request_mode_present((string)$k);
            if(relation_field_is_multiple($f)&&$ft==='nested_relation'&&$entryId<=0&&!$modePresent)continue;
            $autoAll=relation_request_auto_all($f);$cur[$k]=validate_relation_value($f,$v,$entryId,$autoAll);validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);continue;
        }
        if($ft==='file'||$ft==='image'){
            $old=json_decode((string)($_POST['_file'][$k]??'null'),true);$safe=null;
            if(is_array($old)){
                if(!empty($old['file_id'])){$fr=file_by_id((int)$old['file_id']);if($fr)$safe=['file_id'=>(int)$fr['id']];}
                elseif(!empty($old['file'])){$fr=one('SELECT * FROM files WHERE pid=? AND fn=?',[current_project_id(),basename((string)$old['file'])]);if($fr)$safe=['file_id'=>(int)$fr['id']];}
            }
            $cur[$k]=!empty($_POST['_remove_file'][$k])?null:($safe??($seed[$k]??null));
            if($includeFiles&&($up=upload_value($k,$ft)))$cur[$k]=$up;
            validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);continue;
        }
        $cur[$k]=normalize_collection_field_value($f,$v);validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);
    }
    return $cur;
}

function request_entry_payload_for_lang(int $cid,string $lang,bool $includeFiles=true,int $entryId=0,array $seed=[]):array{
    assert_collection($cid);$posted=is_array($_POST['translations'][$lang]['d']??null)?$_POST['translations'][$lang]['d']:[];$cur=[];
    foreach(fields($cid) as $f){
        if(!field_is_translatable($f))continue;
        $k=(string)$f['k'];$opt=field_options($f);$has=array_key_exists($k,$posted);$v=$has?$posted[$k]:($seed[$k]??($opt['default']??''));
        if((!$has||field_value_empty($f,$v))&&array_key_exists($k,$seed)&&!field_value_empty($f,$seed[$k]))$v=$seed[$k];
        $cur[$k]=normalize_collection_field_value($f,$v);validate_required_value($f,$cur[$k]);validate_field_rules($f,$cur[$k],$entryId);
    }
    return $cur;
}
function entry_post_pack(?array $old,int $cid,int $entryId=0,bool $includeFiles=true):array{
    $langs=content_langs();$primary=default_content_lang();if(in_array($primary,$langs,true))$langs=array_values(array_unique(array_merge([$primary],$langs)));else $primary=$langs[0]??configured_default_content_lang();
    $title=trim((string)($_POST['t']??($old['t']??'')));if($title==='')throw new Exception(T('title_required'));
    $posted=is_array($_POST['translations']??null)?$_POST['translations']:[];$existing=$old?i18n_of($old):i18n_pack([],$title);$pack=$existing;$pack['_i18n']=true;$pack['_translated']=is_array($pack['_translated']??null)?$pack['_translated']:[];unset($pack['_titles']);
    $existingPrimary=$old?data_lang($old,$primary,false):[];$global=request_entry_global_payload($cid,$includeFiles,$entryId,$existingPrimary);$seedLocal=[];
    foreach($langs as $index=>$lang){
        $row=is_array($posted[$lang]??null)?$posted[$lang]:[];$existingLang=is_array($pack[$lang]??null)?$pack[$lang]:[];
        $existingLocal=[];foreach(fields($cid) as $f)if(field_is_translatable($f)&&array_key_exists((string)$f['k'],$existingLang))$existingLocal[(string)$f['k']]=$existingLang[(string)$f['k']];
        if($index===0){$local=request_entry_payload_for_lang($cid,$lang,$includeFiles,$entryId,$existingLocal);$seedLocal=$local;}
        else{$localSeed=array_replace($seedLocal,$existingLocal);$local=request_entry_payload_for_lang($cid,$lang,$includeFiles,$entryId,$localSeed);}
        $pack[$lang]=array_replace($global,$local);
        $was=!empty($pack['_translated'][$lang]);$postedTranslated=!empty($row['_translated']);$different=$lang===$primary||value_compare_token($local)!==value_compare_token($seedLocal);$pack['_translated'][$lang]=$postedTranslated||$was||$different;
    }
    return [$pack,$title,$pack[$primary]??array_replace($global,$seedLocal)];
}
function action(){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    if(($requestLimitError=upload_request_limit_error())!==null){flash($requestLimitError,'danger');go($_SERVER['HTTP_REFERER']??'./');}
    chk();$a=(string)($_POST['_a']??'');
    try{
        if($a==='autosave_entry'){
            if(!ok()||!can_entries())J(['ok'=>false,'error'=>'access_denied'],403);
            $cid=(int)($_POST['cid']??0);$eid=(int)($_POST['id']??0);assert_collection($cid);if($eid)assert_entry($eid,$cid);
            if(content_i18n_enabled()){$cl='__all__';$globalData=is_array($_POST['d']??null)?$_POST['d']:[];foreach(fields($cid) as $f)if(!field_is_translatable($f)&&($f['t']??'')==='bool'&&!array_key_exists($f['k'],$globalData))$globalData[$f['k']]=false;$globalData=relation_draft_apply_modes($globalData,$cid);$payload=['t'=>(string)($_POST['t']??''),'s'=>(string)($_POST['s']??''),'st'=>(string)($_POST['st']??'draft'),'d'=>$globalData,'translations'=>is_array($_POST['translations']??null)?$_POST['translations']:[]];}
            else{$cl=default_content_lang();$draftData=is_array($_POST['d']??null)?$_POST['d']:[];foreach(fields($cid) as $f)if(($f['t']??'')==='bool'&&!array_key_exists($f['k'],$draftData))$draftData[$f['k']]=false;$draftData=relation_draft_apply_modes($draftData,$cid);$payload=['t'=>(string)($_POST['t']??''),'s'=>(string)($_POST['s']??''),'st'=>(string)($_POST['st']??'draft'),'d'=>$draftData];}
            $tm=entry_draft_save(current_user_id(),$cid,$eid,$cl,$payload);J(['ok'=>true,'saved_at'=>$tm,'files_included'=>false]);
        }
        if($a==='clear_entry_draft'){
            if(!ok()||!can_entries())J(['ok'=>false,'error'=>'access_denied'],403);
            $cid=(int)($_POST['cid']??0);$eid=(int)($_POST['id']??0);assert_collection($cid);if($eid)assert_entry($eid,$cid);
            foreach(array_unique(array_merge(content_langs(),['__all__'])) as $draftLang)entry_draft_delete(current_user_id(),$cid,$eid,$draftLang);
            J(['ok'=>true]);
        }
        if($a==='set_lang'){set_lang($_POST['lang']??'ru');go($_POST['_back']??'./');}
        if($a==='set_theme'){set_theme($_POST['theme']??'light');go($_POST['_back']??'./');}
        if($a==='first_user'){
            if(!first_user_required())go('./');$l=trim((string)($_POST['l']??''));$n=trim((string)($_POST['n']??''));$pw=(string)($_POST['p']??'');
            if($l==='')throw new Exception(T('user_required'));if($pw==='')throw new Exception(T('password_required'));if(!valid_password($pw))throw new Exception(T('password_latin'));
            $tm=now();$uid=run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$l,password_hash($pw,PASSWORD_DEFAULT),$n?:$l,'admin','active',$tm,$tm]);cfg_update(function(&$c){$c['settings']['initialized']=true;$c['settings']['initialized_at']=now();});
            session_regenerate_id(true);$_SESSION['uid']=$uid;audit_event('user.create','user',$uid,$l,['role'=>'admin'],null,$uid);flash(T('user_saved'),'success');go('./');
        }
        if($a==='login'){
            $u=trim((string)($_POST['u']??''));$pw=(string)($_POST['p']??'');
            if(login_blocked($u)){flash(T('too_many_attempts'),'danger');go('./');}
            $row=one("SELECT * FROM users WHERE l=? AND st='active'",[$u]);
            if($row&&pass_ok($row['p'],$pw)){login_success($u);session_regenerate_id(true);$_SESSION['uid']=(int)$row['id'];audit_event('auth.login','user',(int)$row['id'],$u,[],null,(int)$row['id']);flash(T('enter'),'success');go('./');}
            login_fail($u);audit_event('auth.login_failed','user',0,$u,['login'=>$u],null,null);flash(T('wrong_login'),'danger');go('./');
        }
        if(!ok())go('./');
        if($a==='save_debug_settings'){
            require_perm(can_settings());$enabled=!empty($_POST['debug_mode']);cfg_update(function(&$c)use($enabled){$c['settings']['debug_mode']=$enabled;});apply_debug_mode($enabled);audit_event('settings.debug','settings',0,$enabled?'enabled':'disabled',['enabled'=>$enabled]);flash(T('debug_mode_saved'),'success');go(U(['settings'=>1]));
        }
        if($a==='verify_telegram_bot'){
            require_perm(can_settings());$pid=current_project_id();$tokenInput=trim((string)($_POST['telegram_bot_token']??''));$token=$tokenInput!==''?$tokenInput:telegram_token($pid);if($token==='')throw new Exception(T('telegram_invalid_token'));$bot=telegram_bot_info($token);$webhook=telegram_api_request($token,'getWebhookInfo');if(trim((string)($webhook['url']??''))!=='')throw new Exception(T('telegram_webhook_active'));$pending=telegram_api_request($token,'getUpdates',['limit'=>100,'timeout'=>0,'allowed_updates'=>json_encode(['message'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$lastUpdateId=0;foreach($pending as $update)if(is_array($update))$lastUpdateId=max($lastUpdateId,(int)($update['update_id']??0));telegram_store_bot($pid,$token,$bot,$lastUpdateId);audit_event('settings.telegram_verify','project',$pid,(string)$bot['username'],['bot_id'=>(int)$bot['id']],$pid);J(['ok'=>true,'message'=>T('telegram_bot_verified'),'bot'=>$bot]);
        }
        if($a==='telegram_check_updates'){
            require_perm(can_settings());$pid=current_project_id();$tokenInput=trim((string)($_POST['telegram_bot_token']??''));$token=$tokenInput!==''?$tokenInput:telegram_token($pid);if($token==='')throw new Exception(T('telegram_invalid_token'));$result=telegram_process_chat_id_updates($token,$pid);if((int)$result['replied']===0&&trim((string)($result['error']??''))!=='')throw new Exception((string)$result['error']);$chatIds=array_values($result['chat_ids']??[]);$chatId=(string)($chatIds[0]??'');audit_event('settings.telegram_chat_id','project',$pid,sprintf(T('telegram_messages_processed'),(int)$result['replied']),['replied'=>(int)$result['replied'],'chat_id'=>$chatId],$pid);J(['ok'=>true,'replied'=>(int)$result['replied'],'chat_id'=>$chatId,'message'=>(int)$result['replied']>0?T('telegram_chat_id_sent'):T('telegram_no_messages'),'bot'=>$result['bot']]);
        }
        if($a==='save_telegram_settings'){
            require_perm(can_settings());$pid=current_project_id();$current=telegram_settings($pid);$enabled=!empty($_POST['telegram_enabled']);$chatId=trim((string)($_POST['telegram_chat_id']??''));$tokenInput=trim((string)($_POST['telegram_bot_token']??''));$tokenEnc=(string)$current['token_enc'];$bot=null;
            if($tokenInput!==''){$bot=telegram_bot_info($tokenInput);$tokenEnc=api_key_encrypt($tokenInput);}if($chatId!==''&&!telegram_chat_id_valid($chatId))throw new Exception(T('telegram_invalid_chat_id'));if($enabled&&($tokenEnc===''||$chatId===''))throw new Exception(T('telegram_not_configured'));
            cfg_update(function(&$c)use($pid,$enabled,$chatId,$tokenEnc,$bot){$row=$c['integrations']['telegram'][(string)$pid]??[];if(!is_array($row))$row=[];$row['enabled']=$enabled;$row['chat_id']=$chatId;$row['token_enc']=$tokenEnc;if(is_array($bot)){$same=(int)($row['bot_id']??0)===(int)$bot['id'];$row['bot_id']=(int)$bot['id'];$row['bot_username']=(string)$bot['username'];$row['bot_name']=(string)$bot['name'];if(!$same)$row['last_update_id']=0;}$row['updated_at']=now();$c['integrations']['telegram'][(string)$pid]=$row;});audit_event('settings.telegram','project',$pid,$enabled?'enabled':'disabled',['enabled'=>$enabled,'chat_id'=>$chatId],$pid);flash(T('telegram_settings_saved'),'success');go(U(['settings'=>1]));
        }
        if($a==='test_telegram_settings'){
            require_perm(can_settings());$pid=current_project_id();$settings=telegram_settings($pid);$tokenInput=trim((string)($_POST['telegram_bot_token']??''));$token=$tokenInput!==''?$tokenInput:telegram_token($pid);$chatId=trim((string)($_POST['telegram_chat_id']??$settings['chat_id']));if($token===''||$chatId==='')throw new Exception(T('telegram_not_configured'));if(!telegram_token_valid($token))throw new Exception(T('telegram_invalid_token'));if(!telegram_chat_id_valid($chatId))throw new Exception(T('telegram_invalid_chat_id'));$pr=current_project();$message="✅ ".APP."\n\n".T('telegram_test_message')."\n".T('project').': '.($pr['n']??('#'.$pid))."\n".now();telegram_send_message($token,$chatId,$message);audit_event('settings.telegram_test','project',$pid,'sent',['chat_id'=>$chatId],$pid);flash(T('telegram_test_sent'),'success');go(U(['settings'=>1]));
        }
        if($a==='backup_project'){
            require_perm(is_admin_user());backup_project_download(current_project_id());
        }
        if($a==='restore_project_backup'){
            require_perm(is_admin_user());
            if(empty($_FILES['backup']['tmp_name'])||!is_uploaded_file($_FILES['backup']['tmp_name']))throw new Exception(T('backup_invalid'));
            if((int)($_FILES['backup']['size']??0)>max(UPLOAD_MAX*20,104857600))throw new Exception(T('backup_invalid'));
            $pid=restore_project_backup((string)$_FILES['backup']['tmp_name']);$_SESSION['_pid']=$pid;flash(T('backup_restored'),'success');go(U(['settings'=>1]));
        }
        if($a==='create_api_key'){
            require_perm(can_api());$resource=trim((string)($_POST['resource']??''));if(!preg_match('/^(collection|group|form):(\d+)$/',$resource,$m))throw new Exception(T('access_denied'));$type=$m[1];$id=(int)$m[2];if(!resource_row($type,$id))throw new Exception(T('access_denied'));create_managed_api_key($type,$id,(string)($_POST['name']??''),(string)($_POST['expires']??''));flash(T('api_key_saved'),'success');go(U(['api_keys'=>1,'resource'=>$resource]));
        }
        if($a==='revoke_api_key'){
            require_perm(can_api());$id=(int)($_POST['id']??0);$key=one('SELECT * FROM api_keys WHERE id=? AND pid=?',[$id,current_project_id()]);if(!$key)throw new Exception(T('access_denied'));q("UPDATE api_keys SET st='revoked',ua=? WHERE id=? AND pid=?",[now(),$id,current_project_id()]);audit_event('api_key.revoke',(string)$key['resource_type'],(int)$key['resource_id'],T('api_key_revoke'),['key_id'=>$id,'name'=>$key['name']??'']);flash(T('api_key_revoked'),'success');go(U(['api_keys'=>1,'resource'=>$key['resource_type'].':'.(int)$key['resource_id']]));
        }
        if($a==='set_project'){
            $id=(int)($_POST['id']??0);if(!$id||!project($id)||!project_access_allowed($id))throw new Exception(T('access_denied'));$_SESSION['_pid']=$id;audit_event('project.switch','project',$id,T('project_switched'));flash(T('project_switched'),'success');go($_POST['_return']??U(['groups'=>1]));
        }
        if($a==='toggle_favorite'){
            require_perm(can_view_entries());$cid=(int)($_POST['cid']??0);assert_collection($cid);
            if(one('SELECT id FROM favorites WHERE uid=? AND cid=?',[current_user_id(),$cid])){$fav=false;run('DELETE FROM favorites WHERE uid=? AND cid=?',[current_user_id(),$cid]);}
            else{$fav=true;run('INSERT INTO favorites(uid,cid,ca)VALUES(?,?,?)',[current_user_id(),$cid,now()]);}audit_event('favorite.toggle','collection',$cid,$fav?'add':'remove');go($_POST['_return']??request_return());
        }
        if($a==='form_def'){
            require_perm(can_manage_forms());$id=(int)($_POST['id']??0);$existing=$id?assert_form($id):null;[$n,$d,$msg,$i18n]=form_post_values($existing);$ss=unique_form_slug($_POST['s']?:$n,current_project_id(),$id);$st=($_POST['st']??'active')==='inactive'?'inactive':'active';$o=$existing?(int)$existing['o']:(int)q('SELECT COALESCE(MAX(o),0)+10 FROM forms WHERE pid=?',[current_project_id()])->fetchColumn();$retention=(int)($_POST['retention_days']??($existing['retention_days']??365));if(!in_array($retention,[0,30,90,180,365],true))$retention=365;[$access,$keyHash,$keyEnc]=access_values_from_post($existing);$cors='*';$notify=trim((string)($_POST['notify_email']??''));if($notify!==''&&!filter_var($notify,FILTER_VALIDATE_EMAIL))throw new Exception(T('form_invalid_field_value'));$webhook=trim((string)($_POST['webhook_url']??''));if($webhook!==''&&(!filter_var($webhook,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($webhook,PHP_URL_SCHEME)),['http','https'],true)))throw new Exception(T('form_invalid_field_value'));$secret=(string)($_POST['webhook_secret']??'');if($secret===''&&$existing)$secret=(string)($existing['webhook_secret']??'');$tm=now();$defs=form_fields_from_post();$pid=current_project_id();$pdo=D();$pdo->beginTransaction();try{
                if($id){q('UPDATE forms SET n=?,s=?,d=?,i18n=?,st=?,success_message=?,o=?,retention_days=?,access_mode=?,api_key_hash=?,api_key_enc=?,cors_origins=?,notify_email=?,webhook_url=?,webhook_secret=?,ua=? WHERE id=? AND pid=?',[$n,$ss,$d,$i18n,$st,$msg,$o,$retention,$access,$keyHash,$keyEnc,$cors,$notify,$webhook,$secret,$tm,$id,$pid]);}
                else $id=run('INSERT INTO forms(pid,n,s,d,i18n,st,success_message,o,retention_days,access_mode,api_key_hash,api_key_enc,cors_origins,notify_email,webhook_url,webhook_secret,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$pid,$n,$ss,$d,$i18n,$st,$msg,$o,$retention,$access,$keyHash,$keyEnc,$cors,$notify,$webhook,$secret,$tm,$tm]);
                sync_form_fields($id,$pid,$defs);$pdo->commit();
            }catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}ensure_default_api_key('form',$id);audit_event($existing?'form.update':'form.create','form',$id,$n);flash(T('form_saved'),'success');go(U(['forms'=>1]));
        }
        if($a==='del_form'){
            require_perm(can_manage_forms());$f=assert_form((int)($_POST['id']??0));$pdo=D();$pdo->beginTransaction();try{q('DELETE FROM form_submissions WHERE fid=? AND pid=?',[(int)$f['id'],current_project_id()]);q('DELETE FROM form_fields WHERE fid=? AND pid=?',[(int)$f['id'],current_project_id()]);q("DELETE FROM api_keys WHERE pid=? AND resource_type='form' AND resource_id=?",[current_project_id(),(int)$f['id']]);q('DELETE FROM forms WHERE id=? AND pid=?',[(int)$f['id'],current_project_id()]);audit_event('form.delete','form',(int)$f['id'],(string)$f['n']);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('form_deleted'),'success');go(U(['forms'=>1]));
        }
        if($a==='form_submission_status'){
            require_perm(can_manage_form_submissions());$sub=assert_form_submission((int)($_POST['id']??0));$st=in_array((string)($_POST['st']??''),['new','read','spam'],true)?(string)$_POST['st']:'read';q('UPDATE form_submissions SET st=?,ua=? WHERE id=? AND pid=?',[$st,now(),(int)$sub['id'],current_project_id()]);audit_event('submission.status','submission',(int)$sub['id'],$st,['form_id'=>(int)$sub['fid']]);flash(T('form_submission_status_saved'),'success');go(U(['form_submissions'=>(int)$sub['fid']]));
        }
        if($a==='bulk_form_submissions'){
            require_perm(can_manage_form_submissions());
            $fid=(int)($_POST['fid']??0);assert_form($fid);$raw=$_POST['ids']??[];if(!is_array($raw))$raw=[];
            $ids=array_values(array_unique(array_filter(array_map('intval',$raw),fn($id)=>$id>0)));
            if(!$ids)throw new Exception(T('form_submissions_select_required'));if(count($ids)>500)throw new Exception(T('access_denied'));
            $bulk=(string)($_POST['bulk_action']??'');if(!in_array($bulk,['read','spam','delete'],true))throw new Exception(T('access_denied'));
            $ph=implode(',',array_fill(0,count($ids),'?'));$pid=current_project_id();$valid=all('SELECT id FROM form_submissions WHERE pid=? AND fid=? AND id IN ('.$ph.')',array_merge([$pid,$fid],$ids));$validIds=array_map(fn($row)=>(int)$row['id'],$valid);
            sort($validIds);$check=$ids;sort($check);if($validIds!==$check)throw new Exception(T('access_denied'));
            $pdo=D();$pdo->beginTransaction();try{
                if($bulk==='delete')q('DELETE FROM form_submissions WHERE pid=? AND fid=? AND id IN ('.$ph.')',array_merge([$pid,$fid],$ids));
                else q('UPDATE form_submissions SET st=?,ua=? WHERE pid=? AND fid=? AND id IN ('.$ph.')',array_merge([$bulk,now(),$pid,$fid],$ids));
                $pdo->commit();
            }catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}
            audit_event($bulk==='delete'?'submission.bulk_delete':'submission.bulk_status','form',$fid,$bulk,['ids'=>$ids,'count'=>count($ids)]);flash(sprintf(T($bulk==='delete'?'form_submissions_bulk_deleted':'form_submissions_bulk_status_saved'),count($ids)),'success');
            $ret=trim((string)($_POST['_return']??''));if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go($ret);go(U(['form_submissions'=>$fid]));
        }
        if($a==='del_form_submission'){
            require_perm(can_manage_form_submissions());$sub=assert_form_submission((int)($_POST['id']??0));q('DELETE FROM form_submissions WHERE id=? AND fid=? AND pid=?',[(int)$sub['id'],(int)$sub['fid'],current_project_id()]);audit_event('submission.delete','submission',(int)$sub['id'],'',['form_id'=>(int)$sub['fid']]);flash(T('form_submission_deleted'),'success');go(U(['form_submissions'=>(int)$sub['fid']]));
        }
        if($a==='project'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$existingProject=$id?project($id):null;$n=trim((string)($_POST['n']??''));$ss=slug($_POST['s']?:$n);$d=trim((string)($_POST['d']??''));$o=$existingProject?(int)$existingProject['o']:(int)q('SELECT COALESCE(MAX(o),0)+10 FROM p')->fetchColumn();$tm=now();if(!$n)throw new Exception(T('name_required'));
            if($id){if(!$existingProject)throw new Exception(T('access_denied'));q('UPDATE p SET n=?,s=?,d=?,o=?,ua=? WHERE id=?',[$n,$ss,$d,$o,$tm,$id]);}
            else $id=run('INSERT INTO p(n,s,d,o,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?)',[$n,$ss,$d,$o,'*',$tm,$tm]);
            $_SESSION['_pid']=$id;audit_event($existingProject?'project.update':'project.create','project',$id,$n,[],$id);flash(T('project_saved'),'success');go(U(['settings'=>1]));
        }
        if($a==='del_project'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$pr=project($id);if(!$pr)throw new Exception(T('access_denied'));if((int)D()->query('SELECT COUNT(*) FROM p')->fetchColumn()<=1)throw new Exception(T('cannot_delete_last_project'));if($id===current_project_id())throw new Exception(T('cannot_delete_active_project'));
            $pdo=D();$pdo->beginTransaction();
            try{
                q("UPDATE files SET opid=?,opn=?,pid=NULL,st='global_trash',reason='project_deleted',ua=? WHERE pid=? AND st!='deleted'",[$id,$pr['n'],now(),$id]);
                q("DELETE FROM files WHERE pid=? AND st='deleted'",[$id]);
                $cids=array_map('intval',array_column(all('SELECT id FROM c WHERE pid=?',[$id]),'id'));foreach($cids as $cid){run('DELETE FROM entry_drafts WHERE cid=?',[$cid]);run('DELETE FROM entry_versions WHERE cid=?',[$cid]);run('DELETE FROM favorites WHERE cid=?',[$cid]);run('DELETE FROM gc WHERE cid=?',[$cid]);}
                audit_event('project.delete','project',$id,(string)$pr['n'],[],$id);run('DELETE FROM gc WHERE gid IN (SELECT id FROM g WHERE pid=?)',[$id]);run('DELETE FROM api_keys WHERE pid=?',[$id]);run('DELETE FROM user_projects WHERE pid=?',[$id]);run('DELETE FROM g WHERE pid=?',[$id]);run('DELETE FROM c WHERE pid=?',[$id]);run('DELETE FROM form_submissions WHERE pid=?',[$id]);run('DELETE FROM forms WHERE pid=?',[$id]);run('DELETE FROM p WHERE id=?',[$id]);
                $pdo->commit();
            }catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}
            telegram_forget_project($id);flash(T('project_deleted'),'success');go(U(['settings'=>1]));
        }
        if($a==='restore_global_file'){
            require_perm(is_admin_user());$f=global_trash_file((int)($_POST['id']??0));if(!$f)throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.basename((string)$f['fn']);if(!is_file($path))throw new Exception(T('file_missing'));q("UPDATE files SET pid=?,st='active',reason='restored',ua=? WHERE id=? AND st='global_trash' AND pid IS NULL",[current_project_id(),now(),(int)$f['id']]);audit_event('file.restore_global','file',(int)$f['id'],(string)$f['onm']);flash(T('restore'),'success');go(U(['files'=>1,'tab'=>'global_trash']));
        }
        if($a==='delete_global_file_forever'){
            require_perm(is_admin_user());$f=global_trash_file((int)($_POST['id']??0));if(!$f)throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.basename((string)$f['fn']);if(is_file($path))@unlink($path);q("DELETE FROM files WHERE id=? AND st='global_trash' AND pid IS NULL",[(int)$f['id']]);audit_event('file.delete_forever','file',(int)$f['id'],(string)$f['onm']);flash(T('delete_forever'),'success');go(U(['files'=>1,'tab'=>'global_trash']));
        }
        if($a==='assign_orphan_file'){
            require_perm(is_admin_user());$fn=basename((string)($_POST['file']??''));$pid=(int)($_POST['pid']??0);if($fn===''||!project($pid)||!is_file(UPLOAD_DIR.'/'.$fn)||one('SELECT id FROM files WHERE fn=?',[$fn]))throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.$fn;$tm=now();run('INSERT INTO files(pid,onm,fn,p,u,mime,ext,sz,st,ca,ua,reason)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$pid,$fn,$fn,UPLOAD_URL.'/'.$fn,file_url($fn),mime_of($path),clean_ext($fn),(int)filesize($path),'active',$tm,$tm,'orphan_assigned']);audit_event('file.assign_orphan','file',0,$fn,['project_id'=>$pid],$pid);flash(T('file_saved'),'success');go(U(['files'=>1,'tab'=>'orphans']));
        }
        if($a==='delete_orphan_file'){
            require_perm(is_admin_user());$fn=basename((string)($_POST['file']??''));if($fn===''||one('SELECT id FROM files WHERE fn=?',[$fn]))throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.$fn;if(is_file($path))@unlink($path);audit_event('file.delete_orphan','file',0,$fn);flash(T('delete_forever'),'success');go(U(['files'=>1,'tab'=>'orphans']));
        }
        if($a==='save_cors_settings'){
            require_perm(can_settings());$pid=current_project_id();$cors=cors_origins_from_post($_POST['cors_origins']??'');q('UPDATE p SET cors_origins=?,ua=? WHERE id=?',[$cors,now(),$pid]);audit_event('settings.cors','project',$pid,$cors,['origins'=>$cors],$pid);flash(T('cors_saved'),'success');go(U(['settings'=>1]));
        }
        if($a==='save_i18n_settings'){
            require_perm(can_settings());$enabled=!empty($_POST['content_i18n']);$before=configured_content_langs();$usage=content_language_usage();$hasData=[];
            if($enabled){$langs=$_POST['content_langs']??[];if(!is_array($langs))$langs=[];$langs=array_values(array_unique(array_intersect($langs,array_keys(CONTENT_LANGS))));if(!$langs)throw new Exception(T('last_language_locked'));$default=in_array(configured_default_content_lang(),$langs,true)?configured_default_content_lang():($langs[0]??'ru');$removed=array_values(array_diff($before,$langs));$hasData=array_values(array_filter($removed,fn($l)=>(int)($usage[$l]??0)>0));}
            else{$default=(string)($_POST['content_default_lang']??'');if(!array_key_exists($default,CONTENT_LANGS))throw new Exception(T('choose_new_default_language'));$langs=$before;if(!in_array($default,$langs,true))array_unshift($langs,$default);$langs=array_values(array_unique($langs));}
            cfg_update(function(&$c)use($enabled,$langs,$default){$c['settings']['content_i18n']=$enabled;$c['settings']['content_langs']=$langs;$c['settings']['content_default_lang']=$default;});audit_event('settings.i18n','settings',0,$default,['languages'=>$langs,'enabled'=>$enabled]);
            $_COOKIE['cms_content_lang']=$default;setcookie('cms_content_lang',$default,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);
            flash(T('content_i18n_saved'),$hasData?'warning':'success');go(U(['settings'=>1]));
        }
        if($a==='cleanup_maintenance'){
            require_perm(can_settings());$r=cleanup_maintenance();audit_event('maintenance.cleanup','settings',0,'',['drafts'=>$r['drafts'],'versions'=>$r['versions']]);flash(sprintf(T('maintenance_done'),$r['drafts'],$r['versions']),'success');go(U(['settings'=>1]));
        }
        if($a==='reset_db_config'){require_perm(is_admin_user());audit_event('settings.database_reset','settings');cfg_reset();session_destroy();go('./');}
        if($a==='user'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$l=trim((string)($_POST['l']??''));$n=trim((string)($_POST['n']??''));$pw=(string)($_POST['p']??'');$roles=['admin','developer','editor','viewer'];$role=in_array($_POST['role']??'editor',$roles,true)?$_POST['role']:'editor';$st=($_POST['st']??'active')==='active'?'active':'inactive';$tm=now();
            if(!$l)throw new Exception(T('user_required'));if($pw!==''&&!valid_password($pw))throw new Exception(T('password_latin'));if($id===current_user_id()&&$st!=='active')throw new Exception(T('self_protected'));
            $existingUser=$id?user_row($id):null;
            if($id){if(!$existingUser)throw new Exception(T('access_denied'));if($pw!=='')q('UPDATE users SET l=?,n=?,p=?,role=?,st=?,ua=? WHERE id=?',[$l,$n,password_hash($pw,PASSWORD_DEFAULT),$role,$st,$tm,$id]);else q('UPDATE users SET l=?,n=?,role=?,st=?,ua=? WHERE id=?',[$l,$n,$role,$st,$tm,$id]);}
            else{if($pw==='')throw new Exception(T('password_required'));$id=run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$l,password_hash($pw,PASSWORD_DEFAULT),$n,$role,$st,$tm,$tm]);}
            $projectRoles=$_POST['project_roles']??[];if(!is_array($projectRoles))$projectRoles=[];sync_user_project_memberships($id,$projectRoles);audit_event($existingUser?'user.update':'user.create','user',$id,$l,['role'=>$role,'status'=>$st,'projects'=>$projectRoles]);flash(T('user_saved'),'success');go(U(['users'=>1]));
        }
        if($a==='remove_user_project_access'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$pid=(int)($_POST['pid']??current_project_id());$u=user_row($id);if(!$u||!project($pid))throw new Exception(T('access_denied'));if($id===current_user_id()||($u['role']??'viewer')==='admin')throw new Exception(T('self_protected'));
            q('DELETE FROM user_projects WHERE uid=? AND pid=?',[$id,$pid]);audit_event('user.project_unlink','user',$id,(string)($u['l']??''),['project_id'=>$pid],$pid);flash(T('user_removed_from_project'),'success');$ret=trim((string)($_POST['_return']??U(['users'=>1])));if($ret===''||preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))$ret=U(['users'=>1]);go($ret);
        }
        if($a==='del_user'){require_perm(is_admin_user());$id=(int)$_POST['id'];$u=user_row($id);if(!$u)throw new Exception(T('access_denied'));if($id===current_user_id())throw new Exception(T('self_protected'));q('DELETE FROM user_projects WHERE uid=?',[$id]);run('DELETE FROM users WHERE id=?',[$id]);audit_event('user.delete','user',$id,(string)($u['l']??''));flash(T('user_deleted'),'success');go(U(['users'=>1]));}
        if($a==='group'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$existing=$id?assert_group($id):null;[$n,$d,$i18n]=resource_post_values($existing);$ss=unique_group_slug($_POST['s']?:$n,current_project_id(),$id);$o=$existing?(int)$existing['o']:(int)q('SELECT COALESCE(MAX(o),0)+10 FROM g WHERE pid=?',[current_project_id()])->fetchColumn();[$access,$keyHash,$keyEnc]=access_values_from_post($existing);$cors='*';$tm=now();
            if($id){q('UPDATE g SET n=?,s=?,d=?,i18n=?,o=?,access_mode=?,api_key_hash=?,api_key_enc=?,cors_origins=?,ua=? WHERE id=? AND pid=?',[$n,$ss,$d,$i18n,$o,$access,$keyHash,$keyEnc,$cors,$tm,$id,current_project_id()]);}
            else $id=run('INSERT INTO g(pid,n,s,d,i18n,o,access_mode,api_key_hash,api_key_enc,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),$n,$ss,$d,$i18n,$o,$access,$keyHash,$keyEnc,$cors,$tm,$tm]);
            ensure_default_api_key('group',$id);audit_event($existing?'group.update':'group.create','group',$id,$n);flash(T('group_saved'),'success');go(U(['groups'=>1]));
        }
        if($a==='group_cols'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$g=assert_group($id);$ids=$_POST['collections']??[];if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $cid){$cc=assert_collection($cid);if(collection_is_nested($cc)||(int)$cc['pid']!==(int)$g['pid'])throw new Exception(T('access_denied'));}
            $pdo=D();$pdo->beginTransaction();try{q('DELETE FROM gc WHERE gid=?',[$id]);$o=10;foreach($ids as $cid){q('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[$id,$cid,$o]);$o+=10;}$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}audit_event('group.collections_sync','group',$id,(string)$g['n'],['collection_ids'=>$ids]);flash(T('group_saved'),'success');go(U(['group'=>$id]));
        }
        if($a==='add_collection_to_group'){
            require_perm(can_schema());$gid=(int)($_POST['gid']??0);$g=assert_group($gid);$ids=$_POST['collections']??[];if(!is_array($ids))$ids=[];if(!empty($_POST['cid']))$ids[]=(int)$_POST['cid'];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $cid){$cc=assert_collection($cid);if(collection_is_nested($cc)||(int)$cc['pid']!==(int)$g['pid'])throw new Exception(T('access_denied'));}$changed=0;$pdo=D();$pdo->beginTransaction();try{foreach($ids as $cid)if(link_collection_to_group($gid,$cid))$changed++;$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}audit_event('group.collections_add','group',$gid,(string)$g['n'],['collection_ids'=>$ids]);flash(T('collection_linked'),$changed?'success':'info');$ret=(string)($_POST['_return']??U(['group'=>$gid]));go($ret);
        }
        if($a==='unlink_group_collection'){
            require_perm(can_schema());$gid=(int)($_POST['gid']??0);$cid=(int)($_POST['cid']??0);unlink_collection_from_group($gid,$cid);audit_event('group.collection_unlink','group',$gid,'',['collection_id'=>$cid]);flash(T('collection_unlinked'),'success');$ret=stable_return_url($_POST['_return']??U(['group'=>$gid]));go($ret);
        }
        if($a==='reorder_group_collections'){
            require_perm(can_schema());$gid=(int)($_POST['gid']??0);$g=assert_group($gid);$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $cid){$c=assert_collection($cid);if((int)$c['pid']!==(int)$g['pid']||!one('SELECT id FROM gc WHERE gid=? AND cid=?',[$gid,$cid]))throw new Exception(T('access_denied'));}$o=10;foreach($ids as $cid){q('UPDATE gc SET o=? WHERE gid=? AND cid=?',[$o,$gid,$cid]);$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='reorder_groups'){
            require_perm(can_schema());$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $gid)assert_group($gid);$o=10;foreach($ids as $gid){q('UPDATE g SET o=?,ua=? WHERE id=? AND pid=?',[$o,now(),$gid,current_project_id()]);$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='reorder_projects'){
            require_perm(is_admin_user());$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $pid)if(!project($pid))throw new Exception(T('access_denied'));$o=10;foreach($ids as $pid){q('UPDATE p SET o=?,ua=? WHERE id=?',[$o,now(),$pid]);$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='reorder_forms'){
            require_perm(can_manage_forms());$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $fid)assert_form($fid);$o=10;foreach($ids as $fid){q('UPDATE forms SET o=?,ua=? WHERE id=? AND pid=?',[$o,now(),$fid,current_project_id()]);$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='del_group'){require_perm(can_schema());$g=assert_group((int)$_POST['id']);$pdo=D();$pdo->beginTransaction();try{q('DELETE FROM gc WHERE gid=?',[(int)$g['id']]);q("DELETE FROM api_keys WHERE pid=? AND resource_type='group' AND resource_id=?",[current_project_id(),(int)$g['id']]);q('DELETE FROM g WHERE id=? AND pid=?',[(int)$g['id'],current_project_id()]);audit_event('group.delete','group',(int)$g['id'],(string)$g['n']);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('group_deleted'),'success');go(U(['groups'=>1]));}
        if($a==='col'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$existing=$id?assert_collection($id):null;$parentCid=$existing?(int)($existing['parent_cid']??0):(int)($_POST['parent_cid']??0);$parent=null;if($parentCid){$parent=assert_collection($parentCid);if(collection_is_nested($parent))throw new Exception(T('nested_depth_limit'));}[$n,$d,$i18n]=resource_post_values($existing);$slugInput=trim((string)($_POST['s']??''));$ss=unique_collection_slug($slugInput!==''?$slugInput:$n,current_project_id(),$id);$o=$existing?(int)$existing['o']:(int)q('SELECT COALESCE(MAX(o),0)+10 FROM c WHERE pid=? AND '.($parentCid?'parent_cid=?':'parent_cid IS NULL'),$parentCid?[current_project_id(),$parentCid]:[current_project_id()])->fetchColumn();[$access,$keyHash,$keyEnc]=access_values_from_post($existing);if($parent){$access=collection_effective_access($parent);$keyHash='';$keyEnc='';}$cors='*';$tm=now();
            if($id){$sync=!empty($_POST['_sync_sections']);$gids=$_POST['section_ids']??[];if(!is_array($gids))$gids=[];if($sync){foreach(array_values(array_unique(array_filter(array_map('intval',$gids)))) as $gid)assert_group($gid);}$pdo=D();$pdo->beginTransaction();try{q('UPDATE c SET n=?,s=?,d=?,i18n=?,o=?,access_mode=?,api_key_hash=?,api_key_enc=?,cors_origins=?,ua=? WHERE id=? AND pid=?',[$n,$ss,$d,$i18n,$o,$access,$keyHash,$keyEnc,$cors,$tm,$id,current_project_id()]);if($sync)sync_collection_groups($id,$gids);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}}
            else{$m=($_POST['m']??'multiple')==='single'?'single':'multiple';$gid=$parentCid?0:(int)($_POST['add_group_id']??0);if($gid)assert_group($gid);$pdo=D();$pdo->beginTransaction();try{$id=run('INSERT INTO c(pid,parent_cid,n,s,d,i18n,m,o,access_mode,api_key_hash,api_key_enc,cors_origins,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),$parentCid?:null,$n,$ss,$d,$i18n,$m,$o,$access,$keyHash,$keyEnc,$cors,$tm,$tm]);add_preset_fields($id,(string)($_POST['preset']??'page'));if($gid)link_collection_to_group($gid,$id);if($parent&&$m==='single'&&collection_mode($parent)==='single'&&can_entries()){$parentEntry=single_entry($parent,true);if($parentEntry){$created=col($id);if($created)single_entry($created,true,(int)$parentEntry['id']);}}$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}}
            if(!$parentCid)ensure_default_api_key('collection',$id);audit_event($existing?'collection.update':($parentCid?'collection.nested_create':'collection.create'),'collection',$id,$n,['parent_collection_id'=>$parentCid?:null]);flash($parentCid?T('nested_collection_created'):T('collection_saved'),'success');$ret=trim((string)($_POST['_return']??''));if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go($ret);go($parentCid?U(['c'=>$parentCid]):U(['c'=>$id]));
        }
        if($a==='del_col'){
            require_perm(can_schema());$c=assert_collection((int)$_POST['id']);$id=(int)$c['id'];$parentId=(int)($c['parent_cid']??0);$pdo=D();$pdo->beginTransaction();try{$deleted=delete_collection_tree($id,current_project_id());audit_event('collection.delete','collection',$id,(string)$c['n'],['deleted_collection_ids'=>$deleted,'parent_collection_id'=>$parentId?:null]);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('collection_deleted'),'success');$ret=trim((string)($_POST['_return']??''));if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go(stable_return_url($ret));go($parentId?U(['c'=>$parentId]):U(['collections'=>1]));
        }
        if($a==='clone_col'){require_perm(can_schema());$source=assert_collection((int)$_POST['id']);$id=clone_collection_schema((int)$_POST['id']);audit_event('collection.clone','collection',$id,(string)$source['n'],['source_id'=>(int)$source['id']]);flash(T('collection_cloned'),'success');go(U(['c'=>$id]));}
        if($a==='import_col_schema'){
            require_perm(can_schema());if(empty($_FILES['schema']['tmp_name'])||!is_uploaded_file($_FILES['schema']['tmp_name']))throw new Exception(T('invalid_schema'));$raw=(string)file_get_contents($_FILES['schema']['tmp_name']);$schema=json_decode($raw,true);if(json_last_error()!==JSON_ERROR_NONE)throw new Exception(T('invalid_schema'));$warnings=[];$id=import_collection_schema_array($schema,$warnings);flash(T('schema_imported').(!empty($warnings['relation_target_missing'])?' '.T('relation_import_warning'):''),!empty($warnings['relation_target_missing'])?'warning':'success');go(U(['c'=>$id]));
        }
        if($a==='reorder_collections'||$a==='reorder_fields'){
            require_perm(can_schema());$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$o=10;
            foreach(array_map('intval',$ids) as $id){if($a==='reorder_collections'){$cc=col($id);if($cc)q('UPDATE c SET o=?,ua=? WHERE id=? AND pid=?',[$o,now(),$id,current_project_id()]);}else{$ff=field($id);if($ff)q('UPDATE f SET o=?,ua=? WHERE id=?',[$o,now(),$id]);}$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='clean_files'){require_perm(can_files());$n=clean_files();audit_event('file.cleanup','files',0,'',['moved'=>$n]);flash(T('files_cleaned').$n,'success');go(U(['files'=>1]));}
        if($a==='restore_file'){require_perm(can_files());$f=assert_file((int)$_POST['id']);q("UPDATE files SET st='active',ua=? WHERE id=? AND pid=?",[now(),(int)$f['id'],current_project_id()]);audit_event('file.restore','file',(int)$f['id'],(string)$f['onm']);flash(T('restore'),'success');go(U(['files'=>1,'tab'=>'trash']));}
        if($a==='delete_file_forever'){
            require_perm(can_files());$f=assert_file((int)$_POST['id']);if($f['st']!=='trash')throw new Exception(T('access_denied'));$fn=basename((string)$f['fn']);if($fn&&is_file(UPLOAD_DIR.'/'.$fn))@unlink(UPLOAD_DIR.'/'.$fn);q("UPDATE files SET st='deleted',ua=? WHERE id=? AND pid=?",[now(),(int)$f['id'],current_project_id()]);audit_event('file.delete_forever','file',(int)$f['id'],(string)$f['onm']);flash(T('delete_forever'),'success');go(U(['files'=>1,'tab'=>'trash']));
        }
        if($a==='field'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$cid=(int)$_POST['cid'];assert_collection($cid);$existing=$id?assert_field($id,$cid):null;$r=!empty($_POST['r'])?1:0;$o=$existing?(int)$existing['o']:(int)q('SELECT COALESCE(MAX(o),0)+10 FROM f WHERE cid=?',[$cid])->fetchColumn();$tm=now();$l=trim((string)($_POST['l']??''));if($l==='')throw new Exception(T('field_required'));
            if($id){$opt=field_options($existing);unset($opt['_i18n']);$rules=validation_rules_from_array($_POST,true);foreach(['target_collection_id','mode'] as $rk)if(array_key_exists($rk,$opt))$rules[$rk]=$opt[$rk];foreach(['placeholder','hint','choice_labels'] as $rk)if(array_key_exists($rk,$opt))$rules[$rk]=$opt[$rk];$x=$rules?json_encode($rules,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;q('UPDATE f SET l=?,x=?,r=?,o=?,ua=? WHERE id=? AND cid=?',[$l,$x,$r,$o,$tm,$id,$cid]);audit_event('field.update','field',$id,$l);}
            else{$k=str_replace('-','_',slug($_POST['k']?:$l));$allowed=['text','text_global','textarea','ul_list','ol_list','ul_list_i18n','ol_list_i18n','html','email','tel','number','integer','date','datetime','bool','url','image','file','json','relation','nested_relation'];$t=in_array($_POST['t']??'text',$allowed,true)?$_POST['t']:'text';$base=json_decode((string)field_options_from_post($t,$cid),true);if(!is_array($base))$base=[];unset($base['_i18n']);$x=$base?json_encode($base,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;$id=run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$l,$k,$t,$x,$r,$o,$tm,$tm]);audit_event('field.create','field',$id,$l);}
            flash(T('field_saved'),'success');go(U(['c'=>$cid,'fields'=>1]));
        }
        if($a==='del_field'){require_perm(can_schema());$cid=(int)$_POST['cid'];assert_collection($cid);$f=assert_field((int)$_POST['id'],$cid);q('DELETE FROM f WHERE id=? AND cid=?',[(int)$f['id'],$cid]);audit_event('field.delete','field',(int)$f['id'],(string)$f['l'],['collection_id'=>$cid]);flash(T('field_deleted'),'success');go(U(['c'=>$cid,'fields'=>1]));}
        if($a==='entry'){
            require_perm(can_entries());$id=(int)($_POST['id']??0);$cid=(int)$_POST['cid'];$cc=assert_collection($cid);$old=$id?assert_entry($id,$cid):null;$st=($_POST['st']??'draft')==='published'?'published':'draft';$parentEid=collection_is_nested($cc)?($old?(int)($old['parent_eid']??0):(int)($_POST['parent_eid']??0)):0;if(collection_is_nested($cc))assert_nested_parent_entry($cc,$parentEid);if(!$id&&collection_mode($cc)==='single'&&one('SELECT id FROM e WHERE cid=? AND '.(collection_is_nested($cc)?'parent_eid=?':'parent_eid IS NULL').' LIMIT 1',collection_is_nested($cc)?[$cid,$parentEid]:[$cid]))throw new Exception(T('single_entry_limit'));$tm=now();
            $slugInput=trim((string)($_POST['s']??''));
            if(content_i18n_enabled()){[$pack,$t]=entry_post_pack($old,$cid,$id,true);$ss=unique_entry_slug($slugInput!==''?$slugInput:$t,$cid,$id);$j=json_encode($pack,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($j===false)throw new RuntimeException(T('translation_save_failed'));$changeLang=null;}
            else{$cl=default_content_lang();$t=trim((string)($_POST['t']??''));if($t==='')throw new Exception(T('title_required'));$ss=unique_entry_slug($slugInput!==''?$slugInput:$t,$cid,$id);$cur=request_entry_payload($cid,true,$id);$raw=$old?data($old):[];if(is_i18n($raw)){$pack=i18n_pack($raw,$old['t']??$t);$pack[$cl]=$cur;$pack['_translated'][$cl]=true;unset($pack['_titles']);$j=json_encode($pack,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);}else $j=json_encode($cur,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);if($j===false)throw new RuntimeException(T('translation_save_failed'));$changeLang=$cl;}
            $new=['t'=>$t,'s'=>$ss,'st'=>$st,'j'=>$j];if($old){entry_snapshot($old,current_user_id(),entry_change_summary($old,$new,$changeLang));q('UPDATE e SET parent_eid=?,uid=?,t=?,s=?,st=?,j=?,ua=? WHERE id=? AND cid=?',[$parentEid?:null,current_user_id(),$t,$ss,$st,$j,$tm,$id,$cid]);}else{$id=run('INSERT INTO e(cid,parent_eid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$parentEid?:null,current_user_id(),$t,$ss,$st,$j,$tm,$tm]);}
            foreach(array_unique(array_merge(content_langs(),['__all__'])) as $draftLang){entry_draft_delete(current_user_id(),$cid,$old?(int)$old['id']:0,$draftLang);entry_draft_delete(current_user_id(),$cid,$id,$draftLang);}audit_event($old?'entry.update':'entry.create','entry',$id,$t,['collection_id'=>$cid,'parent_entry_id'=>$parentEid?:null,'status'=>$st,'languages'=>content_langs()]);flash(T('entry_saved'),'success');$ret=trim((string)($_POST['_return']??''));if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go($ret);go(collection_is_nested($cc)?nested_collection_url($cc,$parentEid):U(['c'=>$cid]));
        }
        if($a==='restore_version'){
            require_perm(can_entries());$vid=(int)$_POST['version_id'];$v=version_row($vid);if(!$v)throw new Exception(T('access_denied'));$e=assert_entry((int)$v['eid'],(int)$v['cid']);entry_snapshot($e,current_user_id(),[T('restore_version').' #'.$vid]);q('UPDATE e SET uid=?,t=?,s=?,st=?,j=?,ua=? WHERE id=? AND cid=?',[current_user_id(),$v['t'],$v['s'],$v['st'],$v['j'],now(),$e['id'],$e['cid']]);audit_event('entry.restore_version','entry',(int)$e['id'],(string)$e['t'],['version_id'=>$vid]);flash(T('version_restored'),'success');go(U(['c'=>$e['cid'],'entry'=>$e['id']]));
        }
        if($a==='del_entry'){
            require_perm(can_entries());$cid=(int)$_POST['cid'];$cc=assert_collection($cid);$e=assert_entry((int)$_POST['id'],$cid);$parentEid=(int)($e['parent_eid']??0);$pdo=D();$pdo->beginTransaction();try{delete_nested_entries_for_parent((int)$e['id']);q('DELETE FROM entry_drafts WHERE eid=? AND cid=?',[(int)$e['id'],$cid]);q('DELETE FROM entry_versions WHERE eid=? AND cid=?',[(int)$e['id'],$cid]);q('DELETE FROM e WHERE id=? AND cid=?',[(int)$e['id'],$cid]);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}audit_event('entry.delete','entry',(int)$e['id'],(string)$e['t'],['collection_id'=>$cid,'parent_entry_id'=>$parentEid?:null]);flash(T('entry_deleted'),'success');go(collection_is_nested($cc)?nested_collection_url($cc,$parentEid):U(['c'=>$cid]));
        }
    }catch(Throwable $e){
        if($a==='autosave_entry')J(['ok'=>false,'error'=>$e->getMessage()],422);
        if(in_array($a,['verify_telegram_bot','telegram_check_updates'],true))J(['ok'=>false,'message'=>$e->getMessage()],422);
        old_store($_POST,action_modal_for($a));flash($e->getMessage(),'danger');go($_SERVER['HTTP_REFERER']??'./');
    }
}

/* HTML COMPONENTS */
function icon($n){return '<i class="bi bi-'.h($n).'"></i>';}
function token(){static $x=null;return $x??='<input type="hidden" name="_csrf" value="'.h(csrf()).'">';}
function attrs($a){$s='';foreach($a as $k=>$v){if($v===false||$v===null)continue;$s.=' '.h($k).($v===true?'':'="'.h($v).'"');}return $s;}
function inp($n,$l,$v='',$type='text',$a=[]){
    if($type!=='password'&&!array_key_exists('data-no-old',$a))$v=old_value((string)$n,$v);
    unset($a['data-no-old']);
    $base=['class'=>'form-control form-control-lg rounded-4 bg-body-tertiary border-0','type'=>$type,'name'=>$n,'value'=>is_array($v)?'':$v];
    $a=array_merge($base,$a);
    $label='<label class="form-label">'.h($l).'</label>';
    if(($a['type']??$type)==='password'){
        $a['class']='form-control form-control-lg bg-body-tertiary border-0';
        $id='pw_'.substr(md5($n.$l.random_int(1,999999)),0,10);$a['id']=$a['id']??$id;
        return '<div class="mb-3">'.$label.'<div class="input-group input-group-lg rounded-4 overflow-hidden bg-body-tertiary"><input'.attrs($a).'><button class="btn btn-outline-secondary border-0 js-pw-toggle" type="button" data-target="'.h($a['id']).'" aria-label="'.h(T('preview')).'">'.icon('eye').'</button></div></div>';
    }
    return '<div class="mb-3">'.$label.'<input'.attrs($a).'></div>';
}
function area($n,$l,$v='',$a=[]){
    if(!array_key_exists('data-no-old',$a))$v=old_value((string)$n,$v);unset($a['data-no-old']);
    $a=array_merge(['class'=>'form-control rounded-4 bg-body-tertiary border-0','name'=>$n,'rows'=>'7'],$a);
    return '<div class="mb-3"><label class="form-label">'.h($l).'</label><textarea'.attrs($a).'>'.h(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT):$v).'</textarea></div>';
}
function select_html($n,$l,$opts,$cur='',$a=[]){
    if(!array_key_exists('data-no-old',$a))$cur=old_value((string)$n,$cur);unset($a['data-no-old']);
    $a=array_merge(['class'=>'form-select rounded-4 bg-body-tertiary border-0','name'=>$n],$a);
    $h='<div class="mb-3"><label class="form-label">'.h($l).'</label><select'.attrs($a).'>';
    foreach($opts as $k=>$v)$h.='<option value="'.h($k).'" '.((string)$cur===(string)$k?'selected':'').'>'.h($v).'</option>';
    return $h.'</select></div>';
}
function modal($id,$title,$body='',$footer='',$size='modal-lg'){
    $label=h($id).'Label';
    if($footer==='')$footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('close')).'</button>';
    return '<div class="modal fade" id="'.h($id).'" tabindex="-1" aria-labelledby="'.$label.'" aria-hidden="true"><div class="modal-dialog '.h($size).' modal-dialog-scrollable"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header border-0 px-4 pt-4"><h5 class="modal-title" id="'.$label.'">'.h($title).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body px-4">'.$body.'</div><div class="modal-footer border-0 px-4 pb-4">'.$footer.'</div></div></div></div>';
}
function form_modal($id,$title,$action,$body,$footer,$size='modal-lg',$extra=''){
    $label=h($id).'Label';
    return '<div class="modal fade" id="'.h($id).'" tabindex="-1" aria-labelledby="'.$label.'" aria-hidden="true"><div class="modal-dialog '.h($size).' modal-dialog-scrollable"><form class="modal-content rounded-4 border-0 shadow-lg" method="post" '.$extra.'>'.token().'<input type="hidden" name="_a" value="'.h($action).'"><div class="modal-header border-0 px-4 pt-4"><h5 class="modal-title" id="'.$label.'">'.h($title).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body px-4">'.$body.'</div><div class="modal-footer border-0 px-4 pb-4">'.$footer.'</div></form></div></div>';
}
function post_form($a,$body,$extra=''){return '<form method="post" '.$extra.'>'.token().'<input type="hidden" name="_a" value="'.h($a).'">'.$body.'</form>';}
function lang_select(){return post_form('set_lang','<input type="hidden" name="_back" value="'.h(clean_url($_SERVER['REQUEST_URI']??'./')).'"><select class="form-select form-select-sm rounded-pill border-0 bg-body-tertiary" name="lang" onchange="this.form.submit()">'.implode('',array_map(fn($k,$v)=>'<option value="'.h($k).'" '.(lang()===$k?'selected':'').'>'.h($v).'</option>',array_keys(LANGS),LANGS)).'</select>');}
function theme_select(){return post_form('set_theme','<input type="hidden" name="_back" value="'.h(clean_url($_SERVER['REQUEST_URI']??'./')).'"><select class="form-select form-select-sm rounded-pill border-0 bg-body-tertiary" name="theme" onchange="this.form.submit()"><option value="light" '.(theme()==='light'?'selected':'').'>☀️ '.h(T('light')).'</option><option value="dark" '.(theme()==='dark'?'selected':'').'>🌙 '.h(T('dark')).'</option></select>');}
function theme_toggle(){return post_form('set_theme','<input type="hidden" name="_back" value="'.h(clean_url($_SERVER['REQUEST_URI']??'./')).'"><input type="hidden" name="theme" value="light"><div class="d-flex align-items-center justify-content-between gap-3"><div><div class="fw-semibold">'.h(T('dark_mode')).'</div><div class="text-muted small">'.h(T(theme()==='dark'?'dark':'light')).'</div></div><label class="ios-toggle"><input type="checkbox" name="theme" value="dark" onchange="this.form.submit()" '.(theme()==='dark'?'checked':'').'><span></span></label></div>');}

function client_js():string{return <<<'CMSJS'

    document.addEventListener("click",e=>{
        const b=e.target.closest(".js-pw-toggle");
        if(!b)return;
        const i=document.getElementById(b.dataset.target);
        if(!i)return;
        const show=i.type==="password";
        i.type=show?"text":"password";
        b.innerHTML=show?"<i class=\"bi bi-eye-slash\"></i>":"<i class=\"bi bi-eye\"></i>";
    });
    const modalUrlParams={collectionEditModal:"edit_col",collectionNewModal:"new_col",projectModal:"project_edit",formModal:"form_edit",groupModal:"gid",userModal:"uid",fieldModal:"fid"};
    document.addEventListener("hidden.bs.modal",e=>{
        const key=modalUrlParams[e.target&&e.target.id];
        if(!key)return;
        const url=new URL(window.location.href);
        if(!url.searchParams.has(key))return;
        url.searchParams.delete(key);
        const query=url.searchParams.toString();
        window.history.replaceState({},document.title,url.pathname+(query?"?"+query:"")+url.hash);
    });
    document.addEventListener("DOMContentLoaded",()=>{
        const L=window.CMS_TABLE_I18N||{};
        document.querySelectorAll(".js-field-type").forEach(sel=>{
            const form=sel.closest("form")||document;
            const relationBox=form.querySelector(".cms-relation-options");
            const nestedRelationBox=form.querySelector(".cms-nested-relation-options");
            const sync=()=>{
                if(relationBox)relationBox.classList.toggle("d-none",sel.value!=="relation");
                if(nestedRelationBox)nestedRelationBox.classList.toggle("d-none",sel.value!=="nested_relation");
            };
            sel.addEventListener("change",sync);sync();
        });
        const cmsSlug=(v)=>String(v||"").toLowerCase()
            .replace(/[аә]/g,"a").replace(/[б]/g,"b").replace(/[в]/g,"v").replace(/[гғ]/g,"g").replace(/[д]/g,"d").replace(/[еёэ]/g,"e").replace(/[ж]/g,"zh").replace(/[з]/g,"z").replace(/[иі]/g,"i").replace(/[й]/g,"y").replace(/[кқ]/g,"k").replace(/[л]/g,"l").replace(/[м]/g,"m").replace(/[нң]/g,"n").replace(/[оө]/g,"o").replace(/[п]/g,"p").replace(/[р]/g,"r").replace(/[с]/g,"s").replace(/[т]/g,"t").replace(/[уұү]/g,"u").replace(/[ф]/g,"f").replace(/[хһ]/g,"h").replace(/[ц]/g,"ts").replace(/[ч]/g,"ch").replace(/[ш]/g,"sh").replace(/[щ]/g,"sch").replace(/[ы]/g,"y").replace(/[ьъ]/g,"")
            .replace(/[^a-z0-9_-]+/g,"-").replace(/-+/g,"-").replace(/^-|-$/g,"")||"item";
        document.querySelectorAll("[data-slug-source]").forEach(src=>{
            const form=src.closest("form")||document;
            const target=form.querySelector("[name=\""+src.dataset.slugSource+"\"]");
            if(!target)return;
            let touched=!!target.value;
            target.addEventListener("input",()=>{touched=true;});
            src.addEventListener("input",()=>{if(!touched)target.value=cmsSlug(src.value);});
        });
        const presetMap={content:["Content","content","html",1],excerpt:["Excerpt","excerpt","textarea",0],image:["Image","image","image",0],file:["File","file","file",0],date:["Date","date","date",0],url:["URL","url","url",0],relation:["Relation","relation","relation",0],nested_relation:["Nested relation","nested_relation","nested_relation",0]};
        document.querySelectorAll(".js-field-preset").forEach(sel=>sel.addEventListener("change",()=>{
            const p=presetMap[sel.value]; if(!p)return;
            const form=sel.closest("form"); if(!form)return;
            const l=form.querySelector("[name=\"l\"]"), k=form.querySelector("[name=\"k\"]"), t=form.querySelector("[name=\"t\"]"), r=form.querySelector("[name=\"r\"]");
            if(l)l.value=p[0]; if(k)k.value=p[1]; if(t){t.value=p[2]; t.dispatchEvent(new Event("change"));} if(r)r.checked=!!p[3];
        }));
        document.querySelectorAll(".js-form-schema-builder").forEach(builder=>{
            const list=builder.querySelector(".js-form-schema-list"),tpl=builder.querySelector(".js-form-field-template"),add=builder.querySelector(".js-add-form-field");if(!list||!tpl||!add)return;
            let next=1000000;
            const rows=()=>[...list.querySelectorAll("[data-form-field-row]")];
            const updateOrder=()=>rows().forEach((row,index)=>{const order=row.querySelector(".js-form-field-order");if(order)order.value=String((index+1)*10);});
            const moveRow=(row,direction)=>{
                if(direction<0){const previous=row.previousElementSibling;if(previous)list.insertBefore(row,previous);}
                else{const nextRow=row.nextElementSibling;if(nextRow)list.insertBefore(nextRow,row);}
                updateOrder();
            };

            // Mouse uses native HTML5 drag-and-drop. Touch/pen use Pointer Events.
            // The previous implementation captured the pointer on the handle, which made
            // elementFromPoint unreliable in a number of browsers and the row did not move.
            let nativeDragging=null;
            let pointerDragging=null;

            const placeRowAtPointer=(row,clientX,clientY)=>{
                const under=document.elementFromPoint(clientX,clientY);
                const target=under&&under.closest?under.closest("[data-form-field-row]"):null;
                if(!target||target===row||target.parentElement!==list)return;
                const rect=target.getBoundingClientRect();
                const before=clientY < rect.top + rect.height/2;
                list.insertBefore(row,before?target:target.nextElementSibling);
                updateOrder();
            };

            list.addEventListener("dragover",e=>{
                if(!nativeDragging)return;
                e.preventDefault();
                if(e.dataTransfer)e.dataTransfer.dropEffect="move";
                placeRowAtPointer(nativeDragging,e.clientX,e.clientY);
            });
            list.addEventListener("drop",e=>{
                if(!nativeDragging)return;
                e.preventDefault();
                updateOrder();
            });
            document.addEventListener("pointermove",e=>{
                if(!pointerDragging||pointerDragging.pointerId!==e.pointerId)return;
                e.preventDefault();
                placeRowAtPointer(pointerDragging.row,e.clientX,e.clientY);
            },{passive:false});
            const finishPointerDrag=e=>{
                if(!pointerDragging||pointerDragging.pointerId!==e.pointerId)return;
                pointerDragging.row.classList.remove("is-dragging");
                pointerDragging=null;
                updateOrder();
            };
            document.addEventListener("pointerup",finishPointerDrag);
            document.addEventListener("pointercancel",finishPointerDrag);

            const wire=row=>{
                const label=row.querySelector(".js-form-field-label"),key=row.querySelector(".js-form-field-key");
                if(label&&key){let touched=!!key.value;key.addEventListener("input",()=>touched=true);label.addEventListener("input",()=>{if(!touched)key.value=cmsSlug(label.value).replace(/-/g,"_");});}
                row.querySelector(".js-remove-form-field")?.addEventListener("click",()=>{row.remove();updateOrder();});
                const handle=row.querySelector(".js-form-field-drag");
                if(handle){
                    let nativeDragAllowed=false;
                    row.draggable=false;

                    handle.addEventListener("pointerdown",e=>{
                        if(e.pointerType==="mouse"){
                            if(e.button!==0)return;
                            nativeDragAllowed=true;
                            row.draggable=true;
                            return;
                        }
                        pointerDragging={row,pointerId:e.pointerId};
                        row.classList.add("is-dragging");
                        e.preventDefault();
                    });

                    row.addEventListener("dragstart",e=>{
                        if(!nativeDragAllowed){e.preventDefault();return;}
                        nativeDragging=row;
                        row.classList.add("is-dragging");
                        if(e.dataTransfer){
                            e.dataTransfer.effectAllowed="move";
                            e.dataTransfer.setData("text/plain","form-field");
                        }
                    });
                    row.addEventListener("dragend",()=>{
                        row.classList.remove("is-dragging");
                        nativeDragging=null;
                        nativeDragAllowed=false;
                        row.draggable=false;
                        updateOrder();
                    });
                    handle.addEventListener("pointerup",e=>{
                        if(e.pointerType==="mouse"&&!nativeDragging){
                            nativeDragAllowed=false;
                            row.draggable=false;
                        }
                    });
                    handle.addEventListener("pointercancel",()=>{
                        nativeDragAllowed=false;
                        if(!nativeDragging)row.draggable=false;
                    });
                    handle.addEventListener("keydown",e=>{
                        if(e.key==="ArrowUp"){e.preventDefault();moveRow(row,-1);handle.focus();}
                        if(e.key==="ArrowDown"){e.preventDefault();moveRow(row,1);handle.focus();}
                        if(e.key==="Home"){e.preventDefault();list.prepend(row);updateOrder();handle.focus();}
                        if(e.key==="End"){e.preventDefault();list.append(row);updateOrder();handle.focus();}
                    });
                }
            };
            rows().forEach(wire);updateOrder();
            add.addEventListener("click",()=>{const html=tpl.innerHTML.replaceAll("999999",String(next));next++;const box=document.createElement("div");box.innerHTML=html.trim();const row=box.firstElementChild;if(!row)return;list.appendChild(row);wire(row);updateOrder();row.querySelector("input")?.focus();});
        });
        document.querySelectorAll(".js-relation-picker").forEach(picker=>{
            const list=picker.querySelector(".list-group"),allToggle=picker.querySelector(".js-relation-all"),manualState=new Map();
            const items=()=>Array.from(picker.querySelectorAll(".js-relation-item"));
            const reorder=()=>{
                if(!list)return;const arr=items();arr.forEach((it,i)=>{if(!it.dataset.orig)it.dataset.orig=String(i);});
                arr.sort((a,b)=>{const ac=a.querySelector(".js-relation-check")?.checked,bc=b.querySelector(".js-relation-check")?.checked;if(ac!==bc)return ac?-1:1;return Number(a.dataset.orig)-Number(b.dataset.orig);}).forEach(it=>list.appendChild(it));
            };
            const updateControls=()=>{const automatic=!!allToggle?.checked;items().forEach(it=>{const check=it.querySelector(".js-relation-check");if(!check)return;check.disabled=automatic;it.querySelectorAll(".js-relation-up,.js-relation-down").forEach(btn=>btn.disabled=automatic||!check.checked);});};
            const syncAutomatic=(initial=false)=>{
                const automatic=!!allToggle?.checked;if(automatic){if(!initial)items().forEach(it=>{const check=it.querySelector(".js-relation-check");if(check)manualState.set(check.value,check.checked);});items().forEach(it=>{const check=it.querySelector(".js-relation-check");if(check)check.checked=true;});}
                else if(!initial&&manualState.size){items().forEach(it=>{const check=it.querySelector(".js-relation-check");if(check&&manualState.has(check.value))check.checked=manualState.get(check.value);});manualState.clear();}
                reorder();updateControls();
            };
            picker.addEventListener("change",e=>{if(e.target.matches(".js-relation-all")){syncAutomatic();return;}if(e.target.matches(".js-relation-check")){e.target.closest(".js-relation-item").dataset.selected=e.target.checked?"1":"0";reorder();updateControls();}});
            picker.addEventListener("click",e=>{
                const up=e.target.closest(".js-relation-up"),down=e.target.closest(".js-relation-down");if(!up&&!down)return;if(allToggle?.checked)return;
                const it=e.target.closest(".js-relation-item");if(!it||!it.querySelector(".js-relation-check")?.checked||!list)return;
                const sib=up?it.previousElementSibling:it.nextElementSibling;if(sib&&sib.querySelector(".js-relation-check")?.checked){up?list.insertBefore(it,sib):list.insertBefore(sib,it);picker.dispatchEvent(new Event("change",{bubbles:true}));}
            });
            const search=picker.querySelector(".js-relation-search");if(search)search.addEventListener("input",()=>{const q=search.value.trim().toLowerCase();items().forEach(it=>it.classList.toggle("d-none",q&&!(it.dataset.search||"").includes(q)));});
            syncAutomatic(true);
        });
        document.querySelectorAll(".js-list-editor").forEach(editor=>{
            const list=editor.querySelector(".js-list-items"), tpl=editor.querySelector(".js-list-item-template"), add=editor.querySelector(".js-list-add");
            if(!list||!tpl||!add)return;
            const rows=()=>Array.from(list.querySelectorAll(":scope > .js-list-item"));
            const inputOf=row=>row.querySelector(".js-list-item-input");
            const update=()=>{const all=rows();all.forEach((row,index)=>{const up=row.querySelector(".js-list-up"),down=row.querySelector(".js-list-down");if(up)up.disabled=index===0;if(down)down.disabled=index===all.length-1;});};
            const append=()=>{const clone=tpl.content.firstElementChild?.cloneNode(true);if(!clone)return;list.appendChild(clone);update();inputOf(clone)?.focus();};
            add.addEventListener("click",append);
            editor.addEventListener("click",e=>{
                const up=e.target.closest(".js-list-up"),down=e.target.closest(".js-list-down"),remove=e.target.closest(".js-list-delete");
                if(!up&&!down&&!remove)return;
                const row=e.target.closest(".js-list-item");if(!row)return;
                if(remove){
                    const next=row.nextElementSibling||row.previousElementSibling;
                    row.remove();update();inputOf(next)?.focus();return;
                }
                if(up&&row.previousElementSibling)list.insertBefore(row,row.previousElementSibling);
                if(down&&row.nextElementSibling)list.insertBefore(row.nextElementSibling,row);
                update();inputOf(row)?.focus();
            });
            editor.closest("form")?.addEventListener("submit",e=>{
                let inputs=rows().map(inputOf).filter(Boolean);inputs.forEach(input=>input.setCustomValidity(""));
                if(editor.dataset.required==="1"&&!inputs.some(input=>input.value.trim()!=="")){
                    if(inputs.length===0){append();inputs=rows().map(inputOf).filter(Boolean);}
                    const input=inputs[0];if(input){input.setCustomValidity(editor.dataset.requiredMessage||"Required");input.reportValidity();}e.preventDefault();
                }
            });
            if(rows().length===0)append();else update();
        });
        document.querySelectorAll(".js-html-source").forEach(src=>{
            const pane=document.querySelector(src.dataset.htmlPreview||"");const target=pane?.querySelector(".js-html-preview");if(!target)return;let timer=null;
            const render=()=>{clearTimeout(timer);timer=setTimeout(()=>{const fd=new URLSearchParams();fd.set("_csrf",document.querySelector("meta[name=csrf-token]")?.content||"");fd.set("html",src.value);fetch("./?html_sanitize=1",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:fd,credentials:"same-origin"}).then(r=>r.text()).then(x=>target.innerHTML=x).catch(()=>target.textContent=src.value);},180);};
            src.addEventListener("input",render);document.querySelectorAll("[data-bs-target=\""+src.dataset.htmlPreview+"\"]").forEach(btn=>btn.addEventListener("shown.bs.tab",render));
        });
        document.querySelectorAll(".js-group-collections-list").forEach(list=>{
            const modal=list.closest(".modal");
            const search=modal?modal.querySelector(".js-group-collections-search"):null;
            const items=Array.from(list.querySelectorAll(".js-group-collection-item"));
            const arrange=()=>{
                const q=(search&&search.value?search.value:"").toLowerCase().trim();
                items.sort((a,b)=>{
                    const ac=!!a.querySelector(".js-group-collection-check")?.checked;
                    const bc=!!b.querySelector(".js-group-collection-check")?.checked;
                    if(ac!==bc)return ac?-1:1;
                    return Number(a.dataset.original||0)-Number(b.dataset.original||0);
                }).forEach(item=>{
                    const checked=!!item.querySelector(".js-group-collection-check")?.checked;
                    item.querySelector(".js-selected-badge")?.classList.toggle("d-none",!checked);
                    item.classList.toggle("d-none",!!q && !(item.dataset.search||"").includes(q));
                    list.appendChild(item);
                });
            };
            list.addEventListener("change",e=>{if(e.target.matches(".js-group-collection-check"))arrange();});
            if(search)search.addEventListener("input",arrange);
            arrange();
        });
        const getText=el=>(el.textContent||"").replace(/\s+/g," ").trim();
        const norm=v=>String(v||"").toLowerCase();
        const cmp=(a,b)=>{
            const ax=getText(a), bx=getText(b);
            const an=Number(ax.replace(/[^0-9.,-]/g,"").replace(",","."));
            const bn=Number(bx.replace(/[^0-9.,-]/g,"").replace(",","."));
            if(ax!==""&&bx!==""&&!Number.isNaN(an)&&!Number.isNaN(bn))return an-bn;
            const ad=Date.parse(ax), bd=Date.parse(bx);
            if(!Number.isNaN(ad)&&!Number.isNaN(bd))return ad-bd;
            return ax.localeCompare(bx,undefined,{numeric:true,sensitivity:"base"});
        };
        document.querySelectorAll("table.table").forEach((table,idx)=>{
            if(table.dataset.cmsEnhanced||table.dataset.serverTable)return;
            const tbody=table.tBodies&&table.tBodies[0];
            if(!tbody)return;
            const rows=Array.from(tbody.rows).filter(r=>!(r.cells.length===1&&r.cells[0].hasAttribute("colspan")));
            if(!rows.length)return;
            table.dataset.cmsEnhanced="1";
            const wrap=table.closest(".table-responsive")||table.parentElement;
            const tools=document.createElement("div");
            tools.className="cms-table-tools d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between mb-3";
            const input=document.createElement("input");
            input.type="search";
            input.className="form-control rounded-pill bg-body-tertiary border-0";
            input.placeholder=L.search||"Search";
            const left=document.createElement("div");
            left.className="flex-grow-1";
            left.appendChild(input);
            const reset=document.createElement("button");
            reset.type="button";
            reset.className="btn btn-outline-dark";
            reset.innerHTML="<i class=\"bi bi-arrow-counterclockwise\"></i> "+(L.reset||"Reset");
            tools.appendChild(left);
            tools.appendChild(reset);
            if(wrap&&wrap.parentNode)wrap.parentNode.insertBefore(tools,wrap);
            const no=document.createElement("tr");
            no.className="cms-no-results d-none";
            no.innerHTML="<td colspan=\""+(table.tHead?table.tHead.rows[0].cells.length:rows[0].cells.length)+"\" class=\"text-center text-muted py-4\">"+(L.no_results||"No results")+"</td>";
            tbody.appendChild(no);
            const applyFilter=()=>{
                const q=norm(input.value);
                let shown=0;
                rows.forEach(r=>{
                    const ok=!q||norm(getText(r)).includes(q);
                    r.classList.toggle("d-none",!ok);
                    if(ok)shown++;
                });
                no.classList.toggle("d-none",shown!==0);
            };
            input.addEventListener("input",applyFilter);
            reset.addEventListener("click",()=>{input.value="";applyFilter();});
            if(table.tHead){
                Array.from(table.tHead.rows[0].cells).forEach((th,i)=>{
                    if(th.classList.contains("text-end")||i===table.tHead.rows[0].cells.length-1)return;
                    const old=th.innerHTML;
                    th.innerHTML="<button type=\"button\" class=\"btn btn-link p-0 text-reset fw-bold text-decoration-none d-inline-flex align-items-center gap-1 cms-sort-btn\">"+old+" <i class=\"bi bi-chevron-expand small\"></i></button>";
                    th.dataset.dir="";
                    th.addEventListener("click",()=>{
                        const dir=th.dataset.dir==="asc"?"desc":"asc";
                        Array.from(table.tHead.rows[0].cells).forEach(x=>{if(x!==th){x.dataset.dir="";const ic=x.querySelector(".cms-sort-btn i");if(ic)ic.className="bi bi-chevron-expand small";}});
                        th.dataset.dir=dir;
                        const ic=th.querySelector(".cms-sort-btn i");
                        if(ic)ic.className=dir==="asc"?"bi bi-chevron-up small":"bi bi-chevron-down small";
                        rows.sort((ra,rb)=>cmp(ra.cells[i]||ra,rb.cells[i]||rb)*(dir==="asc"?1:-1));
                        rows.forEach(r=>tbody.insertBefore(r,no));
                        applyFilter();
                    });
                    th.title=(L.sort_asc||"Sort ascending")+" / "+(L.sort_desc||"Sort descending");
                });
            }
        });
        document.addEventListener("shown.bs.dropdown",e=>{
            const btn=e.target.matches&&e.target.matches("[data-bs-toggle=dropdown]")?e.target:e.target.querySelector&&e.target.querySelector("[data-bs-toggle=dropdown]");
            const dd=btn?btn.closest(".cms-action-dd"):null;
            if(!dd||!dd.closest(".table-responsive"))return;
            const menu=dd.querySelector(".dropdown-menu");
            if(!menu)return;

            dd.dataset.portal="1";
            dd._cmsMenu=menu;
            dd._cmsParent=menu.parentNode;
            dd._cmsNext=menu.nextSibling;
            document.body.appendChild(menu);
            menu.classList.add("show","cms-dd-portal");

            const place=()=>{
                const r=btn.getBoundingClientRect();
                const mw=menu.offsetWidth||220;
                const mh=menu.offsetHeight||120;
                let left=r.right-mw;
                let top=r.bottom+8;

                left=Math.max(12,Math.min(left,window.innerWidth-mw-12));
                if(top+mh+12>window.innerHeight)top=Math.max(12,r.top-mh-8);

                menu.style.position="fixed";
                menu.style.zIndex="3000";
                menu.style.left=left+"px";
                menu.style.top=top+"px";
                menu.style.right="auto";
                menu.style.bottom="auto";
            };

            requestAnimationFrame(place);
            dd._cmsPlace=place;
            window.addEventListener("scroll",place,true);
            window.addEventListener("resize",place);
        });
        document.addEventListener("hidden.bs.dropdown",e=>{
            const btn=e.target.matches&&e.target.matches("[data-bs-toggle=dropdown]")?e.target:e.target.querySelector&&e.target.querySelector("[data-bs-toggle=dropdown]");
            const dd=btn?btn.closest(".cms-action-dd"):null;
            if(!dd||!dd.dataset.portal)return;
            const menu=dd._cmsMenu;

            if(menu&&dd._cmsParent){
                menu.classList.remove("cms-dd-portal","show");
                menu.removeAttribute("style");
                dd._cmsParent.insertBefore(menu,dd._cmsNext||null);
            }
            if(dd._cmsPlace){
                window.removeEventListener("scroll",dd._cmsPlace,true);
                window.removeEventListener("resize",dd._cmsPlace);
            }
            delete dd.dataset.portal;
            delete dd._cmsMenu;
            delete dd._cmsParent;
            delete dd._cmsNext;
            delete dd._cmsPlace;
        });

        const BACK_KEY="cms_smart_back_url";
        const BACK_TIME_KEY="cms_smart_back_time";
        const BACK_TTL=30*60*1000;
        const cleanHref=u=>{
            try{const x=new URL(u,location.href);x.hash="";return x.href;}catch(e){return u||"./";}
        };
        const rememberBack=(a)=>{
            try{
                const current=cleanHref(location.href);
                const target=cleanHref(a.href);
                if(current!==target){
                    sessionStorage.setItem(BACK_KEY,current);
                    sessionStorage.setItem(BACK_TIME_KEY,String(Date.now()));
                }
            }catch(e){}
        };
        const storedBack=()=>{
            try{
                const u=sessionStorage.getItem(BACK_KEY)||"";
                const t=parseInt(sessionStorage.getItem(BACK_TIME_KEY)||"0",10);
                if(!u||!t||Date.now()-t>BACK_TTL||cleanHref(u)===cleanHref(location.href)){
                    sessionStorage.removeItem(BACK_KEY);
                    sessionStorage.removeItem(BACK_TIME_KEY);
                    return "";
                }
                return u;
            }catch(e){return "";}
        };
        document.addEventListener("click",e=>{
            const a=e.target.closest&&e.target.closest("a[data-cms-remember-back]");
            if(a)rememberBack(a);
        });
        document.querySelectorAll(".js-smart-back").forEach(btn=>{
            const hasStored=!!storedBack();
            let hasRef=false;
            try{hasRef=!!document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1;}catch(e){}
            if(hasStored||hasRef)btn.classList.remove("d-none");
            btn.addEventListener("click",()=>{
                try{
                    if(document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1){history.back();return;}
                }catch(e){}
                const stored=storedBack();
                if(stored){
                    sessionStorage.removeItem(BACK_KEY);
                    sessionStorage.removeItem(BACK_TIME_KEY);
                    location.href=stored;
                    return;
                }
                location.href=btn.dataset.fallback||"./";
            });
        });
    });


(()=>{
const A=window.CMS_ADV||{};
const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
const toast=(text)=>{let x=document.querySelector('.cms-toast');if(!x){x=document.createElement('div');x.className='cms-toast alert alert-success shadow';document.body.appendChild(x);}x.textContent=text;x.classList.remove('d-none');clearTimeout(x._t);x._t=setTimeout(()=>x.classList.add('d-none'),1800);};
const copy=async text=>{try{await navigator.clipboard.writeText(text);}catch(e){const t=document.createElement('textarea');t.value=text;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}toast(A.copied||'Copied');};
document.addEventListener('click',e=>{const b=e.target.closest('.js-copy');if(b){e.preventDefault();copy(b.dataset.copy||'');}});
// One shared delete modal.
document.addEventListener('click',e=>{const b=e.target.closest('.js-delete-trigger');if(!b)return;e.preventDefault();let payload={};try{payload=JSON.parse(b.dataset.deletePayload||'{}');}catch(_){}const form=document.getElementById('universalDeleteForm'),fields=document.getElementById('universalDeleteFields');if(!form||!fields)return;document.getElementById('universalDeleteAction').value=b.dataset.deleteAction||'';document.getElementById('universalDeleteTitle').textContent=b.dataset.deleteTitle||'';document.getElementById('universalDeleteMessage').textContent=b.dataset.deleteMessage||'';const confirmLabel=document.getElementById('universalDeleteConfirmLabel'),confirmIcon=document.getElementById('universalDeleteConfirmIcon');if(confirmLabel)confirmLabel.textContent=b.dataset.deleteConfirm||A.delete_label||'Delete';if(confirmIcon)confirmIcon.innerHTML='<i class="bi bi-'+(b.dataset.deleteIcon||'trash3')+'"></i>';fields.innerHTML='';Object.entries(payload).forEach(([k,v])=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=String(v);fields.appendChild(i);});const current=b.closest('.modal');if(current){bootstrap.Modal.getInstance(current)?.hide();setTimeout(()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('universalDeleteModal')).show(),180);}else bootstrap.Modal.getOrCreateInstance(document.getElementById('universalDeleteModal')).show();});
// Form submissions: Gmail-like selection. Delegated handlers keep working even when
// the table is rendered after the main layout scripts or replaced in the DOM.
const submissionPageOf=element=>element?.closest?.('.submission-page')||null;
const submissionBoxes=page=>page?[...page.querySelectorAll('.js-submission-select')]:[];
const syncSubmissionSelection=page=>{
    if(!page)return;
    const form=page.querySelector('#bulkSubmissionForm');
    const master=page.querySelector('.js-submission-select-all');
    const boxes=submissionBoxes(page);
    const selected=boxes.filter(box=>box.checked);
    const count=selected.length;

    if(form){
        form.hidden=count===0;
        const topbar=document.querySelector('.premium-topbar');
        const top=Math.ceil(topbar?.getBoundingClientRect().height||0)+12;
        form.style.setProperty('--submission-sticky-top',top+'px');
        form.querySelectorAll('.js-submission-selected-count').forEach(node=>node.textContent=String(count));
    }

    page.querySelectorAll('.js-bulk-delete-count').forEach(node=>node.textContent=String(count));
    page.querySelectorAll('.js-bulk-submit').forEach(button=>button.disabled=count===0);
    boxes.forEach(box=>box.closest('tr')?.classList.toggle('is-selected',box.checked));

    if(master){
        master.indeterminate=count>0&&count<boxes.length;
        master.checked=boxes.length>0&&count===boxes.length;
        master.setAttribute('aria-checked',master.indeterminate?'mixed':String(master.checked));
    }
};
const setAllSubmissionBoxes=(master)=>{
    const page=submissionPageOf(master);
    if(!page)return;
    const checked=master.checked;
    submissionBoxes(page).forEach(box=>{
        box.checked=checked;
        box.dispatchEvent(new Event('input',{bubbles:true}));
    });
    syncSubmissionSelection(page);
};

document.addEventListener('change',event=>{
    const target=event.target;
    if(!(target instanceof HTMLInputElement))return;
    if(target.matches('.js-submission-select-all')){
        setAllSubmissionBoxes(target);
        return;
    }
    if(target.matches('.js-submission-select'))syncSubmissionSelection(submissionPageOf(target));
});
// The click fallback is intentional: some customised checkbox styles do not reliably
// emit change in every browser. Run after the native checked state has changed.
document.addEventListener('click',event=>{
    const master=event.target.closest?.('.js-submission-select-all');
    if(!master)return;
    setTimeout(()=>setAllSubmissionBoxes(master),0);
});

document.querySelectorAll('.submission-page').forEach(syncSubmissionSelection);
window.addEventListener('resize',()=>document.querySelectorAll('.submission-page').forEach(syncSubmissionSelection),{passive:true});

// Mobile tables become cards without horizontal scrolling.
document.querySelectorAll('table.cms-responsive').forEach(table=>{const heads=[...table.querySelectorAll('thead th')].map(x=>(x.textContent||'').trim());table.querySelectorAll('tbody tr').forEach(row=>[...row.cells].forEach((td,i)=>td.dataset.label=heads[i]||''));});
// Premium dashboard visualisations.
document.querySelectorAll('[data-pd-progress]').forEach(el=>{const value=Math.max(0,Math.min(100,Number(el.dataset.pdProgress||0)));const tone=el.dataset.pdTone||'var(--ui-blue)';el.style.setProperty('--pd-tone',tone);requestAnimationFrame(()=>{el.style.width=value+'%';});});
document.querySelectorAll('[data-pd-donut]').forEach(el=>{const value=Math.max(0,Math.min(100,Number(el.dataset.pdDonut||0)));el.style.setProperty('--pd-value',value+'%');el.style.setProperty('--pd-tone',el.dataset.pdTone||'var(--ui-blue)');});
document.querySelectorAll('[data-pd-clock]').forEach(clock=>{const base=Number(clock.dataset.serverTs||0);const offset=Number(clock.dataset.serverOffset||0);if(!base)return;const started=Date.now();const dateNode=clock.closest('.pd-live')?.querySelector('[data-pd-date]');const tick=()=>{const elapsed=Math.floor((Date.now()-started)/1000);const d=new Date((base+elapsed+offset)*1000);const pad=n=>String(n).padStart(2,'0');clock.textContent=pad(d.getUTCHours())+':'+pad(d.getUTCMinutes());if(dateNode)dateNode.textContent=pad(d.getUTCDate())+'.'+pad(d.getUTCMonth()+1)+'.'+d.getUTCFullYear();};tick();setInterval(tick,30000);});
// Collection offcanvas search.
document.querySelectorAll('.js-collection-search').forEach(input=>input.addEventListener('input',e=>{const q=e.target.value.trim().toLowerCase();const scope=e.target.closest('.offcanvas-body,.ios-sidebar,.modal-body')||document;scope.querySelectorAll('.js-collection-item').forEach(x=>x.classList.toggle('d-none',q&&!(x.dataset.search||'').includes(q)));}));
// Drag/drop and keyboard sorting. Dragging starts only from the handle and works in grids and on touch screens.
const initSortable=(box)=>{
    let dragged=null,startOrder='',moved=false,pointerId=null,activeHandle=null;
    const items=()=>[...box.children].filter(x=>x.matches?.('[data-sort-id]'));
    const order=()=>items().map(x=>x.dataset.sortId||'').join(',');
    const clearTargets=()=>items().forEach(x=>x.classList.remove('drop-target'));
    const persist=async()=>{
        const fd=new FormData();
        fd.set('_csrf',csrf);
        fd.set('_a',box.dataset.sortAction||'');
        if(box.dataset.sortGid)fd.set('gid',box.dataset.sortGid);
        items().forEach(x=>fd.append('ids[]',x.dataset.sortId||''));
        try{
            const r=await fetch('./',{method:'POST',body:fd,credentials:'same-origin'});
            const x=await r.json();
            if(!r.ok||!x.ok)throw new Error(x.error||'Sort failed');
            toast(x.message||'Saved');
        }catch(err){
            toast(err?.message||'Sort failed');
            setTimeout(()=>location.reload(),700);
        }
    };
    const begin=item=>{
        dragged=item;
        startOrder=order();
        moved=false;
        item.classList.add('is-dragging');
        box.classList.add('sort-drag-active');
    };
    const findPosition=(x,y)=>{
        const candidates=items().filter(item=>item!==dragged);
        let target=null,best=Infinity;
        candidates.forEach(item=>{
            const r=item.getBoundingClientRect();
            const cx=r.left+r.width/2,cy=r.top+r.height/2;
            const distance=Math.hypot(x-cx,y-cy);
            if(distance<best){best=distance;target=item;}
        });
        if(!target)return null;
        const r=target.getBoundingClientRect();
        const cx=r.left+r.width/2,cy=r.top+r.height/2;
        const sameRow=Math.abs(y-cy)<=Math.max(24,r.height*.32);
        return {target,before:sameRow?x<cx:y<cy};
    };
    const reorderAt=(x,y)=>{
        if(!dragged)return;
        const pos=findPosition(x,y);
        clearTargets();
        if(!pos)return;
        pos.target.classList.add('drop-target');
        const reference=pos.before?pos.target:pos.target.nextSibling;
        if(reference!==dragged&&dragged.nextSibling!==reference){
            box.insertBefore(dragged,reference);
            moved=true;
        }
        const edge=48;
        if(y<edge)window.scrollBy({top:-18,behavior:'auto'});
        else if(y>window.innerHeight-edge)window.scrollBy({top:18,behavior:'auto'});
    };
    const finish=()=>{
        if(!dragged)return;
        const changed=moved&&order()!==startOrder;
        dragged.classList.remove('is-dragging');
        clearTargets();
        box.classList.remove('sort-drag-active','sort-touch-active');
        dragged=null;
        pointerId=null;
        activeHandle=null;
        if(changed)persist();
    };
    box.addEventListener('dragover',e=>{
        if(!dragged)return;
        e.preventDefault();
        if(e.dataTransfer)e.dataTransfer.dropEffect='move';
        reorderAt(e.clientX,e.clientY);
    });
    box.addEventListener('drop',e=>{if(dragged)e.preventDefault();});
    items().forEach(item=>{
        item.draggable=false;
        const handle=item.querySelector('.drag-handle');
        if(!handle)return;
        handle.tabIndex=0;
        handle.draggable=true;
        handle.setAttribute('role','button');
        handle.addEventListener('dragstart',e=>{
            begin(item);
            if(e.dataTransfer){
                e.dataTransfer.effectAllowed='move';
                e.dataTransfer.setData('text/cms-sort-id',item.dataset.sortId||'');
                try{e.dataTransfer.setDragImage(item,24,24);}catch(_){}
            }
        });
        handle.addEventListener('dragend',finish);
        handle.addEventListener('pointerdown',e=>{
            if(e.pointerType==='mouse')return;
            e.preventDefault();
            pointerId=e.pointerId;
            activeHandle=handle;
            handle.setPointerCapture?.(pointerId);
            begin(item);
            box.classList.add('sort-touch-active');
        });
        handle.addEventListener('pointermove',e=>{
            if(pointerId!==e.pointerId||!dragged)return;
            e.preventDefault();
            reorderAt(e.clientX,e.clientY);
        });
        const endPointer=e=>{
            if(pointerId!==e.pointerId)return;
            try{activeHandle?.releasePointerCapture?.(pointerId);}catch(_){}
            finish();
        };
        handle.addEventListener('pointerup',endPointer);
        handle.addEventListener('pointercancel',endPointer);
        handle.addEventListener('keydown',e=>{
            if(!['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key))return;
            e.preventDefault();
            const previous=['ArrowUp','ArrowLeft'].includes(e.key);
            const sibling=previous?item.previousElementSibling:item.nextElementSibling;
            if(!sibling||!sibling.matches?.('[data-sort-id]'))return;
            previous?box.insertBefore(item,sibling):box.insertBefore(sibling,item);
            persist();
            handle.focus();
        });
    });
};
document.querySelectorAll('[data-sort-action]').forEach(initSortable);
// Drag an existing collection onto a content section: creates only a gc link.
document.querySelectorAll('[data-collection-drag-id]').forEach(x=>x.addEventListener('dragstart',e=>{e.dataTransfer?.setData('text/cms-collection-id',x.dataset.collectionDragId||'');e.dataTransfer.effectAllowed='copy';}));document.querySelectorAll('[data-group-drop-id]').forEach(x=>{x.addEventListener('dragover',e=>{if(Array.from(e.dataTransfer?.types||[]).includes('text/cms-collection-id')){e.preventDefault();x.classList.add('drop-target');e.dataTransfer.dropEffect='copy';}});x.addEventListener('dragleave',()=>x.classList.remove('drop-target'));x.addEventListener('drop',e=>{e.preventDefault();x.classList.remove('drop-target');const cid=e.dataTransfer?.getData('text/cms-collection-id');if(!cid)return;const fd=new FormData();fd.set('_csrf',csrf);fd.set('_a','add_collection_to_group');fd.set('gid',x.dataset.groupDropId||'');fd.set('cid',cid);fd.set('_return',location.href);fetch('./',{method:'POST',body:fd,credentials:'same-origin'}).then(()=>location.reload());});});
// Dirty forms, hotkeys, autosave and live JSON preview.
let dirty=false,submitting=false;document.querySelectorAll('.js-dirty-form').forEach(f=>{f.addEventListener('input',()=>dirty=true);f.addEventListener('change',()=>dirty=true);f.addEventListener('submit',()=>{submitting=true;dirty=false;});});window.addEventListener('beforeunload',e=>{if(dirty&&!submitting){e.preventDefault();e.returnValue=A.unsaved||'';}});
const editor=document.getElementById('entryEditorForm');
const buildPreview=()=>{if(!editor)return;const fd=new FormData(editor),flat={title:editor.elements.t?.value||'',slug:editor.elements.s?.value||'',status:editor.elements.st?.value||'',lang:editor.elements._cl?.value||'',data:{}},translations={};const cast=v=>/^\d+$/.test(String(v))?Number(v):v;const put=(obj,key,val,multi)=>{if(multi){obj[key]=obj[key]||[];obj[key].push(cast(val));}else obj[key]=cast(val);};for(const [name,val] of fd.entries()){if(val instanceof File)continue;let m=name.match(/^d\[([^\]]+)\](\[\])?$/);if(m){put(flat.data,m[1],val,!!m[2]);continue;}m=name.match(/^translations\[([^\]]+)\]\[d\]\[([^\]]+)\](\[\])?$/);if(m){translations[m[1]]=translations[m[1]]||{data:{}};put(translations[m[1]].data,m[2],val,!!m[3]);}}editor.querySelectorAll('input[type=checkbox]').forEach(i=>{let m=i.name.match(/^d\[([^\]]+)\]$/);if(m&&!i.checked)flat.data[m[1]]=false;m=i.name.match(/^translations\[([^\]]+)\]\[d\]\[([^\]]+)\]$/);if(m&&!i.checked){translations[m[1]]=translations[m[1]]||{data:{}};translations[m[1]].data[m[2]]=false;}});const out=Object.keys(translations).length?{title:flat.title,slug:flat.slug,status:flat.status,default_lang:flat.lang,data:flat.data,translations}:flat;const pre=document.getElementById('entryJsonPreview');if(pre)pre.textContent=JSON.stringify(out,null,2);};
document.querySelectorAll('.js-entry-language').forEach(item=>{const hidden=item.querySelector('.js-entry-translated'),badge=item.querySelector('.js-i18n-state');const mark=()=>{if(item.dataset.entryPrimary==='1')return;item.dataset.translated='1';if(hidden)hidden.value='1';if(badge){badge.classList.remove('text-bg-warning');badge.classList.add('text-bg-success');badge.textContent=badge.dataset.translatedLabel||badge.textContent;}};item.querySelectorAll('input:not([type=file]):not(.js-entry-translated),textarea,select').forEach(el=>{el.addEventListener('input',mark);el.addEventListener('change',mark);});item.querySelector('.js-entry-confirm-translation')?.addEventListener('click',mark);});
const entryTranslations=document.querySelector('.js-entry-translations');if(entryTranslations){const primary=entryTranslations.querySelector('.js-entry-language[data-entry-primary="1"]');if(primary){const simple=el=>el.matches('input:not([type=file]):not([type=hidden]):not([type=checkbox]):not([type=radio]),textarea,select');primary.querySelectorAll('input,textarea,select').forEach(source=>{if(!simple(source))return;const key=source.name.match(/\[d\]\[([^\]]+)\]/)?.[1];if(!key)return;entryTranslations.querySelectorAll('.js-entry-language:not([data-entry-primary="1"])').forEach(item=>{const target=item.querySelector(`[name$="[d][${CSS.escape(key)}]"]`);if(!target||!simple(target))return;target.dataset.entryAutofill=(target.value.trim()===''||target.value===source.value)?'1':'0';target.addEventListener('input',()=>target.dataset.entryAutofill='0');source.addEventListener('input',()=>{if(target.dataset.entryAutofill==='1'||target.value.trim()===''){target.value=source.value;target.dataset.entryAutofill='1';buildPreview();}});});});}}
document.querySelectorAll('.js-form-translations').forEach(group=>{const primary=group.querySelector('.js-entry-language[data-entry-primary="1"]');if(!primary)return;primary.querySelectorAll('[data-form-i18n-key]').forEach(source=>{const key=source.dataset.formI18nKey;if(!key)return;group.querySelectorAll('.js-entry-language:not([data-entry-primary="1"])').forEach(item=>{const target=item.querySelector(`[data-form-i18n-key="${CSS.escape(key)}"]`);if(!target)return;const translated=item.dataset.translated==='1';target.dataset.formAutofill=translated?'0':'1';target.addEventListener('input',()=>target.dataset.formAutofill='0');source.addEventListener('input',()=>{if(target.dataset.formAutofill==='1'||target.value.trim()===''){target.value=source.value;target.dataset.formAutofill='1';}});});});});
if(editor){editor.addEventListener('input',buildPreview);editor.addEventListener('change',buildPreview);buildPreview();let timer=null,running=false,pending=false,lastSaved='',revision=0;const state=document.getElementById('autosaveState');const snapshot=()=>{const fd=new FormData(editor);fd.set('_a','autosave_entry');for(const el of editor.querySelectorAll('input[type=file],input[name^="_file["],input[name^="_remove_file["]'))fd.delete(el.name);const pairs=[];for(const [k,v] of fd.entries())if(!(v instanceof File))pairs.push([k,String(v)]);pairs.sort((a,b)=>a[0].localeCompare(b[0])||a[1].localeCompare(b[1]));return {fd,key:JSON.stringify(pairs),revision};};const autosave=async()=>{if(running){pending=true;return;}const snap=snapshot();if(snap.key===lastSaved&&!pending)return;running=true;pending=false;if(state)state.textContent=A.autosave||'Autosave…';try{const r=await fetch('./',{method:'POST',body:snap.fd,credentials:'same-origin'});const x=await r.json();if(!r.ok||!x.ok)throw new Error(x.error||'Failed');if(snap.revision===revision)lastSaved=snap.key;if(state)state.textContent=(A.autosaved||'Saved')+' · '+(x.saved_at||'')+' · '+(A.files_not_autosaved||'Files are not included');}catch(_){if(state)state.textContent=A.autosave_failed||'Failed';}finally{running=false;if(pending){pending=false;clearTimeout(timer);timer=setTimeout(autosave,250);}}};const schedule=e=>{if(e?.target?.matches?.('input[type=file],input[name^="_remove_file["]'))return;revision++;clearTimeout(timer);if(running)pending=true;timer=setTimeout(autosave,1600);};editor.addEventListener('input',schedule);editor.addEventListener('change',schedule);
const resetListEditor=listEditor=>{const list=listEditor.querySelector('.js-list-items'),tpl=listEditor.querySelector('.js-list-item-template');if(!list||!tpl)return;list.innerHTML='';list.appendChild(tpl.content.cloneNode(true));const row=list.querySelector('.js-list-item');row?.querySelector('.js-list-up')?.setAttribute('disabled','');row?.querySelector('.js-list-down')?.setAttribute('disabled','');};
const clearEditor=()=>{clearTimeout(timer);pending=false;editor.querySelectorAll('input,textarea,select').forEach(el=>{if(el.type==='hidden'){if(el.classList.contains('js-entry-translated')){const item=el.closest('.js-entry-language');const primary=item?.dataset.entryPrimary==='1';el.value=primary?'1':'0';if(item){item.dataset.translated=primary?'1':'0';const badge=item.querySelector('.badge');if(badge){badge.classList.toggle('text-bg-success',primary);badge.classList.toggle('text-bg-warning',!primary);badge.textContent=primary?(A.translation_translated||'Translated'):(A.translation_autofilled||'Auto-filled');}}}else if(el.name?.startsWith('_file['))el.value='null';return;}if(el.name==='st'){el.value='draft';return;}if(el.type==='checkbox'||el.type==='radio'){el.checked=!!el.name?.startsWith('_remove_file[');return;}if(el.tagName==='SELECT'){el.selectedIndex=0;return;}el.value='';if(el.dataset.entryAutofill!==undefined)el.dataset.entryAutofill='1';});editor.querySelectorAll('.js-list-editor').forEach(resetListEditor);editor.querySelectorAll('.js-relation-picker').forEach(picker=>{const all=picker.querySelector('.js-relation-all');picker.querySelectorAll('.js-relation-item').forEach(x=>x.classList.remove('d-none'));const search=picker.querySelector('.js-relation-search');if(search)search.value='';if(all){all.checked=true;all.dispatchEvent(new Event('change',{bubbles:true}));}else picker.querySelectorAll('.js-relation-check').forEach(x=>x.checked=false);});editor.querySelectorAll('.js-html-preview').forEach(x=>x.innerHTML='');editor.querySelector('.js-restored-draft-alert')?.remove();revision++;buildPreview();lastSaved=snapshot().key;dirty=true;if(state)state.textContent=A.entry_form_cleared||'The form was cleared and the autosaved draft was deleted.';};
document.querySelectorAll('.js-entry-clear').forEach(btn=>btn.addEventListener('click',async()=>{if(!confirm(btn.dataset.confirm||A.clear_entry_form_q||'Clear the form?'))return;btn.disabled=true;try{const fd=new FormData();fd.set('_csrf',document.querySelector('meta[name="csrf-token"]')?.content||'');fd.set('_a','clear_entry_draft');fd.set('cid',editor.dataset.collectionId||'0');fd.set('id',editor.dataset.entryId||'0');const r=await fetch('./',{method:'POST',body:fd,credentials:'same-origin'});const x=await r.json();if(!r.ok||!x.ok)throw new Error(x.error||'Failed');clearEditor();bootstrap.Dropdown.getInstance(btn.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]'))?.hide();}catch(err){alert(err?.message||A.autosave_failed||'Failed');}finally{btn.disabled=false;}}));}
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){const f=document.querySelector('.js-entry-editor');if(f){e.preventDefault();submitting=true;dirty=false;f.requestSubmit();}}if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)){const q=document.querySelector('input[type=search]');if(q){e.preventDefault();q.focus();}}});
// Warn before disabling a language that contains data.
const i18nForm=document.querySelector('.js-i18n-settings');if(i18nForm){let confirmed=false;const toggle=i18nForm.querySelector('.js-i18n-toggle');const checks=[...i18nForm.querySelectorAll('.js-content-lang')];const defaultBlock=i18nForm.querySelector('.js-i18n-default-block');const languagesBlock=i18nForm.querySelector('.js-i18n-languages-block');const modeHint=i18nForm.querySelector('.js-i18n-mode-hint');checks.forEach(x=>x.dataset.original=x.checked?'1':'0');const syncLocks=()=>{i18nForm.querySelectorAll('[data-lang-lock-hidden]').forEach(x=>x.remove());checks.forEach(x=>{x.disabled=false;x.closest('label')?.classList.remove('opacity-75');x.removeAttribute('title');});if(!toggle?.checked)return;const enabled=checks.filter(x=>x.checked);if(enabled.length===1){const x=enabled[0];x.disabled=true;x.closest('label')?.classList.add('opacity-75');x.title=i18nForm.dataset.lastLanguageMessage||'At least one content language must remain active.';const hidden=document.createElement('input');hidden.type='hidden';hidden.name='content_langs[]';hidden.value=x.value;hidden.dataset.langLockHidden='1';i18nForm.appendChild(hidden);}};const syncMode=()=>{const enabled=!!toggle?.checked;defaultBlock?.classList.toggle('d-none',enabled);languagesBlock?.classList.toggle('d-none',!enabled);if(modeHint)modeHint.textContent=enabled?(i18nForm.dataset.modeOn||''):(i18nForm.dataset.modeOff||'');syncLocks();};checks.forEach(x=>x.addEventListener('change',syncLocks));toggle?.addEventListener('change',syncMode);syncMode();i18nForm.addEventListener('submit',e=>{if(confirmed||!toggle?.checked)return;const risky=checks.filter(x=>x.dataset.hasData==='1'&&x.dataset.original==='1'&&!x.checked);if(!risky.length)return;e.preventDefault();const list=document.getElementById('languageDisableList');if(list)list.textContent=risky.map(x=>x.dataset.langName||x.value).join(', ');bootstrap.Modal.getOrCreateInstance(document.getElementById('languageDisableModal')).show();});document.getElementById('languageDisableConfirm')?.addEventListener('click',()=>{confirmed=true;bootstrap.Modal.getInstance(document.getElementById('languageDisableModal'))?.hide();i18nForm.requestSubmit();});}
document.querySelectorAll('.js-resource-i18n').forEach(root=>{['n','d'].forEach(key=>{const fields=[...root.querySelectorAll('[data-i18n-field="'+key+'"]')];const master=fields.find(x=>x.dataset.i18nMaster==='1');if(!master)return;fields.forEach(field=>{if(field===master)return;field.dataset.i18nAutofill=(field.value.trim()===''||field.value===master.value)?'1':'0';field.addEventListener('input',()=>field.dataset.i18nAutofill='0');});master.addEventListener('input',()=>fields.forEach(field=>{if(field!==master&&(field.dataset.i18nAutofill==='1'||field.value.trim()==='')){field.value=master.value;field.dataset.i18nAutofill='1';field.dispatchEvent(new Event('change',{bubbles:true}));}}));});});
// Public/private resource access. API keys are created only on the server.
const syncAccessControl=box=>{const mode=box.querySelector('.js-access-mode'),privateBox=box.querySelector('.js-private-access'),hint=box.querySelector('.js-access-hint');if(!mode||!privateBox)return;const isPrivate=mode.value==='private';privateBox.classList.toggle('d-none',!isPrivate);if(hint)hint.textContent=isPrivate?(hint.dataset.private||''):(hint.dataset.public||'');};
document.querySelectorAll('.js-access-control').forEach(box=>{const mode=box.querySelector('.js-access-mode');mode?.addEventListener('change',()=>{syncAccessControl(box);dirty=true;});syncAccessControl(box);});
// Global administrators automatically have access to every project.
document.querySelectorAll('.js-global-user-role').forEach(select=>{const modal=select.closest('form'),hint=modal?.querySelector('.js-admin-access-hint'),access=modal?.querySelector('.js-project-access');const sync=()=>{const admin=select.value==='admin';hint?.classList.toggle('d-none',!admin);access?.querySelectorAll('select[name^="project_roles["]').forEach(x=>x.disabled=admin);};select.addEventListener('change',sync);sync();});
// Collection preset preview.
const presetLabels={text:'Text',textarea:'Textarea',html:'HTML',number:'Number',date:'Date',bool:'Boolean',url:'URL',image:'Image',file:'File'};document.querySelectorAll('.js-collection-preset').forEach(sel=>{const box=sel.closest('form')?.querySelector('.js-preset-preview .small');const render=()=>{const rows=(A.preset||{})[sel.value]||[];if(box)box.innerHTML=rows.length?rows.map(x=>'<div class="d-flex justify-content-between py-1"><span>'+x[0]+'</span><code>'+(presetLabels[x[2]]||x[2])+'</code></div>').join(''):'<span class="text-muted">Blank schema</span>';};sel.addEventListener('change',render);render();});
// API explorer.
const apiForm=document.getElementById('apiExplorerForm');if(apiForm){const copyBtn=document.getElementById('apiExplorerCopy');const build=()=>{const ep=document.getElementById('apiExplorerEndpoint').value;const u=new URL(location.href);u.search='';u.searchParams.set('api',ep);const project=apiForm.dataset.project||'';if(project)u.searchParams.set('project',project);const c=document.getElementById('apiExplorerCollection').value,g=document.getElementById('apiExplorerGroup').value,s=document.getElementById('apiExplorerSlug').value,l=document.getElementById('apiExplorerLang').value,p=document.getElementById('apiExplorerPopulate').value;if(['entries','entry','schema','fields'].includes(ep)&&c)u.searchParams.set('c',c);if(ep==='group'&&g)u.searchParams.set('g',g);if(ep==='entry'&&s)u.searchParams.set('s',s);if(l)u.searchParams.set('lang',l);u.searchParams.set('populate',p);if(copyBtn)copyBtn.dataset.copy=u.href;return u;};apiForm.addEventListener('input',build);apiForm.addEventListener('change',build);apiForm.addEventListener('submit',e=>{e.preventDefault();const u=build();const pre=document.getElementById('apiExplorerResponse');pre.textContent='Loading…';fetch(u,{credentials:'same-origin',headers:(()=>{const k=document.getElementById('apiExplorerKey')?.value.trim()||'';return k?{'X-API-Key':k}:{};})()}).then(async r=>({status:r.status,data:await r.json()})).then(x=>pre.textContent=JSON.stringify(x,null,2)).catch(x=>pre.textContent=String(x));});build();}
})();
CMSJS;
}
function serve_internal_asset(string $asset):never{
    $asset=basename($asset);
    if($asset==='app.css'){$body=ui_css();$type='text/css; charset=utf-8';}
    elseif($asset==='app.js'){$body=client_js();$type='application/javascript; charset=utf-8';}
    else{http_response_code(404);exit;}
    $etag='"'.hash('sha256',APP_CACHE_VERSION.'|'.$body).'"';
    header('Content-Type: '.$type);header('Cache-Control: public, max-age=31536000, immutable');header('ETag: '.$etag);header('Vary: Accept-Encoding');
    if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}echo $body;exit;
}

function ui_css(){static $x=null;return $x??=$x='
[data-bs-theme=light]{--ui-bg:#f5f5f7;--ui-panel:#ffffff;--ui-input:#ffffff;--ui-text:#1d1d1f;--ui-muted:#7d7d85;--ui-line:#e6e6eb;--ui-soft:#f2f2f7;--ui-blue:#007aff;--ui-red:#ff3b30;--ui-green:#34c759;--ui-on:#ffffff;--ui-red-soft:rgba(255,59,48,.12);--ui-green-soft:rgba(52,199,89,.14);--ui-success-text:#168a3a;--ui-radius:22px;--bs-body-bg:var(--ui-bg);--bs-body-color:var(--ui-text);--bs-emphasis-color:var(--ui-text);--bs-secondary-color:var(--ui-muted);--bs-tertiary-bg:var(--ui-soft);--bs-border-color:var(--ui-line);--bs-heading-color:var(--ui-text);--bs-link-color:var(--ui-blue);--bs-link-hover-color:var(--ui-blue);--bs-modal-bg:var(--ui-panel);--bs-modal-color:var(--ui-text);--bs-card-bg:var(--ui-panel)}
[data-bs-theme=dark]{--ui-bg:#07080b;--ui-panel:#17181d;--ui-input:#111217;--ui-text:#f5f5f7;--ui-muted:#a1a1aa;--ui-line:#30313a;--ui-soft:#24252c;--ui-blue:#0a84ff;--ui-red:#ff453a;--ui-green:#30d158;--ui-on:#ffffff;--ui-red-soft:rgba(255,69,58,.16);--ui-green-soft:rgba(48,209,88,.16);--ui-success-text:#32d74b;--ui-radius:22px;color-scheme:dark;--bs-body-bg:var(--ui-bg);--bs-body-color:var(--ui-text);--bs-emphasis-color:var(--ui-text);--bs-secondary-color:var(--ui-muted);--bs-tertiary-bg:var(--ui-soft);--bs-border-color:var(--ui-line);--bs-heading-color:var(--ui-text);--bs-link-color:var(--ui-blue);--bs-link-hover-color:var(--ui-blue);--bs-modal-bg:var(--ui-panel);--bs-modal-color:var(--ui-text);--bs-card-bg:var(--ui-panel)}
body.premium-bg{min-height:100vh;background:var(--ui-bg);color:var(--ui-text);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",system-ui,sans-serif}a{text-decoration:none}.premium-brand{letter-spacing:-.035em}.premium-topbar{background:color-mix(in srgb,var(--ui-bg) 88%,transparent)!important;backdrop-filter:saturate(180%) blur(18px);border-bottom:1px solid var(--ui-line);color:var(--ui-text)}.premium-topbar>.container-fluid{position:relative}.premium-topbar .navbar-collapse{position:static}.premium-nav{position:static;gap:.85rem!important;margin-left:1rem}.premium-nav .nav-link{padding:.65rem .15rem!important;font-weight:680;letter-spacing:-.015em;white-space:nowrap}.premium-nav .nav-link.btn{line-height:1.5}.premium-actions{margin-left:auto}.project-name{max-width:180px}.app-frame{min-width:0}.app-content{min-width:0}.btn-primary{box-shadow:0 .35rem 1rem color-mix(in srgb,var(--ui-blue) 22%,transparent)}.btn-secondary{background:var(--ui-soft)!important;color:var(--ui-text)!important}.ios-actions .btn{min-height:2.55rem}.ios-actions .btn:not(.btn-primary){font-weight:620}
.ios-shell{max-width:1680px;margin:0 auto}.ios-sidebar{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:var(--ui-radius);overflow:hidden}.ios-sidebar .list-group{padding:.5rem}.ios-sidebar .list-group-item{border:0!important;border-radius:16px!important;margin:.15rem 0;background:transparent;color:var(--ui-text)}.ios-sidebar .list-group-item:hover{background:var(--ui-soft)}.ios-sidebar .list-group-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.ios-sidebar .list-group-item.active small{color:color-mix(in srgb,var(--ui-on) 72%,transparent)!important}
.ios-head{display:flex;gap:1rem;align-items:flex-end;justify-content:space-between;margin-bottom:1rem}.ios-title{font-size:1.85rem;line-height:1.05;font-weight:760;letter-spacing:-.04em;margin:0;color:var(--ui-text)}.ios-sub{color:var(--ui-muted);font-size:.9rem;margin-top:.25rem}.ios-actions{display:flex;gap:.55rem;flex-wrap:wrap;justify-content:flex-end}.ios-surface{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:var(--ui-radius);overflow:hidden}.ios-toolbar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}.ios-kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--ui-muted);margin-bottom:.25rem}.collection-workspace-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1.25rem;margin-bottom:1.25rem;padding:.25rem 0}.collection-workspace-main{display:flex;align-items:center;gap:1rem;min-width:0}.collection-workspace-back{width:3rem;height:3rem;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}.collection-workspace-title{min-width:0}.collection-workspace-title h1{font-size:2rem;line-height:1.05;font-weight:780;letter-spacing:-.04em;margin:0;overflow-wrap:anywhere}.collection-workspace-meta{display:flex;align-items:center;flex-wrap:wrap;gap:.45rem;margin-top:.55rem}.collection-workspace-actions{display:flex;align-items:center;justify-content:flex-end;gap:.6rem;flex-wrap:wrap}.collection-workspace-actions .btn{white-space:nowrap}.collection-workspace-actions .dropdown-menu{min-width:260px}.collection-workspace-actions .dropdown-item{padding:.7rem .8rem}.collection-empty-state{min-height:320px;display:flex;align-items:center;justify-content:center;padding:3rem 1.5rem;text-align:center}.collection-empty-state-inner{max-width:520px}.collection-empty-state-icon{width:4.5rem;height:4.5rem;border-radius:1.5rem;display:inline-flex;align-items:center;justify-content:center;background:var(--ui-soft);color:var(--ui-muted);margin-bottom:1.1rem}.collection-empty-state-icon .bi{font-size:2rem}.collection-empty-state h2{font-size:1.65rem;letter-spacing:-.025em;margin-bottom:.45rem}.collection-empty-state p{margin-bottom:1.25rem}.nested-collections-embedded{border-top:1px solid var(--ui-line);margin-top:1.5rem;padding-top:1.5rem}.nested-collection-card,.nested-collection-empty{display:block;background:var(--ui-soft);border:1px solid var(--ui-line);border-radius:18px;transition:border-color .15s ease,background-color .15s ease,box-shadow .15s ease}.nested-collection-card:hover{border-color:color-mix(in srgb,var(--ui-blue) 28%,var(--ui-line));background:color-mix(in srgb,var(--ui-blue) 6%,var(--ui-soft));box-shadow:0 .35rem 1rem color-mix(in srgb,var(--ui-text) 6%,transparent)}@media(max-width:1199.98px){.collection-workspace-head{grid-template-columns:1fr;align-items:start}.collection-workspace-actions{justify-content:flex-start;padding-left:4rem}}@media(max-width:767.98px){.collection-workspace-head{gap:1rem}.collection-workspace-main{align-items:flex-start}.collection-workspace-title h1{font-size:1.75rem}.collection-workspace-actions{padding-left:0;display:grid;grid-template-columns:1fr auto;width:100%}.collection-workspace-actions>.btn-primary{grid-column:1/-1;width:100%}.collection-workspace-actions>.btn-light:not(.dropdown-toggle){width:100%}.collection-workspace-actions>.dropdown{justify-self:end}.collection-empty-state{min-height:280px;padding:2.5rem 1rem}}.group-workspace-head{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:start;gap:1.25rem;margin-bottom:1.25rem}.group-workspace-main{display:flex;align-items:flex-start;gap:1rem;min-width:0}.group-workspace-title{min-width:0}.group-workspace-title h1{font-size:2rem;line-height:1.05;font-weight:780;letter-spacing:-.04em;margin:0;overflow-wrap:anywhere}.group-workspace-meta{display:flex;align-items:center;flex-wrap:wrap;gap:.45rem;margin-top:.55rem}.group-workspace-actions{display:flex;align-items:center;justify-content:flex-end;gap:.6rem;flex-wrap:wrap}.group-workspace-actions .dropdown-menu{min-width:260px}.group-workspace-actions .btn{white-space:nowrap}.group-collections-card{padding:1.1rem 1.2rem}.group-collections-card-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.section-collection-row{border:1px solid var(--ui-line);border-radius:20px;background:var(--ui-panel);padding:.95rem 1rem;transition:border-color .15s ease,background-color .15s ease,box-shadow .15s ease}.section-collection-row:hover{border-color:color-mix(in srgb,var(--ui-blue) 28%,var(--ui-line));background:color-mix(in srgb,var(--ui-soft) 66%,var(--ui-panel));box-shadow:0 .35rem 1rem color-mix(in srgb,var(--ui-text) 6%,transparent)}.section-collection-row-main{display:flex;align-items:flex-start;gap:.9rem;min-width:0}.section-collection-row-body{min-width:0}.section-collection-row-title{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.2rem}.section-collection-row-title a{font-size:1.15rem;font-weight:760;text-decoration:none}.section-collection-row-meta{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;color:var(--ui-muted)}.section-collection-row-actions{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;justify-content:flex-end}.section-collection-row-actions .btn-primary{white-space:nowrap}@media(max-width:1199.98px){.group-workspace-head{grid-template-columns:1fr}.group-workspace-actions{justify-content:flex-start;padding-left:4rem}}@media(max-width:767.98px){.group-workspace-main{align-items:flex-start}.group-workspace-title h1{font-size:1.75rem}.group-workspace-actions{padding-left:0;display:grid;grid-template-columns:1fr auto;width:100%}.group-workspace-actions>.btn-primary{grid-column:1/-1;width:100%}.group-workspace-actions>.dropdown{justify-self:end}.group-collections-card{padding:1rem}.group-collections-card-head{align-items:flex-start;flex-direction:column}.section-collection-row{padding:.9rem}.section-collection-row-actions{width:100%;justify-content:space-between}.section-collection-row-actions .btn-primary{flex:1 1 auto}}
.btn{border-radius:13px!important;font-weight:650;border-width:0!important}.nav-pills .nav-link,.btn.btn-pill{border-radius:999px!important}.btn-icon{border-radius:50%!important}
.btn-dark,.btn-primary{background:var(--ui-blue)!important;color:var(--ui-on)!important}.btn-outline-dark,.btn-light,.btn-outline-light,.btn-outline-secondary{background:var(--ui-soft)!important;color:var(--ui-text)!important;border:0!important}.btn-outline-dark:hover,.btn-light:hover,.btn-outline-light:hover,.btn-outline-secondary:hover{background:var(--ui-line)!important;color:var(--ui-text)!important}.btn-danger,.btn-outline-danger{background:var(--ui-red-soft)!important;color:var(--ui-red)!important;border:0!important}.btn-danger:hover,.btn-outline-danger:hover{background:var(--ui-red)!important;color:var(--ui-on)!important}.btn-icon{width:2.35rem;height:2.35rem;min-width:2.35rem;min-height:2.35rem;aspect-ratio:1/1;flex:0 0 2.35rem;display:inline-flex;align-items:center;justify-content:center;padding:0!important}
.form-control,.form-select{border:1px solid var(--ui-line)!important;border-radius:16px!important;background:var(--ui-input)!important;color:var(--ui-text)!important;padding:.72rem .9rem}.form-control::placeholder{color:var(--ui-muted)!important}.form-select-sm{padding:.45rem 2.25rem .45rem .85rem}.form-control:focus,.form-select:focus{border-color:var(--ui-blue)!important;box-shadow:0 0 0 .22rem color-mix(in srgb,var(--ui-blue) 18%,transparent)!important}.form-label{color:var(--ui-muted);font-size:.82rem;font-weight:650;margin-bottom:.35rem}.form-check-input{background-color:var(--ui-input);border-color:var(--ui-line)}.form-check-input:checked{background-color:var(--ui-blue);border-color:var(--ui-blue)}
.resource-language-accordion{display:grid;gap:.65rem}.resource-language-accordion .accordion-item{border:1px solid var(--ui-line)!important;border-radius:1rem!important;overflow:hidden;background:var(--ui-panel)}.resource-language-accordion .accordion-button{background:var(--ui-soft);color:var(--ui-text);box-shadow:none!important;font-weight:650}.resource-language-accordion .accordion-button:not(.collapsed){background:color-mix(in srgb,var(--ui-blue) 7%,var(--ui-panel));color:var(--ui-text)}.resource-language-accordion .accordion-button:focus{box-shadow:0 0 0 .18rem color-mix(in srgb,var(--ui-blue) 12%,transparent)!important}.resource-language-accordion .accordion-body{background:var(--ui-panel)}.content-language-card{cursor:pointer}.content-language-option{background:var(--ui-bg);border-color:var(--ui-line)!important;transition:border-color .18s ease,background-color .18s ease,box-shadow .18s ease,transform .18s ease}.content-language-card:hover .content-language-option{border-color:color-mix(in srgb,var(--ui-blue) 42%,var(--ui-line))!important;transform:translateY(-1px)}.content-language-check{width:1.75rem;height:1.75rem;border:1px solid var(--ui-line);border-radius:50%;background:var(--ui-input);color:transparent;display:inline-flex;align-items:center;justify-content:center;transition:all .18s ease}.content-language-check .bi{font-size:1rem;line-height:1}.js-content-lang:checked+.content-language-option{border-color:var(--ui-blue)!important;background:color-mix(in srgb,var(--ui-blue) 7%,var(--ui-bg));box-shadow:0 0 0 .18rem color-mix(in srgb,var(--ui-blue) 10%,transparent)}.js-content-lang:checked+.content-language-option .content-language-check{background:var(--ui-blue);border-color:var(--ui-blue);color:#fff}.js-content-lang:focus-visible+.content-language-option{outline:3px solid color-mix(in srgb,var(--ui-blue) 25%,transparent);outline-offset:2px}.js-content-lang:disabled+.content-language-option{cursor:not-allowed}.content-language-code{letter-spacing:.03em}.form-control[type=file]::file-selector-button{background:var(--ui-soft);color:var(--ui-text);border:0;border-right:1px solid var(--ui-line);border-radius:12px;margin:-.72rem .9rem -.72rem -.9rem;padding:.72rem .9rem}.text-muted{color:var(--ui-muted)!important}.link-dark{color:var(--ui-text)!important}.text-white{color:var(--ui-bg)!important}.bg-dark{background:var(--ui-text)!important}.bg-light,.bg-body-tertiary{background:var(--ui-soft)!important;color:var(--ui-text)!important}.backup-action-card{background:linear-gradient(180deg,color-mix(in srgb,var(--ui-soft) 86%,var(--ui-bg) 14%),var(--ui-soft));border:1px solid color-mix(in srgb,var(--ui-line) 82%,var(--ui-blue) 18%)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.28)}.backup-action-card+.backup-action-card{margin-top:.25rem}.backup-action-card .backup-action-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.1rem}.backup-action-card .backup-action-icon{width:3.9rem;height:3.9rem;border-radius:1.4rem;display:inline-flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--ui-bg) 76%,var(--ui-soft) 24%);border:1px solid color-mix(in srgb,var(--ui-line) 80%,transparent);color:var(--ui-muted);flex-shrink:0}.backup-action-card .backup-action-icon .bi{font-size:1.25rem}.backup-action-card .backup-action-kicker{font-size:.76rem;letter-spacing:.08em;text-transform:uppercase;font-weight:760;color:var(--ui-muted);margin-top:.25rem}.backup-action-card .btn{border-radius:1rem}.backup-action-card .form-control[type=file]{background:var(--ui-panel)!important;border:1px solid color-mix(in srgb,var(--ui-line) 72%,var(--ui-text) 28%)!important;box-shadow:0 1px 2px rgba(0,0,0,.04);color:var(--ui-text)}.backup-action-card .form-control[type=file]::file-selector-button{background:color-mix(in srgb,var(--ui-blue) 9%,var(--ui-panel))!important;color:var(--ui-blue)!important;border:0!important;border-right:1px solid color-mix(in srgb,var(--ui-blue) 18%,var(--ui-line))!important;font-weight:700}.backup-action-card .form-control[type=file]:hover::file-selector-button{background:color-mix(in srgb,var(--ui-blue) 14%,var(--ui-panel))!important}.backup-action-card .form-control[type=file]:focus{border-color:color-mix(in srgb,var(--ui-blue) 55%,var(--ui-line))!important;box-shadow:0 0 0 .18rem color-mix(in srgb,var(--ui-blue) 12%,transparent)}.backup-action-card.is-download{border-left:4px solid color-mix(in srgb,var(--ui-blue) 58%,var(--ui-line))!important}.backup-action-card.is-restore{border-left:4px solid color-mix(in srgb,var(--ui-text) 18%,var(--ui-line))!important}@media(max-width:575.98px){.backup-action-card .backup-action-head{align-items:center}.backup-action-card .backup-action-icon{width:3.2rem;height:3.2rem;border-radius:1rem}}
.table{--bs-table-bg:var(--ui-panel);--bs-table-color:var(--ui-text);--bs-table-border-color:var(--ui-line);--bs-table-hover-color:var(--ui-text);--bs-table-hover-bg:var(--ui-soft);color:var(--ui-text)}.table thead th{background:var(--ui-panel);color:var(--ui-muted);font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760}.table td,.table th{border-color:var(--ui-line)!important;padding:1rem}.table tbody tr:last-child td{border-bottom:0!important}.badge{font-weight:650;border-radius:999px!important}.badge.text-bg-success{background:var(--ui-green-soft)!important;color:var(--ui-success-text)!important}.badge.text-bg-secondary,.badge.text-bg-light{background:var(--ui-soft)!important;color:var(--ui-muted)!important;border:0!important}.badge.text-bg-dark{background:var(--ui-text)!important;color:var(--ui-bg)!important}.badge.text-bg-warning{background:var(--ui-red-soft)!important;color:var(--ui-red)!important}.modal-content{background:var(--ui-panel)!important;color:var(--ui-text)!important;border:1px solid var(--ui-line)!important;border-radius:24px!important;overflow:hidden}.modal-header,.modal-body,.modal-footer{background:var(--ui-panel)!important;color:var(--ui-text)!important}.modal-header,.modal-footer{border-color:var(--ui-line)!important}.btn-close{filter:none}.alert{border-radius:18px!important;border:0!important}code{color:var(--ui-blue)}
.premium-panel,.card{background:var(--ui-panel)!important;color:var(--ui-text)!important;border:1px solid var(--ui-line)!important;border-radius:var(--ui-radius)!important;box-shadow:none!important}.card-header,.card-body,.card-footer{background:var(--ui-panel)!important;color:var(--ui-text)!important;border-color:var(--ui-line)!important}.premium-side-card{background:var(--ui-panel)!important;color:var(--ui-text)!important}.premium-side-card .text-white-50{color:var(--ui-muted)!important}.premium-side-card .card-header,.premium-side-card .card-body{border-color:var(--ui-line)!important}.premium-side-card .list-group{padding:.5rem}.premium-side-card .list-group-item{background:transparent!important;color:var(--ui-text)!important;border:0!important;border-radius:16px!important;margin-bottom:.2rem}.premium-side-card .list-group-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.premium-side-card .list-group-item.active small{color:color-mix(in srgb,var(--ui-on) 72%,transparent)!important}.navbar,.navbar-brand,.nav-link{color:var(--ui-text)!important}.nav-link.active{color:var(--ui-blue)!important}.dropdown-menu{background:var(--ui-panel)!important;color:var(--ui-text)!important;border-color:var(--ui-line)!important;border-radius:18px!important}.dropdown-item{color:var(--ui-text)!important}.dropdown-item:hover,.dropdown-item:focus{background:var(--ui-soft)!important;color:var(--ui-text)!important}.dropdown-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}
.ios-toggle{position:relative;display:inline-flex;width:3.35rem;height:2rem;flex:0 0 auto}.ios-toggle input{position:absolute;opacity:0;pointer-events:none}.ios-toggle span{position:absolute;inset:0;cursor:pointer;border-radius:999px;background:var(--ui-line);transition:.18s}.ios-toggle span:before{content:"";position:absolute;width:1.62rem;height:1.62rem;left:.19rem;top:.19rem;border-radius:50%;background:#fff;box-shadow:0 .15rem .45rem rgba(0,0,0,.22);transition:.18s}.ios-toggle input:checked+span{background:var(--ui-blue)}.ios-toggle input:checked+span:before{transform:translateX(1.35rem)}

:focus-visible{outline:3px solid color-mix(in srgb,var(--ui-blue) 38%,transparent)!important;outline-offset:2px}.offcanvas{--bs-offcanvas-width:min(92vw,430px);background:var(--ui-panel);color:var(--ui-text);border-color:var(--ui-line)!important}.collection-item{border:1px solid transparent;border-radius:18px;padding:.75rem;transition:.15s}.collection-item:hover,.collection-item.active{background:var(--ui-soft);border-color:var(--ui-line)}.collection-item .collection-open{min-width:0}.drag-handle{cursor:grab;touch-action:none;user-select:none;-webkit-user-select:none}.drag-handle:active{cursor:grabbing}.is-dragging{opacity:.5}.drop-target{box-shadow:inset 0 0 0 2px var(--ui-blue)}[data-sort-id].is-dragging>.content-section-card,[data-sort-id].is-dragging>.section-collection-row{opacity:.58;transform:scale(.985)}[data-sort-id].drop-target>.content-section-card,[data-sort-id].drop-target>.section-collection-row{box-shadow:0 0 0 2px var(--ui-blue),0 .65rem 1.5rem color-mix(in srgb,var(--ui-blue) 14%,transparent)}.sort-touch-active{touch-action:none}.sort-touch-active *{cursor:grabbing!important}.entry-editor-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,420px);gap:1rem;align-items:start}.entry-preview{position:sticky;top:5.5rem}.json-preview{min-height:280px;max-height:65vh;overflow:auto;background:var(--ui-input);border:1px solid var(--ui-line);border-radius:18px;padding:1rem;font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre-wrap;word-break:break-word}.autosave-state{font-size:.8rem;color:var(--ui-muted)}.dashboard-stat{padding:1.15rem;border:1px solid var(--ui-line);border-radius:22px;background:var(--ui-panel);height:100%}.dashboard-stat-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.85rem}.dashboard-stat small{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--ui-muted);font-weight:760;margin-bottom:.45rem}.dashboard-stat strong{font-size:2rem;letter-spacing:-.05em;line-height:1}.dashboard-stat-note{margin-top:.65rem;font-size:.84rem;color:var(--ui-muted)}.dashboard-hero{padding:1.35rem;border:1px solid var(--ui-line);border-radius:28px;background:linear-gradient(135deg,color-mix(in srgb,var(--ui-blue) 11%,var(--ui-panel)),var(--ui-panel))}.dashboard-kicker{font-size:.78rem;text-transform:uppercase;letter-spacing:.09em;color:var(--ui-muted);font-weight:800;margin-bottom:.55rem}.dashboard-hero-main{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.dashboard-hero-title h2{font-size:clamp(1.85rem,4vw,2.55rem);letter-spacing:-.06em;line-height:1.02;margin:0 0 .4rem}.dashboard-hero-title p{margin:0;color:var(--ui-muted);max-width:58rem}.dashboard-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin-top:1rem}.dashboard-meta-item{padding:.9rem 1rem;border-radius:18px;background:var(--ui-soft);border:1px solid var(--ui-line)}.dashboard-meta-label{display:block;font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ui-muted);font-weight:760;margin-bottom:.35rem}.dashboard-meta-value{display:block;font-weight:700;word-break:break-word}.dashboard-time-card{padding:1.3rem;border:1px solid var(--ui-line);border-radius:28px;background:var(--ui-panel);height:100%}.dashboard-clock{font-size:2.35rem;line-height:1;font-weight:800;letter-spacing:-.06em;margin:.2rem 0 .45rem}.dashboard-date{color:var(--ui-muted);font-size:1rem}.dashboard-quick-actions{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1rem}.dashboard-quick-actions .btn{min-height:44px}.dashboard-card{padding:1.25rem;border:1px solid var(--ui-line);border-radius:24px;background:var(--ui-panel);height:100%}.dashboard-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1rem}.dashboard-card-header h2{font-size:1.14rem;letter-spacing:-.03em;margin:0}.dashboard-card-sub{font-size:.92rem;color:var(--ui-muted);margin-top:.25rem}.dashboard-progress-list,.dashboard-bars,.dashboard-list,.dashboard-activity{display:grid;gap:.85rem}.dashboard-progress-row,.dashboard-bar-item{display:grid;gap:.4rem}.dashboard-progress-meta,.dashboard-bar-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;font-size:.93rem}.dashboard-progress-track,.dashboard-bar-track{height:.72rem;border-radius:999px;background:var(--ui-soft);overflow:hidden}.dashboard-progress-fill,.dashboard-bar-fill{height:100%;width:var(--dash-fill,0%);border-radius:inherit;background:var(--dash-color,var(--ui-blue))}.dashboard-badges{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:1rem}.dashboard-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.42rem .68rem;border-radius:999px;background:var(--ui-soft);border:1px solid var(--ui-line);font-size:.85rem;font-weight:650}.dashboard-insight-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.dashboard-insight{padding:.95rem 1rem;border-radius:18px;background:var(--ui-soft);border:1px solid var(--ui-line)}.dashboard-insight strong{display:block;font-size:1.25rem;letter-spacing:-.04em;line-height:1.05}.dashboard-insight span{display:block;font-size:.84rem;color:var(--ui-muted);margin-top:.3rem}.dashboard-list-item{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;padding:.9rem 1rem;border:1px solid var(--ui-line);border-radius:18px;background:var(--ui-soft);color:var(--ui-text);text-decoration:none}.dashboard-list-item:hover{border-color:color-mix(in srgb,var(--ui-blue) 30%,var(--ui-line));box-shadow:0 .4rem 1rem color-mix(in srgb,var(--ui-text) 5%,transparent)}.dashboard-list-meta{display:block;font-size:.88rem;color:var(--ui-muted);margin-top:.22rem}.dashboard-list-value{font-weight:700;white-space:nowrap}.dashboard-activity-item{display:grid;grid-template-columns:auto 1fr;gap:.8rem;align-items:start}.dashboard-activity-dot{width:.8rem;height:.8rem;border-radius:50%;background:var(--ui-blue);margin-top:.35rem;box-shadow:0 0 0 .32rem color-mix(in srgb,var(--ui-blue) 14%,transparent)}.dashboard-activity-title{font-weight:700;line-height:1.35}.dashboard-activity-meta{font-size:.86rem;color:var(--ui-muted);margin-top:.18rem}.dashboard-empty{padding:1rem 1.05rem;border:1px dashed var(--ui-line);border-radius:18px;color:var(--ui-muted);background:color-mix(in srgb,var(--ui-soft) 65%,transparent)}.dashboard-project-card{padding:1.35rem;border:1px solid var(--ui-line);border-radius:28px;background:linear-gradient(135deg,color-mix(in srgb,var(--ui-blue) 9%,var(--ui-panel)),var(--ui-panel));height:100%}.dashboard-project-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.dashboard-project-name{font-size:clamp(1.85rem,4vw,2.6rem);letter-spacing:-.06em;line-height:1.02;font-weight:800;margin:0 0 .4rem}.dashboard-project-subtitle{margin:0;color:var(--ui-muted);max-width:56rem}.dashboard-project-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}.dashboard-project-actions{display:flex;flex-wrap:wrap;gap:.65rem}.dashboard-project-actions .btn{min-height:44px}.dashboard-pulse-card{padding:1.25rem;border:1px solid var(--ui-line);border-radius:28px;background:var(--ui-panel);height:100%}.dashboard-pulse-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.dashboard-pulse-time{font-size:2.5rem;line-height:1;font-weight:800;letter-spacing:-.06em;margin:.15rem 0 .45rem}.dashboard-pulse-date{color:var(--ui-muted);font-size:1rem}.dashboard-pulse-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;margin-top:1rem}.dashboard-compact-metric{padding:.95rem 1rem;border-radius:18px;background:var(--ui-soft);border:1px solid var(--ui-line)}.dashboard-compact-metric strong{display:block;font-size:1.45rem;line-height:1.05;letter-spacing:-.04em}.dashboard-compact-metric span{display:block;margin-top:.28rem;color:var(--ui-muted);font-size:.84rem}.dashboard-soft-note{padding:.9rem 1rem;border-radius:18px;border:1px solid var(--ui-line);background:var(--ui-soft);color:var(--ui-muted)}@media(max-width:991.98px){.dashboard-hero-main{flex-direction:column}.dashboard-meta-grid{grid-template-columns:1fr}.dashboard-insight-grid{grid-template-columns:1fr}.dashboard-project-header{flex-direction:column}.dashboard-project-meta{grid-template-columns:1fr 1fr}.dashboard-pulse-grid{grid-template-columns:1fr 1fr}}@media(max-width:767.98px){.dashboard-card,.dashboard-hero,.dashboard-time-card,.dashboard-project-card,.dashboard-pulse-card{padding:1rem}.dashboard-clock,.dashboard-pulse-time{font-size:2rem}.dashboard-quick-actions,.dashboard-project-actions{display:grid;grid-template-columns:1fr 1fr}.dashboard-quick-actions .btn,.dashboard-project-actions .btn{width:100%}.dashboard-quick-actions .btn:only-child,.dashboard-project-actions .btn:only-child{grid-column:1/-1}.dashboard-project-meta,.dashboard-pulse-grid{grid-template-columns:1fr}}
.premium-dashboard{display:grid;gap:1rem}.pd-surface{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:24px;box-shadow:0 1px 2px color-mix(in srgb,var(--ui-text) 3%,transparent);transition:border-color .18s ease,box-shadow .18s ease,transform .18s ease}.pd-surface:hover{border-color:color-mix(in srgb,var(--ui-blue) 18%,var(--ui-line));box-shadow:0 .75rem 2rem color-mix(in srgb,var(--ui-text) 6%,transparent)}.pd-hero{position:relative;overflow:hidden;padding:1.35rem;background:linear-gradient(135deg,color-mix(in srgb,var(--ui-blue) 10%,var(--ui-panel)),var(--ui-panel) 58%)}.pd-hero:after{content:"";position:absolute;right:-90px;top:-110px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,var(--ui-blue) 16%,transparent),transparent 68%);pointer-events:none}.pd-hero-head{position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.pd-eyebrow{font-size:.72rem;line-height:1;text-transform:uppercase;letter-spacing:.09em;color:var(--ui-muted);font-weight:800;margin-bottom:.6rem}.pd-title{font-size:clamp(1.85rem,4vw,2.55rem);line-height:1;letter-spacing:-.055em;font-weight:820;margin:0 0 .5rem}.pd-lead{color:var(--ui-muted);margin:0;max-width:760px}.pd-status-stack{position:relative;z-index:1;display:flex;align-items:center;justify-content:flex-end;gap:.45rem;flex-wrap:wrap}.pd-pill{display:inline-flex;align-items:center;gap:.38rem;min-height:34px;padding:.38rem .68rem;border:1px solid var(--ui-line);border-radius:999px;background:color-mix(in srgb,var(--ui-panel) 80%,transparent);font-size:.82rem;font-weight:680;white-space:nowrap}.pd-pill.is-success{background:var(--ui-green-soft);color:var(--ui-success-text);border-color:transparent}.pd-pill.is-warning{background:color-mix(in srgb,#f59e0b 14%,var(--ui-panel));color:color-mix(in srgb,#9a6200 88%,var(--ui-text));border-color:transparent}.pd-hero-meta{position:relative;z-index:1;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;margin-top:1.15rem}.pd-meta{padding:.78rem .88rem;border-radius:17px;background:color-mix(in srgb,var(--ui-panel) 78%,transparent);border:1px solid color-mix(in srgb,var(--ui-line) 84%,transparent);min-width:0}.pd-meta-label{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.065em;color:var(--ui-muted);font-weight:780;margin-bottom:.28rem}.pd-meta-value{display:block;font-weight:720;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pd-actions{position:relative;z-index:1;display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;margin-top:1rem}.pd-actions .btn{min-height:42px}.pd-live{padding:1.25rem;display:flex;flex-direction:column;min-height:100%}.pd-live-head,.pd-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.pd-card-title{font-size:1.05rem;line-height:1.25;font-weight:780;letter-spacing:-.025em;margin:0}.pd-card-subtitle{font-size:.86rem;color:var(--ui-muted);margin-top:.28rem}.pd-icon{width:2.55rem;height:2.55rem;min-width:2.55rem;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--ui-soft);color:var(--ui-muted);font-size:1rem}.pd-clock{font-size:2.45rem;line-height:1;font-weight:830;letter-spacing:-.06em;margin:1rem 0 .35rem}.pd-date{color:var(--ui-muted);font-size:.95rem}.pd-live-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin-top:1rem}.pd-live-metric{padding:.82rem .88rem;border-radius:17px;background:var(--ui-soft);border:1px solid var(--ui-line)}.pd-live-metric strong{display:block;font-size:1.35rem;line-height:1;letter-spacing:-.04em}.pd-live-metric span{display:block;color:var(--ui-muted);font-size:.78rem;margin-top:.35rem}.pd-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.pd-stat{position:relative;overflow:hidden;padding:1.05rem;text-decoration:none;color:var(--ui-text);min-height:132px;display:flex;flex-direction:column}.pd-stat:hover{color:var(--ui-text);transform:translateY(-2px)}.pd-stat-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem}.pd-stat-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.065em;color:var(--ui-muted);font-weight:780}.pd-stat-value{font-size:2rem;line-height:1;letter-spacing:-.055em;font-weight:820;margin-top:.85rem}.pd-stat-note{font-size:.82rem;color:var(--ui-muted);margin-top:auto;padding-top:.7rem}.pd-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.75fr);gap:1rem;align-items:start}.pd-card{padding:1.2rem}.pd-card-head{margin-bottom:1rem}.pd-card-actions{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap}.pd-donut-wrap{display:grid;grid-template-columns:180px minmax(0,1fr);gap:1.25rem;align-items:center}.pd-donut{--pd-value:0%;--pd-tone:var(--ui-blue);width:170px;aspect-ratio:1;border-radius:50%;background:conic-gradient(var(--pd-tone) 0 var(--pd-value),var(--ui-soft) var(--pd-value) 100%);position:relative;display:grid;place-items:center;margin:auto}.pd-donut:after{content:"";position:absolute;inset:20px;border-radius:50%;background:var(--ui-panel);box-shadow:inset 0 0 0 1px var(--ui-line)}.pd-donut-content{position:relative;z-index:1;text-align:center}.pd-donut-value{display:block;font-size:2rem;line-height:1;font-weight:840;letter-spacing:-.055em}.pd-donut-label{display:block;font-size:.75rem;color:var(--ui-muted);margin-top:.35rem}.pd-meter-list{display:grid;gap:.9rem}.pd-meter{display:grid;gap:.38rem}.pd-meter-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem;font-size:.88rem}.pd-meter-head strong{font-size:.84rem}.pd-meter-track{height:.62rem;border-radius:999px;background:var(--ui-soft);overflow:hidden}.pd-meter-fill{height:100%;width:0;border-radius:inherit;background:var(--pd-tone,var(--ui-blue));transition:width .55s cubic-bezier(.2,.8,.2,1)}.pd-attention-list{display:grid;gap:.65rem}.pd-attention{display:flex;align-items:center;gap:.75rem;padding:.8rem .85rem;border-radius:17px;background:var(--ui-soft);border:1px solid var(--ui-line);text-decoration:none;color:var(--ui-text)}.pd-attention:hover{color:var(--ui-text);border-color:color-mix(in srgb,var(--ui-blue) 25%,var(--ui-line))}.pd-attention-icon{width:2.2rem;height:2.2rem;min-width:2.2rem;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--ui-panel)}.pd-attention-body{min-width:0;flex:1}.pd-attention-title{display:block;font-weight:720;line-height:1.25}.pd-attention-sub{display:block;color:var(--ui-muted);font-size:.78rem;margin-top:.18rem}.pd-attention-count{font-size:1.05rem;font-weight:820}.pd-attention.is-warning .pd-attention-icon{background:color-mix(in srgb,#f59e0b 16%,var(--ui-panel));color:#b26b00}.pd-attention.is-danger .pd-attention-icon{background:var(--ui-red-soft);color:var(--ui-red)}.pd-attention.is-success .pd-attention-icon{background:var(--ui-green-soft);color:var(--ui-success-text)}.pd-two-col{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.pd-composition{display:grid;gap:.75rem}.pd-composition-row{display:grid;grid-template-columns:minmax(110px,1fr) 2fr auto;gap:.75rem;align-items:center}.pd-composition-label{font-size:.86rem}.pd-composition-value{font-size:.82rem;font-weight:760;min-width:2rem;text-align:right}.pd-list{display:grid;gap:.62rem}.pd-list-item{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.78rem .85rem;border-radius:16px;background:var(--ui-soft);border:1px solid var(--ui-line);text-decoration:none;color:var(--ui-text)}.pd-list-item:hover{color:var(--ui-text);border-color:color-mix(in srgb,var(--ui-blue) 22%,var(--ui-line))}.pd-list-main{min-width:0}.pd-list-title{display:block;font-weight:720;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pd-list-sub{display:block;font-size:.78rem;color:var(--ui-muted);margin-top:.18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pd-list-end{font-size:.8rem;color:var(--ui-muted);white-space:nowrap}.pd-activity{display:grid;gap:.75rem}.pd-activity-item{display:grid;grid-template-columns:auto 1fr;gap:.75rem}.pd-activity-dot{width:.72rem;height:.72rem;margin-top:.35rem;border-radius:50%;background:var(--ui-blue);box-shadow:0 0 0 .3rem color-mix(in srgb,var(--ui-blue) 13%,transparent)}.pd-activity-title{font-weight:720;line-height:1.3}.pd-activity-meta{font-size:.78rem;color:var(--ui-muted);margin-top:.18rem}.pd-empty{padding:1rem;border:1px dashed var(--ui-line);border-radius:16px;background:color-mix(in srgb,var(--ui-soft) 68%,transparent);color:var(--ui-muted);font-size:.88rem}.pd-footer-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.pd-divider{height:1px;background:var(--ui-line);margin:1rem 0}.pd-section-label{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.7rem}.pd-section-label strong{font-size:.9rem}.pd-mini-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.pd-mini{padding:.8rem .85rem;border:1px solid var(--ui-line);border-radius:16px;background:var(--ui-soft)}.pd-mini strong{display:block;font-size:1.15rem;letter-spacing:-.035em}.pd-mini span{display:block;font-size:.76rem;color:var(--ui-muted);margin-top:.25rem}@media(max-width:1399.98px){.pd-stat-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.pd-layout{grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr)}}@media(max-width:1199.98px){.pd-hero-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.pd-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pd-layout,.pd-two-col{grid-template-columns:1fr}.pd-footer-grid{grid-template-columns:1fr 1fr}.pd-donut-wrap{grid-template-columns:160px minmax(0,1fr)}}@media(max-width:767.98px){.pd-hero,.pd-live,.pd-card{padding:1rem}.pd-hero-head{flex-direction:column}.pd-status-stack{justify-content:flex-start}.pd-hero-meta{grid-template-columns:1fr}.pd-actions{display:grid;grid-template-columns:1fr 1fr}.pd-actions .btn{width:100%}.pd-live-grid{grid-template-columns:1fr 1fr}.pd-stat-grid{grid-template-columns:1fr 1fr;gap:.75rem}.pd-stat{min-height:118px;padding:.9rem}.pd-stat-value{font-size:1.7rem}.pd-donut-wrap{grid-template-columns:1fr}.pd-donut{width:150px}.pd-footer-grid{grid-template-columns:1fr}.pd-composition-row{grid-template-columns:1fr auto}.pd-composition-row .pd-meter-track{grid-column:1/-1}.pd-mini-grid{grid-template-columns:1fr 1fr}}@media(max-width:479.98px){.pd-actions,.pd-stat-grid,.pd-live-grid,.pd-mini-grid{grid-template-columns:1fr}}
.overview-premium{display:grid;gap:1rem}.ov-panel{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:24px}.ov-project{padding:1.25rem}.ov-project-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1.25rem}.ov-project-name{font-size:clamp(1.8rem,3vw,2.35rem);line-height:1.05;letter-spacing:-.055em;font-weight:820;margin:0 0 .35rem}.ov-project-sub{color:var(--ui-muted);margin:0;max-width:760px}.ov-project-status{display:flex;align-items:center;justify-content:flex-end;gap:.45rem;flex-wrap:wrap}.ov-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .68rem;border:1px solid var(--ui-line);border-radius:999px;background:var(--ui-soft);font-size:.8rem;font-weight:680;white-space:nowrap}.ov-chip.is-success{background:var(--ui-green-soft);color:var(--ui-success-text);border-color:transparent}.ov-project-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;margin-top:1rem}.ov-meta{padding:.8rem .9rem;border-radius:17px;background:var(--ui-soft);border:1px solid var(--ui-line);min-width:0}.ov-meta-label{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.065em;color:var(--ui-muted);font-weight:780;margin-bottom:.28rem}.ov-meta-value{display:block;font-weight:720;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ov-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.ov-kpi{padding:1rem 1.05rem;min-height:122px;display:flex;flex-direction:column;color:var(--ui-text)}.ov-kpi-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem}.ov-kpi-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.065em;color:var(--ui-muted);font-weight:780}.ov-icon{width:2.35rem;height:2.35rem;min-width:2.35rem;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--ui-soft);color:var(--ui-muted)}.ov-kpi-value{font-size:2rem;line-height:1;letter-spacing:-.055em;font-weight:830;margin-top:.75rem}.ov-kpi-note{font-size:.8rem;color:var(--ui-muted);margin-top:auto;padding-top:.55rem}.ov-main{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.72fr);gap:1rem;align-items:start}.ov-card{padding:1.2rem}.ov-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;margin-bottom:1rem}.ov-card-title{font-size:1.08rem;line-height:1.25;font-weight:790;letter-spacing:-.025em;margin:0}.ov-card-sub{font-size:.86rem;color:var(--ui-muted);margin-top:.25rem}.ov-health{display:grid;grid-template-columns:170px minmax(0,1fr);gap:1.25rem;align-items:center}.ov-donut{--pd-value:0%;--pd-tone:var(--ui-blue);width:160px;aspect-ratio:1;border-radius:50%;background:conic-gradient(var(--pd-tone) 0 var(--pd-value),var(--ui-soft) var(--pd-value) 100%);position:relative;display:grid;place-items:center;margin:auto}.ov-donut:after{content:"";position:absolute;inset:20px;border-radius:50%;background:var(--ui-panel);box-shadow:inset 0 0 0 1px var(--ui-line)}.ov-donut-content{position:relative;z-index:1;text-align:center}.ov-donut-value{display:block;font-size:2rem;line-height:1;font-weight:840;letter-spacing:-.055em}.ov-donut-label{display:block;font-size:.75rem;color:var(--ui-muted);margin-top:.35rem}.ov-progress-list{display:grid;gap:.9rem}.ov-progress{display:grid;gap:.38rem}.ov-progress-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem;font-size:.88rem}.ov-progress-head strong{font-size:.84rem}.ov-progress-track{height:.62rem;border-radius:999px;background:var(--ui-soft);overflow:hidden}.ov-progress-fill{height:100%;width:0;border-radius:inherit;background:var(--pd-tone,var(--ui-blue));transition:width .55s cubic-bezier(.2,.8,.2,1)}.ov-health-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.ov-health-metric{padding:1rem;border:1px solid var(--ui-line);border-radius:18px;background:var(--ui-soft);display:grid;gap:.7rem;min-width:0}.ov-health-metric-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.ov-health-metric-main{min-width:0}.ov-health-metric-label{display:block;font-size:.86rem;color:var(--ui-muted);margin-bottom:.3rem}.ov-health-metric-value{display:flex;align-items:baseline;gap:.45rem;flex-wrap:wrap}.ov-health-metric-value strong{font-size:1.65rem;line-height:1;letter-spacing:-.05em}.ov-health-metric-value span{font-size:.82rem;color:var(--ui-muted);font-weight:650}.ov-health-metric .ov-icon{background:var(--ui-panel)}.ov-health-metric .ov-progress-track{height:.5rem;background:var(--ui-panel)}.ov-health-metric.is-primary{background:color-mix(in srgb,var(--ui-blue) 7%,var(--ui-soft));border-color:color-mix(in srgb,var(--ui-blue) 18%,var(--ui-line))}.ov-health-summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 0 0;border-top:1px solid var(--ui-line);margin-top:1rem}.ov-health-summary-main{display:flex;align-items:center;gap:.65rem;min-width:0}.ov-health-summary-text{min-width:0}.ov-health-summary-title{font-weight:760}.ov-health-summary-sub{font-size:.8rem;color:var(--ui-muted);margin-top:.12rem}.ov-health-summary-value{font-size:1.15rem;font-weight:820;white-space:nowrap}@media(max-width:767.98px){.ov-health-grid{grid-template-columns:1fr}.ov-health-summary{align-items:flex-start;flex-direction:column}.ov-health-summary-value{white-space:normal}}.ov-divider{height:1px;background:var(--ui-line);margin:1rem 0}.ov-top-list,.ov-list,.ov-attention,.ov-activity{display:grid;gap:.65rem}.ov-row{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.78rem .85rem;border:1px solid var(--ui-line);border-radius:16px;background:var(--ui-soft);color:var(--ui-text);text-decoration:none}.ov-row:hover{color:var(--ui-text);border-color:color-mix(in srgb,var(--ui-blue) 22%,var(--ui-line))}.ov-row-main{min-width:0}.ov-row-title{display:block;font-weight:720;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ov-row-sub{display:block;font-size:.78rem;color:var(--ui-muted);margin-top:.18rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ov-row-end{font-size:.82rem;font-weight:720;white-space:nowrap}.ov-side{display:grid;gap:1rem}.ov-attention-item{display:flex;align-items:center;gap:.75rem;padding:.78rem .85rem;border:1px solid var(--ui-line);border-radius:16px;background:var(--ui-soft);color:var(--ui-text);text-decoration:none}.ov-attention-item:hover{color:var(--ui-text);border-color:color-mix(in srgb,var(--ui-blue) 22%,var(--ui-line))}.ov-attention-icon{width:2.15rem;height:2.15rem;min-width:2.15rem;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:var(--ui-panel)}.ov-attention-body{min-width:0;flex:1}.ov-attention-title{display:block;font-weight:720}.ov-attention-sub{display:block;color:var(--ui-muted);font-size:.77rem;margin-top:.14rem}.ov-attention-count{font-weight:820}.ov-attention-item.is-warning .ov-attention-icon{background:color-mix(in srgb,#f59e0b 15%,var(--ui-panel));color:#b26b00}.ov-attention-item.is-danger .ov-attention-icon{background:var(--ui-red-soft);color:var(--ui-red)}.ov-attention-item.is-success .ov-attention-icon{background:var(--ui-green-soft);color:var(--ui-success-text)}.ov-bottom{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.ov-activity-item{display:grid;grid-template-columns:auto 1fr;gap:.75rem}.ov-activity-dot{width:.72rem;height:.72rem;margin-top:.35rem;border-radius:50%;background:var(--ui-blue);box-shadow:0 0 0 .3rem color-mix(in srgb,var(--ui-blue) 13%,transparent)}.ov-activity-title{font-weight:720;line-height:1.3}.ov-activity-meta{font-size:.78rem;color:var(--ui-muted);margin-top:.18rem}.ov-empty{padding:1rem;border:1px dashed var(--ui-line);border-radius:16px;background:color-mix(in srgb,var(--ui-soft) 68%,transparent);color:var(--ui-muted);font-size:.88rem}@media(max-width:1199.98px){.ov-project-meta{grid-template-columns:repeat(2,minmax(0,1fr))}.ov-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.ov-main{grid-template-columns:1fr}.ov-bottom{grid-template-columns:1fr}}@media(max-width:767.98px){.ov-project,.ov-card{padding:1rem}.ov-project-top{flex-direction:column;align-items:flex-start}.ov-project-status{justify-content:flex-start}.ov-project-meta{grid-template-columns:1fr}.ov-kpis{grid-template-columns:1fr 1fr;gap:.75rem}.ov-health{grid-template-columns:1fr}.ov-donut{width:145px}}@media(max-width:479.98px){.ov-kpis{grid-template-columns:1fr}}

.endpoint-box{display:flex;gap:.5rem;align-items:center;background:var(--ui-soft);border-radius:16px;padding:.55rem .75rem}.endpoint-box code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.empty-cta{max-width:560px;margin:auto}.role-card{border:1px solid var(--ui-line);border-radius:18px;padding:1rem;height:100%;background:var(--ui-soft)}.history-list{max-height:320px;overflow:auto}.preset-preview{border:1px solid var(--ui-line);border-radius:18px;padding:1rem;background:var(--ui-soft)}.kbd-hint kbd{background:var(--ui-soft);color:var(--ui-text);border:1px solid var(--ui-line);box-shadow:none}.mobile-action-bar{position:sticky;bottom:0;background:color-mix(in srgb,var(--ui-panel) 92%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--ui-line);padding:.75rem;margin:1rem -.75rem -.75rem;z-index:20}.cms-toast{position:fixed;right:1rem;bottom:1rem;z-index:4000}.server-search{max-width:720px}.collection-filter-panel{background:var(--ui-soft);border:1px solid var(--ui-line);border-radius:20px;padding:1.15rem 1.2rem}.collection-filter-panel .form-control,.collection-filter-panel .form-select{background:var(--ui-panel)!important;border:1px solid var(--ui-line)!important;min-height:48px;width:100%!important}.collection-filter-panel .form-label{font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760;color:var(--ui-muted);margin-bottom:.42rem}.collection-filter-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;border-top:1px solid var(--ui-line);margin-top:1rem;padding-top:1rem}.collection-filter-summary{display:flex;align-items:center;gap:.75rem;min-width:0;flex-wrap:wrap}.collection-filter-summary .hint{min-width:0}.collection-filter-count{white-space:nowrap;font-weight:700}.collection-filter-actions{display:flex;gap:.6rem;align-items:center;flex:0 0 auto}.collection-filter-actions .btn{min-height:44px;padding-inline:1.05rem}.collection-table-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:.5rem;flex-wrap:nowrap}.collection-table-actions .btn-primary{white-space:nowrap}@media(max-width:767.98px){.collection-filter-panel{padding:1rem}.collection-filter-footer{align-items:stretch;flex-direction:column}.collection-filter-summary{align-items:flex-start;flex-direction:column;gap:.45rem}.collection-filter-count{white-space:normal}.collection-filter-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.collection-filter-actions .btn{width:100%}.collection-filter-actions .btn:only-child{grid-column:1/-1}.collection-table-actions{width:100%;justify-content:stretch}.collection-table-actions .btn-primary{flex:1 1 auto}.collection-table-actions .cms-action-dd{flex:0 0 auto}}.content-section-card{border:1px solid var(--ui-line);border-radius:22px;padding:1.1rem;background:var(--ui-panel);transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease}.content-section-card:hover{border-color:color-mix(in srgb,var(--ui-blue) 35%,var(--ui-line));box-shadow:0 .5rem 1.2rem color-mix(in srgb,var(--ui-text) 6%,transparent)}.content-section-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.9rem}.content-section-card-main{display:flex;align-items:flex-start;gap:.85rem;min-width:0}.content-section-card-title{min-width:0}.content-section-card-title h2{font-size:1.95rem;line-height:1.05;letter-spacing:-.04em;margin:0 0 .35rem 0}.content-section-card-title h2 a{text-decoration:none;color:var(--ui-text)}.content-section-card-title p{margin:0;color:var(--ui-muted)}.content-section-card-meta{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin-bottom:.9rem}.content-section-card-slug{font-size:.95rem;color:var(--ui-muted);font-weight:600}.content-section-card-endpoint{display:flex;align-items:center;gap:.75rem;justify-content:space-between;background:var(--ui-soft);border:1px solid var(--ui-line);border-radius:18px;padding:.8rem .9rem;margin-bottom:1rem}.content-section-card-endpoint code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}.content-section-card-actions{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:auto}.content-section-card-actions .btn-primary{flex:1 1 auto}.content-sections-filter-panel{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:24px;padding:1.4rem}.content-sections-filter-panel .form-control,.content-sections-filter-panel .form-select{min-height:48px;background:var(--ui-bg)!important;border:1px solid var(--ui-line)!important;width:100%!important}.content-sections-filter-panel .form-label{font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760;color:var(--ui-muted);margin-bottom:.42rem}.content-sections-filter-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;border-top:1px solid var(--ui-line);margin-top:1rem;padding-top:1rem}.content-sections-filter-summary{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;min-width:0}.content-sections-filter-actions{display:flex;align-items:center;gap:.6rem;flex:0 0 auto}.content-sections-filter-actions .btn{min-height:44px;padding-inline:1.05rem}.favorite-star{color:#ff9f0a}@media(max-width:767.98px){.content-section-card{padding:1rem}.content-section-card-title h2{font-size:1.65rem}.content-section-card-endpoint{align-items:flex-start;flex-direction:column}.content-section-card-actions{align-items:stretch;flex-direction:column}.content-section-card-actions .cms-action-dd,.content-section-card-actions .btn{width:100%}.content-sections-filter-panel{padding:1rem}.content-sections-filter-footer{align-items:stretch;flex-direction:column}.content-sections-filter-summary{align-items:flex-start;flex-direction:column;gap:.4rem}.content-sections-filter-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.content-sections-filter-actions .btn{width:100%}.content-sections-filter-actions .btn:only-child{grid-column:1/-1}}.file-trash-row{opacity:.82}.text-preline{white-space:pre-line}.min-w-0{min-width:0!important}.content-tree{display:grid;gap:.35rem;min-width:0;max-width:100%;overflow:hidden}.content-tree>*{min-width:0;max-width:100%}.content-tree .d-flex{min-width:0;max-width:100%}.content-tree-link{display:flex;align-items:center;gap:.55rem;width:100%;min-width:0;max-width:100%;overflow:hidden;padding:.55rem .65rem;border-radius:12px;color:var(--ui-text)}.content-tree-link>i,.content-tree-link>.badge,.content-tree-link>.drag-handle{flex:0 0 auto}.content-tree-link>.text-truncate,.content-tree-link>.content-tree-label{display:block;flex:1 1 auto;min-width:0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.content-tree .dropdown,.content-tree form,.content-tree button{flex:0 0 auto}.content-tree-link:hover,.content-tree-link.active{background:var(--ui-soft)}.content-tree-children{display:grid;gap:.15rem;min-width:0;max-width:100%;margin:.15rem 0 .5rem 1.1rem;padding-left:.7rem;border-left:1px solid var(--ui-line);overflow:hidden}.content-tree-children .content-tree-link{font-size:.9rem;padding:.42rem .55rem}.section-drop{border:1px solid transparent}.section-drop.drop-target{border-color:var(--ui-blue);background:color-mix(in srgb,var(--ui-blue) 10%,var(--ui-panel))}.collection-sections{display:flex;flex-wrap:wrap;gap:.3rem}.form-schema-row{background:var(--ui-soft);border-color:var(--ui-line)!important;transition:opacity .15s ease,transform .15s ease,box-shadow .15s ease}.form-schema-row.is-dragging{opacity:.72;box-shadow:0 1rem 2rem color-mix(in srgb,var(--ui-text) 16%,transparent);transform:scale(.995)}.form-schema-row .form-control,.form-schema-row .form-select{background:var(--ui-panel)!important}.js-form-field-drag{touch-action:none;user-select:none}.forms-filter-panel{background:var(--ui-soft);border:1px solid var(--ui-line);border-radius:24px;padding:1.15rem 1.2rem}.forms-filter-panel .form-control,.forms-filter-panel .form-select{background:var(--ui-panel)!important;border:1px solid var(--ui-line)!important;min-height:48px;width:100%!important}.forms-filter-panel .form-label{font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760;color:var(--ui-muted);margin-bottom:.42rem}.forms-filter-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;border-top:1px solid var(--ui-line);margin-top:1rem;padding-top:1rem}.forms-filter-summary{display:flex;align-items:center;gap:.75rem;min-width:0;flex-wrap:wrap}.forms-filter-count{white-space:nowrap;font-weight:700}.forms-filter-actions{display:flex;gap:.6rem;align-items:center;flex:0 0 auto}.forms-filter-actions .btn{min-height:44px;padding-inline:1.05rem}.forms-endpoint-cell{display:flex;align-items:center;gap:.55rem;min-width:0}.forms-endpoint-cell code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;max-width:240px}.forms-table-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:.5rem;white-space:nowrap}.forms-table-actions .btn-primary{white-space:nowrap}@media(max-width:767.98px){.forms-filter-panel{padding:1rem}.forms-filter-footer{align-items:stretch;flex-direction:column}.forms-filter-summary{align-items:flex-start;flex-direction:column;gap:.45rem}.forms-filter-count{white-space:normal}.forms-filter-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}.forms-filter-actions .btn{width:100%}.forms-filter-actions .btn:only-child{grid-column:1/-1}.forms-table-actions{width:100%;justify-content:stretch}.forms-table-actions .btn-primary{flex:1 1 auto}.forms-table-actions .cms-action-dd{flex:0 0 auto}.forms-endpoint-cell{align-items:flex-start;flex-direction:column}.forms-endpoint-cell code{max-width:100%}}.submission-filter-panel{background:var(--ui-soft);border:1px solid var(--ui-line);border-radius:24px;padding:1.15rem 1.2rem}.submission-filter-panel .form-control,.submission-filter-panel .form-select{background:var(--ui-panel)!important;border:1px solid var(--ui-line)!important;min-height:48px;width:100%!important}.submission-filter-panel .form-label{font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760;color:var(--ui-muted);margin-bottom:.42rem}.submission-filter-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;border-top:1px solid var(--ui-line);margin-top:1rem;padding-top:1rem}.submission-filter-summary{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}.submission-bulk-bar{position:sticky;top:var(--submission-sticky-top,4.75rem);z-index:1025;display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid var(--ui-line);border-radius:20px;padding:1rem 1.1rem;background:color-mix(in srgb,var(--ui-panel) 94%,transparent);backdrop-filter:saturate(180%) blur(18px);box-shadow:0 .75rem 2rem color-mix(in srgb,var(--ui-text) 10%,transparent);margin:0 0 1rem}.submission-bulk-bar[hidden]{display:none!important}.submission-bulk-meta{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.submission-bulk-count{font-size:1.1rem;font-weight:780;letter-spacing:-.03em}.submission-bulk-actions{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;justify-content:flex-end}.submission-bulk-actions .btn{min-height:42px}.submission-select-cell{width:56px;text-align:center}.submission-master-check{display:inline-flex;align-items:center;justify-content:center;width:2.2rem;height:2.2rem;border-radius:999px;background:var(--ui-soft);cursor:pointer}.submission-master-check input{margin:0}.submission-summary-cell{min-width:260px;max-width:420px}.submission-summary-cell .btn-link{line-height:1.3}.submission-page-table .text-truncate{max-width:260px}.submission-page-table tbody tr>td{transition:background-color .15s ease}.submission-page-table tbody tr.is-selected>td{background:color-mix(in srgb,var(--ui-blue) 8%,var(--ui-panel))}@media(max-width:767.98px){.submission-filter-panel{padding:1rem}.submission-filter-footer{align-items:stretch;flex-direction:column}.submission-filter-summary{align-items:flex-start;flex-direction:column;gap:.45rem}.submission-bulk-bar{align-items:stretch;flex-direction:column}.submission-bulk-actions{display:grid;grid-template-columns:1fr;width:100%}.submission-bulk-actions .btn{width:100%}.submission-master-check{width:2rem;height:2rem}.submission-summary-cell{min-width:0;max-width:none}}

.setup-shell{max-width:980px;margin-inline:auto}.setup-progress{display:flex;align-items:center;justify-content:center;gap:.65rem}.setup-progress-item{display:flex;align-items:center;gap:.55rem;color:var(--ui-muted);white-space:nowrap}.setup-progress-item span{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:999px;background:var(--ui-soft);border:1px solid var(--ui-line);font-weight:800}.setup-progress-item strong{font-size:.88rem}.setup-progress-item.active{color:var(--ui-text)}.setup-progress-item.active span{background:var(--ui-blue);border-color:var(--ui-blue);color:#fff}.setup-progress-item.done span{background:var(--ui-green);border-color:var(--ui-green);color:#fff}.setup-progress-line{width:56px;height:1px;background:var(--ui-line)}.setup-section-head{display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.2rem}.setup-section-head h2{font-size:1.25rem;margin:0 0 .3rem;font-weight:780;letter-spacing:-.025em}.setup-section-head p{margin:0;color:var(--ui-muted)}.setup-section-icon{display:flex;align-items:center;justify-content:center;width:2.75rem;height:2.75rem;flex:0 0 2.75rem;border-radius:14px;background:var(--ui-soft);font-size:1.15rem}.setup-choice-card{width:100%;min-height:122px;border:1px solid var(--ui-line);background:var(--ui-soft);color:var(--ui-text);border-radius:20px;padding:1rem;text-align:left;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.9rem;transition:.16s ease}.setup-choice-card:hover{transform:translateY(-1px);border-color:color-mix(in srgb,var(--ui-blue) 45%,var(--ui-line))}.setup-choice-card.is-selected{border-color:var(--ui-blue);box-shadow:0 0 0 2px color-mix(in srgb,var(--ui-blue) 18%,transparent);background:color-mix(in srgb,var(--ui-blue) 8%,var(--ui-panel))}.setup-choice-card strong,.setup-choice-card small{display:block}.setup-choice-card strong{font-size:1rem;margin-bottom:.25rem}.setup-choice-card small{color:var(--ui-muted);line-height:1.35}.setup-choice-icon{display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;border-radius:13px;background:var(--ui-panel);font-size:1.1rem}.setup-choice-check{display:flex;align-items:center;justify-content:center;width:1.7rem;height:1.7rem;border-radius:999px;border:1px solid var(--ui-line);color:transparent}.setup-choice-card.is-selected .setup-choice-check{background:var(--ui-blue);border-color:var(--ui-blue);color:#fff}.setup-language-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.setup-language-card{position:relative;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.75rem;min-height:76px;padding:.8rem .9rem;border:1px solid var(--ui-line);background:var(--ui-soft);border-radius:17px;cursor:pointer;transition:.15s ease}.setup-language-card:hover{border-color:color-mix(in srgb,var(--ui-blue) 40%,var(--ui-line))}.setup-language-card.is-selected{border-color:var(--ui-blue);background:color-mix(in srgb,var(--ui-blue) 8%,var(--ui-panel));box-shadow:0 0 0 1px color-mix(in srgb,var(--ui-blue) 18%,transparent)}.setup-language-card input{margin:0}.setup-language-copy strong,.setup-language-copy small{display:block}.setup-language-copy small{color:var(--ui-muted);margin-top:.12rem}.setup-language-check{display:flex;align-items:center;justify-content:center;width:1.6rem;height:1.6rem;border-radius:999px;background:var(--ui-blue);color:#fff;opacity:0}.setup-language-card.is-selected .setup-language-check{opacity:1}@media(max-width:767.98px){.setup-progress-item strong{display:none}.setup-progress-line{width:34px}.setup-language-grid{grid-template-columns:1fr}.setup-choice-card{min-height:104px}.setup-shell{padding:1rem!important}}



/* Keep a visible dropdown arrow on every single-value select. Background utility classes previously reset the Bootstrap arrow image. */
select.form-select:not([multiple]):not([size]){
-webkit-appearance:none!important;appearance:none!important;
background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 16 16%22%3E%3Cpath fill=%22none%22 stroke=%22%238e8e93%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%222%22 d=%22m2 5 6 6 6-6%22/%3E%3C/svg%3E")!important;
background-repeat:no-repeat!important;background-position:right .95rem center!important;background-size:14px 10px!important;
padding-right:2.75rem!important
}
select.form-select:not([multiple]):not([size]):disabled{opacity:.72;cursor:not-allowed}
.settings-layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:1.25rem;align-items:start}.settings-sidebar{position:sticky;top:5.4rem;z-index:25}.settings-nav-title{font-size:.74rem;text-transform:uppercase;letter-spacing:.07em;font-weight:760;color:var(--ui-muted)}.settings-nav{gap:.2rem}.settings-nav .nav-link{display:flex;align-items:center;gap:.7rem;border-radius:14px;padding:.72rem .8rem;color:var(--ui-text)!important;font-weight:670;text-align:left;white-space:normal}.settings-nav .nav-link:hover{background:var(--ui-soft)}.settings-nav .nav-link.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.settings-nav .nav-link i{font-size:1rem;width:1.15rem;text-align:center}.settings-content{min-width:0}.settings-section{scroll-margin-top:6.2rem}.settings-section+.settings-section{margin-top:2.75rem}.settings-section-heading{padding-inline:.15rem}.settings-section-heading h2{letter-spacing:-.025em}.settings-section-heading p{max-width:760px}.settings-section .ios-surface{scroll-margin-top:6.2rem}
@media(max-width:991.98px){.settings-layout{grid-template-columns:minmax(0,1fr);gap:1rem}.settings-sidebar{top:4.35rem;width:100%;max-width:100%;min-width:0;overflow:visible}.settings-sidebar>.ios-surface{width:100%;max-width:100%;min-width:0;border-radius:18px;overflow-x:auto!important;overflow-y:hidden!important;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch;touch-action:pan-x;scrollbar-width:none}.settings-sidebar>.ios-surface::-webkit-scrollbar{display:none}.settings-nav-title{display:none}.settings-nav{display:inline-flex!important;width:max-content;min-width:100%;max-width:none;flex-direction:row!important;flex-wrap:nowrap!important;gap:.2rem;padding:.1rem}.settings-nav .nav-link{flex:0 0 auto;white-space:nowrap;padding:.65rem .78rem}.settings-section{scroll-margin-top:9.2rem}.settings-section+.settings-section{margin-top:2.25rem}}

/* Structured UL/OL field editor */
.list-editor-structure{border:1px solid var(--ui-line);border-radius:18px;background:var(--ui-soft);padding:1rem}
.list-editor-list{margin:0;padding-left:1.75rem}
.list-editor-list>.list-editor-item{margin:.55rem 0;padding-left:.2rem}
.list-editor-list>.list-editor-item::marker{color:var(--ui-muted);font-weight:750}
.list-editor-row{display:flex;align-items:center;gap:.55rem;min-width:0}
.list-editor-row .form-control{flex:1 1 auto;min-width:0;min-height:44px}
.list-editor-controls{display:flex;align-items:center;gap:.35rem;flex:0 0 auto}
.list-editor-controls .btn:disabled{opacity:.35}
@media(max-width:575.98px){.list-editor-structure{padding:.8rem}.list-editor-list{padding-left:1.45rem}.list-editor-row{align-items:stretch}.list-editor-controls{gap:.25rem}}

/* Breadcrumb navigation */
.cms-breadcrumb{margin:0 0 1rem;max-width:100%;overflow-x:auto;overscroll-behavior-x:contain;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.cms-breadcrumb::-webkit-scrollbar{display:none}
.cms-breadcrumb .breadcrumb{--bs-breadcrumb-divider:"›";display:flex;flex-wrap:nowrap;align-items:center;width:max-content;min-width:0;max-width:100%;margin:0;padding:.1rem .15rem;font-size:.82rem;font-weight:650;color:var(--ui-muted)}
.cms-breadcrumb .breadcrumb-item{display:flex;align-items:center;min-width:0;white-space:nowrap}
.cms-breadcrumb .breadcrumb-item+.breadcrumb-item:before{color:color-mix(in srgb,var(--ui-muted) 62%,transparent);padding-inline:.55rem}
.cms-breadcrumb .breadcrumb-item a{display:inline-flex;align-items:center;gap:.38rem;color:var(--ui-muted);text-decoration:none;transition:color .15s ease}
.cms-breadcrumb .breadcrumb-item a:hover{color:var(--ui-blue)}
.cms-breadcrumb .breadcrumb-item.active{max-width:min(36vw,340px);overflow:hidden;text-overflow:ellipsis;color:var(--ui-text)}
.cms-breadcrumb .breadcrumb-item i{font-size:.88rem}
@media(max-width:767.98px){.cms-breadcrumb{margin-bottom:.8rem}.cms-breadcrumb .breadcrumb{font-size:.78rem}.cms-breadcrumb .breadcrumb-item.active{max-width:58vw}}

/* Active text fix: preserve component backgrounds and leave the top navigation unchanged. */
.nav-pills .nav-link.active,.nav-pills .nav-link[aria-selected="true"],.nav-tabs .nav-link.active,.nav-tabs .nav-link[aria-selected="true"],.dropdown-item.active,.dropdown-item:active,.list-group-item.active,.page-item.active .page-link,.btn.active,.btn[aria-pressed="true"],.btn-check:checked+.btn{color:var(--ui-on)!important}
.nav-pills .nav-link.active :is(i,span,small),.nav-pills .nav-link[aria-selected="true"] :is(i,span,small),.nav-tabs .nav-link.active :is(i,span,small),.nav-tabs .nav-link[aria-selected="true"] :is(i,span,small),.dropdown-item.active :is(i,span,small),.dropdown-item:active :is(i,span,small),.list-group-item.active :is(i,span,small),.page-item.active .page-link :is(i,span,small),.btn.active :is(i,span,small),.btn[aria-pressed="true"] :is(i,span,small),.btn-check:checked+.btn :is(i,span,small){color:var(--ui-on)!important}
@media(min-width:1400px){.premium-nav{position:absolute;left:50%;transform:translateX(-50%);gap:1.35rem!important;margin-left:0}.app-frame{display:grid;grid-template-columns:300px minmax(0,1fr);gap:1rem;align-items:start}.app-sidebar{position:sticky;top:5.2rem;min-width:0;max-width:100%;max-height:calc(100vh - 6.2rem);overflow-y:auto;overflow-x:hidden}.app-sidebar>.ios-sidebar{min-width:0;max-width:100%;overflow:hidden}.ios-shell{max-width:1840px}}
@media(min-width:1200px) and (max-width:1399.98px){.premium-nav{gap:.65rem!important;margin-left:.75rem}.premium-actions{gap:.35rem!important}.premium-actions .db-badge{display:none}.project-name{max-width:130px}.premium-brand{font-size:1.05rem}}

.app-frame.no-sidebar{display:block!important}.html-preview{min-height:12rem;padding:1rem;border:1px solid var(--ui-line);border-radius:16px;background:var(--ui-panel);overflow:auto}.html-preview img{max-width:100%;height:auto}.html-preview table{width:100%;border-collapse:collapse}.html-preview th,.html-preview td{border:1px solid var(--ui-line);padding:.5rem}.project-origin{font-size:.78rem;color:var(--ui-muted)}
@media(max-width:1199.98px){.entry-editor-grid{grid-template-columns:1fr}.entry-preview{position:static}.project-name{max-width:220px}}
@media(max-width:575.98px){.premium-topbar .brand-text{display:none}.premium-topbar .navbar-brand{margin-right:.25rem}.premium-topbar .project-name{max-width:110px}.premium-topbar .navbar-toggler{padding:.35rem .5rem}}
@media(max-width:767.98px){.premium-actions .logout-text{display:none}.premium-actions .btn{padding-inline:.7rem}.table-responsive{overflow:visible!important}.cms-responsive{display:block;width:100%}.cms-responsive thead{display:none}.cms-responsive tbody,.cms-responsive tr,.cms-responsive td{display:block;width:100%}.cms-responsive tbody{display:grid;gap:.75rem}.cms-responsive tr{border:1px solid var(--ui-line);border-radius:18px;padding:.4rem .9rem;background:var(--ui-panel)}.cms-responsive td{display:grid;grid-template-columns:minmax(105px,36%) minmax(0,1fr);gap:.75rem;align-items:center;padding:.65rem 0!important;border-bottom:1px solid var(--ui-line)!important;text-align:left!important}.cms-responsive td:last-child{border-bottom:0!important}.cms-responsive td:before{content:attr(data-label);color:var(--ui-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.045em;font-weight:750}.cms-responsive td[colspan]{display:block;text-align:center!important}.cms-responsive td[colspan]:before{display:none}.ios-actions .btn{flex:1 1 auto}.ios-head{gap:.75rem}.premium-actions .badge{display:none}}
@media(max-width:1199.98px){.premium-nav{position:static;left:auto;transform:none;gap:.75rem!important;margin:1rem 0 0;align-items:flex-start!important}.premium-actions{margin-left:0;margin-top:1rem}.ios-head{align-items:stretch;flex-direction:column}.ios-actions{justify-content:flex-start}.premium-side-card .list-group{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.45rem}.premium-side-card .list-group-item{margin-bottom:0}}
';}
function head_html($title){if(!headers_sent()){header('Cache-Control: private, no-cache, max-age=0, must-revalidate');header('Vary: Accept-Encoding');}$th=h(theme());echo '<!doctype html><html lang="'.h(lang()).'" data-bs-theme="'.$th.'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><meta name="csrf-token" content="'.h(csrf()).'"><title>'.h($title).'</title><link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin><link rel="dns-prefetch" href="//cdn.jsdelivr.net"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"><noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"></noscript><link href="?asset=app.css&amp;v='.rawurlencode(APP_CACHE_VERSION).'" rel="stylesheet"></head><body class="premium-bg">';}
function foot($show=null){
    $labels=json_encode([
        'search'=>T('search'),
        'reset'=>T('reset'),
        'no_results'=>T('no_results'),
        'sort_asc'=>T('sort_asc'),
        'sort_desc'=>T('sort_desc'),
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script>window.CMS_TABLE_I18N='.$labels.';</script>';
    $adv=json_encode(['copied'=>T('copied'),'unsaved'=>T('unsaved_changes'),'autosave'=>T('autosave'),'autosaved'=>T('autosaved'),'autosave_failed'=>T('autosave_failed'),'files_not_autosaved'=>T('files_not_autosaved'),'clear_entry_form_q'=>T('clear_entry_form_q'),'entry_form_cleared'=>T('entry_form_cleared'),'translation_translated'=>T('translation_translated'),'translation_autofilled'=>T('translation_autofilled'),'disable_lang'=>T('disable_language_warning'),'preset'=>preset_field_sets()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo '<script>window.CMS_ADV='.$adv.';</script>';
    echo '<script src="?asset=app.js&amp;v='.rawurlencode(APP_CACHE_VERSION).'"></script>';

    if($show)echo '<script>const m=document.getElementById('.json_encode($show).');if(m)new bootstrap.Modal(m).show()</script>';
    if(debug_enabled()&&!headers_sent()){$stats=Database::stats();header('Server-Timing: db;dur='.round($stats['seconds']*1000,2).';desc=\"'.$stats['queries'].' queries\"');}
    echo '</body></html>';
}

function page_head($title,$sub='',$actions='',$kicker='',$back=true){
    $backBtn=$back?smart_back_icon('./'):'';
    $titleBlock='<div class="d-flex align-items-start gap-3">'.$backBtn.'<div>'.($kicker?'<div class="ios-kicker">'.h($kicker).'</div>':'').'<h1 class="ios-title">'.h($title).'</h1>'.($sub?'<div class="ios-sub">'.$sub.'</div>':'').'</div></div>';
    return '<div class="ios-head">'.$titleBlock.($actions?'<div class="ios-actions">'.$actions.'</div>':'').'</div>';
}
function table_wrap($table,$tools='',$pager=''){return '<div class="ios-surface p-3 p-lg-4">'.$tools.'<div class="table-responsive">'.$table.'</div>'.$pager.'</div>';}

function dd_link($label,$href,$ico='',$target=''){
    return '<li><a class="dropdown-item rounded-3 d-flex align-items-center gap-2" '.($target?'target="'.h($target).'" ':'').'href="'.h($href).'">'.($ico?icon($ico):'').'<span>'.h($label).'</span></a></li>';
}
function dd_modal($label,$target,$ico='',$danger=false,$disabled=false){
    $cls='dropdown-item rounded-3 d-flex align-items-center gap-2'.($danger?' text-danger':'');
    return '<li><button type="button" class="'.$cls.'" data-bs-toggle="modal" data-bs-target="'.h($target).'" '.($disabled?'disabled':'').'>'.($ico?icon($ico):'').'<span>'.h($label).'</span></button></li>';
}
function dd_form($label,$action,$hidden,$ico='',$danger=false,$disabled=false){
    return '<li><form method="post" class="m-0">'.token().'<input type="hidden" name="_a" value="'.h($action).'">'.$hidden.'<button class="dropdown-item rounded-3 d-flex align-items-center gap-2'.($danger?' text-danger':'').'" '.($disabled?'disabled':'').'>'.($ico?icon($ico):'').'<span>'.h($label).'</span></button></form></li>';
}
function dd_menu($items){
    $items=array_filter($items);
    if(!$items)return '';
    static $i=0;$i++;
    $id='ddAction'.$i;
    return '<div class="dropdown d-inline-block cms-action-dd"><button type="button" class="btn btn-outline-dark btn-icon" id="'.h($id).'" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="'.h(T('action_menu')).'">'.icon('three-dots').'</button><ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-4 p-2" aria-labelledby="'.h($id).'">'.implode('',$items).'</ul></div>';
}
function smart_back_btn($fallback='./'){
    return '<button type="button" class="btn btn-outline-dark js-smart-back d-none" data-fallback="'.h($fallback).'">'.icon('arrow-left').' '.h(T('back')).'</button>';
}
function smart_back_icon($fallback='./'){
    return '<button type="button" class="btn btn-outline-dark btn-icon js-smart-back d-none flex-shrink-0" data-fallback="'.h($fallback).'" aria-label="'.h(T('back')).'">'.icon('arrow-left').'</button>';
}
function workspace_back_icon($fallback='./'){
    return '<button type="button" class="btn btn-light collection-workspace-back js-smart-back" data-fallback="'.h($fallback).'" aria-label="'.h(T('back')).'">'.icon('arrow-left').'</button>';
}
function breadcrumbs_html(array $items):string{
    $clean=[];
    foreach($items as $item){
        if(!is_array($item))continue;
        $label=trim((string)($item['label']??''));
        if($label==='')continue;
        $url=isset($item['url'])&&$item['url']!==''?(string)$item['url']:null;
        $iconName=trim((string)($item['icon']??''));
        $last=end($clean);
        if(is_array($last)&&$last['label']===$label&&($last['url']??null)===$url)continue;
        $clean[]=['label'=>$label,'url'=>$url,'icon'=>$iconName];
    }
    if(count($clean)<=1)return '';
    $lastIndex=count($clean)-1;$html='<nav class="cms-breadcrumb" aria-label="'.h(T('breadcrumb_nav')).'"><ol class="breadcrumb">';
    foreach($clean as $index=>$item){
        $current=$index===$lastIndex||$item['url']===null;
        $content=($item['icon']!==''?icon($item['icon']):'').'<span>'.h($item['label']).'</span>';
        if($current)$html.='<li class="breadcrumb-item active" aria-current="page">'.$content.'</li>';
        else $html.='<li class="breadcrumb-item"><a href="'.h($item['url']).'" data-cms-remember-back="1">'.$content.'</a></li>';
    }
    return $html.'</ol></nav>';
}
function current_breadcrumb_items(int $cid=0):array{
    $items=[['label'=>T('overview'),'url'=>U(['overview'=>1]),'icon'=>'house-door']];
    if(!$_GET||isset($_GET['overview']))return $items;

    if(isset($_GET['group'])){
        $items[]=['label'=>T('content_sections'),'url'=>U(['groups'=>1])];
        $g=group_row((int)$_GET['group']);
        if($g)$items[]=['label'=>(string)$g['n']];
        return $items;
    }
    if(isset($_GET['groups'])){
        $items[]=['label'=>T('content_sections')];
        return $items;
    }
    if(isset($_GET['form_submissions'])){
        $items[]=['label'=>T('forms'),'url'=>U(['forms'=>1])];
        $f=form_row((int)$_GET['form_submissions']);
        if($f)$items[]=['label'=>(string)$f['n'],'url'=>U(['forms'=>1,'form_edit'=>(int)$f['id']])];
        $items[]=['label'=>T('submissions')];
        return $items;
    }
    if(isset($_GET['forms'])){$items[]=['label'=>T('forms')];return $items;}
    if(isset($_GET['files'])){$items[]=['label'=>T('files')];return $items;}
    if(isset($_GET['users'])){$items[]=['label'=>T('settings'),'url'=>U(['settings'=>1])];$items[]=['label'=>T('users')];return $items;}
    if(isset($_GET['api_explorer'])){$items[]=['label'=>T('settings'),'url'=>U(['settings'=>1])];$items[]=['label'=>T('api_explorer')];return $items;}
    if(isset($_GET['api_keys'])){$items[]=['label'=>T('settings'),'url'=>U(['settings'=>1])];$items[]=['label'=>T('api_keys')];return $items;}
    if(isset($_GET['audit'])){$items[]=['label'=>T('settings'),'url'=>U(['settings'=>1])];$items[]=['label'=>T('audit_log')];return $items;}
    if(isset($_GET['diagnostics'])){$items[]=['label'=>T('settings'),'url'=>U(['settings'=>1])];$items[]=['label'=>T('diagnostics')];return $items;}
    if(isset($_GET['settings'])){$items[]=['label'=>T('settings')];return $items;}

    $collectionId=$cid?:((int)($_GET['c']??0));
    $collection=$collectionId?col($collectionId):null;
    if($collection){
        $items[]=['label'=>T('collections'),'url'=>U(['collections'=>1])];
        $isFields=isset($_GET['fields']);$isEntry=array_key_exists('entry',$_GET);$nested=collection_is_nested($collection);
        $parentEntryId=0;
        if($nested){
            $parent=collection_parent($collection);
            if($parent)$items[]=['label'=>(string)$parent['n'],'url'=>U(['c'=>(int)$parent['id']])];
            $parentEntryId=(int)($_GET['parent_entry']??0);
            if(!$parentEntryId)$parentEntryId=nested_parent_entry_id($collection);
            if($parentEntryId){
                $parentEntry=entry($parentEntryId);
                if($parentEntry)$items[]=['label'=>(string)$parentEntry['t'],'url'=>U(['c'=>(int)($parent['id']??0),'entry'=>$parentEntryId])];
            }
        }
        $collectionUrl=U(['c'=>$collectionId]+($parentEntryId?['parent_entry'=>$parentEntryId]:[]));
        $items[]=['label'=>(string)$collection['n'],'url'=>($isFields||$isEntry)?$collectionUrl:null];
        if($isFields)$items[]=['label'=>T('fields')];
        elseif($isEntry){
            $entryId=(int)($_GET['entry']??0);$row=$entryId?entry($entryId):null;
            $items[]=['label'=>$row?(string)$row['t']:T('new_entry')];
        }
        return $items;
    }
    if(isset($_GET['collections'])||isset($_GET['new_col'])||isset($_GET['edit_col'])){
        $items[]=['label'=>T('collections')];
        return $items;
    }
    return $items;
}


/* UI PAGES */
function login_page(){head_html(T('app'));echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-sm-10 col-md-6 col-lg-4"><div class="card premium-panel border-0"><div class="card-body p-4 p-md-5 rounded-4"><div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('database').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('app')).'</h1><p class="text-muted mb-0">Headless data admin</p></div>';if($m=flash())echo flash_html($m);echo post_form('login',inp('u',T('login'),'','text',['required'=>true,'autocomplete'=>'username']).inp('p',T('password'),'','password',['required'=>true,'autocomplete'=>'current-password']).'<button class="btn btn-dark btn-lg w-100">'.icon('box-arrow-in-right').' '.h(T('enter')).'</button>').'</div></div></div></div></main>';foot();}
function setup_page(){
    head_html(T('setup_welcome'));
    $old=old_all();
    $uiLang=(string)($old['ui_language']??lang());if(!isset(LANGS[$uiLang]))$uiLang=lang();
    $uiTheme=(string)($old['ui_theme']??theme());if(!isset(THEMES[$uiTheme]))$uiTheme=theme();
    $driver=(string)($old['driver']??'sqlite');if(!in_array($driver,['sqlite','mysql'],true))$driver='sqlite';
    $i18n=array_key_exists('content_i18n',$old)?!empty($old['content_i18n']):false;
    $defaultLang=(string)($old['content_default_lang']??$uiLang);if(!array_key_exists($defaultLang,CONTENT_LANGS))$defaultLang='ru';
    $selected=is_array($old['content_langs']??null)?array_values(array_intersect($old['content_langs'],array_keys(CONTENT_LANGS))):configured_content_langs();if(!$selected)$selected=['ru','kk','en'];
    $langOptions='';foreach(LANGS as $code=>$name)$langOptions.='<option value="'.h($code).'" '.($uiLang===$code?'selected':'').'>'.h($name).'</option>';
    $contentOptions='';foreach(CONTENT_LANGS as $code=>$name)$contentOptions.='<option value="'.h($code).'" '.($defaultLang===$code?'selected':'').'>'.h($name).'</option>';
    $languageCards='';foreach(CONTENT_LANGS as $code=>$name){$checked=in_array($code,$selected,true);$languageCards.='<label class="setup-language-card '.($checked?'is-selected':'').'"><input class="form-check-input js-setup-content-lang" type="checkbox" name="content_langs[]" value="'.h($code).'" '.($checked?'checked':'').'><span class="setup-language-copy"><strong>'.h($name).'</strong><small>'.h($code).'</small></span><span class="setup-language-check">'.icon('check-lg').'</span></label>';}
    $mysql='<div class="row g-3"><div class="col-md-6">'.inp('mysql_host',T('host'),(string)($old['mysql_host']??'localhost'),'text',['data-no-old'=>true]).'</div><div class="col-md-6">'.inp('mysql_database',T('database'),(string)($old['mysql_database']??'cms'),'text',['autocomplete'=>'off','data-no-old'=>true]).'</div><div class="col-md-6">'.inp('mysql_user',T('user_db'),(string)($old['mysql_user']??'root'),'text',['autocomplete'=>'off','data-no-old'=>true]).'</div><div class="col-md-6">'.inp('mysql_password',T('db_password'),'','password',['autocomplete'=>'new-password']).'</div></div>';
    $progress='<div class="setup-progress mb-4"><div class="setup-progress-item active" data-setup-progress="1"><span>1</span><strong>'.h(T('setup_step_interface')).'</strong></div><div class="setup-progress-line"></div><div class="setup-progress-item" data-setup-progress="2"><span>2</span><strong>'.h(T('setup_step_content')).'</strong></div><div class="setup-progress-line"></div><div class="setup-progress-item" data-setup-progress="3"><span>3</span><strong>'.h(T('setup_step_database')).'</strong></div></div>';
    $intro='<div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('sliders').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('setup_welcome')).'</h1><p class="text-muted mb-0">'.h(T('setup_welcome_hint')).'</p></div>';
    $step1='<section class="setup-step" data-setup-step="1"><div class="setup-section-head"><div class="setup-section-icon">'.icon('translate').'</div><div><h2>'.h(T('interface_language')).'</h2><p>'.h(T('interface_language_hint')).'</p></div></div><div class="mb-4"><select class="form-select form-select-lg rounded-4 bg-body-tertiary border-0" name="ui_language" id="setupUiLanguage" onchange="changeSetupLanguage(this.value)">'.$langOptions.'</select></div><div class="setup-section-head mt-4"><div class="setup-section-icon">'.icon('circle-half').'</div><div><h2>'.h(T('choose_theme')).'</h2><p>'.h(T('choose_theme_hint')).'</p></div></div><input type="hidden" name="ui_theme" id="setupUiTheme" value="'.h($uiTheme).'"><div class="row g-3"><div class="col-md-6"><button type="button" class="setup-choice-card '.($uiTheme==='light'?'is-selected':'').'" data-theme-choice="light" onclick="applySetupTheme(\'light\')"><span class="setup-choice-icon">'.icon('sun').'</span><span><strong>'.h(T('light')).'</strong><small>'.h(T('light_theme_hint')).'</small></span><span class="setup-choice-check">'.icon('check-lg').'</span></button></div><div class="col-md-6"><button type="button" class="setup-choice-card '.($uiTheme==='dark'?'is-selected':'').'" data-theme-choice="dark" onclick="applySetupTheme(\'dark\')"><span class="setup-choice-icon">'.icon('moon-stars').'</span><span><strong>'.h(T('dark')).'</strong><small>'.h(T('dark_theme_hint')).'</small></span><span class="setup-choice-check">'.icon('check-lg').'</span></button></div></div><div class="d-flex justify-content-end mt-4"><button type="button" class="btn btn-primary btn-lg px-4" onclick="setupShowStep(2)">'.h(T('continue')).' '.icon('arrow-right').'</button></div></section>';
    $step2='<section class="setup-step d-none" data-setup-step="2"><div class="setup-section-head"><div class="setup-section-icon">'.icon('globe2').'</div><div><h2>'.h(T('content_settings')).'</h2><p>'.h(T('content_language_setup_hint')).'</p></div></div><input type="hidden" name="content_i18n" value="0"><div class="row g-3 mb-4"><div class="col-md-6"><button type="button" class="setup-choice-card '.(!$i18n?'is-selected':'').'" data-content-mode="single" onclick="setSetupI18n(false)"><span class="setup-choice-icon">'.icon('file-text').'</span><span><strong>'.h(T('single_language_mode')).'</strong><small>'.h(T('single_language_mode_hint')).'</small></span><span class="setup-choice-check">'.icon('check-lg').'</span></button></div><div class="col-md-6"><button type="button" class="setup-choice-card '.($i18n?'is-selected':'').'" data-content-mode="multi" onclick="setSetupI18n(true)"><span class="setup-choice-icon">'.icon('translate').'</span><span><strong>'.h(T('multilingual_mode')).'</strong><small>'.h(T('multilingual_mode_hint')).'</small></span><span class="setup-choice-check">'.icon('check-lg').'</span></button></div></div><label class="d-none"><input type="checkbox" name="content_i18n" value="1" id="setupI18nToggle" '.($i18n?'checked':'').'></label><div id="setupSingleLanguage" class="'.($i18n?'d-none':'').'"><label class="form-label fw-semibold">'.h(T('default_content_language')).'</label><select class="form-select form-select-lg rounded-4 bg-body-tertiary border-0" name="content_default_lang">'.$contentOptions.'</select><div class="form-text mt-2">'.h(T('content_i18n_off_hint')).'</div></div><div id="setupMultiLanguage" class="'.($i18n?'':'d-none').'"><div class="d-flex align-items-center justify-content-between gap-3 mb-3"><div><div class="fw-semibold">'.h(T('choose_content_languages')).'</div><div class="small text-muted">'.h(T('content_i18n_on_hint')).'</div></div><span class="badge text-bg-light" id="setupLanguageCount">0</span></div><div class="setup-language-grid">'.$languageCards.'</div><div class="alert alert-danger rounded-4 border-0 mt-3 d-none" id="setupLanguageError">'.h(T('choose_at_least_one_language')).'</div></div><div class="d-flex justify-content-between gap-3 mt-4"><button type="button" class="btn btn-light btn-lg px-4" onclick="setupShowStep(1)">'.icon('arrow-left').' '.h(T('back')).'</button><button type="button" class="btn btn-primary btn-lg px-4" onclick="setupContentNext()">'.h(T('continue')).' '.icon('arrow-right').'</button></div></section>';
    $step3='<section class="setup-step d-none" data-setup-step="3"><div class="setup-section-head"><div class="setup-section-icon">'.icon('database').'</div><div><h2>'.h(T('setup_db')).'</h2><p>'.h(T('setup_db_hint')).'</p></div></div><input type="hidden" name="driver" id="dbDriver" value="'.h($driver).'"><div class="row g-3 mb-4"><div class="col-md-6"><button type="button" class="setup-choice-card '.($driver==='sqlite'?'is-selected':'').'" id="sqliteBtn" onclick="setDbDriver(\'sqlite\')"><span class="setup-choice-icon">'.icon('filetype-sql').'</span><span><strong>'.h(T('sqlite')).'</strong><small>'.h(T('sqlite_hint')).'</small></span><span class="setup-choice-check">'.icon('check-lg').'</span></button></div><div class="col-md-6"><button type="button" class="setup-choice-card '.($driver==='mysql'?'is-selected':'').'" id="mysqlBtn" onclick="setDbDriver(\'mysql\')"><span class="setup-choice-icon">'.icon('database-gear').'</span><span><strong>'.h(T('mysql')).'</strong><small>'.h(T('mysql_hint')).'</small></span><span class="setup-choice-check">'.icon('check-lg').'</span></button></div></div><div id="mysqlBox" class="'.($driver==='mysql'?'':'d-none').'">'.$mysql.'</div><div class="alert alert-info rounded-4 border-0 mt-4">'.icon('info-circle').' '.h(T('setup_summary')).'</div><div class="d-flex justify-content-between gap-3 mt-4"><button type="button" class="btn btn-light btn-lg px-4" onclick="setupShowStep(2)">'.icon('arrow-left').' '.h(T('back')).'</button><button class="btn btn-dark btn-lg px-4">'.icon('check-lg').' '.h(T('finish_setup')).'</button></div></section>';
    $body=$intro;if($m=flash())$body.=flash_html($m);$body.=$progress.$step1.$step2.$step3;
    $script=<<<'JS'
<script>
function setupShowStep(step){document.querySelectorAll('[data-setup-step]').forEach(function(el){el.classList.toggle('d-none',Number(el.dataset.setupStep)!==step)});document.querySelectorAll('[data-setup-progress]').forEach(function(el){var n=Number(el.dataset.setupProgress);el.classList.toggle('active',n===step);el.classList.toggle('done',n<step)});window.scrollTo({top:0,behavior:'smooth'})}
function applySetupTheme(value){document.getElementById('setupUiTheme').value=value;document.body.setAttribute('data-bs-theme',value);document.querySelectorAll('[data-theme-choice]').forEach(function(el){el.classList.toggle('is-selected',el.dataset.themeChoice===value)})}
function changeSetupLanguage(value){var theme=document.getElementById('setupUiTheme').value;document.cookie='cms_ui_theme='+encodeURIComponent(theme)+';path=/;max-age=31536000;SameSite=Lax';location.href='?lang='+encodeURIComponent(value)}
function setSetupI18n(enabled){document.getElementById('setupI18nToggle').checked=enabled;document.getElementById('setupSingleLanguage').classList.toggle('d-none',enabled);document.getElementById('setupMultiLanguage').classList.toggle('d-none',!enabled);document.querySelectorAll('[data-content-mode]').forEach(function(el){el.classList.toggle('is-selected',(enabled&&el.dataset.contentMode==='multi')||(!enabled&&el.dataset.contentMode==='single'))});updateSetupLanguageCount()}
function updateSetupLanguageCount(){var checked=document.querySelectorAll('.js-setup-content-lang:checked').length;var badge=document.getElementById('setupLanguageCount');if(badge)badge.textContent=checked;document.querySelectorAll('.setup-language-card').forEach(function(card){var input=card.querySelector('input');card.classList.toggle('is-selected',input.checked)});var error=document.getElementById('setupLanguageError');if(error&&checked>0)error.classList.add('d-none')}
function setupContentNext(){if(document.getElementById('setupI18nToggle').checked&&!document.querySelector('.js-setup-content-lang:checked')){document.getElementById('setupLanguageError').classList.remove('d-none');return}setupShowStep(3)}
function setDbDriver(driver){document.getElementById('dbDriver').value=driver;document.getElementById('mysqlBox').classList.toggle('d-none',driver!=='mysql');document.getElementById('sqliteBtn').classList.toggle('is-selected',driver==='sqlite');document.getElementById('mysqlBtn').classList.toggle('is-selected',driver==='mysql')}
document.querySelectorAll('.js-setup-content-lang').forEach(function(input){input.addEventListener('change',updateSetupLanguageCount)});updateSetupLanguageCount();applySetupTheme(document.getElementById('setupUiTheme').value);setDbDriver(document.getElementById('dbDriver').value);
</script>
JS;
    echo '<main class="container py-4 py-lg-5"><div class="row justify-content-center"><div class="col-12 col-xl-9"><div class="ios-surface setup-shell p-4 p-lg-5">'.post_form('setup_db',$body,'id="setupWizardForm"').'</div>'.diagnostics_compact_html(setup_diagnostics_report()).'</div></div></main>'.$script;
    foot();
}
function first_user_page(){
    head_html(T('first_user'));
    $body='<div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('person-plus').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('first_user')).'</h1><p class="text-muted mb-0">'.h(T('first_user_hint')).'</p></div>';
    if($m=flash())$body.=flash_html($m);
    $body.='<div class="row g-3"><div class="col-md-6">'.inp('l',T('username'),LOGIN,'text',['required'=>true,'autocomplete'=>'username']).'</div><div class="col-md-6">'.inp('n',T('display_name'),'Administrator','text',['autocomplete'=>'name']).'</div></div>'.inp('p',T('password'),'','password',['required'=>true,'autocomplete'=>'new-password']).'<button class="btn btn-dark btn-lg w-100 mt-2">'.icon('person-check').' '.h(T('save')).'</button>';
    echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-lg-6"><div class="ios-surface p-4 p-lg-5">'.post_form('first_user',$body).'</div></div></div></main>';
    foot();
}

function project_nav(){
    $p=current_project();
    return '<button class="nav-link btn btn-link" type="button" data-bs-toggle="modal" data-bs-target="#projectsModal">'.icon('window-stack').' '.h(T('projects')).($p?' <span class="badge rounded-pill text-bg-light ms-1">'.h($p['s']).'</span>':'').'</button>';
}
function projects_modal(){
    if(!can_view_entries())return '';$rows=projects();$edit=(is_admin_user()&&isset($_GET['project_edit']))?project((int)$_GET['project_edit']):null;$active=current_project_id();$body='<div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><div class="fw-semibold">'.h(T('project_switch')).'</div><div class="small text-muted">'.h(T('projects_hint')).'</div></div>'.(is_admin_user()?'<button class="btn btn-primary" data-bs-target="#projectModal" data-bs-toggle="modal">'.icon('plus-lg').' '.h(T('new_project')).'</button>':'').'</div><div class="d-grid gap-2" '.(is_admin_user()?'data-sort-action="reorder_projects"':'').'>';
    foreach($rows as $pr){$id=(int)$pr['id'];$is=$id===$active;$switch=post_form('set_project','<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="_return" value="'.h(U(['overview'=>1])).'"><button class="btn '.($is?'btn-primary':'btn-light').' btn-icon" '.($is?'disabled':'').' aria-label="'.h(T('project_switch')).'">'.icon($is?'check-lg':'arrow-right').'</button>');$items=[];if(is_admin_user()){$items[]=dd_link(T('edit_project'),U(['settings'=>1,'project_edit'=>$id]),'pencil');if(!$is){$stats=project_file_stats($id);$items[]=dd_modal(T('delete_project'),'#deleteProjectModal'.$id,'trash3',true);}}$body.='<div class="collection-item '.($is?'active':'').' d-flex align-items-center gap-3" '.(is_admin_user()?'draggable="true" data-sort-id="'.$id.'"':'').'>'.(is_admin_user()?'<button type="button" class="btn btn-light btn-icon drag-handle flex-shrink-0" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button>':'').'<div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">'.h($pr['n']).($is?' <span class="badge text-bg-success">'.h(T('active')).'</span>':'').'</div><div class="small text-muted text-truncate"><code>'.h($pr['s']).'</code> · '.h($pr['d']).'</div></div>'.$switch.($items?dd_menu($items):'').'</div>';}$body.='</div>';
    return '<div class="modal fade" id="projectsModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h(T('project_switch')).'</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body">'.$body.'</div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">'.h(T('close')).'</button></div></div></div></div>'.($edit?project_modal($edit):'').(is_admin_user()&&!$edit?project_modal(null):'').(is_admin_user()?implode('',array_map(fn($pr)=>((int)$pr['id']!==current_project_id()?delete_project_modal($pr,'deleteProjectModal'.(int)$pr['id']):''),$rows)):'');
}
function project_modal($p=null){$id=(int)($p['id']??0);$body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$p['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-6">'.inp('s',T('slug'),$p['s']??'','text',['data-slug-target'=>'1']).'</div></div>'.area('d',T('description'),$p['d']??'',['rows'=>'3']);$footer='<span class="me-auto"></span><button type="button" class="btn btn-light" data-bs-target="#projectsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('projectModal',$id?T('edit_project'):T('new_project'),'project',$body,$footer);}
function delete_project_modal($p,$mid){$stats=project_file_stats((int)$p['id']);$body='<input type="hidden" name="id" value="'.(int)$p['id'].'"><p>'.h(T('delete_project_q')).'</p><div class="alert alert-danger mb-3">'.h($p['n']).'</div><div class="ios-surface p-3 mb-3"><div class="d-flex justify-content-between"><span>'.h(T('files')).'</span><strong>'.(int)$stats['count'].'</strong></div><div class="d-flex justify-content-between mt-2"><span>'.h(T('cleanup_total')).'</span><strong>'.h(fmt_size($stats['size'])).'</strong></div></div><div class="alert alert-warning mb-0">'.h(T('project_files_to_global_trash')).'</div>';$footer='<button type="button" class="btn btn-light" data-bs-target="#projectsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';return form_modal($mid,T('delete_project'),'del_project',$body,$footer);}
function groups_nav(){
    if(!can_view_entries())return '';$active=isset($_GET['collections'])||isset($_GET['groups'])||isset($_GET['group'])||isset($_GET['c'])||isset($_GET['fields'])||isset($_GET['entry'])||isset($_GET['new_col'])||isset($_GET['edit_col']);
    return '<div class="nav-item dropdown"><button class="nav-link dropdown-toggle '.($active?'active':'').'" type="button" data-bs-toggle="dropdown" aria-expanded="false">'.icon('folder2').' '.h(T('content_nav')).'</button><ul class="dropdown-menu border-0 shadow rounded-4 p-2"><li><a class="dropdown-item rounded-3" href="'.h(U(['collections'=>1])).'">'.icon('collection').' '.h(T('collections')).'</a></li><li><a class="dropdown-item rounded-3" href="'.h(U(['groups'=>1])).'">'.icon('diagram-3').' '.h(T('content_sections')).'</a></li></ul></div>';
}
function content_context(){return isset($_GET['collections'])||isset($_GET['c'])||isset($_GET['fields'])||isset($_GET['entry'])||isset($_GET['group'])||isset($_GET['groups'])||isset($_GET['new_col'])||isset($_GET['edit_col']);}
function entry_context(){return isset($_GET['entry']);}
function collection_nav($cid=0){
    if(!can_view_entries()||!content_context())return '';
    return '<button class="nav-link btn btn-link d-xxl-none '.($cid?'active':'').'" type="button" data-bs-toggle="offcanvas" data-bs-target="#collectionsOffcanvas" aria-controls="collectionsOffcanvas">'.icon('list-nested').' '.h(T('content_nav')).'</button>';
}
function collections_browser_body($cid=0,$desktop=false){
    if(!can_view_entries())return '';
    $pid=current_project_id();$all=cols($pid);$ungrouped=ungrouped_cols($pid);$sections=groups($pid);$activeGroup=(int)($_GET['group']??0);$filter=(string)($_GET['section']??'');
    $h='<div class="content-tree">';
    $h.='<a class="content-tree-link '.(isset($_GET['collections'])&&$filter===''?'active':'').'" href="'.h(U(['collections'=>1])).'">'.icon('collection').'<span class="content-tree-label">'.h(T('all_collections')).'</span><span class="badge text-bg-light">'.count($all).'</span></a>';
    $h.='<a class="content-tree-link '.($filter==='none'?'active':'').'" href="'.h(U(['collections'=>1,'section'=>'none'])).'">'.icon('inbox').'<span class="content-tree-label">'.h(T('without_section')).'</span><span class="badge text-bg-light">'.count($ungrouped).'</span></a>';
    if($ungrouped){
        $h.='<div class="content-tree-children">';
        foreach($ungrouped as $c){
            $id=(int)$c['id'];
            $h.='<div class="d-flex align-items-center gap-1 mb-1" '.(can_schema()?'draggable="true" data-collection-drag-id="'.$id.'"':'').'><a class="content-tree-link js-collection-item flex-grow-1 min-w-0 '.($cid===$id?'active':'').'" data-search="'.h(mb_strtolower($c['n'].' '.$c['s'])).'" title="'.h($c['n']).'" href="'.h(U(['c'=>$id])).'">'.icon('database').'<span class="content-tree-label">'.h($c['n']).'</span></a>'.collection_manage_buttons($c,'link',false).'</div>';
        }
        $h.='</div>';
    }
    $h.='<div class="d-flex align-items-center justify-content-between mt-2 px-2"><span class="small text-uppercase fw-bold text-muted">'.h(T('content_sections')).'</span><span class="badge text-bg-light">'.count($sections).'</span></div>';
    $h.='<div class="js-sortable-groups" '.(can_schema()?'data-sort-action="reorder_groups"':'').'>';
    foreach($sections as $g){
        $gid=(int)$g['id'];$children=group_cols($gid,$pid);
        $h.='<div class="section-drop mb-1" '.(can_schema()?'draggable="true" data-sort-id="'.$gid.'" data-group-drop-id="'.$gid.'"':'').'><div class="d-flex align-items-center gap-1"><a class="content-tree-link flex-grow-1 min-w-0 '.($activeGroup===$gid?'active':'').'" title="'.h($g['n']).'" href="'.h(U(['group'=>$gid])).'">'.(can_schema()?'<span class="drag-handle" tabindex="0" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</span>':'').icon('folder2').'<span class="content-tree-label">'.h($g['n']).'</span><span class="badge text-bg-light">'.count($children).'</span></a>'.group_manage_buttons($g,'link',false).'</div>';
        if($children){
            $h.='<div class="content-tree-children">';
            foreach($children as $c){
                $id=(int)$c['id'];
                $h.='<div class="d-flex align-items-center gap-1 mb-1" '.(can_schema()?'draggable="true" data-collection-drag-id="'.$id.'"':'').'><a class="content-tree-link js-collection-item flex-grow-1 min-w-0 '.($cid===$id?'active':'').'" data-search="'.h(mb_strtolower($c['n'].' '.$c['s'])).'" title="'.h($c['n']).'" href="'.h(U(['c'=>$id])).'">'.icon('database').'<span class="content-tree-label">'.h($c['n']).'</span></a>'.collection_manage_buttons($c,'link',false,false).collection_section_action_trigger($g,$c,false,!$desktop).'</div>';
            }
            $h.='</div>';
        }
        $h.='</div>';
    }
    $h.='</div></div>';
    if(can_schema())$h.='<div class="border-top pt-3 mt-3"><a class="btn btn-primary w-100 mb-2" href="'.h(U(['collections'=>1,'new_col'=>1])).'">'.icon('plus-lg').' '.h(T('new_collection')).'</a><form method="post" enctype="multipart/form-data">'.token().'<input type="hidden" name="_a" value="import_col_schema"><label class="form-label">'.h(T('import_schema')).'</label><div class="input-group"><input class="form-control" type="file" name="schema" accept="application/json,.json" required><button class="btn btn-secondary" aria-label="'.h(T('import_schema')).'">'.icon('upload').'</button></div></form></div>';
    return $h;
}
function collections_modal($cid=0){
    if(!can_view_entries())return '';
    return '<div class="offcanvas offcanvas-start" tabindex="-1" id="collectionsOffcanvas" aria-labelledby="collectionsOffcanvasLabel"><div class="offcanvas-header"><div><h5 class="offcanvas-title" id="collectionsOffcanvasLabel">'.h(T('content_nav')).'</h5><div class="small text-muted">'.h(T('section_shortcut_hint')).'</div></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="'.h(T('close')).'"></button></div><div class="offcanvas-body">'.collections_browser_body($cid,false).'</div></div>';
}
function collections_sidebar($cid=0){
    if(!can_view_entries())return '';
    return '<div class="ios-sidebar p-3"><div class="mb-3"><div class="fw-bold">'.h(T('content_nav')).'</div><div class="small text-muted">'.h(T('section_shortcut_hint')).'</div></div>'.collections_browser_body($cid,true).'</div>';
}
function layout($html,$cid=0,$show=null){
    head_html(T('app'));$pr=current_project();$user=current_user();$context=content_context();$desktopSidebar=$context&&!entry_context();$projectLabel=h($pr?$pr['n']:'—');$projectButton='<button class="btn btn-light btn-sm d-none d-xl-inline-flex align-items-center gap-2" type="button" data-bs-toggle="modal" data-bs-target="#projectsModal" aria-label="'.h(T('project_switch')).'">'.icon('window-stack').'<span class="text-truncate project-name">'.$projectLabel.'</span>'.icon('chevron-down').'</button>';$projectButtonMobile='<button class="btn btn-light btn-sm d-inline-flex d-xl-none align-items-center gap-2 ms-auto me-2" type="button" data-bs-toggle="modal" data-bs-target="#projectsModal" aria-label="'.h(T('project_switch')).'">'.icon('window-stack').'<span class="text-truncate project-name">'.$projectLabel.'</span></button>';$overviewActive=(!$_GET||isset($_GET['overview']))?'active':'';
    echo '<nav class="navbar navbar-expand-xl premium-topbar sticky-top"><div class="container-fluid px-3 px-lg-4"><a class="navbar-brand fw-bold premium-brand" href="'.h(U(['overview'=>1])).'">'.icon('database').' <span class="brand-text">'.h(T('app')).'</span></a>'.$projectButtonMobile.'<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Menu"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="topNav"><div class="navbar-nav premium-nav align-items-xl-center justify-content-center"><a class="nav-link '.$overviewActive.'" href="'.h(U(['overview'=>1])).'">'.icon('speedometer2').' '.h(T('overview')).'</a>'.groups_nav().collection_nav($cid).(can_forms()?'<a class="nav-link '.((isset($_GET['forms'])||isset($_GET['form_submissions']))?'active':'').'" href="'.h(U(['forms'=>1])).'">'.icon('inbox-fill').' '.h(T('forms')).'</a>':'').(can_files()?'<a class="nav-link '.(isset($_GET['files'])?'active':'').'" href="'.h(U(['files'=>1])).'">'.icon('folder2-open').' '.h(T('files')).'</a>':'').(can_settings()?'<a class="nav-link '.(isset($_GET['settings'])||isset($_GET['api_explorer'])?'active':'').'" href="'.h(U(['settings'=>1])).'">'.icon('gear').' '.h(T('settings')).'</a>':'').'</div><div class="premium-actions d-flex flex-wrap gap-2 align-items-center">'.$projectButton.'<span class="badge rounded-pill text-bg-light border px-3 py-2 db-badge">'.h(T('db')).': '.h(strtoupper(db_driver())).'</span><span class="small text-muted d-none d-xxl-inline">'.h($user['n']??$user['l']??'').'</span><a class="btn btn-light btn-sm" href="'.h(U(['logout'=>1])).'">'.icon('box-arrow-right').' <span class="logout-text">'.h(T('logout')).'</span></a></div></div></div></nav><main class="container-fluid p-3 p-lg-4 ios-shell"><div class="app-frame '.(!$desktopSidebar?'no-sidebar':'').'"><aside class="app-sidebar '.($desktopSidebar?'d-none d-xxl-block':'d-none').'">'.($desktopSidebar?collections_sidebar($cid):'').'</aside><section class="app-content min-w-0">';
    echo breadcrumbs_html(current_breadcrumb_items($cid));
    if($f=flash()){$type=$f['type']??'info';$map=['success'=>'success','warning'=>'warning','danger'=>'danger','info'=>'info'];echo '<div class="alert alert-'.h($map[$type]??'info').' alert-dismissible fade show">'.h($f['message']??'').'<button class="btn-close" data-bs-dismiss="alert" aria-label="'.h(T('close')).'"></button></div>';}
    $editCollection=$cid?col($cid):(isset($_GET['edit_col'])?col((int)$_GET['edit_col']):null);$defaultGroup=(isset($_GET['new_col'])&&isset($_GET['group']))?(int)$_GET['group']:0;$nestedParent=isset($_GET['new_nested'])?col((int)$_GET['new_nested']):null;if($nestedParent&&collection_is_nested($nestedParent))$nestedParent=null;
    echo $html.'</section></div></main>'.projects_modal().($context?collections_modal($cid).collection_modal(null,'collectionNewModal',$defaultGroup).collection_section_action_modals_all():'').($nestedParent?collection_modal(null,'nestedCollectionModal',0,$nestedParent):'').($editCollection?collection_modal($editCollection,'collectionEditModal'):'').clean_files_modal().universal_delete_modal().language_disable_modal();$show=$show?:old_modal();if(!$show&&isset($_GET['new_nested']))$show='nestedCollectionModal';if(!$show&&isset($_GET['new_col']))$show='collectionNewModal';if(!$show&&isset($_GET['edit_col']))$show='collectionEditModal';$show=$show?:((isset($_GET['project_edit']))?'projectModal':null);foot($show);old_clear();
}
function collection_modal($c=null,$mid='collectionModal',$defaultGroup=0,$parentCollection=null){
    $id=(int)($c['id']??0);$nested=$parentCollection||collection_is_nested($c);$parent=$parentCollection?:collection_parent($c);$groups=$nested?[]:groups();$typeField=$id?select_html('m_locked',T('collection_type'),['multiple'=>T('multiple'),'single'=>T('single')],collection_mode($c??[]),['disabled'=>true,'data-no-old'=>true]).'<div class="form-text mb-3">'.h(T('collection_type_locked')).'</div>':select_html('m',T('collection_type'),['multiple'=>T('multiple'),'single'=>T('single')],collection_mode($c??[]));$presetField=$id?'':select_html('preset',T('collection_preset'),collection_preset_options(),'page',['class'=>'form-select js-collection-preset']);
    $body='<input type="hidden" name="id" value="'.$id.'">'.($nested?'<input type="hidden" name="parent_cid" value="'.(int)($parent['id']??0).'">':'');if($id&&!$nested)$body.='<input type="hidden" name="_sync_sections" value="1"><input type="hidden" name="_return" value="'.h(stable_return_url($_SERVER['REQUEST_URI']??U(['collections'=>1]))).'">';elseif($nested)$body.='<input type="hidden" name="_return" value="'.h(U(['c'=>(int)($parent['id']??0)])).'">';
    $body.=resource_i18n_fields($c,$mid).inp('s',T('slug'),$c['s']??'','text',['data-slug-target'=>'1']);
    $body.='<div class="row g-3"><div class="col-md-6">'.$typeField.'</div>'.($presetField?'<div class="col-12">'.$presetField.'<div class="preset-preview js-preset-preview"><div class="fw-semibold mb-2">'.h(T('preset_preview')).'</div><div class="small text-muted"></div></div></div>':'').'</div>'.($nested?'':access_control_html($c,'collection'));
    if(!$id&&!$nested){$opts=[0=>T('without_section')];foreach($groups as $g)$opts[(int)$g['id']]=$g['n'];$body.=select_html('add_group_id',T('section_optional'),$opts,$defaultGroup);}
    elseif(!$nested){$selected=old_value('section_ids',collection_group_ids($id));if(!is_array($selected))$selected=collection_group_ids($id);$selected=array_map('intval',$selected);$body.='<div class="border rounded-4 p-3 mb-3"><div class="fw-semibold mb-1">'.h(T('sections_used')).'</div><div class="small text-muted mb-3">'.h(T('remove_from_section_hint')).'</div>';if(!$groups)$body.='<div class="text-muted">'.h(T('without_section')).'</div>';foreach($groups as $g){$gid=(int)$g['id'];$checkId=$mid.'_section_'.$gid;$body.='<div class="d-flex align-items-center gap-2 mb-2"><div class="form-check flex-grow-1 mb-0"><input class="form-check-input" type="checkbox" name="section_ids[]" value="'.$gid.'" id="'.h($checkId).'" '.(in_array($gid,$selected,true)?'checked':'').'><label class="form-check-label" for="'.h($checkId).'">'.h($g['n']).'</label></div>'.group_manage_buttons($g,'link',false).'</div>';}$body.='</div>';}
    if($nested&&$parent)$body='<div class="alert alert-light rounded-4"><div class="small text-muted">'.h(T('parent_collection')).'</div><div class="fw-semibold">'.h($parent['n']).'</div></div>'.$body;
    $delete=$id?universal_delete_button(T('delete_collection'),'del_col',['id'=>$id],T('delete_collection'),collection_delete_message($c),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal($mid,$id?T('collection_settings'):($nested?T('new_nested_collection'):T('new_collection')),'col',$body,$footer);
}
function collection_section_action_modal($g,$c,$mid,$return=null){
    $gid=(int)$g['id'];$cid=(int)$c['id'];$return=stable_return_url($return?:U(['group'=>$gid]));
    $unlink=post_form('unlink_group_collection','<input type="hidden" name="gid" value="'.$gid.'"><input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="_return" value="'.h($return).'"><button class="btn btn-outline-primary w-100">'.icon('folder-minus').' '.h(T('remove_from_section_only')).'</button>');
    $delete=universal_delete_button(T('delete_collection_everywhere'),'del_col',['id'=>$cid,'_return'=>$return],T('delete_collection'),collection_delete_message($c),true,'btn btn-danger w-100','trash3',T('delete_collection_everywhere'));
    $body='<p class="text-muted">'.h(sprintf(T('collection_actions_hint'),$c['n'],$g['n'])).'</p><div class="d-grid gap-3"><div class="border rounded-4 p-3"><div class="d-flex align-items-start gap-3 mb-3"><span class="btn btn-light btn-icon disabled">'.icon('folder-minus').'</span><div><div class="fw-semibold">'.h(T('remove_from_section_only')).'</div><div class="small text-muted mt-1">'.h(T('remove_from_section_only_hint')).'</div></div></div>'.$unlink.'</div><div class="border border-danger-subtle rounded-4 p-3 bg-danger-subtle"><div class="d-flex align-items-start gap-3 mb-3"><span class="btn btn-danger btn-icon disabled">'.icon('trash3').'</span><div><div class="fw-semibold text-danger">'.h(T('delete_collection_everywhere')).'</div><div class="small text-muted mt-1">'.h(T('delete_collection_everywhere_hint')).'</div></div></div>'.$delete.'</div></div>';
    return modal($mid,T('collection_actions'),$body,'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button>','modal-md');
}
function clean_files_modal(){
    $all=list_files('unused');
    $rows=array_values(array_filter($all,fn($f)=>!empty($f['id'])&&empty($f['global_orphan'])&&(int)($f['project_id']??0)===current_project_id()));
    $orphans=array_values(array_filter($all,fn($f)=>!empty($f['global_orphan'])));
    $size=array_sum(array_map(fn($f)=>(int)($f['size']??0),$rows));
    $render=function($items,$empty){$list='<div class="list-group list-group-flush border rounded-4 overflow-hidden mb-3">';foreach($items as $f)$list.='<div class="list-group-item d-flex justify-content-between gap-3"><span class="text-truncate">'.h($f['name']??$f['file']).'</span><span class="text-muted text-nowrap">'.h(fmt_size((int)($f['size']??0))).'</span></div>';if(!$items)$list.='<div class="list-group-item text-muted">'.h($empty).'</div>';return $list.'</div>';};
    $body='<div class="alert alert-info">'.h(T('cleanup_project_only')).'</div><h6>'.h(T('cleanup_preview')).'</h6>'.$render($rows,T('no_files')).'<div class="d-flex justify-content-between fw-semibold mb-3"><span>'.h(T('cleanup_total')).'</span><span>'.h(fmt_size($size)).'</span></div>';
    if($orphans)$body.='<h6 class="mt-4">'.h(T('global_orphan')).'</h6>'.$render($orphans,T('no_files')).'<div class="form-text mb-3">'.h(T('cleanup_project_only')).'</div>';
    $body.='<div class="alert alert-warning mb-0">'.h(T('cleanup_consequence')).'</div>';
    return form_modal('cleanFilesModal',T('clean_files'),'clean_files',$body,'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger" '.(!$rows?'disabled':'').'>'.icon('trash3').' '.h(T('move_to_trash')).'</button>');
}
function fmt_size($b){$b=(int)$b;$u=['B','KB','MB','GB'];for($i=0;$b>=1024&&$i<count($u)-1;$i++)$b/=1024;return round($b,$i?1:0).' '.$u[$i];}

function dashboardPage(){
    $pid=current_project_id();$project=current_project();$metrics=dashboard_metrics($pid);$stats=array_intersect_key($metrics,array_flip(['collections','entries','published','files']));$recent=recent_entries_get();$favIds=favorite_ids();$favs=[];foreach($favIds as $id)if($c=col($id))$favs[]=$c;
    $sections=$metrics['sections'];$forms=$metrics['forms'];$submissions=$metrics['submissions'];$fileBytes=$metrics['file_bytes'];
    $privateCollections=$metrics['private_collections'];$privateSections=$metrics['private_sections'];$privateForms=$metrics['private_forms'];$activeForms=$metrics['active_forms'];$inactiveForms=max(0,$forms-$activeForms);
    $submissionNew=$metrics['submission_new'];$submissionRead=$metrics['submission_read'];$submissionSpam=$metrics['submission_spam'];$collectionsWithEntries=$metrics['collections_with_entries'];
    $topCollections=all('SELECT c.id,c.n,c.s,c.m,c.access_mode,COUNT(e.id) AS entry_count FROM c LEFT JOIN e ON e.cid=c.id WHERE c.pid=? AND c.parent_cid IS NULL GROUP BY c.id,c.n,c.s,c.m,c.access_mode ORDER BY entry_count DESC,c.n ASC LIMIT 5',[$pid]);
    $activity=all("SELECT a.*,COALESCE(u.n,u.l,'System') AS actor FROM audit_log a LEFT JOIN users u ON u.id=a.uid WHERE a.pid=? ORDER BY a.id DESC LIMIT 6",[$pid]);
    $pct=function($value,$total){$total=(int)$total;$value=(int)$value;return $total>0?max(0,min(100,(int)round($value/$total*100))):0;};
    $fmtDate=function($value){if(!$value)return '—';$ts=strtotime((string)$value);return $ts?date('d.m.Y H:i',$ts):(string)$value;};
    $draftEntries=max(0,(int)$stats['entries']-(int)$stats['published']);
    $publishRate=$pct((int)$stats['published'],(int)$stats['entries']);
    $filledCollectionsRate=$pct($collectionsWithEntries,(int)$stats['collections']);
    $activeFormsRate=$pct($activeForms,$forms);
    $publicTotal=max(0,(int)$stats['collections']-$privateCollections)+max(0,$sections-$privateSections)+max(0,$forms-$privateForms);
    $privateTotal=$privateCollections+$privateSections+$privateForms;
    $projectCreated=(string)($project['ca']??'');$projectUpdated=(string)($project['ua']??'');$projectAge=$projectCreated?max(0,(int)floor((time()-strtotime($projectCreated))/86400)):0;
    $statusLabel=$publishRate>=70?T('content_ready'):T('content_in_progress');
    $attention=[];
    if($submissionNew>0)$attention[]=['tone'=>'warning','icon'=>'inbox','title'=>T('moderation_queue'),'sub'=>T('new_status'),'count'=>$submissionNew,'href'=>can_forms()?U(['forms'=>1]):U(['overview'=>1])];
    if($draftEntries>0)$attention[]=['tone'=>'warning','icon'=>'pencil-square','title'=>T('unpublished_entries'),'sub'=>T('draft'),'count'=>$draftEntries,'href'=>U(['collections'=>1])];
    if($inactiveForms>0)$attention[]=['tone'=>'danger','icon'=>'pause-circle','title'=>T('inactive_forms_total'),'sub'=>T('forms'),'count'=>$inactiveForms,'href'=>can_forms()?U(['forms'=>1,'status'=>'inactive']):U(['overview'=>1])];
    if(!$favs&&(int)$stats['collections']>0)$attention[]=['tone'=>'warning','icon'=>'star','title'=>T('configure_favorites'),'sub'=>T('favorite_collections'),'count'=>0,'href'=>U(['collections'=>1])];
    if(!$attention)$attention[]=['tone'=>'success','icon'=>'check2-circle','title'=>T('healthy'),'sub'=>T('content_ready'),'count'=>0,'href'=>U(['overview'=>1])];
    $progress=function($label,$value,$total,$tone='var(--ui-blue)')use($pct){$percent=$pct($value,$total);return '<div class="ov-progress"><div class="ov-progress-head"><span>'.h($label).'</span><strong>'.(int)$value.' / '.(int)$total.' · '.$percent.'%</strong></div><div class="ov-progress-track"><div class="ov-progress-fill" data-pd-progress="'.$percent.'" data-pd-tone="'.h($tone).'"></div></div></div>';};
    $h=page_head(T('overview'),h(T('active_project')).': '.h($project['n']??'—'),'',T('dashboard'),false);
    $h.='<div class="overview-premium">';
    $h.='<section class="ov-panel ov-project"><div class="ov-project-top"><div><div class="ios-kicker">'.h(T('project_summary')).'</div><h2 class="ov-project-name">'.h($project['n']??'—').'</h2><p class="ov-project-sub">'.h(T('project_summary_hint')).'</p></div><div class="ov-project-status"><span class="ov-chip is-success">'.icon('heart-pulse').' '.h($statusLabel).'</span><span class="ov-chip">'.icon('database').' '.h(strtoupper(db_driver())).'</span><span class="ov-chip">'.icon('globe2').' '.h(T('public_resources')).': '.$publicTotal.'</span><span class="ov-chip">'.icon('lock-fill').' '.h(T('private_resources')).': '.$privateTotal.'</span></div></div><div class="ov-project-meta"><div class="ov-meta"><span class="ov-meta-label">'.h(T('project_slug')).'</span><span class="ov-meta-value"><code>'.h($project['s']??'—').'</code></span></div><div class="ov-meta"><span class="ov-meta-label">'.h(T('project_age')).'</span><span class="ov-meta-value">'.($projectAge?($projectAge.' '.h(T('days'))):'—').'</span></div><div class="ov-meta"><span class="ov-meta-label">'.h(T('project_created_at')).'</span><span class="ov-meta-value">'.h($fmtDate($projectCreated)).'</span></div><div class="ov-meta"><span class="ov-meta-label">'.h(T('project_updated_at')).'</span><span class="ov-meta-value">'.h($fmtDate($projectUpdated)).'</span></div></div></section>';
    $kpis=[
        ['label'=>T('stat_collections'),'value'=>(int)$stats['collections'],'note'=>T('collections_with_entries').': '.$collectionsWithEntries,'icon'=>'collection','href'=>U(['collections'=>1])],
        ['label'=>T('stat_sections'),'value'=>$sections,'note'=>T('public_resources').': '.max(0,$sections-$privateSections),'icon'=>'diagram-3','href'=>U(['groups'=>1])],
        ['label'=>T('stat_entries'),'value'=>(int)$stats['entries'],'note'=>T('draft').': '.$draftEntries,'icon'=>'file-earmark-text','href'=>U(['collections'=>1])],
        ['label'=>T('stat_published'),'value'=>(int)$stats['published'],'note'=>$publishRate.'% '.T('publish_rate'),'icon'=>'check-circle','href'=>U(['collections'=>1])],
        ['label'=>T('stat_forms'),'value'=>$forms,'note'=>T('active').': '.$activeForms,'icon'=>'inbox','href'=>can_forms()?U(['forms'=>1]):U(['overview'=>1])],
        ['label'=>T('stat_submissions'),'value'=>$submissions,'note'=>T('new_status').': '.$submissionNew,'icon'=>'send','href'=>can_forms()?U(['forms'=>1]):U(['overview'=>1])],
        ['label'=>T('stat_files'),'value'=>(int)$stats['files'],'note'=>fmt_size($fileBytes),'icon'=>'folder2-open','href'=>can_files()?U(['files'=>1]):U(['overview'=>1])],
        ['label'=>T('stat_storage'),'value'=>fmt_size($fileBytes),'note'=>T('stat_files').': '.(int)$stats['files'],'icon'=>'hdd-stack','href'=>can_files()?U(['files'=>1]):U(['overview'=>1])],
    ];
    $h.='<div class="ov-kpis">';foreach($kpis as $k)$h.='<div class="ov-panel ov-kpi"><div class="ov-kpi-head"><span class="ov-kpi-label">'.h($k['label']).'</span><span class="ov-icon">'.icon($k['icon']).'</span></div><div class="ov-kpi-value">'.h((string)$k['value']).'</div><div class="ov-kpi-note">'.h($k['note']).'</div></div>';$h.='</div>';
    $publishedPct=$pct((int)$stats['published'],max(1,(int)$stats['entries']));
    $collectionsPct=$pct($collectionsWithEntries,max(1,(int)$stats['collections']));
    $activeFormsPct=$pct($activeForms,max(1,$forms));
    $readPct=$pct($submissionRead,max(1,$submissions));
    $healthAverage=(int)round(($publishedPct+$collectionsPct+$activeFormsPct+$readPct)/4);
    $healthTone=$healthAverage>=75?'check2-circle':($healthAverage>=40?'activity':'exclamation-triangle');
    $healthLabel=$healthAverage>=75?T('healthy'):($healthAverage>=40?T('readiness'):T('needs_attention'));
    $metric=function($label,$value,$total,$percent,$iconName,$tone='var(--ui-blue)',$primary=false){return '<div class="ov-health-metric'.($primary?' is-primary':'').'"><div class="ov-health-metric-top"><div class="ov-health-metric-main"><span class="ov-health-metric-label">'.h($label).'</span><div class="ov-health-metric-value"><strong>'.$percent.'%</strong><span>'.(int)$value.' / '.(int)$total.'</span></div></div><span class="ov-icon">'.icon($iconName).'</span></div><div class="ov-progress-track"><div class="ov-progress-fill" data-pd-progress="'.$percent.'" data-pd-tone="'.h($tone).'"></div></div></div>';};
    $h.='<div class="ov-main"><section class="ov-panel ov-card"><div class="ov-card-head"><div><h2 class="ov-card-title">'.h(T('key_metrics')).'</h2><div class="ov-card-sub">'.h(T('key_metrics_hint')).'</div></div><span class="ov-icon">'.icon('bar-chart').'</span></div><div class="ov-health-grid">'.$metric(T('published'),(int)$stats['published'],max(1,(int)$stats['entries']),$publishedPct,'check-circle','var(--ui-blue)',true).$metric(T('collections_with_entries'),$collectionsWithEntries,max(1,(int)$stats['collections']),$collectionsPct,'collection','#10b981').$metric(T('active_forms_total'),$activeForms,max(1,$forms),$activeFormsPct,'inbox','#0ea5e9').$metric(T('read_status'),$submissionRead,max(1,$submissions),$readPct,'check2-circle','#8b5cf6').'</div><div class="ov-health-summary"><div class="ov-health-summary-main"><span class="ov-icon">'.icon($healthTone).'</span><div class="ov-health-summary-text"><div class="ov-health-summary-title">'.h($healthLabel).'</div><div class="ov-health-summary-sub">'.h(T('project_resources_hint')).'</div></div></div><div class="ov-health-summary-value">'.$healthAverage.'%</div></div><div class="ov-divider"></div><div class="ov-card-head mb-2"><div><h3 class="ov-card-title">'.h(T('top_collections')).'</h3><div class="ov-card-sub">'.h(T('sort_entries')).'</div></div></div><div class="ov-top-list">';
    if($topCollections){foreach($topCollections as $c)$h.='<a class="ov-row" href="'.h(U(['c'=>(int)$c['id']])).'"><span class="ov-row-main"><span class="ov-row-title">'.h($c['n']).'</span><span class="ov-row-sub"><code>'.h($c['s']).'</code> · '.h(T(collection_mode($c))).'</span></span><span class="ov-row-end">'.(int)$c['entry_count'].'</span></a>';}else $h.='<div class="ov-empty">'.h(T('no_collections_yet')).'</div>';
    $h.='</div></section><aside class="ov-side"><section class="ov-panel ov-card"><div class="ov-card-head"><div><h2 class="ov-card-title">'.h(T('needs_attention')).'</h2><div class="ov-card-sub">'.h(T('dashboard_tip')).'</div></div><span class="ov-icon">'.icon('exclamation-triangle').'</span></div><div class="ov-attention">';foreach($attention as $item)$h.='<a class="ov-attention-item is-'.h($item['tone']).'" href="'.h($item['href']).'"><span class="ov-attention-icon">'.icon($item['icon']).'</span><span class="ov-attention-body"><span class="ov-attention-title">'.h($item['title']).'</span><span class="ov-attention-sub">'.h($item['sub']).'</span></span><span class="ov-attention-count">'.(int)$item['count'].'</span></a>';$h.='</div></section><section class="ov-panel ov-card"><div class="ov-card-head"><div><h2 class="ov-card-title">'.h(T('favorite_collections')).'</h2><div class="ov-card-sub">'.h(T('favorites_hint')).'</div></div><span class="ov-icon">'.icon('star-fill').'</span></div><div class="ov-list">';if($favs){foreach(array_slice($favs,0,4) as $c){$count=(int)q('SELECT COUNT(*) FROM e WHERE cid=?',[(int)$c['id']])->fetchColumn();$h.='<a class="ov-row" href="'.h(U(['c'=>$c['id']])).'"><span class="ov-row-main"><span class="ov-row-title">'.h($c['n']).'</span><span class="ov-row-sub"><code>'.h($c['s']).'</code></span></span><span class="ov-row-end">'.$count.'</span></a>';}}else $h.='<div class="ov-empty">'.h(T('no_favorites')).'</div>';$h.='</div></section></aside></div>';
    $h.='<div class="ov-bottom"><section class="ov-panel ov-card"><div class="ov-card-head"><div><h2 class="ov-card-title">'.h(T('latest_content')).'</h2><div class="ov-card-sub">'.h(T('latest_content_hint')).'</div></div><span class="ov-icon">'.icon('clock-history').'</span></div><div class="ov-list">';$recentItems=[];foreach($recent as $r){$cc=col((int)$r['cid']);if(!$cc)continue;$recentItems[]='<a class="ov-row" href="'.h(U(['c'=>$cc['id'],'entry'=>$r['id']])).'"><span class="ov-row-main"><span class="ov-row-title">'.h($r['title']).'</span><span class="ov-row-sub">'.h($cc['n']).' · <code>'.h($r['slug']).'</code></span></span><span class="ov-row-end">'.(!empty($r['time'])?h(date('d.m H:i',(int)$r['time'])):'').'</span></a>';}$h.=($recentItems?implode('',$recentItems):'<div class="ov-empty">'.h(T('no_recent')).'</div>').'</div></section><section class="ov-panel ov-card"><div class="ov-card-head"><div><h2 class="ov-card-title">'.h(T('recent_activity')).'</h2><div class="ov-card-sub">'.h(T('activity_hint')).'</div></div><span class="ov-icon">'.icon('activity').'</span></div><div class="ov-activity">';if($activity){foreach($activity as $a){$title=(string)($a['summary']?:$a['action']);$metaParts=[(string)($a['actor']?:'System')];if(!empty($a['entity']))$metaParts[]=(string)$a['entity'];$metaParts[]=$fmtDate($a['ca']??'');$h.='<div class="ov-activity-item"><span class="ov-activity-dot"></span><div><div class="ov-activity-title">'.h($title).'</div><div class="ov-activity-meta">'.h(implode(' · ',array_filter($metaParts,fn($x)=>$x!==''))).'</div></div></div>';}}else $h.='<div class="ov-empty">'.h(T('no_activity')).'</div>';$h.='</div></section></div></div>';return $h;
}
function collectionsPage(){
    $pid=current_project_id();$qv=trim((string)($_GET['q']??''));$type=in_array((string)($_GET['type']??''),['single','multiple'],true)?(string)$_GET['type']:'';$section=(string)($_GET['section']??'');$sort=in_array((string)($_GET['sort']??''),['name','type','entries','updated'],true)?(string)$_GET['sort']:'name';$dir=isset($_GET['dir'])?request_dir():($sort==='name'?'asc':'desc');$page=max(1,(int)($_GET['page']??1));$per=min(100,max(10,(int)($_GET['per']??25)));$where=['c.pid=?','c.parent_cid IS NULL'];$params=[$pid];
    if($qv!==''){$where[]='(c.n LIKE ? OR c.s LIKE ? OR c.d LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}if($type!==''){$where[]='c.m=?';$params[]=$type;}if($section==='none'){$where[]='NOT EXISTS(SELECT 1 FROM gc gx JOIN g gg ON gg.id=gx.gid WHERE gx.cid=c.id AND gg.pid=?)';$params[]=$pid;}elseif(ctype_digit($section)&&(int)$section>0){$g=group_row((int)$section);if($g){$where[]='EXISTS(SELECT 1 FROM gc gx WHERE gx.cid=c.id AND gx.gid=?)';$params[]=(int)$g['id'];}else $where[]='1=0';}
    $whereSql=implode(' AND ',$where);$allTotal=(int)q('SELECT COUNT(*) FROM c WHERE c.pid=? AND c.parent_cid IS NULL',[$pid])->fetchColumn();$total=(int)q('SELECT COUNT(*) FROM c WHERE '.$whereSql,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$map=['name'=>'c.n','type'=>'c.m','entries'=>'entry_count','updated'=>'c.ua'];$rows=all('SELECT c.*,COALESCE(ec.entry_count,0) AS entry_count FROM c LEFT JOIN (SELECT cid,COUNT(*) entry_count FROM e GROUP BY cid) ec ON ec.cid=c.id WHERE '.$whereSql.' ORDER BY '.$map[$sort].' '.strtoupper($dir).',c.id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);
    $actions=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#collectionNewModal">'.icon('plus-lg').' '.h(T('new_collection')).'</button>':'';$h=page_head(T('collections'),h(T('collection_independent_hint')),$actions,T('content_nav'),false);
    $sectionOpts=[''=>T('section_all'),'none'=>T('without_section')];foreach(groups() as $g)$sectionOpts[(int)$g['id']]=$g['n'];$typeOpts=[''=>T('type_all'),'multiple'=>T('multiple'),'single'=>T('single')];$sortOpts=['name'=>T('sort_name'),'updated'=>T('sort_updated'),'entries'=>T('sort_entries'),'type'=>T('type')];
    $dirOpts=['asc'=>T('sort_asc_short'),'desc'=>T('sort_desc_short')];$hasFilters=$qv!==''||$type!==''||$section!==''||$sort!=='name'||$dir!=='asc';
    $tools='<div class="collection-filter-panel mb-3"><form method="get"><input type="hidden" name="collections" value="1"><div class="row g-3"><div class="col-12"><label class="form-label" for="collectionsSearch">'.h(T('search')).'</label><input class="form-control" id="collectionsSearch" type="search" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"></div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('type')).'</label>'.select_inline('type',$typeOpts,$type).'</div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('section_filter')).'</label>'.select_inline('section',$sectionOpts,$section).'</div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('sort_by')).'</label>'.select_inline('sort',$sortOpts,$sort).'</div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('order')).'</label>'.select_inline('dir',$dirOpts,$dir).'</div></div><div class="collection-filter-footer"><div class="collection-filter-summary"><div class="hint small text-muted">'.icon('arrows-move').' '.h(T('drag_collection_to_section')).'</div><span class="collection-filter-count badge text-bg-light">'.h(sprintf(T('collections_found'),$total)).'</span></div><div class="collection-filter-actions">'.($hasFilters?'<a class="btn btn-outline-secondary" href="'.h(U(['collections'=>1])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>':'').'<button class="btn btn-primary">'.icon('funnel').' '.h(T('apply')).'</button></div></div></form></div>';
    $extra='';if(can_schema())$extra='<div class="ios-surface p-3 mt-3"><form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">'.token().'<input type="hidden" name="_a" value="import_col_schema"><div class="flex-grow-1"><label class="form-label">'.h(T('import_schema')).'</label><input class="form-control" type="file" name="schema" accept="application/json,.json" required></div><button class="btn btn-secondary">'.icon('upload').' '.h(T('import_schema')).'</button></form></div>';
    if(!$rows){
        if($allTotal===0&&!$hasFilters){$cta=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#collectionNewModal">'.icon('plus-lg').' '.h(T('new_collection')).'</button>':'';return $h.empty_state(T('no_collections'),T('collection_independent_hint'),$cta).$extra;}
        $reset='<a class="btn btn-primary" href="'.h(U(['collections'=>1])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>';
        $empty='<div class="text-center px-3 py-5"><div class="display-6 text-muted mb-3">'.icon('search').'</div><h2 class="h4 mb-2">'.h(T('no_results')).'</h2><p class="text-muted mb-4">'.h(T('no_search_results')).'</p>'.$reset.'</div>';
        return $h.'<div class="ios-surface p-3 p-lg-4">'.$tools.$empty.'</div>'.$extra;
    }
    $table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr><th>'.h(T('name')).'</th><th>'.h(T('type')).'</th><th>'.h(T('access_mode')).'</th><th>'.h(T('entry_count')).'</th><th>'.h(T('content_sections')).'</th><th>'.h(T('updated')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $c){
        $cid=(int)$c['id'];$groups=collection_groups($cid);$sections=$groups?'<div class="collection-sections">'.implode('',array_map(fn($g)=>'<a class="badge text-bg-light" href="'.h(U(['group'=>(int)$g['id']])).'">'.h($g['n']).'</a>',$groups)).'</div>':'<a class="badge text-bg-light" href="'.h(U(['collections'=>1,'section'=>'none'])).'">'.h(T('without_section')).'</a>';
        $open='<a class="btn btn-primary btn-sm" href="'.h(U(['c'=>$cid])).'">'.icon('box-arrow-in-right').' '.h(T('open_entries')).'</a>';
        $items=[];
        if(can_schema()){
            $items[]=dd_link(T('edit_collection'),U(['collections'=>1,'edit_col'=>$cid]),'pencil');
            $items[]=dd_link(T('fields'),U(['c'=>$cid,'fields'=>1]),'list-check');
            $items[]=dd_link(T('add_to_section'),U(['collections'=>1,'edit_col'=>$cid]),'folder-plus');
            $items[]=dd_form(T('clone_collection'),'clone_col','<input type="hidden" name="id" value="'.$cid.'">','copy');
            $items[]=dd_link(T('export_schema'),U(['export_schema'=>$cid]),'download');
            $items[]='<li><hr class="dropdown-divider"></li>';
            $items[]=universal_delete_button(T('delete_collection'),'del_col',['id'=>$cid],T('delete_collection'),collection_delete_message($c),true,'dropdown-item rounded-3 d-flex align-items-center gap-2','trash3',T('delete_collection'));
        }
        $updated=h($c['ua']??$c['ca']);
        $table.='<tr '.(can_schema()?'draggable="true" data-collection-drag-id="'.$cid.'"':'').' ><td><a class="link-dark fw-semibold" href="'.h(U(['c'=>$cid])).'">'.h($c['n']).'</a><small class="d-block text-muted"><code>'.h($c['s']).'</code></small></td><td><span class="badge text-bg-light">'.h(T(collection_mode($c))).'</span></td><td>'.access_badge($c).'</td><td>'.(int)$c['entry_count'].'</td><td>'.$sections.'</td><td><span class="text-nowrap small text-muted">'.$updated.'</span></td><td class="text-end"><div class="collection-table-actions">'.$open.($items?dd_menu($items):'').'</div></td></tr>';
    }
    $table.='</tbody></table>';return $h.table_wrap($table,$tools,pager_html($m)).$extra;
}
function groupsPage(){
    $pid=current_project_id();
    $qv=trim((string)($_GET['q']??''));
    $access=in_array((string)($_GET['access']??''),['public','private'],true)?(string)$_GET['access']:'';
    $sort=in_array((string)($_GET['sort']??''),['manual','name','collections','updated'],true)?(string)$_GET['sort']:'manual';
    $defaultDir=in_array($sort,['manual','name'],true)?'asc':'desc';
    $dir=isset($_GET['dir'])?request_dir():$defaultDir;
    $where=['g.pid=?'];$params=[$pid];
    if($qv!==''){$where[]='(g.n LIKE ? OR g.s LIKE ? OR g.d LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}
    if($access!==''){$where[]="COALESCE(NULLIF(g.access_mode,''),'public')=?";$params[]=$access;}
    $whereSql=implode(' AND ',$where);
    $orderSql=match($sort){
        'name'=>'g.n '.strtoupper($dir).',g.id DESC',
        'collections'=>'collection_count '.strtoupper($dir).',g.n ASC,g.id DESC',
        'updated'=>'g.ua '.strtoupper($dir).',g.id DESC',
        default=>'g.o ASC,g.n ASC,g.id ASC',
    };
    $rows=all('SELECT g.*,COALESCE(gcc.collection_count,0) AS collection_count FROM g LEFT JOIN (SELECT gid,COUNT(*) collection_count FROM gc GROUP BY gid) gcc ON gcc.gid=g.id WHERE '.$whereSql.' ORDER BY '.$orderSql,$params);
    $allCount=(int)q('SELECT COUNT(*) FROM g WHERE pid=?',[$pid])->fetchColumn();
    $edit=isset($_GET['gid'])?group_row((int)$_GET['gid']):null;
    $actions=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">'.icon('plus-lg').' '.h(T('new_group')).'</button>':'';
    $h=page_head(T('content_sections'),h(T('section_shortcut_hint')),$actions,T('content_nav'),false);
    if(!$allCount){$cta=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">'.icon('plus-lg').' '.h(T('create_first_section')).'</button>':'';return $h.empty_state(T('content_sections'),T('group_api_hint'),$cta).group_modal($edit);}

    $accessOpts=[''=>T('all_access_modes'),'public'=>T('access_public'),'private'=>T('access_private')];
    $sortOpts=['manual'=>T('sort_manual'),'name'=>T('sort_name'),'collections'=>T('sort_collections_count'),'updated'=>T('sort_updated')];
    $dirOpts=['asc'=>T('sort_asc'),'desc'=>T('sort_desc')];
    $hasFilters=($qv!==''||$access!==''||$sort!=='manual'||$dir!=='asc');
    $tools='<div class="content-sections-filter-panel mb-3"><form method="get"><input type="hidden" name="groups" value="1"><div class="mb-3"><label class="form-label">'.h(T('search')).'</label><input class="form-control" type="search" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"></div><div class="row g-3"><div class="col-12 col-md-4"><label class="form-label">'.h(T('access')).'</label><select class="form-select" name="access">';
    foreach($accessOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$access===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div><div class="col-12 col-md-4"><label class="form-label">'.h(T('sort')).'</label><select class="form-select" name="sort">';
    foreach($sortOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$sort===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div><div class="col-12 col-md-4"><label class="form-label">'.h(T('sort_direction')).'</label><select class="form-select" name="dir">';
    foreach($dirOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$dir===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div></div><div class="content-sections-filter-footer"><div class="content-sections-filter-summary"><span class="fw-semibold">'.h(sprintf(T('sections_found'),count($rows))).'</span>'.(can_schema()&&$qv===''&&$access===''&&$sort==='manual'?'<span class="small text-muted">'.icon('grip-vertical').' '.h(T('drag_to_sort')).'</span>':'').'</div><div class="content-sections-filter-actions"><button class="btn btn-primary">'.icon('funnel').' '.h(T('apply')).'</button>'.($hasFilters?'<a class="btn btn-light" href="'.h(U(['groups'=>1])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>':'').'</div></div></form></div>';
    $h.=$tools;
    if(!$rows){return $h.empty_state(T('no_search_results'),T('section_shortcut_hint'),$hasFilters?'<a class="btn btn-light" href="'.h(U(['groups'=>1])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>':'').group_modal($edit);}

    $canDrag=can_schema()&&$qv===''&&$access===''&&$sort==='manual';
    $h.='<div class="row g-3 js-sortable-groups" '.($canDrag?'data-sort-action="reorder_groups"':'').'>';
    foreach($rows as $g){
        $gid=(int)$g['id'];$count=(int)($g['collection_count']??0);$endpoint=U(['api'=>'group','g'=>$g['s'],'lang'=>default_content_lang()]);
        $items=[];
        if(can_schema())$items[]=dd_link(T('edit_group'),U(['groups'=>1,'gid'=>$gid]),'pencil');
        $items[]='<li><button type="button" class="dropdown-item rounded-3 d-flex align-items-center gap-2 js-copy" data-copy="'.h($endpoint).'">'.icon('copy').'<span>'.h(T('copy_endpoint')).'</span></button></li>';
        $items[]=dd_link(T('api'),$endpoint,'braces','_blank');
        if(can_schema())$items[]=universal_delete_button(T('delete_group'),'del_group',['id'=>$gid],T('delete_group'),T('delete_group_q')."

".T('collections').': '.$count,true,'dropdown-item','trash3',T('delete_group'));
        $menu=dd_menu($items);
        $h.='<div class="col-12 col-xl-6" '.($canDrag?'draggable="true" data-sort-id="'.$gid.'"':'').'><div class="content-section-card h-100 d-flex flex-column section-drop" '.(can_schema()?'data-group-drop-id="'.$gid.'"':'').'><div class="content-section-card-head"><div class="content-section-card-main">'.($canDrag?'<button type="button" class="btn btn-light btn-icon drag-handle" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button>':'').'<div class="content-section-card-title"><h2><a href="'.h(U(['group'=>$gid])).'">'.h($g['n']).'</a></h2><p>'.h($g['d']?:T('section_shortcut_hint')).'</p></div></div>'.$menu.'</div><div class="content-section-card-meta"><span class="badge text-bg-light">'.h(T('collections')).': '.$count.'</span>'.access_badge($g).'<span class="content-section-card-slug">'.h($g['s']).'</span></div><div class="content-section-card-endpoint"><code>'.h($endpoint).'</code>'.endpoint_copy_button($endpoint,'').'</div><div class="content-section-card-actions"><a class="btn btn-primary" href="'.h(U(['group'=>$gid])).'">'.icon('box-arrow-in-right').' '.h(T('open')).'</a></div></div></div>';
    }
    $h.='</div>';return $h.group_modal($edit);
}
function group_workspace_header($g,$endpoint,$count){
    $gid=(int)$g['id'];
    $back=workspace_back_icon(U(['groups'=>1]));
    $meta='<span class="badge text-bg-light">'.h(T('content_sections')).'</span><span class="badge text-bg-light">'.h(T('collections')).': '.$count.'</span>';
    $main='<div class="group-workspace-main">'.$back.'<div class="group-workspace-title"><div class="ios-kicker">'.h(T('content_sections')).'</div><h1>'.h($g['n']).'</h1><div class="ios-sub mt-2">'.h(T('group_api_hint')).'</div><div class="group-workspace-meta">'.$meta.'</div><div class="endpoint-box mt-3"><code>'.h($endpoint).'</code>'.endpoint_copy_button($endpoint,'').'</div></div></div>';
    $actions='';
    if(can_schema())$actions.='<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollectionChoiceModal'.$gid.'">'.icon('plus-lg').' '.h(T('add_collection')).'</button>';
    $items=[];
    if(can_schema())$items[]=dd_modal(T('edit_group'),'#groupModal','pencil');
    $items[]=dd_link(T('api'),$endpoint,'braces','_blank');
    if(can_schema())$items[]=universal_delete_button(T('delete_group'),'del_group',['id'=>$gid],T('delete_group'),T('delete_group_q')."

".T('collections').': '.group_collection_count($gid),true,'dropdown-item','trash3',T('delete_group'));
    if($items){
        static $groupHeadMenu=0;$groupHeadMenu++;$menuId='groupHeadMenu'.$groupHeadMenu;
        $actions.='<div class="dropdown"><button type="button" class="btn btn-light dropdown-toggle" id="'.h($menuId).'" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false">'.icon('three-dots').' '.h(T('actions')).'</button><ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-4 p-2" aria-labelledby="'.h($menuId).'">'.implode('',$items).'</ul></div>';
    }
    return '<div class="group-workspace-head">'.$main.($actions?'<div class="group-workspace-actions">'.$actions.'</div>':'').'</div>';
}
function groupWorkspacePage($g){
    $gid=(int)$g['id'];$collections=group_cols($gid);$endpoint=U(['api'=>'group','g'=>$g['s'],'lang'=>default_content_lang()]);
    $h=group_workspace_header($g,$endpoint,count($collections));
    if(!$collections){
        $cta=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollectionChoiceModal'.$gid.'">'.icon('plus-lg').' '.h(T('add_collection')).'</button>':'';
        $h.=collection_entries_empty_state(T('no_collections'),T('section_shortcut_hint'),$cta);
    }else{
        $dragHint=can_schema()?'<div class="small text-muted">'.icon('grip-vertical').' '.h(T('drag_collection_to_section')).'</div>':'';
        $h.='<div class="ios-surface group-collections-card"><div class="group-collections-card-head"><div><h2 class="h4 mb-1">'.h(T('section_collections')).'</h2><div class="text-muted small">'.h(sprintf(T('collections_found'),count($collections))).'</div></div>'.$dragHint.'</div><div class="d-grid gap-3 js-group-sort" '.(can_schema()?'data-sort-action="reorder_group_collections" data-sort-gid="'.$gid.'"':'').'>';
        foreach($collections as $c){
            $cid=(int)$c['id'];$count=(int)q('SELECT COUNT(*) FROM e WHERE cid=?',[$cid])->fetchColumn();
            $items=[];
            if(can_schema())$items[]=dd_link(T('edit_collection'),U(['collections'=>1,'edit_col'=>$cid]),'pencil');
            if(can_schema())$items[]=dd_modal(T('collection_actions'),'#'.collection_section_action_id($gid,$cid),'folder-minus',true);
            $menu=dd_menu($items);
            $h.='<div class="section-collection-row" '.(can_schema()?'draggable="true" data-sort-id="'.$cid.'"':'').'><div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3"><div class="section-collection-row-main">'.(can_schema()?'<button class="btn btn-light btn-icon drag-handle" type="button" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button>':'').'<div class="section-collection-row-body"><div class="section-collection-row-title"><a class="link-dark" href="'.h(U(['c'=>$cid])).'">'.h($c['n']).'</a><span class="badge text-bg-light">'.h(T(collection_mode($c))).'</span>'.access_badge($c).'</div><div class="section-collection-row-meta"><code>'.h($c['s']).'</code><span>·</span><span>'.h(T('entry_count')).': '.$count.'</span></div></div></div><div class="section-collection-row-actions"><a class="btn btn-primary btn-sm" href="'.h(U(['c'=>$cid])).'">'.icon('box-arrow-in-right').' '.h(T('open')).'</a>'.$menu.'</div></div></div>';
        }
        $h.='</div></div>';
    }
    return $h.(can_schema()?add_collection_choice_modal($g).add_existing_collection_modal($g).group_modal($g):'');
}
function add_collection_choice_modal($g){$gid=(int)$g['id'];$body='<div class="d-grid gap-3"><button class="btn btn-primary btn-lg" type="button" data-bs-target="#addExistingCollectionModal'.$gid.'" data-bs-toggle="modal">'.icon('link-45deg').' '.h(T('add_existing_collection')).'</button><a class="btn btn-secondary btn-lg" href="'.h(U(['group'=>$gid,'new_col'=>1])).'">'.icon('plus-lg').' '.h(T('create_new_collection')).'</a></div><div class="small text-muted mt-3">'.h(T('section_shortcut_hint')).'</div>';return modal('addCollectionChoiceModal'.$gid,T('add_collection'),$body,'<button class="btn btn-light" data-bs-dismiss="modal">'.h(T('close')).'</button>');}
function add_existing_collection_modal($g){$gid=(int)$g['id'];$selected=group_col_ids($gid);$available=array_values(array_filter(cols(),fn($c)=>!in_array((int)$c['id'],$selected,true)));$body='<input type="hidden" name="gid" value="'.$gid.'"><input type="hidden" name="_return" value="'.h(U(['group'=>$gid])).'"><input type="search" class="form-control mb-3 js-collection-search" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"><div class="list-group">';foreach($available as $c){$cid=(int)$c['id'];$body.='<label class="list-group-item d-flex gap-3 align-items-start js-collection-item" data-search="'.h(mb_strtolower($c['n'].' '.$c['s'].' '.$c['d'])).'"><input class="form-check-input mt-1" type="checkbox" name="collections[]" value="'.$cid.'"><span><span class="fw-semibold">'.h($c['n']).'</span><small class="d-block text-muted"><code>'.h($c['s']).'</code> · '.h(T(collection_mode($c))).'</small></span></label>';}$body.='</div>';if(!$available)$body='<div class="alert alert-info mb-0">'.h(T('no_available_collections')).'</div>';$footer='<button type="button" class="btn btn-light" data-bs-target="#addCollectionChoiceModal'.$gid.'" data-bs-toggle="modal">'.h(T('back')).'</button><button class="btn btn-primary" '.(!$available?'disabled':'').'>'.icon('link-45deg').' '.h(T('add_existing_collection')).'</button>';return form_modal('addExistingCollectionModal'.$gid,T('add_existing_collection'),'add_collection_to_group',$body,$footer);}
function group_modal($g=null){
    $id=(int)($g['id']??0);$body='<input type="hidden" name="id" value="'.$id.'">'.resource_i18n_fields($g,'groupModal'.$id).inp('s',T('slug'),$g['s']??'','text',['data-slug-target'=>'1']);$body.=access_control_html($g,'group');$delete=$id?universal_delete_button(T('delete_group'),'del_group',['id'=>$id],T('delete_group'),T('delete_group_q'),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('groupModal',$id?T('edit_group'):T('new_group'),'group',$body,$footer,'modal-xl');
}
function manage_group_collections_modal($g,$mid){
    $gid=(int)$g['id'];
    $selected=group_col_ids($gid);
    $selPos=array_flip($selected);
    $all=cols();
    usort($all,function($a,$b)use($selPos){
        $aid=(int)$a['id'];$bid=(int)$b['id'];$as=isset($selPos[$aid]);$bs=isset($selPos[$bid]);
        if($as!==$bs)return $as?-1:1;
        if($as&&$bs)return $selPos[$aid]<=>$selPos[$bid];
        $ao=(int)($a['o']??0);$bo=(int)($b['o']??0);if($ao!==$bo)return $ao<=>$bo;
        $n=strcasecmp((string)$a['n'],(string)$b['n']);return $n?:($aid<=>$bid);
    });
    $items='';$i=0;
    foreach($all as $c){
        $cid=(int)$c['id'];$is=isset($selPos[$cid]);
        $search=mb_strtolower(($c['n']??'').' '.($c['s']??'').' '.($c['d']??''));
        $items.='<div class="list-group-item d-flex align-items-center gap-3 js-group-collection-item" data-original="'.$i.'" data-search="'.h($search).'">'
            .'<input class="form-check-input flex-shrink-0 js-group-collection-check" type="checkbox" name="collections[]" value="'.$cid.'" '.($is?'checked':'').'>'
            .'<div class="flex-grow-1 min-w-0"><div class="d-flex flex-wrap align-items-center gap-2"><span class="fw-semibold">'.h($c['n']).'</span><span class="badge rounded-pill text-bg-light">'.h(T(collection_mode($c))).'</span>'.($is?'<span class="badge rounded-pill text-bg-success js-selected-badge">'.h(T('selected_collections')).'</span>':'<span class="badge rounded-pill text-bg-success js-selected-badge d-none">'.h(T('selected_collections')).'</span>').'</div><div class="small text-muted"><code>'.h($c['s']).'</code>'.(($c['d']??'')?' · '.h($c['d']):'').'</div></div>'
            .'<a class="btn btn-outline-dark btn-icon flex-shrink-0" data-cms-remember-back="1" href="'.h(U(['c'=>$cid])).'" title="'.h(T('go_to_collection')).'">'.icon('box-arrow-in-right').'</a>'
            .'</div>';
        $i++;
    }
    if(!$items)$items='<div class="alert alert-warning rounded-4 border-0 mb-0">'.h(T('no_collections')).'</div>';
    $body='<input type="hidden" name="id" value="'.$gid.'"><div class="alert alert-info rounded-4 border-0 py-2 small">'.h(T('remove_from_section_hint')).'</div><div class="mb-3"><input type="search" class="form-control rounded-pill js-group-collections-search" placeholder="'.h(T('search')).'"></div><div class="list-group rounded-4 overflow-hidden js-group-collections-list">'.$items.'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save_collections')).'</button>';
    return form_modal($mid,T('manage_collections'),'group_cols',$body,$footer,'modal-lg modal-fullscreen-lg-down');
}
function delete_group_modal($g,$mid=null){
    $mid=$mid?:'deleteGroupModal'.(int)$g['id'];
    $body='<input type="hidden" name="id" value="'.(int)$g['id'].'"><p>'.h(T('delete_group_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($g['n']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_group'),'del_group',$body,$footer);
}

function filesPage(){
    $allowed=['active','trash'];if(is_admin_user())$allowed=array_merge($allowed,['global_trash','orphans']);$tab=in_array((string)($_GET['tab']??'active'),$allowed,true)?(string)($_GET['tab']??'active'):'active';$qv=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$per=min(100,max(10,(int)($_GET['per']??25)));$sort=request_sort(['name','size','updated'],'updated');$dir=request_dir();
    $allRows=list_files($tab);if($qv!=='')$allRows=array_values(array_filter($allRows,fn($f)=>str_contains(mb_strtolower(($f['name']??'').' '.($f['file']??'').' '.($f['ext']??'').' '.($f['origin_project_name']??'')),mb_strtolower($qv))));
    usort($allRows,function($a,$b)use($sort,$dir){$map=['name'=>'name','size'=>'size','updated'=>'updated_at'];$k=$map[$sort];$v=($a[$k]??'')<=>($b[$k]??'');return $dir==='asc'?$v:-$v;});$m=pagination_meta(count($allRows),$page,$per);$rows=array_slice($allRows,$m['offset'],$m['per']);
    $actions=$tab==='active'?'<button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cleanFilesModal">'.icon('trash3').' '.h(T('clean_files')).'</button>':'';$sub=$tab==='global_trash'?T('global_file_trash'):($tab==='orphans'?T('global_orphan_files'):T($tab==='trash'?'file_trash':'files'));$h=page_head(T('files'),h($sub),$actions);
    $tabs='<ul class="nav nav-pills gap-2 mb-3"><li class="nav-item"><a class="nav-link '.($tab==='active'?'active':'').'" href="'.h(U(['files'=>1,'tab'=>'active'])).'">'.h(T('files')).'</a></li><li class="nav-item"><a class="nav-link '.($tab==='trash'?'active':'').'" href="'.h(U(['files'=>1,'tab'=>'trash'])).'">'.h(T('file_trash')).'</a></li>';if(is_admin_user())$tabs.='<li class="nav-item"><a class="nav-link '.($tab==='global_trash'?'active':'').'" href="'.h(U(['files'=>1,'tab'=>'global_trash'])).'">'.h(T('global_file_trash')).'</a></li><li class="nav-item"><a class="nav-link '.($tab==='orphans'?'active':'').'" href="'.h(U(['files'=>1,'tab'=>'orphans'])).'">'.h(T('global_orphan_files')).'</a></li>';$tabs.='</ul>';
    if($tab==='orphans')$h.='<div class="alert alert-warning">'.h(T('global_orphan_hint')).'</div>';
    if(!$allRows)return $h.$tabs.empty_state(T('no_files'),$sub,$tab==='active'?'<p class="small text-muted">'.h(T('upload_first_file')).'</p>':'');
    $table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr><th>'.sort_link(T('file'),'name',$sort,$dir).'</th><th>'.h(T('type')).'</th><th>'.sort_link(T('file_size'),'size',$sort,$dir).'</th><th>'.h(T('status')).'</th><th>'.sort_link(T('updated'),'updated',$sort,$dir).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    $projectOpts='';foreach(projects() as $pr)$projectOpts.='<option value="'.(int)$pr['id'].'" '.((int)$pr['id']===current_project_id()?'selected':'').'>'.h($pr['n']).'</option>';
    foreach($rows as $f){$url=$f['url']??'';$name=$f['name']??$f['file']??'';$actions='';$status=$f['status']??$tab;
        if($tab==='trash')$actions=post_form('restore_file','<input type="hidden" name="id" value="'.(int)$f['id'].'"><button class="btn btn-secondary btn-icon" aria-label="'.h(T('restore')).'">'.icon('arrow-counterclockwise').'</button>').universal_delete_button(T('delete_forever'),'delete_file_forever',['id'=>(int)$f['id']],T('delete_forever'),T('delete_irreversible'),true,'btn btn-danger btn-icon');
        elseif($tab==='global_trash'){$actions=post_form('restore_global_file','<input type="hidden" name="id" value="'.(int)$f['id'].'"><button class="btn btn-secondary btn-sm">'.icon('arrow-counterclockwise').' '.h(T('restore_to_active_project')).'</button>').universal_delete_button(T('delete_forever'),'delete_global_file_forever',['id'=>(int)$f['id']],T('delete_forever'),T('delete_irreversible'),true,'btn btn-danger btn-icon');}
        elseif($tab==='orphans'){$actions=post_form('assign_orphan_file','<input type="hidden" name="file" value="'.h($f['file']).'"><div class="input-group input-group-sm"><select class="form-select" name="pid">'.$projectOpts.'</select><button class="btn btn-secondary">'.h(T('assign_to_project')).'</button></div>').universal_delete_button(T('delete_forever'),'delete_orphan_file',['file'=>$f['file']],T('delete_forever'),T('delete_irreversible'),true,'btn btn-danger btn-icon');}
        else $actions=$url?'<a class="btn btn-secondary btn-icon" target="_blank" href="'.h($url).'" aria-label="'.h(T('open')).'">'.icon('box-arrow-up-right').'</a>':'';
        $origin=$tab==='global_trash'?'<div class="project-origin">'.h(T('origin_project')).': '.h($f['origin_project_name']??'—').'</div>':($tab==='orphans'?'<div class="project-origin">'.h(T('origin_project')).': '.h(!empty($f['possible_projects'])?implode(', ',$f['possible_projects']):'—').'</div><div class="small text-muted text-break">'.h($f['path']??'').'</div>':'');$table.='<tr><td><div class="fw-semibold">'.h($name).'</div><small class="text-muted">'.h($f['file']??'').'</small>'.$origin.'</td><td><span class="badge text-bg-light">'.h($f['ext']??'').'</span></td><td>'.h(fmt_size((int)($f['size']??0))).'</td><td><span class="badge text-bg-light">'.h($status).'</span></td><td>'.h($f['updated_at']??$f['created_at']??'').'</td><td class="text-end"><div class="d-inline-flex flex-wrap justify-content-end gap-2">'.$actions.'</div></td></tr>';}
    $table.='</tbody></table>';return $h.$tabs.table_wrap($table,server_search_form(),pager_html($m));
}

function content_lang_checkbox_grid(){
    $sel=array_flip(content_langs());$h='<div class="row g-2">';
    foreach(CONTENT_LANGS as $k=>$v){$id='cl_'.$k;$h.='<div class="col-6 col-md-4 col-xl-3"><div class="form-check rounded-4 bg-body-tertiary p-3 h-100"><input class="form-check-input ms-0 me-2" type="checkbox" name="content_langs[]" value="'.h($k).'" id="'.h($id).'" '.(isset($sel[$k])?'checked':'').'><label class="form-check-label fw-semibold" for="'.h($id).'">'.h($v).'<span class="text-muted small ms-1">'.h($k).'</span></label></div></div>';}
    return $h.'</div>';
}

function apiExplorerPage(){
    $groups=groups();$collections=cols();$groupOpts='';foreach($groups as $g)$groupOpts.='<option value="'.h($g['s']).'">'.h($g['n']).'</option>';$colOpts='';foreach($collections as $c)$colOpts.='<option value="'.h($c['s']).'">'.h($c['n']).'</option>';
    $h=page_head(T('api_explorer'),h(T('send_request')),'','API');
    $h.='<div class="entry-editor-grid"><div class="ios-surface p-4"><form id="apiExplorerForm" data-project="'.h((current_project()['s']??'')).'"><div class="row g-3"><div class="col-md-4"><label class="form-label">Endpoint</label><select class="form-select" id="apiExplorerEndpoint"><option value="index">index</option><option value="entries">entries</option><option value="entry">entry</option><option value="group">group</option><option value="collections">collections</option><option value="schema">schema</option><option value="fields">fields</option><option value="groups">groups</option><option value="files">files</option><option value="files-trash">files-trash</option></select></div><div class="col-md-4"><label class="form-label">'.h(T('collection')).'</label><select class="form-select" id="apiExplorerCollection">'.$colOpts.'</select></div><div class="col-md-4"><label class="form-label">'.h(T('group')).'</label><select class="form-select" id="apiExplorerGroup">'.$groupOpts.'</select></div><div class="col-md-4"><label class="form-label">Slug entry</label><input class="form-control" id="apiExplorerSlug"></div><div class="col-md-4"><label class="form-label">'.h(T('language')).'</label><select class="form-select" id="apiExplorerLang">';foreach(content_langs() as $l)$h.='<option value="'.h($l).'">'.h(CONTENT_LANGS[$l]??$l).'</option>';if(content_i18n_enabled())$h.='<option value="all">'.h(T('all_languages')).'</option>';$h.='</select></div><div class="col-md-4"><label class="form-label">Populate</label><input class="form-control font-monospace" id="apiExplorerPopulate" value="0" placeholder="0 | all | relation_key"></div><div class="col-12"><label class="form-label">'.h(T('api_key')).'</label><input class="form-control font-monospace" id="apiExplorerKey" autocomplete="off" placeholder="X-API-Key"></div></div><div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-primary" type="submit">'.icon('play-fill').' '.h(T('send_request')).'</button><button type="button" class="btn btn-light js-copy" id="apiExplorerCopy" data-copy="">'.icon('copy').' '.h(T('copy_endpoint')).'</button></div></form></div><aside class="entry-preview"><div class="ios-surface p-3"><h2 class="h5">'.h(T('response')).'</h2><pre class="json-preview" id="apiExplorerResponse">{}</pre></div></aside></div>';return $h;
}

function form_field_row_html(array $field,int $index,bool $template=false){
    $types=form_field_types();$prefix='form_fields['.$index.']';$key=h((string)($field['k']??''));$type=(string)($field['t']??'text');$order=(int)($field['o']??(($index+1)*10));$sourceId=(int)($field['id']??($field['source_id']??0));$opts='';foreach($types as $v=>$txt)$opts.='<option value="'.h($v).'" '.($type===$v?'selected':'').'>'.h($txt).'</option>';$rules=validation_rules_from_options(json_decode((string)($field['x']??'{}'),true)?:[]);$choices=implode("\n",array_map('strval',(array)($rules['choices']??[])));
    $langs=content_langs();$multi=content_i18n_enabled()&&count($langs)>1;$primary=default_content_lang();if(!in_array($primary,$langs,true))$primary=$langs[0]??default_content_lang();$postedMap=is_array($field['i18n']??null)?$field['i18n']:[];$map=$postedMap?:field_i18n_map($field,true);$primaryValue=trim((string)($map[$primary]['l']??($field['l']??'')));$labelName=$multi?$prefix.'[i18n]['.$primary.'][l]':$prefix.'[l]';$labelCaption=T('form_field_label').($multi?' · '.(CONTENT_LANGS[$primary]??$primary):'');
    $translations='';if($multi){$translationFields='';foreach($langs as $code){if($code===$primary)continue;$value=trim((string)($map[$code]['l']??''));$translationFields.='<div class="col-12 col-md-6"><label class="form-label small">'.h(CONTENT_LANGS[$code]??$code).' <code>'.h($code).'</code></label><input class="form-control" name="'.$prefix.'[i18n]['.h($code).'][l]" value="'.h($value).'" placeholder="'.h($primaryValue).'"></div>';}$translations='<details class="mt-3"><summary class="small fw-semibold">'.icon('translate').' '.h(T('form_field_translations')).'</summary><div class="small text-muted mt-2 mb-2">'.h(T('field_i18n_hint')).'</div><div class="row g-2">'.$translationFields.'</div></details>';}
    $ruleInputs='<details class="mt-3"><summary class="small fw-semibold">'.h(T('field_rules')).'</summary><div class="row g-2 mt-1"><div class="col-6 col-lg-3"><label class="form-label small">'.h(T('min_length')).'</label><input class="form-control" type="number" min="0" name="'.$prefix.'[min_length]" value="'.h($rules['min_length']??'').'"></div><div class="col-6 col-lg-3"><label class="form-label small">'.h(T('max_length')).'</label><input class="form-control" type="number" min="0" name="'.$prefix.'[max_length]" value="'.h($rules['max_length']??'').'"></div><div class="col-6 col-lg-3"><label class="form-label small">'.h(T('min_value')).'</label><input class="form-control" type="number" step="any" name="'.$prefix.'[min]" value="'.h($rules['min']??'').'"></div><div class="col-6 col-lg-3"><label class="form-label small">'.h(T('max_value')).'</label><input class="form-control" type="number" step="any" name="'.$prefix.'[max]" value="'.h($rules['max']??'').'"></div><div class="col-12 col-lg-6"><label class="form-label small">'.h(T('pattern_regex')).'</label><input class="form-control" name="'.$prefix.'[regex]" value="'.h($rules['regex']??'').'"></div><div class="col-12 col-lg-6"><label class="form-label small">'.h(T('default_value')).'</label><input class="form-control" name="'.$prefix.'[default]" value="'.h($rules['default']??'').'"></div><div class="col-12"><label class="form-label small">'.h(T('allowed_values')).'</label><textarea class="form-control" rows="2" name="'.$prefix.'[choices]">'.h($choices).'</textarea></div></div></details>';
    return '<div class="form-schema-row border rounded-4 p-3" data-form-field-row><input class="js-form-field-order" type="hidden" name="'.$prefix.'[o]" value="'.$order.'"><input type="hidden" name="'.$prefix.'[source_id]" value="'.$sourceId.'"><div class="row g-2 align-items-end"><div class="col-auto d-flex align-items-end"><button type="button" class="btn btn-light btn-icon drag-handle js-form-field-drag" aria-label="'.h(T('drag_to_sort')).'" title="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button></div><div class="col-12 col-lg"><label class="form-label">'.h($labelCaption).'</label><input class="form-control js-form-field-label" name="'.$labelName.'" value="'.h($primaryValue).'" required></div><div class="col-12 col-md-6 col-lg"><label class="form-label">'.h(T('form_field_key')).'</label><input class="form-control js-form-field-key" name="'.$prefix.'[k]" value="'.$key.'" pattern="[a-z][a-z0-9_]*" required></div><div class="col-12 col-md-6 col-lg"><label class="form-label">'.h(T('form_field_type')).'</label><select class="form-select" name="'.$prefix.'[t]">'.$opts.'</select></div><div class="col-auto"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="'.$prefix.'[r]" value="1" id="formFieldReq'.$index.'" '.(!empty($field['r'])?'checked':'').'><label class="form-check-label" for="formFieldReq'.$index.'">'.h(T('required')).'</label></div></div><div class="col-auto text-end"><button type="button" class="btn btn-danger btn-icon js-remove-form-field" aria-label="'.h(T('remove_form_field')).'">'.icon('trash3').'</button></div></div>'.$translations.$ruleInputs.'</div>';
}
function form_modal_fields_data($f=null){$id=(int)($f['id']??0);$old=old_all();if(isset($old['form_fields'])&&is_array($old['form_fields'])&&(int)($old['id']??0)===$id)return array_values($old['form_fields']);$rows=$id?form_fields_all($id):[];if($rows)return $rows;if(!$id)return [['l'=>T('name'),'k'=>'name','t'=>'text','r'=>1,'o'=>10],['l'=>'Email','k'=>'email','t'=>'email','r'=>1,'o'=>20],['l'=>T('message'),'k'=>'message','t'=>'textarea','r'=>1,'o'=>30]];return [['l'=>'','k'=>'','t'=>'text','r'=>0,'o'=>10]];}
function form_modal_admin($f=null){
    $id=(int)($f['id']??0);$rows=form_modal_fields_data($f);$schema='';foreach($rows as $i=>$row)$schema.=form_field_row_html(is_array($row)?$row:[],$i);$template=form_field_row_html(['l'=>'','k'=>'','t'=>'text','r'=>0,'o'=>10],999999,true);
    $retentionOpts=[0=>T('form_retention_none'),30=>T('form_retention_30'),90=>T('form_retention_90'),180=>T('form_retention_180'),365=>T('form_retention_365')];$retentionValue=(int)($f['retention_days']??365);
    $body='<input type="hidden" name="id" value="'.$id.'">'.form_i18n_fields($f,'formModal'.$id).'<div class="row g-3"><div class="col-md-6">'.inp('s',T('slug'),$f['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-6">'.select_html('st',T('status'),['active'=>T('active'),'inactive'=>T('inactive')],$f['st']??'active').'</div></div>'.area('d',T('description'),$f['d_base']??$f['d']??'',['rows'=>'3']).'<div class="border rounded-4 p-3 mb-3"><div class="fw-semibold mb-3">'.h(T('form_notifications')).'</div>'.inp('notify_email',T('notify_email'),$f['notify_email']??'','email',['data-no-old'=>true]).inp('webhook_url',T('webhook_url'),$f['webhook_url']??'','url',['data-no-old'=>true]).inp('webhook_secret',T('webhook_secret'),$f['webhook_secret']??'','password',['autocomplete'=>'new-password']).'<div class="form-text">'.h(T('webhook_hint')).'</div></div>'.select_html('retention_days',T('form_retention'),$retentionOpts,$retentionValue).'<div class="form-text mb-3">'.h(T('form_retention_hint')).'</div>';
    $body.=access_control_html($f,'form');
    $body.='<div class="border rounded-4 p-3 mb-3 js-form-schema-builder"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><div class="fw-semibold">'.h(T('form_fields')).'</div><div class="small text-muted">'.h(T('form_fields_hint')).' '.h(T('drag_to_sort')).'.</div></div><button type="button" class="btn btn-secondary js-add-form-field">'.icon('plus-lg').' '.h(T('add_form_field')).'</button></div><div class="d-grid gap-2 js-form-schema-list">'.$schema.'</div><div class="form-text mt-3">'.h(T('form_schema_history_note')).'</div><template class="js-form-field-template">'.$template.'</template></div>';
    if($id)$body.='<div class="border rounded-4 p-3 mb-3"><div class="fw-semibold mb-2">'.h(T('form_public_endpoint')).'</div><div class="input-group"><input class="form-control" readonly value="'.h(form_endpoint($f)).'"><button type="button" class="btn btn-secondary js-copy" data-copy="'.h(form_endpoint($f)).'">'.icon('copy').' '.h(T('copy_endpoint')).'</button></div><div class="form-text">'.h(T('form_method_note')).'</div></div>';
    $delete=$id?universal_delete_button(T('delete'),'del_form',['id'=>$id],T('delete'),T('form_delete_q'),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('formModal',$id?T('edit_form'):T('new_form'),'form_def',$body,$footer,'modal-xl');
}
function formsPage(){
    $qv=trim((string)($_GET['q']??''));
    $status=in_array((string)($_GET['status']??''),['active','inactive'],true)?(string)$_GET['status']:'';
    $access=in_array((string)($_GET['access']??''),['public','private'],true)?(string)$_GET['access']:'';
    $sort=in_array((string)($_GET['sort']??''),['manual','name','status','access','fields','submissions','last_submission'],true)?(string)$_GET['sort']:'manual';
    $dir=strtolower((string)($_GET['dir']??'asc'))==='desc'?'desc':'asc';
    $where='f.pid=?';$params=[current_project_id()];
    if($qv!==''){$where.=' AND (f.n LIKE ? OR f.s LIKE ? OR f.d LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}
    if($status!==''){$where.=' AND f.st=?';$params[]=$status;}
    if($access!==''){$where.=' AND f.access_mode=?';$params[]=$access;}
    $order='f.o ASC,f.n ASC,f.id ASC';
    if($sort==='name')$order='f.n '.strtoupper($dir).',f.id '.strtoupper($dir);
    elseif($sort==='status')$order='f.st '.strtoupper($dir).',f.n ASC';
    elseif($sort==='access')$order='f.access_mode '.strtoupper($dir).',f.n ASC';
    elseif($sort==='fields')$order='field_count '.strtoupper($dir).',f.n ASC';
    elseif($sort==='submissions')$order='submission_count '.strtoupper($dir).',f.n ASC';
    elseif($sort==='last_submission')$order='last_submission '.strtoupper($dir).',f.n ASC';
    $rows=all('SELECT f.*,COALESCE(ff.field_count,0) AS field_count,COALESCE(fs.submission_count,0) AS submission_count,fs.last_submission FROM forms f LEFT JOIN (SELECT pid,fid,COUNT(*) field_count FROM form_fields GROUP BY pid,fid) ff ON ff.pid=f.pid AND ff.fid=f.id LEFT JOIN (SELECT pid,fid,COUNT(*) submission_count,MAX(ca) last_submission FROM form_submissions GROUP BY pid,fid) fs ON fs.pid=f.pid AND fs.fid=f.id WHERE '.$where.' ORDER BY '.$order,$params);
    $totalForms=(int)q('SELECT COUNT(*) FROM forms WHERE pid=?',[current_project_id()])->fetchColumn();
    $edit=isset($_GET['form_edit'])?form_row((int)$_GET['form_edit']):null;
    $canSort=can_manage_forms()&&$qv===''&&$status===''&&$access===''&&$sort==='manual';
    $hasFilters=($qv!==''||$status!==''||$access!==''||$sort!=='manual'||$dir!=='asc');
    $actions=can_manage_forms()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">'.icon('plus-lg').' '.h(T('new_form')).'</button>':'';
    $h=page_head(T('forms'),h(T('form_endpoint_hint')),$actions,'',false);
    $statusOpts=[''=>T('all_statuses'),'active'=>T('active'),'inactive'=>T('inactive')];
    $accessOpts=[''=>T('all_access_modes'),'public'=>T('access_public'),'private'=>T('access_private')];
    $sortOpts=['manual'=>T('sort_manual'),'name'=>T('sort_name'),'status'=>T('status'),'access'=>T('access_mode'),'fields'=>T('form_field_count'),'submissions'=>T('form_submissions'),'last_submission'=>T('last_submission')];
    $dirOpts=['asc'=>T('sort_asc_short'),'desc'=>T('sort_desc_short')];
    $tools='<div class="forms-filter-panel mb-3"><form method="get"><input type="hidden" name="forms" value="1"><div class="mb-3"><label class="form-label">'.h(T('search')).'</label><input class="form-control" type="search" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"></div><div class="row g-3"><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('status')).'</label><select class="form-select" name="status">';
    foreach($statusOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$status===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('access_mode')).'</label><select class="form-select" name="access">';
    foreach($accessOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$access===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('sort_by')).'</label><select class="form-select" name="sort">';
    foreach($sortOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$sort===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div><div class="col-12 col-md-6 col-xl-3"><label class="form-label">'.h(T('sort_direction')).'</label><select class="form-select" name="dir">';
    foreach($dirOpts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$dir===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div></div><div class="forms-filter-footer"><div class="forms-filter-summary"><span class="forms-filter-count">'.h(sprintf(T('forms_found'),count($rows))).'</span>'.($canSort?'<span class="small text-muted">'.icon('grip-vertical').' '.h(T('drag_to_sort')).'</span>':'').'</div><div class="forms-filter-actions"><button class="btn btn-primary">'.icon('funnel').' '.h(T('apply')).'</button>'.($hasFilters?'<a class="btn btn-light" href="'.h(U(['forms'=>1])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>':'').'</div></div></form></div>';
    $h.=$tools;
    if(!$rows){
        if($totalForms>0&&$hasFilters){$h.=empty_state(T('no_search_results'),T('form_endpoint_hint'),'<a class="btn btn-light" href="'.h(U(['forms'=>1])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>');return $h.form_modal_admin($edit);} 
        $h.=empty_state(T('no_forms'),T('create_first_form'),can_manage_forms()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">'.icon('plus-lg').' '.h(T('new_form')).'</button>':'');
        return $h.form_modal_admin($edit);
    }
    $table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr>'.($canSort?'<th></th>':'').'<th>'.h(T('name')).'</th><th>'.h(T('status')).'</th><th>'.h(T('access_mode')).'</th><th>'.h(T('form_field_count')).'</th><th>'.h(T('form_submissions')).'</th><th>'.h(T('last_submission')).'</th><th>'.h(T('form_public_endpoint')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody '.($canSort?'data-sort-action="reorder_forms"':'').'>';
    foreach($rows as $f){
        $fid=(int)$f['id'];
        $name=can_view_form_submissions()?'<a class="fw-semibold" href="'.h(U(['form_submissions'=>$fid])).'">'.h($f['n']).'</a>':'<span class="fw-semibold">'.h($f['n']).'</span>';
        $items=[];
        if(can_view_form_submissions())$items[]=dd_link(T('form_submissions'),U(['form_submissions'=>$fid]),'inbox');
        if(can_manage_forms()){$items[]=dd_link(T('edit_form'),U(['forms'=>1,'form_edit'=>$fid]),'pencil');$items[]=universal_delete_button(T('delete'),'del_form',['id'=>$fid],T('delete'),T('form_delete_q'),true);} 
        $openSubs=can_view_form_submissions()?'<a class="btn btn-primary btn-sm" href="'.h(U(['form_submissions'=>$fid])).'">'.icon('inbox').' '.h(T('form_submissions')).'</a>':'';
        $endpointBtn='<button type="button" class="btn btn-light btn-icon js-copy" data-copy="'.h(form_endpoint($f)).'" aria-label="'.h(T('copy_endpoint')).'" title="'.h(T('copy_endpoint')).'">'.icon('copy').'</button>';
        $table.='<tr '.($canSort?'draggable="true" data-sort-id="'.$fid.'"':'').'>'.($canSort?'<td><button type="button" class="btn btn-light btn-icon drag-handle" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button></td>':'').'<td>'.$name.'<small class="d-block text-muted">'.h($f['s']).'</small></td><td>'.form_status_badge($f['st']).'</td><td>'.access_badge($f).'</td><td><span class="badge text-bg-light">'.(int)$f['field_count'].'</span></td><td><span class="badge text-bg-light">'.(int)$f['submission_count'].'</span></td><td>'.h($f['last_submission']?:'—').'</td><td><div class="forms-endpoint-cell"><code>'.h(form_endpoint($f)).'</code>'.$endpointBtn.'</div></td><td class="text-end"><div class="forms-table-actions">'.$openSubs.($items?dd_menu($items):'').'</div></td></tr>';
    }
    $table.='</tbody></table>';
    $h.=table_wrap($table);
    return $h.form_modal_admin($edit);
}
function form_submission_detail_modal($s,array $labels=[]){
    $data=json_decode((string)$s['j'],true);if(!is_array($data))$data=[];$rows='';
    foreach($data as $k=>$v){$name=form_field_display_name($k,$labels);$rows.='<div class="row g-2 py-2 border-bottom"><dt class="col-sm-4 text-muted">'.h($name).'</dt><dd class="col-sm-8 mb-0 text-break">'.nl2br(h(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT):$v)).'</dd></div>';}
    $body='<dl class="mb-3">'.$rows.'</dl><div class="small text-muted d-grid gap-1"><div><strong>IP:</strong> '.h($s['ip']?:'—').'</div><div><strong>'.h(T('referrer')).':</strong> '.h($s['ref']?:'—').'</div><div><strong>'.h(T('user_agent')).':</strong> '.h($s['agent']?:'—').'</div></div>';
    $footer='<div class="d-flex flex-wrap align-items-center gap-2 w-100">';
    if(can_manage_form_submissions()){
        $id=(int)$s['id'];
        if(($s['st']??'new')!=='read')$footer.=post_form('form_submission_status','<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="st" value="read"><button class="btn btn-primary">'.icon('check2-circle').' '.h(T('mark_read')).'</button>','class="m-0"');
        if(($s['st']??'new')!=='spam')$footer.=post_form('form_submission_status','<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="st" value="spam"><button class="btn btn-warning">'.icon('exclamation-triangle').' '.h(T('mark_spam')).'</button>','class="m-0"');
        $footer.=universal_delete_button(T('delete'),'del_form_submission',['id'=>$id],T('delete'),T('form_submission_delete_q'),true,'btn btn-outline-danger','trash3');
    }
    $footer.='<button type="button" class="btn btn-light ms-auto" data-bs-dismiss="modal">'.h(T('close')).'</button></div>';
    return modal('submissionModal'.(int)$s['id'],T('form_submission').' #'.(int)$s['id'],$body,$footer,'modal-lg');
}
function formSubmissionsPage($f){
    $fid=(int)$f['id'];$qv=trim((string)($_GET['q']??''));$status=in_array((string)($_GET['status']??''),['new','read','spam'],true)?(string)$_GET['status']:'';$page=max(1,(int)($_GET['page']??1));$per=(int)($_GET['per']??25);if(!in_array($per,[25,50,100],true))$per=25;$dateFrom=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['date_from']??''))?(string)$_GET['date_from']:'';$dateTo=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['date_to']??''))?(string)$_GET['date_to']:'';
    $where='s.fid=? AND s.pid=?';$params=[$fid,current_project_id()];if($status!==''){$where.=' AND s.st=?';$params[]=$status;}if($qv!==''){$where.=' AND (s.j LIKE ? OR s.ref LIKE ? OR s.ip LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}if($dateFrom!==''){$where.=' AND s.ca>=?';$params[]=$dateFrom.' 00:00:00';}if($dateTo!==''){$where.=' AND s.ca<=?';$params[]=$dateTo.' 23:59:59';}
    $total=(int)q('SELECT COUNT(*) FROM form_submissions s WHERE '.$where,$params)->fetchColumn();
    $m=pagination_meta($total,$page,$per);
    $rows=all('SELECT s.* FROM form_submissions s WHERE '.$where.' ORDER BY s.id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);
    $stats=form_submission_stats($fid);
    $fieldLabels=form_field_labels_map($fid);
    $endpoint=form_endpoint($f);
    $exportBase=['form_export'=>$fid,'q'=>$qv,'status'=>$status,'date_from'=>$dateFrom,'date_to'=>$dateTo];
    $csv='./?'.http_build_query($exportBase+['format'=>'csv']);
    $json='./?'.http_build_query($exportBase+['format'=>'json']);

    $actionItems=[
        '<li><button type="button" class="dropdown-item rounded-3 d-flex align-items-center gap-2 js-copy" data-copy="'.h($endpoint).'">'.icon('copy').'<span>'.h(T('copy_endpoint')).'</span></button></li>',
        '<li><hr class="dropdown-divider"></li>',
        dd_link(T('export_csv'),$csv,'filetype-csv'),
        dd_link(T('export_json'),$json,'filetype-json'),
        '<li><hr class="dropdown-divider"></li>',
        dd_link(T('open'),$endpoint,'box-arrow-up-right','_blank'),
    ];
    $actions='<div class="dropdown"><button type="button" class="btn btn-light dropdown-toggle" id="submissionMoreMenu" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false">'.icon('three-dots').' '.h(T('more')).'</button><ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-4 p-2" aria-labelledby="submissionMoreMenu">'.implode('',$actionItems).'</ul></div>';
    $h='<div class="submission-page">'.page_head($f['n'],'<span>'.h(T('form_table_scalable_hint')).'</span>',$actions,T('forms'));
    $h.='<div class="row g-3 mb-3"><div class="col-6 col-xl-3"><div class="ios-surface p-3 h-100"><div class="small text-muted">'.h(T('submission_total')).'</div><div class="h4 mb-0">'.$stats['total'].'</div></div></div><div class="col-6 col-xl-3"><div class="ios-surface p-3 h-100"><div class="small text-muted">'.h(T('submission_new_count')).'</div><div class="h4 mb-0">'.$stats['new'].'</div></div></div><div class="col-6 col-xl-3"><div class="ios-surface p-3 h-100"><div class="small text-muted">'.h(T('submission_read_count')).'</div><div class="h4 mb-0">'.$stats['read'].'</div></div></div><div class="col-6 col-xl-3"><div class="ios-surface p-3 h-100"><div class="small text-muted">'.h(T('submission_storage')).'</div><div class="h4 mb-0">'.h(fmt_size($stats['bytes'])).'</div></div></div>';
    if((int)($f['retention_days']??0)>0)$h.='<div class="col-12"><div class="alert alert-light border mb-0">'.icon('clock-history').' '.h(T('form_retention')).': '.h((string)$f['retention_days']).' '.h(T('days')).'. '.h(T('form_retention_hint')).'</div></div>';$h.='</div>';
    $opts=[''=>T('all_statuses'),'new'=>T('new_status'),'read'=>T('read_status'),'spam'=>T('spam_status')];$perOpts=[25=>'25',50=>'50',100=>'100'];$hasFilters=($qv!==''||$status!==''||$dateFrom!==''||$dateTo!==''||$per!==25);
    $tools='<div class="submission-filter-panel mb-3"><form method="get"><input type="hidden" name="form_submissions" value="'.$fid.'"><div class="row g-3"><div class="col-12 col-xl-4"><label class="form-label">'.h(T('search')).'</label><input class="form-control" type="search" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'"></div><div class="col-12 col-md-6 col-xl-2"><label class="form-label">'.h(T('status')).'</label><select class="form-select" name="status">';
    foreach($opts as $k=>$v)$tools.='<option value="'.h($k).'" '.((string)$status===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div><div class="col-12 col-md-6 col-xl-2"><label class="form-label">'.h(T('date_from')).'</label><input class="form-control" type="date" name="date_from" value="'.h($dateFrom).'"></div><div class="col-12 col-md-6 col-xl-2"><label class="form-label">'.h(T('date_to')).'</label><input class="form-control" type="date" name="date_to" value="'.h($dateTo).'"></div><div class="col-12 col-md-6 col-xl-2"><label class="form-label">'.h(T('per_page')).'</label><select class="form-select" name="per">';
    foreach($perOpts as $k=>$v)$tools.='<option value="'.h((string)$k).'" '.((string)$per===(string)$k?'selected':'').'>'.h($v).'</option>';
    $tools.='</select></div></div><div class="submission-filter-footer"><div class="submission-filter-summary"><span class="forms-filter-count">'.h(sprintf(T('submissions_found'),$total)).'</span></div><div class="forms-filter-actions"><button class="btn btn-primary">'.icon('funnel').' '.h(T('apply')).'</button>'.($hasFilters?'<a class="btn btn-light" href="'.h(U(['form_submissions'=>$fid])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>':'').'</div></div></form></div>';
    if(!$rows){$emptyCta=$hasFilters?'<a class="btn btn-light" href="'.h(U(['form_submissions'=>$fid])).'">'.icon('arrow-counterclockwise').' '.h(T('reset')).'</a>':endpoint_copy_button($endpoint);$h.=$tools.empty_state(T('no_form_submissions'),T('form_table_scalable_hint'),$emptyCta).'</div>';return $h;}
    $bulkBar='';if(can_manage_form_submissions())$bulkBar='<form method="post" id="bulkSubmissionForm" class="js-submission-bulk submission-bulk-bar" hidden data-empty-message="'.h(T('form_submissions_select_required')).'">'.token().'<input type="hidden" name="_a" value="bulk_form_submissions"><input type="hidden" name="fid" value="'.$fid.'"><input type="hidden" name="_return" value="'.h(clean_url($_SERVER['REQUEST_URI']??U(['form_submissions'=>$fid]))).'"><div class="submission-bulk-meta"><span class="text-muted">'.h(T('selected_items')).'</span><span class="submission-bulk-count js-submission-selected-count" aria-live="polite">0</span></div><div class="submission-bulk-actions"><button class="btn btn-primary btn-sm js-bulk-submit" type="submit" name="bulk_action" value="read" disabled>'.icon('check2-circle').' '.h(T('mark_read')).'</button><button class="btn btn-warning btn-sm js-bulk-submit" type="submit" name="bulk_action" value="spam" disabled>'.icon('exclamation-triangle').' '.h(T('mark_spam')).'</button><button class="btn btn-outline-danger btn-sm js-bulk-submit" type="button" data-bs-toggle="modal" data-bs-target="#bulkSubmissionDeleteModal" disabled>'.icon('trash3').' '.h(T('delete')).'</button></div></form>';
    $selectHead=can_manage_form_submissions()?'<th class="submission-select-cell"><label class="submission-master-check" title="'.h(T('select_all')).'"><input id="submissionSelectAll" class="form-check-input js-submission-select-all" type="checkbox" aria-label="'.h(T('select_all')).'"></label></th>':'';
    $table='<table class="table table-hover align-middle mb-0 cms-responsive submission-page-table"><thead><tr>'.$selectHead.'<th>'.h(T('created')).'</th><th>'.h(T('submission_summary')).'</th><th>'.h(T('form_field_count')).'</th><th>'.h(T('status')).'</th><th>'.h(T('referrer')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';$modals='';
    foreach($rows as $s){$data=json_decode((string)$s['j'],true);if(!is_array($data))$data=[];$summary=form_submission_summary_html($data,$fieldLabels);$statusButtons='';if(can_manage_form_submissions()){foreach(['new'=>T('mark_new'),'read'=>T('mark_read'),'spam'=>T('mark_spam')] as $st=>$label)if($s['st']!==$st)$statusButtons.=dd_form($label,'form_submission_status','<input type="hidden" name="id" value="'.(int)$s['id'].'"><input type="hidden" name="st" value="'.h($st).'">',$st==='spam'?'exclamation-triangle':'check2-circle');$statusButtons.=universal_delete_button(T('delete'),'del_form_submission',['id'=>(int)$s['id']],T('delete'),T('form_submission_delete_q'),true);}$items='<li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#submissionModal'.(int)$s['id'].'">'.icon('eye').' '.h(T('open')).'</button></li>'.$statusButtons;$selectCell=can_manage_form_submissions()?'<td class="submission-select-cell"><input class="form-check-input js-submission-select" type="checkbox" name="ids[]" value="'.(int)$s['id'].'" form="bulkSubmissionForm" aria-label="'.h(T('select_submission').' #'.(int)$s['id']).'"></td>':'';$table.='<tr>'.$selectCell.'<td class="text-nowrap">'.h($s['ca']).'</td><td class="submission-summary-cell"><button class="btn btn-link text-start p-0 text-decoration-none w-100 overflow-hidden" type="button" data-bs-toggle="modal" data-bs-target="#submissionModal'.(int)$s['id'].'">'.$summary.'</button></td><td><span class="badge text-bg-light">'.count($data).'</span></td><td>'.form_status_badge($s['st']).'</td><td class="text-truncate" title="'.h($s['ref']?:'').'">'.h(form_referrer_compact($s['ref']??'')).'</td><td class="text-end">'.dd_menu([$items],T('actions')).'</td></tr>';$modals.=form_submission_detail_modal($s,$fieldLabels);}
    $table.='</tbody></table>';$bulkDeleteModal='';if(can_manage_form_submissions())$bulkDeleteModal='<div class="modal fade" id="bulkSubmissionDeleteModal" tabindex="-1" aria-labelledby="bulkSubmissionDeleteTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="bulkSubmissionDeleteTitle">'.h(T('delete_confirm')).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body"><p class="mb-2">'.h(T('form_submissions_bulk_delete_q')).'</p><div class="alert alert-danger mb-0"><div class="fw-semibold mb-1">'.h(T('selected_items')).': <span class="js-bulk-delete-count">0</span></div><div class="small">'.h(T('delete_irreversible')).'</div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button type="submit" class="btn btn-danger js-bulk-submit" form="bulkSubmissionForm" name="bulk_action" value="delete">'.icon('trash3').' '.h(T('delete')).'</button></div></div></div></div>';$bulkSelectionScript=can_manage_form_submissions()?<<<'HTML'
<script>
(()=>{
    const form=document.getElementById('bulkSubmissionForm');
    if(!form)return;

    const page=form.closest('.submission-page')||document;
    const master=page.querySelector('.js-submission-select-all');
    const countNode=form.querySelector('.js-submission-selected-count');
    const deleteCount=page.querySelector('.js-bulk-delete-count');
    const buttons=[...page.querySelectorAll('.js-bulk-submit')];
    const deleteModal=document.getElementById('bulkSubmissionDeleteModal');
    const topbar=document.querySelector('.premium-topbar');
    const boxes=()=>[...page.querySelectorAll('input.js-submission-select[form="bulkSubmissionForm"]')];

    const selectedCount=()=>boxes().filter(box=>box.checked).length;

    const syncStickyTop=()=>{
        const height=Math.ceil(topbar?.getBoundingClientRect().height||0);
        form.style.setProperty('--submission-sticky-top',`${height+12}px`);
    };

    const sync=()=>{
        const all=boxes();
        const count=all.filter(box=>box.checked).length;

        form.hidden=count===0;
        if(countNode)countNode.textContent=String(count);
        if(deleteCount)deleteCount.textContent=String(count);
        buttons.forEach(button=>button.disabled=count===0);

        all.forEach(box=>box.closest('tr')?.classList.toggle('is-selected',box.checked));

        if(master){
            master.indeterminate=count>0&&count<all.length;
            master.checked=all.length>0&&count===all.length;
            master.setAttribute('aria-checked',master.indeterminate?'mixed':String(master.checked));
        }
    };

    const selectAll=()=>{
        const checked=Boolean(master?.checked);
        boxes().forEach(box=>{box.checked=checked;});
        sync();
    };

    // Both events are intentionally handled: this also works reliably when
    // Bootstrap/table styles wrap the checkbox in a label.
    master?.addEventListener('click',()=>queueMicrotask(selectAll));
    master?.addEventListener('change',selectAll);

    boxes().forEach(box=>box.addEventListener('change',sync));

    form.addEventListener('submit',event=>{
        if(selectedCount()>0)return;
        event.preventDefault();
        alert(form.dataset.emptyMessage||'Select at least one item');
    });

    deleteModal?.addEventListener('show.bs.modal',event=>{
        const count=selectedCount();
        if(!count){event.preventDefault();return;}
        if(deleteCount)deleteCount.textContent=String(count);
    });

    syncStickyTop();
    if(topbar&&'ResizeObserver' in window)new ResizeObserver(syncStickyTop).observe(topbar);
    else window.addEventListener('resize',syncStickyTop,{passive:true});

    sync();
})();
</script>
HTML:'';$h.=$bulkBar.'<div class="ios-surface p-3 p-lg-4">'.$tools.'<div class="table-responsive">'.$table.'</div>'.pager_html($m).'</div>'.$modals.$bulkDeleteModal.$bulkSelectionScript.'</div>';return $h;
}

function settings_card_header(string $iconName,string $title,string $description,bool $small=false): string{
    return '<div class="mb-3"><span class="btn btn-light btn-icon disabled mb-3">'.icon($iconName).'</span><h3 class="h5 mb-1">'.h($title).'</h3><p class="text-muted '.($small?'small ':'').'mb-0">'.h($description).'</p></div>';
}

function settings_nav_item(string $id,string $iconName,string $title,bool $active=false): string{
    return '<a class="nav-link'.($active?' active':'').'" href="#'.h($id).'" data-settings-target="'.h($id).'">'.icon($iconName).'<span>'.h($title).'</span></a>';
}
function settings_section(string $id,string $title,string $description,string $cards): string{
    return '<section id="'.h($id).'" class="settings-section"><div class="settings-section-heading mb-3"><h2 class="h4 mb-1">'.h($title).'</h2><p class="text-muted mb-0">'.h($description).'</p></div><div class="row g-3">'.$cards.'</div></section>';
}
function settingsPage(){
    $h=page_head(T('settings'),h(T('ui_settings')),'','',false);
    $langForm=post_form('set_lang','<input type="hidden" name="_back" value="'.h(clean_url($_SERVER['REQUEST_URI']??'./')).'">'.select_html('lang',T('language'),LANGS,lang(),['onchange'=>'this.form.submit()','data-no-old'=>true]));
    $themeForm=theme_toggle();
    $debug=debug_enabled();
    $debugForm='<form method="post">'.token().'<input type="hidden" name="_a" value="save_debug_settings"><input type="hidden" name="debug_mode" value="0"><div class="d-flex align-items-center justify-content-between gap-3"><div><div class="fw-semibold">'.h(T('debug_mode')).'</div><div class="text-muted small">'.h(T($debug?'enabled':'disabled')).'</div></div><label class="ios-toggle"><input type="checkbox" name="debug_mode" value="1" onchange="this.form.submit()" '.($debug?'checked':'').'><span></span></label></div><div class="alert alert-warning small mt-3 mb-0">'.h(T('debug_mode_warning')).'</div></form>';
    $driver=strtoupper(db_driver());$cfg=db_cfg();$dbInfo=db_driver()==='mysql'?($cfg['mysql']['host']??'').' / '.($cfg['mysql']['database']??''):basename((string)($cfg['sqlite_path']??SQLITE));

    $telegram=telegram_settings();$telegramHasToken=telegram_token()!=='';$telegramEnabled=(bool)old_value('telegram_enabled',$telegram['enabled']?1:0);$telegramChatId=(string)old_value('telegram_chat_id',$telegram['chat_id']);$telegramBotUsername=(string)$telegram['bot_username'];$telegramBotName=(string)$telegram['bot_name'];$telegramBotReady=$telegramHasToken&&$telegramBotUsername!=='';$telegramBotUrl=$telegramBotReady?'https://t.me/'.$telegramBotUsername:'';
    $telegramStatus='<div id="telegramBotStatus" class="alert '.($telegramBotReady?'alert-success':'alert-secondary').' rounded-4 border-0 mb-3"><div class="fw-semibold" id="telegramBotStatusTitle">'.h($telegramBotReady?($telegramBotName?:('@'.$telegramBotUsername)):T('telegram_bot_not_verified')).'</div><div class="small mt-1" id="telegramBotStatusText">'.($telegramBotReady?'@'.h($telegramBotUsername):h(T('telegram_bot_token'))).'</div></div>';
    $telegramGuide='<div id="telegramChatIdGuide" class="border rounded-4 p-3 p-lg-4 mb-3 '.($telegramBotReady?'':'d-none').'"><div class="d-flex gap-3 align-items-start mb-3"><span class="btn btn-light btn-icon disabled flex-shrink-0">'.icon('chat-dots').'</span><div><div class="fw-semibold">'.h(T('telegram_chat_id_setup_title')).'</div><div class="small text-muted mt-1">'.h(T('telegram_chat_id_setup_hint')).'</div></div></div><div class="d-flex flex-column flex-sm-row gap-2"><a id="telegramOpenBot" class="btn btn-primary flex-fill '.($telegramBotUrl!==''?'':'disabled').'" href="'.h($telegramBotUrl?:'#').'" target="_blank" rel="noopener">'.icon('telegram').' '.h(T('telegram_open_bot')).'</a><button id="telegramWaitChatId" class="btn btn-secondary flex-fill" type="button" data-idle="'.h(T('telegram_wait_chat_id')).'" data-waiting="'.h(T('telegram_waiting_message')).'">'.icon('radar').' '.h(T('telegram_wait_chat_id')).'</button></div><div id="telegramChatIdFeedback" class="small text-muted mt-3" aria-live="polite"></div></div>';
    $telegramBody='<form id="telegramConfiguratorForm" method="post">'.token().'<input type="hidden" name="telegram_enabled" value="0"><div class="d-flex align-items-center justify-content-between gap-3 mb-4"><div><div class="fw-semibold">'.h(T('telegram_bot')).'</div><div class="text-muted small">'.h(T($telegramEnabled?'enabled':'disabled')).'</div></div><label class="ios-toggle"><input type="checkbox" name="telegram_enabled" value="1" '.($telegramEnabled?'checked':'').'><span></span></label></div><div class="row g-2 align-items-end"><div class="col-12 col-lg">'.inp('telegram_bot_token',T('telegram_bot_token'),'','password',['autocomplete'=>'new-password','placeholder'=>T('telegram_token_keep'),'data-no-old'=>true,'id'=>'telegramBotToken']).'</div><div class="col-12 col-lg-auto mb-3"><button id="telegramVerifyBot" class="btn btn-secondary btn-lg w-100" type="button">'.icon('check2-circle').' '.h(T('telegram_verify_bot')).'</button></div></div><div id="telegramTokenSaved" class="form-text text-success mb-3 '.($telegramHasToken?'':'d-none').'">'.icon('check-circle').' '.h(T('telegram_token_saved')).'</div>'.$telegramStatus.$telegramGuide.inp('telegram_chat_id',T('telegram_chat_id'),$telegramChatId,'text',['placeholder'=>'123456789 или -1001234567890','data-no-old'=>true,'id'=>'telegramChatId']).'<div class="form-text">'.h(T('telegram_chat_id_hint')).'</div></form>';
    $telegramFooter='<button type="submit" class="btn btn-secondary me-auto" name="_a" value="test_telegram_settings" form="telegramConfiguratorForm">'.icon('send').' '.h(T('telegram_test')).'</button><button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button type="submit" class="btn btn-primary" name="_a" value="save_telegram_settings" form="telegramConfiguratorForm">'.icon('check-lg').' '.h(T('save')).'</button>';
    $telegramModal=modal('telegramConfiguratorModal',T('telegram_configurator'),$telegramBody,$telegramFooter,'modal-lg');
    $telegramCard='<div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('telegram',T('telegram_bot'),T('telegram_bot_hint'),true).'<div class="mt-auto pt-4"><button class="btn btn-primary w-100" type="button" data-bs-toggle="modal" data-bs-target="#telegramConfiguratorModal">'.icon('sliders').' '.h(T('telegram_open_configurator')).'</button></div></div>';

    $usage=content_language_usage();$selected=configured_content_langs();$i18nEnabled=content_i18n_enabled();$checks='<div class="row g-2">';
    foreach(CONTENT_LANGS as $code=>$name){$checked=in_array($code,$selected,true);$count=(int)($usage[$code]??0);$id='contentLang_'.preg_replace('~[^a-z0-9_-]+~i','_',$code);$checks.='<div class="col-12 col-md-6 col-xl-4"><label class="content-language-card d-block h-100" for="'.h($id).'"><input class="visually-hidden js-content-lang" id="'.h($id).'" type="checkbox" name="content_langs[]" value="'.h($code).'" '.($checked?'checked':'').' data-lang-name="'.h($name).'" data-has-data="'.($count>0?'1':'0').'"><span class="content-language-option d-flex align-items-start justify-content-between gap-3 h-100 p-3 rounded-4 border"><span class="min-w-0"><span class="fw-semibold d-block text-truncate">'.h($name).'</span><small class="content-language-code d-block text-muted mt-1">'.h($code).($count?' · '.$count.' '.h(T('language_has_data')):'').'</small></span><span class="content-language-check flex-shrink-0" aria-hidden="true">'.icon('check-lg').'</span></span></label></div>';}
    $checks.='</div>';$toggle='<input type="hidden" name="content_i18n" value="0"><div class="d-flex align-items-center justify-content-between gap-3 mb-4"><div><div class="fw-semibold">'.h(T('content_i18n_toggle')).'</div><div class="text-muted small js-i18n-mode-hint">'.h(T($i18nEnabled?'content_i18n_on_hint':'content_i18n_off_hint')).'</div></div><label class="ios-toggle"><input class="js-i18n-toggle" type="checkbox" name="content_i18n" value="1" '.($i18nEnabled?'checked':'').'><span></span></label></div>';
    $defaultBlock='<div class="js-i18n-default-block '.($i18nEnabled?'d-none':'').'">'.select_html('content_default_lang',T('default_content_language'),CONTENT_LANGS,configured_default_content_lang(),['data-no-old'=>true,'id'=>'contentDefaultLang']).'</div>';
    $languagesBlock='<div class="js-i18n-languages-block '.($i18nEnabled?'':'d-none').'"><div class="mb-3"><div class="fw-semibold mb-1">'.h(T('content_languages')).'</div><div class="text-muted small">'.h(T('content_languages_hint')).'</div></div>'.$checks.'</div>';
    $i18nForm='<form method="post" class="js-i18n-settings" data-last-language-message="'.h(T('last_language_locked')).'" data-mode-on="'.h(T('content_i18n_on_hint')).'" data-mode-off="'.h(T('content_i18n_off_hint')).'">'.token().'<input type="hidden" name="_a" value="save_i18n_settings">'.$toggle.$defaultBlock.$languagesBlock.'<button class="btn btn-primary w-100 mt-4">'.icon('check-lg').' '.h(T('save')).'</button></form>';

    $interfaceCards='<div class="col-12 col-lg-6"><div class="ios-surface p-4 h-100">'.settings_card_header('translate',T('language'),T('language_hint')).$langForm.'</div></div>';
    $interfaceCards.='<div class="col-12 col-lg-6"><div class="ios-surface p-4 h-100">'.settings_card_header(theme()==='dark'?'moon-stars':'sun',T('theme'),T('theme_hint')).$themeForm.'</div></div>';

    $pr=current_project();
    $contentCards='<div class="col-12"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('window-stack',T('projects'),$pr?$pr['n'].' · '.$pr['s']:T('projects_hint')).'<div class="mt-auto pt-4"><button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#projectsModal">'.icon('window-stack').' '.h(T('project_switch')).'</button></div></div></div>';
    $contentCards.='<div class="col-12"><div class="ios-surface p-4 h-100">'.settings_card_header('globe2',T('content_settings'),T('content_i18n_hint2')).$i18nForm.'</div></div>';

    $accessCards='';
    if(is_admin_user())$accessCards.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('people',T('users'),T('role_capabilities')).'<div class="mt-auto pt-4"><a class="btn btn-primary w-100" href="'.h(U(['users'=>1])).'">'.icon('people').' '.h(T('users')).'</a></div></div></div>';
    if(can_api()){
        $apiCol=is_admin_user()?'col-lg-4':'col-lg-6';
        $accessCards.='<div class="col-12 '.$apiCol.'"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('braces',T('api_explorer'),T('open_api')).'<div class="mt-auto pt-4"><a class="btn btn-primary w-100" href="'.h(U(['api_explorer'=>1])).'">'.icon('terminal').' '.h(T('api_explorer')).'</a></div></div></div>';
        $accessCards.='<div class="col-12 '.$apiCol.'"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('key',T('api_keys'),T('access_private_hint')).'<div class="mt-auto pt-4"><a class="btn btn-primary w-100" href="'.h(U(['api_keys'=>1])).'">'.icon('key').' '.h(T('api_management')).'</a></div></div></div>';
    }

    $controlCards='<div class="col-12 '.(is_admin_user()?'col-md-4':'col-lg-6').'">'.$telegramCard.'</div>';
    if(is_admin_user()){
        $controlCards.='<div class="col-12 col-md-4"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('activity',T('audit_log'),T('audit_hint'),true).'<div class="mt-auto pt-4"><a class="btn btn-secondary w-100" href="'.h(U(['audit'=>1])).'">'.h(T('open')).'</a></div></div></div>';
        $controlCards.='<div class="col-12 col-md-4"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('heart-pulse',T('diagnostics'),T('diagnostics_hint'),true).'<div class="mt-auto pt-4"><a class="btn btn-secondary w-100" href="'.h(U(['diagnostics'=>1])).'">'.h(T('open')).'</a></div></div></div>';
    }

    $dataCards='';
    if(is_admin_user()){
        $download=post_form('backup_project','<div class="backup-action-head"><div><div class="fw-semibold fs-5">'.h(T('backup_project')).'</div><div class="backup-action-kicker">ZIP</div></div><span class="backup-action-icon">'.icon('download').'</span></div><button class="btn btn-primary w-100">'.icon('download').' '.h(T('backup_project')).'</button>','class="h-100 d-flex flex-column justify-content-center"');
        $restore='<form method="post" enctype="multipart/form-data" class="h-100 d-flex flex-column justify-content-center">'.token().'<input type="hidden" name="_a" value="restore_project_backup"><div class="backup-action-head"><div><div class="fw-semibold fs-5">'.h(T('restore_backup')).'</div><div class="backup-action-kicker">ZIP</div></div><span class="backup-action-icon">'.icon('upload').'</span></div><input class="form-control mb-3" type="file" name="backup" accept=".zip,application/zip" aria-label="'.h(T('restore_backup')).'" required><button class="btn btn-primary w-100">'.icon('upload').' '.h(T('restore_backup')).'</button></form>';
        $dataCards.='<div class="col-12 col-lg-6"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('archive',T('backups'),T('backup_hint')).'<div class="row g-3 flex-grow-1"><div class="col-12"><div class="backup-action-card is-download rounded-4 p-4 p-xl-4 h-100">'.$download.'</div></div><div class="col-12"><div class="backup-action-card is-restore rounded-4 p-4 p-xl-4 h-100">'.$restore.'</div></div></div></div></div>';
    }
    $corsForm='<form method="post">'.token().'<input type="hidden" name="_a" value="save_cors_settings">'.area('cors_origins',T('cors_origins'),project_cors_origins(),['rows'=>'5','placeholder'=>"https://site.kz\nhttps://admin.site.kz",'data-no-old'=>true]).'<div class="form-text mb-3">'.h(T('cors_origins_hint')).'<br>'.h(T('cors_default_all')).'</div><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button></form>';
    $dataCards.='<div class="col-12 col-lg-6"><div class="ios-surface p-4 h-100">'.settings_card_header('shield-check',T('cors_settings'),T('cors_settings_hint')).$corsForm.'</div></div>';

    $systemCol=is_admin_user()?'col-lg-4':'col-lg-6';$systemCards='';
    if(is_admin_user())$systemCards.='<div class="col-12 '.$systemCol.'"><div class="ios-surface p-4 h-100">'.settings_card_header('database',T('current_db'),$driver.' · '.$dbInfo).'<button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#resetDbModal">'.icon('arrow-counterclockwise').' '.h(T('db_reset')).'</button></div></div>';
    $systemCards.='<div class="col-12 '.$systemCol.'"><div class="ios-surface p-4 h-100">'.settings_card_header('bug',T('debug_mode'),T('debug_mode_hint')).$debugForm.'</div></div>';
    $systemCards.='<div class="col-12 '.$systemCol.'"><div class="ios-surface p-4 h-100 d-flex flex-column">'.settings_card_header('tools',T('maintenance'),T('maintenance_hint')).'<div class="mt-auto pt-4">'.post_form('cleanup_maintenance','<button class="btn btn-secondary w-100">'.icon('trash3').' '.h(T('maintenance')).'</button>').'</div></div></div>';
    $repoUrl='https://github.com/DreamerView/mini-cms';
    $technologyGroups=[
        ['server',T('technology_backend'),[['PHP 8.x','https://www.php.net/'],['PDO','https://www.php.net/manual/en/book.pdo.php'],['PHP Sessions','https://www.php.net/manual/en/book.session.php'],['password_hash / hash_hmac','https://www.php.net/manual/en/book.password.php'],['OpenSSL','https://www.php.net/manual/en/book.openssl.php'],['cURL / PHP streams','https://www.php.net/manual/en/book.curl.php'],['ZipArchive','https://www.php.net/manual/en/class.ziparchive.php']]],
        ['database',T('technology_data'),[['SQLite','https://www.sqlite.org/'],['MySQL','https://www.mysql.com/'],['JSON','https://www.json.org/']]],
        ['palette',T('technology_frontend'),[['HTML5','https://developer.mozilla.org/docs/Web/HTML'],['CSS3','https://developer.mozilla.org/docs/Web/CSS'],['Bootstrap 5.3.3','https://getbootstrap.com/'],['Bootstrap Icons 1.11.3','https://icons.getbootstrap.com/'],['Bootstrap Bundle / Popper','https://getbootstrap.com/docs/5.3/getting-started/javascript/'],['jsDelivr CDN','https://www.jsdelivr.com/']]],
        ['code-slash',T('technology_browser'),[['Vanilla JavaScript (ES6+)','https://developer.mozilla.org/docs/Web/JavaScript'],['Fetch API','https://developer.mozilla.org/docs/Web/API/Fetch_API'],['FormData','https://developer.mozilla.org/docs/Web/API/FormData'],['Drag and Drop API','https://developer.mozilla.org/docs/Web/API/HTML_Drag_and_Drop_API'],['Clipboard API','https://developer.mozilla.org/docs/Web/API/Clipboard_API'],['IntersectionObserver','https://developer.mozilla.org/docs/Web/API/Intersection_Observer_API'],['Session Storage','https://developer.mozilla.org/docs/Web/API/Window/sessionStorage']]],
        ['diagram-3',T('technology_integrations'),[['Telegram Bot API','https://core.telegram.org/bots/api'],['Webhooks / HMAC-SHA256','https://www.php.net/manual/en/function.hash-hmac.php'],['CORS','https://developer.mozilla.org/docs/Web/HTTP/CORS'],['HTTP / REST / JSON API','https://developer.mozilla.org/docs/Web/HTTP']]],
    ];
    $technologyHtml='';foreach($technologyGroups as [$technologyIcon,$technologyTitle,$technologyItems]){$items='';foreach($technologyItems as [$technologyName,$technologyUrl])$items.='<a class="badge rounded-pill text-bg-light text-decoration-none border px-3 py-2" href="'.h($technologyUrl).'" target="_blank" rel="noopener noreferrer">'.h($technologyName).'</a>';$technologyHtml.='<div class="col-12 col-xl-6"><div class="border rounded-4 p-3 h-100"><div class="d-flex align-items-center gap-2 mb-3"><span class="btn btn-light btn-icon disabled">'.icon($technologyIcon).'</span><div class="fw-semibold">'.h($technologyTitle).'</div></div><div class="d-flex flex-wrap gap-2">'.$items.'</div></div></div>';}
    $aboutBody='<div class="row g-3 align-items-stretch"><div class="col-12 col-lg-6"><div class="border rounded-4 p-3 h-100 bg-body-tertiary"><div class="small text-muted mb-1">'.h(T('author')).'</div><div class="fw-semibold fs-5">Темирхан Рустемов</div><div class="small text-muted mt-1">DreamerView</div></div></div><div class="col-12 col-lg-6"><div class="border rounded-4 p-3 h-100 d-flex flex-column bg-body-tertiary"><div class="small text-muted mb-1">'.h(T('repository')).'</div><code class="small text-break mb-3">'.h($repoUrl).'</code><a class="btn btn-dark w-100 mt-auto" href="'.h($repoUrl).'" target="_blank" rel="noopener noreferrer">'.icon('github').' '.h(T('open_repository')).'</a></div></div></div><div class="border-top mt-4 pt-4"><div class="mb-3"><div class="fw-semibold fs-5">'.h(T('technologies_used')).'</div><div class="small text-muted mt-1">'.h(T('technologies_used_hint')).'</div></div><div class="row g-3">'.$technologyHtml.'</div><div class="alert alert-light border rounded-4 small text-muted mt-3 mb-0">'.icon('info-circle').' '.h(T('technology_notice')).'</div></div>';
    $systemCards.='<div class="col-12"><div class="ios-surface p-4 h-100">'.settings_card_header('github',T('about_project'),T('about_project_hint')).$aboutBody.'</div></div>';

    $sections=[];$nav=[];
    $addSection=function(string $id,string $iconName,string $title,string $description,string $cards)use(&$sections,&$nav){if(trim($cards)==='')return;$nav[]=settings_nav_item($id,$iconName,$title,count($nav)===0);$sections[]=settings_section($id,$title,$description,$cards);};
    $addSection('settings-interface','sliders',T('settings_section_interface'),T('settings_section_interface_hint'),$interfaceCards);
    $addSection('settings-content','folder2-open',T('settings_section_content'),T('settings_section_content_hint'),$contentCards);
    $addSection('settings-access','key',T('settings_section_access'),T('settings_section_access_hint'),$accessCards);
    $addSection('settings-control','bell',T('settings_section_control'),T('settings_section_control_hint'),$controlCards);
    $addSection('settings-data','shield-check',T('settings_section_data'),T('settings_section_data_hint'),$dataCards);
    $addSection('settings-system','gear',T('settings_section_system'),T('settings_section_system_hint'),$systemCards);

    $h.='<div class="settings-layout"><aside class="settings-sidebar"><div class="ios-surface p-2"><div class="settings-nav-title px-3 pt-2 pb-2">'.h(T('settings_navigation')).'</div><nav class="nav nav-pills flex-column settings-nav" id="settingsNav" aria-label="'.h(T('settings_navigation')).'">'.implode('',$nav).'</nav></div></aside><div class="settings-content">'.implode('',$sections).'</div></div>';
    if(is_admin_user())$h.=form_modal('resetDbModal',T('db_reset'),'reset_db_config','<p>'.h(T('db_reset_q')).'</p><div class="alert alert-warning">'.h(T('db_reset_hint')).'</div>','<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('arrow-counterclockwise').' '.h(T('db_reset')).'</button>');
    $h.=$telegramModal;
    $h.='<script>(()=>{const nav=document.getElementById("settingsNav");if(!nav)return;const links=[...nav.querySelectorAll("a[data-settings-target]")];const sections=links.map(link=>document.getElementById(link.dataset.settingsTarget)).filter(Boolean);const activate=id=>links.forEach(link=>link.classList.toggle("active",link.dataset.settingsTarget===id));links.forEach(link=>link.addEventListener("click",()=>activate(link.dataset.settingsTarget)));if(location.hash&&document.getElementById(location.hash.slice(1)))activate(location.hash.slice(1));if("IntersectionObserver" in window){const observer=new IntersectionObserver(entries=>{const current=entries.filter(entry=>entry.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];if(current)activate(current.target.id)},{rootMargin:"-18% 0px -68% 0px",threshold:[0,.15,.4]});sections.forEach(section=>observer.observe(section));}})();</script>';
    $telegramNoMessagesJs=json_encode(T('telegram_no_messages'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$telegramRadarJs=json_encode(icon('radar').' ',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $h.='<script>(()=>{const form=document.getElementById("telegramConfiguratorForm"),verify=document.getElementById("telegramVerifyBot"),wait=document.getElementById("telegramWaitChatId"),tokenInput=document.getElementById("telegramBotToken"),chatInput=document.getElementById("telegramChatId"),status=document.getElementById("telegramBotStatus"),statusTitle=document.getElementById("telegramBotStatusTitle"),statusText=document.getElementById("telegramBotStatusText"),guide=document.getElementById("telegramChatIdGuide"),openBot=document.getElementById("telegramOpenBot"),feedback=document.getElementById("telegramChatIdFeedback"),saved=document.getElementById("telegramTokenSaved");if(!form||!verify||!wait)return;let waiting=false;const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));const request=async action=>{const data=new FormData(form);data.set("_a",action);const response=await fetch(location.href,{method:"POST",body:data,headers:{"X-Requested-With":"XMLHttpRequest"}});let json={};try{json=await response.json()}catch(e){throw new Error("HTTP "+response.status)}if(!response.ok||!json.ok)throw new Error(json.message||("HTTP "+response.status));return json};const showBot=bot=>{status.className="alert alert-success rounded-4 border-0 mb-3";statusTitle.textContent=bot.name||("@"+(bot.username||""));statusText.textContent=bot.username?"@"+bot.username:"";guide.classList.remove("d-none");if(bot.url){openBot.href=bot.url;openBot.classList.remove("disabled")}else{openBot.href="#";openBot.classList.add("disabled")}saved.classList.remove("d-none");tokenInput.value=""};const startWaiting=async()=>{if(waiting)return;waiting=true;const idle=wait.dataset.idle||wait.textContent,waitingText=wait.dataset.waiting||idle;wait.disabled=true;wait.innerHTML="<span class=\"spinner-border spinner-border-sm me-2\" aria-hidden=\"true\"></span>"+waitingText;feedback.className="small text-muted mt-3";feedback.textContent=waitingText;try{for(let i=0;i<48;i++){const json=await request("telegram_check_updates");if(json.bot)showBot(json.bot);if(Number(json.replied)>0){if(chatInput&&json.chat_id){chatInput.value=json.chat_id;chatInput.dispatchEvent(new Event("input",{bubbles:true}));chatInput.focus()}feedback.className="small text-success mt-3";feedback.textContent=json.message;return}if(i<47)await sleep(2500)}feedback.className="small text-warning mt-3";feedback.textContent='.$telegramNoMessagesJs.'}catch(error){feedback.className="small text-danger mt-3";feedback.textContent=error.message}finally{waiting=false;wait.disabled=false;wait.innerHTML='.$telegramRadarJs.'+idle}};verify.addEventListener("click",async()=>{verify.disabled=true;feedback.textContent="";try{const json=await request("verify_telegram_bot");showBot(json.bot);feedback.className="small text-success mt-3";feedback.textContent=json.message}catch(error){status.className="alert alert-danger rounded-4 border-0 mb-3";statusTitle.textContent=error.message;statusText.textContent=""}finally{verify.disabled=false}});openBot.addEventListener("click",event=>{if(openBot.classList.contains("disabled")){event.preventDefault();return}startWaiting()});wait.addEventListener("click",startWaiting)})();</script>';

    return $h;
}
function usersPage(){
    $userActionModals='';$qv=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$per=25;$where='1=1';$params=[];if($qv!==''){$where='(l LIKE ? OR n LIKE ? OR role LIKE ?)';$like='%'.$qv.'%';$params=[$like,$like,$like];}$total=(int)q('SELECT COUNT(*) FROM users WHERE '.$where,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$rows=all('SELECT id,l,n,role,st,ca,ua FROM users WHERE '.$where.' ORDER BY id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);$edit=isset($_GET['uid'])?user_row((int)$_GET['uid']):null;
    $actions='<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">'.icon('plus-lg').' '.h(T('new_user')).'</button>';$h=page_head(T('users'),h(T('role_capabilities')),$actions);
    $h.='<div class="row g-3 mb-3">';foreach(['admin','developer','editor','viewer'] as $role)$h.='<div class="col-12 col-md-6 col-xl-3"><div class="role-card"><div class="fw-bold mb-1">'.h(T($role)).'</div><div class="small text-muted">'.h(role_description($role)).'</div></div></div>';$h.='</div>';
    if(!$rows&&$qv==='')$h.=empty_state(T('users'),T('role_capabilities'),'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">'.icon('plus-lg').' '.h(T('new_user')).'</button>');
    else{$table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr><th>'.h(T('username')).'</th><th>'.h(T('display_name')).'</th><th>'.h(T('role')).'</th><th>'.h(T('status')).'</th><th>'.h(T('created')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
        foreach($rows as $u){$uid=(int)$u['id'];$self=$uid===current_user_id();$items=[dd_link(T('edit_user'),U(['users'=>1,'uid'=>$uid]),'pencil')];if(!$self){$canUnlink=($u['role']??'viewer')!=='admin'&&project_membership_role($uid,current_project_id())!==null;if($canUnlink){$mid='userProjectAction'.$uid;$items[]=dd_modal(T('user_actions'),'#'.$mid,'person-dash',true);$userActionModals.=user_project_action_modal($u,$mid);}else $items[]=universal_delete_button(T('delete_user'),'del_user',['id'=>$uid],T('delete_user'),user_delete_message($u),true);}$table.='<tr><td><span class="fw-semibold">'.h($u['l']).'</span>'.($self?' <span class="badge text-bg-dark">me</span>':'').'</td><td>'.h($u['n']).'</td><td><span class="badge text-bg-light">'.h(T(in_array($u['role'],['admin','developer','editor','viewer'],true)?$u['role']:'viewer')).'</span></td><td><span class="badge '.($u['st']==='active'?'text-bg-success':'text-bg-secondary').'">'.h(T($u['st']==='active'?'active':'inactive')).'</span></td><td>'.h($u['ca']).'</td><td class="text-end">'.dd_menu($items).'</td></tr>';}$table.='</tbody></table>';$h.=table_wrap($table,server_search_form(),pager_html($m));}
    return $h.user_modal($edit).$userActionModals;
}

function user_modal($u=null){
    $id=(int)($u['id']??0);$role=$u['role']??'editor';$st=$u['st']??'active';$self=$id&&$id===current_user_id();$members=$id?user_project_memberships($id):[];
    $body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('l',T('username'),$u['l']??'','text',['required'=>true,'autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('n',T('display_name'),$u['n']??'').'</div></div>'.inp('p',$id?T('new_password'):T('password'),'','password',$id?['autocomplete'=>'new-password']:['required'=>true,'autocomplete'=>'new-password']).($id?'<div class="form-text mb-3">'.h(T('password_hint')).'</div>':'').'<div class="row g-3"><div class="col-md-6">'.select_html('role',T('role'),['admin'=>T('admin'),'developer'=>T('developer'),'editor'=>T('editor'),'viewer'=>T('viewer')],$role,['class'=>'form-select js-global-user-role']).'</div><div class="col-md-6">'.select_html('st',T('status'),['active'=>T('active'),'inactive'=>T('inactive')],$st,$self?['disabled'=>true]:[]).($self?'<input type="hidden" name="st" value="active">':'').'</div></div>';
    $projectRows='';$projectRoleOpts=[''=>T('access_denied'),'developer'=>T('developer'),'editor'=>T('editor'),'viewer'=>T('viewer')];foreach(all_projects() as $pr){$pid=(int)$pr['id'];$selected=$members[$pid]??(!$id&&$pid===current_project_id()?'editor':'');$projectRows.='<div class="row g-2 align-items-center py-2 border-bottom"><div class="col-12 col-md"><div class="fw-semibold">'.h($pr['n']).'</div><div class="small text-muted">'.h($pr['s']).'</div></div><div class="col-12 col-md-5">'.select_html('project_roles['.$pid.']',T('project_role'),$projectRoleOpts,$selected,['data-no-old'=>true]).'</div></div>';}
    $body.='<div class="border rounded-4 p-3 mt-2 js-project-access"><div class="d-flex gap-2 align-items-center mb-2"><span class="btn btn-light btn-icon disabled">'.icon('shield-lock').'</span><div><div class="fw-semibold">'.h(T('project_access')).'</div><div class="small text-muted">'.h(T('role_capabilities')).'</div></div></div><div class="alert alert-light py-2 small js-admin-access-hint '.($role==='admin'?'':'d-none').'">'.h(T('admin')).': '.h(T('role_admin_desc')).'</div>'.$projectRows.'<div class="form-text mt-2">'.h(T('project_access_remove_hint')).'</div></div>';
    $footer='<span class="me-auto"></span><button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal('userModal',$id?T('edit_user'):T('new_user'),'user',$body,$footer,'modal-xl');
}
function user_delete_message($u){
    $count=(int)q('SELECT COUNT(*) FROM user_projects WHERE uid=?',[(int)$u['id']])->fetchColumn();
    return T('delete_user_everywhere_hint')."

".T('projects').': '.$count;
}
function user_project_action_modal($u,$mid){
    $uid=(int)$u['id'];$pid=current_project_id();$pr=current_project();$return=U(['users'=>1]);
    $unlink=post_form('remove_user_project_access','<input type="hidden" name="id" value="'.$uid.'"><input type="hidden" name="pid" value="'.$pid.'"><input type="hidden" name="_return" value="'.h($return).'"><button class="btn btn-outline-primary w-100">'.icon('person-dash').' '.h(T('remove_user_from_project_only')).'</button>');
    $delete=universal_delete_button(T('delete_user_everywhere'),'del_user',['id'=>$uid],T('delete_user'),user_delete_message($u),true,'btn btn-danger w-100','trash3',T('delete_user_everywhere'));
    $body='<p class="text-muted">'.h(T('user_actions_hint')).'</p><div class="alert alert-light rounded-4 border-0"><strong>'.h($u['n']?:$u['l']).'</strong><div class="small text-muted mt-1">'.h($pr['n']??'').'</div></div><div class="d-grid gap-3"><div class="border rounded-4 p-3"><div class="d-flex align-items-start gap-3 mb-3"><span class="btn btn-light btn-icon disabled">'.icon('person-dash').'</span><div><div class="fw-semibold">'.h(T('remove_user_from_project_only')).'</div><div class="small text-muted mt-1">'.h(T('remove_user_from_project_only_hint')).'</div></div></div>'.$unlink.'</div><div class="border border-danger-subtle rounded-4 p-3 bg-danger-subtle"><div class="d-flex align-items-start gap-3 mb-3"><span class="btn btn-danger btn-icon disabled">'.icon('trash3').'</span><div><div class="fw-semibold text-danger">'.h(T('delete_user_everywhere')).'</div><div class="small text-muted mt-1">'.h(T('delete_user_everywhere_hint')).'</div></div></div>'.$delete.'</div></div>';
    return modal($mid,T('user_actions'),$body,'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button>','modal-md');
}
function delete_user_modal($u,$mid){
    $body='<input type="hidden" name="id" value="'.(int)$u['id'].'"><p class="text-preline">'.h(user_delete_message($u)).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($u['l']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_user'),'del_user',$body,$footer);
}
function collection_entries_header($c,$endpoint,$mode='multiple',$showCreate=true){
    $cid=(int)$c['id'];$nested=collection_is_nested($c);$parent=$nested?collection_parent($c):null;$parentEntryId=$nested?nested_parent_entry_id($c):0;
    $backUrl=$nested?($parentEntryId?U(['c'=>(int)$parent['id'],'entry'=>$parentEntryId]):U(['c'=>(int)($parent['id']??0)])):U(['collections'=>1]);
    $back=workspace_back_icon($backUrl);
    $meta='<span class="badge text-bg-light">'.h(T($mode==='single'?'single':'multiple')).'</span>'.($nested?'<span class="badge text-bg-info">'.h(T('nested_collection')).'</span><span class="badge text-bg-light">'.h($parent['n']??'').'</span>':collection_sections_badges($cid));
    $main='<div class="collection-workspace-main">'.$back.'<div class="collection-workspace-title"><h1>'.h($c['n']).'</h1><div class="collection-workspace-meta">'.$meta.'</div></div></div>';
    $actions='';
    if($showCreate&&can_entries()&&$mode!=='single'&&(!$nested||$parentEntryId))$actions.='<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0]+($parentEntryId?['parent_entry'=>$parentEntryId]:[]))).'">'.icon('plus-lg').' '.h(T('new_entry')).'</a>';
    if(can_schema())$actions.='<a class="btn btn-light" href="'.h(U(['c'=>$cid,'fields'=>1]+($parentEntryId?['parent_entry'=>$parentEntryId]:[]))).'">'.icon('list-check').' '.h(T('fields')).'</a>';
    $items=[];
    if(can_schema())$items[]=dd_modal(T('edit_collection'),'#collectionEditModal','pencil');
    if(can_schema()&&!$nested)$items[]=dd_link(T('new_nested_collection'),U(['c'=>$cid,'new_nested'=>$cid]),'diagram-3');
    if(!$nested){$items[]='<li><button type="button" class="dropdown-item rounded-3 d-flex align-items-center gap-2 js-copy" data-copy="'.h($endpoint).'">'.icon('copy').'<span>'.h(T('copy_endpoint')).'</span></button></li>';$items[]=dd_link(T('api'),$endpoint,'braces','_blank');}
    if(can_schema())$items[]=universal_delete_button(T('delete_collection'),'del_col',['id'=>$cid],T('delete_collection'),collection_delete_message($c),true,'dropdown-item','trash3',T('delete_collection'));
    if($items){
        static $collectionHeadMenu=0;$collectionHeadMenu++;$menuId='collectionHeadMenu'.$collectionHeadMenu;
        $actions.='<div class="dropdown"><button type="button" class="btn btn-light dropdown-toggle" id="'.h($menuId).'" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false">'.icon('three-dots').' '.h(T('actions')).'</button><ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-4 p-2" aria-labelledby="'.h($menuId).'">'.implode('',$items).'</ul></div>';
    }
    return '<div class="collection-workspace-head">'.$main.($actions?'<div class="collection-workspace-actions">'.$actions.'</div>':'').'</div>';
}
function collection_entries_empty_state($title,$text,$cta=''){
    return '<div class="ios-surface collection-empty-state"><div class="collection-empty-state-inner"><div class="collection-empty-state-icon">'.icon('inbox').'</div><h2>'.h($title).'</h2><p class="text-muted">'.h($text).'</p>'.$cta.'</div></div>';
}
function collection_related_targets(array $collection,string $relationType='relation'):array{
    $cid=(int)($collection['id']??0);$pid=(int)($collection['pid']??current_project_id());if(!$cid||!$pid||!field_is_relation_type($relationType))return [];
    return RequestCache::remember('collection-related-targets:'.$pid.':'.$cid.':'.$relationType.':'.resource_display_lang(),function()use($cid,$pid,$relationType){
        $order=[];foreach(fields($cid,$pid) as $field){if((string)($field['t']??'')!==$relationType)continue;$targetId=(int)(field_options($field)['target_collection_id']??0);if(!$targetId||isset($order[$targetId]))continue;$target=col($targetId,$pid);if(!$target||($relationType==='nested_relation'?!collection_is_nested($target):collection_is_nested($target)))continue;$order[$targetId]=count($order);}
        if(!$order)return [];$ids=array_keys($order);$marks=implode(',',array_fill(0,count($ids),'?'));$rows=all("SELECT c.*,(SELECT COUNT(*) FROM e WHERE e.cid=c.id) AS entry_count FROM c WHERE c.pid=? AND c.id IN ($marks)",array_merge([$pid],$ids));$rows=localize_resource_rows($rows,resource_display_lang());usort($rows,fn($a,$b)=>($order[(int)$a['id']]??PHP_INT_MAX)<=>($order[(int)$b['id']]??PHP_INT_MAX));return $rows;
    });
}
function collection_related_targets_section(array $targets,bool $nested=false):string{
    if(!$targets)return '';$cards='';
    foreach($targets as $target){
        $targetId=(int)$target['id'];$mode=collection_mode($target);$count=(int)($target['entry_count']??0);$parent=$nested?collection_parent($target,(int)($target['pid']??0)):null;$name=$nested&&$parent?($parent['n'].' → '.$target['n']):$target['n'];
        $cards.='<div class="col-12 col-md-6 col-xl-4"><a class="ios-surface d-flex align-items-center gap-3 p-3 text-decoration-none text-reset h-100" href="'.h(U(['c'=>$targetId])).'"><span class="btn btn-light btn-icon flex-shrink-0">'.icon($nested?'diagram-3':'collection').'</span><span class="min-w-0 flex-grow-1"><span class="d-block fw-semibold text-truncate">'.h($name).'</span><code class="d-block text-truncate mt-1">'.h($target['s']).'</code><span class="d-flex flex-wrap gap-2 mt-2"><span class="badge text-bg-light">'.h(T($mode==='single'?'single':'multiple')).'</span><span class="badge text-bg-light">'.h(T('entry_count')).': '.$count.'</span></span></span><span class="text-muted flex-shrink-0">'.icon('chevron-right').'</span></a></div>';
    }
    $title=$nested?T('related_nested_collections'):T('related_collections');$hint=$nested?T('related_nested_collections_hint'):T('related_collections_hint');
    return '<section class="mt-4"><div class="d-flex align-items-start justify-content-between gap-3 mb-3"><div><h2 class="h5 mb-1">'.h($title).'</h2><p class="text-muted small mb-0">'.h($hint).'</p></div><span class="badge text-bg-light">'.count($targets).'</span></div><div class="row g-3">'.$cards.'</div></section>';
}
function collection_related_collections_html(array $collection):string{
    return collection_related_targets_section(collection_related_targets($collection,'relation'),false).collection_related_targets_section(collection_related_targets($collection,'nested_relation'),true);
}

function collection_nested_collections_html(array $collection,?array $parentEntry=null,bool $embedded=false):string{
    if(collection_is_nested($collection))return '';$children=nested_cols((int)$collection['id']);$canCreate=can_schema();if(!$children&&!$canCreate)return '';
    $parentEntryId=(int)($parentEntry['id']??0);if(!$parentEntryId&&collection_mode($collection)==='single')$parentEntryId=(int)(scalar('SELECT id FROM e WHERE cid=? ORDER BY id LIMIT 1',[(int)$collection['id']])?:0);
    $cardClass=$embedded?'nested-collection-card':'ios-surface';
    $cards='';foreach($children as $child){$cid=(int)$child['id'];$count=$parentEntryId?(int)scalar('SELECT COUNT(*) FROM e WHERE cid=? AND parent_eid=?',[$cid,$parentEntryId]):(int)($child['entry_count']??0);$href=nested_collection_url($child,$parentEntryId);$cards.='<div class="col-12 col-md-6 col-xl-4"><a class="'.$cardClass.' d-flex align-items-center gap-3 p-3 text-decoration-none text-reset h-100" href="'.h($href).'"><span class="btn btn-light btn-icon flex-shrink-0">'.icon('diagram-3').'</span><span class="min-w-0 flex-grow-1"><span class="d-block fw-semibold text-truncate">'.h($child['n']).'</span><code class="d-block text-truncate mt-1">'.h($child['s']).'</code><span class="d-flex flex-wrap gap-2 mt-2"><span class="badge text-bg-light">'.h(T(collection_mode($child)==='single'?'single':'multiple')).'</span><span class="badge text-bg-light">'.h(T('entry_count')).': '.$count.'</span></span></span><span class="text-muted flex-shrink-0">'.icon('chevron-right').'</span></a></div>';}
    $create=$canCreate?'<a class="btn btn-light" href="'.h(U(['c'=>(int)$collection['id'],'new_nested'=>(int)$collection['id']])).'">'.icon('plus-lg').' '.h(T('new_nested_collection')).'</a>':'';
    if(!$cards)$cards='<div class="col-12"><div class="'.($embedded?'nested-collection-empty':'ios-surface').' p-4 text-muted">'.h(T('nested_collections_hint')).'</div></div>';
    $content='<div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-3"><div><h2 class="h5 mb-1">'.h(T('nested_collections')).'</h2><p class="text-muted small mb-0">'.h(T('nested_collections_hint')).'</p></div>'.$create.'</div><div class="row g-3">'.$cards.'</div>';
    return $embedded?'<div class="nested-collections-embedded">'.$content.'</div>':'<section class="mt-4">'.$content.'</section>';
}
function entry_nested_collections_html(array $collection,?array $entry,bool $embedded=false):string{return $entry?collection_nested_collections_html($collection,$entry,$embedded):'';}
function nested_parent_picker_html(array $nested):string{
    $parent=collection_parent($nested);if(!$parent)return empty_state(T('parent_collection'),T('access_denied'));
    $rows=all('SELECT id,t,s,st,ua FROM e WHERE cid=? ORDER BY ua DESC,id DESC',[(int)$parent['id']]);$head=collection_entries_header($nested,'',collection_mode($nested),false);
    if(!$rows)return $head.collection_entries_empty_state(T('choose_parent_entry'),T('nested_requires_parent_entry'),can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>(int)$parent['id'],'entry'=>0])).'">'.icon('plus-lg').' '.h(T('create_first_entry')).'</a>':'');
    $cards='';foreach($rows as $row){$count=(int)scalar('SELECT COUNT(*) FROM e WHERE cid=? AND parent_eid=?',[(int)$nested['id'],(int)$row['id']]);$cards.='<div class="col-12 col-md-6 col-xl-4"><a class="ios-surface p-3 d-flex align-items-center gap-3 text-decoration-none text-reset h-100" href="'.h(nested_collection_url($nested,(int)$row['id'])).'"><span class="btn btn-light btn-icon">'.icon('file-earmark-text').'</span><span class="flex-grow-1 min-w-0"><span class="fw-semibold d-block text-truncate">'.h($row['t']).'</span><code class="small">'.h($row['s']).'</code><span class="badge text-bg-light ms-2">'.$count.'</span></span>'.icon('chevron-right').'</a></div>';}
    return $head.'<div class="ios-surface p-4 mb-4"><h2 class="h5 mb-1">'.h(T('choose_parent_entry')).'</h2><p class="text-muted mb-0">'.h(T('choose_parent_entry_hint')).'</p></div><div class="row g-3">'.$cards.'</div>';
}
function nested_rows(array $c):string{
    $parentEid=nested_parent_entry_id($c);if(!$parentEid)return nested_parent_picker_html($c);$parentEntry=one('SELECT * FROM e WHERE id=?',[$parentEid]);if(!$parentEntry)return nested_parent_picker_html($c);
    $cid=(int)$c['id'];$h=collection_entries_header($c,'',collection_mode($c),true);
    if(collection_mode($c)==='single'){$e=single_entry($c,can_entries(),$parentEid);if(!$e)return $h.collection_entries_empty_state(T('no_entries'),T('create_first_entry'),'');$author=$e['uid']?(user_row((int)$e['uid'])?:null):null;$edit=can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>$e['id'],'parent_entry'=>$parentEid])).'">'.icon('pencil').' '.h(T('open_editor')).'</a>':'<a class="btn btn-light" href="'.h(U(['c'=>$cid,'entry'=>$e['id'],'parent_entry'=>$parentEid])).'">'.icon('eye').' '.h(T('open')).'</a>';return $h.'<div class="ios-surface p-4"><div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3"><div><h2 class="h4 mb-2">'.h($e['t']).'</h2><div class="d-flex flex-wrap gap-2"><code>'.h($e['s']).'</code><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span><span class="text-muted small">'.h(T('parent_entry')).': '.h($parentEntry['t']).'</span><span class="text-muted small">'.h(T('last_author')).': '.h($author['n']??$author['l']??'—').'</span></div></div>'.$edit.'</div></div>';}
    $qv=trim((string)($_GET['q']??''));$status=in_array((string)($_GET['status']??''),['draft','published'],true)?(string)$_GET['status']:'';$page=max(1,(int)($_GET['page']??1));$per=min(100,max(10,(int)($_GET['per']??25)));$where='e.cid=? AND e.parent_eid=?';$params=[$cid,$parentEid];if($status!==''){$where.=' AND e.st=?';$params[]=$status;}if($qv!==''){$where.=' AND (e.t LIKE ? OR e.s LIKE ?)';$like='%'.$qv.'%';$params[]=$like;$params[]=$like;}$total=(int)scalar('SELECT COUNT(*) FROM e WHERE '.$where,$params);$m=pagination_meta($total,$page,$per);$rows=all('SELECT e.*,u.n AS author_name,u.l AS author_login FROM e LEFT JOIN users u ON u.id=e.uid WHERE '.$where.' ORDER BY e.ua DESC,e.id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);
    if(!$rows&&$qv===''&&$status==='')return $h.collection_entries_empty_state(T('no_entries'),T('create_first_entry'),can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0,'parent_entry'=>$parentEid])).'">'.icon('plus-lg').' '.h(T('create_first_entry')).'</a>':'');
    $table='<table class="table table-hover align-middle mb-0 cms-responsive"><thead><tr><th>'.h(T('title')).'</th><th>'.h(T('slug')).'</th><th>'.h(T('status')).'</th><th>'.h(T('updated')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';foreach($rows as $e){$url=U(['c'=>$cid,'entry'=>(int)$e['id'],'parent_entry'=>$parentEid]);$items=[];if(can_entries())$items[]=universal_delete_button(T('delete'),'del_entry',['id'=>(int)$e['id'],'cid'=>$cid],T('delete'),T('delete_entry_q'),true);$table.='<tr><td><a class="link-dark fw-semibold" href="'.h($url).'">'.h($e['t']).'</a></td><td><code>'.h($e['s']).'</code></td><td><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span></td><td>'.h($e['ua']).'</td><td class="text-end">'.dd_menu($items).'</td></tr>';}$table.='</tbody></table>';return $h.table_wrap($table,entry_filter_form($status),pager_html($m));
}
function single_rows($c){
    $cid=(int)$c['id'];$e=single_entry($c,can_entries());$endpoint=$e?U(['api'=>'entry','c'=>$c['s'],'s'=>$e['s'],'lang'=>default_content_lang()]):U(['api'=>'entries','c'=>$c['s']]);
    $h=collection_entries_header($c,$endpoint,'single',false);
    if(!$e)return $h.collection_entries_empty_state(T('no_entries'),T('create_first_entry'),can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0])).'">'.icon('plus-lg').' '.h(T('create_first_entry')).'</a>':'').collection_related_collections_html($c).collection_nested_collections_html($c);
    recent_entry_add($e);$edit=can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.icon('pencil').' '.h(T('open_editor')).'</a>':'<a class="btn btn-light" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.icon('eye').' '.h(T('open')).'</a>';$author=$e['uid']?(user_row((int)$e['uid'])?:null):null;
    $nested=collection_nested_collections_html($c,$e,true);
    $h.='<div class="ios-surface p-4"><div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3"><div><h2 class="h4 mb-2">'.h($e['t']).'</h2><div class="d-flex flex-wrap gap-2"><code>'.h($e['s']).'</code><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span><span class="text-muted small">'.h($e['ua']).'</span><span class="text-muted small">'.h(T('last_author')).': '.h($author['n']??$author['l']??'—').'</span></div></div>'.$edit.'</div>'.$nested.'</div>';
    return $h.collection_related_collections_html($c);
}
function rows($c){
    if(collection_is_nested($c))return nested_rows($c);$cid=(int)$c['id'];if(collection_mode($c)==='single')return single_rows($c);
    $qv=trim((string)($_GET['q']??''));$status=in_array((string)($_GET['status']??''),['draft','published'],true)?(string)$_GET['status']:'';$page=max(1,(int)($_GET['page']??1));$per=min(100,max(10,(int)($_GET['per']??25)));$sort=request_sort(['title','slug','status','author','updated'],'updated');$dir=request_dir();$map=['title'=>'e.t','slug'=>'e.s','status'=>'e.st','author'=>'COALESCE(u.n,u.l)','updated'=>'e.ua'];$where='e.cid=?';$params=[$cid];if($status!==''){$where.=' AND e.st=?';$params[]=$status;}if($qv!==''){$where.=' AND (e.t LIKE ? OR e.s LIKE ? OR e.st LIKE ? OR u.n LIKE ? OR u.l LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like,$like,$like);}$total=(int)q('SELECT COUNT(*) FROM e LEFT JOIN users u ON u.id=e.uid WHERE '.$where,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$r=all('SELECT e.*,u.n AS author_name,u.l AS author_login FROM e LEFT JOIN users u ON u.id=e.uid WHERE '.$where.' ORDER BY '.$map[$sort].' '.strtoupper($dir).' LIMIT '.$per.' OFFSET '.$m['offset'],$params);$endpoint=U(['api'=>'entries','c'=>$c['s'],'lang'=>default_content_lang()]);
    $showCreate=can_entries()&&($r||$qv!==''||$status!=='');
    $h=collection_entries_header($c,$endpoint,'multiple',$showCreate);
    if(!$r&&$qv===''&&$status===''){return $h.collection_entries_empty_state(T('no_entries'),T('create_first_entry'),can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0])).'">'.icon('plus-lg').' '.h(T('create_first_entry')).'</a>':'').collection_related_collections_html($c).collection_nested_collections_html($c);}
    $table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr><th>'.sort_link(T('title'),'title',$sort,$dir).'</th><th>'.sort_link(T('slug'),'slug',$sort,$dir).'</th><th>'.sort_link(T('status'),'status',$sort,$dir).'</th><th>'.sort_link(T('last_author'),'author',$sort,$dir).'</th><th>'.sort_link(T('updated'),'updated',$sort,$dir).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($r as $e){$open=can_entries()?'<a class="btn btn-light btn-icon" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'" aria-label="'.h(T('open_editor')).'">'.icon('pencil').'</a>':'<a class="btn btn-light btn-icon" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'" aria-label="'.h(T('open')).'">'.icon('eye').'</a>';$api=U(['api'=>'entry','c'=>$c['s'],'s'=>$e['s'],'lang'=>default_content_lang()]);$items=[dd_link(T('api'),$api,'braces','_blank')];if(can_entries())$items[]=universal_delete_button(T('delete'),'del_entry',['id'=>(int)$e['id'],'cid'=>$cid],T('delete'),T('delete_entry_q'),true);$author=$e['author_name']?:$e['author_login']?:'—';$table.='<tr><td><a class="link-dark fw-semibold" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.h($e['t']).'</a></td><td><code>'.h($e['s']).'</code></td><td><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span></td><td>'.h($author).'</td><td>'.h($e['ua']).'</td><td class="text-end"><div class="d-inline-flex gap-2">'.$open.dd_menu($items).'</div></td></tr>';}
    $table.='</tbody></table>';
    return $h.table_wrap($table,entry_filter_form($status),pager_html($m)).collection_related_collections_html($c).collection_nested_collections_html($c);
}

function delete_collection_modal($c){
    $body='<input type="hidden" name="id" value="'.(int)$c['id'].'"><p>'.h(T('delete_collection_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($c['n']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-target="#collectionEditModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal('deleteCollectionModal',T('delete_collection'),'del_col',$body,$footer);
}
function delete_collection_from_list_modal($c,$mid){
    $body='<input type="hidden" name="id" value="'.(int)$c['id'].'"><p>'.h(T('delete_collection_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($c['n']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-target="#collectionsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_collection'),'del_col',$body,$footer);
}
function fieldsPage($c){
    $cid=(int)$c['id'];$parentEid=collection_is_nested($c)?nested_parent_entry_id($c):0;$edit=isset($_GET['fid'])?field((int)$_GET['fid']):null;$fs=fields($cid);
    $actions='<a class="btn btn-outline-dark" href="'.h(U(['c'=>$cid]+($parentEid?['parent_entry'=>$parentEid]:[]))).'">'.icon('arrow-left').' '.h(T('entries')).'</a>'.collection_manage_buttons($c,'modal',true).'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">'.icon('plus-lg').' '.h(T('new_field')).'</button>';
    $h=page_head(T('fields').': '.$c['n'],h(T('drag_to_sort')),$actions);
    if(!$fs)return $h.empty_state(T('no_fields'),T('create_first_field'),'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">'.icon('plus-lg').' '.h(T('create_first_field')).'</button>').field_modal($c,$edit);
    $table='<table class="table table-hover align-middle mb-0 cms-responsive"><thead><tr><th></th><th>'.h(T('label')).'</th><th>'.h(T('key')).'</th><th>'.h(T('type')).'</th><th>'.h(T('required')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody class="js-sortable-fields" data-sort-action="reorder_fields">';
    foreach($fs as $f){$items=[dd_link(T('edit_field'),U(['c'=>$cid,'fields'=>1,'fid'=>$f['id']]),'pencil'),universal_delete_button(T('delete'),'del_field',['id'=>(int)$f['id'],'cid'=>$cid],T('delete'),T('delete_field_q'),true)];$table.='<tr draggable="true" data-sort-id="'.(int)$f['id'].'"><td><button type="button" class="btn btn-light btn-icon drag-handle" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button></td><td>'.h($f['l']).'</td><td><code>'.h($f['k']).'</code></td><td><span class="badge text-bg-light">'.h($f['t']).'</span></td><td>'.h($f['r']?T('yes'):T('no')).'</td><td class="text-end">'.dd_menu($items).'</td></tr>';}
    $table.='</tbody></table>';
    return $h.table_wrap($table).field_modal($c,$edit);
}

function field_modal($c,$edit=null){
    $cid=(int)$c['id'];$isEdit=(bool)$edit;$types=['text'=>T('type_text'),'text_global'=>T('type_text_global'),'textarea'=>'textarea','ul_list'=>T('type_ul_list'),'ol_list'=>T('type_ol_list'),'ul_list_i18n'=>T('type_ul_list_i18n'),'ol_list_i18n'=>T('type_ol_list_i18n'),'html'=>'html','email'=>'email','tel'=>'tel','number'=>'number','integer'=>'integer','date'=>'date','datetime'=>'datetime-local','bool'=>'bool','url'=>'url','image'=>'image','file'=>'file','json'=>'json','relation'=>T('relation'),'nested_relation'=>T('nested_relation')];
    $fieldPresets=[''=>T('custom'),'content'=>'Content / content / html','excerpt'=>'Excerpt / excerpt / textarea','image'=>'Image / image / image','file'=>'File / file / file','date'=>'Date / date / date','url'=>'URL / url / url','relation'=>'Relation / relation / relation','nested_relation'=>T('nested_relation').' / nested_relation / nested_relation'];
    $opt=$edit?field_options($edit):[];$rules=validation_rules_from_options($opt);$target=(int)($opt['target_collection_id']??0);$mode=($opt['mode']??'single')==='multiple'?'multiple':'single';$fieldType=(string)($edit['t']??'text');$lock=$isEdit?['disabled'=>true,'data-no-old'=>true]:[];
    $rel='<div class="cms-relation-options'.($fieldType==='relation'?'':' d-none').'">'.select_html('rel_cid',T('target_collection'),relation_target_options($cid),$fieldType==='relation'?$target:0,$lock).select_html('rel_mode',T('relation_mode'),['single'=>T('relation_single'),'multiple'=>T('relation_multiple')],$mode,$lock).'</div>';
    $nestedRel='<div class="cms-nested-relation-options'.($fieldType==='nested_relation'?'':' d-none').'">'.select_html('nested_rel_cid',T('target_nested_collection'),nested_relation_target_options($cid),$fieldType==='nested_relation'?$target:0,$lock).select_html('nested_rel_mode',T('relation_mode'),['single'=>T('relation_single'),'multiple'=>T('relation_multiple')],$mode,$lock).'</div>';
    $preset=$edit?'<div class="alert alert-light small">'.h(T('field_schema_locked')).'</div>':'<div class="alert alert-light"><div class="fw-semibold mb-2">'.h(T('field_preset')).'</div>'.select_html('_field_preset',T('field_preset'),$fieldPresets,'',['class'=>'form-select js-field-preset']).'</div>';$keyAttrs=$isEdit?['disabled'=>true,'data-no-old'=>true]:['data-slug-target'=>'1'];$typeAttrs=array_merge(['class'=>'form-select js-field-type'],$lock);
    $choices=implode("\n",array_map('strval',(array)($rules['choices']??[])));$ruleBox='<details class="border rounded-4 p-3 mt-3" open><summary class="fw-semibold mb-3">'.h(T('field_rules')).'</summary><div class="row g-3"><div class="col-md-6">'.inp('min_length',T('min_length'),$rules['min_length']??'','number',['min'=>0,'data-no-old'=>true]).'</div><div class="col-md-6">'.inp('max_length',T('max_length'),$rules['max_length']??'','number',['min'=>0,'data-no-old'=>true]).'</div><div class="col-md-6">'.inp('min',T('min_value'),$rules['min']??'','number',['step'=>'any','data-no-old'=>true]).'</div><div class="col-md-6">'.inp('max',T('max_value'),$rules['max']??'','number',['step'=>'any','data-no-old'=>true]).'</div><div class="col-12">'.inp('regex',T('pattern_regex'),$rules['regex']??'','text',['placeholder'=>'~^[A-Z].+$~u','data-no-old'=>true]).'</div><div class="col-12">'.inp('default',T('default_value'),$rules['default']??'','text',['data-no-old'=>true]).'</div><div class="col-12">'.area('choices',T('allowed_values'),$choices,['rows'=>3,'data-no-old'=>true]).'<div class="form-text">'.h(T('allowed_values_hint')).'</div></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="unique" value="1" id="fieldUnique" '.(!empty($rules['unique'])?'checked':'').'><label class="form-check-label" for="fieldUnique">'.h(T('unique_value')).'</label></div></div></div></details>';
    $body='<input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'">'.$preset.inp('l',T('label'),$edit['l']??'','text',['required'=>true,'data-slug-source'=>'k']).inp('k',T('key'),$edit['k']??'','text',$keyAttrs).select_html('t',T('type'),$types,$fieldType,$typeAttrs).$rel.$nestedRel.'<div class="form-check"><input class="form-check-input" type="checkbox" name="r" value="1" id="req" '.(!empty(old_value('r',$edit['r']??0))?'checked':'').'><label class="form-check-label" for="req">'.h(T('required')).'</label></div>'.$ruleBox;
    $delete=$edit?universal_delete_button(T('delete'),'del_field',['id'=>(int)$edit['id'],'cid'=>$cid],T('delete'),T('delete_field_q'),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('fieldModal',$edit?T('edit_field'):T('new_field'),'field',$body,$footer,'modal-xl');
}
function delete_field_modal($c,$f,$mid='deleteFieldModal'){
    $body='<input type="hidden" name="cid" value="'.(int)$c['id'].'"><input type="hidden" name="id" value="'.(int)$f['id'].'"><p>'.h(T('delete_field_q')).'</p><div class="alert alert-warning rounded-4 border-0 mb-0">'.h($f['l']).' / '.h($f['k']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-target="#fieldModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete'),'del_field',$body,$footer);
}

function list_editor_html(string $name,string $label,mixed $value,bool $required,string $type):string{
    $items=normalize_list_value($value);if(!$items)$items=[''];$tag=field_list_tag($type);$inputName=$name.'[]';
    $renderItem=static function(string $item,string $inputName):string{return '<li class="list-editor-item js-list-item"><div class="list-editor-row"><input type="text" class="form-control rounded-4 bg-body-tertiary border-0 js-list-item-input" name="'.h($inputName).'" value="'.h($item).'" aria-label="'.h(T('list_item')).'"><div class="list-editor-controls"><button type="button" class="btn btn-light btn-icon js-list-up" aria-label="'.h(T('move_up')).'">'.icon('arrow-up').'</button><button type="button" class="btn btn-light btn-icon js-list-down" aria-label="'.h(T('move_down')).'">'.icon('arrow-down').'</button><button type="button" class="btn btn-light btn-icon text-danger js-list-delete" aria-label="'.h(T('delete')).'">'.icon('trash3').'</button></div></div></li>';};
    $rows='';foreach($items as $item)$rows.=$renderItem((string)$item,$inputName);
    $template=$renderItem('',$inputName);
    return '<div class="mb-3 js-list-editor" data-required="'.($required?'1':'0').'" data-required-message="'.h(T('required_missing').': '.$label).'">'
        .'<label class="form-label">'.h($label).'</label><div class="list-editor-structure">'
        .'<'.$tag.' class="list-editor-list js-list-items">'.$rows.'</'.$tag.'>'
        .'<button type="button" class="btn btn-light btn-sm mt-2 js-list-add">'.icon('plus-lg').' '.h(T('add_list_item')).'</button>'
        .'</div><div class="form-text">'.h(T('list_items_hint')).'</div><template class="js-list-item-template">'.$template.'</template></div>';
}

function entry_field_controls($c,$e,$cl,array $values,string $prefix='d',?string $fileLang=null,string $idScope='',bool $requireHtml=true,string $scopeFilter='all'){
    $cid=(int)$c['id'];$body='';$scope=preg_replace('/[^a-z0-9_]+/i','_',$idScope!==''?$idScope:($fileLang??'base'))?:'base';
    $fieldName=static fn(string $key,bool $multi=false):string=>$prefix.'['.$key.']'.($multi?'[]':'');
    $fileName=static fn(string $base,string $key):string=>$fileLang===null?$base.'['.$key.']':$base.'['.$fileLang.']['.$key.']';
    foreach(fields($cid) as $f){
        $translatable=field_is_translatable($f);if($scopeFilter==='translated'&&!$translatable)continue;if($scopeFilter==='global'&&$translatable)continue;
        $k=(string)$f['k'];$opt=field_options($f);$valueExists=array_key_exists($k,$values);$v=$valueExists?$values[$k]:($opt['default']??'');$required=!empty($f['r'])&&$requireHtml;$req=$required?['required'=>true]:[];$fieldLabel=field_text($f,'l',$cl)?:($f['l']??$k);$label=$fieldLabel.(!empty($f['r'])?' *':'');$placeholder=field_text($f,'placeholder',$cl);if($placeholder!=='')$req['placeholder']=$placeholder;$name=$fieldName($k);$safeKey=preg_replace('/[^a-z0-9_]+/i','_',$k)?:'field';
        if($f['t']==='html'){
            $tid='html_'.$scope.'_'.$safeKey;$textarea=area($name,$label,$v,array_merge($req,['class'=>'form-control rounded-4 bg-body-tertiary border-0 js-html-source','data-html-preview'=>'#'.$tid.'_preview']));$body.='<div class="html-editor mb-3"><ul class="nav nav-pills gap-2 mb-2" role="tablist"><li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#'.$tid.'_source">'.h(T('html_source')).'</button></li><li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#'.$tid.'_preview">'.h(T('html_preview')).'</button></li></ul><div class="tab-content"><div class="tab-pane fade show active" id="'.$tid.'_source">'.$textarea.'</div><div class="tab-pane fade" id="'.$tid.'_preview"><div class="html-preview js-html-preview" data-source="'.h($name).'">'.sanitize_html(is_array($v)?'':(string)$v).'</div></div></div></div>';
        }
        elseif(field_is_list_type((string)$f['t']))$body.=list_editor_html($name,$label,$v,$required,(string)$f['t']);
        elseif(in_array($f['t'],['textarea','json'],true))$body.=area($name,$label,$v,array_merge($req,['data-json-field'=>$f['t']==='json'?'1':'0']));
        elseif($f['t']==='bool'){$checked=(bool)old_value($name,$v);$id='entry_'.$scope.'_'.$safeKey;$body.='<input type="hidden" name="'.h($name).'" value="0"><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="'.h($name).'" value="1" id="'.h($id).'" '.($checked?'checked':'').'><label class="form-check-label" for="'.h($id).'">'.h($label).'</label></div>';}
        elseif(field_is_relation_type((string)$f['t'])){
            $isNestedRelation=($f['t']??'relation')==='nested_relation';$target=(int)($opt['target_collection_id']??0);$multi=($opt['mode']??'single')==='multiple';$sourceEntryId=(int)($e['id']??0);$nestedParentEntryId=$isNestedRelation?nested_relation_parent_entry_id($c,$sourceEntryId):0;$items=relation_entries($target,$cl,$cid,$sourceEntryId);$raw=old_value($name,$v);$oldRelationAllPresent=is_array(old_value('relation_all_present',[]))&&array_key_exists($k,old_value('relation_all_present',[]));$autoAll=$multi?($oldRelationAllPresent?!empty(old_value('relation_all['.$k.']',0)):(!$valueExists||relation_value_is_all($raw))):false;$selected=array_map('intval',$multi?(relation_value_is_all($raw)?[]:(array)$raw):($raw!==''&&$raw!==null?[$raw]:[]));$body.='<div class="mb-3"><label class="form-label">'.h($label).'</label>';
            if($isNestedRelation&&$target)$body.='<div class="form-text mb-2">'.h(T('nested_relation_parent_only')).'</div>';
            if(!$target)$body.='<div class="alert alert-danger">'.h(T($isNestedRelation?'nested_relation_target_required':'relation_target_required')).'</div>';
            elseif($isNestedRelation&&!$nestedParentEntryId)$body.='<div class="alert alert-info">'.h(T('nested_relation_save_parent_first')).'</div>';
            elseif($multi){
                $pos=array_flip($selected);usort($items,function($a,$b)use($pos,$autoAll){if($autoAll)return strcasecmp(relation_entry_display_label($a),relation_entry_display_label($b))?:((int)$a['id']<=>(int)$b['id']);$ai=(int)$a['id'];$bi=(int)$b['id'];$as=isset($pos[$ai]);$bs=isset($pos[$bi]);if($as!==$bs)return $as?-1:1;if($as&&$bs)return $pos[$ai]<=>$pos[$bi];return strcasecmp(relation_entry_display_label($a),relation_entry_display_label($b))?:($ai<=>$bi);});
                $allId='relation_all_'.$scope.'_'.$safeKey;$body.='<div class="relation-picker js-relation-picker" data-required="'.(!empty($f['r'])?'1':'0').'"><input type="hidden" name="relation_all_present['.h($k).']" value="1"><div class="border rounded-4 p-3 mb-2 bg-body-tertiary"><div class="form-check"><input class="form-check-input js-relation-all" type="checkbox" name="relation_all['.h($k).']" value="1" id="'.h($allId).'" '.($autoAll?'checked':'').'><label class="form-check-label fw-semibold" for="'.h($allId).'">'.h(T('relation_auto_all')).'</label></div><div class="small text-muted mt-1">'.h(T('relation_auto_all_hint')).'</div></div><input type="search" class="form-control mb-2 js-relation-search" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'" '.(!$items?'disabled':'').'>';
                if(!$items)$body.='<div class="alert alert-warning mb-2">'.h(T($isNestedRelation?'no_nested_relation_entries':'no_relation_entries')).'</div>';
                $body.='<div class="list-group rounded-4 overflow-hidden">';foreach($items as $it){$iid=(int)$it['id'];$checked=$autoAll||in_array($iid,$selected,true);$badge=$it['st']==='published'?'text-bg-success':'text-bg-secondary';$entryLabel=relation_entry_display_label($it,$cl);$search=$entryLabel.' '.$it['s'].' '.($it['parent_slug']??'').' '.relation_status_label($it);$body.='<div class="list-group-item d-flex align-items-center gap-2 js-relation-item" data-search="'.h(mb_strtolower($search)).'"><input class="form-check-input js-relation-check" type="checkbox" name="'.h($fieldName($k,true)).'" value="'.$iid.'" '.($checked?'checked':'').' '.($autoAll?'disabled':'').'><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">'.h($entryLabel).'</div><div class="small text-muted"><code>'.h($it['s']).'</code> <span class="badge '.$badge.' ms-1">'.h(relation_status_label($it)).'</span></div></div><div class="d-flex gap-1"><button type="button" class="btn btn-light btn-icon js-relation-up" aria-label="'.h(T('move_up')).'" '.($autoAll?'disabled':'').'>'.icon('arrow-up').'</button><button type="button" class="btn btn-light btn-icon js-relation-down" aria-label="'.h(T('move_down')).'" '.($autoAll?'disabled':'').'>'.icon('arrow-down').'</button></div></div>';}
                $body.='</div><div class="form-text">'.h(T('relation_order_hint')).'</div></div>';
            }elseif(!$items)$body.='<div class="alert alert-warning">'.h(T($isNestedRelation?'no_nested_relation_entries':'no_relation_entries')).'</div>';
            else{
                $body.='<select class="form-select" name="'.h($name).'" '.($required?'required':'').'><option value="">'.h(T('select_entry')).'</option>';foreach($items as $it){$sel=((int)$raw===(int)$it['id'])?'selected':'';$body.='<option value="'.(int)$it['id'].'" '.$sel.'>'.h(relation_entry_display_label($it,$cl).' · '.$it['s'].' · '.relation_status_label($it)).'</option>';}$body.='</select>';
            }
            $body.='</div>';
        }
        elseif($f['t']==='file'||$f['t']==='image'){
            $old=is_array($v)?$v:null;$show=file_from_value($old);$removeName=$fileName('_remove_file',$k);$storedName=$fileName('_file',$k);$uploadName=$fileName('u',$k);$removeId='rm_'.$scope.'_'.$safeKey;$body.='<div class="mb-3"><label class="form-label">'.h($label).'</label>';
            if($show&&!empty($show['url']))$body.='<div class="endpoint-box mb-2"><a class="text-truncate flex-grow-1" target="_blank" href="'.h($show['url']).'">'.icon('paperclip').' '.h($show['name']??$show['file']??T('current_file')).'</a><span class="text-muted small">'.h(fmt_size((int)($show['size']??0))).'</span></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="'.h($removeName).'" value="1" id="'.h($removeId).'"><label class="form-check-label" for="'.h($removeId).'">'.h(T('remove_file')).'</label></div>';
            $body.='<input type="hidden" name="'.h($storedName).'" value="'.h($old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'null').'"><input class="form-control" type="file" name="'.h($uploadName).'" '.($f['t']==='image'?'accept="image/*"':'').' '.($required&&!$show?'required':'').'></div>';
        }else $body.=inp($name,$label,$v,$f['t']==='number'?'number':($f['t']==='date'?'date':($f['t']==='url'?'url':'text')),$req);
        $hint=field_text($f,'hint',$cl);if($hint!=='')$body.='<div class="form-text mt-n2 mb-3">'.h($hint).'</div>';
    }
    return $body;
}
function history_panel($e){
    if(!$e)return '<div class="text-muted">'.h(T('no_history')).'</div>';$rows=entry_versions((int)$e['id']);if(!$rows)return '<div class="text-muted">'.h(T('no_history')).'</div>';$h='<div class="list-group list-group-flush history-list">';
    foreach($rows as $v){$who=$v['user_name']?:$v['user_login']?:'—';$changes=version_changes($v);$h.='<div class="list-group-item bg-transparent px-0"><div class="d-flex justify-content-between gap-3"><div class="min-w-0"><div class="fw-semibold">'.h(T('version_before_change')).' · '.h($v['ca']).'</div><div class="small text-muted">'.h($who).' · '.h(T($v['st']==='published'?'published':'draft')).'</div><ul class="small mt-2 mb-0 ps-3">';foreach($changes as $change)$h.='<li>'.h($change).'</li>';$h.='</ul></div>'.(can_entries()?post_form('restore_version','<input type="hidden" name="version_id" value="'.(int)$v['id'].'"><button class="btn btn-secondary btn-sm">'.icon('arrow-counterclockwise').' '.h(T('restore_this_version')).'</button>'):'').'</div></div>';}
    return $h.'</div>';
}
function safe_html_url($value){$value=html_entity_decode((string)$value,ENT_QUOTES|ENT_HTML5,'UTF-8');$value=preg_replace('/[\x00-\x20]+/u','',$value)??'';return (bool)preg_match('~^(https?:|mailto:|tel:|/|\./|\.\./|#)~i',$value);}
function sanitize_html($html){
    $html=strip_tags((string)$html,'<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><blockquote><code><pre><span><div><table><thead><tbody><tr><th><td><hr><img>');
    $html=preg_replace('/\s(?:on[a-z0-9_-]+|style|srcdoc)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu','',$html)??'';
    $html=preg_replace_callback('/\s(href|src)\s*=\s*(["\'])(.*?)\2/isu',function($m){$v=trim($m[3]);return safe_html_url($v)?' '.$m[1].'='.$m[2].$v.$m[2]:'';},$html)??'';
    if(!class_exists('DOMDocument'))return $html;
    $dom=new DOMDocument('1.0','UTF-8');$prev=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="utf-8"?><div id="cms-preview-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();libxml_use_internal_errors($prev);
    $allowed=['a'=>['href','title','target','rel'],'img'=>['src','alt','title','width','height'],'td'=>['colspan','rowspan'],'th'=>['colspan','rowspan'],'*'=>['class']];
    $walk=function($node)use(&$walk,$allowed){if($node instanceof DOMElement){$tag=strtolower($node->tagName);$attrs=[];foreach(iterator_to_array($node->attributes??[]) as $attr)$attrs[]=$attr->name;foreach($attrs as $name){$ok=in_array($name,$allowed[$tag]??[],true)||in_array($name,$allowed['*'],true);$val=trim($node->getAttribute($name));if(!$ok||(($name==='href'||$name==='src')&&!safe_html_url($val)))$node->removeAttribute($name);}if($tag==='a'&&$node->getAttribute('target')==='_blank')$node->setAttribute('rel','noopener noreferrer');}foreach(iterator_to_array($node->childNodes??[]) as $child)$walk($child);};$root=$dom->getElementById('cms-preview-root');if(!$root)return '';$walk($root);$out='';foreach(iterator_to_array($root->childNodes) as $child)$out.=$dom->saveHTML($child);return $out;
}

function readonly_value_html($value,$type=''){
    if($type==='html'&&!is_array($value))return '<div class="html-preview">'.sanitize_html((string)$value).'</div>';
    if(field_is_list_type((string)$type)){$items=normalize_list_value($value);if(!$items)return '<span class="text-muted">—</span>';$tag=field_list_tag((string)$type);$html='<'.$tag.' class="mb-0 ps-4">';foreach($items as $item)$html.='<li class="mb-1">'.nl2br(h($item)).'</li>';return $html.'</'.$tag.'>';}
    if(is_array($value)){
        if(isset($value['url'])&&isset($value['name']))return '<a class="btn btn-light btn-sm" target="_blank" href="'.h($value['url']).'">'.icon('paperclip').' '.h($value['name']).'</a>';
        if(isset($value['title'])&&isset($value['slug']))return '<div class="border rounded-4 p-3"><div class="fw-semibold">'.h($value['title']).'</div><div class="small text-muted"><code>'.h($value['slug']).'</code> · '.h(T(($value['status']??'draft')==='published'?'published':'draft')).'</div></div>';
        $items='';$assoc=array_keys($value)!==range(0,count($value)-1);foreach($value as $k=>$v)$items.='<div>'.($assoc?'<div class="small text-muted mb-1"><code>'.h((string)$k).'</code></div>':'').readonly_value_html($v).'</div>';if($items!=='')return '<div class="d-grid gap-2">'.$items.'</div>';
        return '<pre class="json-preview mb-0">'.h(json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)).'</pre>';
    }
    if(is_bool($value))return '<span class="badge '.($value?'text-bg-success':'text-bg-secondary').'">'.h(T($value?'yes':'no')).'</span>';
    if($value===null||$value==='')return '<span class="text-muted">—</span>';
    return '<div class="text-break">'.nl2br(h((string)$value)).'</div>';
}
function entry_editor_languages():array{$langs=content_langs();$primary=default_content_lang();if(in_array($primary,$langs,true))$langs=array_values(array_unique(array_merge([$primary],$langs)));return $langs?:[$primary];}
function entry_editor_page($c,$e=null){
    $cid=(int)$c['id'];$id=(int)($e['id']??0);$isNested=collection_is_nested($c);$parentEid=$isNested?($e?(int)($e['parent_eid']??0):nested_parent_entry_id($c)):0;$returnUrl=$isNested?nested_collection_url($c,$parentEid):U(['c'=>$cid]);$langs=entry_editor_languages();$primary=$langs[0]??default_content_lang();if($e)recent_entry_add($e);$endpoint=$isNested?'':U(['api'=>$id?'entry':'entries','c'=>$c['s'],'lang'=>$primary]+($id?['s'=>$e['s'],'populate'=>1]:[]));
    $actionItems=[];
    if(can_schema())$actionItems[]=dd_modal(T('edit_collection'),'#collectionEditModal','pencil');
    if(!$isNested){$actionItems[]='<li><button type="button" class="dropdown-item rounded-3 d-flex align-items-center gap-2 js-copy" data-copy="'.h($endpoint).'">'.icon('copy').'<span>'.h(T('copy_endpoint')).'</span></button></li>';$actionItems[]=dd_link(T('api'),$endpoint,'braces','_blank');}
    if(can_entries())$actionItems[]='<li><hr class="dropdown-divider"></li><li><button type="button" class="dropdown-item rounded-3 d-flex align-items-center gap-2 js-entry-clear" data-confirm="'.h(T('clear_entry_form_q')).'">'.icon('eraser').'<span>'.h(T('clear_entry_form')).'</span></button></li>';
    if(can_schema())$actionItems[]=universal_delete_button(T('delete_collection'),'del_col',['id'=>$cid],T('delete_collection'),collection_delete_message($c),true,'dropdown-item','trash3',T('delete_collection'));
    $menuId='entryEditorMoreMenu'.$cid.'_'.$id;
    $actions='<div class="dropdown"><button type="button" class="btn btn-light dropdown-toggle" id="'.h($menuId).'" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false">'.icon('three-dots').' '.h(T('more')).'</button><ul class="dropdown-menu dropdown-menu-end border-0 shadow rounded-4 p-2" aria-labelledby="'.h($menuId).'">'.implode('',$actionItems).'</ul></div>';
    $pageTitle=$id?(can_entries()?T('edit_entry'):T('view_entry')):T('new_entry');$h=page_head($pageTitle,h($c['n']),$actions,'');
    $accordion='entryLanguages'.$cid.'_'.$id;$multi=content_i18n_enabled();$allFields=fields($cid);$hasTranslated=(bool)array_filter($allFields,'field_is_translatable');$hasGlobal=(bool)array_filter($allFields,fn($f)=>!field_is_translatable($f));
    if(!can_entries()){
        if(!$e)return $h.empty_state(T('no_entries'),T('readonly'));
        $primaryValues=resolve_entry_data($cid,data_lang($e,$primary,true),$primary,true,$e);$body='<div class="row g-3 mb-4"><div class="col-md-6"><div class="form-label">'.h(T('service_title')).'</div><div class="fw-semibold fs-5">'.h($e['t']).'</div></div><div class="col-md-3"><div class="form-label">'.h(T('status')).'</div><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span></div><div class="col-md-3"><div class="form-label">'.h(T('slug')).'</div><code>'.h($e['s']).'</code></div></div>';
        if($hasGlobal){$body.='<section class="border rounded-4 p-3 mb-4"><h2 class="h6 mb-3">'.h(T('data')).'</h2><div class="d-grid gap-3">';foreach($allFields as $f){if(field_is_translatable($f))continue;$k=(string)$f['k'];$label=field_text($f,'l',$primary)?:($f['l']??$k);$body.='<section class="border rounded-4 p-3"><div class="form-label mb-2">'.h($label).' <code class="small">'.h($k).'</code></div>'.readonly_value_html($primaryValues[$k]??null,$f['t']??'').'</section>';}$body.='</div></section>';}
        if($hasTranslated){$body.='<div class="accordion resource-language-accordion" id="'.h($accordion).'">';foreach($langs as $index=>$langCode){$item=$accordion.'_'.preg_replace('/[^a-z0-9_-]+/i','_',$langCode);$values=resolve_entry_data($cid,data_lang($e,$langCode,true),$langCode,true,$e);$open=$index===0;$state=$multi?'<span class="badge '.(!empty(entry_translated_map($e)[$langCode])?'text-bg-success':'text-bg-warning').' ms-auto me-2">'.h(T(!empty(entry_translated_map($e)[$langCode])?'translation_translated':'translation_autofilled')).'</span>':'<span class="badge text-bg-light ms-auto me-2">'.h(T('content_language')).'</span>';$content='<div class="d-grid gap-3">';foreach($allFields as $f){if(!field_is_translatable($f))continue;$k=(string)$f['k'];$label=field_text($f,'l',$langCode)?:($f['l']??$k);$content.='<section class="border rounded-4 p-3"><div class="form-label mb-2">'.h($label).' <code class="small">'.h($k).'</code></div>'.readonly_value_html($values[$k]??null,$f['t']??'').'</section>';}$content.='</div>';$body.='<div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button '.($open?'':'collapsed').'" type="button" data-bs-toggle="collapse" data-bs-target="#'.h($item).'" aria-expanded="'.($open?'true':'false').'"><span class="d-flex align-items-center gap-2 w-100"><span class="fw-semibold">'.h(CONTENT_LANGS[$langCode]??$langCode).'</span><code class="small">'.h($langCode).'</code>'.$state.'</span></button></h2><div id="'.h($item).'" class="accordion-collapse collapse '.($open?'show':'').'" data-bs-parent="#'.h($accordion).'"><div class="accordion-body">'.$content.'</div></div></div>';}$body.='</div>';}
        $json=$multi?json_encode(['id'=>$id,'title'=>$e['t'],'slug'=>$e['s'],'status'=>$e['st'],'translations'=>i18n_out($e,true)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT):json_encode(outEntry($e,$primary,true),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        return $h.'<div class="entry-editor-grid"><div class="ios-surface p-3 p-lg-4"><div class="alert alert-info">'.icon('eye').' '.h(T('readonly')).'</div>'.$body.entry_nested_collections_html($c,$e,true).'</div><aside class="entry-preview"><div class="ios-surface p-3"><h2 class="h5">'.h(T('json_preview')).'</h2><pre class="json-preview">'.h($json).'</pre></div></aside></div>';
    }
    $draftLang=$multi?'__all__':$primary;$draft=entry_draft_get(current_user_id(),$cid,$id,$draftLang);$draftPayload=is_array($draft['data']??null)?$draft['data']:[];$titleValue=(string)old_value('t',$draftPayload['t']??($e['t']??''));$slugValue=(string)old_value('s',$draftPayload['s']??($e['s']??''));$status=(string)old_value('st',$draftPayload['st']??($e['st']??'draft'));$existingPrimary=$e?data_lang($e,$primary,false):[];$globalValues=is_array($draftPayload['d']??null)?array_replace($existingPrimary,$draftPayload['d']):$existingPrimary;
    $form='<form method="post" enctype="multipart/form-data" id="entryEditorForm" class="js-dirty-form js-entry-editor" data-autosave="1" data-entry-id="'.$id.'" data-collection-id="'.$cid.'" data-content-lang="'.h($primary).'" data-i18n="'.($multi?'1':'0').'">'.token().'<input type="hidden" name="_a" value="entry"><input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="id" value="'.$id.'">'.($parentEid?'<input type="hidden" name="parent_eid" value="'.$parentEid.'">':'').'<input type="hidden" name="_cl" value="'.h($primary).'"><input type="hidden" name="_return" value="'.h($returnUrl).'">';
    if($draft)$form.='<div class="alert alert-info js-restored-draft-alert">'.icon('cloud-check').' '.h(T('restored_draft')).' · '.h($draft['updated_at']).'</div>';
    $form.='<div class="row g-3 mb-4"><div class="col-lg-5">'.inp('t',T('service_title'),$titleValue,'text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-lg-5">'.inp('s',T('slug'),$slugValue,'text',['data-slug-target'=>'1']).'</div><div class="col-lg-2">'.select_html('st',T('status'),['draft'=>T('draft'),'published'=>T('published')],$status).'</div></div><div class="alert alert-info rounded-4 border-0 small">'.icon('translate').' '.h(T('entry_i18n_hint')).'</div>';
    if($hasGlobal){$globalControls=entry_field_controls($c,$e,$primary,$globalValues,'d',null,'global',true,'global');if($globalControls!=='')$form.='<section class="border rounded-4 p-3 mb-4"><h2 class="h6 mb-3">'.h(T('data')).'</h2>'.$globalControls.'</section>';}
    $translatedMap=$e?entry_translated_map($e):[];$initialTranslations=[];
    if($hasTranslated){$form.='<div class="accordion resource-language-accordion js-entry-translations" id="'.h($accordion).'">';foreach($langs as $index=>$langCode){$item=$accordion.'_'.preg_replace('/[^a-z0-9_-]+/i','_',$langCode);$primaryItem=$index===0;$existingValues=$e?data_lang($e,$langCode,false):[];
        if($multi){$draftRow=is_array($draftPayload['translations'][$langCode]??null)?$draftPayload['translations'][$langCode]:[];$values=is_array($draftRow['d']??null)?array_replace($existingValues,$draftRow['d']):$existingValues;$dataPrefix='translations['.$langCode.'][d]';$translated=!empty($draftRow['_translated'])||!empty($translatedMap[$langCode])||$primaryItem;}
        else{$values=is_array($draftPayload['d']??null)?array_replace($existingValues,$draftPayload['d']):$existingValues;$dataPrefix='d';$translated=true;}
        $open=$index===0;$state=$multi?'<span class="ms-auto me-2">'.translation_badge($translated).'</span>':'<span class="badge text-bg-light ms-auto me-2">'.h(T('content_language')).'</span>';$hidden=$multi?'<input type="hidden" name="translations['.h($langCode).'][_translated]" value="'.($translated?'1':'0').'" class="js-entry-translated">':'';$confirm=$multi?'<button type="button" class="btn btn-sm btn-light js-entry-confirm-translation">'.icon('check2-circle').' '.h(T('confirm_translation')).'</button>':'';
        $controls=entry_field_controls($c,$e,$langCode,$values,$dataPrefix,null,$langCode,$primaryItem,'translated').$confirm;
        $form.='<div class="accordion-item js-entry-language" data-lang="'.h($langCode).'" data-entry-primary="'.($primaryItem?'1':'0').'" data-translated="'.($translated?'1':'0').'">'.$hidden.'<h2 class="accordion-header"><button class="accordion-button '.($open?'':'collapsed').'" type="button" data-bs-toggle="collapse" data-bs-target="#'.h($item).'" aria-expanded="'.($open?'true':'false').'" aria-controls="'.h($item).'"><span class="d-flex align-items-center gap-2 w-100"><span class="fw-semibold">'.h(CONTENT_LANGS[$langCode]??$langCode).'</span><code class="small">'.h($langCode).'</code>'.$state.'</span></button></h2><div id="'.h($item).'" class="accordion-collapse collapse '.($open?'show':'').'" data-bs-parent="#'.h($accordion).'"><div class="accordion-body">'.$controls.'</div></div></div>';
        $local=[];foreach($allFields as $f)if(field_is_translatable($f)&&array_key_exists((string)$f['k'],$values))$local[(string)$f['k']]=$values[(string)$f['k']];$initialTranslations[$langCode]=['data'=>$local,'translated'=>$translated];
    }$form.='</div>';}
    $form.='<div class="alert alert-light small mt-3">'.icon('info-circle').' '.h(T('files_not_autosaved')).'</div><div class="mobile-action-bar d-flex flex-wrap gap-2 align-items-center"><span class="autosave-state me-auto" id="autosaveState">'.h(T('autosave')).' · '.h(T('files_not_autosaved')).'</span><a class="btn btn-light" href="'.h($returnUrl).'">'.h(T('cancel')).'</a><button class="btn btn-primary" type="submit">'.icon('check-lg').' '.h(T('save')).'</button></div></form>';
    $globalPreview=[];foreach($allFields as $f)if(!field_is_translatable($f)&&array_key_exists((string)$f['k'],$globalValues))$globalPreview[(string)$f['k']]=$globalValues[(string)$f['k']];$initial=$multi?['title'=>$titleValue,'slug'=>$slugValue,'status'=>$status,'default_lang'=>$primary,'data'=>$globalPreview,'translations'=>$initialTranslations]:['title'=>$titleValue,'slug'=>$slugValue,'status'=>$status,'lang'=>$primary,'data'=>array_replace($globalPreview,$initialTranslations[$primary]['data']??[])];$preview=json_encode($initial,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $aside='<aside class="entry-preview d-grid gap-3"><div class="ios-surface p-3"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">'.h(T('json_preview')).'</h2>'.endpoint_copy_button($endpoint,'').'</div><pre class="json-preview" id="entryJsonPreview">'.h($preview).'</pre></div><div class="ios-surface p-3"><h2 class="h5">'.h(T('history')).'</h2>'.history_panel($e).'</div><div class="text-muted small kbd-hint"><kbd>Ctrl</kbd> + <kbd>S</kbd> · <kbd>/</kbd> '.h(T('search')).'</div></aside>';
    return $h.'<div class="entry-editor-grid"><div class="ios-surface p-3 p-lg-4">'.$form.entry_nested_collections_html($c,$e,true).'</div>'.$aside.'</div>';
}
function entry_modal($c,$e=null,$returnParams=[]){return entry_editor_page($c,$e);}
function entryForm($c,$e=null){return entry_editor_page($c,$e);}
function delete_entry_modal($c,$e,$mid=null){
    $mid=$mid?:'deleteEntryModal'.(int)$e['id'];
    $body='<input type="hidden" name="cid" value="'.(int)$c['id'].'"><input type="hidden" name="id" value="'.(int)$e['id'].'"><p>'.h(T('delete_entry_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($e['t']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete'),'del_entry',$body,$footer);
}


/* ROUTER */
final class AppKernel{
    public static function run():void{
        lang();theme();
        if(isset($_GET['theme'])){set_theme($_GET['theme']);$q=$_GET;unset($q['theme']);go(U($q));}
        if(isset($_GET['lang'])&&!isset($_GET['api'])&&!array_key_exists('form',$_GET)){set_lang($_GET['lang']);$q=$_GET;unset($q['lang']);go(U($q));}
        setup_action();
        if(setup_required()){if(isset($_GET['api'])||array_key_exists('form',$_GET))api_error('setup_required',503,T('setup_db'));setup_page();exit;}
        boot();maintenance_maybe();if(array_key_exists('form',$_GET))public_form_endpoint();if(isset($_GET['html_sanitize'])&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'){if(!ok()){http_response_code(403);exit;}chk();header('Content-Type:text/html;charset=utf-8');echo sanitize_html((string)($_POST['html']??''));exit;}action();
        if(first_user_required()){if(isset($_GET['api'])||array_key_exists('form',$_GET))api_error('first_user_required',503,T('first_user'));first_user_page();exit;}
        if(isset($_GET['api'])){if(session_status()===PHP_SESSION_ACTIVE&&in_array(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),['GET','HEAD'],true))session_write_close();api();}
        if(isset($_GET['logout'])){$l=lang();$th=theme();session_destroy();setcookie(LANG_COOKIE,$l,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);setcookie(THEME_COOKIE,$th,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);go('./');}
        if(!ok()){login_page();exit;}
        if(current_project_id()<=0){head_html(T('no_project_access'));echo '<main class="container py-5"><div class="ios-surface p-4 p-lg-5 text-center mx-auto" style="max-width:720px"><div class="display-6 mb-3">'.icon('shield-lock').'</div><h1 class="h3">'.h(T('no_project_access')).'</h1><p class="text-muted">'.h(T('project_access')).'</p><a class="btn btn-primary" href="'.h(U(['logout'=>1])).'">'.icon('box-arrow-right').' '.h(T('logout')).'</a></div></main>';foot();exit;}
        form_submissions_export_endpoint();
        if(isset($_GET['audit'])){if(!is_admin_user()){flash(T('access_denied'),'danger');go('./');}layout(auditPage(),0);exit;}
        if(isset($_GET['diagnostics'])){if(!is_admin_user()){flash(T('access_denied'),'danger');go('./');}layout(diagnosticsPage(),0);exit;}
        if(isset($_GET['api_keys'])){if(!can_api()){flash(T('access_denied'),'danger');go('./');}layout(apiKeysPage(),0);exit;}
        if(isset($_GET['export_schema'])){if(!can_schema()){flash(T('access_denied'),'danger');go('./');}$cc=col((int)$_GET['export_schema']);if(!$cc){flash(T('access_denied'),'danger');go('./');}$schema=export_collection_schema_array($cc);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="collection-'.slug($cc['s']).'-schema.json"');echo json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;}
        if(isset($_GET['api_explorer'])){if(!can_api()){flash(T('access_denied'),'danger');go('./');}layout(apiExplorerPage(),0);exit;}
        if(isset($_GET['forms'])){if(!can_forms()){flash(T('access_denied'),'danger');go('./');}layout(formsPage(),0,isset($_GET['form_edit'])?'formModal':null);exit;}
        if(isset($_GET['form_submissions'])){if(!can_view_form_submissions()){flash(T('access_denied'),'danger');go(U(['forms'=>1]));}$ff=form_row((int)$_GET['form_submissions']);if(!$ff){flash(T('access_denied'),'danger');go(U(['forms'=>1]));}layout(formSubmissionsPage($ff),0);exit;}
        if(isset($_GET['overview'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(dashboardPage(),0);exit;}
        if(isset($_GET['collections'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(collectionsPage(),0,isset($_GET['edit_col'])?'collectionEditModal':(isset($_GET['new_col'])?'collectionNewModal':null));exit;}
        if(isset($_GET['groups'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(groupsPage(),0,isset($_GET['gid'])?'groupModal':null);exit;}
        if(isset($_GET['group'])){$g=group_row((int)$_GET['group']);if(!$g||(int)$g['pid']!==current_project_id()){flash(T('access_denied'),'danger');go(U(['groups'=>1]));}layout(groupWorkspacePage($g),0,isset($_GET['new_col'])?'collectionNewModal':null);exit;}
        if(isset($_GET['users'])){if(!is_admin_user()){flash(T('access_denied'),'danger');go('./');}layout(usersPage(),0,isset($_GET['uid'])?'userModal':null);exit;}
        if(isset($_GET['settings'])){if(!can_settings()){flash(T('access_denied'),'danger');go('./');}layout(settingsPage(),0);exit;}
        if(isset($_GET['files'])){if(!can_files()){flash(T('access_denied'),'danger');go('./');}layout(filesPage(),0);exit;}
        $cs=cols();$cid=(int)($_GET['c']??0);$c=$cid?col($cid):null;
        if(isset($_GET['new_col'])){if(!can_schema()){flash(T('access_denied'),'danger');go(U(['collections'=>1]));}layout(collectionsPage(),0,'collectionNewModal');}
        elseif(isset($_GET['edit_col'])){if(!can_schema()){flash(T('access_denied'),'danger');go(U(['collections'=>1]));}$ec=col((int)$_GET['edit_col']);if(!$ec){flash(T('access_denied'),'danger');go(U(['collections'=>1]));}layout(collectionsPage(),0,'collectionEditModal');}
        elseif(isset($_GET['c'])&&!$c)layout(empty_state(T('no_collections'),T('create_first_collection'),can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#collectionNewModal">'.h(T('create_first_collection')).'</button>':''));
        elseif(isset($_GET['fields'])){if(!can_schema()){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}layout(fieldsPage($c),$cid,isset($_GET['fid'])?'fieldModal':null);}
        elseif(isset($_GET['entry'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}$eid=(int)$_GET['entry'];if(!$eid&&!can_entries()){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}$e=$eid?entry($eid):null;if($eid&&!$e){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}if($e&&(int)$e['cid']!==$cid){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}if(collection_is_nested($c)){$parentEid=$e?(int)($e['parent_eid']??0):nested_parent_entry_id($c);if(!$parentEid||!nested_entry_belongs($c,$parentEid)){flash(T('nested_requires_parent_entry'),'warning');go(U(['c'=>$cid]));}$_GET['parent_entry']=$parentEid;}layout(entry_editor_page($c,$e),$cid);}
        elseif(isset($_GET['c'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(rows($c),$cid);}
        else {if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(dashboardPage(),0);}
    }
}
AppKernel::run();
