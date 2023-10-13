<?php
namespace App\Auction;

use App\Auction\Helpers;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;

use Psr\Container\ContainerInterface;

class UserResetController
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
        $options = [];
        $options = ['user_access'=>$access_level];
        $options = ['buyer_no'=>true];
        $output = Helpers::UserReset($this->db,$options);
        if($output['error'] != '') $this->addError($output['error']);
        if($output['message'] != '') $this->addMessage($output['message']);
        
        
        $template['title'] = MODULE_LOGO.': Reset User Buyer No`s';
        $template['html'] = $this->viewMessages();
                
        return $this->container->view->render($response,'admin.php',$template);
    }
}