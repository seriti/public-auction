<?php
namespace App\Auction;

use App\Auction\Helpers;
use App\Auction\HelpersReport;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;

use Psr\Container\ContainerInterface;

class BidPdfController
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
        //could pass through user access level and allow GOD to re-allocate lot numbers if no orders linked to auction
        $access_level = $this->container->user->getAccessLevel();


        $doc_name = '';
        $error = '';
        $options = [];
        $options = ['format'=>'PDF'];

        HelpersReport::allBidsReport($this->db,AUCTION_ID,$options,$doc_name,$error);
        if($error != '') {
            $this->addError($error);
        
            $template['title'] = MODULE_LOGO.': All online bids PDF create Error';
            $template['html'] = $this->viewMessages();
                    
            return $this->container->view->render($response,'admin.php',$template);
        }    
    }
}