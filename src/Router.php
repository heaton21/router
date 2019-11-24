<?php

namespace Heaton\Routing;

class Router
{
	private $requestMethod;
	private $requestUri;
	private $arrayUrl = [];
	private $namespace;
	private $middlewareNamespace;
	private $placeholders = [
		':slug' => '([^\/]+)',
		':id'  => '([0-9]+)',
		':any'  => '(.+)'
	];


	public function __construct()
	{
		$this->requestUri = strpbrk($_SERVER['REQUEST_URI'], '?') ? strstr($_SERVER['REQUEST_URI'], '?', true) : $_SERVER['REQUEST_URI'];
		$this->requestMethod = $_SERVER['REQUEST_METHOD'];		
	}

	public function get(string $url, string $handler, array $middleware = [])
	{
		$this->arrayUrl[$url] = [
			'method' => 'GET',
			'url' => $url,
			'controller' => $this->getContoller($handler),
			'action' => $this->getAction($handler),
			'middleware' => $middleware 
		];
	}

	public function post(string $url, string $handler, array $middleware = [])
	{
		$this->arrayUrl[$url] = [
			'method' => 'POST', 
			'url' => $url, 
			'controller' => $this->getContoller($handler),
			'action' => $this->getAction($handler),
			'middleware' => $middleware 
		];
	}

	public function handleRoute()
	{
		$find    = array_keys($this->placeholders);
		$replace = array_values($this->placeholders);
		foreach ($this->arrayUrl as $route => $handler) {
			if (strpos($route, ':') !== false) {
				$route = str_replace($find, $replace, $route);
			}

			if (preg_match('#^' . $route . '$#', $this->requestUri, $matches)) {
				$params = array_slice($matches, 1);
				$this->arrayUrl[$matches[0]] = [
					'method' => $handler['method'], 
					'url' => $handler['url'], 
					'controller' => $handler['controller'],
					'action' => $handler['action'],
					'middleware' => $handler['middleware'] 
				];
			}
		}
		$this->middleware($this->arrayUrl[$this->requestUri]['middleware']);
		$this->notFound($this->requestUri);
		$this->methodValidate($this->arrayUrl[$this->requestUri]['method']);
		$controller = $this->arrayUrl[$this->requestUri]['controller'];
		$action = $this->arrayUrl[$this->requestUri]['action'];
		$obj = $this->namespace . $controller;
		$this->notFoundController($obj);
		$obj = new $obj;
		if (! method_exists($obj, $action)) {
			throw new \Exception(
				"Method '{$controller}::{$action}' not found"
			);
		}
		return call_user_func_array(array($obj, $action), $params);
	}

	public function setNamespace($namespace)
	{
		$this->namespace = $namespace;
		return $this;
	}

	public function setMiddlewareNamespace($middleware)
	{
		$this->middlewareNamespace = $middleware;
		return $this;
	}

	private function getContoller($handler)
	{
		$controller = explode('@', $handler);
		return $controller[0] ?? null;
	}

	private function getAction($handler)
	{
		$action = explode('@', $handler);
		return $action[1] ?? null;
	}

	private function notFound($index)
	{
		if (!array_key_exists($index, $this->arrayUrl)) {
			http_response_code(404);
			die('404');
		}
	}

	private function methodValidate($method)
	{
		if ($this->requestMethod !== $method) {
			throw new \Exception("Requst method does not match");
		}
	}

	private function notFoundController($controller)
	{
		if (!class_exists($controller)) {
			throw new \Exception("Controller class '{$controller}' not found");
		}
	}

	private function middleware($middleware)
	{
		if ($middleware) {
			foreach ($middleware as $m) {
				$obj = $this->middlewareNamespace . $m;
				$obj = new $obj;
				return $obj->handle() ? true : $obj->redirectTo();
			}
		}
		
	}

}