<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use App\Auction\OrderOrphan;

class OrderOrphanController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'order'; 
        $table = new OrderOrphan($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
            
        $template['title'] = MODULE_LOGO.AUCTION_NAME.': UN-checked out user '.MODULE_AUCTION['labels']['order'].'s';
        $template['html'] = $html;
        //$template['javascript'] = $dashboard->getJavascript();
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}