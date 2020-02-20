<?php 
namespace App\Auction;

use Psr\Container\ContainerInterface;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\URL_CLEAN;
use Seriti\Tools\Secure;
use Seriti\Tools\Menu;

class ConfigAccount
{
    
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        $user = $this->container->user;
        $menu = $this->container->menu;

        //configure user access
        //default access levels=['GOD','ADMIN','USER','VIEW']
        $minimum_level = 'USER';

        $redirect_route = 'login';
        $zone = 'PUBLIC';
        $valid = $user->checkAccessRights($zone);

        if($valid) {
            $this->container->mysql->setAuditUserId($user->getId());
            Secure::checkReferer(BASE_URL);
            //$user->level must be >= minimum level
            $valid = $user->checkUserAccess($minimum_level);
            //delete user session,tokens,cookies
            if(!$valid) $user->manageUserAction('LOGOUT');
        } else {
            //die ('WTF-not valid');
            return $response->withRedirect('/'.$redirect_route);
        }

        $routes = ['dashboard'=>'Dashboard','profile'=>'Profile','order'=>'Orders','invoice'=>'Invoices'];
        $submenu_html = $menu->buildNav($routes,URL_CLEAN_LAST);
        $this->container->view->addAttribute('sub_menu',$submenu_html);

        $response = $next($request, $response);
        
        return $response;
    }
}