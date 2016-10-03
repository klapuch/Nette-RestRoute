<?php
namespace Klapuch;

use Klapuch\Support\Inflector;
use Nette\Application\IRouter;
use Nette\InvalidArgumentException;
use Nette\Application\Request;
use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\InvalidStateException;
use Nette\Object;
use Nette\Utils\Strings;
use Nette\Utils\Validators;

/**
 * @author Adam Štipák <adam.stipak@gmail.com>
 * @author Dominik Klapuch <klapuchdominik@gmail.com>
 */
class RestRoute extends Object implements IRouter {
	const HTTP_HEADER_OVERRIDE = 'X-HTTP-Method-Override';
	const QUERY_PARAM_OVERRIDE = '__method';
	/** @var string */
	protected $path;
	/** @var string */
	protected $module;
	/** @var array */
	protected $formats = [
		'json' => 'application/json',
		'xml' => 'application/xml',
	];
	/** @var string */
	protected $defaultFormat;

	public function __construct($module = null, $defaultFormat = 'json') {
		if(!isset($this->formats[$defaultFormat])) {
			throw new InvalidArgumentException(
				sprintf("Format '%s' is not allowed.", $defaultFormat)
			);
		}
		$this->module = $module;
		$this->defaultFormat = $defaultFormat;
	}

	/**
	 * @return string
	 */
	public function getDefaultFormat() {
		return $this->defaultFormat;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		$path = implode('/', explode(':', $this->module));
		$this->path = Strings::lower($path);
		return (string)$this->path;
	}

	/**
	 * Maps HTTP request to a Request object.
	 * @param \Nette\Http\IRequest $httpRequest
	 * @return \Nette\Application\Request|NULL
	 */
	public function match(IRequest $httpRequest) {
		$url = $httpRequest->getUrl();
		$basePath = Strings::replace($url->getBasePath(), '/\//', '\/');
		$cleanPath = Strings::replace($url->getPath(), "/^{$basePath}/");
		$path = Strings::replace($this->getPath(), '/\//', '\/');
		$pathRexExp = empty($path) ? "/^.+$/" : "/^{$path}\/.*$/";
		if(!Strings::match($cleanPath, $pathRexExp)) {
			return null;
		}
		$cleanPath = Strings::replace($cleanPath, '/^' . $path . '\//');
		$params = [];
		$path = $cleanPath;
		$params['action'] = $this->detectAction($httpRequest);
		$frags = explode('/', $path);
		// Resource ID.
		if(count($frags) % 2 === 0) {
			$params['id'] = array_pop($frags);
		} elseif($params['action'] == 'read') {
			$params['action'] = 'readAll';
		}
		$presenterName = Inflector::studlyCase(array_pop($frags));
		// Allow to use URLs like domain.tld/presenter.format.
		$formats = join('|', array_keys($this->formats));
		if(Strings::match($presenterName, "/.+\.({$formats})$/")) {
			list($presenterName) = explode('.', $presenterName);
		}
		// Associations.
		$assoc = [];
		if(count($frags) > 0 && count($frags) % 2 === 0) {
			foreach($frags as $k => $f) {
				if($k % 2 !== 0) continue;
				$assoc[$f] = $frags[$k + 1];
			}
		}
		$params['format'] = $this->detectFormat($httpRequest);
		$params['associations'] = $assoc;
		$params['data'] = $this->readInput();
		$params['query'] = $httpRequest->getQuery();
		$params['query'] = array_map(
			function($parameter) {
				return is_numeric($parameter) ? (int)$parameter : $parameter;
			},
			$params['query']
		);
		$presenterName = empty($this->module) ? $presenterName : $this->module . ':' . $presenterName;
		return new Request(
			$presenterName,
			$httpRequest->getMethod(),
			$params,
			[],
			$httpRequest->getFiles()
		);
	}

	protected function detectAction(IRequest $request) {
		$method = $this->detectMethod($request);
		switch($method) {
			case 'GET':
				return 'read';
			case 'POST':
				return 'create';
			case 'PATCH':
				return 'partialUpdate';
			case 'PUT':
				return 'update';
			case 'DELETE':
				return 'delete';
			case 'OPTIONS':
				return 'options';
			default:
				throw new InvalidStateException(
					'Method ' . $method . ' is not allowed.'
				);
		}
	}

	/**
	 * @param IRequest $request
	 * @return string
	 */
	protected function detectMethod(IRequest $request) {
		$requestMethod = $request->getMethod();
		if($requestMethod !== 'POST') {
			return $request->getMethod();
		}
		$method = $request->getHeader(self::HTTP_HEADER_OVERRIDE);
		if(isset($method)) {
			return Strings::upper($method);
		}
		$method = $request->getQuery(self::QUERY_PARAM_OVERRIDE);
		if(isset($method)) {
			return Strings::upper($method);
		}
		return $requestMethod;
	}

	/**
	 * @param \Nette\Http\IRequest $request
	 * @return string
	 */
	private function detectFormat(IRequest $request) {
		$header = $request->getHeader(
			'Accept'
		); // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
		foreach($this->formats as $format => $fullFormatName) {
			$fullFormatName = Strings::replace($fullFormatName, '/\//', '\/');
			if(Strings::match($header, "/{$fullFormatName}/")) {
				return $format;
			}
		}
		// Try retrieve fallback from URL.
		$path = $request->getUrl()->getPath();
		$formats = array_keys($this->formats);
		$formats = implode('|', $formats);
		if(Strings::match($path, "/\.({$formats})$/")) {
			list($path, $format) = explode('.', $path);
			return $format;
		}
		return $this->defaultFormat;
	}

	/**
	 * @return array|null
	 */
	protected function readInput() {
		return file_get_contents('php://input');
	}

	/**
	 * Constructs absolute URL from Request object.
	 * @param \Nette\Application\Request $appRequest
	 * @param \Nette\Http\Url $refUrl
	 * @throws \Nette\InvalidStateException
	 * @return string|NULL
	 */
	public function constructUrl(Request $appRequest, Url $refUrl) {
		// Module prefix not match.
		if($this->module && !Strings::startsWith(
				$appRequest->getPresenterName(),
				$this->module
			)
		) {
			return null;
		}
		$parameters = $appRequest->getParameters();
		$url = $refUrl->getBaseUrl();
		$urlStack = [];
		// Module prefix.
		$moduleFrags = explode(":", $appRequest->getPresenterName());
		$moduleFrags = array_map(
			'\Klapuch\Support\Inflector::spinalCase',
			$moduleFrags
		);
		$resourceName = array_pop($moduleFrags);
		$urlStack += $moduleFrags;
		// Associations.
		if(isset($parameters['associations']) && Validators::is(
				$parameters['associations'],
				'array'
			)
		) {
			$associations = $parameters['associations'];
			unset($parameters['associations']);
			foreach($associations as $key => $value) {
				$urlStack[] = $key;
				$urlStack[] = $value;
			}
		}
		// Resource.
		$urlStack[] = $resourceName;
		// Id.
		if(isset($parameters['id']) && Validators::is(
				$parameters['id'],
				'scalar'
			)
		) {
			$urlStack[] = $parameters['id'];
			unset($parameters['id']);
		}
		$url = $url . implode('/', $urlStack);
		$sep = ini_get('arg_separator.input');
		if(isset($parameters['query'])) {
			$query = http_build_query(
				$parameters['query'],
				'',
				$sep ? $sep[0] : '&'
			);
			if($query != '') {
				$url .= '?' . $query;
			}
		}
		return $url;
	}
}
