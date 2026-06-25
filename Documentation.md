# Mini Headless CMS — документация для разработчиков

> Полное практическое руководство по установке, проектированию контента, работе с административной панелью и подключению frontend-приложений к Mini Headless CMS.

**Версия API:** `1.4`  
**Версия схемы приложения:** `10`  
**Версия сборки:** `2026.06.25.4`  
**Минимальная версия PHP:** `8.1`  
**Поддерживаемые базы:** SQLite и MySQL  
**Актуальность документа:** 25 июня 2026 года

---

## О документации

Документ предназначен для программистов, которые:

- впервые устанавливают CMS;
- проектируют структуру контента;
- подключают обычный сайт, SPA, Nuxt, Vue, React или серверное приложение;
- создают публичные формы;
- настраивают приватный API, CORS, пользователей и резервные копии;
- сопровождают CMS в production.

Документацию можно читать последовательно как обучение или использовать как справочник.

Связанные документы:

- [`README.md`](README.md) — обзор и быстрый старт;
- [`mini-cms-api-contract.md`](mini-cms-api-contract.md) — точный контракт API, форматы ответов и ошибки.

> [!IMPORTANT]
> Если документация и установленный PHP-файл расходятся, источником истины является код развёрнутой версии CMS. Перед обновлением проверяйте `api_version` и выполняйте smoke-тесты.

---

## Содержание

1. [Что такое Mini Headless CMS](#1-что-такое-mini-headless-cms)
2. [Архитектура и термины](#2-архитектура-и-термины)
3. [Системные требования](#3-системные-требования)
4. [Локальная установка](#4-локальная-установка)
5. [Установка на shared-хостинг](#5-установка-на-shared-хостинг)
6. [Apache и Nginx](#6-apache-и-nginx)
7. [Первоначальная настройка](#7-первоначальная-настройка)
8. [Интерфейс и рабочий процесс](#8-интерфейс-и-рабочий-процесс)
9. [Проектирование контента](#9-проектирование-контента)
10. [Проекты](#10-проекты)
11. [Разделы контента](#11-разделы-контента)
12. [Коллекции](#12-коллекции)
13. [Поля и правила](#13-поля-и-правила)
14. [Записи, автосохранение и история](#14-записи-автосохранение-и-история)
15. [Мультиязычность](#15-мультиязычность)
16. [Связи и вложенные коллекции](#16-связи-и-вложенные-коллекции)
17. [Файлы](#17-файлы)
18. [Публичные формы](#18-публичные-формы)
19. [Заявки и уведомления](#19-заявки-и-уведомления)
20. [Пользователи и роли](#20-пользователи-и-роли)
21. [Content API](#21-content-api)
22. [Frontend-интеграция](#22-frontend-интеграция)
23. [Фильтрация, сортировка и populate](#23-фильтрация-сортировка-и-populate)
24. [Приватные ресурсы и API-ключи](#24-приватные-ресурсы-и-api-ключи)
25. [CORS и кэширование](#25-cors-и-кэширование)
26. [Backup, журнал и диагностика](#26-backup-журнал-и-диагностика)
27. [Обновление и production](#27-обновление-и-production)
28. [Практические схемы](#28-практические-схемы)
29. [Устранение неполадок](#29-устранение-неполадок)
30. [Безопасность и краткий справочник](#30-безопасность-и-краткий-справочник)

---

# 1. Что такое Mini Headless CMS

Mini Headless CMS — компактная headless CMS на PHP. Она хранит и редактирует контент, а публичный сайт получает данные через JSON API.

CMS не определяет внешний вид сайта. Frontend можно написать на любой технологии:

- HTML и JavaScript;
- PHP;
- Vue или Nuxt;
- React или Next.js;
- мобильное приложение;
- другой backend.

CMS отвечает за:

- структуру контента;
- записи и публикацию;
- мультиязычность;
- файлы;
- формы и заявки;
- пользователей и права;
- публичный и приватный API;
- резервные копии;
- диагностику и журнал действий.

Главная особенность — single-file архитектура. Основное приложение находится в одном PHP-файле и не требует Composer, Node.js или отдельной сборки.

## Когда CMS подходит

- корпоративные сайты;
- лендинги;
- сайты услуг;
- небольшие блоги;
- каталоги без сложной складской логики;
- FAQ и портфолио;
- мультиязычные сайты;
- контактные формы;
- небольшие внутренние справочники.

## Когда лучше выбрать другое решение

- крупный интернет-магазин с оплатой и складом;
- CRM или ERP;
- высоконагруженная социальная сеть;
- неограниченная вложенность;
- публичный CRUD API;
- сложный workflow согласования;
- полнотекстовый поиск уровня Elasticsearch.

---

# 2. Архитектура и термины

После установки структура выглядит примерно так:

```text
cms/
├── index.php
├── storage/
│   ├── config.json
│   ├── cms.sqlite
│   ├── login_attempts.json
│   ├── cache/
│   ├── .htaccess
│   └── index.html
└── uploads/
```

При MySQL файл `cms.sqlite` не используется.

## Проект

Независимое рабочее пространство внутри одной установки:

```text
corporate-site
client-a
client-b
```

У проекта свои коллекции, формы, файлы, CORS, API-ключи, Telegram-настройки и права.

## Раздел контента

Логическое объединение нескольких глобальных коллекций.

```text
homepage
├── hero
├── advantages
├── services
└── contacts
```

Раздел позволяет получить несколько коллекций одним API-запросом.

## Коллекция

Схема и хранилище однотипного контента:

```text
services
articles
products
contacts
```

## Поле

Описание одного значения записи:

```text
description
price
image
published_date
category
```

## Запись

Конкретный элемент коллекции.

## Single-коллекция

Содержит одну запись. Подходит для hero, контактов, настроек сайта, страницы «О компании».

## Multiple-коллекция

Содержит несколько записей. Подходит для услуг, статей, товаров, отзывов и FAQ.

## Глобальная коллекция

Обычная коллекция проекта. Может входить в несколько разделов.

## Вложенная коллекция

Коллекция, принадлежащая родительской коллекции. Её записи существуют отдельно для каждой родительской записи.

## Slug

Технический идентификатор в URL и API:

```text
services
industrial-automation
homepage
```

## Key поля

Техническое имя внутри `data`.

```text
Label: Цена
Key: price
```

В API:

```json
{
  "data": {
    "price": 15000
  }
}
```

---

# 3. Системные требования

## Обязательно

- PHP `8.1+`;
- PDO;
- JSON;
- OpenSSL;
- Fileinfo;
- `pdo_sqlite` или `pdo_mysql`;
- права записи в `storage` и `uploads`.

## Рекомендуется

- Mbstring;
- HTTPS;
- ZipArchive;
- cURL;
- настроенная `mail()`.

CMS содержит ограниченные UTF-8 fallback-функции, но для production лучше установить `mbstring`.

## Для резервных копий

```text
ZipArchive
```

## Для Telegram и webhook

Желателен cURL. При его отсутствии может использоваться `file_get_contents()`, если сервер разрешает внешние HTTP-запросы.

## Лимиты загрузки

Максимальный размер файла в CMS по умолчанию — 10 МБ.

```ini
upload_max_filesize = 10M
post_max_size = 12M
```

`post_max_size` должен быть больше `upload_max_filesize`.

---

# 4. Локальная установка

## Встроенный сервер PHP

Поместите PHP-файл в отдельный каталог и переименуйте в `index.php`.

```bash
mkdir mini-cms
cd mini-cms
php -S localhost:8000
```

Откройте:

```text
http://localhost:8000/
```

## OpenServer, XAMPP или Laragon

```text
htdocs/mini-cms/index.php
```

Откройте:

```text
http://localhost/mini-cms/
```

## Проверка расширений

```bash
php -m
```

Ищите:

```text
PDO
pdo_sqlite или pdo_mysql
json
openssl
fileinfo
mbstring
zip
curl
```

## Права Linux/macOS

```bash
mkdir -p storage uploads
chmod -R 775 storage uploads
```

Не используйте `777` без необходимости.

---

# 5. Установка на shared-хостинг

1. Создайте каталог, например `public_html/cms/`.
2. Загрузите PHP-файл как `index.php`.
3. Выберите PHP 8.1 или новее.
4. Включите необходимые расширения.
5. Откройте `https://example.com/cms/`.

CMS создаст каталоги автоматически, если PHP имеет право записи.

Если это не произошло, создайте вручную:

```text
storage
storage/cache
uploads
```

Обычно подходят права `775`. На некоторых хостингах важнее владелец файлов, чем числовое значение прав.

---

# 6. Apache и Nginx

## Apache

CMS создаёт защитный `.htaccess` в `storage`.

Проверьте, что Apache разрешает:

```apache
AllowOverride All
```

URL:

```text
https://example.com/cms/storage/config.json
```

должен вернуть `403` или `404`.

## Nginx

Nginx не читает `.htaccess`. Добавьте:

```nginx
location ^~ /cms/storage/ {
    deny all;
    return 404;
}
```

Дополнительно:

```nginx
location ~ /\.(?!well-known) {
    deny all;
}
```

Не храните рядом с публичным `index.php` файлы:

```text
index-old.php
cms-backup.php
index-copy.php
```

Они могут быть доступны из браузера.

---

# 7. Первоначальная настройка

## Интерфейс

Выберите язык панели и тему.

Интерфейс поддерживает:

```text
Русский
Қазақша
English
```

Язык интерфейса и язык контента — разные настройки.

## Контент

### Один язык

Подходит для одноязычного сайта. Редактор проще, а API не содержит языковых структур.

### Несколько языков

Используйте, если frontend запрашивает:

```text
lang=ru
lang=kk
lang=en
```

## База данных

### SQLite

Подходит для небольших сайтов и быстрого старта.

```text
storage/cms.sqlite
```

### MySQL

База создаётся заранее. CMS создаёт таблицы и индексы самостоятельно.

## Первый пользователь

Создайте Administrator. Пароль должен содержать от 10 до 72 символов.

После установки проверьте:

1. диагностику;
2. защиту `storage`;
3. HTTPS;
4. загрузку файла;
5. создание backup;
6. API index.

---

# 8. Интерфейс и рабочий процесс

## Главная

Сводка, недавние записи, избранные коллекции и быстрые переходы.

## Контент

Работа с разделами, коллекциями, записями и вложенными коллекциями.

## Формы

Определения форм, поля, endpoint, заявки, экспорт и уведомления.

## Файлы

Активные файлы, использование, корзина и очистка.

## Настройки

- интерфейс;
- проект и языки;
- пользователи и API;
- интеграции;
- данные и безопасность;
- система.

## Рекомендуемый цикл разработки

1. спроектировать сущности;
2. создать проект;
3. создать коллекции и поля;
4. добавить тестовые записи;
5. проверить Draft/Published;
6. проверить API Explorer;
7. подключить frontend;
8. настроить формы;
9. создать backup;
10. выполнить production checklist.

---

# 9. Проектирование контента

Не переносите дизайн страницы в одно HTML-поле.

Плохая схема:

```text
homepage
└── content HTML со всей страницей
```

Хорошая схема:

```text
homepage
├── hero         Single
├── advantages   Multiple
├── services     Multiple
└── contacts     Single
```

## Вопросы перед созданием сущности

1. Это один объект или список?
2. Данные используются в одном месте или в нескольких?
3. Нужны фильтрация и сортировка?
4. Нужен отдельный URL?
5. Нужны связи?
6. Нужны переводы?
7. Должен ли frontend получать сущность отдельно?

## Выбор Single/Multiple

Single:

```text
главный экран
контакты
настройки сайта
```

Multiple:

```text
услуги
статьи
товары
отзывы
```

## Раздел не является обязательной папкой

Коллекция может существовать без раздела или входить в несколько разделов. Раздел нужен для удобного агрегирующего API-запроса.

---

# 10. Проекты

Проекты создаёт Administrator.

Укажите:

- название;
- slug;
- описание.

Пример:

```text
Название: Business Engineering KZ
Slug: asu-bekz
```

API:

```text
?project=asu-bekz
```

## Когда нужен отдельный проект

- другой клиент;
- другой сайт;
- другой набор пользователей;
- отдельный CORS;
- независимый backup;
- данные не должны пересекаться.

Не создавайте проект для каждой страницы одного сайта.

## Удаление

Перед удалением:

1. скачайте backup;
2. проверьте формы и заявки;
3. проверьте файлы;
4. переключитесь на другой проект.

Последний проект удалить нельзя.

---

# 11. Разделы контента

Раздел объединяет глобальные коллекции.

Укажите:

- название;
- slug;
- описание;
- доступ;
- коллекции.

Одна коллекция может входить в несколько разделов.

Удаление связи с разделом не удаляет коллекцию. Полное удаление коллекции — отдельная операция.

## Порядок

Порядок коллекций влияет на `group.collections`.

## API раздела

```http
GET /?api=group&project=site&g=homepage&lang=ru&populate=all
```

```js
const hero = payload.group.collections.find(
  item => item.slug === "hero",
);
```

С параметром:

```text
by_slug=1
```

ответ дополнительно содержит индекс коллекций по slug.


---

# 12. Коллекции

При создании укажите:

- название;
- slug;
- описание;
- тип `Single` или `Multiple`;
- пресет;
- раздел, если нужен;
- режим доступа.

## Тип фиксируется

После создания тип коллекции менять нельзя. Это защищает структуру данных и frontend-контракт.

Если тип выбран неверно:

1. создайте новую коллекцию;
2. перенесите данные;
3. переключите frontend;
4. удалите старую после проверки.

## Single

Возвращает один объект или `null`.

Подходит для:

- hero;
- контактов;
- настроек;
- одной страницы.

## Multiple

Возвращает массив и поддерживает пагинацию.

## Slug

Хорошо:

```text
services
product-categories
company-contacts
```

Плохо:

```text
data1
new-collection
test2
```

Slug — часть API-контракта. Не меняйте его без необходимости после интеграции.

## Public и Private

`Public` доступен без ключа и отдаёт только опубликованные записи.

`Private` требует ключ, привязанный к этой коллекции.

## Пресеты

Пресет создаёт стартовые поля. После создания это обычные поля.

### Blank

Пустая схема.

### Page

| Label | Key | Type | Required |
|---|---|---|---|
| Content | `content` | HTML | Да |
| Meta description | `meta_description` | Textarea | Нет |

### Blog

| Label | Key | Type | Required |
|---|---|---|---|
| Cover image | `cover_image` | Image | Нет |
| Excerpt | `excerpt` | Textarea | Нет |
| Content | `content` | HTML | Да |
| Published date | `published_date` | Date | Нет |

### Product

| Label | Key | Type | Required |
|---|---|---|---|
| Image | `image` | Image | Нет |
| Price | `price` | Number | Нет |
| Description | `description` | HTML | Да |
| In stock | `in_stock` | Boolean | Нет |

### FAQ

| Label | Key | Type | Required |
|---|---|---|---|
| Question | `question` | Text | Да |
| Answer | `answer` | Textarea | Да |

### Contacts

| Label | Key | Type | Required |
|---|---|---|---|
| Phone | `phone` | Text | Нет |
| Email | `email` | Text | Нет |
| Address | `address` | Textarea | Нет |
| Map URL | `map_url` | URL | Нет |

---

# 13. Поля и правила

Для каждого поля задайте:

- понятный Label;
- стабильный key;
- правильный тип;
- обязательность;
- правила;
- default при необходимости.

## Типы полей

### Text

Короткая переводимая строка.

### Текст без перевода (`text_global`)

Общая строка для всех языков. Подходит для SKU, кода, номера и неизменяемого бренда.

### Textarea

Многострочный переводимый текст без HTML.

### HTML

Форматированный общий HTML-контент.

Не используйте HTML вместо структурированных полей, если данные нужно фильтровать или переиспользовать.

### Email и Tel

Значения с серверной проверкой.

### Number и Integer

Возвращаются как JSON number и integer.

### Date и Datetime

Дата:

```text
YYYY-MM-DD
```

Datetime принимает дату и время.

### Boolean

```json
true
```

или:

```json
false
```

### URL

Абсолютный `http` или `https` URL.

### Image и File

Объект файла из файлового менеджера.

### JSON

Произвольное JSON-совместимое значение. Используйте только когда отдельные поля действительно неудобны.

### Списки

Маркированные и нумерованные списки возвращают массив строк.

```json
[
  "Первый пункт",
  "Второй пункт"
]
```

Есть общие и переводимые варианты.

### Relation

Связь с глобальной коллекцией.

### Nested relation

Связь с вложенной коллекцией внутри текущей родительской записи.

## Правила полей

### Required

Обязательное значение.

### Min length и Max length

Ограничение длины строки.

### Min и Max

Числовые границы.

### Regex

Пример:

```regex
~^[A-Z]{2}-\d{4}$~
```

### Choices

По одному разрешённому значению в строке:

```text
basic
standard
premium
```

### Default

Значение по умолчанию.

### Unique

Уникальность среди записей коллекции. Полезно для SKU и внешнего ID.

## Key поля

После создания key, type и relation-параметры блокируются.

Рекомендуемый стиль:

```text
snake_case
```

```text
short_description
published_date
cover_image
is_featured
```

Label можно менять без изменения API-ключа поля.

---

# 14. Записи, автосохранение и история

Запись имеет:

- служебное название;
- slug;
- статус;
- пользовательские поля;
- даты создания и изменения.

## Служебное название

Используется в панели и как `title` API.

Для публичного заголовка можно создать отдельное поле:

```text
heading
```

Пример:

```text
Служебное название: Hero главной
heading: Автоматизируем бизнес-процессы
```

## Статусы

### Draft

Не возвращается публичным API.

### Published

Доступен публичному API.

Рекомендуется сначала создавать Draft, затем публиковать после проверки.

## Slug записи

Используется:

```text
?api=entry&c=services&s=industrial-automation
```

Slug должен быть стабильным.

## Автосохранение

Редактор сохраняет незавершённые значения отдельно для текущего пользователя.

Автосохранение не заменяет основную кнопку «Сохранить».

### Файлы

Файлы не входят в автосохранение и загружаются только при основном сохранении.

## Очистка формы

Очистка:

- удаляет введённые значения;
- удаляет автосохранённый черновик;
- не меняет сохранённую запись до основной команды сохранения.

## Срок хранения

Автосохранения старше 30 дней удаляются обслуживанием.

## История версий

Перед изменением существующей записи CMS сохраняет старое состояние:

- title;
- slug;
- status;
- data;
- автора;
- дату;
- сводку изменений.

## Восстановление версии

Текущее состояние сначала попадает в историю, затем выбранная версия становится активной.

---

# 15. Мультиязычность

Язык интерфейса и языки контента независимы.

## Переводимые типы

```text
text
textarea
ul_list_i18n
ol_list_i18n
```

## Общие типы

```text
text_global
html
email
tel
number
integer
date
datetime
bool
url
image
file
json
relation
nested_relation
```

> [!IMPORTANT]
> HTML в текущей модели является общим полем. Для полностью раздельного мультиязычного контента используйте переводимые структурированные поля.

## Редактор

Каждый язык отображается отдельным блоком.

Первый язык может автозаполнить пустые переводы. После этого текст следует проверить и подтвердить.

## Добавление языка

1. включите язык;
2. заполните существующие записи;
3. проверьте формы;
4. проверьте `lang=<code>`;
5. проверьте SEO;
6. только затем публикуйте переключатель языка.

## API

```text
lang=ru
lang=kk
lang=en
lang=default
lang=all
```

Пример:

```http
GET /?api=entries&project=site&c=services&lang=kk
```

## Fallback

При отсутствии перевода API может использовать основной язык.

Поле `translated` помогает отличать подтверждённый перевод от fallback.

## `lang=all`

Подходит для:

- статической генерации;
- синхронизации;
- экспорта;
- серверного кэша.

Не запрашивайте его на каждой клиентской странице без необходимости.

---

# 16. Связи и вложенные коллекции

## Обычная relation

Пример:

```text
categories
products
```

Поле `products.category`:

```text
Type: Relation
Target: categories
Mode: Single
```

Без populate:

```json
{
  "data": {
    "category": 4
  }
}
```

С `populate=category`:

```json
{
  "data": {
    "category": {
      "id": 4,
      "title": "Automation",
      "slug": "automation",
      "data": {}
    }
  }
}
```

## Multiple relation

```json
{
  "data": {
    "related_services": [4, 7, 9]
  }
}
```

## Автоматически всегда показывать все

Для multiple relation можно использовать все текущие и будущие записи связанной коллекции.

Включайте, когда нужен полный справочник. Отключайте для ручного выбора и порядка.

## Ограничения relation

- populate работает на один уровень;
- target выбирается при создании поля;
- удалённая или неопубликованная запись может отсутствовать в раскрытом ответе.

## Вложенная коллекция

Пример тарифов внутри услуг:

```text
services
├── Industrial automation
│   └── tariffs
│       ├── Basic
│       └── Advanced
└── Electrical supply
    └── tariffs
        └── Installation
```

## Создание

1. Откройте родительскую коллекцию.
2. Создайте вложенную.
3. Настройте поля.
4. Сохраните родительскую запись.
5. Создайте вложенные записи внутри неё.
6. Добавьте `nested_relation` в родителя.

## Глубина

Поддерживается один уровень:

```text
parent
└── nested
```

## API

Вложенная коллекция не имеет отдельного публичного endpoint.

```http
GET /?api=entries&project=site&c=services&populate=tariffs
```

Nested relation ограничена текущей родительской записью.

При удалении родителя его вложенные записи удаляются.

---

# 17. Файлы

## Разрешённые расширения

```text
jpg jpeg png webp gif
pdf doc docx xls xlsx ppt pptx
txt csv zip rar
```

## Максимум

```text
10 МБ
```

CMS проверяет:

- PHP upload error;
- размер;
- расширение;
- MIME;
- права;
- перемещение файла.

## Объект API

```json
{
  "id": 7,
  "file_id": 7,
  "name": "service-image.webp",
  "file": "a1b2c3-service-image.webp",
  "url": "uploads/a1b2c3-service-image.webp",
  "size": 184320,
  "mime": "image/webp",
  "ext": "webp"
}
```

URL может быть относительным:

```js
const imageUrl = new URL(
  file.url,
  "https://cms.example.com/",
).href;
```

## Использование

CMS различает используемые и неиспользуемые файлы по ссылкам в записях.

Перед очисткой убедитесь, что файл не подключён вручную вне CMS.

## Корзина

Обычное удаление перемещает файл в корзину. Оттуда его можно восстановить или удалить окончательно.

Administrator также управляет глобальной корзиной и orphan-файлами.

---

# 18. Публичные формы

Форма — отдельный endpoint для заявок.

## Настройки

- название;
- slug;
- описание;
- статус;
- success message;
- поля;
- retention;
- доступ;
- email;
- webhook.

## Статусы

`active` — GET и POST доступны.

`inactive` — endpoint отвечает `410 Gone`.

## Типы полей

```text
text
textarea
email
tel
number
integer
boolean
date
datetime
url
json
```

Файлы не поддерживаются.

## Получение схемы

```http
GET https://cms.example.com/?form=contact&project=site&lang=ru
```

Ответ содержит метаданные, поля, правила, endpoint и поддерживаемые форматы.

## Отправка JSON

```js
const response = await fetch(
  "https://cms.example.com/?form=contact&project=site&lang=ru",
  {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      name: "Темирхан",
      phone: "+7 700 000 00 00",
      message: "Нужна консультация",
      _hp: "",
    }),
  },
);

const payload = await response.json();

if (!response.ok || !payload.ok) {
  const error = new Error(
    payload.message ?? `HTTP ${response.status}`,
  );

  error.code = payload.error;
  error.details = payload.details;

  throw error;
}
```

## HTML

```html
<form
  method="post"
  action="https://cms.example.com/?form=contact&project=site&lang=ru"
>
  <input name="name" required>
  <input name="phone" type="tel" required>
  <textarea name="message"></textarea>
  <input name="_hp" tabindex="-1" autocomplete="off" hidden>
  <button type="submit">Отправить</button>
</form>
```

Обычная HTML-форма получит JSON-ответ. Для хорошего UX перехватывайте submit через JavaScript.

## Неизвестные поля

Payload с key, отсутствующим в схеме, отклоняется.

## Honeypot

```text
_hp
_website
```

Они должны быть пустыми. Заполненная honeypot-заявка может получить успешный ответ, но будет помечена как spam.

## Rate limit

```text
20 отправок / 10 минут / IP / форма
```

При превышении:

```text
429
Retry-After: 600
```

## Размер payload

```text
65535 bytes
```


---

# 19. Заявки и уведомления

## Статусы заявок

```text
new
read
spam
```

Editor и Administrator могут:

- просматривать заявки;
- отмечать прочитанными;
- отмечать как spam;
- удалять;
- выполнять массовые операции;
- экспортировать CSV и JSON.

Developer управляет схемой формы, но не используется как оператор заявок.

## Фильтры

- статус;
- период;
- поиск;
- IP;
- Referer;
- данные payload.

## Экспорт CSV

CSV содержит UTF-8 BOM и использует `;` как разделитель.

## Экспорт JSON

Содержит:

- form;
- exported_at;
- status;
- data;
- IP;
- User-Agent;
- Referer;
- created_at.

## Retention

Варианты:

```text
не удалять
30 дней
90 дней
180 дней
365 дней
```

Удаляются только старые `read` и `spam`. Новые заявки автоматически не удаляются.

## Email

Если в форме указан email, CMS вызывает `mail()`.

Перед production:

1. отправьте тест;
2. проверьте spam;
3. настройте SPF/DKIM/DMARC;
4. не используйте email как единственный канал.

Ошибка email не отменяет сохранение заявки.

## Webhook

CMS отправляет:

```json
{
  "event": "form.submitted",
  "submission_id": 158,
  "status": "new",
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "form": {
    "id": 4,
    "name": "Contact form",
    "slug": "contact"
  },
  "data": {},
  "received_at": "2026-06-25 12:00:00"
}
```

Заголовки:

```http
X-CMS-Event: form.submitted
X-CMS-Signature: sha256=<hmac>
Content-Type: application/json
```

### Проверка HMAC в PHP

```php
<?php

$raw = file_get_contents('php://input');
$received = $_SERVER['HTTP_X_CMS_SIGNATURE'] ?? '';
$secret = $_ENV['CMS_WEBHOOK_SECRET'] ?? '';

$expected = 'sha256=' . hash_hmac('sha256', $raw, $secret);

if (!hash_equals($expected, $received)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);

http_response_code(204);
```

### Node.js

```js
import crypto from "node:crypto";
import express from "express";

const app = express();

app.post(
  "/cms-webhook",
  express.raw({ type: "application/json" }),
  (req, res) => {
    const signature = req.get("X-CMS-Signature") ?? "";

    const expected =
      "sha256=" +
      crypto
        .createHmac("sha256", process.env.CMS_WEBHOOK_SECRET)
        .update(req.body)
        .digest("hex");

    const valid =
      signature.length === expected.length &&
      crypto.timingSafeEqual(
        Buffer.from(signature),
        Buffer.from(expected),
      );

    if (!valid) {
      return res.status(401).send("Invalid signature");
    }

    const event = JSON.parse(req.body.toString("utf8"));

    console.log(event);

    return res.sendStatus(204);
  },
);
```

Ошибка webhook не отменяет сохранение заявки.

## Telegram

Telegram настраивается отдельно для проекта.

Нужно:

- token бота;
- Chat ID;
- включённая интеграция.

Для группы можно отправить боту:

```text
/chatid
```

Если у бота настроен Telegram webhook, `getUpdates` недоступен до удаления webhook.

Уведомление содержит проект, форму, ID, дату, поля и Referer.

---

# 20. Пользователи и роли

## Administrator

Глобальный полный доступ:

- проекты;
- пользователи;
- схема;
- контент;
- формы и заявки;
- API;
- файлы;
- backup/restore;
- журнал;
- глобальная корзина;
- системные операции.

## Developer

Проектная роль:

- коллекции;
- поля;
- relation;
- разделы;
- импорт и экспорт схем;
- формы;
- API-ключи;
- API Explorer;
- проектные настройки;
- просмотр записей.

Developer не является ролью редактора контента.

## Editor

Проектная роль:

- создание и изменение записей;
- публикация;
- файлы;
- просмотр и обработка заявок;
- экспорт заявок.

Не изменяет схему.

## Viewer

Только просмотр.

## Матрица

| Возможность | Admin | Developer | Editor | Viewer |
|---|:---:|:---:|:---:|:---:|
| Просмотр записей | ✓ | ✓ | ✓ | ✓ |
| Изменение записей | ✓ | — | ✓ | — |
| Схема коллекций | ✓ | ✓ | — | — |
| Разделы | ✓ | ✓ | — | — |
| API и ключи | ✓ | ✓ | — | — |
| Настройки проекта | ✓ | ✓ | — | — |
| Формы: схема | ✓ | ✓ | — | — |
| Формы: заявки | ✓ | — | ✓ | — |
| Файлы | ✓ | — | ✓ | — |
| Пользователи | ✓ | — | — | — |
| Проекты | ✓ | — | — | — |
| Backup/restore | ✓ | — | — | — |
| Журнал | ✓ | — | — | — |

Administrator имеет глобальный доступ. Остальным роль назначается отдельно в каждом проекте.

«Нет доступа» убирает пользователя из проекта, но не удаляет аккаунт.

---

# 21. Content API

## Base URL

```text
https://cms.example.com/
```

Маршрутизация выполняется query-параметрами.

## API index

```http
GET /?api=index&project=site
```

## Список записей

```http
GET /?api=entries&project=site&c=services
```

## Одна запись

```http
GET /?api=entry&project=site&c=services&s=industrial-automation
```

## Раздел

```http
GET /?api=group&project=site&g=homepage
```

## Методы

```text
GET
HEAD
OPTIONS
```

Content API работает только на чтение.

## Публикация

Публично возвращаются только `published`.

## Успех

```json
{
  "api_version": "1.4",
  "ok": true
}
```

## Ошибка

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "collection_not_found",
  "message": "Collection not found.",
  "status": 404
}
```

Клиент проверяет:

1. HTTP status;
2. `ok`;
3. `error`;
4. `details`.

`message` не является стабильным машинным кодом.

---

# 22. Frontend-интеграция

## Универсальная функция JavaScript

```js
async function cmsFetch(params, options = {}) {
  const url = new URL("https://cms.example.com/");

  url.search = new URLSearchParams({
    project: "site",
    ...params,
  });

  const response = await fetch(url, {
    headers: {
      Accept: "application/json",
      ...options.headers,
    },
    ...options,
  });

  const payload = await response.json().catch(() => null);

  if (!response.ok || !payload?.ok) {
    const error = new Error(
      payload?.message ?? `CMS request failed: HTTP ${response.status}`,
    );

    error.status = response.status;
    error.code = payload?.error ?? "unknown_error";
    error.details = payload?.details ?? null;

    throw error;
  }

  return payload;
}
```

Использование:

```js
const payload = await cmsFetch({
  api: "entries",
  c: "services",
  lang: "ru",
  sort: "-id",
  limit: "20",
});

const services = payload.data;
```

## Nuxt 3

```js
// composables/useCms.js
export function useCms() {
  const config = useRuntimeConfig();

  async function request(params) {
    return await $fetch(config.public.cmsUrl, {
      query: {
        project: config.public.cmsProject,
        ...params,
      },
    });
  }

  return { request };
}
```

```js
// nuxt.config.js
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      cmsUrl: "https://cms.example.com/",
      cmsProject: "site",
    },
  },
});
```

```vue
<script setup>
const { request } = useCms();

const { data: payload, error } = await useAsyncData(
  "services-ru",
  () =>
    request({
      api: "entries",
      c: "services",
      lang: "ru",
      populate: "category,image",
    }),
);

const services = computed(() => payload.value?.data ?? []);
</script>
```

## PHP

```php
<?php

$query = http_build_query([
    'api' => 'entries',
    'project' => 'site',
    'c' => 'services',
    'lang' => 'ru',
]);

$url = 'https://cms.example.com/?' . $query;

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Accept: application/json\r\n",
        'timeout' => 5,
        'ignore_errors' => true,
    ],
]);

$raw = file_get_contents($url, false, $context);

if ($raw === false) {
    throw new RuntimeException('CMS request failed');
}

$payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);

if (empty($payload['ok'])) {
    throw new RuntimeException(
        $payload['message'] ?? 'CMS returned an error',
    );
}

$services = $payload['data'];
```

## Single может вернуть null

```js
const contacts = payload.data ?? {};
```

## Относительные URL файлов

```js
const cmsBase = new URL("https://cms.example.com/");
const fileUrl = new URL(file.url, cmsBase).href;
```

---

# 23. Фильтрация, сортировка и populate

## Пагинация

```text
page=1
limit=25
```

Максимум:

```text
100
```

```http
GET /?api=entries&project=site&c=articles&page=2&limit=10
```

`meta` содержит:

```text
total
page
limit
pages
has_more
next_page
prev_page
```

## Сортировка

```text
sort=title
sort=-created_at
sort=data.price
sort=-data.priority
```

## Поиск

```text
q=automation
```

Поиск выполняется по сериализованным данным. Для крупного каталога это не заменяет поисковый движок.

## Фильтр

```text
filter[data.category]=industrial
filter[data.featured]=true
```

Разные фильтры работают как AND.

Несколько значений:

```text
filter[data.category]=industrial,energy
```

внутри одного фильтра работают как OR.

## Проекция

```text
fields=id,title,slug,data.price,data.image
```

Она уменьшает ответ и исключает ненужные тяжёлые поля.

## Populate

Без параметра relation возвращает ID.

Все связи:

```text
populate=all
```

Только выбранные:

```text
populate=category,author
```

Рекомендация для списка:

```text
fields=id,title,slug,data.price,data.category
populate=category
```

Глубина раскрытия — один уровень.

---

# 24. Приватные ресурсы и API-ключи

Приватными могут быть:

- коллекции;
- разделы;
- формы.

## Создание

Developer или Administrator:

1. открывает API-ключи;
2. выбирает ресурс;
3. задаёт имя;
4. при необходимости указывает срок;
5. создаёт ключ;
6. копирует его сразу.

Полное значение показывается один раз.

## Передача

```http
Authorization: Bearer cms_xxxxxxxxx
```

или:

```http
X-API-Key: cms_xxxxxxxxx
```

## Серверный запрос

```js
const response = await fetch(
  "https://cms.example.com/?api=entries&project=site&c=private-data",
  {
    headers: {
      Authorization: `Bearer ${process.env.CMS_API_KEY}`,
      Accept: "application/json",
    },
  },
);
```

## Не размещайте ключ в браузере

Плохо:

```js
const key = "cms_real_secret";
```

Для конфиденциальных данных браузер должен обращаться к вашему backend, а backend — к CMS.

## Scope

Ключ действует только для выбранной коллекции, раздела или формы.

## Компрометация

1. отозвать;
2. создать новый;
3. обновить environment;
4. проверить `last_used_at`;
5. проверить журнал и access logs.

---

# 25. CORS и кэширование

## CORS

Задаётся на уровне проекта.

Разрешить всё:

```text
*
```

Allowlist:

```text
https://example.com
https://www.example.com
http://localhost:3000
http://localhost:5173
```

Указывайте только origin, без пути.

Правильно:

```text
https://example.com
```

Неправильно:

```text
https://example.com/page
```

CORS не заменяет авторизацию. Он ограничивает браузер, но не серверные запросы.

## Кэширование

Публичные GET-ответы могут использовать:

- файловый response cache;
- ETag;
- Last-Modified;
- `304 Not Modified`;
- автоматическую инвалидацию.

Приватные ответы:

```http
Cache-Control: private, no-store
```

В debug mode могут появляться:

```text
X-CMS-Cache: HIT
X-CMS-Cache: MISS
```

Не используйте их в бизнес-логике.

## CDN

Учитывайте в cache key:

```text
project
lang
api
c
g
s
populate
filter
fields
page
limit
sort
```

Не кэшируйте приватные ответы как публичные.


---

# 26. Backup, журнал и диагностика

## Резервная копия проекта

Backup включает:

- проект;
- глобальные и вложенные коллекции;
- поля;
- записи;
- разделы и связи;
- формы и поля форм;
- заявки;
- файлы проекта.

Для функции нужен `ZipArchive`.

## Создание

Administrator открывает настройки резервных копий и скачивает ZIP:

```text
cms-backup-site-20260625-120000.zip
```

Храните его вне публичного каталога.

Хорошо:

```text
/home/user/backups/
```

Плохо:

```text
public_html/cms/backup.zip
```

## Восстановление

Restore создаёт новый проект и переназначает:

- ID коллекций;
- ID записей;
- ID полей;
- ID файлов;
- relation;
- связи разделов;
- формы;
- заявки.

Если slug уже занят, создаётся уникальный вариант.

После восстановления проверьте:

1. структуру коллекций;
2. relation и nested relation;
3. файлы;
4. формы;
5. CORS;
6. пользователей;
7. приватные ключи;
8. Telegram;
9. frontend.

## API-ключи после restore

Не рассчитывайте, что действующие секреты будут перенесены как открытые ключи. Для приватных ресурсов создайте новые ключи и обновите приложения.

## Журнал действий

Доступен Administrator и записывает:

- пользователя;
- проект;
- action;
- entity;
- ID;
- описание;
- IP;
- User-Agent;
- дату.

Примеры:

```text
auth.login
auth.login_failed
project.switch
collection.create
entry.update
entry.restore_version
form.submitted
api_key.create
backup.download
backup.restore
```

Журнал помогает расследовать изменения, но не заменяет access/error logs сервера.

## Диагностика

Проверяет:

- PHP 8.1+;
- PDO;
- mbstring;
- JSON;
- OpenSSL;
- Fileinfo;
- драйвер базы;
- ZipArchive;
- запись в storage и uploads;
- config.json;
- HTTPS;
- защиту storage;
- upload limits;
- mail().

Статусы:

```text
ok
warning
error
```

`error` означает, что функция может не работать. `warning` — CMS может работать, но production-настройка неполная.

## Debug mode

Включайте только на время разработки или диагностики.

Он может раскрыть:

- пути;
- stack trace;
- настройки;
- SQL/DB детали;
- подробные upload errors.

После исправления обязательно выключите debug mode.

## Системное обслуживание

Удаляет:

- автосохранения старше 30 дней;
- orphan versions;
- старые read/spam заявки по retention;
- устаревший API-кэш.

Запускайте после крупных удалений, restore и периодически на активной установке.

---

# 27. Обновление и production

## Перед обновлением

1. Создайте backup каждого проекта.
2. Скопируйте `storage`.
3. Скопируйте `uploads`.
4. Сохраните текущий PHP-файл.
5. Запишите версии API и schema.
6. Проверьте свободное место.

## Обновление

1. Замените основной PHP-файл.
2. Не удаляйте `storage/config.json`.
3. Не удаляйте базу.
4. Не удаляйте `uploads`.
5. Откройте CMS.
6. Дождитесь автоматических миграций.
7. Проверьте диагностику.
8. Проверьте вход.
9. Проверьте записи.
10. Проверьте формы.
11. Проверьте API.
12. Создайте новый контрольный backup.

## Smoke test

```text
GET ?api=index&project=<project>
GET ?api=entries&project=<project>&c=<collection>
GET ?api=entry&project=<project>&c=<collection>&s=<slug>
GET ?form=<form>&project=<project>
POST ?form=<form>&project=<project>
```

## Откат

Если обновление не работает:

1. верните старый PHP-файл;
2. при необходимости восстановите storage и базу;
3. не смешивайте значительно более старый код с новой схемой без проверки;
4. используйте staging для крупных обновлений.

## Production checklist

- HTTPS;
- сильные пароли;
- debug выключен;
- storage закрыт;
- PHP обновлён;
- backup вне сайта;
- CORS ограничен;
- формы протестированы;
- роли минимальны;
- ключи не находятся в браузере.

## Права

Ориентировочно:

```text
directories: 775
files: 640 или 644
```

Фактические значения зависят от PHP-FPM пользователя и группы.

## Мониторинг

Минимум:

- HTTP-проверка API index;
- свободное место;
- размер базы;
- PHP error log;
- тестовая заявка;
- регулярный backup.

---

# 28. Практические схемы

## Корпоративный сайт

### Разделы

```text
homepage
services-page
about-page
contacts-page
```

### Коллекции

```text
hero               Single
advantages         Multiple
services           Multiple
equipment          Multiple
about              Single
contacts           Single
seo-home           Single
```

API главной:

```http
GET /?api=group&project=company&g=homepage&lang=ru&populate=all
```

## Блог

```text
articles            Multiple
article-categories  Multiple
authors             Multiple
blog-settings       Single
```

Поля `articles`:

```text
cover_image       Image
excerpt           Textarea
content           HTML
published_date    Date
category          Relation → article-categories
author            Relation → authors
featured          Boolean
```

Список:

```http
GET /?api=entries&project=site&c=articles&sort=-data.published_date&page=1&limit=10&populate=category,author
```

Статья:

```http
GET /?api=entry&project=site&c=articles&s=my-article&populate=all
```

## Каталог

```text
products
categories
brands
```

Поля:

```text
image
price
description
in_stock
category      Relation
brand         Relation
sku           Text global + Unique
```

```http
GET /?api=entries&project=shop&c=products&filter[data.category]=4&sort=data.price
```

## Услуги с тарифами

```text
services          Multiple
└── tariffs       Nested Multiple
```

В `services`:

```text
tariffs           Nested relation → tariffs
```

```http
GET /?api=entries&project=site&c=services&populate=tariffs
```

## FAQ

```text
faq Multiple
```

Поля:

```text
question Text required
answer Textarea required
category Relation optional
```

```http
GET /?api=entries&project=site&c=faq&limit=100
```

## Контактная форма

```text
Form: contact
```

Поля:

```text
name       text required
phone      tel required
company    text
message    textarea required
```

Рекомендуемые настройки:

```text
retention: 365
email: manager@example.com
Telegram: enabled
webhook: optional
```

---

# 29. Устранение неполадок

## DB error

Проверьте:

- драйвер PDO;
- существование MySQL базы;
- host;
- username/password;
- права MySQL;
- доступность SQLite;
- права storage.

## Не создаётся config.json

Проверьте владельца и права:

```bash
ls -ld storage
```

PHP должен иметь право записи.

## Ошибка загрузки

Проверьте:

- `upload_max_filesize`;
- `post_max_size`;
- размер;
- расширение;
- MIME;
- права uploads;
- `open_basedir`;
- temp directory;
- свободное место.

Временно включите debug, затем выключите.

## API возвращает пустой массив

Проверьте:

- запись Published;
- project;
- collection slug;
- фильтр;
- язык;
- доступ;
- parent context nested-данных.

## 401 API key required

Проверьте:

- resource private;
- заголовок;
- scope ключа;
- revoke;
- expiration;
- передачу Authorization reverse proxy.

## 403 CORS

Проверьте exact origin:

```text
scheme + host + port
```

`https://example.com` и `https://www.example.com` различаются.

## `unknown_field` формы

Frontend отправляет key, которого нет в схеме. Получите GET-схему формы и сравните payload.

## `required`

Проверьте:

- поле присутствует;
- значение не пустое;
- required boolean равен `true`;
- key совпадает;
- тип корректен.

## Email не приходит

Проверьте:

- `mail()` в диагностике;
- адрес;
- spam;
- SPF/DKIM;
- ограничения хостинга.

Заявка всё равно должна сохраниться.

## Telegram не приходит

Проверьте:

- token;
- Chat ID;
- enabled;
- права бота;
- membership в группе;
- Telegram webhook;
- журнал `form.telegram_failed`.

## Webhook не приходит

Проверьте:

- URL;
- HTTPS certificate;
- DNS;
- firewall;
- timeout;
- принимающий endpoint;
- журнал;
- подпись по raw body.

## Backup не создаётся

Проверьте:

- ZipArchive;
- temp directory;
- свободное место;
- чтение uploads;
- PHP memory/upload limits.

## Nginx отдаёт config.json

Настройте запрет `/storage/`. `.htaccess` в Nginx не работает.

## Изменения не видны

Проверьте:

- статус Published;
- язык;
- API URL;
- браузерный/CDN-кэш;
- ETag;
- service worker;
- правильный проект;
- правильную коллекцию.

---

# 30. Безопасность и краткий справочник

## Частые ошибки проектирования

### Одна коллекция на весь сайт

Разделяйте данные на сущности.

### HTML для всех данных

HTML плохо фильтруется и переиспользуется.

### Случайные slug

Slug является контрактом.

### Изменение key после интеграции

Frontend перестанет находить данные.

### Relation вместо nested relation

Relation — общий справочник. Nested relation — дочерние данные конкретного родителя.

### Секретный ключ в frontend

Ключ в браузере не секретен.

### `populate=all` везде

Раскрывайте только необходимые связи.

### Отсутствие `project`

Всегда указывайте project в production-интеграции.

### Очистка файлов без проверки

Файл может использоваться вне полей CMS.

## Контрольный список безопасности

### До запуска

- [ ] PHP обновлён.
- [ ] HTTPS включён.
- [ ] Сильный admin password.
- [ ] Debug выключен.
- [ ] `storage` недоступен из браузера.
- [ ] Старые PHP-копии удалены из public directory.
- [ ] CORS ограничен.
- [ ] API-ключи не встроены в frontend.
- [ ] Backup хранится вне `public_html`.
- [ ] Формы протестированы.
- [ ] Retention соответствует политике.
- [ ] Webhook проверяет HMAC.
- [ ] Telegram token не опубликован.
- [ ] Пользователи имеют минимальные роли.
- [ ] Удалены тестовые аккаунты.
- [ ] Проверены права каталогов.

### Регулярно

- [ ] Обновлять PHP.
- [ ] Проверять журнал.
- [ ] Отзывать старые ключи.
- [ ] Проверять `last_used_at`.
- [ ] Делать backup.
- [ ] Тестировать restore.
- [ ] Проверять свободное место.
- [ ] Отправлять тестовую заявку.
- [ ] Проверять диагностику после изменений хостинга.

## Основные URL

```text
/?api=index&project=PROJECT
/?api=entries&project=PROJECT&c=COLLECTION
/?api=entry&project=PROJECT&c=COLLECTION&s=ENTRY
/?api=group&project=PROJECT&g=GROUP
/?form=FORM&project=PROJECT
```

## Язык

```text
lang=ru
lang=kk
lang=en
lang=default
lang=all
```

## Выборка

```text
page=1
limit=25
q=text
sort=-created_at
filter[data.field]=value
fields=id,title,slug,data.price
populate=all
populate=category,author
```

## Приватный доступ

```http
Authorization: Bearer <KEY>
```

или:

```http
X-API-Key: <KEY>
```

## Статусы

Записи:

```text
draft
published
```

Формы:

```text
active
inactive
```

Заявки:

```text
new
read
spam
```

## Ограничения

```text
API limit: 100
Upload: 10 MB
Form payload: 65535 bytes
Form rate: 20 / 10 minutes / IP / form
Nested depth: 1
Populate depth: 1
Autosave retention: 30 days
```

## Глоссарий

**API Explorer** — встроенная проверка запросов.

**Content API** — read-only JSON API опубликованного контента.

**Group** — раздел, объединяющий несколько коллекций.

**Headless CMS** — CMS данных без обязательного публичного шаблона.

**Nested collection** — дочерняя коллекция в контексте родительской записи.

**Populate** — преобразование relation ID в связанные объекты.

**Resource-scoped key** — ключ одной коллекции, раздела или формы.

**Single** — коллекция с одной записью.

**Multiple** — коллекция со списком записей.

**Slug** — стабильный идентификатор URL и API.

**Snapshot** — предыдущее состояние записи в истории.

---

# Итоговый рабочий процесс

1. Установить CMS.
2. Проверить диагностику.
3. Создать проект.
4. Описать сущности.
5. Создать коллекции и поля.
6. Создать тестовый контент.
7. Проверить Draft и Published.
8. Проверить языки.
9. Проверить relation и nested relation.
10. Проверить API Explorer.
11. Подключить frontend.
12. Настроить формы.
13. Настроить CORS и ключи.
14. Создать backup.
15. Выполнить production checklist.

Mini Headless CMS работает наиболее предсказуемо, когда схема остаётся простой, данные структурированы, slug и key стабильны, а frontend воспринимает API как явно определённый контракт.
