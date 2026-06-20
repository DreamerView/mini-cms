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
const AUTOSAVE_RETENTION_DAYS=30;
const MAINTENANCE_INTERVAL=86400;

/* I18N */
function lang(){static $l=null;if($l!==null)return $l;$x=$_COOKIE[LANG_COOKIE]??($_SESSION['_lang']??'ru');$l=isset(LANGS[$x])?$x:'ru';$_SESSION['_lang']=$l;return $l;}
function set_lang($l){$l=isset(LANGS[$l])?$l:'ru';$_SESSION['_lang']=$l;$_COOKIE[LANG_COOKIE]=$l;setcookie(LANG_COOKIE,$l,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $l;}
function theme(){static $x=null;if($x!==null)return $x;$v=$_COOKIE[THEME_COOKIE]??($_SESSION['_theme']??'light');$x=isset(THEMES[$v])?$v:'light';$_SESSION['_theme']=$x;return $x;}
function set_theme($v){$v=isset(THEMES[$v])?$v:'light';$_SESSION['_theme']=$v;$_COOKIE[THEME_COOKIE]=$v;setcookie(THEME_COOKIE,$v,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);return $v;}
function T($k){static $t=[
'app'=>['ru'=>'Мини Headless CMS','kk'=>'Mini Headless CMS','en'=>'Mini Headless CMS'],'login'=>['ru'=>'Логин','kk'=>'Логин','en'=>'Login'],'password'=>['ru'=>'Пароль','kk'=>'Құпиясөз','en'=>'Password'],'enter'=>['ru'=>'Войти','kk'=>'Кіру','en'=>'Sign in'],'logout'=>['ru'=>'Выйти','kk'=>'Шығу','en'=>'Logout'],'wrong_login'=>['ru'=>'Неверный логин или пароль','kk'=>'Логин немесе құпиясөз қате','en'=>'Wrong login or password'],
'home'=>['ru'=>'Главная','kk'=>'Басты бет','en'=>'Home'],'groups'=>['ru'=>'Разделы контента','kk'=>'Контент бөлімдері','en'=>'Content sections'],'group'=>['ru'=>'Раздел контента','kk'=>'Контент бөлімі','en'=>'Content section'],'new_group'=>['ru'=>'Создать раздел контента','kk'=>'Контент бөлімін жасау','en'=>'Create content section'],'edit_group'=>['ru'=>'Редактировать раздел','kk'=>'Бөлімді өзгерту','en'=>'Edit section'],'delete_group'=>['ru'=>'Удалить раздел','kk'=>'Бөлімді жою','en'=>'Delete section'],'delete_group_q'=>['ru'=>'Удалить раздел? Коллекции, записи, поля и файлы останутся в CMS.','kk'=>'Бөлімді жоясыз ба? Коллекциялар, жазбалар, өрістер және файлдар CMS ішінде қалады.','en'=>'Delete the section? Collections, entries, fields, and files will remain in the CMS.'],'group_saved'=>['ru'=>'Раздел контента сохранён','kk'=>'Контент бөлімі сақталды','en'=>'Content section saved'],'group_deleted'=>['ru'=>'Раздел контента удалён','kk'=>'Контент бөлімі жойылды','en'=>'Content section deleted'],'select_collections'=>['ru'=>'Выбери коллекции','kk'=>'Коллекцияларды таңдаңыз','en'=>'Select collections'],'group_api_hint'=>['ru'=>'Один запрос отдаёт несколько коллекций сразу','kk'=>'Бір сұраныс бірнеше коллекцияны бірге қайтарады','en'=>'One request returns multiple collections at once'],'collections'=>['ru'=>'Коллекции','kk'=>'Коллекциялар','en'=>'Collections'],'collection'=>['ru'=>'Коллекция','kk'=>'Коллекция','en'=>'Collection'],'new_collection'=>['ru'=>'Создать коллекцию','kk'=>'Коллекция жасау','en'=>'Create collection'],'edit_collection'=>['ru'=>'Редактировать коллекцию','kk'=>'Коллекцияны өзгерту','en'=>'Edit collection'],'name'=>['ru'=>'Название','kk'=>'Атауы','en'=>'Name'],'slug'=>['ru'=>'Slug','kk'=>'Slug','en'=>'Slug'],'description'=>['ru'=>'Описание','kk'=>'Сипаттама','en'=>'Description'],
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
,'developer'=>['ru'=>'Разработчик','kk'=>'Әзірлеуші','en'=>'Developer'],'viewer'=>['ru'=>'Наблюдатель','kk'=>'Көруші','en'=>'Viewer'],'api_private'=>['ru'=>'Этот API доступен только авторизованным пользователям','kk'=>'Бұл API тек авторизацияланған пайдаланушыларға қолжетімді','en'=>'This API is available only to authenticated users'],'too_many_attempts'=>['ru'=>'Слишком много попыток. Попробуй позже.','kk'=>'Әрекет тым көп. Кейінірек қайталап көріңіз.','en'=>'Too many attempts. Try again later.'],'password_latin'=>['ru'=>'Пароль должен быть от 10 до 72 символов. Можно использовать буквы, цифры и спецсимволы','kk'=>'Құпиясөз 10–72 таңба болуы керек. Әріптерді, сандарды және арнайы таңбаларды қолдануға болады','en'=>'Password must be 10–72 characters. Letters, numbers, and symbols are allowed'],'single'=>['ru'=>'Single','kk'=>'Single','en'=>'Single'],'multiple'=>['ru'=>'Multiple','kk'=>'Multiple','en'=>'Multiple'],'collection_type'=>['ru'=>'Тип коллекции','kk'=>'Коллекция түрі','en'=>'Collection type'],'collection_order'=>['ru'=>'Порядок коллекции','kk'=>'Коллекция реті','en'=>'Collection order'],'collections_hint'=>['ru'=>'Выберите коллекцию в контекстной панели. На планшете и телефоне список открывается в боковой панели.','kk'=>'Контекстік панельден коллекцияны таңдаңыз. Планшет пен телефонда тізім бүйірлік панельде ашылады.','en'=>'Choose a collection from the contextual panel. On tablet and mobile, the list opens in an offcanvas panel.'],'single_entry_limit'=>['ru'=>'Single-коллекция может иметь только одну запись','kk'=>'Single коллекцияда тек бір жазба болуы мүмкін','en'=>'Single collection can have only one entry'],'collection_type_locked'=>['ru'=>'Тип коллекции нельзя менять после создания','kk'=>'Коллекция түрін жасалғаннан кейін өзгертуге болмайды','en'=>'Collection type cannot be changed after creation'],
'relation'=>['ru'=>'Связь','kk'=>'Байланыс','en'=>'Relation'],'target_collection'=>['ru'=>'Связанная коллекция','kk'=>'Байланысқан коллекция','en'=>'Target collection'],'relation_mode'=>['ru'=>'Тип связи','kk'=>'Байланыс түрі','en'=>'Relation mode'],'relation_single'=>['ru'=>'Одна запись','kk'=>'Бір жазба','en'=>'Single entry'],'relation_multiple'=>['ru'=>'Несколько записей','kk'=>'Бірнеше жазба','en'=>'Multiple entries'],'select_entry'=>['ru'=>'Выбери запись','kk'=>'Жазбаны таңдаңыз','en'=>'Select entry'],'no_relation_entries'=>['ru'=>'В связанной коллекции пока нет записей','kk'=>'Байланысқан коллекцияда әзірге жазба жоқ','en'=>'Target collection has no entries yet'],'relation_target_required'=>['ru'=>'Для поля relation нужно выбрать связанную коллекцию','kk'=>'Relation өрісі үшін байланысқан коллекцияны таңдау керек','en'=>'Relation field requires a target collection'],'relation_invalid_entry'=>['ru'=>'Связанная запись не принадлежит выбранной коллекции','kk'=>'Байланысқан жазба таңдалған коллекцияға тиесілі емес','en'=>'Related entry does not belong to the selected collection'],'populate'=>['ru'=>'Раскрывать связи','kk'=>'Байланыстарды ашу','en'=>'Populate relations'],
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
'manage_collections'=>['ru'=>'Управление коллекциями','kk'=>'Коллекцияларды басқару','en'=>'Manage collections'],'save_collections'=>['ru'=>'Сохранить коллекции','kk'=>'Коллекцияларды сақтау','en'=>'Save collections'],'selected_collections'=>['ru'=>'Выбранные коллекции','kk'=>'Таңдалған коллекциялар','en'=>'Selected collections'],'all_collections'=>['ru'=>'Все коллекции','kk'=>'Барлық коллекциялар','en'=>'All collections'],'go_to_collection'=>['ru'=>'Перейти в коллекцию','kk'=>'Коллекцияға өту','en'=>'Go to collection'],'search'=>['ru'=>'Поиск','kk'=>'Іздеу','en'=>'Search'],'reset'=>['ru'=>'Сбросить','kk'=>'Тазарту','en'=>'Reset'],'no_results'=>['ru'=>'Ничего не найдено','kk'=>'Ештеңе табылмады','en'=>'No results'],'sort_asc'=>['ru'=>'Сортировать по возрастанию','kk'=>'Өсу ретімен сұрыптау','en'=>'Sort ascending'],'sort_desc'=>['ru'=>'Сортировать по убыванию','kk'=>'Кему ретімен сұрыптау','en'=>'Sort descending'],
'collection_preset'=>['ru'=>'Пресет коллекции','kk'=>'Коллекция пресеті','en'=>'Collection preset'],'preset_blank'=>['ru'=>'Пустая','kk'=>'Бос','en'=>'Blank'],'preset_page'=>['ru'=>'Страница','kk'=>'Бет','en'=>'Page'],'preset_blog'=>['ru'=>'Блог','kk'=>'Блог','en'=>'Blog'],'preset_product'=>['ru'=>'Товар','kk'=>'Тауар','en'=>'Product'],'preset_faq'=>['ru'=>'FAQ','kk'=>'FAQ','en'=>'FAQ'],'preset_contact'=>['ru'=>'Контакты','kk'=>'Байланыс','en'=>'Contacts'],
'field_preset'=>['ru'=>'Быстрое поле','kk'=>'Жылдам өріс','en'=>'Quick field'],'field_preset_hint'=>['ru'=>'Выбери пресет, чтобы быстро заполнить label/key/type','kk'=>'Label/key/type тез толтыру үшін пресет таңдаңыз','en'=>'Choose a preset to quickly fill label/key/type'],'custom'=>['ru'=>'Своё','kk'=>'Өзім','en'=>'Custom'],
'clone_collection'=>['ru'=>'Клонировать коллекцию','kk'=>'Коллекцияны клондау','en'=>'Clone collection'],'collection_cloned'=>['ru'=>'Коллекция склонирована','kk'=>'Коллекция клонданды','en'=>'Collection cloned'],'export_schema'=>['ru'=>'Экспорт схемы','kk'=>'Схеманы экспорттау','en'=>'Export schema'],'import_schema'=>['ru'=>'Импорт схемы','kk'=>'Схеманы импорттау','en'=>'Import schema'],'schema_imported'=>['ru'=>'Схема импортирована','kk'=>'Схема импортталды','en'=>'Schema imported'],'invalid_schema'=>['ru'=>'Некорректная схема','kk'=>'Схема дұрыс емес','en'=>'Invalid schema'],'relation_import_warning'=>['ru'=>'Некоторые relation-поля были импортированы как text, потому что связанная коллекция не найдена.','kk'=>'Кейбір relation өрістері text ретінде импортталды, себебі байланысқан коллекция табылмады.','en'=>'Some relation fields were imported as text because the target collection was not found.'],'field_schema_locked'=>['ru'=>'Key, type и настройки relation заблокированы после создания поля.','kk'=>'Өріс жасалғаннан кейін key, type және relation баптаулары бұғатталады.','en'=>'Key, type, and relation settings are locked after field creation.'],
'required_missing'=>['ru'=>'Заполните обязательное поле','kk'=>'Міндетті өрісті толтырыңыз','en'=>'Fill required field'],'relation_order_hint'=>['ru'=>'Отмеченные связи идут первыми. Кнопками вверх/вниз можно менять порядок.','kk'=>'Таңдалған байланыстар жоғары тұрады. Жоғары/төмен батырмаларымен ретін өзгертуге болады.','en'=>'Selected relations stay first. Use up/down buttons to change order.'],'move_up'=>['ru'=>'Выше','kk'=>'Жоғары','en'=>'Move up'],'move_down'=>['ru'=>'Ниже','kk'=>'Төмен','en'=>'Move down'],

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
'form_expected_fields'=>['ru'=>'Ожидаемые поля','kk'=>'Күтілетін өрістер','en'=>'Expected fields'],
'form_field_count'=>['ru'=>'Полей','kk'=>'Өрістер','en'=>'Fields'],
'message'=>['ru'=>'Сообщение','kk'=>'Хабарлама','en'=>'Message'],
'type_text'=>['ru'=>'Текст','kk'=>'Мәтін','en'=>'Text'],
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
    foreach(['p','password','mysql_password'] as $k)if(isset($data[$k]))$data[$k]='';
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
function go($u){header('Location: '.clean_url((string)$u));exit;}
function U(array $p=[]):string{if(isset($p['api'])&&!isset($p['project'])&&cfg_exists()&&!setup_required()){try{$pr=current_project();if($pr)$p['project']=$pr['s'];}catch(Throwable $e){}}return './'.($p?'?'.http_build_query($p):'');}
function J($x,$c=200){http_response_code($c);header('Content-Type:application/json;charset=utf-8');echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);exit;}

/* APP CONFIG */
function cfg_path(){return CONFIG_FILE;}
function cfg_exists(){return is_file(cfg_path());}
function cfg_read(){if(array_key_exists('_cfg_cache',$GLOBALS)&&is_array($GLOBALS['_cfg_cache']))return $GLOBALS['_cfg_cache'];if(!cfg_exists())return $GLOBALS['_cfg_cache']=[];$j=json_decode((string)file_get_contents(cfg_path()),true);return $GLOBALS['_cfg_cache']=is_array($j)?$j:[];}
function cfg_write(array $c){$dir=dirname(cfg_path());if(!is_dir($dir))mkdir($dir,0775,true);$c['updated_at']=now();$json=json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($json===false||file_put_contents(cfg_path(),$json,LOCK_EX)===false)throw new RuntimeException('Config write failed');$GLOBALS['_cfg_cache']=$c;clearstatcache(true,cfg_path());return $c;}
function cfg_cache_reset(){unset($GLOBALS['_cfg_cache']);}
function cfg_reset(){if(cfg_exists())@unlink(cfg_path());cfg_cache_reset();}
function cfg_update(callable $fn){$c=cfg_read();$fn($c);return cfg_write($c);}
function cfg_setting($k,$d=null){$c=cfg_read();return $c['settings'][$k]??$d;}
function content_i18n_enabled(){return (bool)cfg_setting('content_i18n',true);}
function content_langs(){$v=cfg_setting('content_langs',['ru','kk','en']);if(!is_array($v))$v=['ru','kk','en'];$v=array_values(array_intersect($v,array_keys(CONTENT_LANGS)));return $v?:['ru'];}
function default_content_lang(){
    $langs=content_langs();$v=(string)cfg_setting('content_default_lang',$langs[0]??'ru');
    return in_array($v,$langs,true)?$v:($langs[0]??'ru');
}
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
        CREATE TABLE IF NOT EXISTS e(id INTEGER PRIMARY KEY AUTOINCREMENT,cid INTEGER NOT NULL,uid INTEGER,t TEXT NOT NULL,s TEXT NOT NULL,st TEXT DEFAULT 'draft',j TEXT NOT NULL,ca TEXT,ua TEXT,UNIQUE(cid,s),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS files(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,onm TEXT NOT NULL,fn TEXT NOT NULL UNIQUE,p TEXT NOT NULL,u TEXT NOT NULL,mime TEXT,ext TEXT,sz INTEGER DEFAULT 0,st TEXT DEFAULT 'active',ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY AUTOINCREMENT,l TEXT NOT NULL UNIQUE,p TEXT NOT NULL,n TEXT,role TEXT DEFAULT 'admin',st TEXT DEFAULT 'active',ca TEXT,ua TEXT);
        CREATE TABLE IF NOT EXISTS g(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS gc(id INTEGER PRIMARY KEY AUTOINCREMENT,gid INTEGER NOT NULL,cid INTEGER NOT NULL,o INTEGER DEFAULT 0,UNIQUE(gid,cid),FOREIGN KEY(gid) REFERENCES g(id) ON DELETE CASCADE,FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS entry_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,eid INTEGER NOT NULL,cid INTEGER NOT NULL,uid INTEGER,t TEXT,s TEXT,st TEXT,j TEXT,changes TEXT,ca TEXT);
        CREATE TABLE IF NOT EXISTS entry_drafts(id INTEGER PRIMARY KEY AUTOINCREMENT,uid INTEGER NOT NULL,cid INTEGER NOT NULL,eid INTEGER NOT NULL DEFAULT 0,lang TEXT NOT NULL,payload TEXT NOT NULL,ua TEXT,UNIQUE(uid,cid,eid,lang));
        CREATE TABLE IF NOT EXISTS favorites(id INTEGER PRIMARY KEY AUTOINCREMENT,uid INTEGER NOT NULL,cid INTEGER NOT NULL,ca TEXT,UNIQUE(uid,cid));
        CREATE TABLE IF NOT EXISTS forms(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,n TEXT NOT NULL,s TEXT NOT NULL,d TEXT,st TEXT DEFAULT 'active',success_message TEXT,o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(pid,s));
        CREATE TABLE IF NOT EXISTS form_fields(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,fid INTEGER NOT NULL,l TEXT NOT NULL,k TEXT NOT NULL,t TEXT NOT NULL,r INTEGER DEFAULT 0,o INTEGER DEFAULT 0,ca TEXT,ua TEXT,UNIQUE(fid,k),FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE);
        CREATE TABLE IF NOT EXISTS form_submissions(id INTEGER PRIMARY KEY AUTOINCREMENT,pid INTEGER NOT NULL,fid INTEGER NOT NULL,st TEXT DEFAULT 'new',j TEXT NOT NULL,ip TEXT,agent TEXT,ref TEXT,ca TEXT,ua TEXT,FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE);");
    }else{
        D()->exec("CREATE TABLE IF NOT EXISTS p(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL UNIQUE,d TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS c(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,m VARCHAR(40) DEFAULT 'multiple',o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_collection_slug(pid,s))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS f(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cid INT UNSIGNED NOT NULL,l VARCHAR(255) NOT NULL,k VARCHAR(160) NOT NULL,t VARCHAR(40) NOT NULL,x MEDIUMTEXT,r TINYINT DEFAULT 0,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE(cid,k),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS e(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,cid INT UNSIGNED NOT NULL,uid INT UNSIGNED,t VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,st VARCHAR(40) DEFAULT 'draft',j MEDIUMTEXT NOT NULL,ca DATETIME,ua DATETIME,UNIQUE(cid,s),FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS files(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,onm VARCHAR(255) NOT NULL,fn VARCHAR(255) NOT NULL UNIQUE,p VARCHAR(255) NOT NULL,u VARCHAR(255) NOT NULL,mime VARCHAR(120),ext VARCHAR(20),sz BIGINT DEFAULT 0,st VARCHAR(40) DEFAULT 'active',ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS users(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,l VARCHAR(120) NOT NULL UNIQUE,p VARCHAR(255) NOT NULL,n VARCHAR(255),role VARCHAR(40) DEFAULT 'admin',st VARCHAR(40) DEFAULT 'active',ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS g(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_group_slug(pid,s))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS gc(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,gid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,o INT DEFAULT 0,UNIQUE(gid,cid),FOREIGN KEY(gid) REFERENCES g(id) ON DELETE CASCADE,FOREIGN KEY(cid) REFERENCES c(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS entry_versions(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,eid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,uid INT UNSIGNED,t VARCHAR(255),s VARCHAR(160),st VARCHAR(40),j MEDIUMTEXT,changes MEDIUMTEXT,ca DATETIME,INDEX(eid),INDEX(cid))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS entry_drafts(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,eid INT UNSIGNED NOT NULL DEFAULT 0,lang VARCHAR(20) NOT NULL,payload MEDIUMTEXT NOT NULL,ua DATETIME,UNIQUE KEY unique_entry_draft(uid,cid,eid,lang))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS favorites(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,uid INT UNSIGNED NOT NULL,cid INT UNSIGNED NOT NULL,ca DATETIME,UNIQUE KEY unique_favorite(uid,cid))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS forms(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL,d TEXT,st VARCHAR(40) DEFAULT 'active',success_message TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE KEY unique_project_form_slug(pid,s),INDEX(pid),INDEX(st))ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS form_fields(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,fid INT UNSIGNED NOT NULL,l VARCHAR(255) NOT NULL,k VARCHAR(160) NOT NULL,t VARCHAR(40) NOT NULL,r TINYINT DEFAULT 0,o INT DEFAULT 0,ca DATETIME,ua DATETIME,UNIQUE KEY unique_form_field_key(fid,k),INDEX form_fields_project(pid,fid),FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS form_submissions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,pid INT UNSIGNED NOT NULL,fid INT UNSIGNED NOT NULL,st VARCHAR(40) DEFAULT 'new',j MEDIUMTEXT NOT NULL,ip VARCHAR(64),agent TEXT,ref TEXT,ca DATETIME,ua DATETIME,INDEX form_project_created(pid,fid,ca),INDEX form_status(fid,st),FOREIGN KEY(fid) REFERENCES forms(id) ON DELETE CASCADE)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    ensure_users_compat();ensure_files_compat();ensure_collection_compat();ensure_fields_compat();ensure_entries_compat();ensure_versions_compat();ensure_projects_compat();ensure_groups_compat();seed_users();
    if((int)D()->query('SELECT COUNT(*) FROM c')->fetchColumn())return;
    $n=now();$pid=current_project_id();
    $cid=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$pid,'Pages','pages','Headless pages','multiple',0,$n,$n]);
    add_default_fields($cid);
    run('INSERT INTO e(cid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$cid,null,'Home','home','published',json_encode(['content'=>'<h1>Hello</h1><p>Данные идут из headless CMS.</p>','meta_description'=>'Главная страница'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$n,$n]);
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
function ensure_groups_compat(){
    if(!has_col('g','o'))D()->exec(db_driver()==='sqlite'?"ALTER TABLE g ADD COLUMN o INTEGER DEFAULT 0":"ALTER TABLE g ADD COLUMN o INT DEFAULT 0");
    run("UPDATE g SET o=0 WHERE o IS NULL");
}
function ensure_fields_compat(){
    if(!has_col('f','x'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE f ADD COLUMN x TEXT':'ALTER TABLE f ADD COLUMN x MEDIUMTEXT');
}
function ensure_entries_compat(){
    if(!has_col('e','uid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE e ADD COLUMN uid INTEGER':'ALTER TABLE e ADD COLUMN uid INT UNSIGNED');
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
    if(db_driver()==='sqlite')D()->exec("CREATE TABLE IF NOT EXISTS p(id INTEGER PRIMARY KEY AUTOINCREMENT,n TEXT NOT NULL,s TEXT NOT NULL UNIQUE,d TEXT,o INTEGER DEFAULT 0,ca TEXT,ua TEXT)");
    else D()->exec("CREATE TABLE IF NOT EXISTS p(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,n VARCHAR(255) NOT NULL,s VARCHAR(160) NOT NULL UNIQUE,d TEXT,o INT DEFAULT 0,ca DATETIME,ua DATETIME)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $n=now();if(!(int)D()->query('SELECT COUNT(*) FROM p')->fetchColumn())run('INSERT INTO p(n,s,d,o,ca,ua)VALUES(?,?,?,?,?,?)',['Default','default','Default workspace',0,$n,$n]);
    $pid=(int)D()->query('SELECT id FROM p ORDER BY o,id LIMIT 1')->fetchColumn();
    if(!has_col('c','pid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE c ADD COLUMN pid INTEGER':'ALTER TABLE c ADD COLUMN pid INT UNSIGNED');
    if(!has_col('g','pid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE g ADD COLUMN pid INTEGER':'ALTER TABLE g ADD COLUMN pid INT UNSIGNED');
    if(!has_col('files','pid'))D()->exec(db_driver()==='sqlite'?'ALTER TABLE files ADD COLUMN pid INTEGER':'ALTER TABLE files ADD COLUMN pid INT UNSIGNED');
    run('UPDATE c SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);run('UPDATE g SET pid=? WHERE pid IS NULL OR pid=0',[$pid]);run("UPDATE files SET pid=? WHERE (pid IS NULL OR pid=0) AND COALESCE(st,'active')!='global_trash'",[$pid]);
}
function projects(){return all('SELECT * FROM p ORDER BY o,n,id');}
function project($id){$id=(int)$id;return $id?one('SELECT * FROM p WHERE id=?',[$id]):null;}
function project_by_slug($s){$s=slug($s);return one('SELECT * FROM p WHERE s=?',[$s]);}
function default_project_id(){return (int)D()->query('SELECT id FROM p ORDER BY o,id LIMIT 1')->fetchColumn();}
function current_project_id(){static $pid=null;if($pid!==null)return $pid;$id=(int)($_SESSION['_pid']??0);if(!$id||!project($id))$id=default_project_id();$_SESSION['_pid']=$id;return $pid=$id;}
function current_project(){return project(current_project_id());}
function api_project_id(){if(isset($_GET['project'])||isset($_GET['p'])){$p=project_by_slug($_GET['project']??$_GET['p']);return $p?(int)$p['id']:0;}return default_project_id();}
function seed_default_group($pid=null){return;}
function cols($pid=null){$pid=$pid?:current_project_id();return all('SELECT * FROM c WHERE pid=? ORDER BY o,n,id',[$pid]);}
function col($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT * FROM c WHERE id=? AND pid=?',[$id,$pid]):null;}
function col_by_slug($s,$pid=null){$s=slug($s);$pid=$pid?:current_project_id();return one('SELECT * FROM c WHERE s=? AND pid=?',[$s,$pid]);}
function groups($pid=null){$pid=$pid?:current_project_id();return all('SELECT * FROM g WHERE pid=? ORDER BY o,n,id',[$pid]);}
function group_row($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT * FROM g WHERE id=? AND pid=?',[$id,$pid]):null;}
function group_by_slug($s,$pid=null){$s=slug($s);$pid=$pid?:current_project_id();return one('SELECT * FROM g WHERE s=? AND pid=?',[$s,$pid]);}
function group_col_ids($gid,$pid=null){$gid=(int)$gid;$pid=$pid?:current_project_id();return array_map('intval',array_column(all('SELECT gc.cid FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.gid=? AND g.pid=? AND c.pid=? ORDER BY gc.o,gc.id',[$gid,$pid,$pid]),'cid'));}
function group_cols($gid,$pid=null){$gid=(int)$gid;$pid=$pid?:current_project_id();return all('SELECT c.*,gc.o AS group_order FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.gid=? AND g.pid=? AND c.pid=? ORDER BY gc.o,c.o,c.n,gc.id',[$gid,$pid,$pid]);}
function collection_groups($cid,$pid=null){$cid=(int)$cid;$pid=$pid?:current_project_id();if(!$cid||!col($cid,$pid))return [];return all('SELECT g.* FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.cid=? AND g.pid=? AND c.pid=? ORDER BY g.o,g.n,g.id',[$cid,$pid,$pid]);}
function collection_group_ids($cid,$pid=null){return array_map('intval',array_column(collection_groups((int)$cid,$pid),'id'));}
function ungrouped_cols($pid=null){$pid=$pid?:current_project_id();return all('SELECT c.* FROM c WHERE c.pid=? AND NOT EXISTS(SELECT 1 FROM gc JOIN g ON g.id=gc.gid WHERE gc.cid=c.id AND g.pid=?) ORDER BY c.o,c.n,c.id',[$pid,$pid]);}
function ungrouped_count($pid=null){$pid=$pid?:current_project_id();return (int)q('SELECT COUNT(*) FROM c WHERE c.pid=? AND NOT EXISTS(SELECT 1 FROM gc JOIN g ON g.id=gc.gid WHERE gc.cid=c.id AND g.pid=?)',[$pid,$pid])->fetchColumn();}
function group_collection_count($gid,$pid=null){$gid=(int)$gid;$pid=$pid?:current_project_id();return (int)q('SELECT COUNT(*) FROM gc JOIN g ON g.id=gc.gid JOIN c ON c.id=gc.cid WHERE gc.gid=? AND g.pid=? AND c.pid=?',[$gid,$pid,$pid])->fetchColumn();}
function link_collection_to_group($gid,$cid,$order=null){$g=assert_group((int)$gid);$c=assert_collection((int)$cid);if((int)$g['pid']!==(int)$c['pid'])throw new Exception(T('access_denied'));if(one('SELECT id FROM gc WHERE gid=? AND cid=?',[(int)$g['id'],(int)$c['id']]))return false;if($order===null)$order=(int)q('SELECT COALESCE(MAX(o),0)+10 FROM gc WHERE gid=?',[(int)$g['id']])->fetchColumn();run('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[(int)$g['id'],(int)$c['id'],(int)$order]);return true;}
function unlink_collection_from_group($gid,$cid){$g=assert_group((int)$gid);$c=assert_collection((int)$cid);if((int)$g['pid']!==(int)$c['pid'])throw new Exception(T('access_denied'));q('DELETE FROM gc WHERE gid=? AND cid=?',[(int)$g['id'],(int)$c['id']]);}
function sync_collection_groups($cid,array $gids){$c=assert_collection((int)$cid);$valid=[];foreach(array_values(array_unique(array_filter(array_map('intval',$gids)))) as $gid){$g=group_row($gid);if(!$g||(int)$g['pid']!==(int)$c['pid'])throw new Exception(T('access_denied'));$valid[]=$gid;}$pdo=D();$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();try{q('DELETE FROM gc WHERE cid=?',[(int)$c['id']]);foreach($valid as $gid)link_collection_to_group($gid,(int)$c['id']);if($own)$pdo->commit();}catch(Throwable $tx){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $tx;}}
function unique_group_slug($base,$pid=null,$ignoreId=0){$pid=$pid?:current_project_id();$base=slug($base);$try=$base;$i=2;while($r=one('SELECT id FROM g WHERE pid=? AND s=?',[$pid,$try])){if($ignoreId&&(int)$r['id']===(int)$ignoreId)break;$try=$base.'-'.$i++;}return $try;}
function collection_file_stats($cid){$c=assert_collection((int)$cid);$ids=[];$walk=function($v)use(&$walk,&$ids){if(!is_array($v))return;if(!empty($v['file_id']))$ids[(int)$v['file_id']]=true;if(!empty($v['id'])&&!empty($v['file']))$ids[(int)$v['id']]=true;foreach($v as $vv)$walk($vv);};foreach(all('SELECT j FROM e WHERE cid=?',[(int)$c['id']]) as $row){$x=json_decode((string)($row['j']??'{}'),true);if(is_array($x))$walk($x);}if(!$ids)return ['count'=>0,'size'=>0];$count=0;$size=0;foreach(all("SELECT id,sz FROM files WHERE pid=? AND st!='deleted'",[current_project_id()]) as $f)if(isset($ids[(int)$f['id']])){$count++;$size+=(int)$f['sz'];}return ['count'=>$count,'size'=>$size];}
function collection_delete_stats($cid){$c=assert_collection((int)$cid);$files=collection_file_stats((int)$c['id']);return ['entries'=>(int)q('SELECT COUNT(*) FROM e WHERE cid=?',[(int)$c['id']])->fetchColumn(),'fields'=>(int)q('SELECT COUNT(*) FROM f WHERE cid=?',[(int)$c['id']])->fetchColumn(),'sections'=>(int)q('SELECT COUNT(*) FROM gc WHERE cid=?',[(int)$c['id']])->fetchColumn(),'files'=>$files['count'],'file_size'=>$files['size']];}
function collection_delete_message($c){$st=collection_delete_stats((int)$c['id']);return T('collection_delete_irreversible')."\n\n".T('entry_count').': '.$st['entries']."\n".T('field_count').': '.$st['fields']."\n".T('section_count').': '.$st['sections']."\n".T('files').': '.$st['files'].' · '.fmt_size($st['file_size'])."\n\n".T('collection_files_note');}
function collection_sections_badges($cid,$linked=true){$gs=collection_groups((int)$cid);if(!$gs)return '<span class="badge text-bg-light">'.h(T('without_section')).'</span>';$h='';foreach($gs as $g)$h.='<a class="badge text-bg-light" href="'.h(U(['group'=>(int)$g['id']])).'">'.h($g['n']).'</a>';return $h;}
function fields($cid,$pid=null){static $x=[];$cid=(int)$cid;$pid=$pid?:current_project_id();$cache=$pid.':'.$cid;if(!$cid||!col($cid,$pid))return [];if(!one('SELECT f.id FROM f JOIN c ON c.id=f.cid WHERE f.cid=? AND c.pid=? LIMIT 1',[$cid,$pid]))add_default_fields($cid);return $x[$cache]??=all('SELECT f.* FROM f JOIN c ON c.id=f.cid WHERE f.cid=? AND c.pid=? ORDER BY f.o,f.id',[$cid,$pid]);}
function field($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT f.* FROM f JOIN c ON c.id=f.cid WHERE f.id=? AND c.pid=?',[$id,$pid]):null;}
function entry($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT e.* FROM e JOIN c ON c.id=e.cid WHERE e.id=? AND c.pid=?',[$id,$pid]):null;}
function collection_project_id($cid){return (int)(q('SELECT pid FROM c WHERE id=?',[(int)$cid])->fetchColumn()?:0);}
function version_row($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT v.* FROM entry_versions v JOIN c ON c.id=v.cid JOIN e ON e.id=v.eid AND e.cid=v.cid WHERE v.id=? AND c.pid=?',[$id,$pid]):null;}
function assert_collection($cid){$c=col((int)$cid);if(!$c)throw new Exception(T('access_denied'));return $c;}
function assert_entry($eid,$cid=0){$e=entry((int)$eid);if(!$e||($cid&&(int)$e['cid']!==(int)$cid))throw new Exception(T('access_denied'));return $e;}
function assert_field($fid,$cid=0){$f=field((int)$fid);if(!$f||($cid&&(int)$f['cid']!==(int)$cid))throw new Exception(T('access_denied'));return $f;}
function assert_group($gid){$g=group_row((int)$gid);if(!$g)throw new Exception(T('access_denied'));return $g;}
function assert_file($id){$f=file_by_id((int)$id);if(!$f)throw new Exception(T('access_denied'));return $f;}

function single_entry($c,$create=false){
    $cid=(int)($c['id']??0);
    if(!$cid)return null;
    $e=one('SELECT * FROM e WHERE cid=? ORDER BY id LIMIT 1',[$cid]);
    if($e||!$create)return $e;
    $tm=now();
    $title=trim((string)($c['n']??'Single'))?:'Single';
    $slug=slug($c['s']??$title);
    $id=run('INSERT INTO e(cid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$cid,ok()?current_user_id():null,$title,$slug,'draft','{}',$tm,$tm]);
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


function favorite_ids(){if(!ok())return [];return array_map('intval',array_column(all('SELECT cid FROM favorites WHERE uid=?',[current_user_id()]),'cid'));}
function is_favorite($cid){return in_array((int)$cid,favorite_ids(),true);}
function recent_entries_get(){
    $rows=$_SESSION['_recent_entries']??[];return is_array($rows)?array_slice($rows,0,8):[];
}
function recent_entry_add($e){
    if(!$e)return;$item=['id'=>(int)$e['id'],'cid'=>(int)$e['cid'],'title'=>$e['t'],'slug'=>$e['s'],'time'=>time()];
    $rows=array_values(array_filter(recent_entries_get(),fn($x)=>(int)($x['id']??0)!==(int)$e['id']));array_unshift($rows,$item);$_SESSION['_recent_entries']=array_slice($rows,0,8);
}
function project_stats(){
    $pid=current_project_id();
    return [
        'collections'=>(int)q('SELECT COUNT(*) FROM c WHERE pid=?',[$pid])->fetchColumn(),
        'entries'=>(int)q('SELECT COUNT(*) FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[$pid])->fetchColumn(),
        'published'=>(int)q("SELECT COUNT(*) FROM e JOIN c ON c.id=e.cid WHERE c.pid=? AND e.st='published'",[$pid])->fetchColumn(),
        'files'=>(int)q("SELECT COUNT(*) FROM files WHERE pid=? AND st='active'",[$pid])->fetchColumn(),
    ];
}
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
    return ['drafts'=>$drafts,'versions'=>$versions];
}
function maintenance_maybe(){
    $last=(int)cfg_setting('maintenance_last',0);if($last&&time()-$last<MAINTENANCE_INTERVAL)return;
    cleanup_maintenance();cfg_update(function(&$c){$c['settings']['maintenance_last']=time();});
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
    foreach(all('SELECT j FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[current_project_id()]) as $r){$d=json_decode($r['j']??'{}',true);if(!is_array($d)||empty($d['_i18n']))continue;foreach($counts as $l=>$n)if(!empty($d[$l])&&is_array($d[$l])&&array_filter($d[$l],fn($v)=>$v!==''&&$v!==null&&$v!==[]))$counts[$l]++;}
    return $counts;
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
function collection_manage_buttons($c,$editMode='link',$labels=false){
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
    $delete=universal_delete_button(T('delete_collection'),'del_col',['id'=>$cid],T('delete_collection'),collection_delete_message($c),true,$deleteClass,'trash3',T('delete_collection'));
    return '<div class="d-inline-flex align-items-center gap-1 flex-shrink-0">'.$edit.$delete.'</div>';
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
function uploads(){if(!is_dir(UPLOAD_DIR))mkdir(UPLOAD_DIR,0775,true);return UPLOAD_DIR;}
function clean_ext($name){return strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));}
function file_url($name){return UPLOAD_URL.'/'.rawurlencode((string)$name);}
function mime_of($path){return function_exists('mime_content_type')&&is_file($path)?(mime_content_type($path)?:null):null;}
function file_out($r){return $r?['id'=>(int)$r['id'],'file_id'=>(int)$r['id'],'name'=>$r['onm'],'file'=>$r['fn'],'url'=>$r['u'],'size'=>(int)$r['sz'],'mime'=>$r['mime'],'ext'=>$r['ext'],'status'=>$r['st'],'origin_project_id'=>isset($r['opid'])?(int)$r['opid']:null,'origin_project_name'=>$r['opn']??null,'reason'=>$r['reason']??null,'created_at'=>$r['ca'],'updated_at'=>$r['ua']]:null;}
function file_by_id($id,$pid=null){$id=(int)$id;$pid=$pid?:current_project_id();return $id?one('SELECT * FROM files WHERE id=? AND pid=?',[$id,$pid]):null;}
function global_trash_file($id){return is_admin_user()?one("SELECT * FROM files WHERE id=? AND st='global_trash' AND pid IS NULL",[(int)$id]):null;}
function project_file_stats($pid){$r=one("SELECT COUNT(*) AS cnt,COALESCE(SUM(sz),0) AS total FROM files WHERE pid=? AND st!='deleted'",[(int)$pid]);return ['count'=>(int)($r['cnt']??0),'size'=>(int)($r['total']??0)];}
function file_from_value($v,$pid=null){$pid=$pid?:current_project_id();if(is_numeric($v))return file_out(file_by_id((int)$v,$pid));if(!is_array($v))return null;if(!empty($v['file_id']))return file_out(file_by_id((int)$v['file_id'],$pid))?:null;if(!empty($v['id']))return file_out(file_by_id((int)$v['id'],$pid))?:null;return !empty($v['file'])?$v:null;}
function save_file_row($orig,$name,$size,$ext,$mime){$n=now();return run('INSERT INTO files(pid,onm,fn,p,u,mime,ext,sz,st,ca,ua)VALUES(?,?,?,?,?,?,?,?,?,?,?)',[current_project_id(),$orig,$name,UPLOAD_URL.'/'.$name,file_url($name),$mime,$ext,$size,'active',$n,$n]);}
function upload_value($key,$type='file'){if(empty($_FILES['u']['name'][$key]))return null;$err=(int)($_FILES['u']['error'][$key]??UPLOAD_ERR_NO_FILE);if($err===UPLOAD_ERR_NO_FILE)return null;if($err!==UPLOAD_ERR_OK)throw new RuntimeException(T('upload_error'));$size=(int)$_FILES['u']['size'][$key];if($size>UPLOAD_MAX)throw new RuntimeException(T('file_too_large'));$orig=(string)$_FILES['u']['name'][$key];$tmp=(string)$_FILES['u']['tmp_name'][$key];$ext=clean_ext($orig);$allowed=$type==='image'?IMAGE_EXT:FILE_EXT;if(!$ext||!in_array($ext,$allowed,true))throw new RuntimeException(T('file_type_denied'));if($type==='image'&&!@getimagesize($tmp))throw new RuntimeException(T('file_type_denied'));$base=slug(pathinfo($orig,PATHINFO_FILENAME));$name=date('Ymd_His').'_'.bin2hex(random_bytes(4)).'_'.$base.'.'.$ext;$to=uploads().'/'.$name;if(!move_uploaded_file($tmp,$to))throw new RuntimeException(T('upload_error'));return ['file_id'=>save_file_row($orig,$name,$size,$ext,mime_of($to))];}
function used_file_ids_names($pid=null){$pid=$pid?:current_project_id();$ids=[];$names=[];foreach(all('SELECT e.j FROM e JOIN c ON c.id=e.cid WHERE c.pid=?',[$pid]) as $row){$x=json_decode($row['j']??'{}',true);if(!is_array($x))continue;$walk=function($v)use(&$walk,&$ids,&$names){if(!is_array($v))return;if(!empty($v['file_id']))$ids[(int)$v['file_id']]=true;if(!empty($v['id'])&&!empty($v['file']))$ids[(int)$v['id']]=true;if(!empty($v['file'])&&is_string($v['file']))$names[basename($v['file'])]=true;foreach($v as $vv)$walk($vv);};$walk($x);}return [$ids,$names];}
function resolve_files($v,$pid=null){$pid=$pid?:current_project_id();if(!is_array($v))return $v;if(isset($v['file_id'])&&count($v)<=2){$f=file_out(file_by_id((int)$v['file_id'],$pid));return $f?:null;}foreach($v as $k=>$vv)$v[$k]=resolve_files($vv,$pid);return $v;}
function field_options($f){$x=json_decode($f['x']??'{}',true);return is_array($x)?$x:[];}
function field_options_from_post($t,$cid){
    if($t!=='relation')return null;
    $target=(int)($_POST['rel_cid']??0);
    $mode=($_POST['rel_mode']??'single')==='multiple'?'multiple':'single';
    if(!$target||!col($target))throw new Exception(T('relation_target_required'));
    return json_encode(['target_collection_id'=>$target,'mode'=>$mode],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
function relation_target_options($currentCid=0){$opts=[''=>T('select_entry')];foreach(cols() as $c){if((int)$c['id']===(int)$currentCid)continue;$opts[(int)$c['id']]=$c['n'].' · '.$c['s'];}return $opts;}
function relation_entries($targetCid){$targetCid=(int)$targetCid;if(!$targetCid||!col($targetCid))return [];return all("SELECT * FROM e WHERE cid=? ORDER BY t,id",[$targetCid]);}
function relation_status_label($e){return ($e['st']??'draft')==='published'?T('published'):T('draft');}
function relation_option_label($e){return $e['t'].' · '.$e['s'].' · '.relation_status_label($e);}
function relation_entry_for_field($f,$id,$publicOnly=true){
    $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$id=(int)$id;$pid=collection_project_id((int)($f['cid']??0));
    if(!$target||!$id||!$pid||!col($target,$pid))return null;
    $sql='SELECT e.* FROM e JOIN c ON c.id=e.cid WHERE e.id=? AND e.cid=? AND c.pid=?'.($publicOnly?" AND e.st='published'":'').' LIMIT 1';
    return one($sql,[$id,$target,$pid]);
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
    $pid=collection_project_id((int)($e['cid']??0));
    $data=$l?data_lang($e,$l,true):(content_i18n_enabled()?data_lang($e,default_content_lang(),true):data($e));
    return ['id'=>(int)$e['id'],'title'=>$e['t'],'slug'=>$e['s'],'status'=>$e['st'],'data'=>resolve_files($data,$pid),'created_at'=>$e['ca'],'updated_at'=>$e['ua']];
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
    $pid=collection_project_id((int)$cid);
    $data=resolve_files($data,$pid?:current_project_id());
    if(!$cid||!is_array($data)||!$populate)return $data;
    foreach(fields((int)$cid,$pid?:current_project_id()) as $f){if(($f['t']??'')!=='relation')continue;$k=$f['k'];if(array_key_exists($k,$data))$data[$k]=relation_value_out($f,$data[$k],$l);}return $data;
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
function outGroup($g,$l=null,$withFields=false,$populate=false){$pid=(int)($g['pid']??0);$items=[];$by=[];foreach(group_cols((int)$g['id'],$pid) as $c){$x=['id'=>(int)$c['id'],'name'=>$c['n'],'slug'=>$c['s'],'description'=>$c['d'],'type'=>collection_mode($c),'order'=>(int)($c['group_order']??$c['o']??0),'data'=>collection_entries_out($c,$l,$populate)];if($withFields)$x['fields']=array_map('outField',fields((int)$c['id'],$pid));$items[]=$x;$by[$c['s']]=$x;}$pr=project($pid);return ['id'=>(int)$g['id'],'name'=>$g['n'],'slug'=>$g['s'],'description'=>$g['d'],'project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'lang'=>$l,'populate'=>$populate,'collections'=>$items,'by_slug'=>$by];}
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

function can_view_entries(){return role_is(['admin','developer','editor','viewer']);}
function can_forms(){return role_is(['admin','developer','editor']);}
function can_manage_forms(){return role_is(['admin','developer']);}
function can_view_form_submissions(){return role_is(['admin','editor']);}
function can_manage_form_submissions(){return role_is(['admin','editor']);}
function role_description($role){return T('role_'.$role.'_desc');}

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


/* FORMS */
function forms_all(){return all("SELECT f.*,(SELECT COUNT(*) FROM form_fields ff WHERE ff.fid=f.id AND ff.pid=f.pid) AS field_count,(SELECT COUNT(*) FROM form_submissions s WHERE s.fid=f.id AND s.pid=f.pid) AS submission_count,(SELECT MAX(ca) FROM form_submissions s WHERE s.fid=f.id AND s.pid=f.pid) AS last_submission FROM forms f WHERE f.pid=? ORDER BY f.o,f.n,f.id",[current_project_id()]);}
function form_row($id){return one('SELECT * FROM forms WHERE id=? AND pid=?',[(int)$id,current_project_id()]);}
function assert_form($id){$f=form_row((int)$id);if(!$f)throw new Exception(T('access_denied'));return $f;}
function form_field_types(){return ['text'=>T('type_text'),'textarea'=>T('type_textarea'),'email'=>T('type_email'),'tel'=>T('type_tel'),'number'=>T('type_number'),'integer'=>T('type_integer'),'boolean'=>T('type_boolean'),'date'=>T('type_date'),'datetime'=>T('type_datetime'),'url'=>T('type_url'),'json'=>T('type_json')];}
function form_fields_all($fid,$pid=null){$pid=$pid===null?current_project_id():(int)$pid;return all('SELECT * FROM form_fields WHERE fid=? AND pid=? ORDER BY o,id',[(int)$fid,$pid]);}
function form_field_key_normalize($value){$key=str_replace('-','_',slug((string)$value));$key=preg_replace('/_+/','_',$key);$key=trim((string)$key,'_');if($key===''||$key==='item')$key='field';if(preg_match('/^[0-9]/',$key))$key='field_'.$key;return mb_substr($key,0,120);}
function form_fields_from_post(){
    $rows=$_POST['form_fields']??[];if(!is_array($rows))$rows=[];$types=form_field_types();$out=[];$seen=[];$pos=10;
    foreach($rows as $row){if(!is_array($row))continue;$label=trim((string)($row['l']??''));$rawKey=trim((string)($row['k']??''));$type=(string)($row['t']??'text');$required=!empty($row['r'])?1:0;$order=(int)($row['o']??$pos);
        if($label===''&&$rawKey==='')continue;$key=form_field_key_normalize($rawKey!==''?$rawKey:$label);if($label===''||!isset($types[$type])||!preg_match('/^[a-z][a-z0-9_]*$/',$key))throw new Exception(T('invalid_form_field'));if(isset($seen[$key]))throw new Exception(T('duplicate_form_field_key'));$seen[$key]=1;$out[]=['l'=>$label,'k'=>$key,'t'=>$type,'r'=>$required,'o'=>$order];$pos+=10;
    }
    if(!$out)throw new Exception(T('form_fields_required'));usort($out,fn($a,$b)=>$a['o']<=>$b['o']);return $out;
}
function sync_form_fields($fid,$pid,array $defs){q('DELETE FROM form_fields WHERE fid=? AND pid=?',[(int)$fid,(int)$pid]);$tm=now();$o=10;foreach($defs as $f){run('INSERT INTO form_fields(pid,fid,l,k,t,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[(int)$pid,(int)$fid,$f['l'],$f['k'],$f['t'],(int)$f['r'],$o,$tm,$tm]);$o+=10;}}
function form_submission_row($id){return one('SELECT s.*,f.n AS form_name,f.s AS form_slug FROM form_submissions s JOIN forms f ON f.id=s.fid AND f.pid=s.pid WHERE s.id=? AND s.pid=?',[(int)$id,current_project_id()]);}
function assert_form_submission($id){$s=form_submission_row((int)$id);if(!$s)throw new Exception(T('access_denied'));return $s;}
function unique_form_slug($value,$pid,$ignore=0){$base=slug($value);$try=$base;$i=2;while(one('SELECT id FROM forms WHERE pid=? AND s=? AND id<>?',[(int)$pid,$try,(int)$ignore]))$try=$base.'-'.$i++;return $try;}
function form_endpoint($f,$projectSlug=null){
    if($projectSlug===null){$pr=current_project();$projectSlug=$pr['s']??'';}$query=http_build_query(['form'=>$f['s'],'project'=>$projectSlug]);$host=preg_replace('/[^a-z0-9.\-:\[\]]/i','',(string)($_SERVER['HTTP_HOST']??''));
    if($host==='')return './?'.$query;$https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$scheme=$https?'https':'http';$script=(string)($_SERVER['SCRIPT_NAME']??'/index.php');return $scheme.'://'.$host.$script.'?'.$query;
}
function form_status_badge($status){$map=['active'=>'success','inactive'=>'secondary','new'=>'primary','read'=>'success','spam'=>'warning'];$key=$status==='new'?'new_status':($status==='read'?'read_status':($status==='spam'?'spam_status':$status));return '<span class="badge text-bg-'.h($map[$status]??'secondary').'">'.h(T($key)).'</span>';}
function form_project_id_public(){if(isset($_GET['project'])||isset($_GET['p'])){$p=project_by_slug($_GET['project']??$_GET['p']);return $p?(int)$p['id']:0;}return default_project_id();}
function clean_form_value($value,$depth=0){if($depth>5)return null;if(is_array($value)){$out=[];$count=0;foreach($value as $k=>$v){if($count++>=100)break;$key=is_int($k)?$k:mb_substr(trim((string)$k),0,120);$out[$key]=clean_form_value($v,$depth+1);}return $out;}if(is_bool($value)||is_int($value)||is_float($value)||$value===null)return $value;return mb_substr(trim((string)$value),0,5000);}
function form_value_empty($value){return $value===null||$value===''||(is_array($value)&&count($value)===0);}
function normalize_form_field_value(array $field,$value){$type=$field['t'];$label=$field['l'];if($type==='boolean'){if(is_bool($value))return $value;$v=mb_strtolower(trim((string)$value));return in_array($v,['1','true','yes','on','да','иә'],true);}if($value===null)return null;if($type==='json'){if(is_array($value))return clean_form_value($value);$decoded=json_decode((string)$value,true);if(json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));return clean_form_value($decoded);}if(is_array($value))throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));$v=mb_substr(trim((string)$value),0,$type==='textarea'?12000:5000);if($v==='')return '';
    if($type==='email'&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));
    if($type==='url'&&!filter_var($v,FILTER_VALIDATE_URL))throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));
    if($type==='number'){if(!is_numeric($v))throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));return (float)$v;}
    if($type==='integer'){if(filter_var($v,FILTER_VALIDATE_INT)===false)throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));return (int)$v;}
    if($type==='date'&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));
    if($type==='datetime'&&!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?$/',$v))throw new RuntimeException(sprintf(T('form_invalid_field_value'),$label));
    return $v;
}
function validate_public_form_payload(array $form,array $payload,int $pid){$defs=form_fields_all((int)$form['id'],$pid);if(!$defs)return $payload;$out=[];foreach($defs as $field){$key=$field['k'];$exists=array_key_exists($key,$payload);$raw=$exists?$payload[$key]:(($field['t']??'')==='boolean'?false:null);$value=normalize_form_field_value($field,$raw);if(!empty($field['r'])&&((($field['t']??'')==='boolean'&&$value!==true)||form_value_empty($value)))throw new RuntimeException(sprintf(T('form_required_field'),$field['l']));$out[$key]=$value;}return $out;}
function public_form_payload(){
    $ct=strtolower((string)($_SERVER['CONTENT_TYPE']??''));$raw=[];
    if(str_contains($ct,'application/json')){$body=file_get_contents('php://input');$decoded=json_decode((string)$body,true);if(!is_array($decoded))$decoded=[];$raw=$decoded;}else $raw=$_POST;
    $honeypot=trim((string)($raw['_hp']??$raw['_website']??''));
    foreach(['_csrf','_a','_form','form','project','_hp','_website'] as $k)unset($raw[$k]);
    $data=clean_form_value($raw);if(!is_array($data))$data=[];
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)$json='{}';
    if(strlen($json)>65535)throw new RuntimeException(T('form_payload_too_large'));
    return [$data,$json,$honeypot];
}
function public_form_endpoint(){
    if(!array_key_exists('form',$_GET))return false;
    header('Access-Control-Allow-Origin: *');header('Access-Control-Allow-Methods: POST, GET, OPTIONS');header('Access-Control-Allow-Headers: Content-Type, Accept');header('Vary: Origin');
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if($method==='OPTIONS'){http_response_code(204);exit;}
    $pid=form_project_id_public();if(!$pid)J(['ok'=>false,'error'=>'project_not_found'],404);
    $slugValue=trim((string)($_GET['form']??''));if($slugValue==='')$slugValue=trim((string)($_POST['_form']??$_POST['form']??''));if($slugValue==='')J(['ok'=>false,'error'=>'form_required','message'=>T('form_not_found')],400);$slugValue=slug($slugValue);
    $f=one("SELECT * FROM forms WHERE pid=? AND s=?",[$pid,$slugValue]);if(!$f)J(['ok'=>false,'error'=>'form_not_found','message'=>T('form_not_found')],404);
    if(($f['st']??'inactive')!=='active')J(['ok'=>false,'error'=>'form_inactive','message'=>T('form_inactive')],410);
    $publicProject=one('SELECT s FROM p WHERE id=?',[$pid]);if($method==='GET'){$schema=array_map(fn($x)=>['label'=>$x['l'],'key'=>$x['k'],'type'=>$x['t'],'required'=>(bool)$x['r']],form_fields_all((int)$f['id'],$pid));J(['ok'=>true,'form'=>['name'=>$f['n'],'slug'=>$f['s'],'description'=>$f['d'],'fields'=>$schema],'endpoint'=>form_endpoint($f,$publicProject['s']??''),'method'=>'POST','accept'=>['application/x-www-form-urlencoded','multipart/form-data','application/json'],'files'=>false]);}
    if($method!=='POST')J(['ok'=>false,'error'=>'method_not_allowed'],405);
    try{[$payload,$json,$honeypot]=public_form_payload();$payload=validate_public_form_payload($f,$payload,$pid);$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false)$json='{}';}catch(Throwable $e){J(['ok'=>false,'error'=>'validation_failed','message'=>$e->getMessage()],422);}
    if(!$payload&&$honeypot==='')J(['ok'=>false,'error'=>'empty_payload','message'=>T('form_payload_empty')],422);
    $ip=client_ip();$cutoff=date('Y-m-d H:i:s',time()-600);$recent=(int)q('SELECT COUNT(*) FROM form_submissions WHERE fid=? AND pid=? AND ip=? AND ca>=?',[(int)$f['id'],$pid,$ip,$cutoff])->fetchColumn();if($recent>=20)J(['ok'=>false,'error'=>'rate_limited','message'=>T('form_rate_limited')],429);
    $status=$honeypot!==''?'spam':'new';$tm=now();$id=run('INSERT INTO form_submissions(pid,fid,st,j,ip,agent,ref,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$pid,(int)$f['id'],$status,$json,$ip,mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,1000),mb_substr((string)($_SERVER['HTTP_REFERER']??''),0,2000),$tm,$tm]);
    $message=trim((string)($f['success_message']??''))?:T('form_default_success');J(['ok'=>true,'submission_id'=>$id,'message'=>$message],201);
}

/* API */
function api(){
    $r=$_GET['api']?:'index';
    $pid=api_project_id();
    $pr=project($pid);
    if(!$pid||!$pr)J(['ok'=>false,'error'=>'project_not_found'],404);
    if($r==='index'){
        $route=function(array $params)use($pr){if($pr)$params=['project'=>$pr['s']]+$params;return '?'.http_build_query($params);};
        $routes=[];$firstCollection=one('SELECT * FROM c WHERE pid=? ORDER BY o,n,id LIMIT 1',[$pid]);$firstGroup=one('SELECT * FROM g WHERE pid=? ORDER BY o,n,id LIMIT 1',[$pid]);
        if($firstCollection){$routes[]=$route(['api'=>'entries','c'=>$firstCollection['s'],'lang'=>default_content_lang()]);$routes[]=$route(['api'=>'entries','c'=>$firstCollection['s'],'lang'=>default_content_lang(),'populate'=>1]);$firstEntry=one("SELECT s FROM e WHERE cid=? AND st='published' ORDER BY id LIMIT 1",[(int)$firstCollection['id']]);if($firstEntry)$routes[]=$route(['api'=>'entry','c'=>$firstCollection['s'],'s'=>$firstEntry['s'],'lang'=>default_content_lang(),'populate'=>1]);}
        if(ok()&&can_api()){$private=[$route(['api'=>'groups']),$route(['api'=>'collections'])];if($firstGroup)$private[]=$route(['api'=>'group','g'=>$firstGroup['s'],'lang'=>default_content_lang(),'populate'=>1]);if($firstCollection){$private[]=$route(['api'=>'fields','c'=>$firstCollection['s']]);$private[]=$route(['api'=>'schema','c'=>$firstCollection['s']]);}$routes=array_merge($routes,$private);}
        if(ok()&&can_files())$routes=array_merge($routes,[$route(['api'=>'files']),$route(['api'=>'files-trash'])]);
        J(['ok'=>true,'name'=>APP,'public'=>'published_content_only','project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'content_i18n'=>content_i18n_enabled(),'content_languages'=>content_langs(),'routes'=>$routes]);
    }
    if($r==='files'){api_require(ok()&&can_files());J(['ok'=>true,'project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'data'=>list_files('active',$pid)]);}
    if($r==='files-trash'){api_require(ok()&&can_files());J(['ok'=>true,'project'=>$pr?['id'=>(int)$pr['id'],'name'=>$pr['n'],'slug'=>$pr['s']]:null,'data'=>list_files('trash',$pid)]);}
    if($r==='collections'){api_require(ok()&&can_api());J(['ok'=>true,'data'=>array_map(fn($c)=>['id'=>(int)$c['id'],'name'=>$c['n'],'slug'=>$c['s'],'description'=>$c['d'],'type'=>collection_mode($c),'order'=>(int)($c['o']??0)],cols($pid))]);}
    if($r==='groups'){api_require(ok()&&can_api());J(['ok'=>true,'data'=>array_map(fn($g)=>['id'=>(int)$g['id'],'name'=>$g['n'],'slug'=>$g['s'],'description'=>$g['d'],'collections'=>array_map(fn($c)=>['slug'=>$c['s'],'type'=>collection_mode($c),'order'=>(int)($c['group_order']??$c['o']??0)],group_cols((int)$g['id'],$pid))],groups($pid))]);}
    if($r==='group'){$g=group_by_slug($_GET['g']??($_GET['s']??''),$pid);if(!$g)J(['ok'=>false,'error'=>'group_not_found'],404);$wf=isset($_GET['fields']);if($wf)api_require(ok()&&can_api());J(['ok'=>true,'group'=>outGroup($g,api_content_lang(),$wf,api_populate())]);}
    $c=col_by_slug($_GET['c']??'',$pid);
    if(!$c)J(['ok'=>false,'error'=>'collection_not_found'],404);
    $privateSchema=in_array($r,['fields','schema'],true)||isset($_GET['fields']);
    if($privateSchema)api_require(ok()&&can_api());
    $fs=array_map('outField',fields((int)$c['id'],$pid));
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

function action_modal_for($a){return match($a){'col'=>(!empty($_POST['id'])?'collectionEditModal':'collectionNewModal'),'field'=>'fieldModal','user'=>'userModal','group'=>'groupModal','project'=>'projectModal','form_def'=>'formModal',default=>''};}
function request_entry_payload($cid,$includeFiles=true){
    assert_collection((int)$cid);$cur=[];
    foreach(fields((int)$cid) as $f){
        $k=$f['k'];$ft=$f['t'];$v=$_POST['d'][$k]??'';
        if($ft==='bool'){$cur[$k]=!empty($_POST['d'][$k]);validate_required_value($f,$cur[$k]);continue;}
        if($ft==='relation'){$cur[$k]=validate_relation_value($f,$v);validate_required_value($f,$cur[$k]);continue;}
        if($ft==='file'||$ft==='image'){
            $old=json_decode((string)($_POST['_file'][$k]??'null'),true);$safe=null;
            if(is_array($old)){
                if(!empty($old['file_id'])){$fr=file_by_id((int)$old['file_id']);if($fr)$safe=['file_id'=>(int)$fr['id']];}
                elseif(!empty($old['file'])){$fr=one('SELECT * FROM files WHERE pid=? AND fn=?',[current_project_id(),basename((string)$old['file'])]);if($fr)$safe=['file_id'=>(int)$fr['id']];}
            }
            $cur[$k]=!empty($_POST['_remove_file'][$k])?null:$safe;
            if($includeFiles&&($up=upload_value($k,$ft)))$cur[$k]=$up;
            validate_required_value($f,$cur[$k]);continue;
        }
        $cur[$k]=$ft==='json'?normalize_json_value($v):$v;validate_required_value($f,$cur[$k]);
    }
    return $cur;
}

function action(){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    chk();$a=(string)($_POST['_a']??'');
    try{
        if($a==='autosave_entry'){
            if(!ok()||!can_entries())J(['ok'=>false,'error'=>'access_denied'],403);
            $cid=(int)($_POST['cid']??0);$eid=(int)($_POST['id']??0);assert_collection($cid);if($eid)assert_entry($eid,$cid);$cl=content_lang($_POST['_cl']??null);
            $draftData=is_array($_POST['d']??null)?$_POST['d']:[];foreach(fields($cid) as $f)if(($f['t']??'')==='bool'&&!array_key_exists($f['k'],$draftData))$draftData[$f['k']]=false;$payload=['t'=>(string)($_POST['t']??''),'s'=>(string)($_POST['s']??''),'st'=>(string)($_POST['st']??'draft'),'d'=>$draftData];
            $tm=entry_draft_save(current_user_id(),$cid,$eid,$cl,$payload);J(['ok'=>true,'saved_at'=>$tm,'files_included'=>false]);
        }
        if($a==='set_lang'){set_lang($_POST['lang']??'ru');go($_POST['_back']??'./');}
        if($a==='set_theme'){set_theme($_POST['theme']??'light');go($_POST['_back']??'./');}
        if($a==='first_user'){
            if(!first_user_required())go('./');$l=trim((string)($_POST['l']??''));$n=trim((string)($_POST['n']??''));$pw=(string)($_POST['p']??'');
            if($l==='')throw new Exception(T('user_required'));if($pw==='')throw new Exception(T('password_required'));if(!valid_password($pw))throw new Exception(T('password_latin'));
            $tm=now();$uid=run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$l,password_hash($pw,PASSWORD_DEFAULT),$n?:$l,'admin','active',$tm,$tm]);
            session_regenerate_id(true);$_SESSION['uid']=$uid;flash(T('user_saved'),'success');go('./');
        }
        if($a==='login'){
            $u=trim((string)($_POST['u']??''));$pw=(string)($_POST['p']??'');
            if(login_blocked($u)){flash(T('too_many_attempts'),'danger');go('./');}
            $row=one("SELECT * FROM users WHERE l=? AND st='active'",[$u]);
            if($row&&pass_ok($row['p'],$pw)){login_success($u);session_regenerate_id(true);$_SESSION['uid']=(int)$row['id'];flash(T('enter'),'success');go('./');}
            login_fail($u);flash(T('wrong_login'),'danger');go('./');
        }
        if(!ok())go('./');
        if($a==='set_project'){
            require_perm(can_view_entries());$id=(int)($_POST['id']??0);if(!$id||!project($id))throw new Exception(T('access_denied'));$_SESSION['_pid']=$id;flash(T('project_switched'),'success');go($_POST['_return']??U(['groups'=>1]));
        }
        if($a==='toggle_favorite'){
            require_perm(can_view_entries());$cid=(int)($_POST['cid']??0);assert_collection($cid);
            if(one('SELECT id FROM favorites WHERE uid=? AND cid=?',[current_user_id(),$cid]))run('DELETE FROM favorites WHERE uid=? AND cid=?',[current_user_id(),$cid]);
            else run('INSERT INTO favorites(uid,cid,ca)VALUES(?,?,?)',[current_user_id(),$cid,now()]);go($_POST['_return']??request_return());
        }
        if($a==='form_def'){
            require_perm(can_manage_forms());$id=(int)($_POST['id']??0);$n=trim((string)($_POST['n']??''));if($n==='')throw new Exception(T('name_required'));$ss=unique_form_slug($_POST['s']?:$n,current_project_id(),$id);$d=trim((string)($_POST['d']??''));$st=($_POST['st']??'active')==='inactive'?'inactive':'active';$msg=trim((string)($_POST['success_message']??''));$o=(int)($_POST['o']??0);$tm=now();$defs=form_fields_from_post();$pid=current_project_id();$pdo=D();$pdo->beginTransaction();try{
                if($id){assert_form($id);q('UPDATE forms SET n=?,s=?,d=?,st=?,success_message=?,o=?,ua=? WHERE id=? AND pid=?',[$n,$ss,$d,$st,$msg,$o,$tm,$id,$pid]);}
                else $id=run('INSERT INTO forms(pid,n,s,d,st,success_message,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$pid,$n,$ss,$d,$st,$msg,$o,$tm,$tm]);
                sync_form_fields($id,$pid,$defs);$pdo->commit();
            }catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}
            flash(T('form_saved'),'success');go(U(['forms'=>1]));
        }
        if($a==='del_form'){
            require_perm(can_manage_forms());$f=assert_form((int)($_POST['id']??0));$pdo=D();$pdo->beginTransaction();try{q('DELETE FROM form_submissions WHERE fid=? AND pid=?',[(int)$f['id'],current_project_id()]);q('DELETE FROM form_fields WHERE fid=? AND pid=?',[(int)$f['id'],current_project_id()]);q('DELETE FROM forms WHERE id=? AND pid=?',[(int)$f['id'],current_project_id()]);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('form_deleted'),'success');go(U(['forms'=>1]));
        }
        if($a==='form_submission_status'){
            require_perm(can_manage_form_submissions());$sub=assert_form_submission((int)($_POST['id']??0));$st=in_array((string)($_POST['st']??''),['new','read','spam'],true)?(string)$_POST['st']:'read';q('UPDATE form_submissions SET st=?,ua=? WHERE id=? AND pid=?',[$st,now(),(int)$sub['id'],current_project_id()]);flash(T('form_submission_status_saved'),'success');go(U(['form_submissions'=>(int)$sub['fid']]));
        }
        if($a==='del_form_submission'){
            require_perm(can_manage_form_submissions());$sub=assert_form_submission((int)($_POST['id']??0));q('DELETE FROM form_submissions WHERE id=? AND fid=? AND pid=?',[(int)$sub['id'],(int)$sub['fid'],current_project_id()]);flash(T('form_submission_deleted'),'success');go(U(['form_submissions'=>(int)$sub['fid']]));
        }
        if($a==='project'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$n=trim((string)($_POST['n']??''));$ss=slug($_POST['s']?:$n);$d=trim((string)($_POST['d']??''));$o=(int)($_POST['o']??0);$tm=now();if(!$n)throw new Exception(T('name_required'));
            if($id){if(!project($id))throw new Exception(T('access_denied'));q('UPDATE p SET n=?,s=?,d=?,o=?,ua=? WHERE id=?',[$n,$ss,$d,$o,$tm,$id]);}
            else $id=run('INSERT INTO p(n,s,d,o,ca,ua)VALUES(?,?,?,?,?,?)',[$n,$ss,$d,$o,$tm,$tm]);
            $_SESSION['_pid']=$id;flash(T('project_saved'),'success');go(U(['settings'=>1]));
        }
        if($a==='del_project'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$pr=project($id);if(!$pr)throw new Exception(T('access_denied'));if((int)D()->query('SELECT COUNT(*) FROM p')->fetchColumn()<=1)throw new Exception(T('cannot_delete_last_project'));if($id===current_project_id())throw new Exception(T('cannot_delete_active_project'));
            $pdo=D();$pdo->beginTransaction();
            try{
                q("UPDATE files SET opid=?,opn=?,pid=NULL,st='global_trash',reason='project_deleted',ua=? WHERE pid=? AND st!='deleted'",[$id,$pr['n'],now(),$id]);
                q("DELETE FROM files WHERE pid=? AND st='deleted'",[$id]);
                $cids=array_map('intval',array_column(all('SELECT id FROM c WHERE pid=?',[$id]),'id'));foreach($cids as $cid){run('DELETE FROM entry_drafts WHERE cid=?',[$cid]);run('DELETE FROM entry_versions WHERE cid=?',[$cid]);run('DELETE FROM favorites WHERE cid=?',[$cid]);run('DELETE FROM gc WHERE cid=?',[$cid]);}
                run('DELETE FROM gc WHERE gid IN (SELECT id FROM g WHERE pid=?)',[$id]);run('DELETE FROM g WHERE pid=?',[$id]);run('DELETE FROM c WHERE pid=?',[$id]);run('DELETE FROM form_submissions WHERE pid=?',[$id]);run('DELETE FROM forms WHERE pid=?',[$id]);run('DELETE FROM p WHERE id=?',[$id]);
                $pdo->commit();
            }catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}
            flash(T('project_deleted'),'success');go(U(['settings'=>1]));
        }
        if($a==='restore_global_file'){
            require_perm(is_admin_user());$f=global_trash_file((int)($_POST['id']??0));if(!$f)throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.basename((string)$f['fn']);if(!is_file($path))throw new Exception(T('file_missing'));q("UPDATE files SET pid=?,st='active',reason='restored',ua=? WHERE id=? AND st='global_trash' AND pid IS NULL",[current_project_id(),now(),(int)$f['id']]);flash(T('restore'),'success');go(U(['files'=>1,'tab'=>'global_trash']));
        }
        if($a==='delete_global_file_forever'){
            require_perm(is_admin_user());$f=global_trash_file((int)($_POST['id']??0));if(!$f)throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.basename((string)$f['fn']);if(is_file($path))@unlink($path);q("DELETE FROM files WHERE id=? AND st='global_trash' AND pid IS NULL",[(int)$f['id']]);flash(T('delete_forever'),'success');go(U(['files'=>1,'tab'=>'global_trash']));
        }
        if($a==='assign_orphan_file'){
            require_perm(is_admin_user());$fn=basename((string)($_POST['file']??''));$pid=(int)($_POST['pid']??0);if($fn===''||!project($pid)||!is_file(UPLOAD_DIR.'/'.$fn)||one('SELECT id FROM files WHERE fn=?',[$fn]))throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.$fn;$tm=now();run('INSERT INTO files(pid,onm,fn,p,u,mime,ext,sz,st,ca,ua,reason)VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$pid,$fn,$fn,UPLOAD_URL.'/'.$fn,file_url($fn),mime_of($path),clean_ext($fn),(int)filesize($path),'active',$tm,$tm,'orphan_assigned']);flash(T('file_saved'),'success');go(U(['files'=>1,'tab'=>'orphans']));
        }
        if($a==='delete_orphan_file'){
            require_perm(is_admin_user());$fn=basename((string)($_POST['file']??''));if($fn===''||one('SELECT id FROM files WHERE fn=?',[$fn]))throw new Exception(T('access_denied'));$path=UPLOAD_DIR.'/'.$fn;if(is_file($path))@unlink($path);flash(T('delete_forever'),'success');go(U(['files'=>1,'tab'=>'orphans']));
        }
        if($a==='save_i18n_settings'){
            require_perm(can_settings());$enabled=!empty($_POST['content_i18n']);$langs=$_POST['content_langs']??[];if(!is_array($langs))$langs=[];$langs=array_values(array_intersect($langs,array_keys(CONTENT_LANGS)));if(!$langs)throw new Exception(T('last_language_locked'));
            $default=(string)($_POST['content_default_lang']??'');if(!in_array($default,$langs,true))throw new Exception(T('choose_new_default_language'));
            $before=content_langs();$removed=array_values(array_diff($before,$langs));$usage=content_language_usage();$hasData=array_values(array_filter($removed,fn($l)=>(int)($usage[$l]??0)>0));
            cfg_update(function(&$c)use($enabled,$langs,$default){$c['settings']['content_i18n']=$enabled;$c['settings']['content_langs']=$langs;$c['settings']['content_default_lang']=$default;});
            $_COOKIE['cms_content_lang']=$default;setcookie('cms_content_lang',$default,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);
            flash(T('content_i18n_saved'),$hasData?'warning':'success');go(U(['settings'=>1]));
        }
        if($a==='cleanup_maintenance'){
            require_perm(can_settings());$r=cleanup_maintenance();flash(sprintf(T('maintenance_done'),$r['drafts'],$r['versions']),'success');go(U(['settings'=>1]));
        }
        if($a==='reset_db_config'){require_perm(is_admin_user());cfg_reset();session_destroy();go('./');}
        if($a==='user'){
            require_perm(is_admin_user());$id=(int)($_POST['id']??0);$l=trim((string)($_POST['l']??''));$n=trim((string)($_POST['n']??''));$pw=(string)($_POST['p']??'');$roles=['admin','developer','editor','viewer'];$role=in_array($_POST['role']??'editor',$roles,true)?$_POST['role']:'editor';$st=($_POST['st']??'active')==='active'?'active':'inactive';$tm=now();
            if(!$l)throw new Exception(T('user_required'));if($pw!==''&&!valid_password($pw))throw new Exception(T('password_latin'));if($id===current_user_id()&&$st!=='active')throw new Exception(T('self_protected'));
            if($id){if(!user_row($id))throw new Exception(T('access_denied'));if($pw!=='')q('UPDATE users SET l=?,n=?,p=?,role=?,st=?,ua=? WHERE id=?',[$l,$n,password_hash($pw,PASSWORD_DEFAULT),$role,$st,$tm,$id]);else q('UPDATE users SET l=?,n=?,role=?,st=?,ua=? WHERE id=?',[$l,$n,$role,$st,$tm,$id]);}
            else{if($pw==='')throw new Exception(T('password_required'));$id=run('INSERT INTO users(l,p,n,role,st,ca,ua)VALUES(?,?,?,?,?,?,?)',[$l,password_hash($pw,PASSWORD_DEFAULT),$n,$role,$st,$tm,$tm]);}
            flash(T('user_saved'),'success');go(U(['users'=>1]));
        }
        if($a==='del_user'){require_perm(is_admin_user());$id=(int)$_POST['id'];if(!user_row($id))throw new Exception(T('access_denied'));if($id===current_user_id())throw new Exception(T('self_protected'));run('DELETE FROM users WHERE id=?',[$id]);flash(T('user_deleted'),'success');go(U(['users'=>1]));}
        if($a==='group'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$n=trim((string)($_POST['n']??''));$ss=unique_group_slug($_POST['s']?:$n,current_project_id(),$id);$d=trim((string)($_POST['d']??''));$o=(int)($_POST['o']??0);$tm=now();if(!$n)throw new Exception(T('name_required'));
            if($id){assert_group($id);q('UPDATE g SET n=?,s=?,d=?,o=?,ua=? WHERE id=? AND pid=?',[$n,$ss,$d,$o,$tm,$id,current_project_id()]);}
            else $id=run('INSERT INTO g(pid,n,s,d,o,ca,ua)VALUES(?,?,?,?,?,?,?)',[current_project_id(),$n,$ss,$d,$o,$tm,$tm]);
            flash(T('group_saved'),'success');go(U(['groups'=>1]));
        }
        if($a==='group_cols'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$g=assert_group($id);$ids=$_POST['collections']??[];if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $cid){$cc=assert_collection($cid);if((int)$cc['pid']!==(int)$g['pid'])throw new Exception(T('access_denied'));}
            $pdo=D();$pdo->beginTransaction();try{q('DELETE FROM gc WHERE gid=?',[$id]);$o=10;foreach($ids as $cid){q('INSERT INTO gc(gid,cid,o)VALUES(?,?,?)',[$id,$cid,$o]);$o+=10;}$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('group_saved'),'success');go(U(['group'=>$id]));
        }
        if($a==='add_collection_to_group'){
            require_perm(can_schema());$gid=(int)($_POST['gid']??0);$g=assert_group($gid);$ids=$_POST['collections']??[];if(!is_array($ids))$ids=[];if(!empty($_POST['cid']))$ids[]=(int)$_POST['cid'];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $cid){$cc=assert_collection($cid);if((int)$cc['pid']!==(int)$g['pid'])throw new Exception(T('access_denied'));}$changed=0;$pdo=D();$pdo->beginTransaction();try{foreach($ids as $cid)if(link_collection_to_group($gid,$cid))$changed++;$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('collection_linked'),$changed?'success':'info');$ret=(string)($_POST['_return']??U(['group'=>$gid]));go($ret);
        }
        if($a==='unlink_group_collection'){
            require_perm(can_schema());$gid=(int)($_POST['gid']??0);$cid=(int)($_POST['cid']??0);unlink_collection_from_group($gid,$cid);flash(T('collection_unlinked'),'success');go($_POST['_return']??U(['group'=>$gid]));
        }
        if($a==='reorder_group_collections'){
            require_perm(can_schema());$gid=(int)($_POST['gid']??0);$g=assert_group($gid);$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $cid){$c=assert_collection($cid);if((int)$c['pid']!==(int)$g['pid']||!one('SELECT id FROM gc WHERE gid=? AND cid=?',[$gid,$cid]))throw new Exception(T('access_denied'));}$o=10;foreach($ids as $cid){q('UPDATE gc SET o=? WHERE gid=? AND cid=?',[$o,$gid,$cid]);$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='reorder_groups'){
            require_perm(can_schema());$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$ids=array_values(array_unique(array_filter(array_map('intval',$ids))));foreach($ids as $gid)assert_group($gid);$o=10;foreach($ids as $gid){q('UPDATE g SET o=?,ua=? WHERE id=? AND pid=?',[$o,now(),$gid,current_project_id()]);$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='del_group'){require_perm(can_schema());$g=assert_group((int)$_POST['id']);$pdo=D();$pdo->beginTransaction();try{q('DELETE FROM gc WHERE gid=?',[(int)$g['id']]);q('DELETE FROM g WHERE id=? AND pid=?',[(int)$g['id'],current_project_id()]);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('group_deleted'),'success');go(U(['groups'=>1]));}
        if($a==='col'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$n=trim((string)($_POST['n']??''));$ss=unique_collection_slug($_POST['s']?:$n,current_project_id(),$id);$d=trim((string)($_POST['d']??''));$o=(int)($_POST['o']??0);$tm=now();if(!$n)throw new Exception(T('name_required'));
            if($id){assert_collection($id);$sync=!empty($_POST['_sync_sections']);$gids=$_POST['section_ids']??[];if(!is_array($gids))$gids=[];if($sync){foreach(array_values(array_unique(array_filter(array_map('intval',$gids)))) as $gid)assert_group($gid);}$pdo=D();$pdo->beginTransaction();try{q('UPDATE c SET n=?,s=?,d=?,o=?,ua=? WHERE id=? AND pid=?',[$n,$ss,$d,$o,$tm,$id,current_project_id()]);if($sync)sync_collection_groups($id,$gids);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}}
            else{$m=($_POST['m']??'multiple')==='single'?'single':'multiple';$gid=(int)($_POST['add_group_id']??0);if($gid)assert_group($gid);$pdo=D();$pdo->beginTransaction();try{$id=run('INSERT INTO c(pid,n,s,d,m,o,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[current_project_id(),$n,$ss,$d,$m,$o,$tm,$tm]);add_preset_fields($id,(string)($_POST['preset']??'page'));if($gid)link_collection_to_group($gid,$id);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}}
            flash(T('collection_saved'),'success');$ret=trim((string)($_POST['_return']??''));if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go($ret);go(U(['c'=>$id]));
        }
        if($a==='del_col'){
            require_perm(can_schema());$c=assert_collection((int)$_POST['id']);$id=(int)$c['id'];$pdo=D();$pdo->beginTransaction();try{q('DELETE FROM entry_drafts WHERE cid=?',[$id]);q('DELETE FROM entry_versions WHERE cid=?',[$id]);q('DELETE FROM gc WHERE cid=?',[$id]);q('DELETE FROM favorites WHERE cid=?',[$id]);q('DELETE FROM c WHERE id=? AND pid=?',[$id,current_project_id()]);$pdo->commit();}catch(Throwable $tx){if($pdo->inTransaction())$pdo->rollBack();throw $tx;}flash(T('collection_deleted'),'success');go(U(['collections'=>1]));
        }
        if($a==='clone_col'){require_perm(can_schema());assert_collection((int)$_POST['id']);$id=clone_collection_schema((int)$_POST['id']);flash(T('collection_cloned'),'success');go(U(['c'=>$id]));}
        if($a==='import_col_schema'){
            require_perm(can_schema());if(empty($_FILES['schema']['tmp_name'])||!is_uploaded_file($_FILES['schema']['tmp_name']))throw new Exception(T('invalid_schema'));$raw=(string)file_get_contents($_FILES['schema']['tmp_name']);$schema=json_decode($raw,true);if(json_last_error()!==JSON_ERROR_NONE)throw new Exception(T('invalid_schema'));$warnings=[];$id=import_collection_schema_array($schema,$warnings);flash(T('schema_imported').(!empty($warnings['relation_target_missing'])?' '.T('relation_import_warning'):''),!empty($warnings['relation_target_missing'])?'warning':'success');go(U(['c'=>$id]));
        }
        if($a==='reorder_collections'||$a==='reorder_fields'){
            require_perm(can_schema());$ids=$_POST['ids']??[];if(is_string($ids))$ids=json_decode($ids,true);if(!is_array($ids))$ids=[];$o=10;
            foreach(array_map('intval',$ids) as $id){if($a==='reorder_collections'){$cc=col($id);if($cc)q('UPDATE c SET o=?,ua=? WHERE id=? AND pid=?',[$o,now(),$id,current_project_id()]);}else{$ff=field($id);if($ff)q('UPDATE f SET o=?,ua=? WHERE id=?',[$o,now(),$id]);}$o+=10;}J(['ok'=>true,'message'=>T('sort_saved')]);
        }
        if($a==='clean_files'){require_perm(can_files());$n=clean_files();flash(T('files_cleaned').$n,'success');go(U(['files'=>1]));}
        if($a==='restore_file'){require_perm(can_files());$f=assert_file((int)$_POST['id']);q("UPDATE files SET st='active',ua=? WHERE id=? AND pid=?",[now(),(int)$f['id'],current_project_id()]);flash(T('restore'),'success');go(U(['files'=>1,'tab'=>'trash']));}
        if($a==='delete_file_forever'){
            require_perm(can_files());$f=assert_file((int)$_POST['id']);if($f['st']!=='trash')throw new Exception(T('access_denied'));$fn=basename((string)$f['fn']);if($fn&&is_file(UPLOAD_DIR.'/'.$fn))@unlink(UPLOAD_DIR.'/'.$fn);q("UPDATE files SET st='deleted',ua=? WHERE id=? AND pid=?",[now(),(int)$f['id'],current_project_id()]);flash(T('delete_forever'),'success');go(U(['files'=>1,'tab'=>'trash']));
        }
        if($a==='field'){
            require_perm(can_schema());$id=(int)($_POST['id']??0);$cid=(int)$_POST['cid'];assert_collection($cid);$l=trim((string)($_POST['l']??''));$r=!empty($_POST['r'])?1:0;$o=(int)($_POST['o']??0);$tm=now();if(!$l)throw new Exception(T('field_required'));
            if($id){assert_field($id,$cid);q('UPDATE f SET l=?,r=?,o=?,ua=? WHERE id=? AND cid=?',[$l,$r,$o,$tm,$id,$cid]);}
            else{$k=str_replace('-','_',slug($_POST['k']?:$l));$allowed=['text','textarea','html','number','date','bool','url','image','file','json','relation'];$t=in_array($_POST['t']??'text',$allowed,true)?$_POST['t']:'text';$x=field_options_from_post($t,$cid);run('INSERT INTO f(cid,l,k,t,x,r,o,ca,ua)VALUES(?,?,?,?,?,?,?,?,?)',[$cid,$l,$k,$t,$x,$r,$o,$tm,$tm]);}
            flash(T('field_saved'),'success');go(U(['c'=>$cid,'fields'=>1]));
        }
        if($a==='del_field'){require_perm(can_schema());$cid=(int)$_POST['cid'];assert_collection($cid);$f=assert_field((int)$_POST['id'],$cid);q('DELETE FROM f WHERE id=? AND cid=?',[(int)$f['id'],$cid]);flash(T('field_deleted'),'success');go(U(['c'=>$cid,'fields'=>1]));}
        if($a==='entry'){
            require_perm(can_entries());$id=(int)($_POST['id']??0);$cid=(int)$_POST['cid'];$cc=assert_collection($cid);$old=$id?assert_entry($id,$cid):null;$cl=content_lang($_POST['_cl']??null);$t=trim((string)($_POST['t']??''));$ss=unique_entry_slug($_POST['s']?:$t,$cid,$id);$st=($_POST['st']??'draft')==='published'?'published':'draft';if(!$id&&collection_mode($cc)==='single'&&one('SELECT id FROM e WHERE cid=? LIMIT 1',[$cid]))throw new Exception(T('single_entry_limit'));$cur=request_entry_payload($cid,true);$tm=now();if(!$t)throw new Exception(T('title_required'));
            $j=content_i18n_enabled()?json_encode((function()use($old,$cl,$cur){$pack=$old?i18n_of($old):i18n_pack([]);$pack[$cl]=$cur;return $pack;})(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):json_encode($cur,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $new=['t'=>$t,'s'=>$ss,'st'=>$st,'j'=>$j];
            if($old){entry_snapshot($old,current_user_id(),entry_change_summary($old,$new,$cl));q('UPDATE e SET uid=?,t=?,s=?,st=?,j=?,ua=? WHERE id=? AND cid=?',[current_user_id(),$t,$ss,$st,$j,$tm,$id,$cid]);}
            else{$id=run('INSERT INTO e(cid,uid,t,s,st,j,ca,ua)VALUES(?,?,?,?,?,?,?,?)',[$cid,current_user_id(),$t,$ss,$st,$j,$tm,$tm]);}
            entry_draft_delete(current_user_id(),$cid,$old?(int)$old['id']:0,$cl);entry_draft_delete(current_user_id(),$cid,$id,$cl);flash(T('entry_saved'),'success');$ret=trim((string)($_POST['_return']??''));if($ret!==''&&!preg_match('~^[a-z][a-z0-9+.-]*:~i',$ret))go($ret);go(U(['c'=>$cid]));
        }
        if($a==='restore_version'){
            require_perm(can_entries());$vid=(int)$_POST['version_id'];$v=version_row($vid);if(!$v)throw new Exception(T('access_denied'));$e=assert_entry((int)$v['eid'],(int)$v['cid']);entry_snapshot($e,current_user_id(),[T('restore_version').' #'.$vid]);q('UPDATE e SET uid=?,t=?,s=?,st=?,j=?,ua=? WHERE id=? AND cid=?',[current_user_id(),$v['t'],$v['s'],$v['st'],$v['j'],now(),$e['id'],$e['cid']]);flash(T('version_restored'),'success');go(U(['c'=>$e['cid'],'entry'=>$e['id']]));
        }
        if($a==='del_entry'){
            require_perm(can_entries());$cid=(int)$_POST['cid'];assert_collection($cid);$e=assert_entry((int)$_POST['id'],$cid);run('DELETE FROM entry_drafts WHERE eid=? AND cid=?',[(int)$e['id'],$cid]);run('DELETE FROM entry_versions WHERE eid=? AND cid=?',[(int)$e['id'],$cid]);q('DELETE FROM e WHERE id=? AND cid=?',[(int)$e['id'],$cid]);flash(T('entry_deleted'),'success');go(U(['c'=>$cid]));
        }
    }catch(Throwable $e){
        if($a==='autosave_entry')J(['ok'=>false,'error'=>$e->getMessage()],422);
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

function ui_css(){static $x=null;return $x??=$x='
[data-bs-theme=light]{--ui-bg:#f5f5f7;--ui-panel:#ffffff;--ui-input:#ffffff;--ui-text:#1d1d1f;--ui-muted:#7d7d85;--ui-line:#e6e6eb;--ui-soft:#f2f2f7;--ui-blue:#007aff;--ui-red:#ff3b30;--ui-green:#34c759;--ui-on:#ffffff;--ui-red-soft:rgba(255,59,48,.12);--ui-green-soft:rgba(52,199,89,.14);--ui-success-text:#168a3a;--ui-radius:22px;--bs-body-bg:var(--ui-bg);--bs-body-color:var(--ui-text);--bs-emphasis-color:var(--ui-text);--bs-secondary-color:var(--ui-muted);--bs-tertiary-bg:var(--ui-soft);--bs-border-color:var(--ui-line);--bs-heading-color:var(--ui-text);--bs-link-color:var(--ui-blue);--bs-link-hover-color:var(--ui-blue);--bs-modal-bg:var(--ui-panel);--bs-modal-color:var(--ui-text);--bs-card-bg:var(--ui-panel)}
[data-bs-theme=dark]{--ui-bg:#07080b;--ui-panel:#17181d;--ui-input:#111217;--ui-text:#f5f5f7;--ui-muted:#a1a1aa;--ui-line:#30313a;--ui-soft:#24252c;--ui-blue:#0a84ff;--ui-red:#ff453a;--ui-green:#30d158;--ui-on:#ffffff;--ui-red-soft:rgba(255,69,58,.16);--ui-green-soft:rgba(48,209,88,.16);--ui-success-text:#32d74b;--ui-radius:22px;color-scheme:dark;--bs-body-bg:var(--ui-bg);--bs-body-color:var(--ui-text);--bs-emphasis-color:var(--ui-text);--bs-secondary-color:var(--ui-muted);--bs-tertiary-bg:var(--ui-soft);--bs-border-color:var(--ui-line);--bs-heading-color:var(--ui-text);--bs-link-color:var(--ui-blue);--bs-link-hover-color:var(--ui-blue);--bs-modal-bg:var(--ui-panel);--bs-modal-color:var(--ui-text);--bs-card-bg:var(--ui-panel)}
body.premium-bg{min-height:100vh;background:var(--ui-bg);color:var(--ui-text);font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",system-ui,sans-serif}a{text-decoration:none}.premium-brand{letter-spacing:-.035em}.premium-topbar{background:color-mix(in srgb,var(--ui-bg) 88%,transparent)!important;backdrop-filter:saturate(180%) blur(18px);border-bottom:1px solid var(--ui-line);color:var(--ui-text)}.premium-topbar>.container-fluid{position:relative}.premium-topbar .navbar-collapse{position:static}.premium-nav{position:static;gap:.85rem!important;margin-left:1rem}.premium-nav .nav-link{padding:.65rem .15rem!important;font-weight:680;letter-spacing:-.015em;white-space:nowrap}.premium-nav .nav-link.btn{line-height:1.5}.premium-actions{margin-left:auto}.project-name{max-width:180px}.app-frame{min-width:0}.app-content{min-width:0}.btn-primary{box-shadow:0 .35rem 1rem color-mix(in srgb,var(--ui-blue) 22%,transparent)}.btn-secondary{background:var(--ui-soft)!important;color:var(--ui-text)!important}.ios-actions .btn{min-height:2.55rem}.ios-actions .btn:not(.btn-primary){font-weight:620}
.ios-shell{max-width:1680px;margin:0 auto}.ios-sidebar{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:var(--ui-radius);overflow:hidden}.ios-sidebar .list-group{padding:.5rem}.ios-sidebar .list-group-item{border:0!important;border-radius:16px!important;margin:.15rem 0;background:transparent;color:var(--ui-text)}.ios-sidebar .list-group-item:hover{background:var(--ui-soft)}.ios-sidebar .list-group-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.ios-sidebar .list-group-item.active small{color:color-mix(in srgb,var(--ui-on) 72%,transparent)!important}
.ios-head{display:flex;gap:1rem;align-items:flex-end;justify-content:space-between;margin-bottom:1rem}.ios-title{font-size:1.85rem;line-height:1.05;font-weight:760;letter-spacing:-.04em;margin:0;color:var(--ui-text)}.ios-sub{color:var(--ui-muted);font-size:.9rem;margin-top:.25rem}.ios-actions{display:flex;gap:.55rem;flex-wrap:wrap;justify-content:flex-end}.ios-surface{background:var(--ui-panel);border:1px solid var(--ui-line);border-radius:var(--ui-radius);overflow:hidden}.ios-toolbar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}.ios-kicker{font-size:.76rem;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--ui-muted);margin-bottom:.25rem}
.btn{border-radius:13px!important;font-weight:650;border-width:0!important}.nav-pills .nav-link,.btn.btn-pill{border-radius:999px!important}.btn-icon{border-radius:50%!important}
.btn-dark,.btn-primary{background:var(--ui-blue)!important;color:var(--ui-on)!important}.btn-outline-dark,.btn-light,.btn-outline-light,.btn-outline-secondary{background:var(--ui-soft)!important;color:var(--ui-text)!important;border:0!important}.btn-outline-dark:hover,.btn-light:hover,.btn-outline-light:hover,.btn-outline-secondary:hover{background:var(--ui-line)!important;color:var(--ui-text)!important}.btn-danger,.btn-outline-danger{background:var(--ui-red-soft)!important;color:var(--ui-red)!important;border:0!important}.btn-danger:hover,.btn-outline-danger:hover{background:var(--ui-red)!important;color:var(--ui-on)!important}.btn-icon{width:2.35rem;height:2.35rem;display:inline-flex;align-items:center;justify-content:center;padding:0!important}
.form-control,.form-select{border:1px solid var(--ui-line)!important;border-radius:16px!important;background:var(--ui-input)!important;color:var(--ui-text)!important;padding:.72rem .9rem}.form-control::placeholder{color:var(--ui-muted)!important}.form-select-sm{padding:.45rem 2.25rem .45rem .85rem}.form-control:focus,.form-select:focus{border-color:var(--ui-blue)!important;box-shadow:0 0 0 .22rem color-mix(in srgb,var(--ui-blue) 18%,transparent)!important}.form-label{color:var(--ui-muted);font-size:.82rem;font-weight:650;margin-bottom:.35rem}.form-check-input{background-color:var(--ui-input);border-color:var(--ui-line)}.form-check-input:checked{background-color:var(--ui-blue);border-color:var(--ui-blue)}.form-control[type=file]::file-selector-button{background:var(--ui-soft);color:var(--ui-text);border:0;border-right:1px solid var(--ui-line);border-radius:12px;margin:-.72rem .9rem -.72rem -.9rem;padding:.72rem .9rem}.text-muted{color:var(--ui-muted)!important}.link-dark{color:var(--ui-text)!important}.text-white{color:var(--ui-bg)!important}.bg-dark{background:var(--ui-text)!important}.bg-light,.bg-body-tertiary{background:var(--ui-soft)!important;color:var(--ui-text)!important}
.table{--bs-table-bg:var(--ui-panel);--bs-table-color:var(--ui-text);--bs-table-border-color:var(--ui-line);--bs-table-hover-color:var(--ui-text);--bs-table-hover-bg:var(--ui-soft);color:var(--ui-text)}.table thead th{background:var(--ui-panel);color:var(--ui-muted);font-size:.74rem;text-transform:uppercase;letter-spacing:.055em;font-weight:760}.table td,.table th{border-color:var(--ui-line)!important;padding:1rem}.table tbody tr:last-child td{border-bottom:0!important}.badge{font-weight:650;border-radius:999px!important}.badge.text-bg-success{background:var(--ui-green-soft)!important;color:var(--ui-success-text)!important}.badge.text-bg-secondary,.badge.text-bg-light{background:var(--ui-soft)!important;color:var(--ui-muted)!important;border:0!important}.badge.text-bg-dark{background:var(--ui-text)!important;color:var(--ui-bg)!important}.badge.text-bg-warning{background:var(--ui-red-soft)!important;color:var(--ui-red)!important}.modal-content{background:var(--ui-panel)!important;color:var(--ui-text)!important;border:1px solid var(--ui-line)!important;border-radius:24px!important;overflow:hidden}.modal-header,.modal-body,.modal-footer{background:var(--ui-panel)!important;color:var(--ui-text)!important}.modal-header,.modal-footer{border-color:var(--ui-line)!important}.btn-close{filter:none}.alert{border-radius:18px!important;border:0!important}code{color:var(--ui-blue)}
.premium-panel,.card{background:var(--ui-panel)!important;color:var(--ui-text)!important;border:1px solid var(--ui-line)!important;border-radius:var(--ui-radius)!important;box-shadow:none!important}.card-header,.card-body,.card-footer{background:var(--ui-panel)!important;color:var(--ui-text)!important;border-color:var(--ui-line)!important}.premium-side-card{background:var(--ui-panel)!important;color:var(--ui-text)!important}.premium-side-card .text-white-50{color:var(--ui-muted)!important}.premium-side-card .card-header,.premium-side-card .card-body{border-color:var(--ui-line)!important}.premium-side-card .list-group{padding:.5rem}.premium-side-card .list-group-item{background:transparent!important;color:var(--ui-text)!important;border:0!important;border-radius:16px!important;margin-bottom:.2rem}.premium-side-card .list-group-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}.premium-side-card .list-group-item.active small{color:color-mix(in srgb,var(--ui-on) 72%,transparent)!important}.navbar,.navbar-brand,.nav-link{color:var(--ui-text)!important}.nav-link.active{color:var(--ui-blue)!important}.dropdown-menu{background:var(--ui-panel)!important;color:var(--ui-text)!important;border-color:var(--ui-line)!important;border-radius:18px!important}.dropdown-item{color:var(--ui-text)!important}.dropdown-item:hover,.dropdown-item:focus{background:var(--ui-soft)!important;color:var(--ui-text)!important}.dropdown-item.active{background:var(--ui-blue)!important;color:var(--ui-on)!important}
.ios-toggle{position:relative;display:inline-flex;width:3.35rem;height:2rem;flex:0 0 auto}.ios-toggle input{position:absolute;opacity:0;pointer-events:none}.ios-toggle span{position:absolute;inset:0;cursor:pointer;border-radius:999px;background:var(--ui-line);transition:.18s}.ios-toggle span:before{content:"";position:absolute;width:1.62rem;height:1.62rem;left:.19rem;top:.19rem;border-radius:50%;background:#fff;box-shadow:0 .15rem .45rem rgba(0,0,0,.22);transition:.18s}.ios-toggle input:checked+span{background:var(--ui-blue)}.ios-toggle input:checked+span:before{transform:translateX(1.35rem)}

:focus-visible{outline:3px solid color-mix(in srgb,var(--ui-blue) 38%,transparent)!important;outline-offset:2px}.offcanvas{--bs-offcanvas-width:min(92vw,430px);background:var(--ui-panel);color:var(--ui-text);border-color:var(--ui-line)!important}.collection-item{border:1px solid transparent;border-radius:18px;padding:.75rem;transition:.15s}.collection-item:hover,.collection-item.active{background:var(--ui-soft);border-color:var(--ui-line)}.collection-item .collection-open{min-width:0}.drag-handle{cursor:grab}.drag-handle:active{cursor:grabbing}.is-dragging{opacity:.45}.drop-target{box-shadow:inset 0 0 0 2px var(--ui-blue)}.entry-editor-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,420px);gap:1rem;align-items:start}.entry-preview{position:sticky;top:5.5rem}.json-preview{min-height:280px;max-height:65vh;overflow:auto;background:var(--ui-input);border:1px solid var(--ui-line);border-radius:18px;padding:1rem;font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;white-space:pre-wrap;word-break:break-word}.autosave-state{font-size:.8rem;color:var(--ui-muted)}.dashboard-stat{padding:1.1rem;border:1px solid var(--ui-line);border-radius:20px;background:var(--ui-panel)}.dashboard-stat strong{font-size:1.65rem;letter-spacing:-.04em}.endpoint-box{display:flex;gap:.5rem;align-items:center;background:var(--ui-soft);border-radius:16px;padding:.55rem .75rem}.endpoint-box code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.empty-cta{max-width:560px;margin:auto}.role-card{border:1px solid var(--ui-line);border-radius:18px;padding:1rem;height:100%;background:var(--ui-soft)}.history-list{max-height:320px;overflow:auto}.preset-preview{border:1px solid var(--ui-line);border-radius:18px;padding:1rem;background:var(--ui-soft)}.kbd-hint kbd{background:var(--ui-soft);color:var(--ui-text);border:1px solid var(--ui-line);box-shadow:none}.mobile-action-bar{position:sticky;bottom:0;background:color-mix(in srgb,var(--ui-panel) 92%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--ui-line);padding:.75rem;margin:1rem -.75rem -.75rem;z-index:20}.cms-toast{position:fixed;right:1rem;bottom:1rem;z-index:4000}.server-search{max-width:720px}.content-section-card{border:1px solid var(--ui-line);border-radius:20px;padding:1rem;background:var(--ui-panel)}.content-section-card:hover{border-color:color-mix(in srgb,var(--ui-blue) 35%,var(--ui-line))}.favorite-star{color:#ff9f0a}.file-trash-row{opacity:.82}.text-preline{white-space:pre-line}.content-tree{display:grid;gap:.35rem}.content-tree-link{display:flex;align-items:center;gap:.55rem;padding:.55rem .65rem;border-radius:12px;color:var(--ui-text)}.content-tree-link:hover,.content-tree-link.active{background:var(--ui-soft)}.content-tree-children{display:grid;gap:.15rem;margin:.15rem 0 .5rem 1.1rem;padding-left:.7rem;border-left:1px solid var(--ui-line)}.content-tree-children .content-tree-link{font-size:.9rem;padding:.42rem .55rem}.section-drop{border:1px solid transparent}.section-drop.drop-target{border-color:var(--ui-blue);background:color-mix(in srgb,var(--ui-blue) 10%,var(--ui-panel))}.collection-sections{display:flex;flex-wrap:wrap;gap:.3rem}.form-schema-row{background:var(--ui-soft);border-color:var(--ui-line)!important;transition:opacity .15s ease,transform .15s ease,box-shadow .15s ease}.form-schema-row.is-dragging{opacity:.72;box-shadow:0 1rem 2rem color-mix(in srgb,var(--ui-text) 16%,transparent);transform:scale(.995)}.form-schema-row .form-control,.form-schema-row .form-select{background:var(--ui-panel)!important}.js-form-field-drag{touch-action:none;user-select:none}
/* Active text fix: preserve component backgrounds and leave the top navigation unchanged. */
.nav-pills .nav-link.active,.nav-pills .nav-link[aria-selected="true"],.nav-tabs .nav-link.active,.nav-tabs .nav-link[aria-selected="true"],.dropdown-item.active,.dropdown-item:active,.list-group-item.active,.page-item.active .page-link,.btn.active,.btn[aria-pressed="true"],.btn-check:checked+.btn{color:var(--ui-on)!important}
.nav-pills .nav-link.active :is(i,span,small),.nav-pills .nav-link[aria-selected="true"] :is(i,span,small),.nav-tabs .nav-link.active :is(i,span,small),.nav-tabs .nav-link[aria-selected="true"] :is(i,span,small),.dropdown-item.active :is(i,span,small),.dropdown-item:active :is(i,span,small),.list-group-item.active :is(i,span,small),.page-item.active .page-link :is(i,span,small),.btn.active :is(i,span,small),.btn[aria-pressed="true"] :is(i,span,small),.btn-check:checked+.btn :is(i,span,small){color:var(--ui-on)!important}
@media(min-width:1400px){.premium-nav{position:absolute;left:50%;transform:translateX(-50%);gap:1.35rem!important;margin-left:0}.app-frame{display:grid;grid-template-columns:300px minmax(0,1fr);gap:1rem;align-items:start}.app-sidebar{position:sticky;top:5.2rem;max-height:calc(100vh - 6.2rem);overflow:auto}.ios-shell{max-width:1840px}}
@media(min-width:1200px) and (max-width:1399.98px){.premium-nav{gap:.65rem!important;margin-left:.75rem}.premium-actions{gap:.35rem!important}.premium-actions .db-badge{display:none}.project-name{max-width:130px}.premium-brand{font-size:1.05rem}}

.app-frame.no-sidebar{display:block!important}.html-preview{min-height:12rem;padding:1rem;border:1px solid var(--ui-line);border-radius:16px;background:var(--ui-panel);overflow:auto}.html-preview img{max-width:100%;height:auto}.html-preview table{width:100%;border-collapse:collapse}.html-preview th,.html-preview td{border:1px solid var(--ui-line);padding:.5rem}.project-origin{font-size:.78rem;color:var(--ui-muted)}
@media(max-width:1199.98px){.entry-editor-grid{grid-template-columns:1fr}.entry-preview{position:static}.project-name{max-width:220px}}
@media(max-width:575.98px){.premium-topbar .brand-text{display:none}.premium-topbar .navbar-brand{margin-right:.25rem}.premium-topbar .project-name{max-width:110px}.premium-topbar .navbar-toggler{padding:.35rem .5rem}}
@media(max-width:767.98px){.premium-actions .logout-text{display:none}.premium-actions .btn{padding-inline:.7rem}.table-responsive{overflow:visible!important}.cms-responsive{display:block;width:100%}.cms-responsive thead{display:none}.cms-responsive tbody,.cms-responsive tr,.cms-responsive td{display:block;width:100%}.cms-responsive tbody{display:grid;gap:.75rem}.cms-responsive tr{border:1px solid var(--ui-line);border-radius:18px;padding:.4rem .9rem;background:var(--ui-panel)}.cms-responsive td{display:grid;grid-template-columns:minmax(105px,36%) minmax(0,1fr);gap:.75rem;align-items:center;padding:.65rem 0!important;border-bottom:1px solid var(--ui-line)!important;text-align:left!important}.cms-responsive td:last-child{border-bottom:0!important}.cms-responsive td:before{content:attr(data-label);color:var(--ui-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.045em;font-weight:750}.cms-responsive td[colspan]{display:block;text-align:center!important}.cms-responsive td[colspan]:before{display:none}.ios-actions .btn{flex:1 1 auto}.ios-head{gap:.75rem}.premium-actions .badge{display:none}}
@media(max-width:1199.98px){.premium-nav{position:static;left:auto;transform:none;gap:.75rem!important;margin:1rem 0 0;align-items:flex-start!important}.premium-actions{margin-left:0;margin-top:1rem}.ios-head{align-items:stretch;flex-direction:column}.ios-actions{justify-content:flex-start}.premium-side-card .list-group{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.45rem}.premium-side-card .list-group-item{margin-bottom:0}}
';}
function head_html($title){echo '<!doctype html><html lang="'.h(lang()).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="'.h(csrf()).'"><title>'.h($title).'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>'.ui_css().'</style></head><body class="premium-bg" data-bs-theme="'.h(theme()).'">';}
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
                if(sib&&sib.querySelector(".js-relation-check")&&sib.querySelector(".js-relation-check").checked){up?list.insertBefore(it,sib):list.insertBefore(sib,it);picker.dispatchEvent(new Event("change",{bubbles:true}));}
            });
            const search=picker.querySelector(".js-relation-search");
            if(search)search.addEventListener("input",()=>{const q=search.value.trim().toLowerCase();items().forEach(it=>it.classList.toggle("d-none",q&&!(it.dataset.search||"").includes(q)));});
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
    $adv=json_encode(['copied'=>T('copied'),'unsaved'=>T('unsaved_changes'),'autosave'=>T('autosave'),'autosaved'=>T('autosaved'),'autosave_failed'=>T('autosave_failed'),'files_not_autosaved'=>T('files_not_autosaved'),'disable_lang'=>T('disable_language_warning'),'preset'=>preset_field_sets()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    echo '<script>window.CMS_ADV='.$adv.';</script>';
    echo <<<'JS'
<script>
(()=>{
const A=window.CMS_ADV||{};
const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
const toast=(text)=>{let x=document.querySelector('.cms-toast');if(!x){x=document.createElement('div');x.className='cms-toast alert alert-success shadow';document.body.appendChild(x);}x.textContent=text;x.classList.remove('d-none');clearTimeout(x._t);x._t=setTimeout(()=>x.classList.add('d-none'),1800);};
const copy=async text=>{try{await navigator.clipboard.writeText(text);}catch(e){const t=document.createElement('textarea');t.value=text;document.body.appendChild(t);t.select();document.execCommand('copy');t.remove();}toast(A.copied||'Copied');};
document.addEventListener('click',e=>{const b=e.target.closest('.js-copy');if(b){e.preventDefault();copy(b.dataset.copy||'');}});
// One shared delete modal.
document.addEventListener('click',e=>{const b=e.target.closest('.js-delete-trigger');if(!b)return;e.preventDefault();let payload={};try{payload=JSON.parse(b.dataset.deletePayload||'{}');}catch(_){}const form=document.getElementById('universalDeleteForm'),fields=document.getElementById('universalDeleteFields');if(!form||!fields)return;document.getElementById('universalDeleteAction').value=b.dataset.deleteAction||'';document.getElementById('universalDeleteTitle').textContent=b.dataset.deleteTitle||'';document.getElementById('universalDeleteMessage').textContent=b.dataset.deleteMessage||'';const confirmLabel=document.getElementById('universalDeleteConfirmLabel'),confirmIcon=document.getElementById('universalDeleteConfirmIcon');if(confirmLabel)confirmLabel.textContent=b.dataset.deleteConfirm||A.delete_label||'Delete';if(confirmIcon)confirmIcon.innerHTML='<i class="bi bi-'+(b.dataset.deleteIcon||'trash3')+'"></i>';fields.innerHTML='';Object.entries(payload).forEach(([k,v])=>{const i=document.createElement('input');i.type='hidden';i.name=k;i.value=String(v);fields.appendChild(i);});const current=b.closest('.modal');if(current){bootstrap.Modal.getInstance(current)?.hide();setTimeout(()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('universalDeleteModal')).show(),180);}else bootstrap.Modal.getOrCreateInstance(document.getElementById('universalDeleteModal')).show();});
// Mobile tables become cards without horizontal scrolling.
document.querySelectorAll('table.cms-responsive').forEach(table=>{const heads=[...table.querySelectorAll('thead th')].map(x=>(x.textContent||'').trim());table.querySelectorAll('tbody tr').forEach(row=>[...row.cells].forEach((td,i)=>td.dataset.label=heads[i]||''));});
// Collection offcanvas search.
document.querySelectorAll('.js-collection-search').forEach(input=>input.addEventListener('input',e=>{const q=e.target.value.trim().toLowerCase();const scope=e.target.closest('.offcanvas-body,.ios-sidebar,.modal-body')||document;scope.querySelectorAll('.js-collection-item').forEach(x=>x.classList.toggle('d-none',q&&!(x.dataset.search||'').includes(q)));}));
// Drag/drop and keyboard sorting.
const initSortable=(box)=>{let dragged=null;const items=()=>[...box.querySelectorAll('[data-sort-id]')];const save=()=>{const fd=new FormData();fd.set('_csrf',csrf);fd.set('_a',box.dataset.sortAction||'');if(box.dataset.sortGid)fd.set('gid',box.dataset.sortGid);items().forEach(x=>fd.append('ids[]',x.dataset.sortId));fetch('./',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(x=>{if(x.ok)toast(x.message||'Saved');});};items().forEach(item=>{item.addEventListener('dragstart',()=>{dragged=item;item.classList.add('is-dragging');});item.addEventListener('dragend',()=>{item.classList.remove('is-dragging');items().forEach(x=>x.classList.remove('drop-target'));dragged=null;save();});item.addEventListener('dragover',e=>{e.preventDefault();if(!dragged||dragged===item)return;item.classList.add('drop-target');const r=item.getBoundingClientRect();box.insertBefore(dragged,e.clientY<r.top+r.height/2?item:item.nextSibling);});item.addEventListener('dragleave',()=>item.classList.remove('drop-target'));const handle=item.querySelector('.drag-handle');if(handle){handle.tabIndex=0;handle.addEventListener('keydown',e=>{if(!['ArrowUp','ArrowDown'].includes(e.key))return;e.preventDefault();const sib=e.key==='ArrowUp'?item.previousElementSibling:item.nextElementSibling;if(!sib)return;e.key==='ArrowUp'?box.insertBefore(item,sib):box.insertBefore(sib,item);save();handle.focus();});}});};document.querySelectorAll('[data-sort-action]').forEach(initSortable);
// Drag an existing collection onto a content section: creates only a gc link.
document.querySelectorAll('[data-collection-drag-id]').forEach(x=>x.addEventListener('dragstart',e=>{e.dataTransfer?.setData('text/cms-collection-id',x.dataset.collectionDragId||'');e.dataTransfer.effectAllowed='copy';}));document.querySelectorAll('[data-group-drop-id]').forEach(x=>{x.addEventListener('dragover',e=>{if(Array.from(e.dataTransfer?.types||[]).includes('text/cms-collection-id')){e.preventDefault();x.classList.add('drop-target');e.dataTransfer.dropEffect='copy';}});x.addEventListener('dragleave',()=>x.classList.remove('drop-target'));x.addEventListener('drop',e=>{e.preventDefault();x.classList.remove('drop-target');const cid=e.dataTransfer?.getData('text/cms-collection-id');if(!cid)return;const fd=new FormData();fd.set('_csrf',csrf);fd.set('_a','add_collection_to_group');fd.set('gid',x.dataset.groupDropId||'');fd.set('cid',cid);fd.set('_return',location.href);fetch('./',{method:'POST',body:fd,credentials:'same-origin'}).then(()=>location.reload());});});
// Dirty forms, hotkeys, autosave and live JSON preview.
let dirty=false,submitting=false;document.querySelectorAll('.js-dirty-form').forEach(f=>{f.addEventListener('input',()=>dirty=true);f.addEventListener('change',()=>dirty=true);f.addEventListener('submit',()=>{submitting=true;dirty=false;});});window.addEventListener('beforeunload',e=>{if(dirty&&!submitting){e.preventDefault();e.returnValue=A.unsaved||'';}});
const editor=document.getElementById('entryEditorForm');
const buildPreview=()=>{if(!editor)return;const out={title:editor.elements.t?.value||'',slug:editor.elements.s?.value||'',status:editor.elements.st?.value||'',lang:editor.elements._cl?.value||'',data:{}};const fd=new FormData(editor);for(const [name,val] of fd.entries()){let m=name.match(/^d\[([^\]]+)\](\[\])?$/);if(!m)continue;const key=m[1];if(m[2]){out.data[key]=out.data[key]||[];out.data[key].push(/^\d+$/.test(val)?Number(val):val);}else out.data[key]=val;}editor.querySelectorAll('input[type=checkbox][name^="d["]').forEach(i=>{const m=i.name.match(/^d\[([^\]]+)\]$/);if(m&&!i.checked)out.data[m[1]]=false;});const pre=document.getElementById('entryJsonPreview');if(pre)pre.textContent=JSON.stringify(out,null,2);};
if(editor){editor.addEventListener('input',buildPreview);editor.addEventListener('change',buildPreview);buildPreview();let timer=null,running=false,pending=false,lastSaved='',revision=0;const state=document.getElementById('autosaveState');const snapshot=()=>{const fd=new FormData(editor);fd.set('_a','autosave_entry');for(const el of editor.querySelectorAll('input[type=file],input[name^="_file["],input[name^="_remove_file["]'))fd.delete(el.name);const pairs=[];for(const [k,v] of fd.entries())if(!(v instanceof File))pairs.push([k,String(v)]);pairs.sort((a,b)=>a[0].localeCompare(b[0])||a[1].localeCompare(b[1]));return {fd,key:JSON.stringify(pairs),revision};};const autosave=async()=>{if(running){pending=true;return;}const snap=snapshot();if(snap.key===lastSaved&&!pending)return;running=true;pending=false;if(state)state.textContent=A.autosave||'Autosave…';try{const r=await fetch('./',{method:'POST',body:snap.fd,credentials:'same-origin'});const x=await r.json();if(!r.ok||!x.ok)throw new Error(x.error||'Failed');if(snap.revision===revision)lastSaved=snap.key;if(state)state.textContent=(A.autosaved||'Saved')+' · '+(x.saved_at||'')+' · '+(A.files_not_autosaved||'Files are not included');}catch(_){if(state)state.textContent=A.autosave_failed||'Failed';}finally{running=false;if(pending){pending=false;clearTimeout(timer);timer=setTimeout(autosave,250);}}};const schedule=e=>{if(e?.target?.matches?.('input[type=file],input[name^="_remove_file["]'))return;revision++;clearTimeout(timer);if(running)pending=true;timer=setTimeout(autosave,1600);};editor.addEventListener('input',schedule);editor.addEventListener('change',schedule);}
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){const f=document.querySelector('.js-entry-editor');if(f){e.preventDefault();submitting=true;dirty=false;f.requestSubmit();}}if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)){const q=document.querySelector('input[type=search]');if(q){e.preventDefault();q.focus();}}});
// Warn before disabling a language that contains data.
const i18nForm=document.querySelector('.js-i18n-settings');if(i18nForm){let confirmed=false;const checks=[...i18nForm.querySelectorAll('.js-content-lang')];checks.forEach(x=>x.dataset.original=x.checked?'1':'0');const defaultSelect=i18nForm.querySelector('#contentDefaultLang');const syncDefault=()=>{if(!defaultSelect)return;const current=defaultSelect.value;const enabled=checks.filter(x=>x.checked);defaultSelect.innerHTML='';enabled.forEach(x=>{const o=document.createElement('option');o.value=x.value;o.textContent=x.dataset.langName||x.value;defaultSelect.appendChild(o);});if([...defaultSelect.options].some(o=>o.value===current))defaultSelect.value=current;else{const hint=document.createElement('option');hint.value='';hint.textContent=i18nForm.dataset.chooseDefaultMessage||'Choose a new default language';hint.disabled=true;hint.selected=true;defaultSelect.prepend(hint);}};const syncLocks=()=>{i18nForm.querySelectorAll('[data-lang-lock-hidden]').forEach(x=>x.remove());checks.forEach(x=>{x.disabled=false;x.closest('label')?.classList.remove('opacity-75');x.removeAttribute('title');});const enabled=checks.filter(x=>x.checked);if(enabled.length===1){const x=enabled[0];x.disabled=true;x.closest('label')?.classList.add('opacity-75');x.title=i18nForm.dataset.lastLanguageMessage||'At least one content language must remain active.';const hidden=document.createElement('input');hidden.type='hidden';hidden.name='content_langs[]';hidden.value=x.value;hidden.dataset.langLockHidden='1';i18nForm.appendChild(hidden);}};checks.forEach(x=>x.addEventListener('change',()=>{syncDefault();syncLocks();}));syncDefault();syncLocks();i18nForm.addEventListener('submit',e=>{if(confirmed)return;const risky=checks.filter(x=>x.dataset.hasData==='1'&&x.dataset.original==='1'&&!x.checked);if(!risky.length)return;e.preventDefault();const list=document.getElementById('languageDisableList');if(list)list.textContent=risky.map(x=>x.dataset.langName||x.value).join(', ');bootstrap.Modal.getOrCreateInstance(document.getElementById('languageDisableModal')).show();});document.getElementById('languageDisableConfirm')?.addEventListener('click',()=>{confirmed=true;bootstrap.Modal.getInstance(document.getElementById('languageDisableModal'))?.hide();i18nForm.requestSubmit();});}
// Collection preset preview.
const presetLabels={text:'Text',textarea:'Textarea',html:'HTML',number:'Number',date:'Date',bool:'Boolean',url:'URL',image:'Image',file:'File'};document.querySelectorAll('.js-collection-preset').forEach(sel=>{const box=sel.closest('form')?.querySelector('.js-preset-preview .small');const render=()=>{const rows=(A.preset||{})[sel.value]||[];if(box)box.innerHTML=rows.length?rows.map(x=>'<div class="d-flex justify-content-between py-1"><span>'+x[0]+'</span><code>'+(presetLabels[x[2]]||x[2])+'</code></div>').join(''):'<span class="text-muted">Blank schema</span>';};sel.addEventListener('change',render);render();});
// API explorer.
const apiForm=document.getElementById('apiExplorerForm');if(apiForm){const copyBtn=document.getElementById('apiExplorerCopy');const build=()=>{const ep=document.getElementById('apiExplorerEndpoint').value;const u=new URL(location.href);u.search='';u.searchParams.set('api',ep);const project=apiForm.dataset.project||'';if(project)u.searchParams.set('project',project);const c=document.getElementById('apiExplorerCollection').value,g=document.getElementById('apiExplorerGroup').value,s=document.getElementById('apiExplorerSlug').value,l=document.getElementById('apiExplorerLang').value,p=document.getElementById('apiExplorerPopulate').value;if(['entries','entry','schema','fields'].includes(ep)&&c)u.searchParams.set('c',c);if(ep==='group'&&g)u.searchParams.set('g',g);if(ep==='entry'&&s)u.searchParams.set('s',s);if(l)u.searchParams.set('lang',l);u.searchParams.set('populate',p);if(copyBtn)copyBtn.dataset.copy=u.href;return u;};apiForm.addEventListener('input',build);apiForm.addEventListener('change',build);apiForm.addEventListener('submit',e=>{e.preventDefault();const u=build();const pre=document.getElementById('apiExplorerResponse');pre.textContent='Loading…';fetch(u,{credentials:'same-origin'}).then(async r=>({status:r.status,data:await r.json()})).then(x=>pre.textContent=JSON.stringify(x,null,2)).catch(x=>pre.textContent=String(x));});build();}
})();
</script>
JS;

    if($show)echo '<script>const m=document.getElementById('.json_encode($show).');if(m)new bootstrap.Modal(m).show()</script>';
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


/* UI PAGES */
function login_page(){head_html(T('app'));echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-sm-10 col-md-6 col-lg-4"><div class="card premium-panel border-0"><div class="card-body p-4 p-md-5"><div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('database').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('app')).'</h1><p class="text-muted mb-0">Headless data admin</p></div>';if($m=flash())echo flash_html($m);echo post_form('login',inp('u',T('login'),'','text',['required'=>true,'autocomplete'=>'username']).inp('p',T('password'),'','password',['required'=>true,'autocomplete'=>'current-password']).'<button class="btn btn-dark btn-lg w-100">'.icon('box-arrow-in-right').' '.h(T('enter')).'</button>').'</div></div></div></div></main>';foot();}
function setup_page(){
    head_html(T('setup_db'));
    $langPicker='<div class="mb-4"><label class="form-label fw-semibold">'.h(T('language')).'</label><select class="form-select rounded-pill" onchange="location.href=\'?lang=\'+encodeURIComponent(this.value)">'.implode('',array_map(fn($k,$v)=>'<option value="'.h($k).'" '.(lang()===$k?'selected':'').'>'.h($v).'</option>',array_keys(LANGS),LANGS)).'</select></div>';
    $mysql='<div class="row g-3"><div class="col-md-6">'.inp('mysql_host',T('host'),'localhost').'</div><div class="col-md-6">'.inp('mysql_database',T('database'),'cms','text',['autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('mysql_user',T('user_db'),'root','text',['autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('mysql_password',T('db_password'),'','password',['autocomplete'=>'new-password']).'</div></div>';
    $body='<input type="hidden" name="driver" id="dbDriver" value="sqlite"><div class="text-center mb-4"><div class="d-inline-flex align-items-center justify-content-center rounded-4 bg-dark text-white p-3 mb-3">'.icon('database').'</div><h1 class="h3 fw-bold premium-brand mb-1">'.h(T('setup_db')).'</h1><p class="text-muted mb-0">'.h(T('setup_db_hint')).'</p></div>';
    if($m=flash())$body.=flash_html($m);
    $body.=$langPicker.'<div class="row g-3 mb-4"><div class="col-md-6"><button type="button" class="btn btn-primary w-100 py-3" id="sqliteBtn" onclick="setDbDriver(\'sqlite\')">'.icon('filetype-sql').' '.h(T('sqlite')).'</button><div class="text-muted small mt-2">'.h(T('sqlite_hint')).'</div></div><div class="col-md-6"><button type="button" class="btn btn-outline-dark w-100 py-3" id="mysqlBtn" onclick="setDbDriver(\'mysql\')">'.icon('database-gear').' '.h(T('mysql')).'</button><div class="text-muted small mt-2">'.h(T('mysql_hint')).'</div></div></div><div id="mysqlBox" class="d-none">'.$mysql.'</div><button class="btn btn-dark btn-lg w-100 mt-4">'.icon('check-lg').' '.h(T('continue')).'</button>';
    echo '<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-lg-8"><div class="ios-surface p-4 p-lg-5">'.post_form('setup_db',$body).'</div></div></div></main><script>function setDbDriver(d){document.getElementById("dbDriver").value=d;document.getElementById("mysqlBox").classList.toggle("d-none",d!=="mysql");document.getElementById("sqliteBtn").className=d==="sqlite"?"btn btn-primary w-100 py-3":"btn btn-outline-dark w-100 py-3";document.getElementById("mysqlBtn").className=d==="mysql"?"btn btn-primary w-100 py-3":"btn btn-outline-dark w-100 py-3"}</script>';
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
    if(!can_view_entries())return '';$rows=projects();$edit=(is_admin_user()&&isset($_GET['project_edit']))?project((int)$_GET['project_edit']):null;$active=current_project_id();$body='<div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><div class="fw-semibold">'.h(T('project_switch')).'</div><div class="small text-muted">'.h(T('projects_hint')).'</div></div>'.(is_admin_user()?'<button class="btn btn-primary" data-bs-target="#projectModal" data-bs-toggle="modal">'.icon('plus-lg').' '.h(T('new_project')).'</button>':'').'</div><div class="d-grid gap-2">';
    foreach($rows as $pr){$id=(int)$pr['id'];$is=$id===$active;$switch=post_form('set_project','<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="_return" value="'.h(U(['overview'=>1])).'"><button class="btn '.($is?'btn-primary':'btn-light').' btn-icon" '.($is?'disabled':'').' aria-label="'.h(T('project_switch')).'">'.icon($is?'check-lg':'arrow-right').'</button>');$items=[];if(is_admin_user()){$items[]=dd_link(T('edit_project'),U(['settings'=>1,'project_edit'=>$id]),'pencil');if(!$is){$stats=project_file_stats($id);$items[]=dd_modal(T('delete_project'),'#deleteProjectModal'.$id,'trash3',true);}}$body.='<div class="collection-item '.($is?'active':'').' d-flex align-items-center gap-3"><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">'.h($pr['n']).($is?' <span class="badge text-bg-success">'.h(T('active')).'</span>':'').'</div><div class="small text-muted text-truncate"><code>'.h($pr['s']).'</code> · '.h($pr['d']).'</div></div>'.$switch.($items?dd_menu($items):'').'</div>';}$body.='</div>';
    return '<div class="modal fade" id="projectsModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">'.h(T('project_switch')).'</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="'.h(T('close')).'"></button></div><div class="modal-body">'.$body.'</div><div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">'.h(T('close')).'</button></div></div></div></div>'.($edit?project_modal($edit):'').(is_admin_user()&&!$edit?project_modal(null):'').(is_admin_user()?implode('',array_map(fn($pr)=>((int)$pr['id']!==current_project_id()?delete_project_modal($pr,'deleteProjectModal'.(int)$pr['id']):''),$rows)):'');
}
function project_modal($p=null){$id=(int)($p['id']??0);$body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$p['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-6">'.inp('s',T('slug'),$p['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-6">'.inp('o',T('order'),$p['o']??0,'number').'</div></div>'.area('d',T('description'),$p['d']??'',['rows'=>'3']);$footer='<span class="me-auto"></span><button type="button" class="btn btn-light" data-bs-target="#projectsModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('projectModal',$id?T('edit_project'):T('new_project'),'project',$body,$footer);}
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
    $h='<div class="d-flex gap-2 mb-3"><input type="search" class="form-control js-collection-search" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'">'.(can_schema()?'<button class="btn btn-primary btn-icon" data-bs-toggle="modal" data-bs-target="#collectionNewModal" '.(!$desktop?'data-bs-dismiss="offcanvas"':'').' aria-label="'.h(T('new_collection')).'">'.icon('plus-lg').'</button>':'').'</div>';
    $h.='<div class="small text-muted mb-2">'.h(T('section_shortcut_hint')).'</div><div class="content-tree">';
    $h.='<a class="content-tree-link '.(isset($_GET['collections'])&&$filter===''?'active':'').'" href="'.h(U(['collections'=>1])).'">'.icon('collection').'<span class="flex-grow-1">'.h(T('all_collections')).'</span><span class="badge text-bg-light">'.count($all).'</span></a>';
    $h.='<a class="content-tree-link '.($filter==='none'?'active':'').'" href="'.h(U(['collections'=>1,'section'=>'none'])).'">'.icon('inbox').'<span class="flex-grow-1">'.h(T('without_section')).'</span><span class="badge text-bg-light">'.count($ungrouped).'</span></a>';
    if($ungrouped){
        $h.='<div class="content-tree-children">';
        foreach($ungrouped as $c){
            $id=(int)$c['id'];
            $h.='<div class="d-flex align-items-center gap-1 mb-1" '.(can_schema()?'draggable="true" data-collection-drag-id="'.$id.'"':'').'><a class="content-tree-link js-collection-item flex-grow-1 min-w-0 '.($cid===$id?'active':'').'" data-search="'.h(mb_strtolower($c['n'].' '.$c['s'])).'" href="'.h(U(['c'=>$id])).'">'.icon('database').'<span class="text-truncate">'.h($c['n']).'</span></a>'.collection_manage_buttons($c,'link',false).'</div>';
        }
        $h.='</div>';
    }
    $h.='<div class="d-flex align-items-center justify-content-between mt-2 px-2"><span class="small text-uppercase fw-bold text-muted">'.h(T('content_sections')).'</span><span class="badge text-bg-light">'.count($sections).'</span></div>';
    $h.='<div class="js-sortable-groups" '.(can_schema()?'data-sort-action="reorder_groups"':'').'>';
    foreach($sections as $g){
        $gid=(int)$g['id'];$children=group_cols($gid,$pid);
        $h.='<div class="section-drop mb-1" '.(can_schema()?'draggable="true" data-sort-id="'.$gid.'" data-group-drop-id="'.$gid.'"':'').'><div class="d-flex align-items-center gap-1"><a class="content-tree-link flex-grow-1 min-w-0 '.($activeGroup===$gid?'active':'').'" href="'.h(U(['group'=>$gid])).'">'.(can_schema()?'<span class="drag-handle" tabindex="0" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</span>':'').icon('folder2').'<span class="flex-grow-1 text-truncate">'.h($g['n']).'</span><span class="badge text-bg-light">'.count($children).'</span></a>'.group_manage_buttons($g,'link',false).'</div>';
        if($children){
            $h.='<div class="content-tree-children">';
            foreach($children as $c){
                $id=(int)$c['id'];
                $h.='<div class="d-flex align-items-center gap-1 mb-1" '.(can_schema()?'draggable="true" data-collection-drag-id="'.$id.'"':'').'><a class="content-tree-link js-collection-item flex-grow-1 min-w-0 '.($cid===$id?'active':'').'" data-search="'.h(mb_strtolower($c['n'].' '.$c['s'])).'" href="'.h(U(['c'=>$id])).'">'.icon('database').'<span class="text-truncate">'.h($c['n']).'</span></a>'.collection_manage_buttons($c,'link',false).'</div>';
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
    if($f=flash()){$type=$f['type']??'info';$map=['success'=>'success','warning'=>'warning','danger'=>'danger','info'=>'info'];echo '<div class="alert alert-'.h($map[$type]??'info').' alert-dismissible fade show">'.h($f['message']??'').'<button class="btn-close" data-bs-dismiss="alert" aria-label="'.h(T('close')).'"></button></div>';}
    $editCollection=$cid?col($cid):(isset($_GET['edit_col'])?col((int)$_GET['edit_col']):null);$defaultGroup=(isset($_GET['new_col'])&&isset($_GET['group']))?(int)$_GET['group']:0;
    echo $html.'</section></div></main>'.projects_modal().($context?collections_modal($cid).collection_modal(null,'collectionNewModal',$defaultGroup):'').($editCollection?collection_modal($editCollection,'collectionEditModal'):'').clean_files_modal().universal_delete_modal().language_disable_modal();$show=$show?:old_modal();if(!$show&&isset($_GET['new_col']))$show='collectionNewModal';if(!$show&&isset($_GET['edit_col']))$show='collectionEditModal';$show=$show?:((isset($_GET['project_edit']))?'projectModal':null);foot($show);old_clear();
}
function collection_modal($c=null,$mid='collectionModal',$defaultGroup=0){
    $id=(int)($c['id']??0);$groups=groups();$typeField=$id?select_html('m_locked',T('collection_type'),['multiple'=>T('multiple'),'single'=>T('single')],collection_mode($c??[]),['disabled'=>true,'data-no-old'=>true]).'<div class="form-text mb-3">'.h(T('collection_type_locked')).'</div>':select_html('m',T('collection_type'),['multiple'=>T('multiple'),'single'=>T('single')],collection_mode($c??[]));$presetField=$id?'':select_html('preset',T('collection_preset'),collection_preset_options(),'page',['class'=>'form-select js-collection-preset']);
    $body='<input type="hidden" name="id" value="'.$id.'">';if($id)$body.='<input type="hidden" name="_sync_sections" value="1"><input type="hidden" name="_return" value="'.h(clean_url($_SERVER['REQUEST_URI']??U(['collections'=>1]))).'">';
    $body.='<div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$c['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-6">'.inp('s',T('slug'),$c['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-6">'.$typeField.'</div><div class="col-md-6">'.inp('o',T('collection_order'),$c['o']??0,'number').'</div>'.($presetField?'<div class="col-12">'.$presetField.'<div class="preset-preview js-preset-preview"><div class="fw-semibold mb-2">'.h(T('preset_preview')).'</div><div class="small text-muted"></div></div></div>':'').'</div>'.area('d',T('description'),$c['d']??'',['rows'=>'3']);
    if(!$id){$opts=[0=>T('without_section')];foreach($groups as $g)$opts[(int)$g['id']]=$g['n'];$body.=select_html('add_group_id',T('section_optional'),$opts,$defaultGroup);}
    else{$selected=old_value('section_ids',collection_group_ids($id));if(!is_array($selected))$selected=collection_group_ids($id);$selected=array_map('intval',$selected);$body.='<div class="border rounded-4 p-3 mb-3"><div class="fw-semibold mb-1">'.h(T('sections_used')).'</div><div class="small text-muted mb-3">'.h(T('remove_from_section_hint')).'</div>';if(!$groups)$body.='<div class="text-muted">'.h(T('without_section')).'</div>';foreach($groups as $g){$gid=(int)$g['id'];$checkId=$mid.'_section_'.$gid;$body.='<div class="d-flex align-items-center gap-2 mb-2"><div class="form-check flex-grow-1 mb-0"><input class="form-check-input" type="checkbox" name="section_ids[]" value="'.$gid.'" id="'.h($checkId).'" '.(in_array($gid,$selected,true)?'checked':'').'><label class="form-check-label" for="'.h($checkId).'">'.h($g['n']).'</label></div>'.group_manage_buttons($g,'link',false).'</div>';}$body.='</div>';}
    $delete=$id?universal_delete_button(T('delete_collection'),'del_col',['id'=>$id],T('delete_collection'),collection_delete_message($c),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal($mid,$id?T('collection_settings'):T('new_collection'),'col',$body,$footer);
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
    $stats=project_stats();$recent=recent_entries_get();$favIds=favorite_ids();$favs=[];foreach($favIds as $id)if($c=col($id))$favs[]=$c;
    $actions='<a class="btn btn-primary" href="'.h(U(['collections'=>1])).'">'.icon('collection').' '.h(T('collections')).'</a>';
    $h=page_head(T('overview'),h(T('active_project')).': '.h(current_project()['n']??'—'),$actions,T('dashboard'),false);
    $h.='<div class="row g-3 mb-4">';foreach(['collections'=>'collection','entries'=>'file-earmark-text','published'=>'check-circle','files'=>'folder2-open'] as $k=>$ico)$h.='<div class="col-6 col-xl-3"><div class="dashboard-stat h-100"><div class="text-muted small mb-1">'.h(T('stat_'.$k)).'</div><div class="d-flex align-items-center justify-content-between"><strong>'.(int)$stats[$k].'</strong><span class="btn btn-icon btn-light disabled">'.icon($ico).'</span></div></div></div>';$h.='</div>';
    $h.='<div class="row g-3"><div class="col-12 col-xl-7"><div class="ios-surface p-3 p-lg-4 h-100"><h2 class="h5 mb-3">'.h(T('recent_entries')).'</h2>';
    if($recent){$h.='<div class="list-group list-group-flush">';foreach($recent as $r){$cc=col((int)$r['cid']);if(!$cc)continue;$h.='<a class="list-group-item list-group-item-action bg-transparent px-0 d-flex justify-content-between align-items-center gap-3" href="'.h(U(['c'=>$cc['id'],'entry'=>$r['id']])).'"><span><span class="fw-semibold">'.h($r['title']).'</span><small class="d-block text-muted">'.h($cc['n']).'</small></span>'.icon('chevron-right').'</a>';}$h.='</div>';}else $h.='<p class="text-muted mb-0">'.h(T('no_recent')).'</p>';$h.='</div></div>';
    $h.='<div class="col-12 col-xl-5"><div class="ios-surface p-3 p-lg-4 h-100"><h2 class="h5 mb-3">'.h(T('favorite_collections')).'</h2>';if($favs){$h.='<div class="d-grid gap-2">';foreach($favs as $c)$h.='<a class="btn btn-light text-start d-flex justify-content-between" href="'.h(U(['c'=>$c['id']])).'"><span>'.icon('star-fill').' '.h($c['n']).'</span><small>'.h(T(collection_mode($c))).'</small></a>';$h.='</div>';}else $h.='<p class="text-muted mb-0">'.h(T('no_favorites')).'</p>';$h.='</div></div></div>';return $h;
}
function collectionsPage(){
    $pid=current_project_id();$qv=trim((string)($_GET['q']??''));$type=in_array((string)($_GET['type']??''),['single','multiple'],true)?(string)$_GET['type']:'';$section=(string)($_GET['section']??'');$sort=in_array((string)($_GET['sort']??''),['name','type','entries','updated'],true)?(string)$_GET['sort']:'name';$dir=isset($_GET['dir'])?request_dir():($sort==='name'?'asc':'desc');$page=max(1,(int)($_GET['page']??1));$per=min(100,max(10,(int)($_GET['per']??25)));$where=['c.pid=?'];$params=[$pid];
    if($qv!==''){$where[]='(c.n LIKE ? OR c.s LIKE ? OR c.d LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}if($type!==''){$where[]='c.m=?';$params[]=$type;}if($section==='none'){$where[]='NOT EXISTS(SELECT 1 FROM gc gx JOIN g gg ON gg.id=gx.gid WHERE gx.cid=c.id AND gg.pid=?)';$params[]=$pid;}elseif(ctype_digit($section)&&(int)$section>0){$g=group_row((int)$section);if($g){$where[]='EXISTS(SELECT 1 FROM gc gx WHERE gx.cid=c.id AND gx.gid=?)';$params[]=(int)$g['id'];}else $where[]='1=0';}
    $whereSql=implode(' AND ',$where);$total=(int)q('SELECT COUNT(*) FROM c WHERE '.$whereSql,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$map=['name'=>'c.n','type'=>'c.m','entries'=>'entry_count','updated'=>'c.ua'];$rows=all('SELECT c.*,(SELECT COUNT(*) FROM e WHERE e.cid=c.id) AS entry_count FROM c WHERE '.$whereSql.' ORDER BY '.$map[$sort].' '.strtoupper($dir).',c.id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);
    $actions=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#collectionNewModal">'.icon('plus-lg').' '.h(T('new_collection')).'</button>':'';$h=page_head(T('collections'),h(T('collection_independent_hint')),$actions,T('content_nav'),false);
    $sectionOpts=[''=>T('section_all'),'none'=>T('without_section')];foreach(groups() as $g)$sectionOpts[(int)$g['id']]=$g['n'];$typeOpts=[''=>T('type_all'),'multiple'=>T('multiple'),'single'=>T('single')];$sortOpts=['name'=>T('sort_name'),'updated'=>T('sort_updated'),'entries'=>T('sort_entries'),'type'=>T('type')];
    $dirOpts=['asc'=>T('sort_asc'),'desc'=>T('sort_desc')];$tools='<form method="get" class="ios-toolbar server-search"><input type="hidden" name="collections" value="1"><input class="form-control flex-grow-1" type="search" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'">'.select_inline('type',$typeOpts,$type).select_inline('section',$sectionOpts,$section).select_inline('sort',$sortOpts,$sort).select_inline('dir',$dirOpts,$dir).'<button class="btn btn-secondary">'.icon('funnel').' '.h(T('search')).'</button></form><div class="small text-muted mt-2">'.h(T('drag_collection_to_section')).'</div>';
    if(!$rows){$cta=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#collectionNewModal">'.icon('plus-lg').' '.h(T('new_collection')).'</button>':'';return $h.empty_state(T('no_collections'),T('collection_independent_hint'),$cta);}
    $table='<table class="table table-hover align-middle mb-0 cms-responsive"><thead><tr><th>'.h(T('name')).'</th><th>'.h(T('type')).'</th><th>'.h(T('entry_count')).'</th><th>'.h(T('content_sections')).'</th><th>'.h(T('updated')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $c){
        $cid=(int)$c['id'];$groups=collection_groups($cid);$sections=$groups?'<div class="collection-sections">'.implode('',array_map(fn($g)=>'<a class="badge text-bg-light" href="'.h(U(['group'=>(int)$g['id']])).'">'.h($g['n']).'</a>',$groups)).'</div>':'<a class="badge text-bg-light" href="'.h(U(['collections'=>1,'section'=>'none'])).'">'.h(T('without_section')).'</a>';
        $open='<a class="btn btn-primary btn-sm" href="'.h(U(['c'=>$cid])).'">'.icon('box-arrow-in-right').' '.h(T('open_entries')).'</a>';
        $items=[];
        if(can_schema()){
            $items[]=dd_link(T('fields'),U(['c'=>$cid,'fields'=>1]),'list-check');
            $items[]=dd_link(T('add_to_section'),U(['collections'=>1,'edit_col'=>$cid]),'folder-plus');
            $items[]=dd_form(T('clone_collection'),'clone_col','<input type="hidden" name="id" value="'.$cid.'">','copy');
            $items[]=dd_link(T('export_schema'),U(['export_schema'=>$cid]),'download');
        }
        $quick=collection_manage_buttons($c,'link',false);
        $table.='<tr '.(can_schema()?'draggable="true" data-collection-drag-id="'.$cid.'"':'').' ><td><a class="link-dark fw-semibold" href="'.h(U(['c'=>$cid])).'">'.h($c['n']).'</a><small class="d-block text-muted"><code>'.h($c['s']).'</code></small></td><td><span class="badge text-bg-light">'.h(T(collection_mode($c))).'</span></td><td>'.(int)$c['entry_count'].'</td><td>'.$sections.'</td><td>'.h($c['ua']??$c['ca']).'</td><td class="text-end"><div class="d-inline-flex flex-wrap justify-content-end gap-2">'.$open.$quick.($items?dd_menu($items):'').'</div></td></tr>';
    }
    $table.='</tbody></table>';$extra='';if(can_schema())$extra='<div class="ios-surface p-3 mt-3"><form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">'.token().'<input type="hidden" name="_a" value="import_col_schema"><div class="flex-grow-1"><label class="form-label">'.h(T('import_schema')).'</label><input class="form-control" type="file" name="schema" accept="application/json,.json" required></div><button class="btn btn-secondary">'.icon('upload').' '.h(T('import_schema')).'</button></form></div>';return $h.table_wrap($table,$tools,pager_html($m)).$extra;
}
function groupsPage(){
    $rows=groups();$edit=isset($_GET['gid'])?group_row((int)$_GET['gid']):null;$actions=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">'.icon('plus-lg').' '.h(T('new_group')).'</button>':'';$h=page_head(T('content_sections'),h(T('section_shortcut_hint')),$actions,T('content_nav'),false);
    if(!$rows){$cta=can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">'.icon('plus-lg').' '.h(T('create_first_section')).'</button>':'';return $h.empty_state(T('content_sections'),T('group_api_hint'),$cta).group_modal($edit);}
    $h.='<div class="row g-3 js-sortable-groups" '.(can_schema()?'data-sort-action="reorder_groups"':'').'>';
    foreach($rows as $g){
        $gid=(int)$g['id'];$count=group_collection_count($gid);$endpoint=U(['api'=>'group','g'=>$g['s'],'lang'=>default_content_lang()]);
        $quick=group_manage_buttons($g,'link',false);
        $h.='<div class="col-12 col-xl-6" '.(can_schema()?'draggable="true" data-sort-id="'.$gid.'"':'').'><div class="content-section-card h-100 d-flex flex-column section-drop" '.(can_schema()?'data-group-drop-id="'.$gid.'"':'').' ><div class="d-flex align-items-start justify-content-between gap-3"><div class="d-flex gap-2 align-items-start">'.(can_schema()?'<button type="button" class="btn btn-light btn-icon drag-handle" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button>':'').'<div><h2 class="h4 mb-1"><a class="link-dark" href="'.h(U(['group'=>$gid])).'">'.h($g['n']).'</a></h2><p class="text-muted mb-2">'.h($g['d']).'</p></div></div>'.$quick.'</div><div class="d-flex align-items-center justify-content-between mb-3"><span class="badge text-bg-light">'.h(T('collections')).': '.$count.'</span><code>'.h($g['s']).'</code></div><div class="endpoint-box mb-3"><code>'.h($endpoint).'</code>'.endpoint_copy_button($endpoint,'').'</div><div class="mt-auto"><a class="btn btn-primary w-100" href="'.h(U(['group'=>$gid])).'">'.icon('box-arrow-in-right').' '.h(T('open')).'</a></div></div></div>';
    }
    $h.='</div>';return $h.group_modal($edit);
}
function groupWorkspacePage($g){
    $gid=(int)$g['id'];$collections=group_cols($gid);$endpoint=U(['api'=>'group','g'=>$g['s'],'lang'=>default_content_lang()]);
    $actions=(can_schema()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollectionChoiceModal'.$gid.'">'.icon('plus-lg').' '.h(T('add_collection')).'</button>'.group_manage_buttons($g,'modal',true):'').endpoint_copy_button($endpoint).'<a class="btn btn-light" target="_blank" href="'.h($endpoint).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head($g['n'],'<span>'.h(T('group_api_hint')).'</span><div class="mt-1"><code>'.h($endpoint).'</code></div>',$actions,T('content_sections'));
    if(!$collections){
        $cta=can_schema()?'<div class="d-flex flex-wrap justify-content-center gap-2"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCollectionChoiceModal'.$gid.'">'.icon('plus-lg').' '.h(T('add_collection')).'</button>'.group_manage_buttons($g,'modal',true).'</div>':'';
        $h.=empty_state(T('no_collections'),T('section_shortcut_hint'),$cta);
    }else{
        $h.='<div class="ios-surface p-3 p-lg-4"><h2 class="h5 mb-3">'.h(T('section_collections')).'</h2><div class="d-grid gap-2 js-group-sort" '.(can_schema()?'data-sort-action="reorder_group_collections" data-sort-gid="'.$gid.'"':'').'>';
        foreach($collections as $c){
            $cid=(int)$c['id'];$count=(int)q('SELECT COUNT(*) FROM e WHERE cid=?',[$cid])->fetchColumn();
            $unlink='';
            if(can_schema()){
                $msg=sprintf(T('remove_from_section_question'),$c['n'],$g['n'])."\n\n".T('remove_from_section_hint');
                $unlink=universal_delete_button(T('remove_from_section'),'unlink_group_collection',['gid'=>$gid,'cid'=>$cid,'_return'=>U(['group'=>$gid])],T('remove_from_section'),$msg,true,'btn btn-outline-danger btn-icon','folder-minus',T('remove_from_section'));
            }
            $h.='<div class="collection-item d-flex flex-wrap align-items-center gap-2" '.(can_schema()?'draggable="true" data-sort-id="'.$cid.'"':'').'>'.(can_schema()?'<button class="btn btn-light btn-icon drag-handle" type="button" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button>':'').'<a class="flex-grow-1 min-w-0" href="'.h(U(['c'=>$cid])).'"><div class="fw-semibold">'.h($c['n']).'</div><div class="small text-muted"><code>'.h($c['s']).'</code> · '.h(T(collection_mode($c))).' · '.h(T('entry_count')).': '.$count.'</div></a><a class="btn btn-primary btn-sm" href="'.h(U(['c'=>$cid])).'">'.h(T('open')).'</a>'.collection_manage_buttons($c,'link',false).$unlink.'</div>';
        }
        $h.='</div></div>';
    }
    return $h.(can_schema()?add_collection_choice_modal($g).add_existing_collection_modal($g).group_modal($g):'');
}
function add_collection_choice_modal($g){$gid=(int)$g['id'];$body='<div class="d-grid gap-3"><button class="btn btn-primary btn-lg" type="button" data-bs-target="#addExistingCollectionModal'.$gid.'" data-bs-toggle="modal">'.icon('link-45deg').' '.h(T('add_existing_collection')).'</button><a class="btn btn-secondary btn-lg" href="'.h(U(['group'=>$gid,'new_col'=>1])).'">'.icon('plus-lg').' '.h(T('create_new_collection')).'</a></div><div class="small text-muted mt-3">'.h(T('section_shortcut_hint')).'</div>';return modal('addCollectionChoiceModal'.$gid,T('add_collection'),$body,'<button class="btn btn-light" data-bs-dismiss="modal">'.h(T('close')).'</button>');}
function add_existing_collection_modal($g){$gid=(int)$g['id'];$selected=group_col_ids($gid);$available=array_values(array_filter(cols(),fn($c)=>!in_array((int)$c['id'],$selected,true)));$body='<input type="hidden" name="gid" value="'.$gid.'"><input type="hidden" name="_return" value="'.h(U(['group'=>$gid])).'"><input type="search" class="form-control mb-3 js-collection-search" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"><div class="list-group">';foreach($available as $c){$cid=(int)$c['id'];$body.='<label class="list-group-item d-flex gap-3 align-items-start js-collection-item" data-search="'.h(mb_strtolower($c['n'].' '.$c['s'].' '.$c['d'])).'"><input class="form-check-input mt-1" type="checkbox" name="collections[]" value="'.$cid.'"><span><span class="fw-semibold">'.h($c['n']).'</span><small class="d-block text-muted"><code>'.h($c['s']).'</code> · '.h(T(collection_mode($c))).'</small></span></label>';}$body.='</div>';if(!$available)$body='<div class="alert alert-info mb-0">'.h(T('no_available_collections')).'</div>';$footer='<button type="button" class="btn btn-light" data-bs-target="#addCollectionChoiceModal'.$gid.'" data-bs-toggle="modal">'.h(T('back')).'</button><button class="btn btn-primary" '.(!$available?'disabled':'').'>'.icon('link-45deg').' '.h(T('add_existing_collection')).'</button>';return form_modal('addExistingCollectionModal'.$gid,T('add_existing_collection'),'add_collection_to_group',$body,$footer);}
function group_modal($g=null){
    $id=(int)($g['id']??0);$body='<input type="hidden" name="id" value="'.$id.'">'.inp('n',T('name'),$g['n']??'','text',['required'=>true,'data-slug-source'=>'s']).inp('s',T('slug'),$g['s']??'','text',['data-slug-target'=>'1']).area('d',T('description'),$g['d']??'',['rows'=>'3']).inp('o',T('order'),$g['o']??0,'number');$delete=$id?universal_delete_button(T('delete_group'),'del_group',['id'=>$id],T('delete_group'),T('delete_group_q'),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('groupModal',$id?T('edit_group'):T('new_group'),'group',$body,$footer,'modal-lg');
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
    $h.='<div class="entry-editor-grid"><div class="ios-surface p-4"><form id="apiExplorerForm" data-project="'.h((current_project()['s']??'')).'"><div class="row g-3"><div class="col-md-4"><label class="form-label">Endpoint</label><select class="form-select" id="apiExplorerEndpoint"><option value="index">index</option><option value="entries">entries</option><option value="entry">entry</option><option value="group">group</option><option value="collections">collections</option><option value="schema">schema</option><option value="files">files</option></select></div><div class="col-md-4"><label class="form-label">'.h(T('collection')).'</label><select class="form-select" id="apiExplorerCollection">'.$colOpts.'</select></div><div class="col-md-4"><label class="form-label">'.h(T('group')).'</label><select class="form-select" id="apiExplorerGroup">'.$groupOpts.'</select></div><div class="col-md-4"><label class="form-label">Slug entry</label><input class="form-control" id="apiExplorerSlug"></div><div class="col-md-4"><label class="form-label">'.h(T('language')).'</label><select class="form-select" id="apiExplorerLang">';foreach(content_langs() as $l)$h.='<option value="'.h($l).'">'.h(CONTENT_LANGS[$l]??$l).'</option>';$h.='</select></div><div class="col-md-4"><label class="form-label">Populate</label><select class="form-select" id="apiExplorerPopulate"><option value="0">0</option><option value="1">1</option></select></div></div><div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-primary" type="submit">'.icon('play-fill').' '.h(T('send_request')).'</button><button type="button" class="btn btn-light js-copy" id="apiExplorerCopy" data-copy="">'.icon('copy').' '.h(T('copy_endpoint')).'</button></div></form></div><aside class="entry-preview"><div class="ios-surface p-3"><h2 class="h5">'.h(T('response')).'</h2><pre class="json-preview" id="apiExplorerResponse">{}</pre></div></aside></div>';return $h;
}

function form_field_row_html(array $field,int $index,bool $template=false){
    $types=form_field_types();$prefix='form_fields['.$index.']';$key=h((string)($field['k']??''));$label=h((string)($field['l']??''));$type=(string)($field['t']??'text');$order=(int)($field['o']??(($index+1)*10));$opts='';foreach($types as $v=>$txt)$opts.='<option value="'.h($v).'" '.($type===$v?'selected':'').'>'.h($txt).'</option>';
    return '<div class="form-schema-row border rounded-4 p-3" data-form-field-row><input class="js-form-field-order" type="hidden" name="'.$prefix.'[o]" value="'.$order.'"><div class="row g-2 align-items-end"><div class="col-auto d-flex align-items-end"><button type="button" class="btn btn-light btn-icon drag-handle js-form-field-drag" aria-label="'.h(T('drag_to_sort')).'" title="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button></div><div class="col-12 col-lg"><label class="form-label">'.h(T('form_field_label')).'</label><input class="form-control js-form-field-label" name="'.$prefix.'[l]" value="'.$label.'" required></div><div class="col-12 col-md-6 col-lg"><label class="form-label">'.h(T('form_field_key')).'</label><input class="form-control js-form-field-key" name="'.$prefix.'[k]" value="'.$key.'" pattern="[a-z][a-z0-9_]*" required></div><div class="col-12 col-md-6 col-lg"><label class="form-label">'.h(T('form_field_type')).'</label><select class="form-select" name="'.$prefix.'[t]">'.$opts.'</select></div><div class="col-auto"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="'.$prefix.'[r]" value="1" id="formFieldReq'.$index.'" '.(!empty($field['r'])?'checked':'').'><label class="form-check-label" for="formFieldReq'.$index.'">'.h(T('required')).'</label></div></div><div class="col-auto text-end"><button type="button" class="btn btn-danger btn-icon js-remove-form-field" aria-label="'.h(T('remove_form_field')).'">'.icon('trash3').'</button></div></div></div>';
}
function form_modal_fields_data($f=null){$id=(int)($f['id']??0);$old=old_all();if(isset($old['form_fields'])&&is_array($old['form_fields'])&&(int)($old['id']??0)===$id)return array_values($old['form_fields']);$rows=$id?form_fields_all($id):[];if($rows)return $rows;if(!$id)return [['l'=>T('name'),'k'=>'name','t'=>'text','r'=>1,'o'=>10],['l'=>'Email','k'=>'email','t'=>'email','r'=>1,'o'=>20],['l'=>T('message'),'k'=>'message','t'=>'textarea','r'=>1,'o'=>30]];return [['l'=>'','k'=>'','t'=>'text','r'=>0,'o'=>10]];}
function form_modal_admin($f=null){
    $id=(int)($f['id']??0);$rows=form_modal_fields_data($f);$schema='';foreach($rows as $i=>$row)$schema.=form_field_row_html(is_array($row)?$row:[],$i);$template=form_field_row_html(['l'=>'','k'=>'','t'=>'text','r'=>0,'o'=>10],999999,true);
    $body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('n',T('name'),$f['n']??'','text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-md-6">'.inp('s',T('slug'),$f['s']??'','text',['data-slug-target'=>'1']).'</div><div class="col-md-6">'.select_html('st',T('status'),['active'=>T('active'),'inactive'=>T('inactive')],$f['st']??'active').'</div><div class="col-md-6">'.inp('o',T('order'),$f['o']??0,'number').'</div></div>'.area('d',T('description'),$f['d']??'',['rows'=>'3']).area('success_message',T('form_success_message'),$f['success_message']??T('form_default_success'),['rows'=>'2']);
    $body.='<div class="border rounded-4 p-3 mb-3 js-form-schema-builder"><div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><div class="fw-semibold">'.h(T('form_fields')).'</div><div class="small text-muted">'.h(T('form_fields_hint')).' '.h(T('drag_to_sort')).'.</div></div><button type="button" class="btn btn-secondary js-add-form-field">'.icon('plus-lg').' '.h(T('add_form_field')).'</button></div><div class="d-grid gap-2 js-form-schema-list">'.$schema.'</div><div class="form-text mt-3">'.h(T('form_schema_history_note')).'</div><template class="js-form-field-template">'.$template.'</template></div>';
    if($id)$body.='<div class="border rounded-4 p-3 mb-3"><div class="fw-semibold mb-2">'.h(T('form_public_endpoint')).'</div><div class="input-group"><input class="form-control" readonly value="'.h(form_endpoint($f)).'"><button type="button" class="btn btn-secondary js-copy" data-copy="'.h(form_endpoint($f)).'">'.icon('copy').' '.h(T('copy_endpoint')).'</button></div><div class="form-text">'.h(T('form_method_note')).'</div></div>';
    $delete=$id?universal_delete_button(T('delete'),'del_form',['id'=>$id],T('delete'),T('form_delete_q'),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('formModal',$id?T('edit_form'):T('new_form'),'form_def',$body,$footer,'modal-xl');
}
function formsPage(){
    $qv=trim((string)($_GET['q']??''));$status=in_array((string)($_GET['status']??''),['active','inactive'],true)?(string)$_GET['status']:'';$where='f.pid=?';$params=[current_project_id()];if($qv!==''){$where.=' AND (f.n LIKE ? OR f.s LIKE ? OR f.d LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}if($status!==''){$where.=' AND f.st=?';$params[]=$status;}$rows=all('SELECT f.*,(SELECT COUNT(*) FROM form_fields ff WHERE ff.fid=f.id AND ff.pid=f.pid) AS field_count,(SELECT COUNT(*) FROM form_submissions x WHERE x.fid=f.id AND x.pid=f.pid) AS submission_count,(SELECT MAX(ca) FROM form_submissions x WHERE x.fid=f.id AND x.pid=f.pid) AS last_submission FROM forms f WHERE '.$where.' ORDER BY f.o,f.n,f.id',$params);$edit=isset($_GET['form_edit'])?form_row((int)$_GET['form_edit']):null;
    $actions=can_manage_forms()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">'.icon('plus-lg').' '.h(T('new_form')).'</button>':'';$h=page_head(T('forms'),h(T('form_endpoint_hint')),$actions,'',false);
    $statusOpts=[''=>T('all_statuses'),'active'=>T('active'),'inactive'=>T('inactive')];$tools='<form class="row g-2 mb-3" method="get"><input type="hidden" name="forms" value="1"><div class="col-12 col-lg"><input class="form-control" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'"></div><div class="col-8 col-lg-3">'.select_inline('status',$statusOpts,$status).'</div><div class="col-4 col-lg-auto"><button class="btn btn-secondary w-100">'.icon('search').'</button></div>'.(($qv!==''||$status!=='')?'<div class="col-12 col-lg-auto"><a class="btn btn-light w-100" href="'.h(U(['forms'=>1])).'">'.icon('x-lg').' '.h(T('reset')).'</a></div>':'').'</form>';
    if(!$rows){$h.=empty_state(T('no_forms'),T('create_first_form'),can_manage_forms()?'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">'.icon('plus-lg').' '.h(T('new_form')).'</button>':'');return $h.form_modal_admin($edit);}
    $table='<table class="table table-hover align-middle mb-0 cms-responsive"><thead><tr><th>'.h(T('name')).'</th><th>'.h(T('status')).'</th><th>'.h(T('form_field_count')).'</th><th>'.h(T('form_submissions')).'</th><th>'.h(T('last_submission')).'</th><th>'.h(T('form_public_endpoint')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($rows as $f){$fid=(int)$f['id'];$name=can_view_form_submissions()?'<a class="fw-semibold" href="'.h(U(['form_submissions'=>$fid])).'">'.h($f['n']).'</a>':'<span class="fw-semibold">'.h($f['n']).'</span>';$items=[];if(can_manage_forms()){$items[]=dd_link(T('edit_form'),U(['forms'=>1,'form_edit'=>$fid]),'pencil');$items[]=universal_delete_button(T('delete'),'del_form',['id'=>$fid],T('delete'),T('form_delete_q'),true);}if(can_view_form_submissions())$items[]=dd_link(T('form_submissions'),U(['form_submissions'=>$fid]),'inbox');$table.='<tr><td>'.$name.'<small class="d-block text-muted">'.h($f['s']).'</small></td><td>'.form_status_badge($f['st']).'</td><td><span class="badge text-bg-light">'.(int)$f['field_count'].'</span></td><td><span class="badge text-bg-light">'.(int)$f['submission_count'].'</span></td><td>'.h($f['last_submission']?:'—').'</td><td><div class="d-flex align-items-center gap-2"><code class="text-truncate d-inline-block" style="max-width:220px">'.h(form_endpoint($f)).'</code>'.endpoint_copy_button(form_endpoint($f),'').'</div></td><td class="text-end">'.($items?dd_menu($items):'—').'</td></tr>';}
    $table.='</tbody></table>';$h.=table_wrap($table,$tools);return $h.form_modal_admin($edit);
}
function form_submission_detail_modal($s){$data=json_decode((string)$s['j'],true);if(!is_array($data))$data=[];$rows='';foreach($data as $k=>$v)$rows.='<div class="row g-2 py-2 border-bottom"><dt class="col-sm-4 text-muted">'.h($k).'</dt><dd class="col-sm-8 mb-0 text-break">'.nl2br(h(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT):$v)).'</dd></div>';$body='<dl class="mb-3">'.$rows.'</dl><div class="small text-muted d-grid gap-1"><div><strong>IP:</strong> '.h($s['ip']?:'—').'</div><div><strong>'.h(T('referrer')).':</strong> '.h($s['ref']?:'—').'</div><div><strong>'.h(T('user_agent')).':</strong> '.h($s['agent']?:'—').'</div></div>';$footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('close')).'</button>';return modal('submissionModal'.(int)$s['id'],T('form_submission').' #'.(int)$s['id'],$body,$footer,'modal-lg');}
function formSubmissionsPage($f){
    $fid=(int)$f['id'];$qv=trim((string)($_GET['q']??''));$status=in_array((string)($_GET['status']??''),['new','read','spam'],true)?(string)$_GET['status']:'';$page=max(1,(int)($_GET['page']??1));$per=25;$where='s.fid=? AND s.pid=?';$params=[$fid,current_project_id()];if($status!==''){$where.=' AND s.st=?';$params[]=$status;}if($qv!==''){$where.=' AND (s.j LIKE ? OR s.ref LIKE ? OR s.ip LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like);}$total=(int)q('SELECT COUNT(*) FROM form_submissions s WHERE '.$where,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$rows=all('SELECT s.* FROM form_submissions s WHERE '.$where.' ORDER BY s.id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);$endpoint=form_endpoint($f);$actions=endpoint_copy_button($endpoint).'<a class="btn btn-light" target="_blank" href="'.h($endpoint).'">'.icon('box-arrow-up-right').' '.h(T('open')).'</a>';$h=page_head($f['n'],h(T('form_endpoint_hint')),$actions,T('forms'));
    $opts=[''=>T('all_statuses'),'new'=>T('new_status'),'read'=>T('read_status'),'spam'=>T('spam_status')];$tools='<form class="row g-2 mb-3" method="get"><input type="hidden" name="form_submissions" value="'.$fid.'"><div class="col-12 col-lg"><input class="form-control" name="q" value="'.h($qv).'" placeholder="'.h(T('search')).'"></div><div class="col-8 col-lg-3">'.select_inline('status',$opts,$status).'</div><div class="col-4 col-lg-auto"><button class="btn btn-secondary w-100">'.icon('search').'</button></div></form>';
    if(!$rows){$h.=empty_state(T('no_form_submissions'),T('form_endpoint_hint'),endpoint_copy_button($endpoint));return $h;}
    $table='<table class="table table-hover align-middle mb-0 cms-responsive"><thead><tr><th>'.h(T('created')).'</th><th>'.h(T('payload')).'</th><th>'.h(T('status')).'</th><th>'.h(T('referrer')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';$modals='';
    foreach($rows as $s){$data=json_decode((string)$s['j'],true);$preview=[];if(is_array($data))foreach(array_slice($data,0,3,true) as $k=>$v)$preview[]=h($k).': '.h(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):mb_substr((string)$v,0,90));$statusButtons='';if(can_manage_form_submissions()){foreach(['new'=>T('mark_new'),'read'=>T('mark_read'),'spam'=>T('mark_spam')] as $st=>$label)if($s['st']!==$st)$statusButtons.=dd_form($label,'form_submission_status','<input type="hidden" name="id" value="'.(int)$s['id'].'"><input type="hidden" name="st" value="'.h($st).'">',$st==='spam'?'exclamation-triangle':'check2-circle');$statusButtons.=universal_delete_button(T('delete'),'del_form_submission',['id'=>(int)$s['id']],T('delete'),T('form_submission_delete_q'),true);}$items='<li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#submissionModal'.(int)$s['id'].'">'.icon('eye').' '.h(T('open')).'</button></li>'.$statusButtons;$table.='<tr><td>'.h($s['ca']).'</td><td><button class="btn btn-link text-start p-0" type="button" data-bs-toggle="modal" data-bs-target="#submissionModal'.(int)$s['id'].'">'.implode('<br>',$preview).'</button></td><td>'.form_status_badge($s['st']).'</td><td class="text-truncate" style="max-width:220px">'.h($s['ref']?:'—').'</td><td class="text-end">'.dd_menu([$items],T('actions')).'</td></tr>';$modals.=form_submission_detail_modal($s);}
    $table.='</tbody></table>';$h.=table_wrap($table,$tools,pager_html($m)).$modals;return $h;
}

function settingsPage(){
    $h=page_head(T('settings'),h(T('ui_settings')),'','',false);$langForm=post_form('set_lang','<input type="hidden" name="_back" value="'.h(clean_url($_SERVER['REQUEST_URI']??'./')).'">'.select_html('lang',T('language'),LANGS,lang(),['onchange'=>'this.form.submit()','data-no-old'=>true]));$themeForm=theme_toggle();$driver=strtoupper(db_driver());$cfg=db_cfg();$dbInfo=db_driver()==='mysql'?($cfg['mysql']['host']??'').' / '.($cfg['mysql']['database']??''):basename((string)($cfg['sqlite_path']??SQLITE));
    $usage=content_language_usage();$selected=content_langs();$checks='<div class="row g-2">';foreach(CONTENT_LANGS as $code=>$name){$checked=in_array($code,$selected,true);$count=(int)($usage[$code]??0);$checks.='<div class="col-12 col-md-6 col-xl-4"><label class="form-check border rounded-4 p-3 h-100 d-flex align-items-start gap-2"><input class="form-check-input js-content-lang" type="checkbox" name="content_langs[]" value="'.h($code).'" '.($checked?'checked':'').' data-lang-name="'.h($name).'" data-has-data="'.($count>0?'1':'0').'"><span><span class="fw-semibold">'.h($name).'</span><small class="d-block text-muted">'.h($code).($count?' · '.$count.' '.h(T('language_has_data')):'').'</small></span></label></div>';}$checks.='</div>';
    $defaultOpts=[];foreach($selected as $code)$defaultOpts[$code]=CONTENT_LANGS[$code]??$code;
    $i18nForm='<form method="post" class="js-i18n-settings" data-last-language-message="'.h(T('last_language_locked')).'" data-choose-default-message="'.h(T('choose_new_default_language')).'">'.token().'<input type="hidden" name="_a" value="save_i18n_settings"><input type="hidden" name="content_i18n" value="1">'.select_html('content_default_lang',T('default_content_language'),$defaultOpts,default_content_lang(),['data-no-old'=>true,'id'=>'contentDefaultLang']).'<div class="mb-3"><div class="fw-semibold mb-1">'.h(T('content_languages')).'</div><div class="text-muted small">'.h(T('content_languages_hint')).'</div></div>'.$checks.'<button class="btn btn-primary w-100 mt-4">'.icon('check-lg').' '.h(T('save')).'</button></form>';
    $h.='<div class="row g-3"><div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100"><div class="d-flex gap-3 mb-3"><span class="btn btn-light btn-icon disabled">'.icon('translate').'</span><div><h2 class="h5">'.h(T('language')).'</h2><p class="text-muted">'.h(T('language_hint')).'</p></div></div>'.$langForm.'</div></div>';
    $h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100"><div class="d-flex gap-3 mb-3"><span class="btn btn-light btn-icon disabled">'.icon(theme()==='dark'?'moon-stars':'sun').'</span><div><h2 class="h5">'.h(T('theme')).'</h2><p class="text-muted">'.h(T('theme_hint')).'</p></div></div>'.$themeForm.'</div></div>';
    if(is_admin_user())$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100"><div class="d-flex gap-3 mb-3"><span class="btn btn-light btn-icon disabled">'.icon('database').'</span><div><h2 class="h5">'.h(T('current_db')).'</h2><p class="text-muted">'.h($driver.' · '.$dbInfo).'</p></div></div><button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#resetDbModal">'.icon('arrow-counterclockwise').' '.h(T('db_reset')).'</button></div></div>';
    $pr=current_project();$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex gap-3"><span class="btn btn-light btn-icon disabled">'.icon('window-stack').'</span><div><h2 class="h5">'.h(T('projects')).'</h2><p class="text-muted">'.h($pr?$pr['n'].' · '.$pr['s']:T('projects_hint')).'</p></div></div><div class="mt-auto pt-4"><button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#projectsModal">'.icon('window-stack').' '.h(T('project_switch')).'</button></div></div></div>';
    if(can_api())$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex gap-3"><span class="btn btn-light btn-icon disabled">'.icon('braces').'</span><div><h2 class="h5">'.h(T('api_explorer')).'</h2><p class="text-muted">'.h(T('open_api')).'</p></div></div><div class="mt-auto pt-4"><a class="btn btn-primary w-100" href="'.h(U(['api_explorer'=>1])).'">'.icon('terminal').' '.h(T('api_explorer')).'</a></div></div></div>';
    if(is_admin_user())$h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex gap-3"><span class="btn btn-light btn-icon disabled">'.icon('people').'</span><div><h2 class="h5">'.h(T('users')).'</h2><p class="text-muted">'.h(T('role_capabilities')).'</p></div></div><div class="mt-auto pt-4"><a class="btn btn-primary w-100" href="'.h(U(['users'=>1])).'">'.icon('people').' '.h(T('users')).'</a></div></div></div>';
    $h.='<div class="col-12 col-lg-4"><div class="ios-surface p-4 h-100 d-flex flex-column"><div class="d-flex gap-3"><span class="btn btn-light btn-icon disabled">'.icon('tools').'</span><div><h2 class="h5">'.h(T('maintenance')).'</h2><p class="text-muted">'.h(T('maintenance_hint')).'</p></div></div><div class="mt-auto pt-4">'.post_form('cleanup_maintenance','<button class="btn btn-secondary w-100">'.icon('trash3').' '.h(T('maintenance')).'</button>').'</div></div></div>';
    $h.='<div class="col-12"><div class="ios-surface p-4"><div class="d-flex gap-3 mb-3"><span class="btn btn-light btn-icon disabled">'.icon('globe2').'</span><div><h2 class="h5">'.h(T('content_settings')).'</h2><p class="text-muted">'.h(T('content_i18n_hint2')).'</p></div></div>'.$i18nForm.'</div></div></div>';
    if(is_admin_user())$h.=form_modal('resetDbModal',T('db_reset'),'reset_db_config','<p>'.h(T('db_reset_q')).'</p><div class="alert alert-warning">'.h(T('db_reset_hint')).'</div>','<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('arrow-counterclockwise').' '.h(T('db_reset')).'</button>');return $h;
}
function usersPage(){
    $qv=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$per=25;$where='1=1';$params=[];if($qv!==''){$where='(l LIKE ? OR n LIKE ? OR role LIKE ?)';$like='%'.$qv.'%';$params=[$like,$like,$like];}$total=(int)q('SELECT COUNT(*) FROM users WHERE '.$where,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$rows=all('SELECT id,l,n,role,st,ca,ua FROM users WHERE '.$where.' ORDER BY id DESC LIMIT '.$per.' OFFSET '.$m['offset'],$params);$edit=isset($_GET['uid'])?user_row((int)$_GET['uid']):null;
    $actions='<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">'.icon('plus-lg').' '.h(T('new_user')).'</button>';$h=page_head(T('users'),h(T('role_capabilities')),$actions);
    $h.='<div class="row g-3 mb-3">';foreach(['admin','developer','editor','viewer'] as $role)$h.='<div class="col-12 col-md-6 col-xl-3"><div class="role-card"><div class="fw-bold mb-1">'.h(T($role)).'</div><div class="small text-muted">'.h(role_description($role)).'</div></div></div>';$h.='</div>';
    if(!$rows&&$qv==='')$h.=empty_state(T('users'),T('role_capabilities'),'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">'.icon('plus-lg').' '.h(T('new_user')).'</button>');
    else{$table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr><th>'.h(T('username')).'</th><th>'.h(T('display_name')).'</th><th>'.h(T('role')).'</th><th>'.h(T('status')).'</th><th>'.h(T('created')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
        foreach($rows as $u){$self=(int)$u['id']===current_user_id();$items=[dd_link(T('edit_user'),U(['users'=>1,'uid'=>$u['id']]),'pencil')];if(!$self)$items[]=universal_delete_button(T('delete_user'),'del_user',['id'=>(int)$u['id']],T('delete_user'),T('delete_user_q'),true);$table.='<tr><td><span class="fw-semibold">'.h($u['l']).'</span>'.($self?' <span class="badge text-bg-dark">me</span>':'').'</td><td>'.h($u['n']).'</td><td><span class="badge text-bg-light">'.h(T(in_array($u['role'],['admin','developer','editor','viewer'],true)?$u['role']:'viewer')).'</span></td><td><span class="badge '.($u['st']==='active'?'text-bg-success':'text-bg-secondary').'">'.h(T($u['st']==='active'?'active':'inactive')).'</span></td><td>'.h($u['ca']).'</td><td class="text-end">'.dd_menu($items).'</td></tr>';}$table.='</tbody></table>';$h.=table_wrap($table,server_search_form(),pager_html($m));}
    return $h.user_modal($edit);
}

function user_modal($u=null){
    $id=(int)($u['id']??0);$role=$u['role']??'editor';$st=$u['st']??'active';$self=$id&&$id===current_user_id();
    $body='<input type="hidden" name="id" value="'.$id.'"><div class="row g-3"><div class="col-md-6">'.inp('l',T('username'),$u['l']??'','text',['required'=>true,'autocomplete'=>'off']).'</div><div class="col-md-6">'.inp('n',T('display_name'),$u['n']??'').'</div></div>'.inp('p',$id?T('new_password'):T('password'),'','password',$id?['autocomplete'=>'new-password']:['required'=>true,'autocomplete'=>'new-password']).($id?'<div class="form-text mb-3">'.h(T('password_hint')).'</div>':'').'<div class="row g-3"><div class="col-md-6">'.select_html('role',T('role'),['admin'=>T('admin'),'developer'=>T('developer'),'editor'=>T('editor'),'viewer'=>T('viewer')],$role).'</div><div class="col-md-6">'.select_html('st',T('status'),['active'=>T('active'),'inactive'=>T('inactive')],$st,$self?['disabled'=>true]:[]).($self?'<input type="hidden" name="st" value="active">':'').'</div></div>';
    $footer='<span class="me-auto"></span><button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';
    return form_modal('userModal',$id?T('edit_user'):T('new_user'),'user',$body,$footer);
}
function delete_user_modal($u,$mid){
    $body='<input type="hidden" name="id" value="'.(int)$u['id'].'"><p>'.h(T('delete_user_q')).'</p><div class="alert alert-danger rounded-4 border-0 mb-0">'.h($u['l']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete_user'),'del_user',$body,$footer);
}
function single_rows($c){
    $cid=(int)$c['id'];$e=single_entry($c,can_entries());$endpoint=$e?U(['api'=>'entry','c'=>$c['s'],'s'=>$e['s'],'lang'=>default_content_lang()]):U(['api'=>'entries','c'=>$c['s']]);$actions=(can_schema()?collection_manage_buttons($c,'modal',true).'<a class="btn btn-secondary" href="'.h(U(['c'=>$cid,'fields'=>1])).'">'.icon('list-check').' '.h(T('fields')).'</a>':'').endpoint_copy_button($endpoint).'<a class="btn btn-light" target="_blank" href="'.h($endpoint).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head($c['n'],'<div class="d-flex flex-wrap gap-2 align-items-center"><span class="badge text-bg-light">'.h(T('single')).'</span>'.collection_sections_badges($cid).'</div>',$actions);
    if(!$e)return $h.empty_state(T('no_entries'),T('create_first_entry'),can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0])).'">'.h(T('create_first_entry')).'</a>':'');
    recent_entry_add($e);$edit=can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.icon('pencil').' '.h(T('open_editor')).'</a>':'<a class="btn btn-light" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.icon('eye').' '.h(T('open')).'</a>';$author=$e['uid']?(user_row((int)$e['uid'])?:null):null;
    $h.='<div class="ios-surface p-4"><div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3"><div><h2 class="h4 mb-2">'.h($e['t']).'</h2><div class="d-flex flex-wrap gap-2"><code>'.h($e['s']).'</code><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span><span class="text-muted small">'.h($e['ua']).'</span><span class="text-muted small">'.h(T('last_author')).': '.h($author['n']??$author['l']??'—').'</span></div></div>'.$edit.'</div></div>';
    return $h;
}
function rows($c){
    $cid=(int)$c['id'];if(collection_mode($c)==='single')return single_rows($c);
    $qv=trim((string)($_GET['q']??''));$status=in_array((string)($_GET['status']??''),['draft','published'],true)?(string)$_GET['status']:'';$page=max(1,(int)($_GET['page']??1));$per=min(100,max(10,(int)($_GET['per']??25)));$sort=request_sort(['title','slug','status','author','updated'],'updated');$dir=request_dir();$map=['title'=>'e.t','slug'=>'e.s','status'=>'e.st','author'=>'COALESCE(u.n,u.l)','updated'=>'e.ua'];$where='e.cid=?';$params=[$cid];if($status!==''){$where.=' AND e.st=?';$params[]=$status;}if($qv!==''){$where.=' AND (e.t LIKE ? OR e.s LIKE ? OR e.st LIKE ? OR u.n LIKE ? OR u.l LIKE ?)';$like='%'.$qv.'%';array_push($params,$like,$like,$like,$like,$like);}$total=(int)q('SELECT COUNT(*) FROM e LEFT JOIN users u ON u.id=e.uid WHERE '.$where,$params)->fetchColumn();$m=pagination_meta($total,$page,$per);$r=all('SELECT e.*,u.n AS author_name,u.l AS author_login FROM e LEFT JOIN users u ON u.id=e.uid WHERE '.$where.' ORDER BY '.$map[$sort].' '.strtoupper($dir).' LIMIT '.$per.' OFFSET '.$m['offset'],$params);$endpoint=U(['api'=>'entries','c'=>$c['s'],'lang'=>default_content_lang()]);
    $actions=(can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0])).'">'.icon('plus-lg').' '.h(T('new_entry')).'</a>':'').(can_schema()?collection_manage_buttons($c,'modal',true).'<a class="btn btn-secondary" href="'.h(U(['c'=>$cid,'fields'=>1])).'">'.icon('list-check').' '.h(T('fields')).'</a>':'').endpoint_copy_button($endpoint).'<a class="btn btn-light" target="_blank" href="'.h($endpoint).'">'.icon('braces').' '.h(T('api')).'</a>';
    $h=page_head($c['n'],'<div class="d-flex flex-wrap gap-2 align-items-center"><span class="badge text-bg-light">'.h(T('multiple')).'</span>'.collection_sections_badges($cid).'</div>',$actions);
    if(!$r&&$qv===''&&$status===''){return $h.empty_state(T('no_entries'),T('create_first_entry'),can_entries()?'<a class="btn btn-primary" href="'.h(U(['c'=>$cid,'entry'=>0])).'">'.icon('plus-lg').' '.h(T('create_first_entry')).'</a>':'');}
    $table='<table class="table table-hover align-middle mb-0 cms-responsive" data-server-table="1"><thead><tr><th>'.sort_link(T('title'),'title',$sort,$dir).'</th><th>'.sort_link(T('slug'),'slug',$sort,$dir).'</th><th>'.sort_link(T('status'),'status',$sort,$dir).'</th><th>'.sort_link(T('last_author'),'author',$sort,$dir).'</th><th>'.sort_link(T('updated'),'updated',$sort,$dir).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody>';
    foreach($r as $e){$open=can_entries()?'<a class="btn btn-light btn-icon" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'" aria-label="'.h(T('open_editor')).'">'.icon('pencil').'</a>':'<a class="btn btn-light btn-icon" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'" aria-label="'.h(T('open')).'">'.icon('eye').'</a>';$api=U(['api'=>'entry','c'=>$c['s'],'s'=>$e['s'],'lang'=>default_content_lang()]);$items=[dd_link(T('api'),$api,'braces','_blank')];if(can_entries())$items[]=universal_delete_button(T('delete'),'del_entry',['id'=>(int)$e['id'],'cid'=>$cid],T('delete'),T('delete_entry_q'),true);$author=$e['author_name']?:$e['author_login']?:'—';$table.='<tr><td><a class="link-dark fw-semibold" href="'.h(U(['c'=>$cid,'entry'=>$e['id']])).'">'.h($e['t']).'</a></td><td><code>'.h($e['s']).'</code></td><td><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span></td><td>'.h($author).'</td><td>'.h($e['ua']).'</td><td class="text-end"><div class="d-inline-flex gap-2">'.$open.dd_menu($items).'</div></td></tr>';}
    $table.='</tbody></table>';
    return $h.table_wrap($table,entry_filter_form($status),pager_html($m));
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
    $cid=(int)$c['id'];$edit=isset($_GET['fid'])?field((int)$_GET['fid']):null;$fs=fields($cid);
    $actions='<a class="btn btn-outline-dark" href="'.h(U(['c'=>$cid])).'">'.icon('arrow-left').' '.h(T('entries')).'</a>'.collection_manage_buttons($c,'modal',true).'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">'.icon('plus-lg').' '.h(T('new_field')).'</button>';
    $h=page_head(T('fields').': '.$c['n'],h(T('drag_to_sort')),$actions);
    if(!$fs)return $h.empty_state(T('no_fields'),T('create_first_field'),'<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fieldModal">'.icon('plus-lg').' '.h(T('create_first_field')).'</button>').field_modal($c,$edit);
    $table='<table class="table table-hover align-middle mb-0 cms-responsive"><thead><tr><th></th><th>'.h(T('label')).'</th><th>'.h(T('key')).'</th><th>'.h(T('type')).'</th><th>'.h(T('required')).'</th><th class="text-end">'.h(T('actions')).'</th></tr></thead><tbody class="js-sortable-fields" data-sort-action="reorder_fields">';
    foreach($fs as $f){$items=[dd_link(T('edit_field'),U(['c'=>$cid,'fields'=>1,'fid'=>$f['id']]),'pencil'),universal_delete_button(T('delete'),'del_field',['id'=>(int)$f['id'],'cid'=>$cid],T('delete'),T('delete_field_q'),true)];$table.='<tr draggable="true" data-sort-id="'.(int)$f['id'].'"><td><button type="button" class="btn btn-light btn-icon drag-handle" aria-label="'.h(T('drag_to_sort')).'">'.icon('grip-vertical').'</button></td><td>'.h($f['l']).'</td><td><code>'.h($f['k']).'</code></td><td><span class="badge text-bg-light">'.h($f['t']).'</span></td><td>'.h($f['r']?T('yes'):T('no')).'</td><td class="text-end">'.dd_menu($items).'</td></tr>';}
    $table.='</tbody></table>';
    return $h.table_wrap($table).field_modal($c,$edit);
}

function field_modal($c,$edit=null){
    $cid=(int)$c['id'];$isEdit=(bool)$edit;$types=['text'=>'text','textarea'=>'textarea','html'=>'html','number'=>'number','date'=>'date','bool'=>'bool','url'=>'url','image'=>'image','file'=>'file','json'=>'json','relation'=>T('relation')];$fieldPresets=[''=>T('custom'),'content'=>'Content / content / html','excerpt'=>'Excerpt / excerpt / textarea','image'=>'Image / image / image','file'=>'File / file / file','date'=>'Date / date / date','url'=>'URL / url / url','relation'=>'Relation / relation / relation'];$opt=$edit?field_options($edit):[];$target=(int)($opt['target_collection_id']??0);$mode=($opt['mode']??'single')==='multiple'?'multiple':'single';$lock=$isEdit?['disabled'=>true,'data-no-old'=>true]:[];
    $rel='<div class="cms-relation-options">'.select_html('rel_cid',T('target_collection'),relation_target_options($cid),$target,$lock).select_html('rel_mode',T('relation_mode'),['single'=>T('relation_single'),'multiple'=>T('relation_multiple')],$mode,$lock).'</div>';$preset=$edit?'<div class="alert alert-light small">'.h(T('field_schema_locked')).'</div>':'<div class="alert alert-light"><div class="fw-semibold mb-2">'.h(T('field_preset')).'</div>'.select_html('_field_preset',T('field_preset'),$fieldPresets,'',['class'=>'form-select js-field-preset']).'</div>';$keyAttrs=$isEdit?['disabled'=>true,'data-no-old'=>true]:['data-slug-target'=>'1'];$typeAttrs=array_merge(['class'=>'form-select js-field-type'],$lock);
    $body='<input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="id" value="'.(int)($edit['id']??0).'">'.$preset.inp('l',T('label'),$edit['l']??'','text',['required'=>true,'data-slug-source'=>'k']).inp('k',T('key'),$edit['k']??'','text',$keyAttrs).select_html('t',T('type'),$types,$edit['t']??'text',$typeAttrs).$rel.inp('o',T('order'),$edit['o']??0,'number').'<div class="form-check"><input class="form-check-input" type="checkbox" name="r" value="1" id="req" '.(!empty(old_value('r',$edit['r']??0))?'checked':'').'><label class="form-check-label" for="req">'.h(T('required')).'</label></div>';
    $delete=$edit?universal_delete_button(T('delete'),'del_field',['id'=>(int)$edit['id'],'cid'=>$cid],T('delete'),T('delete_field_q'),true,'btn btn-danger me-auto'):'<span class="me-auto"></span>';$footer=$delete.'<button type="button" class="btn btn-light" data-bs-dismiss="modal">'.h(T('cancel')).'</button><button class="btn btn-primary">'.icon('check-lg').' '.h(T('save')).'</button>';return form_modal('fieldModal',$edit?T('edit_field'):T('new_field'),'field',$body,$footer);
}
function delete_field_modal($c,$f,$mid='deleteFieldModal'){
    $body='<input type="hidden" name="cid" value="'.(int)$c['id'].'"><input type="hidden" name="id" value="'.(int)$f['id'].'"><p>'.h(T('delete_field_q')).'</p><div class="alert alert-warning rounded-4 border-0 mb-0">'.h($f['l']).' / '.h($f['k']).'</div>';
    $footer='<button type="button" class="btn btn-light" data-bs-target="#fieldModal" data-bs-toggle="modal">'.h(T('cancel')).'</button><button class="btn btn-danger">'.icon('trash3').' '.h(T('delete')).'</button>';
    return form_modal($mid,T('delete'),'del_field',$body,$footer);
}

function entry_field_controls($c,$e,$cl,array $values){
    $cid=(int)$c['id'];$body='';
    foreach(fields($cid) as $f){
        $k=$f['k'];$v=$values[$k]??'';$req=$f['r']?['required'=>true]:[];$label=$f['l'].($f['r']?' *':'');
        if($f['t']==='html'){
            $tid='html_'.preg_replace('/[^a-z0-9_]/i','_',$k);$textarea=area("d[$k]",$label,$v,array_merge($req,['class'=>'form-control rounded-4 bg-body-tertiary border-0 js-html-source','data-html-preview'=>'#'.$tid.'_preview']));$body.='<div class="html-editor mb-3"><ul class="nav nav-pills gap-2 mb-2" role="tablist"><li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#'.$tid.'_source">'.h(T('html_source')).'</button></li><li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#'.$tid.'_preview">'.h(T('html_preview')).'</button></li></ul><div class="tab-content"><div class="tab-pane fade show active" id="'.$tid.'_source">'.$textarea.'</div><div class="tab-pane fade" id="'.$tid.'_preview"><div class="html-preview js-html-preview" data-source="d['.h($k).']">'.sanitize_html(is_array($v)?'':(string)$v).'</div></div></div></div>';
        }
        elseif(in_array($f['t'],['textarea','json'],true))$body.=area("d[$k]",$label,$v,array_merge($req,['data-json-field'=>$f['t']==='json'?'1':'0']));
        elseif($f['t']==='bool'){$checked=(bool)old_value("d[$k]",$v);$body.='<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="d['.h($k).']" value="1" id="entry_'.h($k).'" '.($checked?'checked':'').'><label class="form-check-label" for="entry_'.h($k).'">'.h($label).'</label></div>';}
        elseif($f['t']==='relation'){
            $opt=field_options($f);$target=(int)($opt['target_collection_id']??0);$multi=($opt['mode']??'single')==='multiple';$items=relation_entries($target);$raw=old_value("d[$k]",$v);$selected=array_map('intval',$multi?(array)$raw:($raw!==''&&$raw!==null?[$raw]:[]));
            $body.='<div class="mb-3"><label class="form-label">'.h($label).'</label>';
            if(!$target)$body.='<div class="alert alert-danger">'.h(T('relation_target_required')).'</div>';
            elseif(!$items)$body.='<div class="alert alert-warning">'.h(T('no_relation_entries')).'</div>';
            elseif($multi){
                $pos=array_flip($selected);usort($items,function($a,$b)use($pos){$ai=(int)$a['id'];$bi=(int)$b['id'];$as=isset($pos[$ai]);$bs=isset($pos[$bi]);if($as!==$bs)return $as?-1:1;if($as&&$bs)return $pos[$ai]<=>$pos[$bi];return strcasecmp((string)$a['t'],(string)$b['t'])?:($ai<=>$bi);});
                $body.='<div class="relation-picker js-relation-picker" data-required="'.($f['r']?'1':'0').'"><input type="search" class="form-control mb-2 js-relation-search" placeholder="'.h(T('search')).'" aria-label="'.h(T('search')).'"><div class="list-group rounded-4 overflow-hidden">';
                foreach($items as $it){$iid=(int)$it['id'];$checked=in_array($iid,$selected,true);$badge=$it['st']==='published'?'text-bg-success':'text-bg-secondary';$body.='<div class="list-group-item d-flex align-items-center gap-2 js-relation-item" data-search="'.h(mb_strtolower($it['t'].' '.$it['s'].' '.relation_status_label($it))).'"><input class="form-check-input js-relation-check" type="checkbox" name="d['.h($k).'][]" value="'.$iid.'" '.($checked?'checked':'').'><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">'.h($it['t']).'</div><div class="small text-muted"><code>'.h($it['s']).'</code> <span class="badge '.$badge.' ms-1">'.h(relation_status_label($it)).'</span></div></div><div class="d-flex gap-1"><button type="button" class="btn btn-light btn-icon js-relation-up" aria-label="'.h(T('move_up')).'">'.icon('arrow-up').'</button><button type="button" class="btn btn-light btn-icon js-relation-down" aria-label="'.h(T('move_down')).'">'.icon('arrow-down').'</button></div></div>';}
                $body.='</div><div class="form-text">'.h(T('relation_order_hint')).'</div></div>';
            }else{
                $body.='<select class="form-select" name="d['.h($k).']" '.($f['r']?'required':'').'><option value="">'.h(T('select_entry')).'</option>';foreach($items as $it){$sel=((int)$raw===(int)$it['id'])?'selected':'';$body.='<option value="'.(int)$it['id'].'" '.$sel.'>'.h(relation_option_label($it)).'</option>';}$body.='</select>';
            }
            $body.='</div>';
        }
        elseif($f['t']==='file'||$f['t']==='image'){
            $old=is_array($v)?$v:null;$show=file_from_value($old);$body.='<div class="mb-3"><label class="form-label">'.h($label).'</label>';
            if($show&&!empty($show['url']))$body.='<div class="endpoint-box mb-2"><a class="text-truncate flex-grow-1" target="_blank" href="'.h($show['url']).'">'.icon('paperclip').' '.h($show['name']??$show['file']??T('current_file')).'</a><span class="text-muted small">'.h(fmt_size((int)($show['size']??0))).'</span></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="_remove_file['.h($k).']" value="1" id="rm_'.h($k).'"><label class="form-check-label" for="rm_'.h($k).'">'.h(T('remove_file')).'</label></div>';
            $body.='<input type="hidden" name="_file['.h($k).']" value="'.h($old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):'null').'"><input class="form-control" type="file" name="u['.h($k).']" '.($f['t']==='image'?'accept="image/*"':'').' '.($f['r']&&!$show?'required':'').'></div>';
        }else $body.=inp("d[$k]",$label,$v,$f['t']==='number'?'number':($f['t']==='date'?'date':($f['t']==='url'?'url':'text')),$req);
    }
    return $body?:'<div class="alert alert-warning">'.h(T('no_fields')).'</div>';
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
function entry_editor_page($c,$e=null){
    $cid=(int)$c['id'];$id=(int)($e['id']??0);$cl=content_lang($_GET['cl']??null);if($e)recent_entry_add($e);$endpoint=$id?U(['api'=>'entry','c'=>$c['s'],'s'=>$e['s'],'lang'=>$cl,'populate'=>1]):U(['api'=>'entries','c'=>$c['s'],'lang'=>$cl]);$actions=(can_schema()?collection_manage_buttons($c,'modal',true):'').endpoint_copy_button($endpoint).'<a class="btn btn-light" target="_blank" href="'.h($endpoint).'">'.icon('braces').' '.h(T('api')).'</a>';$pageTitle=$id?(can_entries()?T('edit_entry'):T('view_entry')):T('new_entry');$h=page_head($pageTitle,h($c['n']),$actions,T('content'));
    if(!can_entries()){
        if(!$e)return $h.empty_state(T('no_entries'),T('readonly'));
        $values=resolve_entry_data($cid,data_lang($e,$cl,true),$cl,true);$langs='';
        if(content_i18n_enabled()){$opts=[];foreach(content_langs() as $l)$opts[$l]=CONTENT_LANGS[$l]??$l;$langs='<div class="col-md-4">'.select_html('_cl_view',T('content_language'),$opts,$cl,['data-no-old'=>true,'onchange'=>'location.href='.json_encode(U(['c'=>$cid,'entry'=>$id])).'+\'&cl=\'+encodeURIComponent(this.value)']).'</div>';}
        $body='<div class="row g-3 mb-4">'.$langs.'<div class="col-md-4"><div class="form-label">'.h(T('status')).'</div><span class="badge '.($e['st']==='published'?'text-bg-success':'text-bg-secondary').'">'.h(T($e['st']==='published'?'published':'draft')).'</span></div><div class="col-md-4"><div class="form-label">'.h(T('slug')).'</div><code>'.h($e['s']).'</code></div></div><div class="d-grid gap-3">';
        foreach(fields($cid) as $f){$k=$f['k'];$body.='<section class="border rounded-4 p-3"><div class="form-label mb-2">'.h($f['l']).' <code class="small">'.h($k).'</code></div>'.readonly_value_html($values[$k]??null,$f['t']??'').'</section>';}
        $body.='</div>';$json=json_encode(outEntry($e,$cl,true),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        return $h.'<div class="entry-editor-grid"><div class="ios-surface p-3 p-lg-4"><div class="alert alert-info">'.icon('eye').' '.h(T('readonly')).'</div>'.$body.'</div><aside class="entry-preview"><div class="ios-surface p-3"><h2 class="h5">'.h(T('json_preview')).'</h2><pre class="json-preview">'.h($json).'</pre></div></aside></div>';
    }
    $values=$e?data_lang($e,$cl,false):[];$draft=entry_draft_get(current_user_id(),$cid,$id,$cl);$draftPayload=$draft['data']??[];
    if(!empty($draftPayload['d'])&&is_array($draftPayload['d']))$values=array_replace($values,$draftPayload['d']);$title=$draftPayload['t']??($e['t']??'');$slugValue=$draftPayload['s']??($e['s']??'');$status=$draftPayload['st']??($e['st']??'draft');
    $langs='';if(content_i18n_enabled()){$opts=[];foreach(content_langs() as $l)$opts[$l]=CONTENT_LANGS[$l]??$l;$langs='<div class="col-lg-4">'.select_html('_cl_select',T('content_language'),$opts,$cl,['data-no-old'=>true,'onchange'=>'location.href='.json_encode(U(['c'=>$cid,'entry'=>$id])).'+\'&cl=\'+encodeURIComponent(this.value)']).'</div>';}
    $form='<form method="post" enctype="multipart/form-data" id="entryEditorForm" class="js-dirty-form js-entry-editor" data-autosave="1" data-entry-id="'.$id.'" data-collection-id="'.$cid.'" data-content-lang="'.h($cl).'">'.token().'<input type="hidden" name="_a" value="entry"><input type="hidden" name="cid" value="'.$cid.'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="_cl" value="'.h($cl).'"><input type="hidden" name="_return" value="'.h(U(['c'=>$cid])).'">';
    if($draft)$form.='<div class="alert alert-info">'.icon('cloud-check').' '.h(T('restored_draft')).' · '.h($draft['updated_at']).'</div>';
    $form.='<div class="row g-3">'.$langs.'<div class="col-lg-'.(content_i18n_enabled()?'4':'5').'">'.inp('t',T('title'),$title,'text',['required'=>true,'data-slug-source'=>'s']).'</div><div class="col-lg-'.(content_i18n_enabled()?'4':'5').'">'.inp('s',T('slug'),$slugValue,'text',['data-slug-target'=>'1']).'</div><div class="col-lg-2">'.select_html('st',T('status'),['draft'=>T('draft'),'published'=>T('published')],$status).'</div></div><hr><h2 class="h5 mb-3">'.h(T('data')).'</h2>'.entry_field_controls($c,$e,$cl,$values).'<div class="alert alert-light small">'.icon('info-circle').' '.h(T('files_not_autosaved')).'</div><div class="mobile-action-bar d-flex flex-wrap gap-2 align-items-center"><span class="autosave-state me-auto" id="autosaveState">'.h(T('autosave')).' · '.h(T('files_not_autosaved')).'</span><a class="btn btn-light" href="'.h(U(['c'=>$cid])).'">'.h(T('cancel')).'</a><button class="btn btn-primary" type="submit">'.icon('check-lg').' '.h(T('save')).'</button></div></form>';
    $initial=['title'=>$title,'slug'=>$slugValue,'status'=>$status,'lang'=>$cl,'data'=>$values];$preview=json_encode($initial,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    $aside='<aside class="entry-preview d-grid gap-3"><div class="ios-surface p-3"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">'.h(T('json_preview')).'</h2>'.endpoint_copy_button($endpoint,'').'</div><pre class="json-preview" id="entryJsonPreview">'.h($preview).'</pre></div><div class="ios-surface p-3"><h2 class="h5">'.h(T('history')).'</h2>'.history_panel($e).'</div><div class="text-muted small kbd-hint"><kbd>Ctrl</kbd> + <kbd>S</kbd> · <kbd>/</kbd> '.h(T('search')).'</div></aside>';
    return $h.'<div class="entry-editor-grid"><div class="ios-surface p-3 p-lg-4">'.$form.'</div>'.$aside.'</div>';
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
lang();theme();
if(isset($_GET['theme'])){set_theme($_GET['theme']);$q=$_GET;unset($q['theme']);go(U($q));}
if(isset($_GET['lang'])&&!isset($_GET['api'])){set_lang($_GET['lang']);$q=$_GET;unset($q['lang']);go(U($q));}
setup_action();
if(setup_required()){if(isset($_GET['api'])||array_key_exists('form',$_GET))J(['ok'=>false,'error'=>'setup_required','message'=>T('setup_db')],503);setup_page();exit;}
boot();maintenance_maybe();if(array_key_exists('form',$_GET))public_form_endpoint();if(isset($_GET['html_sanitize'])&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'){if(!ok()){http_response_code(403);exit;}chk();header('Content-Type:text/html;charset=utf-8');echo sanitize_html((string)($_POST['html']??''));exit;}action();
if(first_user_required()){if(isset($_GET['api']))J(['ok'=>false,'error'=>'first_user_required','message'=>T('first_user')],503);first_user_page();exit;}
if(isset($_GET['api']))api();
if(isset($_GET['logout'])){$l=lang();$th=theme();session_destroy();setcookie(LANG_COOKIE,$l,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);setcookie(THEME_COOKIE,$th,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);go('./');}
if(!ok()){login_page();exit;}
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
elseif(isset($_GET['entry'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}$eid=(int)$_GET['entry'];if(!$eid&&!can_entries()){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}$e=$eid?entry($eid):null;if($eid&&!$e){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}if($e&&(int)$e['cid']!==$cid){flash(T('access_denied'),'danger');go(U(['c'=>$cid]));}layout(entry_editor_page($c,$e),$cid);}
elseif(isset($_GET['c'])){if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(rows($c),$cid);}
else {if(!can_view_entries()){flash(T('access_denied'),'danger');go('./');}layout(dashboardPage(),0);}
