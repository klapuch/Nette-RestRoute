# REST route for [Nette Framework](http://nette.org)
[![Build Status](https://travis-ci.org/klapuch/Nette-RestRoute.svg?branch=master)](https://travis-ci.org/klapuch/Nette-RestRoute)

Route automatically maps CRUD to Presenters and actions in the defined module.
And creates parameters which are accessible in Presenter.  

- format
- id (autodetected)
- associations (an array with associations)
- data (raw data from the request)
- query (an array of items from the query string)

## Format detection:
Variable ```$format``` is detected from HTTP header ```Accept```. If header is not present Route try detect format from the URL (```.../foo.json```). If no format is in the URL Route use a default format ```json```.

## Installation:
The best way to install Nette-RestRoute is using  [Composer](http://getcomposer.org/):

```sh
$ composer require klapuch/nette-rest-route
```

## Usage:

```php
use Klapuch\RestRoute;

// $router is an instance of Nette\Application\Routers\RouteList  

// No parameters needed. Presenter name will be generated.
$router[] = new RestRoute;

// With module.
$router[] = new RestRoute('Api');

// With module and xml as a default format.
$router[] = new RestRoute('Api', 'xml');
```


First parameter is a name of the module where the route will sends an Request. URL prefix will be generated. See examples.
####Examples:
 
```
NULL      => /<generated presenter name>
'Api'     => /api/<generated presenter name>
'My:Api'  => /my/api/<generated presenter name>
...
```

Second parameter is default format. By default the default format is ```json```.
RestRoute support only 2 formats:  

- json *(default)*  
- xml

## Examples

### Read all:
**URL:** ```/api/users``` &rarr; ```\ApiModule\UsersPresenter::actionReadAll```   
**HTTP HEADER Accept:** ```application/json```  
**Method:** GET  
**Request body:** Empty  
**Params:**  

```
format = json
associations = array(0)
data = ""
query = array(0)
```

> Flag ```readAll``` was dropped and `Route` automatically generate action `readAll` if no Resource ID was not found in the URL.


---
### Read with resource ID
**URL:** ```/api/users/123``` &rarr; ```\ApiModule\UsersPresenter::actionRead```  
**HTTP HEADER Accept:** ```application/json```  
**Method:** GET  
**Request body:** Empty  
**Params:**  

```
format = json
id = 123
associations = array(0)
data = ""
query = array(0)
```
---
### Query params:
**URL:** ```/api/users?foo=bar&page=1``` &rarr; ```\ApiModule\UsersPresenter::actionReadAll```  
**HTTP HEADER Accept:** ```application/json```  
**Method:** GET  
**Request body:** Empty  
**Params:**  

```
format = json
associations = array(0)
data = ""
query = array(
	foo => "bar"
	page => 1
)
```
---
### Create:
**URL:** ```/api/users``` &rarr; ```\ApiModule\UsersPresenter::actionCreate```  
**HTTP HEADER Accept:** ```application/json```  
**Method:** POST  
**Request body:**  

```json
{
	"foo": "bar",
	"nested": {
		"foo": "bar"	
	}
}
```
  
**Params:**  

```
format = json
associations = array(0)
data = {"foo": "bar", "nested": {"foo": "bar"}}
query = array(0)
```
---
### Update:
**URL:** ```/api/users/123``` &rarr; ```\ApiModule\UsersPresenter::actionUpdate```  
**HTTP HEADER Accept:** ```application/json```    
**Method:** PUT  
**Request body:**  

```json
{
	"foo": "bar",
	"nested": {
		"foo": "bar"	
	}
}
```
  
**Params:**  

```
format = json
id = 123
associations = array(0)
data = {"foo": "bar", "nested": {"foo": "bar"}}
query = array(0)
```

---
### Partial update:
**URL:** ```/api/users/123``` &rarr; ```\ApiModule\UsersPresenter::actionPartialUpdate```  
**HTTP HEADER Accept:** ```application/json```    
**Method:** PATCH  
**Request body:**  

```json
{
	"foo": "bar",
	"nested": {
		"foo": "bar"	
	}
}
```
  
**Params:**  

```
format = json
id = 123
associations = array(0)
data = {"foo": "bar", "nested": {"foo": "bar"}}
query = array(0)
```

---
### Delete:
**URL:** ```/api/users/123``` &rarr; ```\ApiModule\UsersPresenter::actionDelete```  
**HTTP HEADER Accept:** ```application/json```   
**Method:** DELETE  
**Request body:** Empty  
**Params:**  

```
format = json
id = 123
associations = array(0)
data = ""
query = array(0)
```
---
### Options:
For more about OPTIONS documentation see [w3.org](http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.2).

**URL:** ```/api/users``` &rarr; ```\ApiModule\UsersPresenter::actionOptions```  
**HTTP HEADER Accept:** ```application/json```   
**Method:** OPTIONS  
**Request body:** Empty  
**Params:**  

```
format = json
associations = array(0)
data = ""
query = array(0)
```
---
### Associations:
Last item (pair) before .<format> is main resource. Everything what is before the last item are associations ([apigee.com](http://apigee.com/about/)).

**URL:** ```/api/users/1/comments``` &rarr; ```\ApiModule\CommentsPresenter::actionRead|actionCreate|actionUpdate|actionDelete```  
**HTTP HEADER Accept:** ```application/json```  
**Method:** GET, POST, PUT, DELETE  
**Request body:** Empty  
**Params:**  

```
format = json
associations = array(
	users => 1
)
data = ""
query = array(0)
```

**URL:** ```/api/users/123/comments/456``` &rarr; ```\ApiModule\CommentsPresenter::actionRead|actionCreate|actionUpdate|actionDelete```  
**HTTP HEADER Accept:** ```application/json```  
**Method:** GET, POST, PUT, DELETE  
**Request body:** Empty  
**Params:**  

```
format = json
id = 456
associations = array(
	users => 123
)
data = ""
query = array(0)
```

**URL:** ```/api/users/1/blogs/2/comments``` &rarr; ```\ApiModule\CommentsPresenter::actionRead|actionCreate|actionUpdate|actionDelete```  
**HTTP HEADER Accept:** ```application/json```  
**Method:** GET, POST, PUT, DELETE  
**Request body:** Empty  
**Params:**  

```
format = json
id = 1
associations = array(
	users => 1
	blogs => 2
)
data = ""
query = array(0)
```

---
### File uploads:
RestRoute reads standard [PHP input](https://github.com/newPOPE/Nette-RestRoute/blob/master/src/RestRoute.php#L202) and data puts to `$data` param in action. This is fit for one file upload or upload with chunks because it is a RAW data.

For multiple file upload RestRoute just set files when creates [`\Nette\Application\Request`](https://github.com/newPOPE/Nette-RestRoute/blob/master/src/RestRoute.php#L125). In presenter just inject `\Nette\Application\Request` service and use these files.

```php
class FooPresenter {
  /** @var \Nette\Application\Request @inject */
  public $request;

  public function actionCreate () {
    $files = $this->request->getFiles();
  }
}
``` 

##Overriding methods PUT, PATCH, DELETE

Methods ```PUT```, ```PATCH``` and ```DELETE``` can be overriden via:  

### HTTP header ```X-HTTP-Method-Override```
Example:

```
X-HTTP-Method-Override: <PUT|PATCH|DELETE>
```

### Query param ```__method```
Example:

```
?__method=<PUT|PATCH|DELETE>
```

##Development

RestRoute is developed in [Docker](https://docker.com) container via `docker-compose` command.

Example:  
```sh
$ docker-compose run --rm default install  # install deps via composer
$ docker-compose run --rm default  # runs tests in container
```

Attach to container:  
```sh
$ docker-compose run --rm default bash # runs bash in container and attach tty
```
