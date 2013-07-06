<?php

/*
 * This file is part of the CCDNUser SecurityBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/>
 *
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCDNUser\SecurityBundle\Component\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use CCDNUser\SecurityBundle\Component\Listener\Chain\RouteRefererIgnoreChain;

/**
 *
 * @category CCDNUser
 * @package  SecurityBundle
 *
 * @author   Reece Fowell <reece@codeconsortium.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @version  Release: 2.0
 * @link     https://github.com/codeconsortium/CCDNUserSecurityBundle
 *
 */
class RouteRefererListener
{
    /**
     *
     * @access protected
     * @var \Symfony\Bundle\FrameworkBundle\Routing\Router $router
     */
    protected $router;

    /**
     *
     * @access protected
     * @var \CCDNUser\SecurityBundle\Component\Listener\Chain\RouteRefererIgnoreChain $routeIgnoreChain
     */
    protected $routeIgnoreChain;

    /**
     *
     * @access protected
     * @var array $routeIgnoreList
     */
    protected $routeIgnoreList;

    /**
     * @param \Symfony\Bundle\FrameworkBundle\Routing\Router                            $router
     * @param \CCDNUser\SecurityBundle\Component\Listener\Chain\RouteRefererIgnoreChain $routeIgnoreChain
     * @param array                                                                     $routeIgnoreList
     */
    public function __construct(Router $router, RouteRefererIgnoreChain $routeIgnoreChain, $routeIgnoreList)
    {
        $this->router = $router;
        $this->routeIgnoreChain = $routeIgnoreChain;
        $this->routeIgnoreList = $routeIgnoreList;
    }

    /**
     *
     * Log all routes (except login/logout/registration etc) so that you can be
     * Redirected back to your original location once you login successfully.
     *
     * @access public
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        // Abort if we are dealing with some symfony2 internal requests.
        if ($event->getRequestType() !== \Symfony\Component\HttpKernel\HttpKernel::MASTER_REQUEST) {
            return;
        }

        // Get the route from the request object.
        $request = $event->getRequest();

        $route = $request->get('_route');

        // Get the list of routes we must ignore.
        $logIgnore = $this->routeIgnoreList;

        $routeIgnoreChain = $this->routeIgnoreChain;

        $ignorable = is_array($routeIgnoreChain) ? array_merge($routeIgnoreChain, $logIgnore) : $logIgnore;

        // Abort if the route is ignorable.
        foreach ($ignorable as $ignore) {
            if ($route == $ignore['route']) {
                return;
            }
        }

        // Check for any internal routes.
        if ($route[0] == '_') {
            return;
        }

        // Get the session and assign it the url we are at presently.
        $session = $request->getSession();

        $script = ($request->getScriptName() == $request->getBasePath() . '/app_dev.php') ? $request->getScriptName() : $request->getBasePath();

        $session->set('referer', $script . $request->getPathInfo());
    }
}
