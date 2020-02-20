<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;
use App\Auction\AccountProfile;

class AccountProfileController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $user = $this->container->user;
        
        $table_name = TABLE_PREFIX_AUCTION.'user_extend'; 
        $record = new AccountProfile($this->container->mysql,$this->container,$table_name);

        $param = [];
        $param['user_id'] = $user->getId();
        $param['table_prefix'] = $table_prefix;
        $record->setup($param);
        $html = $record->processRecord();
        
        $template['html'] = $html;
        $template['title'] = 'Your profile data';
        
        return $this->container->view->render($response,'public.php',$template);
    }
}