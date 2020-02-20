<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\IndexTerm;

class IndexTermController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'index_term'; 
        $table = new IndexTerm($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All auctions: Lot index terms';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}