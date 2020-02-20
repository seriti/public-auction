<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Secure;

use App\Auction\Helpers;


class Ajax
{
    protected $container;
    protected $db;
    protected $user;

    protected $debug = false;
    //Class accessed outside /App/Auction so cannot use TABLE_PREFIX constant
    protected $table_prefix = '';
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $this->container->mysql;
        $this->user = $this->container->user;

        //Class accessed outside /App/Auction so cannot use TABLE_PREFIX constant
        $module = $this->container->config->get('module','auction');
        $this->table_prefix = $module['table_prefix'];

        if(defined('\Seriti\Tools\DEBUG')) $this->debug = \Seriti\Tools\DEBUG;
    }


    public function __invoke($request, $response, $args)
    {
        $mode = '';
        $output = '';

        if(isset($_GET['mode'])) $mode = Secure::clean('basic',$_GET['mode']);

        /*
        $this->csrf_token = Secure::clean('basic',Form::getVariable('csrf_token','GP'));

        $this->user_access_level = $this->getContainer('user')->getAccessLevel();
        $this->user_id = $this->getContainer('user')->getId();
        $this->user_csrf_token = $this->getContainer('user')->getCsrfToken();

        $this->verifyCsrfToken($error); //maybe need a verifyPublicCsrfToken() with more meaningful error message??
        */

        if($mode === 'list_add') $output = $this->addToCart($_POST);

        return $output;
    }

    protected function addToCart($form)
    {
        $error = '';
        
        /*
        $output = 'Hello you beauty:';

        foreach($form as $id => $value) {
            $output .= $id.':'.$value."\r\n";            
        }
        return $output;
        */

        $temp_token = $this->user->getTempToken();

        $message = Helpers::addOrderItem($this->db,$this->table_prefix,$temp_token,$form,$error);

        if($error === '') {
            return $message;
        } else {
            if($this->debug) $message .= ': '.$error;
            return 'ERROR: '.$message;
        }

    }
}