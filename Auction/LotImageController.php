<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\LotImage;

class LotImageController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table = TABLE_PREFIX.'file'; 
        $upload = new LotImage($this->container->mysql,$this->container,$table);

        $upload->setup();
        $html = $upload->processUpload();
        
        $template['html'] = $html;
        //$template['title'] = MODULE_LOGO;
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}