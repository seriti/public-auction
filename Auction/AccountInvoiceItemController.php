<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\AccountInvoiceItem;

class AccountInvoiceItemController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        //NB: TABLE_PREFIX constant not applicable as not called within admin module
        $module = $this->container->config->get('module','auction');
        $table_prefix = $module['table_prefix'];

        $user = $this->container->user;

        $table_name = $table_prefix.'invoice_item'; 
        $table = new AccountInvoiceItem($this->container->mysql,$this->container,$table_name);

        $param = [];
        $param['user_id'] = $user->getId();
        $param['table_prefix'] = $table_prefix;
        $table->setup($param);
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = '';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}