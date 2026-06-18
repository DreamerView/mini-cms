<?php
declare(strict_types=1);
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
$__secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$__secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();

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

/* I18N */
function lang(){static $l=null;if($l!==null)return $l;$x=$_COOKIE[LANG_COOKIE]??($_SESSION['_lang']??'ru');$l=isset(LANGS[$x])?$x:'ru';$_SESSION['_lang']=$l;return $l;}
function set_lang($l){$l=isset(LANGS[$l])?$l:'ru';$_SESSION['_lang']=$l;$_COOKIE[LANG_COOKIE]=$l;setcookie(LANG_COOKIE,$l,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $l;}
function theme(){static $x=null;if($x!==null)return $x;$v=$_COOKIE[THEME_COOKIE]??($_SESSION['_theme']??'light');$x=isset(THEMES[$v])?$v:'light';$_SESSION['_theme']=$x;return $x;}
function set_theme($v){$v=isset(THEMES[$v])?$v:'light';$_SESSION['_theme']=$v;$_COOKIE[THEME_COOKIE]=$v;setcookie(THEME_COOKIE,$v,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $v;}
function T($k){static $t=[
'app'=>['ru'=>'Мини Headless CMS','kk'=>'Mini Headless CMS','en'=>'Mini Headless CMS'],'login'=>['ru'=>'Логин','kk'=>'Логин','en'=>'Login'],'password'=>['ru'=>'Пароль','kk'=>'Құпиясөз','en'=>'Password'],'enter'=>['ru'=>'Войти','kk'=>'Кіру','en'=>'Sign in'],'logout'=>['ru'=>'Выйти','kk'=>'Шығу','en'=>'Logout'],'wrong_login'=>['ru'=>'Неверный логин или пароль','kk'=>'Логин немесе құпиясөз қате','en'=>'Wrong login or password'],
'home'=>['ru'=>'Главная','kk'=>'Басты бет','en'=>'Home'],'groups'=>['ru'=>'Группы','kk'=>'Топтар','en'=>'Groups'],'group'=>['ru'=>'Группа','kk'=>'Топ','en'=>'Group'],'new_group'=>['ru'=>'Новая группа','kk'=>'Жаңа топ','en'=>'New group'],'edit_group'=>['ru'=>'Редактировать группу','kk'=>'Топты өзгерту','en'=>'Edit group'],'delete_group'=>['ru'=>'Удалить группу','kk'=>'Топты жою','en'=>'Delete group'],'delete_group_q'=>['ru'=>'Удалить группу? Коллекции и записи не удалятся.','kk'=>'Топты жоясыз ба? Коллекциялар мен жазбалар жойылмайды.','en'=>'Delete group? Collections and entries will stay.'],'group_saved'=>['ru'=>'Группа сохранена','kk'=>'Топ сақталды','en'=>'Group saved'],'group_deleted'=>['ru'=>'Группа удалена','kk'=>'Топ жойылды','en'=>'Group deleted'],'select_collections'=>['ru'=>'Выбери коллекции','kk'=>'Коллекцияларды таңдаңыз','en'=>'Select collections'],'group_api_hint'=>['ru'=>'Один запрос отдаёт несколько коллекций сразу','kk'=>'Бір сұраныс бірнеше коллекцияны бірге қайтарады','en'=>'One request returns multiple collections at once'],'collections'=>['ru'=>'Коллекции','kk'=>'Коллекциялар','en'=>'Collections'],'collection'=>['ru'=>'Коллекция','kk'=>'Коллекция','en'=>'Collection'],'new_collection'=>['ru'=>'Новая коллекция','kk'=>'Жаңа коллекция','en'=>'New collection'],'edit_collection'=>['ru'=>'Редактировать коллекцию','kk'=>'Коллекцияны өзгерту','en'=>'Edit collection'],'name'=>['ru'=>'Название','kk'=>'Атауы','en'=>'Name'],'slug'=>['ru'=>'Slug','kk'=>'Slug','en'=>'Slug'],'description'=>['ru'=>'Описание','kk'=>'Сипаттама','en'=>'Description'],
'save'=>['ru'=>'Сохранить','kk'=>'Сақтау','en'=>'Save'],'close'=>['ru'=>'Закрыть','kk'=>'Жабу','en'=>'Close'],'delete'=>['ru'=>'Удалить','kk'=>'Жою','en'=>'Delete'],'delete_collection'=>['ru'=>'Удалить коллекцию','kk'=>'Коллекцияны жою','en'=>'Delete collection'],'delete_collection_q'=>['ru'=>'Удалить коллекцию вместе с полями и записями?','kk'=>'Коллекцияны өрістерімен және жазбаларымен бірге жоясыз ба?','en'=>'Delete collection with fields and entries?'],
'collection_saved'=>['ru'=>'Коллекция сохранена','kk'=>'Коллекция сақталды','en'=>'Collection saved'],'collection_deleted'=>['ru'=>'Коллекция удалена','kk'=>'Коллекция жойылды','en'=>'Collection deleted'],'name_required'=>['ru'=>'Название обязательно','kk'=>'Атауы міндетті','en'=>'Name is required'],
'entries'=>['ru'=>'Записи','kk'=>'Жазбалар','en'=>'Entries'],'new_entry'=>['ru'=>'Новая запись','kk'=>'Жаңа жазба','en'=>'New entry'],'edit_entry'=>['ru'=>'Редактировать запись','kk'=>'Жазбаны өзгерту','en'=>'Edit entry'],'title'=>['ru'=>'Заголовок','kk'=>'Тақырып','en'=>'Title'],'status'=>['ru'=>'Статус','kk'=>'Статус','en'=>'Status'],'draft'=>['ru'=>'Черновик','kk'=>'Жоба','en'=>'Draft'],'published'=>['ru'=>'Опубликовано','kk'=>'Жарияланды','en'=>'Published'],'data'=>['ru'=>'Данные','kk'=>'Деректер','en'=>'Data'],'entry_saved'=>['ru'=>'Запись сохранена','kk'=>'Жазба сақталды','en'=>'Entry saved'],'entry_deleted'=>['ru'=>'Запись удалена','kk'=>'Жазба жойылды','en'=>'Entry deleted'],'title_required'=>['ru'=>'Заголовок обязателен','kk'=>'Тақырып міндетті','en'=>'Title is required'],'delete_entry_q'=>['ru'=>'Удалить запись?','kk'=>'Жазбаны жоясыз ба?','en'=>'Delete entry?'],
'fields'=>['ru'=>'Поля','kk'=>'Өрістер','en'=>'Fields'],'field'=>['ru'=>'Поле','kk'=>'Өріс','en'=>'Field'],'new_field'=>['ru'=>'Новое поле','kk'=>'Жаңа өріс','en'=>'New field'],'edit_field'=>['ru'=>'Редактировать поле','kk'=>'Өрісті өзгерту','en'=>'Edit field'],'label'=>['ru'=>'Метка','kk'=>'Атауы','en'=>'Label'],'key'=>['ru'=>'Ключ','kk'=>'Кілт','en'=>'Key'],'type'=>['ru'=>'Тип','kk'=>'Түрі','en'=>'Type'],'required'=>['ru'=>'Обязательное','kk'=>'Міндетті','en'=>'Required'],'order'=>['ru'=>'Порядок','kk'=>'Реті','en'=>'Order'],'current_fields'=>['ru'=>'Текущие поля','kk'=>'Қазіргі өрістер','en'=>'Current fields'],'field_saved'=>['ru'=>'Поле сохранено','kk'=>'Өріс сақталды','en'=>'Field saved'],'field_deleted'=>['ru'=>'Поле удалено','kk'=>'Өріс жойылды','en'=>'Field deleted'],'field_required'=>['ru'=>'Название поля обязательно','kk'=>'Өріс атауы міндетті','en'=>'Field label is required'],'delete_field_q'=>['ru'=>'Удалить поле?','kk'=>'Өрісті жоясыз ба?','en'=>'Delete field?'],
'api'=>['ru'=>'API','kk'=>'API','en'=>'API'],'theme'=>['ru'=>'Тема','kk'=>'Тақырып','en'=>'Theme'],'light'=>['ru'=>'Светлая','kk'=>'Жарық','en'=>'Light'],'dark'=>['ru'=>'Тёмная','kk'=>'Қараңғы','en'=>'Dark'],'open_api'=>['ru'=>'Открыть API','kk'=>'API ашу','en'=>'Open API'],'settings'=>['ru'=>'Настройки','kk'=>'Баптаулар','en'=>'Settings'],'language'=>['ru'=>'Язык','kk'=>'Тіл','en'=>'Language'],'db'=>['ru'=>'База','kk'=>'База','en'=>'Database'],'no_collections'=>['ru'=>'Нет коллекций','kk'=>'Коллекциялар жоқ','en'=>'No collections'],'no_entries'=>['ru'=>'Нет записей','kk'=>'Жазбалар жоқ','en'=>'No entries'],'no_fields'=>['ru'=>'Нет полей','kk'=>'Өрістер жоқ','en'=>'No fields'],'yes'=>['ru'=>'да','kk'=>'иә','en'=>'yes'],'no'=>['ru'=>'нет','kk'=>'жоқ','en'=>'no'],'updated'=>['ru'=>'Обновлено','kk'=>'Жаңартылды','en'=>'Updated'],'created'=>['ru'=>'Создано','kk'=>'Жасалды','en'=>'Created'],
'files'=>['ru'=>'Файлы','kk'=>'Файлдар','en'=>'Files'],'file'=>['ru'=>'Файл','kk'=>'Файл','en'=>'File'],'current_file'=>['ru'=>'Текущий файл','kk'=>'Қазіргі файл','en'=>'Current file'],'remove_file'=>['ru'=>'Убрать файл из записи','kk'=>'Файлды жазбадан өшіру','en'=>'Remove file from entry'],'file_too_large'=>['ru'=>'Файл слишком большой','kk'=>'Файл тым үлкен','en'=>'File is too large'],'file_type_denied'=>['ru'=>'Недопустимый тип файла','kk'=>'Файл түріне рұқсат жоқ','en'=>'File type is not allowed'],'upload_error'=>['ru'=>'Ошибка загрузки файла','kk'=>'Файл жүктеу қатесі','en'=>'File upload error'],'clean_files'=>['ru'=>'Очистить файлы','kk'=>'Файлдарды тазалау','en'=>'Clean files'],'clean_files_q'=>['ru'=>'Удалить неиспользуемые файлы?','kk'=>'Қолданылмайтын файлдарды жоясыз ба?','en'=>'Delete unused files?'],'files_cleaned'=>['ru'=>'Файлы очищены. Удалено: ','kk'=>'Файлдар тазаланды. Жойылды: ','en'=>'Files cleaned. Deleted: '],'trash_files'=>['ru'=>'Мусорные файлы','kk'=>'Артық файлдар','en'=>'Trash files'],'used'=>['ru'=>'Используется','kk'=>'Қолданылады','en'=>'Used'],'unused'=>['ru'=>'Не используется','kk'=>'Қолданылмайды','en'=>'Unused'],'no_files'=>['ru'=>'Файлов пока нет','kk'=>'Файлдар әзірге жоқ','en'=>'No files yet'],'file_size'=>['ru'=>'Размер','kk'=>'Өлшемі','en'=>'Size'],'open'=>['ru'=>'Открыть','kk'=>'Ашу','en'=>'Open'],
'cancel'=>['ru'=>'Отмена','kk'=>'Болдырмау','en'=>'Cancel'],'actions'=>['ru'=>'Действия','kk'=>'Әрекеттер','en'=>'Actions'],'more'=>['ru'=>'Ещё','kk'=>'Тағы','en'=>'More'],'action_menu'=>['ru'=>'Меню действий','kk'=>'Әрекеттер мәзірі','en'=>'Action menu'],'schema'=>['ru'=>'Схема','kk'=>'Схема','en'=>'Schema'],'content'=>['ru'=>'Контент','kk'=>'Контент','en'=>'Content'],'danger_zone'=>['ru'=>'Опасная зона','kk'=>'Қауіпті аймақ','en'=>'Danger zone'],'back'=>['ru'=>'Назад','kk'=>'Артқа','en'=>'Back'],
'users'=>['ru'=>'Пользователи','kk'=>'Пайдаланушылар','en'=>'Users'],'user'=>['ru'=>'Пользователь','kk'=>'Пайдаланушы','en'=>'User'],'new_user'=>['ru'=>'Новый пользователь','kk'=>'Жаңа пайдаланушы','en'=>'New user'],'edit_user'=>['ru'=>'Редактировать пользователя','kk'=>'Пайдаланушыны өзгерту','en'=>'Edit user'],'username'=>['ru'=>'Логин','kk'=>'Логин','en'=>'Username'],'display_name'=>['ru'=>'Имя','kk'=>'Аты','en'=>'Display name'],'new_password'=>['ru'=>'Новый пароль','kk'=>'Жаңа құпиясөз','en'=>'New password'],'password_hint'=>['ru'=>'Оставь пустым, если не нужно менять пароль','kk'=>'Құпиясөзді өзгертпесеңіз, бос қалдырыңыз','en'=>'Leave empty to keep current password'],'role'=>['ru'=>'Роль','kk'=>'Рөл','en'=>'Role'],'admin'=>['ru'=>'Администратор','kk'=>'Әкімші','en'=>'Administrator'],'editor'=>['ru'=>'Редактор','kk'=>'Редактор','en'=>'Editor'],'active'=>['ru'=>'Активен','kk'=>'Белсенді','en'=>'Active'],'inactive'=>['ru'=>'Отключён','kk'=>'Өшірілген','en'=>'Inactive'],'user_saved'=>['ru'=>'Пользователь сохранён','kk'=>'Пайдаланушы сақталды','en'=>'User saved'],'user_deleted'=>['ru'=>'Пользователь удалён','kk'=>'Пайдаланушы жойылды','en'=>'User deleted'],'user_required'=>['ru'=>'Логин обязателен','kk'=>'Логин міндетті','en'=>'Username is required'],'password_required'=>['ru'=>'Пароль обязателен','kk'=>'Құпиясөз міндетті','en'=>'Password is required'],'delete_user'=>['ru'=>'Удалить пользователя','kk'=>'Пайдаланушыны жою','en'=>'Delete user'],'delete_user_q'=>['ru'=>'Удалить пользователя?','kk'=>'Пайдаланушыны жоясыз ба?','en'=>'Delete user?'],'self_protected'=>['ru'=>'Нельзя удалить или отключить текущего пользователя','kk'=>'Ағымдағы пайдаланушыны жоюға немесе өшіруге болмайды','en'=>'You cannot delete or disable the current user'],'access_denied'=>['ru'=>'Нет доступа','kk'=>'Қолжетімсіз','en'=>'Access denied'],
'setup_db'=>['ru'=>'Выбор базы данных','kk'=>'Дерекқор таңдау','en'=>'Database setup'],'setup_db_hint'=>['ru'=>'Выберите, где CMS будет хранить данные. SQLite выбран по умолчанию.','kk'=>'CMS деректерді қайда сақтайтынын таңдаңыз. SQLite әдепкі бойынша таңдалған.','en'=>'Choose where CMS stores data. SQLite is selected by default.'],'sqlite'=>['ru'=>'SQLite','kk'=>'SQLite','en'=>'SQLite'],'mysql'=>['ru'=>'MySQL','kk'=>'MySQL','en'=>'MySQL'],'sqlite_hint'=>['ru'=>'Простой режим: файл базы будет создан автоматически.','kk'=>'Қарапайым режим: база файлы автоматты жасалады.','en'=>'Simple mode: database file is created automatically.'],'mysql_hint'=>['ru'=>'Для MySQL база должна быть создана заранее.','kk'=>'MySQL үшін база алдын ала жасалуы керек.','en'=>'For MySQL the database must already exist.'],'host'=>['ru'=>'Хост','kk'=>'Хост','en'=>'Host'],'database'=>['ru'=>'База данных','kk'=>'Дерекқор','en'=>'Database'],'user_db'=>['ru'=>'Пользователь БД','kk'=>'БД пайдаланушысы','en'=>'DB user'],'db_password'=>['ru'=>'Пароль БД','kk'=>'БД құпиясөзі','en'=>'DB password'],'continue'=>['ru'=>'Продолжить','kk'=>'Жалғастыру','en'=>'Continue'],'db_saved'=>['ru'=>'Настройка базы сохранена','kk'=>'База баптауы сақталды','en'=>'Database settings saved'],'db_reset'=>['ru'=>'Сбросить выбор базы','kk'=>'База таңдауын тастау','en'=>'Reset database choice'],'db_reset_hint'=>['ru'=>'Будет удалён только config.json. SQLite-файл и загруженные файлы не удаляются.','kk'=>'Тек config.json өшіріледі. SQLite файлы және жүктелген файлдар өшірілмейді.','en'=>'Only config.json will be removed. SQLite file and uploads are not deleted.'],'db_reset_q'=>['ru'=>'Сбросить выбор базы данных?','kk'=>'Дерекқор таңдауын тастайсыз ба?','en'=>'Reset database choice?'],'current_db'=>['ru'=>'Текущая база','kk'=>'Қазіргі база','en'=>'Current database'],'first_user'=>['ru'=>'Первый пользователь','kk'=>'Бірінші пайдаланушы','en'=>'First user'],'first_user_hint'=>['ru'=>'Создайте администратора, который первым войдёт в CMS.','kk'=>'CMS жүйесіне бірінші кіретін әкімшіні жасаңыз.','en'=>'Create the administrator who will sign in first.'],
'ui_settings'=>['ru'=>'Настройки интерфейса','kk'=>'Интерфейс баптаулары','en'=>'Interface settings'],'language_hint'=>['ru'=>'Язык админ-панели сохраняется в cookie','kk'=>'Админ панель тілі cookie ішінде сақталады','en'=>'Admin language is saved in a cookie'],'theme_hint'=>['ru'=>'Тёмная тема включается iOS-переключателем','kk'=>'Қараңғы тақырып iOS ауыстырғышымен қосылады','en'=>'Dark mode is controlled by an iOS toggle'],'dark_mode'=>['ru'=>'Тёмный режим','kk'=>'Қараңғы режим','en'=>'Dark mode'],'current_theme'=>['ru'=>'Текущая тема','kk'=>'Қазіргі тақырып','en'=>'Current theme'],
'content_settings'=>['ru'=>'Настройки контента','kk'=>'Контент баптаулары','en'=>'Content settings'],'content_i18n_toggle'=>['ru'=>'Интернационализация контента','kk'=>'Контент интернационализациясы','en'=>'Content internationalization'],'content_i18n_hint2'=>['ru'=>'Если включено, записи редактируются по выбранным языкам. Если выключено, запись хранит обычный data без переводов.','kk'=>'Қосулы болса, жазбалар таңдалған тілдер бойынша өңделеді. Өшірулі болса, жазба аудармасыз кәдімгі data сақтайды.','en'=>'When enabled, entries are edited per selected languages. When disabled, entries store plain data without translations.'],'content_languages'=>['ru'=>'Языки контента','kk'=>'Контент тілдері','en'=>'Content languages'],'content_languages_hint'=>['ru'=>'Выберите языки, которые будут доступны в Entry через select.','kk'=>'Entry ішінде select арқылы қолжетімді болатын тілдерді таңдаңыз.','en'=>'Choose languages available in Entry through select.'],'content_i18n_saved'=>['ru'=>'Настройки интернационализации сохранены','kk'=>'Интернационализация баптаулары сақталды','en'=>'Internationalization settings saved'],'enabled'=>['ru'=>'Включено','kk'=>'Қосулы','en'=>'Enabled'],'disabled'=>['ru'=>'Выключено','kk'=>'Өшірулі','en'=>'Disabled'],
'content_language'=>['ru'=>'Язык контента','kk'=>'Контент тілі','en'=>'Content language'],
'internationalization'=>['ru'=>'Интернационализация','kk'=>'Интернационализация','en'=>'Internationalization'],
'i18n_hint'=>['ru'=>'Заполните данные записи отдельно для каждого языка. API отдаёт нужный язык через параметр lang.', 'kk'=>'Жазба деректерін әр тіл үшін бөлек толтырыңыз. API қажетті тілді lang параметрі арқылы береді.', 'en'=>'Fill entry data separately for each language. API returns the requested language through the lang parameter.'],
'all_languages'=>['ru'=>'Все языки','kk'=>'Барлық тілдер','en'=>'All languages'],
'default_language'=>['ru'=>'Язык по умолчанию','kk'=>'Әдепкі тіл','en'=>'Default language']
,'developer'=>['ru'=>'Разработчик','kk'=>'Әзірлеуші','en'=>'Developer'],'viewer'=>['ru'=>'Наблюдатель','kk'=>'Көруші','en'=>'Viewer'],'api_private'=>['ru'=>'Этот API доступен только авторизованным пользователям','kk'=>'Бұл API тек авторизацияланған пайдаланушыларға қолжетімді','en'=>'This API is available only to authenticated users'],'too_many_attempts'=>['ru'=>'Слишком много попыток. Попробуй позже.','kk'=>'Әрекет тым көп. Кейінірек қайталап көріңіз.','en'=>'Too many attempts. Try again later.'],'password_latin'=>['ru'=>'Пароль должен быть от 10 до 72 символов. Можно использовать буквы, цифры и спецсимволы','kk'=>'Құпиясөз 10–72 таңба болуы керек. Әріптерді, сандарды және арнайы таңбаларды қолдануға болады','en'=>'Password must be 10–72 characters. Letters, numbers, and symbols are allowed'],'single'=>['ru'=>'Single','kk'=>'Single','en'=>'Single'],'multiple'=>['ru'=>'Multiple','kk'=>'Multiple','en'=>'Multiple'],'collection_type'=>['ru'=>'Тип коллекции','kk'=>'Коллекция түрі','en'=>'Collection type'],'collection_order'=>['ru'=>'Порядок коллекции','kk'=>'Коллекция реті','en'=>'Collection order'],'collections_hint'=>['ru'=>'Выберите коллекцию или создайте новую. Список открыт в modal, поэтому он удобен при большом количестве коллекций.','kk'=>'Коллекцияны таңдаңыз немесе жаңасын жасаңыз. Тізім modal ішінде, сондықтан көп коллекция болғанда ыңғайлы.','en'=>'Choose a collection or create a new one. The list opens in a modal, so it stays usable with many collections.'],'single_entry_limit'=>['ru'=>'Single-коллекция может иметь только одну запись','kk'=>'Single коллекцияда тек бір жазба болуы мүмкін','en'=>'Single collection can have only one entry'],'collection_type_locked'=>['ru'=>'Тип коллекции нельзя менять после создания','kk'=>'Коллекция түрін жасалғаннан кейін өзгертуге болмайды','en'=>'Collection type cannot be changed after creation'],
'relation'=>['ru'=>'Связь','kk'=>'Байланыс','en'=>'Relation'],'target_collection'=>['ru'=>'Связанная коллекция','kk'=>'Байланысқан коллекция','en'=>'Target collection'],'relation_mode'=>['ru'=>'Тип связи','kk'=>'Байланыс түрі','en'=>'Relation mode'],'relation_single'=>['ru'=>'Одна запись','kk'=>'Бір жазба','en'=>'Single entry'],'relation_multiple'=>['ru'=>'Несколько записей','kk'=>'Бірнеше жазба','en'=>'Multiple entries'],'select_entry'=>['ru'=>'Выбери запись','kk'=>'Жазбаны таңдаңыз','en'=>'Select entry'],'no_relation_entries'=>['ru'=>'В связанной коллекции пока нет записей','kk'=>'Байланысқан коллекцияда әзірге жазба жоқ','en'=>'Target collection has no entries yet'],'relation_target_required'=>['ru'=>'Для поля relation нужно выбрать связанную коллекцию','kk'=>'Relation өрісі үшін байланысқан коллекцияны таңдау керек','en'=>'Relation field requires a target collection'],'relation_invalid_entry'=>['ru'=>'Связанная запись не принадлежит выбранной коллекции','kk'=>'Байланысқан жазба таңдалған коллекцияға тиесілі емес','en'=>'Related entry does not belong to the selected collection'],'populate'=>['ru'=>'Раскрывать связи','kk'=>'Байланыстарды ашу','en'=>'Populate relations'],
'projects'=>['ru'=>'Проекты','kk'=>'Жобалар','en'=>'Projects'],
'project'=>['ru'=>'Проект','kk'=>'Жоба','en'=>'Project'],
'new_project'=>['ru'=>'Новый проект','kk'=>'Жаңа жоба','en'=>'New project'],
'edit_project'=>['ru'=>'Редактировать проект','kk'=>'Жобаны өзгерту','en'=>'Edit project'],
'delete_project'=>['ru'=>'Удалить проект','kk'=>'Жобаны жою','en'=>'Delete project'],
'delete_project_q'=>['ru'=>'Удалить проект вместе с его коллекциями, группами и записями?','kk'=>'Жобаны коллекцияларымен, топтарымен және жазбаларымен бірге жоясыз ба?','en'=>'Delete project with its collections, groups and entries?'],
'project_saved'=>['ru'=>'Проект сохранён','kk'=>'Жоба сақталды','en'=>'Project saved'],
'project_deleted'=>['ru'=>'Проект удалён','kk'=>'Жоба жойылды','en'=>'Project deleted'],
'project_switched'=>['ru'=>'Проект переключён','kk'=>'Жоба ауыстырылды','en'=>'Project switched'],
'projects_hint'=>['ru'=>'Workspace для отдельной CMS внутри одной базы данных','kk'=>'Бір дерекқор ішіндегі жеке CMS workspace','en'=>'Workspace for a separate CMS inside one database'],
'cannot_delete_last_project'=>['ru'=>'Нельзя удалить последний проект','kk'=>'Соңғы жобаны жоюға болмайды','en'=>'Cannot delete the last project'],
'cannot_delete_active_project'=>['ru'=>'Сначала переключитесь на другой проект','kk'=>'Алдымен басқа жобаға ауысыңыз','en'=>'Switch to another project first'],
'manage_collections'=>['ru'=>'Управление коллекциями','kk'=>'Коллекцияларды басқару','en'=>'Manage collections'],'save_collections'=>['ru'=>'Сохранить коллекции','kk'=>'Коллекцияларды сақтау','en'=>'Save collections'],'selected_collections'=>['ru'=>'Выбранные коллекции','kk'=>'Таңдалған коллекциялар','en'=>'Selected collections'],'all_collections'=>['ru'=>'Все коллекции','kk'=>'Барлық коллекциялар','en'=>'All collections'],'go_to_collection'=>['ru'=>'Перейти в коллекцию','kk'=>'Коллекцияға өту','en'=>'Go to collection'],'search'=>['ru'=>'Поиск','kk'=>'Іздеу','en'=>'Search'],'reset'=>['ru'=>'Сбросить','kk'=>'Тазарту','en'=>'Reset'],'no_results'=>['ru'=>'Ничего не найдено','kk'=>'Ештеңе табылмады','en'=>'No results'],'sort_asc'=>['ru'=>'Сортировать по возрастанию','kk'=>'Өсу ретімен сұрыптау','en'=>'Sort ascending'],'sort_desc'=>['ru'=>'Сортировать по убыванию','kk'=>'Кему ретімен сұрыптау','en'=>'Sort descending'],
'collection_preset'=>['ru'=>'Пресет коллекции','kk'=>'Коллекция пресеті','en'=>'Collection preset'],'preset_blank'=>['ru'=>'Пустая','kk'=>'Бос','en'=>'Blank'],'preset_page'=>['ru'=>'Страница','kk'=>'Бет','en'=>'Page'],'preset_blog'=>['ru'=>'Блог','kk'=>'Блог','en'=>'Blog'],'preset_product'=>['ru'=>'Товар','kk'=>'Тауар','en'=>'Product'],'preset_faq'=>['ru'=>'FAQ','kk'=>'FAQ','en'=>'FAQ'],'preset_contact'=>['ru'=>'Контакты','kk'=>'Байланыс','en'=>'Contacts'],
'field_preset'=>['ru'=>'Быстрое поле','kk'=>'Жылдам өріс','en'=>'Quick field'],'field_preset_hint'=>['ru'=>'Выбери пресет, чтобы быстро заполнить label/key/type','kk'=>'Label/key/type тез толтыру үшін пресет таңдаңыз','en'=>'Choose a preset to quickly fill label/key/type'],'custom'=>['ru'=>'Своё','kk'=>'Өзім','en'=>'Custom'],
'clone_collection'=>['ru'=>'Клонировать коллекцию','kk'=>'Коллекцияны клондау','en'=>'Clone collection'],'collection_cloned'=>['ru'=>'Коллекция склонирована','kk'=>'Коллекция клонданды','en'=>'Collection cloned'],'export_schema'=>['ru'=>'Экспорт схемы','kk'=>'Схеманы экспорттау','en'=>'Export schema'],'import_schema'=>['ru'=>'Импорт схемы','kk'=>'Схеманы импорттау','en'=>'Import schema'],'schema_imported'=>['ru'=>'Схема импортирована','kk'=>'Схема импортталды','en'=>'Schema imported'],'invalid_schema'=>['ru'=>'Некорректная схема','kk'=>'Схема дұрыс емес','en'=>'Invalid schema'],'relation_import_warning'=>['ru'=>'Некоторые relation-поля были импортированы как text, потому что связанная коллекция не найдена.','kk'=>'Кейбір relation өрістері text ретінде импортталды, себебі байланысқан коллекция табылмады.','en'=>'Some relation fields were imported as text because the target collection was not found.'],'field_schema_locked'=>['ru'=>'Key, type и настройки relation заблокированы после создания поля.','kk'=>'Өріс жасалғаннан кейін key, type және relation баптаулары бұғатталады.','en'=>'Key, type, and relation settings are locked after field creation.'],
'required_missing'=>['ru'=>'Заполните обязательное поле','kk'=>'Міндетті өрісті толтырыңыз','en'=>'Fill required field'],'relation_order_hint'=>['ru'=>'Отмеченные связи идут первыми. Кнопками вверх/вниз можно менять порядок.','kk'=>'Таңдалған байланыстар жоғары тұрады. Жоғары/төмен батырмаларымен ретін өзгертуге болады.','en'=>'Selected relations stay first. Use up/down buttons to change order.'],'move_up'=>['ru'=>'Выше','kk'=>'Жоғары','en'=>'Move up'],'move_down'=>['ru'=>'Ниже','kk'=>'Төмен','en'=>'Move down'],];$l=lang();return $t[$k][$l]??$t[$k]['ru']??$k;}

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
function flash($m=null){if($m!==null){$_SESSION['_f']=$m;return;} $m=$_SESSION['_f']??''; unset($_SESSION['_f']); return $m;}
function clean_url($u){$p=parse_url((string)$u);$q=[];if(!empty($p['query']))parse_str($p['query'],$q);unset($q['lang']);$path=$p['path']??'./';if($path===''||$path===basename(__FILE__))$path='./';return $path.($q?'?'.http_build_query($q):'');}
function go($u){header('Location: '.clean_url((string)$u));exit;}
function U(array $p=[]):string{if(isset($p['api'])&&!isset($p['project'])&&cfg_exists()&&!setup_required()){try{$pr=current_project();if($pr)$p['project']=$pr['s'];}catch(Throwable $e){}}return './'.($p?'?'.http_build_query($p):'');}
function J($x,$c=200){http_response_code($c);header('Content-Type:application/json;charset=utf-8');echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;}

/* APP CONFIG */
function cfg_path(){return CONFIG_FILE;}
function cfg_exists(){return is_file(cfg_path());}
function cfg_read(){static $c=null;if($c!==null)return $c;if(!cfg_exists())return $c=[];$j=json_decode((string)file_get_contents(cfg_path()),true);return $c=is_array($j)?$j:[];}
function cfg_write(array $c){$dir=dirname(cfg_path());if(!is_dir($dir))mkdir($dir,0775,true);$c['updated_at']=now();file_put_contents(cfg_path(),json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);clearstatcache(true,cfg_path());}
function cfg_reset(){if(cfg_exists())@unlink(cfg_path());}
function cfg_update(callable $fn){$c=cfg_read();$fn($c);cfg_write($c);return $c;}
function cfg_setting($k,$d=null){$c=cfg_read();return $c['settings'][$k]??$d;}
function content_i18n_enabled(){return (bool)cfg_setting('content_i18n',true);}
function content_langs(){static $x=null;if($x!==null)return $x;$v=cfg_setting('content_langs',['ru','kk','en']);if(!is_array($v))$v=['ru','kk','en'];$v=array_values(array_intersect($v,array_keys(CONTENT_LANGS)));return $x=$v?:['ru'];}
function default_content_lang(){return content_langs()[0]??'ru';}
function db_cfg(){return cfg_read()['db']??[];}
function db_driver(){return db_cfg()['driver']??DB;}
function sqlite_file(){return db_cfg()['sqlite_path']??SQLITE;}
function mysql_cfg(){return db_cfg()['mysql']??['host'=>'localhost','database'=>'cms','user'=>'root','password'=>'','charset'=>'utf8mb4'];}
function mysql_dsn_from(array $m){$host=$m['host']??'localhost';$db=$m['database']??'cms';$ch=$m['charset']??'utf8mb4';return 'mysql:host='.$host.';dbname='.$db.';charset='.$ch;}
function setup_required(){return !cfg_exists()||!in_array(db_driver(),['sqlite','mysql'],true);}
function D():PDO{static $d;if($d)return $d;$o=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];try{$driver=db_driver();if($driver==='sqlite'){if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('pdo_sqlite is disabled');$file=sqlite_file();if(!is_dir(dirname($file)))mkdir(dirname($file),0775,true);$d=new PDO('sqlite:'.$file,null,null,$o);$d->exec('PRAGMA foreign_keys=ON;PRAGMA journal_mode=WAL;PRAGMA synchronous=NORMAL');return $d;}if($driver==='mysql'){if(!extension_loaded('pdo_mysql'))throw new RuntimeException('pdo_mysql is disabled');$m=mysql_cfg();return $d=new PDO(mysql_dsn_from($m),$m['user']??MYSQL_USER,$m['password']??MYSQL_PASS,$o);}throw new RuntimeException('Unknown DB driver');}catch(Throwable $e){exit('DB error: '.h($e->getMessage()));}}
function q($sql,$p=[]){$s=D()->prepare($sql);$s->execute($p);return $s;}
function all($sql,$p=[]){return q($sql,$p)->fetchAll();}
function one($sql,$p=[]){$r=q($sql,$p)->fetch();return $r?:null;}
function run($sql,$p=[]){q($sql,$p);return (int)D()->lastInsertId();}


/* SETUP */
function setup_action(){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||($_POST['_a']??'')!=='setup_db')return;
    chk();
    $driver=($_POST['driver']??'sqlite')==='mysql'?'mysql':'sqlite';
    try{
        if($driver==='sqlite'){
            $file=SQLITE;
            if(!is_dir(dirname($file)))mkdir(dirname($file),0775,true);
            cfg_write(['db'=>['driver'=>'sqlite','sqlite_path'=>$file],'created_at'=>now()]);
            flash(T('db_saved'));
            go('./');
        }
        $m=['host'=>trim((string)($_POST['mysql_host']??'localhost')),'database'=>trim((string)($_POST['mysql_database']??'')),'user'=>trim((string)($_POST['mysql_user']??'root')),'password'=>(string)($_POST['mysql_password']??''),'charset'=>'utf8mb4'];
        if($m['database']==='')throw new Exception(T('database').' required');
        if(!extension_loaded('pdo_mysql'))throw new RuntimeException('pdo_mysql is disabled');
        $o=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
        new PDO(mysql_dsn_from($m),$m['user'],$m['password'],$o);
        cfg_write(['db'=>['driver'=>'mysql','mysql'=>$m],'created_at'=>now()]);
        flash(T('db_saved'));
        go('./');
    }catch(Throwable $e){flash($e->getMessage());go('./');}
}
function first_user_required(){return cfg_exists()&&!setup_required()&&((int)D()->query('SELECT COUNT(*) FROM users')->fetchColumn()===0);}

/* DB */
function boot(){
    uploads();
    if(db_driver()==='sqlite'){
        D()->exec("CREATE TABLE IF NOT EXISTS p(id INTEGER PRIMARY KEY AUTOINCREMENT,n TEXT NOT NULL,s TEXT NOT NULL UNIQUE,d TEXT,o INTEGER DEFAULT 0,ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS c(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,m TEXT DEFAULT 'multiple',o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS f(id INTEGER PRIMARY KEY AUTOINCREMENT,cid INTEGER NOT NULL,l TEXT NOT NULL,k TEXT NOT NULL,t TEXT NOT NULL,x TEXT,r INTEGER DEFAULT 0,o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(cid,k),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS e(id INTEGER PRIMARY KEY AUTOINCREMENT,cid INTEGER NOT NULL,t TEXT NOT NULL,s TEXT NOT NULL,st TEXT DEFAULT 'draft',j TEXT NOT NULL,ca TEXT,ua TEXT,UNIQUE(cid,s),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS files(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,onm TEXT NOT NULL,fn TEXT NOT NULL UNIQUE,p TEXT NOT NULL,u TEXT NOT NULL,mime TEXT,ext TEXT,sz INTEGER DEFAULT 0,st TEXT DEFAULT 'active',ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY AUTOINCREMENT,l TEXT NOT NULL UNIQUE,p TEXT NOT NULL,n TEXT,role TEXT DEFAULT 'admin',st TEXT DEFAULT 'active',ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS g(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS gc(id INTEGER PRIMARY KEY AUTOINCREMENT,gid INTEGER NOT NULL,cid INTEGER NOT NULL,o INTEGER DEFAULT 0,UNIQUE(gid,cid),FOREIGN KEY(gid) REFERENCES g(id) ON DELETE CASCADE,FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);");
    }else{
        D()->exec("CREATE TABLE IF NOT EXISTS p(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL UNIQUE,d TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS c(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,m VARCHAR(40) DEFAULT 'multiple',o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_collection_slug(pid,s))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS f(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cid INT UNSIGNED NOT NULL,l VARCHAR(255) NOT NULL,k VARCHAR(160) NOT NULL,t VARCHAR(40) NOT NULL,x MEDIUMTEXT,r TINYINT DEFAULT 0,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE(cid,k),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS e(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cid INT UNSIGNED NOT NULL,t VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,st VARCHAR(40) DEFAULT 'draft',j MEDIUMTEXT NOT NULL,ca DATETIME,ua DATETIME,UNIQUE(cid,s),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS files(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,onm VARCHAR(255) NOT NULL,fn VARCHAR(255) NOT NULL UNIQUE,p VARCHAR(255) NOT NULL,u VARCHAR(255) NOT NULL,mime VARCHAR(120),ext VARCHAR(20),sz BIGINT DEFAULT 0,st VARCHAR(40) DEFAULT 'active',ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,l VARCHAR(120) NOT NULL UNIQUE,p VARCHAR(255) NOT NULL,n VARCHAR(255),role VARCHAR(40) DEFAULT 'admin',st VARCHAR(40) DEFAULT 'active',ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS g(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_group_slug(pid,s))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS gc(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,gid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,o INT DEFAULT 0,UNIQUE(gid,cid),FOREIGN KEY(gid) REFERENCES g(id) ON DELETE CASCADE,FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    ensure_users_compat();ensure_files_compat();ensure_collection_compat();ensure_fields_compat();ensure_projects_compat();seed_users();
    if((int)D()->query('SELECT COUNT(*) FROM c')->fetchColumn()){seed_default_group();return;}
    $n=now();$pid=current_project_id();
    $cid=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$pid,'Pages','pages','Headless pages','multiple',0,$n,$n]);
    add_default_fields($cid);
    run('INSERT INTO e(cid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?)',[$cid,'Home','home','published',json_encode(['content'=>'<h1>Hello</h1><p>Данные идут из headless CMS.</p>','meta_description'=>'Главная страница'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$n,$n]);
    seed_default_group($pid);
}
function table_cols($t){static $c=[];if(isset($c[$t]))return $c[$t];$rows=db_driver()==='sqlite'?all('PRAGMA table_info('.$t.')'):all('SHOW COLUMNS FROM `'.$t.'`');return $c[$t]=array_map(fn($r)=>$r['name']??$r['Field']??'', $rows);} 
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
    if(!has_col('c','m'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN m TEXT DEFAULT 'multiple'":"ALTER TABLE c ADD COLUMN m VARCHAR(40) DEFAULT 'multiple'");
    if(!has_col('c','o'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE c ADD COLUMN o INTEGER DEFAULT 0":"ALTER TABLE c ADD COLUMN o INT DEFAULT 0");
    run("UPDATE c SET m='multiple' WHERE m IS NULL OR m=''");
    run("UPDATE c SET o=0 WHERE o IS NULL");
}
function ensure_fields_compat(){
    if(!has_col('f','x'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE f ADD COLUMN x TEXT':'ALTER TABLE f ADD COLUMN x MEDIUMTEXT');
}
function ensure_files_compat(){
    if(!has_col('files','onm'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN onm TEXT':'ALTER TABLE files ADD COLUMN onm VARCHAR(255)');
    if(!has_col('files','fn'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN fn TEXT':'ALTER TABLE files ADD COLUMN fn VARCHAR(255)');
    if(!has_col('files','p'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN p TEXT':'ALTER TABLE files ADD COLUMN p VARCHAR(255)');
    if(!has_col('files','u'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN u TEXT':'ALTER TABLE files ADD COLUMN u VARCHAR(255)');
    if(!has_col('files','sz'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN sz INTEGER DEFAULT 0':'ALTER TABLE files ADD COLUMN sz BIGINT DEFAULT 0');
    if(!has_col('files','st'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE files ADD COLUMN st TEXT DEFAULT 'active'":"ALTER TABLE files ADD COLUMN st VARCHAR(40) DEFAULT 'active'");
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
    if(db_driver()==='sqlite')D()->exec("CREATE TABLE IF NOT EXISTS p(id INTEGER PRIMARY KEY AUTOINCREMENT,n TEXT NOT NULL,s TEXT NOT NULL UNIQUE,d TEXT,o INTEGER DEFAULT 0,ca TEXT,ua TEXT)");
    else D()->exec("CREATE TABLE IF NOT EXISTS p(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL UNIQUE,d TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $n=now();if(!(int)D()->query('SELECT COUNT(*) FROM p')->fetchColumn())run('INSERT INTO p(n,s,d,o,ca,ua)VALUES(?,?,?,?,?,?)',['Default','default','Default workspace',0,$n,$n]);
    $pid=(int)D()->query('SELECT id FROM p ORDER BY o,id LIMIT 1')->fetchColumn();
    if(!has_col('c','pid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE c ADD COLUMN pid INTEGER':'ALTER TABLE c ADD COLUMN pid INT UNSIGNED');
    if(!has_col('g','pid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE g ADD COLUMN pid INTEGER':'ALTER TABLE g ADD COLUMN pid INT UNSIGNED');
    if(!has_col('files','pid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN pid INTEGER':'ALTER TABLE files ADD COLUMN pid INT UNSIGNED');
    run('UPDATE c SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);run('UPDATE g SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);run('UPDATE files SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);
}
function projects(){return all('SELECT * FROM p ORDER BY o,n,id');}
function project($id){$id=(int)$id;return $id?one('SELECT * FROM p WHERE id=?',[$id]):null;}
function project_by_slug($s){$s=slug($s);return one('SELECT * FROM p WHERE s=?',[$s]);}
function default_project_id(){return (int)D()->query('SELECT id FROM p ORDER BY o,id LIMIT 1')->fetchColumn();}
function current_project_id(){static $pid=null;if($pid!==null)return $pid;$id=(int)($_SESSION['_pid']??0);if(!$id||!project($id))$id=default_project_id();$_SESSION['_pid']=$id;return $pid=$id;}
function current_project(){return project(current_project_id());}
function api_project_id(){if(isset($_GET['project'])||isset($_GET['p'])){$p=project_by_slug($_GET['project']??$_GET['p']);if($p)return (int)$p['id'];}return default_project_id();}
function seed_default_group($pid=null){$pid=$pid?:current_project_id();if((int)q('SELECT COUNT(*) FROM g WHERE pid=?',[$pid])->fetchColumn())return;$first=one('SELECT id FROM c WHERE pid=? ORDER BY id LIMIT 1',[$pid]);if(!$first)return;$n=now();$gid=run('INSERT INTO g(pid,n,s,d,ca,ua)VALUES(?,?,?,?,?,?)',[$pid,'Main','main','Default API group',$n,$n]);run('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[$gid,(int)$first['id'],0]);}
function cols($pid=null){$pid=$pid?:current_project_id();return all('SELECT * FROM c WHERE pid=? ORDER BY o,n,id',[$pid]);}
function col($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT * FROM c WHERE id=? AND pid=?',[$id,$pid]):null;}
function col_by_slug($s,$pid=null){$s=slug($s);$pid=$pid?:current_project_id();return one('SELECT * FROM c WHERE s=? AND pid=?',[$s,$pid]);}
function groups($pid=null){$pid=$pid?:current_project_id();return all('SELECT * FROM g WHERE pid=? ORDER BY n,id',[$pid]);}
function group_row($id){$id=(int)$id;return $id?one('SELECT * FROM g WHERE id=?',[$id]):null;}
function group_by_slug($s,$pid=null){$s=slug($s);$pid=$pid?:current_project_id();return one('SELECT * FROM g WHERE s=? AND pid=?',[$s,$pid]);}
function group_col_ids($gid){return array_map('intval',array_column(all('SELECT cid FROM gc WHERE gid=? ORDER BY o,id',[(int)$gid]),'cid'));}
function group_cols($gid){return all('SELECT c.* FROM gc JOIN c ON c.id=gc.cid WHERE gc.gid=? ORDER BY gc.o,c.o,c.n,gc.id',[(int)$gid]);}
function fields($cid){static $x=[];$cid=(int)$cid;if($cid&&!one('SELECT id FROM f WHERE cid=? LIMIT 1',[$cid]))add_default_fields($cid);return $x[$cid]??=all('SELECT * FROM f WHERE cid=? ORDER BY o,id',[$cid]);}
function field($id){return one('SELECT * FROM f WHERE id=?',[(int)$id]);}
function entry($id){return one('SELECT * FROM e WHERE id=?',[(int)$id]);}
function single_entry($c,$create=false){
    $cid=(int)($c['id']??0);
    if(!$cid)return null;
    $e=one('SELECT * FROM e WHERE cid=? ORDER BY id LIMIT 1',[$cid]);
    if($e||!$create)return $e;
    $tm=now();
    $title=trim((string)($c['n']??'Single'))?:'Single';
    $slug=slug($c['s']??$title);
    $id=run('INSERT INTO e(cid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?)',[$cid,$title,$slug,'draft','{}',$tm,$tm]);
    return entry($id);
}
function data($e){$j=json_decode($e['j']??'{}',true);return is_array($j)?$j:[];}
function content_lang($v=null){$langs=content_langs();$v=$v??($_GET['cl']??($_COOKIE['cms_content_lang']??default_content_lang()));$v=in_array($v,$langs,true)?$v:default_content_lang();$_COOKIE['cms_content_lang']=$v;setcookie('cms_content_lang',$v,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $v;}
function is_i18n($d){return is_array($d)&&!empty($d['_i18n']);}
function i18n_pack($d){$langs=content_langs();if(is_i18n($d)){foreach($langs as $k)if(!isset($d[$k])||!is_array($d[$k]))$d[$k]=[];$d['_i18n']=true;return $d;}$x=['_i18n'=>true];$base=default_content_lang();foreach($langs as $k)$x[$k]=$k===$base?$d:[];return $x;}
function i18n_of($e){return i18n_pack(data($e));}
function data_lang($e,$l=null,$fallback=true){$d=data($e);if(!content_i18n_enabled())return is_i18n($d)?($d[default_content_lang()]??[]):$d;$l=content_lang($l);if(!is_i18n($d))return $d;$base=default_content_lang();$x=$d[$l]??[];if($fallback&&$l!==$base)$x=array_replace($d[$base]??[],$x);return is_array($x)?$x:[];}
function i18n_out($e,$populate=false){$raw=i18n_of($e);$cid=(int)($e['cid']??0);$out=[];foreach(content_langs() as $k)$out[$k]=resolve_entry_data($cid,$raw[$k]??[],$k,$populate);return $out;}
function api_populate(){return in_array((string)($_GET['populate']??'0'),['1','true','yes','on'],true);}
function api_content_lang(){if(!content_i18n_enabled())return null;$l=$_GET['lang']??($_GET['locale']??null);return in_array($l,content_langs(),true)?$l:null;}
function normalize_json_value($v){if(is_array($v))return $v;$v=trim((string)$v);if($v==='')return null;$x=json_decode($v,true);return json_last_error()===JSON_ERROR_NONE?$x:$v;}

/* FILES */
function uploads(){if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0775,true);return UPLOAD_DIR;}
function clean_ext($name){return strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));}
function file_url($name){return UPLOAD_URL.'/'.rawurlencode((string)$name);}
function mime_of($path){return function_exists('mime_content_type')&&is_file($path)?(mime_content_type($path)?:null):null;}
function file_out($r){return $r?['id'=>(int)$r['id'],'file_id'=>(int)$r['id'],'name'=>$r['onm'],'file'=>$r['fn'],'url'=>$r['u'],'size'=>(int)$r['sz'],'mime'=>$r['mime'],'ext'=>$r['ext'],'status'=>$r['st'],'created_at'=>$r['ca'],'updated_at'=>$r['ua']]:null;}
function file_by_id($id){static $x=[];$id=(int)$id;return $id?($x[$id]??=one('SELECT * FROM files WHERE id=?',[$id])):null;}
function file_from_value($v){if(is_numeric($v))return file_out(file_by_id((int)$v));if(!is_array($v))return null;if(!empty($v['file_id']))return file_out(file_by_id((int)$v['file_id']))?:$v;if(!empty($v['id']))return file_out(file_by_id((int)$v['id']))?:$v;return !empty($v['file'])?$v:null;}
function save_file_row($orig,$name,$size,$ext,$mime){$n=now();return run('INSERT INTO files(pid,onm,fn,p,u,mime,ext,sz,st,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),$orig,$name,UPLOAD_URL.'/'.$name,file_url($name),$mime,$ext,$size,'active',$n,$n]);}
function upload_value($key,$type='file'){if(empty($_FILES['u']['name'][$key]))return null;$err=(int)($_FILES['u']['error'][$key]??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)return null;if($err!==UPLOAD_ERR_OK)throw new RuntimeException(T('upload_error'));$size=(int)$_FILES['u']['size'][$key];if($size>UPLOAD_MAX)throw new RuntimeException(T('file_too_large'));$orig=(string)$_FILES['u']['name'][$key];$tmp=(string)$_FILES['u']['tmp_name'][$key];$ext=clean_ext($orig);$allowed=$type==='image'?IMAGE_EXT:FILE_EXT;if(!$ext||!in_array($ext,$allowed,true))throw new RuntimeException(T('file_type_denied'));if($type==='image'&&!@getimagesize($tmp))throw new RuntimeException(T('file_type_denied'));$base=slug(pathinfo($orig,PATHINFO_FILENAME));$name=date('Ymd_His').'_'.bin2hex(random_bytes(4)).'_'.$base.'.'.$ext;$to=uploads().'/'.$name;if(!move_uploaded_file($tmp,$to))throw new RuntimeException(T('upload_error'));return ['file_id'=>save_file_row($orig,$name,$size,$ext,mime_of($to))];}
function used_file_ids_names($pid=null){$pid=$pid?:current_project_id();$ids=[];$names=[];foreach(all('SELECT e.j FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[$pid]) as $row){$x=json_decode($row['j']??'{}',true);if(!is_array($x))continue;$walk=function($v)use(&$walk,&$ids,&$names){if(!is_array($v))return;if(!empty($v['file_id']))$ids[(int)$v['file_id']]=true;if(!empty($v['id'])&&!empty($v['file']))$ids[(int)$v['id']]=true;if(!empty($v['file'])&&is_string($v['file']))$names[basename($v['file'])]=true;foreach($v as $vv)$walk($vv);};$walk($x);}return [$ids,$names];}
function resolve_files($v){if(!is_array($v))return $v;if(isset($v['file_id'])&&count($v)<=2){$f=file_out(file_by_id((int)$v['file_id']));return $f?:$v;}foreach($v as $k=>$vv)$v[$k]=resolve_files($vv);return $v;}
function field_options($f){$x=json_decode($f['x']??'{}',true);return is_array($x)?$x:[];}
function field_options_from_post($t,$cid){
    if($t!=='relation')return null;
    $target=(int)($_POST['rel_cid']??0);
    $mode=($_POST['rel_mode']??'single')==='multiple'?'multiple':'single';
    if(!$target||!col($target))throw new Exception(T('relation_target_required'));
    return json_encode(['target_collection_id'=>$target,'mode'=>$mode],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
function relation_target_options($currentCid=0){$opts=[''=>T('select_entry')];foreach(cols() as $c){if((int)$c['id']===(int)$currentCid)continue;$opts[(int)$c['id']]=$c['n'].' · '.$c['s'];}return $opts;}
function relation_entries($targetCid){$targetCid=(int)$targetCid;if(!$targetCid)return [];return all("SELECT * FROM e WHERE cid=? ORDER BY t,id",[$targetCid]);}
function relation_status_label($e){return ($e['st']??'draft')==='published'?T('published'):T('draft');}
function relation_option_label($e){return $e['t'].' · '.$e['s'].' · '.relation_status_label($e);}
function relation_entry_for_field($f,$id,$publicOnly=true){
    $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$id=(int)$id;
    if(!$target||!$id)return null;
    $sql='SELECT * FROM e WHERE id=? AND cid=?'.($publicOnly?" AND st='published'":'').' LIMIT 1';
    return one($sql,[$id,$target]);
}
function validate_relation_value($f,$v){
    $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$multi=($opt['mode']??'single')==='multiple';
    if(!$target||!col($target))throw new Exception(T('relation_target_required'));
    $ids=$multi?array_values(array_filter(array_map('intval',(array)$v))):(((int)$v)?[(int)$v]:[]);
    foreach($ids as $id){if(!one('SELECT id FROM e WHERE id=? AND cid=? LIMIT 1',[$id,$target]))throw new Exception(T('relation_invalid_entry'));}
    return $multi?$ids:($ids[0]??null);
}
function relation_entry_out($e,$l=null){
    if(!$e)return null;
    $data=$l?data_lang($e,$l,true):(content_i18n_enabled()?data_lang($e,default_content_lang(),true):data($e));
    return ['id'=>(int)$e['id'],'title'=>$e['t'],'slug'=>$e['s'],'status'=>$e['st'],'data'=>resolve_files($data),'created_at'=>$e['ca'],'updated_at'=>$e['ua']];
}
function relation_value_out($f,$v,$l=null){
    $opt=field_options($f);$mode=($opt['mode']??'single')==='multiple'?'multiple':'single';
    if($mode==='multiple'){
        $ids=is_array($v)?$v:($v!==''&&$v!==null?[$v]:[]);
        $out=[];foreach($ids as $id){$e=relation_entry_for_field($f,(int)$id,true);if($e)$out[]=relation_entry_out($e,$l);}return $out;
    }
    $id=is_array($v)?(int)($v[0]??0):(int)$v;
    $e=relation_entry_for_field($f,$id,true);return $e?relation_entry_out($e,$l):null;
}
function resolve_entry_data($cid,$data,$l=null,$populate=false){
    $data=resolve_files($data);
    if(!$cid||!is_array($data)||!$populate)return $data;
    foreach(fields((int)$cid) as $f){if(($f['t']??'')!=='relation')continue;$k=$f['k'];if(array_key_exists($k,$data))$data[$k]=relation_value_out($f,$data[$k],$l);}return $data;
}
function list_files($trashOnly=false,$pid=null){$pid=$pid?:current_project_id();[$ids,$names]=used_file_ids_names($pid);$rows=all("SELECT * FROM files WHERE st!='deleted' AND pid=? ORDER BY id DESC",[$pid]);$out=[];$known=[];foreach($rows as $r){$known[$r['fn']]=true;$used=isset($ids[(int)$r['id']])||isset($names[$r['fn']]);if($trashOnly&&$used)continue;$x=file_out($r);$x['used']=$used;$out[]=$x;}if(is_dir(UPLOAD_DIR))foreach(scandir(UPLOAD_DIR)?:[] as $fn){if($fn==='.'||$fn==='..'||!is_file(UPLOAD_DIR.'/'.$fn)||isset($known[$fn]))continue;$used=isset($names[$fn]);if($trashOnly&&$used)continue;$out[]=['id'=>null,'file_id'=>null,'name'=>$fn,'file'=>$fn,'url'=>file_url($fn),'size'=>filesize(UPLOAD_DIR.'/'.$fn),'mime'=>null,'ext'=>clean_ext($fn),'status'=>'orphan','created_at'=>null,'updated_at'=>date('Y-m-d H:i:s',filemtime(UPLOAD_DIR.'/'.$fn)),'used'=>$used];}return $out;}
function clean_files(){$deleted=0;foreach(list_files(true) as $f){$fn=basename((string)$f['file']);if($fn&&is_file(UPLOAD_DIR.'/'.$fn)&&@unlink(UPLOAD_DIR.'/'.$fn))$deleted++;if(!empty($f['id']))run("UPDATE files SET st='deleted',ua=? WHERE id=?",[now(),(int)$f['id']]);}return $deleted;}
function outEntry($e,$l=null,$populate=false){$cid=(int)($e['cid']??0);$base=['id'=>(int)$e['id'],'title'=>$e['t'],'slug'=>$e['s'],'status'=>$e['st'],'created_at'=>$e['ca'],'updated_at'=>$e['ua']];if($l){$base['lang']=$l;$base['data']=resolve_entry_data($cid,data_lang($e,$l,true),$l,$populate);return $base;}if(content_i18n_enabled()){$def=default_content_lang();$base['lang']=$def;$base['data']=resolve_entry_data($cid,data_lang($e,$def,true),$def,$populate);$base['i18n']=true;$base['languages']=content_langs();$base['translations']=i18n_out($e,$populate);return $base;}$base['data']=resolve_entry_data($cid,data($e),null,$populate);$base['i18n']=false;return $base;}
function outField($f){$x=['id'=>(int)$f['id'],'label'=>$f['l'],'key'=>$f['k'],'type'=>$f['t'],'required'=>(bool)$f['r'],'order'=>(int)$f['o']];if(($f['t']??'')==='relation')$x['options']=field_options($f);return $x;}
function unique_collection_slug($base,$pid=null,$ignore=0){$pid=$pid?:current_project_id();$base=slug($base);$s=$base?:'collection';$i=2;while(one('SELECT id FROM c WHERE pid=? AND s=? AND id!=?',[$pid,$s,(int)$ignore]))$s=$base.'-'.$i++;return $s;}
function unique_entry_slug($base,$cid,$ignore=0){$cid=(int)$cid;$base=slug($base);$s=$base?:'entry';$i=2;while(one('SELECT id FROM e WHERE cid=? AND s=? AND id!=?',[$cid,$s,(int)$ignore]))$s=$base.'-'.$i++;return $s;}
function export_collection_schema_array($c){$fields=[];foreach(fields((int)$c['id']) as $f){$opt=field_options($f);if(($f['t']??'')==='relation'&&!empty($opt['target_collection_id'])){$tc=col((int)$opt['target_collection_id']);if($tc)$opt['target_collection_slug']=$tc['s'];}$fields[]=['label'=>$f['l'],'key'=>$f['k'],'type'=>$f['t'],'required'=>(bool)$f['r'],'order'=>(int)$f['o'],'options'=>$opt];}return ['schema'=>'mini-headless-cms.collection','version'=>1,'collection'=>['name'=>$c['n'],'slug'=>$c['s'],'description'=>$c['d'],'type'=>collection_mode($c),'order'=>(int)($c['o']??0)],'fields'=>$fields];}
function import_collection_schema_array($schema,&$warnings=[]){
    $warnings=[];
    if(!is_array($schema)||($schema['schema']??'')!=='mini-headless-cms.collection')throw new Exception(T('invalid_schema'));
    $c=$schema['collection']??[];$fields=$schema['fields']??[];
    if(!is_array($c)||!is_array($fields))throw new Exception(T('invalid_schema'));
    $n=trim((string)($c['name']??'Imported collection'));
    $s=unique_collection_slug($c['slug']??$n);
    $m=($c['type']??'multiple')==='single'?'single':'multiple';
    $d=trim((string)($c['description']??''));$o=(int)($c['order']??0);$tm=now();
    $cid=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[current_project_id(),$n,$s,$d,$m,$o,$tm,$tm]);
    foreach($fields as $f){
        if(!is_array($f))continue;
        $key=str_replace('-','_',slug($f['key']??($f['label']??'')));
        $label=trim((string)($f['label']??$key));
        $type=(string)($f['type']??'text');
        if(!$key||!$label)continue;
        if(!in_array($type,['text','textarea','html','number','date','bool','url','image','file','json','relation'],true))$type='text';
        $opt=$f['options']??[];if(!is_array($opt))$opt=[];
        if($type==='relation'){
            $target=0;
            if(!empty($opt['target_collection_slug'])){$tc=col_by_slug((string)$opt['target_collection_slug']);if($tc)$target=(int)$tc['id'];}
            elseif(!empty($opt['target_collection_id'])&&col((int)$opt['target_collection_id']))$target=(int)$opt['target_collection_id'];
            if(!$target){$type='text';$opt=[];$warnings['relation_target_missing']=true;}
            else{$opt=['target_collection_id'=>$target,'mode'=>(($opt['mode']??'single')==='multiple'?'multiple':'single')];}
        }
        $x=$opt?json_encode($opt,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
        run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$label,$key,$type,$x,!empty($f['required'])?1:0,(int)($f['order']??0),$tm,$tm]);
    }
    return $cid;
}
function clone_collection_schema($cid){$c=col((int)$cid);if(!$c)throw new Exception(T('access_denied'));$tm=now();$name=$c['n'].' Copy';$slug=unique_collection_slug($c['s'].'-copy');$new=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[current_project_id(),$name,$slug,$c['d'],collection_mode($c),(int)($c['o']??0)+1,$tm,$tm]);foreach(fields((int)$c['id']) as $f)run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$new,$f['l'],$f['k'],$f['t'],$f['x']??null,(int)$f['r'],(int)$f['o'],$tm,$tm]);return $new;}
function field_value_empty($f,$v){$t=$f['t']??'text';if($t==='bool')return empty($v);if($t==='file'||$t==='image')return empty($v);if($t==='relation'){$opt=field_options($f);$multi=($opt['mode']??'single')==='multiple';return $multi?!count(array_filter(array_map('intval',(array)$v))):!(int)$v;}if($t==='json')return $v===null||$v===''||$v===[];return trim((string)$v)==='';}
function validate_required_value($f,$v){if(!empty($f['r'])&&field_value_empty($f,$v))throw new Exception(T('required_missing').': '.$f['l']);}
function collection_mode($c){return (($c['m']??'multiple')==='single')?'single':'multiple';}
function collection_entries_out($c,$l=null,$populate=false){$rows=all("SELECT * FROM e WHERE cid=? AND st='published' ORDER BY id DESC",[$c['id']]);if(collection_mode($c)==='single')return isset($rows[0])?outEntry($rows[0],$l,$populate):null;return array_map(fn($e)=>outEntry($e,$l,$populate),$rows);}
function outGroup($g,$l=null,$withFields=false,$populate=false){$items=[];$by=[];foreach(group_cols((int)$g['id']) as $c){$x=['id'=>(int)$c['id'],'name'=>$c['n'],'slug'=>$c['s'],'description'=>$c['d'],'type'=>collection_mode($c),'order'=>(int)($c['o']??0),'data'=>collection_entries_out($c,$l,$populate)];if($withFields)$x['fields']=array_map('outField',fields((int)$c['id']));$items[]=$x;$by[$c['s']]=$x;}$pr=project((int)($g['pid']??0));return ['id'=>(int)$g['id'],'name'=>$g['n'],'slug'=>$g['s'],'description'=>$g['d'],'project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'lang'=>$l,'populate'=>$populate,'collections'=>$items,'by_slug'=>$by];}
function users(){return all('SELECT id,l,n,role,st,ca,ua FROM users ORDER BY id DESC');}
function user_row($id){return one('SELECT * FROM users WHERE id=?',[(int)$id]);}
function current_user_id(){return (int)($_SESSION['uid']??0);}
function current_user(){return current_user_id()?user_row(current_user_id()):null;}
function current_role(){static $r=null;if($r!==null)return $r;$u=current_user();$role=$u['role']??'viewer';return $r=in_array($role,['admin','developer','editor','viewer'],true)?$role:'viewer';}
function role_is($roles){return in_array(current_role(),(array)$roles,true);}
function is_admin_user(){return role_is('admin');}
function can_schema(){return role_is(['admin','developer']);}
function can_settings(){return role_is(['admin','developer']);}
function can_api(){return role_is(['admin','developer']);}
function can_entries(){return role_is(['admin','editor']);}
function can_files(){return role_is(['admin','editor']);}
function require_perm($ok){if(!$ok)throw new Exception(T('access_denied'));}
function api_require($ok){if(!$ok)J(['ok'=>false,'error'=>'auth_required','message'=>T('api_private')],403);}
function valid_password($p){return is_string($p)&&mb_strlen($p)>=10&&mb_strlen($p)<=72;}
function pass_ok($hash,$pass){return is_string($hash)&&$hash!==''&&password_verify((string)$pass,$hash);}
function client_ip(){return (string)($_SERVER['REMOTE_ADDR']??'local');}
function attempts_all(){if(!is_file(LOGIN_ATTEMPTS_FILE))return []; $x=json_decode((string)file_get_contents(LOGIN_ATTEMPTS_FILE),true);return is_array($x)?$x:[];}
function attempts_save($x){if(!is_dir(dirname(LOGIN_ATTEMPTS_FILE)))mkdir(dirname(LOGIN_ATTEMPTS_FILE),0775,true);file_put_contents(LOGIN_ATTEMPTS_FILE,json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);}
function attempt_key($login){return sha1(client_ip().'|'.mb_strtolower(trim((string)$login)));}
function login_blocked($login){$a=attempts_all();$k=attempt_key($login);$r=$a[$k]??null;if(!$r)return false;if(($r['until']??0)>time())return true;if(($r['until']??0)>0){unset($a[$k]);attempts_save($a);}return false;}
function login_fail($login){$a=attempts_all();$k=attempt_key($login);$r=$a[$k]??['count'=>0,'until'=>0];$r['count']=(int)($r['count']??0)+1;$r['last']=time();if($r['count']>=LOGIN_MAX_FAILS)$r['until']=time()+LOGIN_BLOCK_SECONDS;$a[$k]=$r;attempts_save($a);}
function login_success($login){$a=attempts_all();$k=attempt_key($login);if(isset($a[$k])){unset($a[$k]);attempts_save($a);}}

/* API */
function api(){
    $r=$_GET['api']?:'index';
    $pid=api_project_id();
    $pr=project($pid);
    if($r==='index'){
        $routes=['?api=entries&c=pages&lang='.default_content_lang(),'?api=entries&c=pages&lang='.default_content_lang().'&populate=1','?api=entry&c=pages&s=home&lang='.default_content_lang().'&populate=1'];
        if(ok()&&can_api())$routes=array_merge($routes,['?api=group&g=main&lang='.default_content_lang().'&populate=1','?api=groups','?api=collections','?api=fields&c=pages','?api=schema&c=pages']);
        if(ok()&&can_files())$routes=array_merge($routes,['?api=files','?api=files-trash']);
        J(['ok'=>true,'name'=>APP,'public'=>'published_content_only','project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'content_i18n'=>content_i18n_enabled(),'content_languages'=>content_langs(),'routes'=>$routes]);
    }
    if($r==='files'){api_require(ok()&&can_files());J(['ok'=>true,'project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'data'=>list_files(false,$pid)]);}
    if($r==='files-trash'){api_require(ok()&&can_files());J(['ok'=>true,'project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'data'=>list_files(true,$pid)]);}
    if($r==='collections'){api_require(ok()&&can_api());J(['ok'=>true,'data'=>array_map(fn($c)=>['id'=>(int)$c['id'],'name'=>$c['n'],'slug'=>$c['s'],'description'=>$c['d'],'type'=>collection_mode($c),'order'=>(int)($c['o']??0)],cols($pid))]);}
    if($r==='groups'){api_require(ok()&&can_api());J(['ok'=>true,'data'=>array_map(fn($g)=>['id'=>(int)$g['id'],'name'=>$g['n'],'slug'=>$g['s'],'description'=>$g['d'],'collections'=>array_map(fn($c)=>['slug'=>$c['s'],'type'=>collection_mode($c),'order'=>(int)($c['o']??0)],group_cols((int)$g['id']))],groups($pid))]);}
    if($r==='group'){$g=group_by_slug($_GET['g']??($_GET['s']??''),$pid);if(!$g)J(['ok'=>false,'error'=>'group_not_found'],404);$wf=isset($_GET['fields']);if($wf)api_require(ok()&&can_api());J(['ok'=>true,'group'=>outGroup($g,api_content_lang(),$wf,api_populate())]);}
    $c=col_by_slug($_GET['c']??'',$pid);
    if(!$c)J(['ok'=>false,'error'=>'collection_not_found'],404);
    $privateSchema=in_array($r,['fields','schema'],true)||isset($_GET['fields']);
    if($privateSchema)api_require(ok()&&can_api());
    $fs=array_map('outField',fields((int)$c['id']));
    if($r==='fields')J(['ok'=>true,'collection'=>$c['s'],'data'=>$fs]);
    if($r==='schema')J(['ok'=>true,'collection'=>['id'=>(int)$c['id'],'name'=>$c['n'],'slug'=>$c['s'],'description'=>$c['d'],'type'=>collection_mode($c),'order'=>(int)($c['o']??0)],'content_i18n'=>content_i18n_enabled(),'content_languages'=>content_langs(),'fields'=>$fs]);
    $lg=api_content_lang();
    if($r==='entries'){
        $x=['ok'=>true,'collection'=>$c['s'],'type'=>collection_mode($c),'lang'=>$lg,'populate'=>api_populate(),'data'=>collection_entries_out($c,$lg,api_populate())];
        if(isset($_GET['fields']))$x['fields']=$fs;
        J($x);
    }
    if($r==='entry'){
        $e=one("SELECT * FROM e WHERE cid=? AND s=? AND st='published'",[$c['id'],slug($_GET['s']??'')]);
        if(!$e)J(['ok'=>false,'error'=>'entry_not_found'],404);
        $x=['ok'=>true,'collection'=>$c['s'],'lang'=>$lg,'populate'=>api_populate(),'data'=>outEntry($e,$lg,api_populate())];
        if(isset($_GET['fields']))$x['fields']=$fs;
        J($x);
    }
    J(['ok'=>false,'error'=>'api_not_found'],404);
}

/* ACTIONS */
function action(){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    chk();
    $a=$_POST['_a']??'';
    try{
        if($a==='set_lang'){set_lang($_POST['lang']??'ru');go($_POST['_back']??'./');}
        if($a==='set_theme'){set_theme($_POST['theme']??'light');go($_POST['_back']??'./');}
        if($a==='first_user'){
            if(!first_user_required())go('./');
            $l=trim((string)($_POST['l']??''));$n=trim((string)($_POST['n']??''));$p=(string)($_POST['p']??'');
            if($l==='')throw new Exception(T('user_required'));
            if($p==='')throw new Exception(T('password_required'));
            if(!valid_password($p))throw new Exception(T('password_latin'));
            $tm=now();$uid=run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$l,password_hash($p,PASSWORD_DEFAULT),$n?:$l,'admin','active',$tm,$tm]);
            session_regenerate_id(true);$_SESSION['uid']=$uid;flash(T('user_saved'));go('./');
        }
        if($a==='login'){
            $u=trim((string)($_POST['u']??''));$p=(string)($_POST['p']??'');
            if(login_blocked($u)){flash(T('too_many_attempts'));go('./');}
            $row=one("SELECT * FROM users WHERE l=? AND st='active'",[$u]);
            if($row&&pass_ok($row['p'],$p)){login_success($u);session_regenerate_id(true);$_SESSION['uid']=(int)$row['id'];go('./');}
            login_fail($u);flash(T('wrong_login'));go('./');
        }
        if(!ok())go('./');
        if($a==='set_project'){
            $id=(int)($_POST['id']??0);if(!$id||!project($id))throw new Exception(T('access_denied'));
            $_SESSION['_pid']=$id;flash(T('project_switched'));go(U(['settings'=>1]));
        }
        if($a==='project'){
            require_perm(can_schema());
            $id=(int)($_POST['id']??0);$n=trim((string)($_POST['n']??''));$s=slug($_POST['s']?:$n);$d=trim((string)($_POST['d']??''));$o=(int)($_POST['o']??0);$tm=now();
            if(!$n)throw new Exception(T('name_required'));
            if($id)run('UPDATE p SET n=?,s=?,d=?,o=?,ua=? WHERE id=?',[$n,$s,$d,$o,$tm,$id]);
            else $id=run('INSERT INTO p(n,s,d,o,ca,ua)VALUES(?,?,?,?,?,?)',[$n,$s,$d,$o,$tm,$tm]);
            $_SESSION['_pid']=$id;flash(T('project_saved'));go(U(['settings'=>1]));
        }
        if($a==='del_project'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);
            if((int)D()->query('SELECT COUNT(*) FROM p')->fetchColumn()<=1)throw new Exception(T('cannot_delete_last_project'));
            if($id===current_project_id())throw new Exception(T('cannot_delete_active_project'));
            $cids=array_map('intval',array_column(all('SELECT id FROM c WHERE pid=?',[$id]),'id'));
            foreach($cids as $cid)run('DELETE FROM gc WHERE cid=?',[$cid]);
            run('DELETE FROM gc WHERE gid IN (SELECT id FROM g WHERE pid=?)',[$id]);run('DELETE FROM g WHERE pid=?',[$id]);run('DELETE FROM c WHERE pid=?',[$id]);run("UPDATE files SET st='deleted',ua=? WHERE pid=?",[now(),$id]);run('DELETE FROM p WHERE id=?',[$id]);
            flash(T('project_deleted'));go(U(['settings'=>1]));
        }
        if($a==='save_i18n_settings'){
            require_perm(can_settings());
            $enabled=!empty($_POST['content_i18n']);$langs=$_POST['content_langs']??[];
            if(!is_array($langs))$langs=[];$langs=array_values(array_intersect($langs,array_keys(CONTENT_LANGS)));if(!$langs)$langs=['ru'];
            cfg_update(function(&$c)use($enabled,$langs){$c['settings']['content_i18n']=$enabled;$c['settings']['content_langs']=$langs;});
            flash(T('content_i18n_saved'));go('./?settings=1');
        }
        if($a==='reset_db_config'){require_perm(is_admin_user());cfg_reset();session_destroy();go('./');}
        if($a==='user'){
            require_perm(is_admin_user());
            $id=(int)($_POST['id']??0);$l=trim((string)($_POST['l']??''));$n=trim((string)($_POST['n']??''));$p=(string)($_POST['p']??'');
            $roles=['admin','developer','editor','viewer'];$role=in_array($_POST['role']??'editor',$roles,true)?$_POST['role']:'editor';
            $st=($_POST['st']??'active')==='active'?'active':'inactive';$tm=now();
            if(!$l)throw new Exception(T('user_required'));
            if($p!==''&&!valid_password($p))throw new Exception(T('password_latin'));
            if($id===current_user_id()&&$st!=='active')throw new Exception(T('self_protected'));
            if($id){
                if($p!=='')run('UPDATE users SET l=?,n=?,p=?,role=?,st=?,ua=? WHERE id=?',[$l,$n,password_hash($p,PASSWORD_DEFAULT),$role,$st,$tm,$id]);
                else run('UPDATE users SET l=?,n=?,role=?,st=?,ua=? WHERE id=?',[$l,$n,$role,$st,$tm,$id]);
            }else{
                if($p==='')throw new Exception(T('password_required'));
                $id=run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$l,password_hash($p,PASSWORD_DEFAULT),$n,$role,$st,$tm,$tm]);
            }
            flash(T('user_saved'));go('./?users=1');
        }
        if($a==='del_user'){require_perm(is_admin_user());$id=(int)$_POST['id'];if($id===current_user_id())throw new Exception(T('self_protected'));run('DELETE FROM users WHERE id=?',[$id]);flash(T('user_deleted'));go('./?users=1');}
        if($a==='group'){
            require_perm(can_schema());
            $id=(int)($_POST['id']??0);$n=trim((string)($_POST['n']??''));$s=slug($_POST['s']?:$n);$d=trim((string)($_POST['d']??''));$tm=now();
            if(!$n)throw new Exception(T('name_required'));
            if($id)run('UPDATE g SET n=?,s=?,d=?,ua=? WHERE id=?',[$n,$s,$d,$tm,$id]);else $id=run('INSERT INTO g(pid,n,s,d,ca,ua)VALUES(?,?,?,?,?,?)',[current_project_id(),$n,$s,$d,$tm,$tm]);
            flash(T('group_saved'));go('./?groups=1');
        }
        if($a==='group_cols'){
            require_perm(can_schema());
            $id=(int)($_POST['id']??0);$g=group_row($id);if(!$g)throw new Exception(T('access_denied'));
            $ids=$_POST['collections']??[];if(!is_array($ids))$ids=[];
            $ids=array_values(array_unique(array_map('intval',$ids)));
            run('DELETE FROM gc WHERE gid=?',[$id]);
            $o=0;foreach($ids as $cid){$c=col($cid);if($c&&(int)$c['pid']===(int)$g['pid'])run('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[$id,$cid,$o++]);}
            flash(T('group_saved'));go('./?groups=1');
        }
        if($a==='del_group'){require_perm(can_schema());run('DELETE FROM g WHERE id=?',[(int)$_POST['id']]);flash(T('group_deleted'));go('./?groups=1');}
        if($a==='col'){
            require_perm(can_schema());
            $id=(int)($_POST['id']??0);$n=trim($_POST['n']??'');$s=unique_collection_slug($_POST['s']?:$n,current_project_id(),$id);$d=trim($_POST['d']??'');$o=(int)($_POST['o']??0);$tm=now();
            if(!$n)throw new Exception(T('name_required'));
            if($id){
                // Collection type is locked after creation: do not update c.m here.
                run('UPDATE c SET n=?,s=?,d=?,o=?,ua=? WHERE id=?',[$n,$s,$d,$o,$tm,$id]);
            }else{
                $m=($_POST['m']??'multiple')==='single'?'single':'multiple';
                $id=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[current_project_id(),$n,$s,$d,$m,$o,$tm,$tm]);
                add_preset_fields($id,(string)($_POST['preset']??'page'));
            }
            flash(T('collection_saved'));go('./?c='.$id);
        }
        if($a==='del_col'){require_perm(can_schema());$id=(int)$_POST['id'];run('DELETE FROM gc WHERE cid=?',[$id]);run('DELETE FROM c WHERE id=?',[$id]);flash(T('collection_deleted'));go('./');}
        if($a==='clone_col'){require_perm(can_schema());$id=clone_collection_schema((int)$_POST['id']);flash(T('collection_cloned'));go('./?c='.$id);}
        if($a==='import_col_schema'){require_perm(can_schema());if(empty($_FILES['schema']['tmp_name'])||!is_uploaded_file($_FILES['schema']['tmp_name']))throw new Exception(T('invalid_schema'));$raw=(string)file_get_contents($_FILES['schema']['tmp_name']);$schema=json_decode($raw,true);if(json_last_error()!==JSON_ERROR_NONE)throw new Exception(T('invalid_schema'));$warnings=[];$id=import_collection_schema_array($schema,$warnings);flash(T('schema_imported').(!empty($warnings['relation_target_missing'])?' '.T('relation_import_warning'):''));go('./?c='.$id);}
        if($a==='clean_files'){require_perm(can_files());$n=clean_files();flash(T('files_cleaned').$n);go('./?files=1');}
        if($a==='field'){
            require_perm(can_schema());
            $id=(int)($_POST['id']??0);$cid=(int)$_POST['cid'];$l=trim($_POST['l']??'');$r=!empty($_POST['r'])?1:0;$o=(int)($_POST['o']??0);$tm=now();
            if(!$l)throw new Exception(T('field_required'));
            if($id){
                $oldf=field($id);if(!$oldf||((int)$oldf['cid']!==$cid))throw new Exception(T('access_denied'));
                // Stable schema: key, type, relation target and relation mode are locked after field creation.
                run('UPDATE f SET l=?,r=?,o=?,ua=? WHERE id=?',[$l,$r,$o,$tm,$id]);
            }else{
                $k=str_replace('-','_',slug($_POST['k']?:$l));
                $allowed=['text','textarea','html','number','date','bool','url','image','file','json','relation'];
                $t=in_array($_POST['t']??'text',$allowed,true)?$_POST['t']:'text';$x=field_options_from_post($t,$cid);
                run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$l,$k,$t,$x,$r,$o,$tm,$tm]);
            }
            flash(T('field_saved'));go('./?c='.$cid.'&fields=1');
        }
        if($a==='del_field'){require_perm(can_schema());run('DELETE FROM f WHERE id=?',[(int)$_POST['id']]);flash(T('field_deleted'));go('./?c='.(int)$_POST['cid'].'&fields=1');}
        if($a==='entry'){
            require_perm(can_entries());
            $id=(int)($_POST['id']??0);$cid=(int)$_POST['cid'];$cl=content_lang($_POST['_cl']??null);$t=trim($_POST['t']??'');$s=unique_entry_slug($_POST['s']?:$t,$cid,$id);$st=($_POST['st']??'draft')==='published'?'published':'draft';$cc=col($cid);if(!$id&&$cc&&collection_mode($cc)==='single'&&one('SELECT id FROM e WHERE cid=? LIMIT 1',[$cid]))throw new Exception(T('single_entry_limit'));$cur=[];
            foreach(fields($cid) as $f){$k=$f['k'];$ft=$f['t'];$v=$_POST['d'][$k]??'';if($ft==='bool'){$cur[$k]=!empty($_POST['d'][$k]);validate_required_value($f,$cur[$k]);continue;}if($ft==='relation'){$cur[$k]=validate_relation_value($f,$v);validate_required_value($f,$cur[$k]);continue;}if($ft==='file'||$ft==='image'){$old=json_decode((string)($_POST['_file'][$k]??'null'),true);$cur[$k]=!empty($_POST['_remove_file'][$k])?null:($old?:null);if($up=upload_value($k,$ft))$cur[$k]=$up;validate_required_value($f,$cur[$k]);continue;}$cur[$k]=$ft==='json'?normalize_json_value($v):$v;validate_required_value($f,$cur[$k]);}
            $j=content_i18n_enabled()?json_encode((function()use($id,$cl,$cur){$pack=$id?i18n_of(entry($id)):i18n_pack([]);$pack[$cl]=$cur;return $pack;})(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):json_encode($cur,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $tm=now();if(!$t)throw new Exception(T('title_required'));
            $id?run('UPDATE e SET t=?,s=?,st=?,j=?,ua=? WHERE id=?',[$t,$s,$st,$j,$tm,$id]):run('INSERT INTO e(cid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?)',[$cid,$t,$s,$st,$j,$tm,$tm]);
            flash(T('entry_saved'));
            $ret=trim((string)($_POST['_return']??''));
            if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go($ret);
            go('./?c='.$cid.'&cl='.$cl);
        }
        if($a==='del_entry'){require_perm(can_entries());run('DELETE FROM e WHERE id=?',[(int)$_POST['id']]);flash(T('entry_deleted'));go('./?c='.(int)$_POST['cid']);}
    }catch(Throwable $e){flash($e->getMessage());go($_SERVER['HTTP_REFERER']??'./');}
}

/* HTML COMPONENTS */
function icon($n){return '<i class="bi bi-'.h($n).'"></i>';}
function token(){static $x=null;return $x??='<input type="hidden" name="_csrf" value="'.h(csrf()).'">';}
function attrs($a){$s='';foreach($a as $k=>$v){if($v===false||$v===null)continue;$s.=' '.h($k).($v===true?'':'="'.h($v).'"');}return $s;}
function inp($n,$l,$v='',$type='text',$a=[]){
    $base=['class'=>'form-control form-control-lg rounded-4 bg-body-tertiary border-0','type'=>$type,'name'=>$n,'value'=>$v];
    $a=array_merge($base,$a);
    $label='<label class="form-label">'.h($l).'</label>';
    if(($a['type']??$type)==='password'){
        $a['class']='form-control form-control-lg bg-body-tertiary border-0';
        $id='pw_'.substr(md5($n.$l.random_int(1,999999)),0,10);
        $a['id']=$a['id']??$id;
        return '<div class="mb-3">'.$label.'<div class="input-group input-group-lg rounded-4 overflow-hidden bg-body-tertiary"><input'.attrs($a).'><button class="btn btn-outline-secondary border-0 js-pw-toggle" type="button" data-target="'.h($a['id']).'" aria-label="Toggle password">'.icon('eye').'</button></div></div>';
    }
    return '<div class="mb-3">'.$label.'<input'.attrs($a).'></div>';
}
function area($n,$l,$v='',$a=[]){$a=array_merge(['class'=>'form-control rounded-4 bg-body-tertiary border-0','name'=>$n,'rows'=>'7'],$a);return '<div class="mb-3"><label class="form-label">'.h($l).'</label><textarea'.attrs($a).'>'.h($v).'</textarea></div>';}
function select_html($n,$l,$opts,$cur='',$a=[]){$a=array_merge(['class'=>'form-select rounded-4 bg-body-tertiary border-0','name'=>$n],$a);$h='<div class="mb-3"><label class="form-label">'.h($l).'</label><select'.attrs($a).'>';foreach($opts as $k=>$v)$h.='<option value="'.h($k).'" '.((string)$cur===(string)$k?'selected':'').'>'.h($v).'</option>';return $h.'</select></div>';}
function modal($id,$title,$body='',$footer='',$size='modal-lg'){
    $label=h($id).'Label';
    if($footer==='')$footer='<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('close')).'</button>';
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

function ui_css(){static $x=null;return $x??=$x='
[data-bs-theme=light]{--ui-bg:#f5f5f7;--ui-panel:#ffffff;--ui-input:#ffffff;--ui-text:#1d1d1f;--ui-muted:#7d7d85;--ui-line:#e6e6eb;--ui-soft:#f2f2f7;--ui-blue:#007aff;--ui-red:#ff3b30;--ui-green:#34c759;--ui-on:#ffffff;--ui-red-soft:rgba(255,59,48,.12);--ui-green-soft:rgba(52,199,89,.14);--ui-success-text:#168a3a;--ui-radius:22px;--bs-body-bg:var(--ui-bg);--bs-body-color:var(--ui-text);--bs-emphasis-color:var(--ui-text);--bs-secondary-color:var(--ui-muted);--bs-tertiary-bg:var(--ui-soft);--bs-border-color:var(--ui-line);--bs-heading-color:var(--ui-text);--bs-link-color:var(--ui-blue);--bs-link-hover-color:var(--ui-blue);--bs-modal-bg:var(--ui-panel);--bs-modal-color:var(--ui-text);--bs-card-bg:var(--ui-panel)}
[data-bs-theme=dark]{--ui-bg:#07080b;--ui-panel:#17181d;--ui-input:#111217;--ui-text:#f5f5f7;--ui-muted:#a1a1aa;--ui-line:#30313a;--ui-soft:#24252c;--ui-blue:#0a84ff;--ui-red:#ff453a;--ui-green:#30d158;--ui-on:#ffffff;--ui-red-soft:rgba(255,69,58,.16);--ui-green-soft:rgba(48,209,88,.16);--ui-success-text:#32d74b;--ui-radius:22px;color-scheme:dark;--bs-body-bg:var(--ui-bg);--bs-body-color:var(--ui-text);--bs-emphasis-color:var(--ui-text);--bs-secondary-color:var(--ui-muted);--bs-tertiary-bg:var(--ui-soft);--bs-border-color:var(--ui-line);--bs-heading-color:var(--ui-text);--bs-link-color:var(--ui-blue);--bs-link-hover-color:var(--ui-blue);--bs-modal-bg:var(--ui-panel);--bs-modal-color:var(--ui-text);--bs-card-bg:var(--ui-panel)}
body.premium-bg{min-height:100vh;background:var(--ui-bg);color:var(--ui-text);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",system-ui,sans-serif}a{text-decoration:none}.premium-brand{letter-spacing:-.035em}.premium-topbar{background:color-mix(in srgb,var(--ui-bg) 88%,transparent)!important;backdrop-filter:saturate(180%) blur(18px);border-bottom:1px solid var(--ui-line);color:var(--ui-text)}.premium-topbar>.container-fluid{position:relative}.premium-topbar .navbar-collapse{position:static}.premium-nav{position:absolute;left:50%;transform:translateX(-50%);gap:1.45rem!important}.premium-nav .nav-link{padding:.65rem .15rem!important;font-weight:680;letter-spacing:-.015em}.premium-nav .nav-link.btn{line-height:1.5}.premium-actions{margin-left:auto}
.ios-shell{max-width:1680px;margin:0 auto}.ios-sidebar{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:var(--ui-radius);overflow:hidden}.ios-sidebar .list-group{padding:.5rem}.ios-sidebar .list-group-item{border:0!important;border-radius:16px!important;margin:.15rem 0;background:transparent;color:var(--ui-text)}.ios-sidebar .list-group-item:hover{background:var(--ui-soft)}.ios-sidebar .list-group-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.ios-sidebar .list-group-item.active small{color:color-mix(in srgb,var(--ui-on) 72%,transparent)!important}
.ios-head{display:flex;gap:1rem;align-items:flex-end;justify-content:space-between;margin-bottom:1rem}.ios-title{font-size:1.85rem;line-height:1.05;font-weight:760;letter-spacing:-.04em;margin:0;color:var(--ui-text)}.ios-sub{color:var(--ui-muted);font-size:.9rem;margin-top:.25rem}.ios-actions{display:flex;gap:.55rem;flex-wrap:wrap;justify-content:flex-end}.ios-surface{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:var(--ui-radius);overflow:hidden}.ios-toolbar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}.ios-kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--ui-muted);margin-bottom:.25rem}
.btn{border-radius:999px!important;font-weight:650;border-width:0!important}.btn-dark,.btn-primary{background:var(--ui-blue)!important;color:var(--ui-on)!important}.btn-outline-dark,.btn-light,.btn-outline-light,.btn-outline-secondary{background:var(--ui-soft)!important;color:var(--ui-text)!important;border:0!important}.btn-outline-dark:hover,.btn-light:hover,.btn-outline-light:hover,.btn-outline-secondary:hover{background:var(--ui-line)!important;color:var(--ui-text)!important}.btn-danger,.btn-outline-danger{background:var(--ui-red-soft)!important;color:var(--ui-red)!important;border:0!important}.btn-danger:hover,.btn-outline-danger:hover{background:var(--ui-red)!important;color:var(--ui-on)!important}.btn-icon{width:2.35rem;height:2.35rem;display:inline-flex;align-items:center;justify-content:center;padding:0!important}
.form-control,.form-select{border:1px solid var(--ui-line)!important;border-radius:16px!important;background:var(--ui-input)!important;color:var(--ui-text)!important;padding:.72rem .9rem}.form-control::placeholder{color:var(--ui-muted)!important}.form-select-sm{padding:.45rem 2.25rem .45rem .85rem}.form-control:focus,.form-select:focus{border-color:var(--ui-blue)!important;box-shadow:0 0 0 .22rem color-mix(in srgb,var(--ui-blue) 18%,transparent)!important}.form-label{color:var(--ui-muted);font-size:.82rem;font-weight:650;margin-bottom:.35rem}.form-check-input{background-color:var(--ui-input);border-color:var(--ui-line)}.form-check-input:checked{background-color:var(--ui-blue);border-color:var(--ui-blue)}.form-control[type=file]::file-selector-button{background:var(--ui-soft);color:var(--ui-text);border:0;border-right:1px solid var(--ui-line);border-radius:12px;margin:-.72rem .9rem -.72rem -.9rem;padding:.72rem .9rem}.text-muted{color:var(--ui-muted)!important}.link-dark{color:var(--ui-text)!important}.text-white{color:var(--ui-bg)!important}.bg-dark{background:var(--ui-text)!important}.bg-light,.bg-body-tertiary{background:var(--ui-soft)!important;color:var(--ui-text)!important}
.table{--bs-table-bg:var(--ui-panel);--bs-table-color:var(--ui-text);--bs-table-border-color:var(--ui-line);--bs-table-hover-color:var(--ui-text);--bs-table-hover-bg:var(--ui-soft);color:var(--ui-text)}.table thead th{background:var(--ui-panel);color:var(--ui-muted);font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760}.table td,.table th{border-color:var(--ui-line)!important;padding:1rem}.table tbody tr:last-child td{border-bottom:0!important}.badge{font-weight:650;border-radius:999px!important}.badge.text-bg-success{background:var(--ui-green-soft)!important;color:var(--ui-success-text)!important}.badge.text-bg-secondary,.badge.text-bg-light{background:var(--ui-soft)!important;color:var(--ui-muted)!important;border:0!important}.badge.text-bg-dark{background:var(--ui-text)!important;color:var(--ui-bg)!important}.badge.text-bg-warning{background:var(--ui-red-soft)!important;color:var(--ui-red)!important}.modal-content{background:var(--ui-panel)!important;color:var(--ui-text)!important;border:1px solid var(--ui-line)!important;border-radius:24px!important;overflow:hidden}.modal-header,.modal-body,.modal-footer{background:var(--ui-panel)!important;color:var(--ui-text)!important}.modal-header,.modal-footer{border-color:var(--ui-line)!important}.btn-close{filter:none}.alert{border-radius:18px!important;border:0!important}code{color:var(--ui-blue)}
.premium-panel,.card{background:var(--ui-panel)!important;color:var(--ui-text)!important;border:1px solid var(--ui-line)!important;border-radius:var(--ui-radius)!important;box-shadow:none!important}.card-header,.card-body,.card-footer{background:var(--ui-panel)!important;color:var(--ui-text)!important;border-color:var(--ui-line)!important}.premium-side-card{background:var(--ui-panel)!important;color:var(--ui-text)!important}.premium-side-card .text-white-50{color:var(--ui-muted)!important}.premium-side-card .card-header,.premium-side-card .card-body{border-color:var(--ui-line)!important}.premium-side-card .list-group{padding:.5rem}.premium-side-card .list-group-item{background:transparent!important;color:var(--ui-text)!important;border:0!important;border-radius:16px!important;margin-bottom:.2rem}.premium-side-card .list-group-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.premium-side-card .list-group-item.active small{color:color-mix(in srgb,var(--ui-on) 72%,transparent)!important}.navbar,.navbar-brand,.nav-link{color:var(--ui-text)!important}.nav-link.active{color:var(--ui-blue)!important}.dropdown-menu{background:var(--ui-panel)!important;color:var(--ui-text)!important;border-color:var(--ui-line)!important;border-radius:18px!important}.dropdown-item{color:var(--ui-text)!important}.dropdown-item:hover,.dropdown-item:focus{background:var(--ui-soft)!important;color:var(--ui-text)!important}.dropdown-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}
.ios-toggle{position:relative;display:inline-flex;width:3.35rem;height:2rem;flex:0 0 auto}.ios-toggle input{position:absolute;opacity:0;pointer-events:none}.ios-toggle span{position:absolute;inset:0;cursor:pointer;border-radius:999px;background:var(--ui-line);transition:.18s}.ios-toggle span:before{content:"";position:absolute;width:1.62rem;height:1.62rem;left:.19rem;top:.19rem;border-radius:50%;background:#fff;box-shadow:0 .15rem .45rem rgba(0,0,0,.22);transition:.18s}.ios-toggle input:checked+span{background:var(--ui-blue)}.ios-toggle input:checked+span:before{transform:translateX(1.35rem)}
@media(max-width:991.98px){.premium-nav{position:static;left:auto;transform:none;gap:.75rem!important;margin-top:1rem;align-items:flex-start!important}.premium-actions{margin-left:0;margin-top:1rem}.ios-head{align-items:stretch;flex-direction:column}.ios-actions{justify-content:flex-start}.app-sidebar{order:-1}.premium-side-card .list-group{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.45rem}.premium-side-card .list-group-item{margin-bottom:0}}
';}
function head_html($title){echo '<!doctype html><html lang="'.h(lang()).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>'.ui_css().'</style></head><body class="premium-bg" data-bs-theme="'.h(theme()).'">';}
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
    echo '<script>
    document.addEventListener("click",e=>{
        const b=e.target.closest(".js-pw-toggle");
        if(!b)return;
        const i=document.getElementById(b.dataset.target);
        if(!i)return;
        const show=i.type==="password";
        i.type=show?"text":"password";
        b.innerHTML=show?"<i class=\"bi bi-eye-slash\"></i>":"<i class=\"bi bi-eye\"></i>";
    });
    document.addEventListener("DOMContentLoaded",()=>{
        const L=window.CMS_TABLE_I18N||{};
        document.querySelectorAll(".js-field-type").forEach(sel=>{
            const form=sel.closest("form")||document;
            const box=form.querySelector(".cms-relation-options");
            const sync=()=>{if(box)box.classList.toggle("d-none",sel.value!=="relation");};
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
        const presetMap={content:["Content","content","html",1],excerpt:["Excerpt","excerpt","textarea",0],image:["Image","image","image",0],file:["File","file","file",0],date:["Date","date","date",0],url:["URL","url","url",0],relation:["Relation","relation","relation",0]};
        document.querySelectorAll(".js-field-preset").forEach(sel=>sel.addEventListener("change",()=>{
            const p=presetMap[sel.value]; if(!p)return;
            const form=sel.closest("form"); if(!form)return;
            const l=form.querySelector("[name=\"l\"]"), k=form.querySelector("[name=\"k\"]"), t=form.querySelector("[name=\"t\"]"), r=form.querySelector("[name=\"r\"]");
            if(l)l.value=p[0]; if(k)k.value=p[1]; if(t){t.value=p[2]; t.dispatchEvent(new Event("change"));} if(r)r.checked=!!p[3];
        }));
        document.querySelectorAll(".js-relation-picker").forEach(picker=>{
            const list=picker.querySelector(".list-group");
            const items=()=>Array.from(picker.querySelectorAll(".js-relation-item"));
            const reorder=()=>{
                const arr=items();
                arr.forEach((it,i)=>{if(!it.dataset.orig)it.dataset.orig=String(i);});
                arr.sort((a,b)=>{
                    const ac=a.querySelector(".js-relation-check").checked, bc=b.querySelector(".js-relation-check").checked;
                    if(ac!==bc)return ac?-1:1;
                    return Number(a.dataset.orig)-Number(b.dataset.orig);
                }).forEach(it=>list.appendChild(it));
            };
            picker.addEventListener("change",e=>{if(e.target.matches(".js-relation-check")){e.target.closest(".js-relation-item").dataset.selected=e.target.checked?"1":"0";reorder();}});
            picker.addEventListener("click",e=>{
                const up=e.target.closest(".js-relation-up"), down=e.target.closest(".js-relation-down"); if(!up&&!down)return;
                const it=e.target.closest(".js-relation-item"); if(!it||!it.querySelector(".js-relation-check").checked)return;
                const sib=up?it.previousElementSibling:it.nextElementSibling;
                if(sib&&sib.querySelector(".js-relation-check")&&sib.querySelector(".js-relation-check").checked){up?list.insertBefore(it,sib):list.insertBefore(sib,it);}
            });
            const search=picker.querySelector(".js-relation-search");
            if(search)search.addEventListener("input",()=>{const q=search.value.trim().toLowerCase();items().forEach(it=>it.classList.toggle("d-none",q&&!(it.dataset.search||"").includes(q)));});
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
            if(table.dataset.cmsEnhanced)return;
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
            reset.className="btn btn-outline-dark rounded-pill";
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
                const stored=storedBack();
                if(stored){
                    sessionStorage.removeItem(BACK_KEY);
                    sessionStorage.removeItem(BACK_TIME_KEY);
                    location.href=stored;
                    return;
                }
                try{
                    if(document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1){history.back();return;}
                }catch(e){}
                location.href=btn.dataset.fallback||"./";
            });
        });
    });
    </script>';
    if($show)echo '<script>const m=document.getElementById('.json_encode($show).');if(m)new bootstrap.Modal(m).show()</script>';
    echo '</body></html>';
}

function page_head($title,$sub='',$actions='',$kicker=''){
    $titleBlock='<div class="d-flex align-items-start gap-3">'.smart_back_icon('./').'<div>'.($kicker?'<div class="ios-kicker">'.h($kicker).'</div>':'').'<h1 class="ios-title">'.h($title).'</h1>'.($sub?'<div class="ios-sub">'.$sub.'</div>':'').'</div></div>';
    return '<div class="ios-head">'.$titleBlock.($actions?'<div class="ios-actions">'.$actions.'</div>':'').'</div>';
}
function table_wrap($table){return '<div class="ios-surface p-3 p-lg-4"><div class="table-responsive">'.$table.'</div></div>';}

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


/* UI PAGES */
function login_page(){head_html(T('app'));echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-sm-10 col-md-6 col-lg-4"><div class="card premium-panel border-0"><div class="card-body p-4 p-md-5"><div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('database').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('app')).'</h1><p class="text-muted mb-0">Headless data admin</p></div>';if($m=flash())echo '<div class="alert alert-warning rounded-4 border-0">'.h($m).'</div>';echo post_form('login',inp('u',T('login'),'','text',['required'=>true,'autocomplete'=>'username']).inp('p',T('password'),'','password',['required'=>true,'autocomplete'=>'current-password']).'<button class="btn btn-dark btn-lg w-100">'.icon('box-arrow-in-right').' '.h(T('enter')).'</button>').'</div></div></div></div></main>';foot();}
function setup_page(){
    head_html(T('setup_db'));
    $langPicker='<div class="mb-4"><label class="form-label fw-semibold">'.h(T('language')).'</label><select class="form-select rounded-pill" onchange="location.href=\'?lang=\'+encodeURIComponent(this.value)">'.implode('',array_map(fn($k,$v)=>'<option value="'.h($k).'" '.(lang()===$k?'selected':'').'>'.h($v).'</option>',array_keys(LANGS),LANGS)).'</select></div>';
    $mysql='<div class="row g-3"><div class="col-md-6">'.inp('mysql_host',T('host'),'localhost').'</div><div class="col-md-6">'.inp('mysql_database',T('database'),'cms','text',['autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('mysql_user',T('user_db'),'root','text',['autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('mysql_password',T('db_password'),'','password',['autocomplete'=>'new-password']).'</div></div>';
    $body='<input type="hidden" name="driver" id="dbDriver" value="sqlite"><div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('database').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('setup_db')).'</h1><p class="text-muted mb-0">'.h(T('setup_db_hint')).'</p></div>';
    if($m=flash())$body.='<div class="alert alert-warning rounded-4 border-0">'.h($m).'</div>';
    $body.=$langPicker.'<div class="row g-3 mb-4"><div class="col-md-6"><button type="button" class="btn btn-primary w-100 py-3" id="sqliteBtn" onclick="setDbDriver(\'sqlite\')">'.icon('filetype-sql').' '.h(T('sqlite')).'</button><div class="text-muted small mt-2">'.h(T('sqlite_hint')).'</div></div><div class="col-md-6"><button type="button" class="btn btn-outline-dark w-100 py-3" id="mysqlBtn" onclick="setDbDriver(\'mysql\')">'.icon('database-gear').' '.h(T('mysql')).'</button><div class="text-muted small mt-2">'.h(T('mysql_hint')).'</div></div></div><div id="mysqlBox" class="d-none">'.$mysql.'</div><button class="btn btn-dark btn-lg w-100 mt-4">'.icon('check-lg').' '.h(T('continue')).'</button>';
    echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-lg-8"><div class="ios-surface p-4 p-lg-5">'.post_form('setup_db',$body).'</div></div></div></main><script>function setDbDriver(d){document.getElementById("dbDriver").value=d;document.getElementById("mysqlBox").classList.toggle("d-none",d!=="mysql");document.getElementById("sqliteBtn").className=d==="sqlite"?"btn btn-primary w-100 py-3":"btn btn-outline-dark w-100 py-3";document.getElementById("mysqlBtn").className=d==="mysql"?"btn btn-primary w-100 py-3":"btn btn-outline-dark w-100 py-3"}</script>';
    foot();
}
function first_user_page(){
    head_html(T('first_user'));
    $body='<div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('person-plus').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('first_user')).'</h1><p class="text-muted mb-0">'.h(T('first_user_hint')).'</p></div>';
    if($m=flash())$body.='<div class="alert alert-warning rounded-4 border-0">'.h($m).'</div>';
    $body.='<div class="row g-3"><div class="col-md-6">'.inp('l',T('username'),LOGIN,'text',['required'=>true,'autocomplete'=>'username']).'</div><div class="col-md-6">'.inp('n',T('display_name'),'Administrator','text',['autocomplete'=>'name']).'</div></div>'.inp('p',T('password'),'','password',['required'=>true,'autocomplete'=>'new-password']).'<button class="btn btn-dark btn-lg w-100 mt-2">'.icon('person-check').' '.h(T('save')).'</button>';
    echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-lg-6"><div class="ios-surface p-4 p-lg-5">'.post_form('first_user',$body).'</div></div></div></main>';
    foot();
}

function project_nav(){
    $p=current_project();
    return '<button class="nav-link btn btn-link" type="button" data-bs-toggle="modal" data-bs-target="#projectsModal">'.icon('window-stack').' '.h(T('projects')).($p?' <span class="badge rounded-pill text-bg-light ms-1">'.h($p['s']).'</span>':'').'</button>';
}
function projects_modal(){
    $rows=projects();$edit=isset($_GET['project_edit'])?project((int)$_GET['project_edit']):null;$mods='';$active=current_project_id();
    $body='<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3"><div><div class="fw-semibold">'.h(T('projects')).'</div><div class="text-muted small">'.h(T('projects_hint')).'</div></div>'.(can_schema()?'<button class="btn btn-primary rounded-pill" type="button" data-bs-target="#projectModal" data-bs-toggle="modal">'.icon('plus-lg').' '.h(T('new_project')).'</button>':'').'</div>';
    $body.='<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>'.h(T('order')).'</th><th>'.h(T('name')).'</th><th>'.h(T('slug')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $p){$id=(int)$p['id'];$delId='deleteProjectModal'.$id;$is=$id===$active;$open=post_form('set_project','<input type="hidden" name="id" value="'.$id.'"><button class="btn btn-outline-dark btn-icon" '.($is?'disabled':'').'>'.icon('box-arrow-in-right').'</button>');$more='';if(can_schema()){$more=dd_menu([dd_link(T('edit_project'),U(['settings'=>1,'project_edit'=>$id]),'pencil'),dd_modal(T('delete_project'),'#'.$delId,'trash3',true,$is)]);$mods.=delete_project_modal($p,$delId);} $body.='<tr class="'.($is?'table-active':'').'"><td>'.(int)($p['o']??0).'</td><td><div class="fw-semibold">'.h($p['n']).($is?' <span class="badge text-bg-dark rounded-pill">active</span>':'').'</div><small class="text-muted">'.h($p['d']).'</small></td><td><code>'.h($p['s']).'</code></td><td class="text-end"><div class="d-inline-flex flex-wrap gap-2">'.$open.$more.'</div></td></tr>';}
    $body.='</tbody></table></div>';
    return '<div class="modal fade" id="projectsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h(T('projects')).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">'.$body.'</div><div class="modal-footer"><button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('close')).'</button></div></div></div></div>'.project_modal($edit).$mods;
}
function project_modal($p=null){$id=(int)($p['id']??0);$body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$p['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-6">'.inp('s',T('slug'),$p['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-6">'.inp('o',T('order'),$p['o']??0,'number').'</div></div>'.area('d',T('description'),$p['d']??'',['rows'=>'3']);$footer='<span class="me-auto"></span><button type="button" class="btn btn-light rounded-pill" data-bs-target="#projectsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('projectModal',$id?T('edit_project'):T('new_project'),'project',$body,$footer);}
function delete_project_modal($p,$mid){$body='<input type="hidden" name="id" value="'.(int)$p['id'].'"><p>'.h(T('delete_project_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($p['n']).'</div>';$footer='<button type="button" class="btn btn-light rounded-pill" data-bs-target="#projectsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';return form_modal($mid,T('delete_project'),'del_project',$body,$footer);}
function groups_nav(){
    if(!(can_schema()||can_entries()))return '';
    $active=(!$_GET||isset($_GET['groups'])||isset($_GET['group']))?'active':'';
    return '<a class="nav-link '.$active.'" href="'.h(U(['groups'=>1])).'">'.icon('grid').' '.h(T('groups')).'</a>';
}
function collection_nav($cid=0){
    return '<button class="nav-link btn btn-link '.($cid?'active':'').'" type="button" data-bs-toggle="modal" data-bs-target="#collectionsModal">'.icon('collection').' '.h(T('collections')).'</button>';
}
function collections_modal($cid=0){
    $rows=cols();$mods='';
    $body='<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3"><div><div class="fw-semibold">'.h(T('collections')).'</div><div class="text-muted small">'.h(T('collections_hint')).'</div></div>'.(can_schema()?'<button class="btn btn-primary rounded-pill" type="button" data-bs-target="#collectionNewModal" data-bs-toggle="modal">'.icon('plus-lg').' '.h(T('new_collection')).'</button>':'').'</div>';
    if(can_schema())$body.='<form method="post" enctype="multipart/form-data" class="ios-surface p-3 mb-3">'.token().'<input type="hidden" name="_a" value="import_col_schema"><div class="row g-2 align-items-end"><div class="col-md"><label class="form-label">'.h(T('import_schema')).'</label><input class="form-control" type="file" name="schema" accept="application/json,.json" required></div><div class="col-md-auto"><button class="btn btn-dark rounded-pill w-100">'.icon('upload').' '.h(T('import_schema')).'</button></div></div></form>';
    $body.='<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>'.h(T('order')).'</th><th>'.h(T('name')).'</th><th>'.h(T('type')).'</th><th>'.h(T('slug')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $c){
        $id=(int)$c['id'];$active=$cid===$id;$delId='deleteCollectionFromListModal'.$id;
        $open='<a class="btn btn-outline-dark btn-icon" href="'.h(U(['c'=>$id])).'">'.icon('box-arrow-in-right').'</a>';
        $more='';
        if(can_schema()){
            $more=dd_menu([dd_link(T('edit_collection'),U(['edit_col'=>$id]),'pencil'),dd_form(T('clone_collection'),'clone_col','<input type="hidden" name="id" value="'.$id.'">','copy'),dd_link(T('export_schema'),U(['export_schema'=>$id]),'download'),'<li><hr class="dropdown-divider"></li>',dd_modal(T('delete_collection'),'#'.$delId,'trash3',true)]);
            $mods.=delete_collection_from_list_modal($c,$delId);
        }
        $body.='<tr class="'.($active?'table-active':'').'"><td>'.(int)($c['o']??0).'</td><td><div class="fw-semibold">'.h($c['n']).'</div><small class="text-muted">'.h($c['d']).'</small></td><td><span class="badge rounded-pill text-bg-light">'.h(T(collection_mode($c))).'</span></td><td><code>'.h($c['s']).'</code></td><td class="text-end"><div class="d-inline-flex flex-wrap gap-2">'.$open.$more.'</div></td></tr>';
    }
    $body.=($rows?'':'<tr><td colspan="5" class="text-center text-muted py-4">'.h(T('no_collections')).'</td></tr>').'</tbody></table></div>';
    return '<div class="modal fade" id="collectionsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h(T('collections')).'</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">'.$body.'</div><div class="modal-footer"><button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('close')).'</button></div></div></div></div>'.$mods;
}
function layout($html,$cid=0,$show=null){
    head_html(T('app'));
    echo '<nav class="navbar navbar-expand-lg premium-topbar sticky-top"><div class="container-fluid px-3 px-lg-4"><a class="navbar-brand fw-bold premium-brand" href="./">'.icon('database').' '.h(T('app')).'</a><button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav"><span class="navbar-toggler-icon"></span></button><div class="collapse navbar-collapse" id="topNav"><div class="navbar-nav premium-nav align-items-lg-center justify-content-center">'.groups_nav().collection_nav($cid).(can_files()?'<a class="nav-link '.(isset($_GET['files'])?'active':'').'" href="'.h(U(['files'=>1])).'">'.icon('folder2-open').' '.h(T('files')).'</a>':'').(can_settings()?'<a class="nav-link '.(isset($_GET['settings'])?'active':'').'" href="'.h(U(['settings'=>1])).'">'.icon('gear').' '.h(T('settings')).'</a>':'').'</div><div class="premium-actions d-flex flex-wrap gap-2 align-items-center"><span class="badge rounded-pill text-bg-light border px-3 py-2">'.h(T('db')).': '.h(strtoupper(db_driver())).'</span><a class="btn btn-outline-dark btn-sm" href="'.h(U(['logout'=>1])).'">'.icon('box-arrow-right').' '.h(T('logout')).'</a></div></div></div></nav><main class="container-fluid p-3 p-lg-4 ios-shell">';
    if($m=flash())echo '<div class="alert alert-info alert-dismissible fade show rounded-4 border-0">'.h($m).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    echo $html.'</main>'.projects_modal().collections_modal($cid).collection_modal(null,'collectionNewModal').clean_files_modal();
    $show=$show?:((isset($_GET['project_edit']))?'projectModal':null);foot($show);
}
function collection_modal($c=null,$mid='collectionModal'){
    $id=(int)($c['id']??0);
    $typeField=$id
        ? select_html('m_locked',T('collection_type'),['multiple'=>T('multiple'),'single'=>T('single')],collection_mode($c??[]),['disabled'=>true]).'<div class="form-text mt-n2 mb-3">'.h(T('collection_type_locked')).'</div>'
        : select_html('m',T('collection_type'),['multiple'=>T('multiple'),'single'=>T('single')],collection_mode($c??[]));
    $presetField=$id?'':select_html('preset',T('collection_preset'),collection_preset_options(),'page');
    $body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$c['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-6">'.inp('s',T('slug'),$c['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-6">'.$typeField.'</div><div class="col-md-6">'.inp('o',T('collection_order'),$c['o']??0,'number').'</div><div class="col-md-12">'.$presetField.'</div></div>'.area('d',T('description'),$c['d']??'',['rows'=>'3']);
    $delete=$id?'<button type="button" class="btn btn-outline-danger rounded-pill me-auto" data-bs-target="#deleteCollectionModal" data-bs-toggle="modal">'.icon('trash3').' '.h(T('delete')).'</button>':'<span class="me-auto"></span>';
    $footer=$delete.'<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal($mid,$id?T('edit_collection'):T('new_collection'),'col',$body,$footer);
}
function clean_files_modal(){
    return form_modal('cleanFilesModal',T('clean_files'),'clean_files','<p class="mb-0">'.h(T('clean_files_q')).'</p>','<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>');
}
function fmt_size($b){$b=(int)$b;$u=['B','KB','MB','GB'];for($i=0;$b>=1024&&$i<count($u)-1;$i++)$b/=1024;return round($b,$i?1:0).' '.$u[$i];}

function groupsPage(){
    $rows=groups();$edit=isset($_GET['gid'])?group_row((int)$_GET['gid']):null;$mods='';
    $actions=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">'.icon('plus-lg').' '.h(T('new_group')).'</button>':'';
    $h=page_head(T('groups'),h(T('group_api_hint')),$actions);
    $h.='<table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>'.h(T('name')).'</th><th>'.h(T('slug')).'</th><th>'.h(T('collections')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $g){
        $gid=(int)$g['id'];
        $mid='deleteGroupModal'.$gid;
        $manageMid='manageGroupCollectionsModal'.$gid;
        $cs=group_cols($gid);
        $open='<a class="btn btn-dark btn-icon" href="'.h(U(['group'=>$gid])).'" title="'.h(T('open')).'">'.icon('box-arrow-in-right').'</a>';
        $api='<a class="btn btn-outline-dark btn-icon" target="_blank" href="'.h(U(['api'=>'group','g'=>$g['s'],'lang'=>default_content_lang()])).'" title="'.h(T('api')).'">'.icon('braces').'</a>';
        $badges=$cs?implode(' ',array_map(fn($c)=>'<a class="badge rounded-pill text-bg-light text-decoration-none" href="'.h(U(['group'=>$gid])).'">'.h($c['s']).'</a>',$cs)):'<span class="text-muted">'.h(T('no_collections')).'</span>';
        $items=[];
        if(can_schema()){
            $items=[
                dd_link(T('edit_group'),U(['groups'=>1,'gid'=>$gid]),'pencil'),
                dd_modal(T('manage_collections'),'#'.$manageMid,'collection'),
                '<li><hr class="dropdown-divider"></li>',
                dd_modal(T('delete_group'),'#'.$mid,'trash3',true)
            ];
            $mods.=manage_group_collections_modal($g,$manageMid).delete_group_modal($g,$mid);
        }
        $more=dd_menu($items);
        $h.='<tr><td>'.$gid.'</td><td><div class="fw-semibold">'.h($g['n']).'</div><small class="text-muted">'.h($g['d']).'</small></td><td><code>'.h($g['s']).'</code></td><td>'.$badges.'</td><td class="text-end"><div class="d-inline-flex flex-wrap gap-2">'.$open.$api.$more.'</div></td></tr>';
    }
    $h.=($rows?'':'<tr><td colspan="5" class="text-center text-muted py-4">'.h(T('no_entries')).'</td></tr>').'</tbody></table>';
    return table_wrap($h).group_modal($edit).$mods;
}
function groupWorkspacePage($g){
    $gid=(int)$g['id'];
    $cols=group_cols($gid);
    $editEntry=null;$editCol=null;
    if(isset($_GET['entry'])&&can_entries()){
        $editEntry=entry((int)$_GET['entry']);
        if($editEntry)$editCol=col((int)$editEntry['cid']);
    }
    $mods='';
    $actions='';
    if(can_schema())$actions.='<button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#manageGroupCollectionsModal'.$gid.'">'.icon('collection').' '.h(T('manage_collections')).'</button>';
    $actions.='<a class="btn btn-outline-dark" target="_blank" href="'.h(U(['api'=>'group','g'=>$g['s'],'lang'=>default_content_lang()])).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head($g['n'],($g['d']?h($g['d']).' · ':'').'<code>?api=group&amp;g='.h($g['s']).'</code>',$actions,T('group'));
    $h.='<div class="ios-surface p-3 p-lg-4"><div class="list-group list-group-flush rounded-4 overflow-hidden">';
    foreach($cols as $c){
        $cid=(int)$c['id'];$mode=collection_mode($c);$meta='<span class="badge rounded-pill text-bg-light">'.h(T($mode)).'</span><code>'.h($c['s']).'</code>';
        $more=dd_menu(array_filter([
            can_schema()?dd_link(T('fields'),U(['c'=>$cid,'fields'=>1]),'list-check'):'',
            dd_link(T('api'),U(['api'=>'entries','c'=>$c['s']]),'braces','_blank')
        ]));
        if($mode==='single'){
            $e=single_entry($c,can_entries());
            $main=$e&&can_entries()?'<a class="btn btn-dark rounded-pill w-100" href="'.h(U(['group'=>$gid,'entry'=>$e['id']])).'">'.icon('pencil').' '.h(T('edit_entry')).'</a>':($e?'<span class="badge text-bg-secondary rounded-pill">'.h(T('viewer')).'</span>':'<span class="text-muted">'.h(T('no_entries')).'</span>');
            $status=$e?'<span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').' rounded-pill">'.h(T($e['st']==='published'?'published':'draft')).'</span>':'';
        }else{
            $cnt=(int)q('SELECT COUNT(*) FROM e WHERE cid=?',[$cid])->fetchColumn();
            $main='<a class="btn btn-dark rounded-pill" data-cms-remember-back="1" href="'.h(U(['c'=>$cid])).'">'.icon('box-arrow-in-right').' '.h(T('entries')).'</a>';
            $status='<span class="badge text-bg-secondary rounded-pill">'.$cnt.'</span>';
        }
        $h.='<div class="list-group-item bg-transparent px-0 py-3"><div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3"><div class="min-w-0"><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h2 class="h5 mb-0">'.h($c['n']).'</h2>'.$status.'</div><div class="d-flex flex-wrap align-items-center gap-2 text-muted small">'.$meta.(($c['d']??'')?'<span>· '.h($c['d']).'</span>':'').'</div></div><div class="d-flex flex-wrap gap-2">'.$main.$more.'</div></div></div>';
    }
    $h.=($cols?'':'<div class="text-center text-muted py-5">'.h(T('no_collections')).'</div>').'</div></div>';
    if(can_schema())$mods.=manage_group_collections_modal($g,'manageGroupCollectionsModal'.$gid);
    if($editEntry&&$editCol)$mods.=entry_modal($editCol,$editEntry,['group'=>$gid]);
    return $h.$mods;
}
function group_modal($g=null){
    $id=(int)($g['id']??0);
    $body='<input type="hidden" name="id" value="'.$id.'">'.inp('n',T('name'),$g['n']??'','text',['required'=>true,'data-slug-source'=>'s']).inp('s',T('slug'),$g['s']??'','text',['data-slug-target'=>'1']).area('d',T('description'),$g['d']??'',['rows'=>'3']);
    $delete=$id?'<button type="button" class="btn btn-outline-danger rounded-pill me-auto" data-bs-target="#deleteGroupModal'.$id.'" data-bs-toggle="modal">'.icon('trash3').' '.h(T('delete')).'</button>':'<span class="me-auto"></span>';
    $footer=$delete.'<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal('groupModal',$id?T('edit_group'):T('new_group'),'group',$body,$footer,'modal-lg');
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
    $body='<input type="hidden" name="id" value="'.$gid.'"><div class="mb-3"><input type="search" class="form-control rounded-pill js-group-collections-search" placeholder="'.h(T('search')).'"></div><div class="list-group rounded-4 overflow-hidden js-group-collections-list">'.$items.'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save_collections')).'</button>';
    return form_modal($mid,T('manage_collections'),'group_cols',$body,$footer,'modal-lg modal-fullscreen-lg-down');
}
function delete_group_modal($g,$mid=null){
    $mid=$mid?:'deleteGroupModal'.(int)$g['id'];
    $body='<input type="hidden" name="id" value="'.(int)$g['id'].'"><p>'.h(T('delete_group_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($g['n']).'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_group'),'del_group',$body,$footer);
}

function filesPage(){
    $rows=list_files(false);
    $actions='<a class="btn btn-outline-dark" target="_blank" href="'.h(U(['api'=>'files'])).'">'.icon('braces').' '.h(T('api')).'</a><button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cleanFilesModal">'.icon('trash3').' '.h(T('clean_files')).'</button>';
    $h=page_head(T('files'),'<code>?api=files</code>',$actions);
    $h.='<table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>'.h(T('file')).'</th><th>'.h(T('type')).'</th><th>'.h(T('file_size')).'</th><th>'.h(T('status')).'</th><th>'.h(T('updated')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $f){$used=!empty($f['used']);$url=$f['url']??'';$name=$f['name']??$f['file']??'';$h.='<tr><td>'.h($f['id']??'—').'</td><td><div class="fw-semibold">'.h($name).'</div><small class="text-muted">'.h($f['file']??'').'</small></td><td><span class="badge rounded-pill text-bg-light">'.h($f['ext']??'').'</span></td><td>'.h(fmt_size((int)($f['size']??0))).'</td><td><span class="badge '.($used?'text-bg-success':'text-bg-warning').'">'.h(T($used?'used':'unused')).'</span></td><td>'.h($f['updated_at']??$f['created_at']??'').'</td><td class="text-end"><div class="d-inline-flex flex-wrap gap-2">'.($url?'<a class="btn btn-outline-dark btn-icon" target="_blank" href="'.h($url).'">'.icon('box-arrow-up-right').'</a>':'').'</div></td></tr>';}
    $h.=($rows?'':'<tr><td colspan="7" class="text-center text-muted py-4">'.h(T('no_files')).'</td></tr>').'</tbody></table>';
    return table_wrap($h);
}

function content_lang_checkbox_grid(){
    $sel=array_flip(content_langs());$h='<div class="row g-2">';
    foreach(CONTENT_LANGS as $k=>$v){$id='cl_'.$k;$h.='<div class="col-6 col-md-4 col-xl-3"><div class="form-check rounded-4 bg-body-tertiary p-3 h-100"><input class="form-check-input ms-0 me-2" type="checkbox" name="content_langs[]" value="'.h($k).'" id="'.h($id).'" '.(isset($sel[$k])?'checked':'').'><label class="form-check-label fw-semibold" for="'.h($id).'">'.h($v).'<span class="text-muted small ms-1">'.h($k).'</span></label></div></div>';}
    return $h.'</div>';
}
function settingsPage(){
    $h=page_head(T('settings'),h(T('ui_settings')));
    $langForm=post_form('set_lang','<input type="hidden" name="_back" value="'.h(clean_url($_SERVER['REQUEST_URI']??'./')).'">'.select_html('lang',T('language'),LANGS,lang(),['onchange'=>'this.form.submit()']));
    $themeForm=theme_toggle();
    $driver=strtoupper(db_driver());$cfg=db_cfg();$dbInfo=db_driver()==='mysql'?($cfg['mysql']['host']??'').' / '.($cfg['mysql']['database']??''):basename((string)($cfg['sqlite_path']??SQLITE));
    $i18nForm=post_form('save_i18n_settings','<div class="d-flex align-items-center justify-content-between gap-3 mb-4"><div><div class="fw-semibold">'.h(T('content_i18n_toggle')).'</div><div class="text-muted small">'.h(T(content_i18n_enabled()?'enabled':'disabled')).'</div></div><label class="ios-toggle"><input type="checkbox" name="content_i18n" value="1" '.(content_i18n_enabled()?'checked':'').'><span></span></label></div><div class="mb-3"><div class="fw-semibold mb-1">'.h(T('content_languages')).'</div><div class="text-muted small">'.h(T('content_languages_hint')).'</div></div>'.content_lang_checkbox_grid().'<button class="btn btn-dark w-100 mt-4">'.icon('check-lg').' '.h(T('save')).'</button>');
    $h.='<div class="row g-3"><div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100"><div class="d-flex align-items-start gap-3 mb-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon('translate').'</div><div><h2 class="h5 mb-1">'.h(T('language')).'</h2><p class="text-muted mb-0">'.h(T('language_hint')).'</p></div></div>'.$langForm.'</div></div>';
    $h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100"><div class="d-flex align-items-start gap-3 mb-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon(theme()==='dark'?'moon-stars':'sun').'</div><div><h2 class="h5 mb-1">'.h(T('theme')).'</h2><p class="text-muted mb-0">'.h(T('theme_hint')).'</p></div></div>'.$themeForm.'</div></div>';
    if(is_admin_user())$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100"><div class="d-flex align-items-start gap-3 mb-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon('database').'</div><div><h2 class="h5 mb-1">'.h(T('current_db')).'</h2><p class="text-muted mb-0">'.h($driver.' · '.$dbInfo).'</p></div></div><button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#resetDbModal">'.icon('arrow-counterclockwise').' '.h(T('db_reset')).'</button><div class="text-muted small mt-3">'.h(T('db_reset_hint')).'</div></div></div>';
    if(can_settings()){$pr=current_project();$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex align-items-start gap-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon('window-stack').'</div><div><h2 class="h5 mb-1">'.h(T('projects')).'</h2><p class="text-muted mb-1">'.h(T('projects_hint')).'</p>'.($pr?'<div class="small text-muted">'.h(T('project')).': <span class="fw-semibold text-body">'.h($pr['n']).'</span> <code>'.h($pr['s']).'</code></div>':'').'</div></div><div class="mt-auto pt-4"><button type="button" class="btn btn-dark rounded-pill w-100" data-bs-toggle="modal" data-bs-target="#projectsModal">'.icon('window-stack').' '.h(T('projects')).'</button></div></div></div>';}
    if(can_api())$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex align-items-start gap-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon('braces').'</div><div><h2 class="h5 mb-1">'.h(T('api')).'</h2><p class="text-muted mb-1">'.h(T('open_api')).'</p><code>?api=index</code></div></div><div class="mt-auto pt-4"><a class="btn btn-dark rounded-pill w-100" target="_blank" href="'.h(U(['api'=>'index'])).'">'.icon('braces').' '.h(T('open_api')).'</a></div></div></div>';
    if(is_admin_user())$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex align-items-start gap-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon('people').'</div><div><h2 class="h5 mb-1">'.h(T('users')).'</h2><p class="text-muted mb-1">'.h(T('settings')).'</p></div></div><div class="mt-auto pt-4"><a class="btn btn-dark rounded-pill w-100" href="'.h(U(['users'=>1])).'">'.icon('people').' '.h(T('users')).'</a></div></div></div>';
    if(can_settings())$h.='<div class="col-12"><div class="ios-surface p-4"><div class="d-flex align-items-start gap-3 mb-3"><div class="btn btn-outline-dark btn-icon disabled">'.icon('globe2').'</div><div><h2 class="h5 mb-1">'.h(T('content_settings')).'</h2><p class="text-muted mb-0">'.h(T('content_i18n_hint2')).'</p></div></div>'.$i18nForm.'</div></div>';
    $h.='</div>';
    if(is_admin_user())$h.=form_modal('resetDbModal',T('db_reset'),'reset_db_config','<p>'.h(T('db_reset_q')).'</p><div class="alert alert-warning rounded-4 border-0 mb-0">'.h(T('db_reset_hint')).'</div>','<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('arrow-counterclockwise').' '.h(T('db_reset')).'</button>');
    return $h;
}
function usersPage(){
    $rows=users();$edit=isset($_GET['uid'])?user_row((int)$_GET['uid']):null;$mods='';
    $actions='<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">'.icon('plus-lg').' '.h(T('new_user')).'</button>';
    $h=page_head(T('users'),h(T('settings')),$actions);
    $h.='<table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>'.h(T('username')).'</th><th>'.h(T('display_name')).'</th><th>'.h(T('role')).'</th><th>'.h(T('status')).'</th><th>'.h(T('created')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $u){$mid='deleteUserModal'.(int)$u['id'];$self=(int)$u['id']===current_user_id();$more=dd_menu([dd_link(T('edit_user'),U(['users'=>1,'uid'=>$u['id']]),'pencil'),$self?'':dd_modal(T('delete_user'),'#'.$mid,'trash3',true)]);$h.='<tr><td>'.(int)$u['id'].'</td><td><span class="fw-semibold">'.h($u['l']).'</span>'.($self?' <span class="badge text-bg-dark rounded-pill">me</span>':'').'</td><td>'.h($u['n']).'</td><td><span class="badge rounded-pill text-bg-light">'.h(T(in_array($u['role'],['admin','developer','editor','viewer'],true)?$u['role']:'viewer')).'</span></td><td><span class="badge '.($u['st']==='active'?'text-bg-success':'text-bg-secondary').'">'.h(T($u['st']==='active'?'active':'inactive')).'</span></td><td>'.h($u['ca']).'</td><td class="text-end">'.$more.'</td></tr>';if(!$self)$mods.=delete_user_modal($u,$mid);} 
    $h.=($rows?'':'<tr><td colspan="7" class="text-center text-muted py-4">'.h(T('no_entries')).'</td></tr>').'</tbody></table>';
    return $h=table_wrap($h).user_modal($edit).$mods;
}

function user_modal($u=null){
    $id=(int)($u['id']??0);$role=$u['role']??'editor';$st=$u['st']??'active';$self=$id&&$id===current_user_id();
    $body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('l',T('username'),$u['l']??'','text',['required'=>true,'autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('n',T('display_name'),$u['n']??'').'</div></div>'.inp('p',$id?T('new_password'):T('password'),'','password',$id?['autocomplete'=>'new-password']:['required'=>true,'autocomplete'=>'new-password']).($id?'<div class="form-text mb-3">'.h(T('password_hint')).'</div>':'').'<div class="row g-3"><div class="col-md-6">'.select_html('role',T('role'),['admin'=>T('admin'),'developer'=>T('developer'),'editor'=>T('editor'),'viewer'=>T('viewer')],$role).'</div><div class="col-md-6">'.select_html('st',T('status'),['active'=>T('active'),'inactive'=>T('inactive')],$st,$self?['disabled'=>true]:[]).($self?'<input type="hidden" name="st" value="active">':'').'</div></div>';
    $footer='<span class="me-auto"></span><button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal('userModal',$id?T('edit_user'):T('new_user'),'user',$body,$footer);
}
function delete_user_modal($u,$mid){
    $body='<input type="hidden" name="id" value="'.(int)$u['id'].'"><p>'.h(T('delete_user_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($u['l']).'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_user'),'del_user',$body,$footer);
}
function single_rows($c){
    $cid=(int)$c['id'];
    $e=single_entry($c,can_entries());
    $edit=null;
    if(isset($_GET['entry'])&&can_entries()){
        $requested=(int)$_GET['entry'];
        $edit=$requested>0?entry($requested):$e;
        if(!$edit)$edit=$e;
    }
    $actions=(can_schema()?'<a class="btn btn-outline-dark" href="'.h(U(['c'=>$cid,'fields'=>1])).'">'.icon('list-check').' '.h(T('fields')).'</a>':'').'<a class="btn btn-outline-dark" target="_blank" href="'.h(U(['api'=>'entries','c'=>$c['s']])).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head($c['n'],'<span class="badge rounded-pill text-bg-light me-2">'.h(T('single')).'</span><code>?api=entries&amp;c='.h($c['s']).'</code>',$actions);
    if($e){
        $apiParams=['api'=>'entry','c'=>$c['s'],'s'=>$e['s']];
        if(content_i18n_enabled())$apiParams['lang']=content_lang();
        $editBtn=can_entries()?'<a class="btn btn-dark rounded-pill w-100" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.icon('pencil').' '.h(T('edit_entry')).'</a>':'';
        $h.='<div class="ios-surface p-3 p-lg-4"><div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">';
        $h.='<div><div class="text-muted small mb-1">'.h(T('content')).'</div><h2 class="h4 mb-2">'.h($e['t']).'</h2><div class="d-flex flex-wrap gap-2 align-items-center"><code>'.h($e['s']).'</code><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span><span class="text-muted small">'.h(T('updated')).': '.h($e['ua']).'</span></div></div>';
        $h.='<div class="d-flex flex-wrap gap-2">'.$editBtn.dd_menu([dd_link(T('api'),U($apiParams),'braces','_blank')]).'</div>';
        $h.='</div></div>';
    }else{
        $h.='<div class="ios-surface p-4 text-center text-muted">'.h(T('no_entries')).'</div>';
    }
    return $h.collection_modal($c,'collectionEditModal').delete_collection_modal($c).($edit?entry_modal($c,$edit):'');
}
function rows($c){
    $cid=(int)$c['id'];
    if(collection_mode($c)==='single')return single_rows($c);
    $r=all('SELECT * FROM e WHERE cid=? ORDER BY id DESC',[$cid]);$edit=isset($_GET['entry'])&&((int)$_GET['entry']>0)?entry((int)$_GET['entry']):null;$mods='';
    $actions=(can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0])).'">'.icon('plus-lg').' '.h(T('new_entry')).'</a>':'').(can_schema()?'<a class="btn btn-outline-dark" href="'.h(U(['c'=>$cid,'fields'=>1])).'">'.icon('list-check').' '.h(T('fields')).'</a>':'').'<a class="btn btn-outline-dark" target="_blank" href="'.h(U(['api'=>'entries','c'=>$c['s']])).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head($c['n'],'<span class="badge rounded-pill text-bg-light me-2">'.h(T('multiple')).'</span><code>?api=entries&amp;c='.h($c['s']).'</code>',$actions);
    $h.='<table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>'.h(T('title')).'</th><th>'.h(T('slug')).'</th><th>'.h(T('status')).'</th><th>'.h(T('updated')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($r as $e){
        $mid='deleteEntryModal'.(int)$e['id'];
        $title=can_entries()?'<a class="link-dark fw-semibold" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.h($e['t']).'</a>':'<span class="fw-semibold">'.h($e['t']).'</span>';
        $open=can_entries()?'<a class="btn btn-outline-dark btn-icon" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.icon('pencil').'</a>':'';
        $more=dd_menu(array_merge([dd_link(T('api'),U(['api'=>'entry','c'=>$c['s'],'s'=>$e['s'],'lang'=>content_lang()]),'braces','_blank')],can_entries()?[dd_modal(T('delete'),'#'.$mid,'trash3',true)]:[]));
        $h.='<tr><td>'.$e['id'].'</td><td>'.$title.'</td><td><code>'.h($e['s']).'</code></td><td><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span></td><td>'.h($e['ua']).'</td><td class="text-end"><div class="d-inline-flex flex-wrap gap-2">'.$open.$more.'</div></td></tr>';
        if(can_entries())$mods.=delete_entry_modal($c,$e,$mid);
    }
    $h.=($r?'':'<tr><td colspan="6" class="text-center text-muted py-4">'.h(T('no_entries')).'</td></tr>').'</tbody></table>';
    return table_wrap($h).collection_modal($c,'collectionEditModal').delete_collection_modal($c).entry_modal($c,$edit).$mods;
}

function delete_collection_modal($c){
    $body='<input type="hidden" name="id" value="'.(int)$c['id'].'"><p>'.h(T('delete_collection_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($c['n']).'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-target="#collectionEditModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal('deleteCollectionModal',T('delete_collection'),'del_col',$body,$footer);
}
function delete_collection_from_list_modal($c,$mid){
    $body='<input type="hidden" name="id" value="'.(int)$c['id'].'"><p>'.h(T('delete_collection_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($c['n']).'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-target="#collectionsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_collection'),'del_col',$body,$footer);
}
function fieldsPage($c){
    $cid=(int)$c['id'];$edit=isset($_GET['fid'])?field((int)$_GET['fid']):null;$fs=fields($cid);$mods='';
    $actions='<a class="btn btn-outline-dark" href="'.h(U(['c'=>$cid])).'">'.icon('arrow-left').' '.h(T('entries')).'</a><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">'.icon('plus-lg').' '.h(T('new_field')).'</button><a class="btn btn-outline-dark" target="_blank" href="'.h(U(['api'=>'fields','c'=>$c['s']])).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head(T('fields').': '.$c['n'],h(T('schema')),$actions);
    $h.='<table class="table table-hover align-middle mb-0"><thead><tr><th>'.h(T('label')).'</th><th>'.h(T('key')).'</th><th>'.h(T('type')).'</th><th>'.h(T('required')).'</th><th>'.h(T('order')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($fs as $f){$mid='deleteFieldRowModal'.(int)$f['id'];$more=dd_menu([dd_link(T('edit_field'),U(['c'=>$cid,'fields'=>1,'fid'=>$f['id']]),'pencil'),dd_modal(T('delete'),'#'.$mid,'trash3',true)]);$h.='<tr><td>'.h($f['l']).'</td><td><code>'.h($f['k']).'</code></td><td><span class="badge rounded-pill text-bg-light">'.h($f['t']).'</span></td><td>'.h($f['r']?T('yes'):T('no')).'</td><td>'.h($f['o']).'</td><td class="text-end">'.$more.'</td></tr>';$mods.=delete_field_modal($c,$f,$mid);} 
    $h.=($fs?'':'<tr><td colspan="6" class="text-center text-muted py-4">'.h(T('no_fields')).'</td></tr>').'</tbody></table>';
    return table_wrap($h).field_modal($c,$edit).($edit?delete_field_modal($c,$edit):'').$mods;
}

function field_modal($c,$edit=null){
    $cid=(int)$c['id'];$isEdit=(bool)$edit;$types=['text'=>'text','textarea'=>'textarea','html'=>'html','number'=>'number','date'=>'date','bool'=>'bool','url'=>'url','image'=>'image','file'=>'file','json'=>'json','relation'=>T('relation')];
    $fieldPresets=[''=>T('custom'),'content'=>'Content / content / html','excerpt'=>'Excerpt / excerpt / textarea','image'=>'Image / image / image','file'=>'File / file / file','date'=>'Date / date / date','url'=>'URL / url / url','relation'=>'Relation / relation / relation'];
    $opt=$edit?field_options($edit):[];
    $target=(int)($opt['target_collection_id']??0);
    $mode=($opt['mode']??'single')==='multiple'?'multiple':'single';
    $lock=$isEdit?['disabled'=>true]:[];
    $rel='<div class="cms-relation-options">'.select_html('rel_cid',T('target_collection'),relation_target_options($cid),$target,$lock).select_html('rel_mode',T('relation_mode'),['single'=>T('relation_single'),'multiple'=>T('relation_multiple')],$mode,$lock).'</div>';
    $preset=$edit?'<div class="alert alert-light rounded-4 border-0 mb-3 small">'.h(T('field_schema_locked')).'</div>':'<div class="alert alert-light rounded-4 border-0 mb-3"><div class="fw-semibold mb-2">'.h(T('field_preset')).'</div><div class="small text-muted mb-3">'.h(T('field_preset_hint')).'</div>'.select_html('_field_preset',T('field_preset'),$fieldPresets,'',['class'=>'form-select rounded-4 bg-body-tertiary border-0 js-field-preset']).'</div>';
    $keyAttrs=$isEdit?['disabled'=>true]:['data-slug-target'=>'1'];
    $typeAttrs=array_merge(['class'=>'form-select rounded-4 bg-body-tertiary border-0 js-field-type'],$lock);
    $body='<input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'">'.$preset.inp('l',T('label'),$edit['l']??'','text',['required'=>true,'data-slug-source'=>'k']).inp('k',T('key'),$edit['k']??'','text',$keyAttrs).select_html('t',T('type'),$types,$edit['t']??'text',$typeAttrs).$rel.inp('o',T('order'),$edit['o']??0,'number').'<div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="r" value="1" id="req" '.(!empty($edit['r'])?'checked':'').'><label class="form-check-label" for="req">'.h(T('required')).'</label></div>';
    $delete=$edit?'<button type="button" class="btn btn-outline-danger rounded-pill me-auto" data-bs-target="#deleteFieldModal" data-bs-toggle="modal">'.icon('trash3').' '.h(T('delete')).'</button>':'<span class="me-auto"></span>';
    $footer=$delete.'<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal('fieldModal',$edit?T('edit_field'):T('new_field'),'field',$body,$footer);
}
function delete_field_modal($c,$f,$mid='deleteFieldModal'){
    $body='<input type="hidden" name="cid" value="'.(int)$c['id'].'"><input type="hidden" name="id" value="'.(int)$f['id'].'"><p>'.h(T('delete_field_q')).'</p><div class="alert alert-warning rounded-4 border-0 mb-0">'.h($f['l']).' / '.h($f['k']).'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-target="#fieldModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete'),'del_field',$body,$footer);
}
function entry_modal($c,$e=null,$back=null){
    $cid=(int)$c['id'];$id=(int)($e['id']??0);$cl=content_lang($_GET['cl']??null);$d=$e?data_lang($e,$cl,false):[];$st=$e['st']??'draft';
    $base=$back?:['c'=>$cid];
    $return=U(array_merge($base,['cl'=>$cl]));
    $langSelect='';
    if(content_i18n_enabled()){
        $opts=[];foreach(content_langs() as $lk)$opts[$lk]=CONTENT_LANGS[$lk]??$lk;
        $langSelect='<div class="alert alert-info rounded-4 border-0"><div class="fw-semibold">'.h(T('internationalization')).'</div><div class="small">'.h(T('i18n_hint')).'</div></div><div class="row g-3 align-items-end"><div class="col-md-6">'.select_html('_cl_select',T('content_language'),$opts,$cl,['onchange'=>'location.href='.json_encode(U(array_merge($base,['entry'=>$id]))).'+\'&cl=\'+encodeURIComponent(this.value)']).'</div></div>';
    }
    $body='<input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="_cl" value="'.h($cl).'"><input type="hidden" name="_return" value="'.h($return).'">'.$langSelect.'<div class="row g-3"><div class="col-md-5">'.inp('t',T('title'),$e['t']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-5">'.inp('s',T('slug'),$e['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-2">'.select_html('st',T('status'),['draft'=>T('draft'),'published'=>T('published')],$st).'</div></div><hr><h2 class="h5 mb-3">'.h(T('data')).(content_i18n_enabled()?' <span class="badge text-bg-light">'.h(CONTENT_LANGS[$cl]??$cl).'</span>':'').'</h2>';
    $fs=fields($cid);
    foreach($fs as $f){$k=$f['k'];$v=$d[$k]??'';$req=$f['r']?['required'=>true]:[];
        if(in_array($f['t'],['textarea','html','json'],true))$body.=area("d[$k]",$f['l'],$v,$req);
        elseif($f['t']==='bool')$body.='<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="d['.h($k).']" value="1" id="entry_'.h($k).'" '.(!empty($v)?'checked':'').'><label class="form-check-label" for="entry_'.h($k).'">'.h($f['l']).'</label></div>';
        elseif($f['t']==='relation'){
            $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$multi=($opt['mode']??'single')==='multiple';$items=relation_entries($target);$selected=array_map('intval',$multi?(array)$v:($v!==''&&$v!==null?[$v]:[]));
            $body.='<div class="mb-3"><label class="form-label">'.h($f['l']).($f['r']?' <span class="text-danger">*</span>':'').'</label>';
            if(!$target||!$items)$body.='<div class="alert alert-warning rounded-4 border-0 mb-0">'.h(T('no_relation_entries')).'</div>';
            elseif($multi){
                $pos=array_flip($selected);
                usort($items,function($a,$b)use($pos){$ai=(int)$a['id'];$bi=(int)$b['id'];$as=isset($pos[$ai]);$bs=isset($pos[$bi]);if($as!==$bs)return $as?-1:1;if($as&&$bs)return $pos[$ai]<=>$pos[$bi];return strcasecmp((string)$a['t'],(string)$b['t'])?:($ai<=>$bi);});
                $body.='<div class="relation-picker js-relation-picker" data-name="d['.h($k).'][]" data-required="'.($f['r']?'1':'0').'"><div class="mb-2"><input type="search" class="form-control rounded-pill js-relation-search" placeholder="'.h(T('search')).'"></div><div class="list-group rounded-4 overflow-hidden">';
                foreach($items as $it){$iid=(int)$it['id'];$checked=in_array($iid,$selected,true);$badge=$it['st']==='published'?'text-bg-success':'text-bg-secondary';$search=mb_strtolower(($it['t']??'').' '.($it['s']??'').' '.relation_status_label($it));$body.='<div class="list-group-item d-flex align-items-center gap-2 js-relation-item" data-search="'.h($search).'" data-selected="'.($checked?'1':'0').'"><input class="form-check-input js-relation-check" type="checkbox" name="d['.h($k).'][]" value="'.$iid.'" '.($checked?'checked':'').'><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">'.h($it['t']).'</div><div class="small text-muted"><code>'.h($it['s']).'</code> <span class="badge '.$badge.' rounded-pill ms-1">'.h(relation_status_label($it)).'</span></div></div><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-dark js-relation-up" title="'.h(T('move_up')).'">'.icon('arrow-up').'</button><button type="button" class="btn btn-outline-dark js-relation-down" title="'.h(T('move_down')).'">'.icon('arrow-down').'</button></div></div>';}
                $body.='</div><div class="form-text">'.h(T('relation_order_hint')).'</div></div>';
            }
            else{$body.='<select class="form-select rounded-4 bg-body-tertiary border-0" name="d['.h($k).']" '.($f['r']?'required':'').'><option value="">'.h(T('select_entry')).'</option>';foreach($items as $it){$sel=((int)$v===(int)$it['id'])?'selected':'';$body.='<option value="'.(int)$it['id'].'" '.$sel.'>'.h(relation_option_label($it)).'</option>';}$body.='</select>';}
            $body.='</div>';
        }
        elseif($f['t']==='file'||$f['t']==='image'){$old=is_array($v)?$v:null;$show=file_from_value($old);$body.='<div class="mb-3"><label class="form-label">'.h($f['l']).'</label>';if($show&&!empty($show['url']))$body.='<div class="border rounded p-2 mb-2 bg-light"><a target="_blank" href="'.h($show['url']).'">'.icon('paperclip').' '.h($show['name']??$show['file']??T('current_file')).'</a><span class="text-muted small ms-2">'.h($show['size']??'').'</span></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="_remove_file['.h($k).']" value="1" id="rm_'.h($k).'"><label class="form-check-label" for="rm_'.h($k).'">'.h(T('remove_file')).'</label></div>';$body.='<input type="hidden" name="_file['.h($k).']" value="'.h($old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'null').'"><input class="form-control" type="file" name="u['.h($k).']" '.($f['t']==='image'?'accept="image/*"':'').' '.($f['r']&&!$show?'required':'').'></div>';}
        else $body.=inp("d[$k]",$f['l'],$v,$f['t']==='number'?'number':($f['t']==='date'?'date':($f['t']==='url'?'url':'text')),$req);
    }
    if(!$fs)$body.='<div class="alert alert-warning rounded-4 border-0 mb-0">'.h(T('no_fields')).'</div>';
    $apiParams=['api'=>'entry','c'=>$c['s'],'s'=>$e['s']??''];if(content_i18n_enabled())$apiParams['lang']=$cl;
    $footer=($id?'<a class="btn btn-outline-dark rounded-pill me-auto" target="_blank" href="'.h(U($apiParams)).'">'.icon('braces').' '.h(T('api')).'</a>':'<span class="me-auto"></span>').'<a class="btn btn-light rounded-pill" href="'.h($return).'">'.h(T('cancel')).'</a><button class="btn btn-dark rounded-pill">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal('entryModal',$id?T('edit_entry'):T('new_entry'),'entry',$body,$footer,'modal-xl modal-fullscreen-lg-down','enctype="multipart/form-data"');
}
function entryForm($c,$e=null){return entry_modal($c,$e);}
function delete_entry_modal($c,$e,$mid=null){
    $mid=$mid?:'deleteEntryModal'.(int)$e['id'];
    $body='<input type="hidden" name="cid" value="'.(int)$c['id'].'"><input type="hidden" name="id" value="'.(int)$e['id'].'"><p>'.h(T('delete_entry_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($e['t']).'</div>';
    $footer='<button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger rounded-pill">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete'),'del_entry',$body,$footer);
}


/* ROUTER */
lang();theme();
if(isset($_GET['theme'])){set_theme($_GET['theme']);$q=$_GET;unset($q['theme']);go(U($q));}
if(isset($_GET['lang'])&&!isset($_GET['api'])){set_lang($_GET['lang']);$q=$_GET;unset($q['lang']);go(U($q));}
setup_action();
if(setup_required()){if(isset($_GET['api']))J(['ok'=>false,'error'=>'setup_required','message'=>T('setup_db')],503);setup_page();exit;}
boot();
action();
if(first_user_required()){if(isset($_GET['api']))J(['ok'=>false,'error'=>'first_user_required','message'=>T('first_user')],503);first_user_page();exit;}
if(isset($_GET['api']))api();
if(isset($_GET['logout'])){$l=lang();$th=theme();session_destroy();setcookie(LANG_COOKIE,$l,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);setcookie(THEME_COOKIE,$th,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);go('./');}
if(!ok()){login_page();exit;}
if(isset($_GET['export_schema'])){if(!can_schema()){flash(T('access_denied'));go('./');}$c=col((int)$_GET['export_schema']);if(!$c){flash(T('access_denied'));go('./');}$schema=export_collection_schema_array($c);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="collection-'.slug($c['s']).'-schema.json"');echo json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;}
if(isset($_GET['groups'])){if(!(can_schema()||can_entries())){flash(T('access_denied'));go('./');}layout(groupsPage(),0,isset($_GET['gid'])?'groupModal':null);exit;}
if(isset($_GET['group'])){$g=group_row((int)$_GET['group']);if(!$g||(int)$g['pid']!==current_project_id()){flash(T('access_denied'));go('./?groups=1');}layout(groupWorkspacePage($g),0,isset($_GET['entry'])?'entryModal':null);exit;}
if(isset($_GET['users'])){if(!is_admin_user()){flash(T('access_denied'));go('./');}layout(usersPage(),0,isset($_GET['uid'])?'userModal':null);exit;}
if(isset($_GET['settings'])){if(!can_settings()){flash(T('access_denied'));go('./');}layout(settingsPage(),0);exit;}
if(isset($_GET['files'])){if(!can_files()){flash(T('access_denied'));go('./');}layout(filesPage(),0);exit;}
$cs=cols();$cid=(int)($_GET['c']??($cs[0]['id']??0));$c=col($cid);
if(isset($_GET['new_col']))layout('<div class="alert alert-info">'.h(T('new_collection')).'</div>',0,'collectionNewModal');
elseif(isset($_GET['edit_col'])&&($ec=col((int)$_GET['edit_col'])))layout(rows($ec),(int)$ec['id'],'collectionEditModal');
elseif(isset($_GET['c'])&&!$c)layout('<div class="ios-surface p-5 text-center"><h1 class="h4">'.h(T('no_collections')).'</h1><button class="btn btn-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#collectionNewModal">'.icon('plus-lg').' '.h(T('new_collection')).'</button></div>');
elseif(isset($_GET['fields'])){if(!can_schema()){flash(T('access_denied'));go('./?c='.$cid);}layout(fieldsPage($c),$cid,isset($_GET['fid'])?'fieldModal':null);}
elseif(isset($_GET['entry'])){if(!can_entries()){flash(T('access_denied'));go('./?c='.$cid);}layout(rows($c),$cid,'entryModal');}
elseif(isset($_GET['c']))layout(rows($c),$cid);
else {if(!(can_schema()||can_entries())){flash(T('access_denied'));go('./?files=1');}layout(groupsPage(),0);}
