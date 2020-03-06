<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Secure;

use App\Auction\AccountOrderItem;
use App\Auction\Helpers;

class AccountOrderItemController
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

        //NB: TABLE_PREFIX constant not applicable as not called within admin module
        //$table_prefix = TABLE_PREFIX_AUCTION;
        $module = $this->container->config->get('module','auction');
        $table_prefix = $module['table_prefix'];
               
        $table_name = $table_prefix.'order_item'; 
        $table = new AccountOrderItem($this->container->mysql,$this->container,$table_name);

        //need order id to check order/auction status 
        if(!isset($_GET['mode'])) {
            $order_id = Secure::clean('basic',$_GET['id']); 
        } else {
            $order_id = $table->getCache('master_id'); 
        }    


        $param = [];
        $param['table_prefix'] = $table_prefix;
        $param['order_id'] = $order_id;
        $table->setup($param);
        $html = $table->processTable();
            
        $template['title'] = '';
        $template['html'] = $html;
        //$template['javascript'] = $dashboard->getJavascript();
        
        return $this->container->view->render($response,'public_popup.php',$template);
    }
}