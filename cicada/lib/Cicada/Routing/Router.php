<?php

namespace Cicada\Routing;

use Cicada\Auth\LoginAction;
use Cicada\Responses\EchoResponse;
use Cicada\Session;
use Exception;
use ReflectionFunction;

class Router {
    private $routeMap = array();
    private $protectors = array();
    private static $instance;

    private function __construct() {
    }

    public function addRoute(Route $route) {
        array_push($this->routeMap, $route);
    }

    public function addProtector(Protector $protector) {
        array_push($this->protectors, $protector);
    }

    public function route($url) {

        /** @var $protector Protector */
        foreach ($this->protectors as $protector) {

            if ($protector->matches($url)) {
                $resultFunction = $this->protect($protector);

                if ($resultFunction != null) {
                    return $resultFunction;
                }
            }
        }

        /** @var $route Route */
        foreach ($this->routeMap as $route) {
            if ($route->matches($url)) {
                $route->validatePost();

                return function() use ($route) {
                    $action = $route->getAction();

                    $matches = $route->getMatches();
                    foreach ($matches as $key => $value) {
                        if (is_int($key)) {
                            unset($matches[$key]);
                        }
                    }

                    $function = new ReflectionFunction($action);
                    return $function->invokeArgs($matches);
                };
            }
        }
        throw new Exception("No match for route");
    }

    /**
     * Returns an action function, if the user is not allowed to proceed,
     * or null, if the user is allowed to proceed.
     *
     * @param Protector $protector
     * @return callable|mixed|null
     */
    public function protect(Protector $protector) {
        $user = Session::getInstance()->get(LoginAction::CICADA_USER);

        if ($user != null) {
            if ($protector->isUserAllowed($user)) {
                return null;
            }
        }

        $onFail = $protector->getOnFail();
        if($onFail != null) {
            return $onFail;
        } else {
            return function() {
                $echo = new EchoResponse("Unauthorized");
                $echo->addHeader("HTTP/1.1 401 Unauthorized");
                return $echo;
            };
        }
    }


    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Router();
        }
        return self::$instance;
    }
}