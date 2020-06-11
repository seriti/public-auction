<?php
namespace App\Auction;

use App\Auction\Helpers;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;

use Psr\Container\ContainerInterface;

class LotResultController
{
    use IconsClassesLinks;
    use MessageHelpers;

    protected $container;
    protected $db;

    protected $mode = '';
    protected $errors = array();
    protected $errors_found = false; 
    protected $messages = array();
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $this->container->mysql;
    }

    public function __invoke($request, $response, $args)
    {
        $output = Helpers::setupAuctionLotResults($this->db,AUCTION_ID);
        if($output['error'] != '') $this->addError($output['error']);
        if($output['message'] != '') $this->addMessage($output['message']);
        
        
        $template['title'] = MODULE_LOGO.AUCTION_NAME.': Lot Numbering';
        $template['html'] = $this->viewMessages();
                
        return $this->container->view->render($response,'admin.php',$template);
    }
}