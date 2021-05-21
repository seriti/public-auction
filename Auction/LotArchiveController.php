<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\LotArchive;

class LotArchiveController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'lot'; 
        $table = new LotArchive($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['title'] = MODULE_LOGO.': ALL auctions archived lots';
        $template['html'] = $html;
                
        return $this->container->view->render($response,'admin.php',$template);
    }
}