<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\Invoice;

class InvoiceController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'invoice'; 
        $table = new Invoice($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.AUCTION_NAME.': All Invoices';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}