<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Template;

use App\Auction\CheckoutWizard;
use App\Auction\Helpers;

class CheckoutWizardController
{
    protected $container;
        

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        $db = $this->container->mysql; 
        $cache = $this->container->cache;
        
        $temp_token = $this->container->user->getTempToken();

        $cart = Helpers::getCart($db,MODULE_AUCTION['table_prefix'],$temp_token);

        //use temp token to identify user for duration of wizard
        $user_specific = false;
        $cache_name = 'checkout_wizard'.$temp_token;
        $cache->setCache($cache_name,$user_specific);

        $wizard_template = new Template(BASE_TEMPLATE);
        
        $wizard = new CheckoutWizard($this->container->mysql,$this->container,$cache,$wizard_template);
        $wizard->setup();        

        $html = $wizard->process();

        $template['html'] = $html;
        $template['title'] = 'Auction <b>'.$cart['auction'].'</b> '.MODULE_AUCTION['labels']['order'].' Checkout';
        //$template['javascript'] = $wizard->getJavascript();

        return $this->container->view->render($response,'public.php',$template);
    }
}