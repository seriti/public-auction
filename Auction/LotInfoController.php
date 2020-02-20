<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Secure;

use App\Auction\LotInfo;

class LotInfoController
{
    protected $container;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $lot_id = Secure::clean('integer',$_GET['id']);

        $lot_orders = new LotInfo($this->container->mysql,$this->container,$lot_id);
        
        $html = $lot_orders->show();

        $template['title'] = MODULE_LOGO.AUCTION_NAME.': Lot['.$lot_id.'] Information';
        $template['html'] = $html;

        //$template['javascript'] = $dashboard->getJavascript();

        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}