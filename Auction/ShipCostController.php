<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\ShipCost;

class ShipCostController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'ship_cost'; 
        $table = new ShipCost($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.': Shipping costs';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}