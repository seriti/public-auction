<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Template;

use App\Auction\Bid;
use App\Auction\Helpers;

class BidController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $db = $this->container->mysql;
        $user = $this->container->user;

        $table_name = TABLE_PREFIX.'order_item'; 
        $table = new Bid($this->container->mysql,$this->container,$table_name);

        $param = [];
        $table->setup($param);
        $html = $table->processTable();
            
        $template['title'] = MODULE_LOGO.AUCTION_NAME.': All Lot bids';
        $template['html'] = $html;
        //$template['javascript'] = $dashboard->getJavascript();
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}