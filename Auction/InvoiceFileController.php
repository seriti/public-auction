<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\InvoiceFile;

class InvoiceFileController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table = TABLE_PREFIX.'file'; 
        $upload = new InvoiceFile($this->container->mysql,$this->container,$table);

        $upload->setup();
        $html = $upload->processUpload();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Invoice Files';
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}