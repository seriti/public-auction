<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\PayOption;

class PayOptionController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'pay_option'; 
        $table = new PayOption($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.': Order payment options';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}