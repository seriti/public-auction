<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\ShipLocation;

class ShipLocationController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'ship_location'; 
        $table = new ShipLocation($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.': Shipping locations';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}