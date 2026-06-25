# Mini Headless CMS API Contract

> Технический контракт публичного JSON API и endpoint-форм Mini Headless CMS.

**Версия API:** `1.4`  
**Версия схемы приложения:** `10`  
**Дата актуализации:** 25 июня 2026 года  
**Статус:** текущий контракт реализации

---

## 1. Назначение документа

Этот документ описывает фактическое поведение API Mini Headless CMS:

- формат URL и маршрутизацию;
- публичные и приватные ресурсы;
- API-ключи и административную сессию;
- получение коллекций, записей и разделов;
- мультиязычные ответы;
- фильтрацию, сортировку, пагинацию и проекцию полей;
- раскрытие связей через `populate`;
- получение схемы и отправку публичных форм;
- форматы успешных ответов и ошибок;
- HTTP-заголовки, CORS и кэширование;
- правила обратной совместимости API `1.x`.

Документ описывает существующую реализацию. Он не является обещанием поддержки отсутствующих REST-маршрутов, GraphQL, записи контента через публичный API или прямого доступа к базе данных.

---

## 2. Базовый URL

Mini Headless CMS использует query-based API. Все endpoint’ы обслуживаются тем же PHP-файлом, в котором работает CMS.

Пример базового адреса:

```text
https://example.com/cms/index.php
```

Пример запроса:

```text
https://example.com/cms/index.php?api=entries&project=website&c=services
```

Если PHP-файл размещён как корневой `index.php`, адрес может выглядеть так:

```text
https://cms.example.com/?api=entries&project=website&c=services
```

### 2.1. Маршрутизация

Основной маршрут задаётся query-параметром `api`.

```text
?api=index
?api=entries
?api=entry
?api=group
?api=collections
?api=groups
?api=fields
?api=schema
?api=files
?api=files-trash
```

Публичные формы используют отдельный параметр:

```text
?form=contact
```

### 2.2. Выбор проекта

Проект задаётся одним из параметров:

```text
project=<project-slug>
p=<project-slug>
```

Рекомендуемый вариант:

```text
project=website
```

Если проект явно не передан, CMS использует проект по умолчанию.

Если переданный slug не существует, API возвращает:

```http
404 Not Found
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "project_not_found",
  "message": "Project not found.",
  "status": 404
}
```

---

## 3. Поддерживаемые HTTP-методы

### 3.1. Content API

Для endpoint’ов `?api=...` разрешены:

```text
GET
HEAD
OPTIONS
```

Публичная запись и изменение контента через Content API не поддерживаются.

### 3.2. Публичные формы

Для `?form=...` разрешены:

```text
GET
HEAD
POST
OPTIONS
```

Назначение методов:

| Метод | Назначение |
|---|---|
| `GET` | Получить схему формы |
| `HEAD` | Получить заголовки без тела |
| `POST` | Отправить заявку |
| `OPTIONS` | CORS preflight |

Для неподдерживаемого метода возвращается `405 Method Not Allowed` и заголовок `Allow`.

---

## 4. Формат данных

### 4.1. Кодировка

Все JSON-ответы возвращаются в UTF-8:

```http
Content-Type: application/json; charset=utf-8
```

JSON формируется без экранирования Unicode и `/`.

### 4.2. Версия API

Каждый JSON-ответ содержит:

```json
{
  "api_version": "1.4"
}
```

Также версия передаётся заголовком:

```http
X-API-Version: 1.4
```

### 4.3. Успешный ответ

Успешные ответы содержат:

```json
{
  "api_version": "1.4",
  "ok": true
}
```

Остальные поля зависят от endpoint’а.

### 4.4. Ошибка

Единый формат ошибки:

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "error_code",
  "message": "Human-readable message",
  "status": 400
}
```

При наличии дополнительных сведений добавляется объект `details`:

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "validation_failed",
  "message": "Invalid field value",
  "status": 422,
  "details": {
    "validation_code": "invalid_value",
    "field": "email",
    "expected_type": "email"
  }
}
```

Клиентская логика должна ориентироваться прежде всего на:

1. HTTP status;
2. `ok`;
3. машинный код `error`;
4. данные из `details`.

Текст `message` предназначен для человека и может быть локализован или уточнён в последующих версиях.

---

## 5. Доступ и авторизация

В CMS существуют три разных механизма доступа:

1. публичный ресурс;
2. приватный ресурс с API-ключом;
3. административный endpoint с пользовательской сессией CMS.

### 5.1. Публичный ресурс

Ресурс с режимом `public` доступен без API-ключа.

Публичный Content API возвращает только записи со статусом:

```text
published
```

Черновики через публичный API не выдаются.

### 5.2. Приватный ресурс

Приватные коллекции, разделы и формы требуют API-ключ.

Ключ можно передать одним из способов.

#### Заголовок `X-API-Key`

```http
X-API-Key: cms_xxxxxxxxxxxxxxxxxxxxxxxxx
```

#### Bearer-токен

```http
Authorization: Bearer cms_xxxxxxxxxxxxxxxxxxxxxxxxx
```

Рекомендуемый вариант для серверных интеграций:

```http
Authorization: Bearer <API_KEY>
```

### 5.3. Область действия API-ключа

API-ключ привязан к конкретному ресурсу:

- коллекции;
- разделу;
- форме.

Ключ коллекции не предоставляет доступ к другой коллекции.

Ключ формы не предоставляет доступ к коллекциям.

Ключ приватного раздела разрешает получить этот раздел и коллекции, входящие в него, включая приватные коллекции внутри данного раздела.

### 5.4. Управляемые API-ключи

Управляемый ключ может иметь:

- имя;
- статус `active` или `revoked`;
- дату окончания действия;
- дату последнего использования;
- привязку к проекту и ресурсу.

Полное значение ключа показывается только при создании. В интерфейсе хранится и отображается только безопасная часть метаданных ключа.

### 5.5. Ошибка ключа

При отсутствии, истечении, отзыве или несовпадении ключа:

```http
401 Unauthorized
WWW-Authenticate: Bearer realm="Mini Headless CMS"
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "api_key_required",
  "message": "Invalid or missing API key",
  "status": 401
}
```

### 5.6. Endpoint’ы административной сессии

Следующие endpoint’ы не открываются обычным ресурсным API-ключом и требуют активную пользовательскую сессию CMS с соответствующими правами:

```text
?api=collections
?api=groups
?api=fields
?api=schema
?api=files
?api=files-trash
```

Также административная сессия требуется, если в публичном запросе запрашивается схема коллекции:

```text
schema=1
include_schema=1
fields=1
```

При недостаточных правах возвращается:

```http
403 Forbidden
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "auth_required",
  "message": "This API is available only to authenticated users",
  "status": 403
}
```

---

## 6. CORS

CORS задаётся на уровне проекта.

Поддерживаются:

- `*` — доступ с любого origin;
- список разрешённых origin;
- точные origin формата `http://host`, `https://host` или с портом.

Примеры допустимых значений:

```text
https://example.com
https://admin.example.com
http://localhost:3000
```

Пути, query string, fragment, user info и неподдерживаемые схемы в origin не допускаются.

### 6.1. Заголовки CORS

API может возвращать:

```http
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, HEAD, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Accept, X-API-Key, Authorization
Access-Control-Max-Age: 600
Vary: Origin
```

Если origin не входит в белый список:

```http
403 Forbidden
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "cors_denied",
  "message": "CORS origin is not allowed",
  "status": 403
}
```

### 6.2. Credentials

Текущий публичный CORS-контракт не объявляет:

```http
Access-Control-Allow-Credentials: true
```

Поэтому внешнему frontend-приложению не следует рассчитывать на cookie-сессию CMS в cross-origin запросах.

Для приватных публичных ресурсов используйте API-ключ.

---

## 7. Мультиязычность

### 7.1. Параметр языка

Язык задаётся параметром:

```text
lang=<language-code>
```

Поддерживается alias:

```text
locale=<language-code>
```

Рекомендуется использовать `lang`.

### 7.2. Значения

| Значение | Поведение |
|---|---|
| параметр отсутствует | язык проекта по умолчанию |
| `default` | язык проекта по умолчанию |
| код языка | конкретный язык, например `ru`, `kk`, `en` |
| `all` | все языки |
| `*` | все языки |

Пример:

```text
?api=entries&project=website&c=services&lang=kk
```

### 7.3. Неподдерживаемый язык

Если включена интернационализация и передан язык, отсутствующий в настройках проекта:

```http
400 Bad Request
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "unsupported_language",
  "message": "Unsupported content language.",
  "status": 400,
  "details": {
    "requested": "pl",
    "supported": ["ru", "kk", "en"]
  }
}
```

### 7.4. Контекст языка в ответе

Content API обычно возвращает:

```json
{
  "lang": "kk",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"]
}
```

Для `lang=all`:

```json
{
  "lang": "all",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"]
}
```

### 7.5. Одноязычный проект

Если интернационализация отключена:

- API использует основной язык проекта;
- `lang=all` не создаёт мультиязычную структуру;
- данные возвращаются в обычном одноязычном формате.

### 7.6. Fallback переводов

Для конкретного языка CMS использует данные языка проекта по умолчанию как fallback для отсутствующих переводимых значений.

Поле `translated` показывает, подтверждён ли перевод выбранного языка:

```json
{
  "lang": "kk",
  "translated": true
}
```

---

## 8. Общие query-параметры

| Параметр | Тип | Назначение |
|---|---:|---|
| `api` | string | Название Content API endpoint’а |
| `project` | string | Slug проекта |
| `p` | string | Alias для `project` |
| `c` | string | Slug коллекции |
| `g` | string | Slug раздела |
| `s` | string | Slug записи; для `group` также может быть alias раздела |
| `lang` | string | Язык ответа |
| `locale` | string | Alias для `lang` |
| `page` | integer | Номер страницы, начиная с `1` |
| `limit` | integer | Размер страницы от `1` до `100` |
| `q` | string | Полнотекстовый поиск по сериализованному JSON представлению |
| `sort` | string | Поле сортировки |
| `filter[field]` | scalar/string | Фильтрация по полю |
| `fields` | string | Проекция возвращаемых полей |
| `populate` | string | Раскрытие связей |
| `schema` | boolean-like | Добавить схему к ответу |
| `include_schema` | boolean-like | Alias для `schema` |
| `by_slug` | boolean-like | Добавить индекс коллекций раздела по slug |
| `pretty` | boolean-like | Форматированный JSON |

Boolean-like значения:

```text
1
true
yes
on
```

Отрицательные значения, используемые для `populate`:

```text
0
false
no
off
none
```

---

## 9. Пагинация

Пагинация применяется к спискам записей и файлов.

Значения по умолчанию:

```text
page=1
limit=25
```

Максимальный `limit`:

```text
100
```

Минимальный `limit`:

```text
1
```

Пример:

```text
?api=entries&project=website&c=news&page=2&limit=20
```

Формат `meta`:

```json
{
  "total": 87,
  "page": 2,
  "limit": 20,
  "pages": 5,
  "has_more": true,
  "next_page": 3,
  "prev_page": 1,
  "type": "multiple",
  "sort": "-id"
}
```

Если запрошенная страница больше последней существующей, CMS возвращает последнюю страницу.

Для пустого списка:

```json
{
  "total": 0,
  "page": 1,
  "limit": 25,
  "pages": 0,
  "has_more": false,
  "next_page": null,
  "prev_page": null,
  "type": "multiple"
}
```

---

## 10. Сортировка

Параметр:

```text
sort=<field>
```

Префикс `-` означает сортировку по убыванию:

```text
sort=-id
```

Префикс `+` или отсутствие префикса означает сортировку по возрастанию:

```text
sort=title
sort=+title
```

Поддерживаемые системные поля:

```text
id
title
slug
status
created_at
updated_at
```

Поддерживается сортировка по данным записи:

```text
sort=data.price
sort=-data.priority
```

Значение по умолчанию:

```text
sort=-id
```

Для неизвестного поля сортировки используется `id`.

---

## 11. Поиск

Параметр:

```text
q=<search-string>
```

Поиск:

- регистронезависимый;
- выполняется по сериализованному JSON представлению элемента;
- может находить значения в системных и пользовательских полях;
- выполняется до пагинации.

Пример:

```text
?api=entries&project=website&c=services&q=automation
```

---

## 12. Фильтрация

Формат:

```text
filter[field]=value
```

Примеры:

```text
filter[status]=published
filter[data.category]=industrial
filter[data.featured]=true
filter[data.price]=15000
```

Несколько фильтров объединяются логикой `AND`:

```text
filter[data.category]=industrial&filter[data.featured]=true
```

Несколько значений одного фильтра можно передать строкой через запятую:

```text
filter[data.category]=industrial,energy
```

Значения одного фильтра сопоставляются логикой `OR`.

Сравнение учитывает тип:

- массив совпадает, если совпал хотя бы один его элемент;
- `null` сопоставляется с пустой строкой или `null`;
- boolean понимает `1`, `0`, `true`, `false`, `yes`, `no`, `on`, `off`;
- числовые значения сравниваются как числа;
- строки сравниваются регистронезависимо после удаления внешних пробелов.

---

## 13. Проекция полей

Параметр `fields` уменьшает объём записи в ответе.

Пример:

```text
fields=id,title,slug,data.price,data.image
```

Поддерживаются системные поля:

```text
id
title
slug
status
created_at
updated_at
parent_entry
lang
i18n
translated
default_lang
languages
translated_languages
translations
data
```

Пользовательское поле можно указать двумя способами:

```text
data.price
price
```

Оба варианта добавят значение в объект `data`.

Пример ответа:

```json
{
  "id": 15,
  "title": "Service",
  "slug": "service",
  "data": {
    "price": 15000,
    "image": {
      "id": 7,
      "url": "uploads/service.webp"
    }
  }
}
```

### 13.1. Особое значение `fields=1`

Значения:

```text
fields=1
fields=true
fields=yes
fields=on
```

не выполняют проекцию. Они означают запрос схемы и требуют административную сессию с правом просмотра API.

Для получения только части данных используйте список через запятую.

---

## 14. Раскрытие связей

### 14.1. Без `populate`

По умолчанию relation-поля возвращают идентификаторы:

```json
{
  "data": {
    "category": 4,
    "related_services": [11, 12, 15]
  }
}
```

### 14.2. Все связи

```text
populate=all
```

Также поддерживаются:

```text
populate=1
populate=true
populate=yes
populate=on
populate=*
```

### 14.3. Выбранные relation-поля

```text
populate=category,related_services
```

Можно разделять ключи запятой или пробелами.

Ограничения:

- не более 50 ключей;
- ключ должен начинаться с буквы или `_`;
- разрешены буквы, цифры, `_` и `-`;
- глубина раскрытия связей — один уровень.

### 14.4. Раскрытая связь

Пример:

```json
{
  "id": 4,
  "title": "Industrial automation",
  "slug": "industrial-automation",
  "status": "published",
  "lang": "en",
  "collection": {
    "id": 2,
    "name": "Categories",
    "slug": "categories",
    "scope": "global"
  },
  "data": {
    "icon": "cpu"
  },
  "created_at": "2026-06-20 10:00:00",
  "updated_at": "2026-06-24 18:30:00"
}
```

### 14.5. Связь с вложенной коллекцией

Для `nested_relation` раскрытая запись дополнительно может содержать:

```json
{
  "parent_entry": {
    "id": 20,
    "title": "Main service",
    "slug": "main-service",
    "collection": {
      "id": 3,
      "name": "Services",
      "slug": "services"
    }
  }
}
```

Вложенные коллекции:

- не имеют отдельного публичного endpoint’а;
- не возвращаются автоматически внутри родителя;
- доступны через поле типа `nested_relation`;
- раскрываются обычным параметром `populate`;
- ограничены контекстом текущей родительской записи;
- поддерживают один уровень вложенности.

### 14.6. Автоматический режим «все связи»

Для multiple relation поле может хранить режим «всегда все».

В API такой режим нормализуется в актуальный массив ID опубликованных записей целевой коллекции. При добавлении новых опубликованных записей они автоматически попадут в результат следующего запроса.

---

## 15. Типы коллекций

Коллекция может иметь тип:

```text
single
multiple
```

### 15.1. Multiple

`data` содержит массив записей:

```json
{
  "type": "multiple",
  "data": []
}
```

### 15.2. Single

`data` содержит одну запись или `null`:

```json
{
  "type": "single",
  "data": {
    "id": 1,
    "title": "Homepage",
    "slug": "homepage",
    "data": {}
  },
  "meta": {
    "total": 1,
    "type": "single",
    "sort": "-id"
  }
}
```

Пагинация для Single-коллекции не используется как для обычного массива.

---

## 16. Модели данных

### 16.1. Project

```json
{
  "id": 1,
  "name": "Website",
  "slug": "website"
}
```

### 16.2. Collection

```json
{
  "id": 3,
  "name": "Services",
  "slug": "services",
  "description": "Company services",
  "type": "multiple",
  "access": "public",
  "order": 20
}
```

Значения `access`:

```text
public
private
```

### 16.3. Group

```json
{
  "id": 2,
  "name": "Homepage",
  "slug": "homepage",
  "description": "Homepage content",
  "access": "public",
  "order": 10
}
```

### 16.4. Entry для одного языка

```json
{
  "id": 15,
  "title": "Industrial automation",
  "slug": "industrial-automation",
  "status": "published",
  "created_at": "2026-06-20 10:00:00",
  "updated_at": "2026-06-24 18:30:00",
  "lang": "en",
  "i18n": true,
  "translated": true,
  "data": {
    "description": "Automation services",
    "price": 15000,
    "active": true
  }
}
```

### 16.5. Entry для `lang=all`

```json
{
  "id": 15,
  "title": "Промышленная автоматизация",
  "slug": "industrial-automation",
  "status": "published",
  "created_at": "2026-06-20 10:00:00",
  "updated_at": "2026-06-24 18:30:00",
  "lang": "all",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "translated_languages": ["ru", "kk", "en"],
  "data": {
    "price": 15000,
    "active": true
  },
  "translations": {
    "ru": {
      "data": {
        "description": "Услуги автоматизации"
      },
      "translated": true
    },
    "kk": {
      "data": {
        "description": "Автоматтандыру қызметтері"
      },
      "translated": true
    },
    "en": {
      "data": {
        "description": "Automation services"
      },
      "translated": true
    }
  }
}
```

При `lang=all`:

- общие непереводимые данные находятся в корневом `data`;
- переводимые значения находятся внутри `translations.<lang>.data`;
- relation и file значения могут быть разрешены в соответствующих объектах;
- `title` остаётся служебным заголовком записи и берётся из основного языка.

### 16.6. Field

```json
{
  "id": 8,
  "label": "Price",
  "key": "price",
  "type": "number",
  "required": true,
  "order": 20,
  "rules": {
    "min": 0,
    "max": 1000000
  },
  "default": 0
}
```

В зависимости от настроек поле может содержать:

```text
placeholder
hint
choice_labels
rules
default
relation
```

### 16.7. Relation field

```json
{
  "id": 12,
  "label": "Category",
  "key": "category",
  "type": "relation",
  "required": false,
  "order": 30,
  "relation": {
    "kind": "global",
    "mode": "single",
    "target_collection_id": 4,
    "target_collection_slug": "categories",
    "target_collection": {
      "id": 4,
      "name": "Categories",
      "slug": "categories",
      "scope": "global",
      "access": "public",
      "parent_collection": null
    }
  }
}
```

Значения:

```text
relation.kind: global | nested
relation.mode: single | multiple
target_collection.scope: global | nested
```

### 16.8. File

```json
{
  "id": 7,
  "file_id": 7,
  "name": "service-image.webp",
  "file": "a1b2c3-service-image.webp",
  "url": "uploads/a1b2c3-service-image.webp",
  "size": 184320,
  "mime": "image/webp",
  "ext": "webp",
  "status": "active",
  "origin_project_id": null,
  "origin_project_name": null,
  "reason": null,
  "created_at": "2026-06-20 10:00:00",
  "updated_at": "2026-06-20 10:00:00"
}
```

URL файла может быть относительным. Клиент должен разрешать его относительно адреса CMS, если приложение не настроило собственный публичный base URL.

---

## 17. Типы полей коллекций

Текущая реализация поддерживает:

```text
text
text_global
textarea
ul_list
ol_list
ul_list_i18n
ol_list_i18n
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

### 17.1. Переводимые типы

Переводимыми считаются:

```text
text
textarea
ul_list_i18n
ol_list_i18n
```

Остальные значения являются общими для всех языков.

### 17.2. Базовые JSON-типы значений

| CMS type | JSON value |
|---|---|
| `text`, `text_global`, `textarea`, `html` | string |
| `email`, `tel`, `date`, `datetime`, `url` | string |
| `number` | number |
| `integer` | integer |
| `bool` | boolean |
| `ul_list`, `ol_list`, `ul_list_i18n`, `ol_list_i18n` | array of strings |
| `image`, `file` | file object или `null` |
| `json` | JSON-compatible value |
| `relation` single | integer ID или populated object |
| `relation` multiple | array of integer IDs или populated objects |
| `nested_relation` | как relation, но в рамках родительской записи |

---

## 18. Endpoint: API index

```http
GET ?api=index&project=<project-slug>
```

Параметр `api` можно не передавать, если запрос уже направлен в API-обработчик без другого режима. Для явного контракта рекомендуется всегда использовать `api=index`.

Ответ содержит:

- имя CMS;
- проект;
- настройки интернационализации;
- ограничения;
- поддерживаемые параметры;
- примеры доступных маршрутов.

Пример:

```json
{
  "api_version": "1.4",
  "ok": true,
  "name": "Mini Headless CMS",
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "content_i18n": true,
  "default_lang": "ru",
  "content_languages": ["ru", "kk", "en"],
  "public": "published_content_only",
  "limits": {
    "max_page_size": 100,
    "relation_depth": 1
  },
  "parameters": {
    "lang": "default | language code | all",
    "populate": "0 | all | relation_key[,relation_key]",
    "fields": "title,slug,data.field",
    "filter": "filter[field]=value",
    "sort": "id, title, slug, created_at, updated_at, data.field"
  },
  "routes": []
}
```

### Доступ

Публичный.

### Кэш

Публичный ответ может кэшироваться до 300 секунд.

---

## 19. Endpoint: список записей коллекции

```http
GET ?api=entries&project=<project>&c=<collection>&lang=<lang>
```

Обязательные параметры:

```text
api=entries
c=<collection-slug>
```

Опциональные параметры:

```text
project
lang
page
limit
q
sort
filter[...]
fields
populate
pretty
```

Пример:

```bash
curl "https://cms.example.com/?api=entries&project=website&c=services&lang=ru&page=1&limit=25&sort=-id"
```

Пример ответа Multiple-коллекции:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "lang": "ru",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "collection": {
    "id": 3,
    "name": "Services",
    "slug": "services",
    "description": "",
    "type": "multiple",
    "access": "public",
    "order": 10
  },
  "type": "multiple",
  "access": "public",
  "populate": false,
  "data": [],
  "meta": {
    "total": 0,
    "page": 1,
    "limit": 25,
    "pages": 0,
    "has_more": false,
    "next_page": null,
    "prev_page": null,
    "type": "multiple",
    "sort": "-id"
  }
}
```

### Доступ

- public collection — без ключа;
- private collection — с ключом этой коллекции;
- nested collection — прямой endpoint запрещён.

### Ошибки

```text
collection_required
collection_not_found
api_key_required
unsupported_language
```

---

## 20. Endpoint: одна запись

```http
GET ?api=entry&project=<project>&c=<collection>&s=<entry-slug>
```

Обязательные параметры:

```text
api=entry
c=<collection-slug>
s=<entry-slug>
```

Опциональные:

```text
project
lang
fields
populate
pretty
```

Пример:

```bash
curl "https://cms.example.com/?api=entry&project=website&c=services&s=industrial-automation&lang=en&populate=category"
```

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "lang": "en",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "collection": {
    "id": 3,
    "name": "Services",
    "slug": "services",
    "description": "",
    "type": "multiple",
    "access": "public",
    "order": 10
  },
  "access": "public",
  "populate": ["category"],
  "data": {
    "id": 15,
    "title": "Industrial automation",
    "slug": "industrial-automation",
    "status": "published",
    "created_at": "2026-06-20 10:00:00",
    "updated_at": "2026-06-24 18:30:00",
    "lang": "en",
    "i18n": true,
    "translated": true,
    "data": {}
  }
}
```

Поиск выполняется только среди опубликованных записей.

### Ошибки

```text
collection_required
collection_not_found
entry_required
entry_not_found
api_key_required
```

---

## 21. Endpoint: раздел контента

```http
GET ?api=group&project=<project>&g=<group-slug>
```

Slug раздела можно передать через:

```text
g=<group-slug>
s=<group-slug>
```

Рекомендуется `g`.

Опциональные параметры:

```text
lang
page
limit
q
sort
filter[...]
fields
populate
by_slug
pretty
```

Пример:

```bash
curl "https://cms.example.com/?api=group&project=website&g=homepage&lang=ru&populate=all"
```

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "lang": "ru",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "access": "public",
  "populate": true,
  "group": {
    "id": 2,
    "name": "Homepage",
    "slug": "homepage",
    "description": "",
    "access": "public",
    "order": 10,
    "project": {
      "id": 1,
      "name": "Website",
      "slug": "website"
    },
    "lang": "ru",
    "populate": true,
    "collections": []
  }
}
```

Каждая коллекция внутри `group.collections` содержит:

```text
метаданные коллекции
data
meta
```

### 21.1. Индекс по slug

При:

```text
by_slug=1
```

раздел дополнительно содержит:

```json
{
  "by_slug": {
    "hero": 0,
    "services": 1,
    "contacts": 2
  }
}
```

Значение — индекс коллекции в массиве `collections`.

### 21.2. Публичный раздел

Публичный раздел без ключа не включает приватные коллекции.

### 21.3. Приватный раздел

Ключ приватного раздела позволяет получить коллекции, включённые в этот раздел, в том числе приватные.

---

## 22. Endpoint: список коллекций

```http
GET ?api=collections&project=<project>&lang=<lang>
```

### Доступ

Только административная сессия CMS с правом API.

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "lang": "ru",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "data": [],
  "meta": {
    "total": 0
  }
}
```

Прямой список предназначен для API Explorer и административных интеграций. Он не является публичным каталогом схем.

---

## 23. Endpoint: список разделов

```http
GET ?api=groups&project=<project>&lang=<lang>
```

### Доступ

Только административная сессия CMS с правом API.

Каждый раздел содержит массив метаданных связанных коллекций:

```json
{
  "id": 2,
  "name": "Homepage",
  "slug": "homepage",
  "description": "",
  "access": "public",
  "order": 10,
  "collections": []
}
```

---

## 24. Endpoint: поля коллекции

```http
GET ?api=fields&project=<project>&c=<collection>&lang=<lang>
```

### Доступ

Только административная сессия CMS с правом API.

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {},
  "lang": "ru",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "collection": {},
  "data": [],
  "meta": {
    "total": 0
  }
}
```

---

## 25. Endpoint: схема коллекции

```http
GET ?api=schema&project=<project>&c=<collection>&lang=<lang>
```

### Доступ

Только административная сессия CMS с правом API.

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {},
  "lang": "ru",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "collection": {},
  "fields": []
}
```

### 25.1. Схема внутри content endpoint

Схему также можно запросить через:

```text
?api=entries&c=services&schema=1
?api=entry&c=services&s=item&include_schema=1
?api=group&g=homepage&schema=1
```

Такой запрос требует административную сессию.

---

## 26. Endpoint: файлы

Активные файлы:

```http
GET ?api=files&project=<project>&page=1&limit=25
```

Корзина файлов:

```http
GET ?api=files-trash&project=<project>&page=1&limit=25
```

### Доступ

Только административная сессия CMS с правом управления файлами.

### Параметры

```text
page
limit
q
```

### Ответ

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {},
  "data": [],
  "meta": {
    "total": 0,
    "page": 1,
    "limit": 25,
    "pages": 0,
    "has_more": false,
    "next_page": null,
    "prev_page": null,
    "type": "multiple"
  }
}
```

Эти endpoint’ы не предназначены для публичного файлового каталога.

---

## 27. Публичные формы

Endpoint формы:

```http
GET|POST ?form=<form-slug>&project=<project-slug>&lang=<lang>
```

Alias проекта:

```text
p=<project-slug>
```

Пример:

```text
https://cms.example.com/?form=contact&project=website&lang=ru
```

Форма должна иметь статус:

```text
active
```

Неактивная форма возвращает:

```http
410 Gone
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "form_inactive",
  "message": "Form is inactive",
  "status": 410
}
```

---

## 28. Получение схемы формы

```http
GET ?form=<form-slug>&project=<project>&lang=<lang>
```

Пример:

```bash
curl "https://cms.example.com/?form=contact&project=website&lang=ru"
```

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "lang": "ru",
  "i18n": true,
  "default_lang": "ru",
  "languages": ["ru", "kk", "en"],
  "form": {
    "id": 4,
    "name": "Contact form",
    "slug": "contact",
    "description": "",
    "success_message": "Thank you. We will contact you.",
    "access": "public",
    "status": "active",
    "fields": []
  },
  "endpoint": "https://cms.example.com/?form=contact&project=website&lang=ru",
  "method": "POST",
  "accept": [
    "application/x-www-form-urlencoded",
    "multipart/form-data",
    "application/json"
  ],
  "files": false
}
```

### 28.1. Типы полей формы

Поддерживаются:

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

### 28.2. Модель поля формы

```json
{
  "id": 10,
  "label": "Email",
  "key": "email",
  "type": "email",
  "required": true,
  "order": 20,
  "rules": {
    "max_length": 254
  }
}
```

При `lang=all` мультиязычная форма может дополнительно возвращать переводы названия формы, success message и label полей.

---

## 29. Отправка формы

```http
POST ?form=<form-slug>&project=<project>&lang=<lang>
```

### 29.1. JSON

```bash
curl -X POST \
  "https://cms.example.com/?form=contact&project=website&lang=ru" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Temirkhan",
    "email": "user@example.com",
    "phone": "+7 700 000 00 00",
    "message": "Project request"
  }'
```

### 29.2. URL encoded

```bash
curl -X POST \
  "https://cms.example.com/?form=contact&project=website&lang=ru" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "name=Temirkhan" \
  --data-urlencode "email=user@example.com" \
  --data-urlencode "message=Project request"
```

### 29.3. Multipart без файлов

```bash
curl -X POST \
  "https://cms.example.com/?form=contact&project=website&lang=ru" \
  -F "name=Temirkhan" \
  -F "email=user@example.com" \
  -F "message=Project request"
```

Формат `multipart/form-data` поддерживается для обычных текстовых полей, но загрузка файлов через публичные формы запрещена.

### 29.4. Приватная форма

```bash
curl -X POST \
  "https://cms.example.com/?form=contact&project=website&lang=ru" \
  -H "Authorization: Bearer cms_xxxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"name":"Temirkhan","message":"Project request"}'
```

---

## 30. Правила payload формы

### 30.1. Верхний уровень

JSON body должен быть объектом:

```json
{
  "field": "value"
}
```

Массив верхнего уровня запрещён.

### 30.2. Размер

Максимальный размер обрабатываемого payload:

```text
65535 bytes
```

Слишком большой payload возвращает `422 validation_failed` с:

```json
{
  "details": {
    "validation_code": "payload_too_large"
  }
}
```

### 30.3. Неизвестные поля

Payload не может содержать поля, отсутствующие в схеме формы.

Пример ошибки:

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "validation_failed",
  "message": "Unknown form field: custom",
  "status": 422,
  "details": {
    "validation_code": "unknown_field",
    "field": "custom"
  }
}
```

### 30.4. Служебные поля

Следующие ключи удаляются до валидации и не сохраняются как данные заявки:

```text
_csrf
_a
_form
form
project
api_key
_api_key
_hp
_website
```

API-ключ должен передаваться заголовком, а не полем формы.

### 30.5. Файлы

Файлы запрещены.

Если запрос содержит загружаемый файл:

```http
422 Unprocessable Entity
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "validation_failed",
  "status": 422,
  "details": {
    "validation_code": "files_not_allowed",
    "field": "file",
    "expected_type": "file"
  }
}
```

### 30.6. Honeypot

Поддерживаются скрытые honeypot-поля:

```text
_hp
_website
```

Обычный пользователь должен оставлять их пустыми.

Если бот заполнил honeypot:

- запрос может получить обычный успешный ответ;
- заявка сохраняется со статусом `spam`;
- данные не должны считаться доверенными.

---

## 31. Нормализация типов формы

### 31.1. Boolean

Истинные значения:

```text
true
1
yes
on
да
иә
```

Ложные значения:

```text
false
0
no
off
нет
жоқ
```

Для required boolean допустимо только итоговое значение `true`.

### 31.2. Number

Поддерживаются:

```text
15
-15
15.5
.5
1e3
-2.5E-2
```

Ответ нормализуется в JSON number.

### 31.3. Integer

Разрешена целая десятичная запись:

```text
15
-15
+15
```

Ответ нормализуется в JSON integer.

### 31.4. Date

Формат:

```text
YYYY-MM-DD
```

Дата должна существовать в календаре.

### 31.5. Datetime

Форматы:

```text
YYYY-MM-DDTHH:MM
YYYY-MM-DDTHH:MM:SS
YYYY-MM-DD HH:MM
YYYY-MM-DD HH:MM:SS
```

### 31.6. URL

Разрешены только абсолютные URL со схемами:

```text
http
https
```

### 31.7. Telephone

Допускаются:

- необязательный `+`;
- цифры;
- пробелы;
- круглые скобки;
- точки;
- дефисы.

Количество цифр:

```text
5–20
```

### 31.8. JSON field

Поле `json` принимает:

- JSON object;
- JSON array;
- boolean;
- integer;
- number;
- `null`;
- JSON-строку, если отправляется через form-urlencoded.

Ограничения:

- максимальная глубина обработки — 8;
- не более 200 элементов в одном массиве или объекте;
- ключ объекта — не более 120 символов;
- строковое значение — не более 12000 символов.

---

## 32. Валидационные правила формы

Поле может содержать:

```text
required
default
min_length
max_length
min
max
regex
choices
```

### 32.1. Required

Если обязательное поле отсутствует или пустое:

```http
422 Unprocessable Entity
```

```json
{
  "error": "validation_failed",
  "details": {
    "validation_code": "required",
    "field": "email",
    "expected_type": "email"
  }
}
```

### 32.2. Длина

```text
min_length
max_length
```

### 32.3. Числовой диапазон

```text
min
max
```

### 32.4. Регулярное выражение

```text
regex
```

### 32.5. Разрешённые значения

```text
choices
```

Значение должно точно совпадать с одним из разрешённых вариантов.

### 32.6. Значение по умолчанию

Если поле отсутствует, CMS может применить:

```text
default
```

Для boolean без default используется `false`.

---

## 33. Успешная отправка формы

HTTP status:

```http
201 Created
```

Ответ:

```json
{
  "api_version": "1.4",
  "ok": true,
  "project": {
    "id": 1,
    "name": "Website",
    "slug": "website"
  },
  "lang": "ru",
  "submission_id": 158,
  "message": "Спасибо! Мы свяжемся с вами."
}
```

`submission_id` является внутренним идентификатором заявки. Клиент может использовать его для журнала или поддержки, но публичный endpoint чтения заявки отсутствует.

---

## 34. Rate limit формы

Ограничение применяется отдельно к форме и IP-адресу:

```text
20 заявок за 600 секунд
```

После превышения:

```http
429 Too Many Requests
Retry-After: 600
```

```json
{
  "api_version": "1.4",
  "ok": false,
  "error": "rate_limited",
  "message": "Too many form submissions",
  "status": 429,
  "details": {
    "retry_after": 600
  }
}
```

Клиент должен учитывать `Retry-After`.

---

## 35. HTTP-кэширование

Публичные GET-ответы могут содержать:

```http
ETag: "<hash>"
Last-Modified: Wed, 24 Jun 2026 13:30:00 GMT
Cache-Control: public, max-age=60, stale-while-revalidate=30
Vary: Accept-Encoding, Origin
```

Некоторые индексные и схемные публичные ответы могут использовать:

```http
Cache-Control: public, max-age=300, stale-while-revalidate=30
```

Приватные и административные ответы:

```http
Cache-Control: private, no-store
```

### 35.1. Условный запрос

Поддерживаются:

```http
If-None-Match
If-Modified-Since
```

Если ресурс не изменился:

```http
304 Not Modified
```

Тело отсутствует.

### 35.2. Отладочный заголовок

При включённом debug mode API может возвращать:

```http
X-CMS-Cache: HIT
X-CMS-Cache: MISS
```

Клиент не должен зависеть от этого заголовка.

### 35.3. Инвалидация

Кэш публичного API сбрасывается при изменении публично значимых таблиц контента, коллекций, полей, разделов, файлов и форм.

---

## 36. Заголовки ответа

API может возвращать:

```http
Content-Type: application/json; charset=utf-8
X-API-Version: 1.4
ETag: "<hash>"
Last-Modified: ...
Cache-Control: ...
Content-Language: ru
Vary: Accept-Encoding, Origin
X-Content-Type-Options: nosniff
Referrer-Policy: same-origin
Permissions-Policy: geolocation=(), camera=(), microphone=()
```

`Content-Language` устанавливается для конкретного языка и не устанавливается для `lang=all`.

---

## 37. Каталог ошибок

| HTTP | `error` | Значение |
|---:|---|---|
| `400` | `project_not_found` | Передан неизвестный проект |
| `400` | `group_required` | Не передан slug раздела |
| `400` | `collection_required` | Не передан slug коллекции |
| `400` | `entry_required` | Не передан slug записи |
| `400` | `form_required` | Не передан slug формы |
| `400` | `unsupported_language` | Язык не поддерживается проектом |
| `401` | `api_key_required` | Нужен валидный API-ключ ресурса |
| `403` | `auth_required` | Нужна административная сессия и право доступа |
| `403` | `cors_denied` | Origin запрещён настройками проекта |
| `404` | `api_not_found` | Неизвестный API endpoint |
| `404` | `project_not_found` | Проект не найден |
| `404` | `group_not_found` | Раздел не найден |
| `404` | `collection_not_found` | Коллекция не найдена или является вложенной |
| `404` | `entry_not_found` | Опубликованная запись не найдена |
| `404` | `form_not_found` | Форма не найдена |
| `405` | `method_not_allowed` | HTTP-метод не поддерживается |
| `410` | `form_inactive` | Форма отключена |
| `422` | `validation_failed` | Ошибка payload или значения поля |
| `422` | `empty_payload` | Payload формы пуст |
| `429` | `rate_limited` | Превышено ограничение отправки формы |
| `500` | `json_encode_failed` | Не удалось сформировать JSON |

Один и тот же код может использоваться с разными уточнениями в `details`.

---

## 38. Коды валидации формы

В `details.validation_code` могут встречаться:

```text
required
unknown_field
invalid_value
min_length
max_length
min
max
regex
choice
too_long
invalid_boolean
invalid_scalar
invalid_number
invalid_json
invalid_json_body
json_depth
json_items
json_key
payload_too_large
files_not_allowed
schema_missing
```

Клиент должен корректно обрабатывать неизвестный validation code, поскольку список может расширяться в совместимых версиях API.

---

## 39. Примеры авторизации

### 39.1. Приватная коллекция через Bearer

```bash
curl \
  "https://cms.example.com/?api=entries&project=website&c=internal-docs&lang=ru" \
  -H "Authorization: Bearer cms_xxxxxxxxxxxxxxxxx"
```

### 39.2. Приватная коллекция через X-API-Key

```bash
curl \
  "https://cms.example.com/?api=entries&project=website&c=internal-docs&lang=ru" \
  -H "X-API-Key: cms_xxxxxxxxxxxxxxxxx"
```

### 39.3. Приватный раздел

```bash
curl \
  "https://cms.example.com/?api=group&project=website&g=private-homepage&populate=all" \
  -H "Authorization: Bearer cms_xxxxxxxxxxxxxxxxx"
```

### 39.4. Приватная форма

```bash
curl -X POST \
  "https://cms.example.com/?form=private-request&project=website&lang=ru" \
  -H "Authorization: Bearer cms_xxxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"name":"Temirkhan","message":"Hello"}'
```

---

## 40. Примеры JavaScript

### 40.1. Получение записей

```js
const endpoint = new URL("https://cms.example.com/");

endpoint.search = new URLSearchParams({
  api: "entries",
  project: "website",
  c: "services",
  lang: "ru",
  page: "1",
  limit: "25",
  sort: "-id",
  populate: "category,image",
});

const response = await fetch(endpoint);

const payload = await response.json();

if (!response.ok || !payload.ok) {
  throw new Error(payload.message || `HTTP ${response.status}`);
}

console.log(payload.data);
```

### 40.2. Приватная коллекция

```js
const response = await fetch(
  "https://cms.example.com/?api=entries&project=website&c=internal",
  {
    headers: {
      Authorization: `Bearer ${apiKey}`,
      Accept: "application/json",
    },
  },
);

const payload = await response.json();

if (!response.ok || !payload.ok) {
  throw new Error(payload.message || `HTTP ${response.status}`);
}
```

Не размещайте приватный API-ключ в публичном frontend bundle, если данные действительно должны оставаться закрытыми. В таком случае запрос должен выполнять доверенный backend.

### 40.3. Отправка формы

```js
const response = await fetch(
  "https://cms.example.com/?form=contact&project=website&lang=ru",
  {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      name: "Temirkhan",
      email: "user@example.com",
      message: "Project request",
      _hp: "",
    }),
  },
);

const payload = await response.json();

if (!response.ok || !payload.ok) {
  if (payload.error === "validation_failed") {
    console.error(payload.details);
  }

  throw new Error(payload.message || `HTTP ${response.status}`);
}

console.log(payload.submission_id, payload.message);
```

---

## 41. Пример TypeScript-интерфейсов

Эти интерфейсы являются ориентиром для клиента и не входят в runtime CMS.

```ts
export interface ApiError {
  api_version: string;
  ok: false;
  error: string;
  message: string;
  status: number;
  details?: Record<string, unknown>;
}

export interface ProjectMeta {
  id: number;
  name: string;
  slug: string;
}

export interface CollectionMeta {
  id: number;
  name: string;
  slug: string;
  description: string;
  type: "single" | "multiple";
  access: "public" | "private";
  order: number;
}

export interface Entry<TData = Record<string, unknown>> {
  id: number;
  title: string;
  slug: string;
  status: "published";
  created_at: string;
  updated_at: string;
  parent_entry?: number;
  lang: string;
  i18n: boolean;
  translated?: boolean;
  data: TData;
}

export interface PaginationMeta {
  total: number;
  page: number;
  limit: number;
  pages: number;
  has_more: boolean;
  next_page: number | null;
  prev_page: number | null;
  type: "single" | "multiple";
  sort?: string;
}
```

---

## 42. Безопасность клиента

Клиент должен соблюдать следующие правила:

1. Не хранить секретный API-ключ в Git-репозитории.
2. Не встраивать приватный ключ в публичный JavaScript bundle.
3. Использовать HTTPS.
4. Проверять `response.ok`, HTTP status и поле `ok`.
5. Не показывать пользователю технические подробности без фильтрации.
6. Не считать `message` стабильным машинным идентификатором.
7. Ограничивать собственный timeout запросов.
8. Учитывать `429` и `Retry-After`.
9. Использовать ETag для экономии трафика, если данные запрашиваются часто.
10. Не рассчитывать на получение черновиков через публичный API.
11. Не передавать API-ключ в query string или payload формы.
12. Не доверять данным honeypot-заявок без проверки статуса в CMS.

---

## 43. Ограничения текущей версии

API `1.4` имеет следующие намеренные ограничения:

- Content API работает только на чтение;
- маршрутизация выполняется query-параметрами, а не REST path;
- публично возвращаются только опубликованные записи;
- вложенные коллекции нельзя запрашивать напрямую;
- максимальная глубина relation populate — один уровень;
- максимальный размер страницы — 100;
- публичные формы не принимают файлы;
- публичного endpoint’а чтения заявок нет;
- схемы коллекций требуют административную сессию;
- файловые endpoint’ы требуют административную сессию;
- API-ключи являются resource-scoped;
- cross-origin cookie credentials не являются частью публичного контракта;
- дата и время возвращаются в формате, используемом сервером CMS, без отдельного timezone offset;
- URL файлов может быть относительным;
- GraphQL не поддерживается;
- bulk API не поддерживается;
- webhook API не является публичным Content API.

---

## 44. Правила совместимости

### 44.1. Совместимые изменения в API `1.x`

Без изменения major-версии могут быть добавлены:

- новые необязательные поля ответа;
- новые endpoint’ы;
- новые значения `details`;
- новые коды валидации;
- новые HTTP-заголовки;
- новые типы полей;
- новые допустимые query-параметры;
- дополнительные элементы в массивах метаданных;
- уточнённый человекочитаемый `message`.

Клиент не должен завершаться с ошибкой из-за неизвестного необязательного поля.

### 44.2. Потенциально несовместимые изменения

Требуют новой major-версии или отдельного режима совместимости:

- удаление существующего endpoint’а;
- переименование обязательного поля;
- изменение базовой структуры успешного ответа;
- изменение смысла существующего `error`;
- изменение типа существующего поля;
- отмена поддержки существующего метода авторизации;
- изменение семантики `lang`, `populate`, `page`, `limit` или `filter`.

### 44.3. Рекомендация клиентам

Клиент должен проверять:

```text
X-API-Version
api_version
```

Для API `1.x` рекомендуется принимать любые версии, начинающиеся с:

```text
1.
```

если приложение не зависит от конкретной новой возможности.

---

## 45. Минимальный smoke test

### API index

```bash
curl -i "https://cms.example.com/?api=index&project=website"
```

Ожидается:

```text
HTTP 200
X-API-Version: 1.4
ok: true
```

### Публичная коллекция

```bash
curl -i "https://cms.example.com/?api=entries&project=website&c=services"
```

Ожидается:

```text
HTTP 200
data: array | object | null
```

### Несуществующая запись

```bash
curl -i "https://cms.example.com/?api=entry&project=website&c=services&s=missing"
```

Ожидается:

```text
HTTP 404
error: entry_not_found
```

### Схема формы

```bash
curl -i "https://cms.example.com/?form=contact&project=website&lang=ru"
```

Ожидается:

```text
HTTP 200
form.fields: array
files: false
```

### Отправка формы

```bash
curl -i -X POST \
  "https://cms.example.com/?form=contact&project=website&lang=ru" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","message":"Test submission"}'
```

Ожидается:

```text
HTTP 201
submission_id: integer
```

---

## 46. Итоговая таблица endpoint’ов

| Endpoint | Методы | Доступ | Назначение |
|---|---|---|---|
| `?api=index` | GET, HEAD, OPTIONS | public | Возможности и примеры маршрутов |
| `?api=entries&c=...` | GET, HEAD, OPTIONS | public или resource key | Список записей коллекции |
| `?api=entry&c=...&s=...` | GET, HEAD, OPTIONS | public или resource key | Одна опубликованная запись |
| `?api=group&g=...` | GET, HEAD, OPTIONS | public или resource key | Раздел с несколькими коллекциями |
| `?api=collections` | GET, HEAD, OPTIONS | CMS session | Метаданные коллекций |
| `?api=groups` | GET, HEAD, OPTIONS | CMS session | Метаданные разделов |
| `?api=fields&c=...` | GET, HEAD, OPTIONS | CMS session | Поля коллекции |
| `?api=schema&c=...` | GET, HEAD, OPTIONS | CMS session | Схема коллекции |
| `?api=files` | GET, HEAD, OPTIONS | CMS session | Активные файлы |
| `?api=files-trash` | GET, HEAD, OPTIONS | CMS session | Корзина файлов |
| `?form=...` | GET, HEAD, OPTIONS | public или form key | Схема формы |
| `?form=...` | POST, OPTIONS | public или form key | Новая заявка |

---

## 47. Source of truth

При расхождении между этим документом и фактическим поведением установленной версии CMS источником истины является код той версии PHP-файла, которая развёрнута на сервере.

Перед обновлением production-интеграции рекомендуется:

1. проверить `api_version`;
2. выполнить smoke tests;
3. проверить приватные ключи и CORS;
4. проверить `lang=default` и используемые языки;
5. проверить relation-поля с `populate`;
6. проверить схему и отправку каждой публичной формы.
