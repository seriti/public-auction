<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use App\Auction\Order;

class OrderController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'order'; 
        $table = new Order($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
            
        $template['title'] = MODULE_LOGO.AUCTION_NAME.': Lot '.AUCTION_ORDER_NAME.'s';
        $template['html'] = $html;
        //$template['javascript'] = $dashboard->getJavascript();
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}