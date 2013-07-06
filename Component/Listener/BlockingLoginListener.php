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

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use CCDNUser\SecurityBundle\Component\Authentication\Tracker\LoginFailureTracker;

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
class BlockingLoginListener
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
     * @var \CCDNUser\SecurityBundle\Component\Authentication\Tracker\LoginFailureTracker $loginFailureTracker
     */
    protected $loginFailureTracker;

    /**
     *
     * @access protected
     * @var bool $enableShield
     */
    protected $enableShield;

    /**
     *
     * @access protected
     * @var array $blockRoutes
     */
    protected $blockRoutes;

    /**
     *
     * @access protected
     * @var int $blockForMinutes
     */
    protected $blockForMinutes;

    /**
     *
     * @access protected
     * @var int $limitBeforeRecover
     */
    protected $limitBeforeRecover;

    /**
     *
     * @access protected
     * @var int $limitBeforeHttp500
     */
    protected $limitBeforeHttp500;

    /**
     *
     * @access protected
     * @var string $recoverRoute
     */
    protected $recoverRoute;

    /**
     *
     * @access protected
     * @var array $recoverRouteParams
     */
    protected $recoverRouteParams;

    /**
     *
     * @access protected
     * @var string $loginRoute
     */
    protected $loginRoute;

    /**
     *
     * @access public
     * @param \Symfony\Bundle\FrameworkBundle\Routing\Router                                $router
     * @param \CCDNUser\SecurityBundle\Component\Authentication\Tracker\LoginFailureTracker $loginFailureTracker
     * @param bool                                                                          $enableShield
     * @param array                                                                         $blockRoutes
     * @param int                                                                           $blockForMinutes
     * @param int                                                                           $limitBeforeRecoverAccount
     * @param int                                                                           $limitBeforeHttp500
     * @param string                                                                        $recoverRoute
     * @param array                                                                         $recoverRouteParams
     * @param string                                                                        $loginRoute
     */
    public function __construct(Router $router, LoginFailureTracker $loginFailureTracker, $enableShield, $blockRoutes, $blockForMinutes, $limitBeforeRecoverAccount, $limitBeforeHttp500, $recoverRoute, $recoverRouteParams, $loginRoute)
    {
        $this->router = $router;
        $this->loginFailureTracker = $loginFailureTracker;
        $this->enableShield = $enableShield;
        $this->blockRoutes = $blockRoutes;
        $this->blockForMinutes = $blockForMinutes;
        $this->limitBeforeRecoverAccount = $limitBeforeRecoverAccount;
        $this->limitBeforeHttp500 = $limitBeforeHttp500;
        $this->recoverRoute = $recoverRoute;
        $this->recoverRouteParams = $recoverRouteParams;
        $this->loginRoute = $loginRoute;
    }

    /**
     * If you have failed to login too many times, a log of this will be present
     * in your session and the databse (incase session is dropped the record remains).
     *
     * @access public
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if ($this->enableShield) {
            // Abort if we are dealing with some symfony2 internal requests.
            if ($event->getRequestType() !== \Symfony\Component\HttpKernel\HttpKernel::MASTER_REQUEST) {
                return;
            }

            // Get the route from the request object.
            $request = $event->getRequest();

            $route = $request->get('_route');

            // Abort if the route is not a login route.
            if (! in_array($route, $this->blockRoutes)) {
                return;
            }

            // Set a limit on how far back we want to look at failed login attempts.
            $timeLimit = new \DateTime('-' . $this->blockForMinutes . ' minutes');

            // Get session and check if it has any entries of failed logins.
            $session = $request->getSession();

            $ipAddress = $request->getClientIp();

            // Get number of failed login attempts.
            $attempts = $this->loginFailureTracker->getAttempts($session, $ipAddress);

            if (count($attempts) > ($this->limitBeforeRecoverAccount -1)) {
                // Only continue incrementing if on the account recovery page
                // because the counter won't increase from the loginFailureHandler.
                if ($route == $this->loginRoute) {
                    $this->loginFailureTracker->addAttempt($session, $ipAddress, '');

                    $attempts = $this->loginFailureTracker->getAttempts($session, $ipAddress);
                }

                // Block the page when continuing to bypass the block.
                if (count($attempts) < ($this->limitBeforeHttp500 + 1)) {

                    $event->setResponse(new RedirectResponse($this->router->generate($this->recoverRoute, $this->recoverRouteParams)));

                    return;
                }

                // In severe cases, block for a while.
                //	$this->container->get('kernel')->shutdown();
                throw new HttpException(500, 'flood control - login blocked');
            }
        }

        return;
    }
}
