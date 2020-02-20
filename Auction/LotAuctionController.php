<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\LotAuction;

class LotAuctionController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'lot'; 
        $table = new LotAuction($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['title'] = MODULE_LOGO.AUCTION_NAME.': Lots';
        $template['html'] = $html;
                
        return $this->container->view->render($response,'admin.php',$template);
    }
}