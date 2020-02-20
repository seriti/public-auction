<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use App\Auction\AccountDashboard;

class AccountDashboardController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $dashboard = new AccountDashboard($this->container->mysql,$this->container);
        $dashboard->setup();

        $html = $dashboard->viewBlocks();

        $template['title'] = 'Your Account';
        $template['html'] = $html;

        //$template['javascript'] = $dashboard->getJavascript();

        return $this->container->view->render($response,'public.php',$template);
    }
}