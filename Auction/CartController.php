<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Template;

use App\Auction\Cart;
use App\Auction\Helpers;

class CartController
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
        $module = $this->container->config->get('module','auction');
        $table_prefix = $module['table_prefix'];
        
        //NB: Cart contents same as order but user_id = 0 and temp_token identifies 
        $temp_token = $user->getTempToken();
        $cart = Helpers::getCart($db,$table_prefix,$temp_token);

        if($cart === 0) {
            $title = 'Your cart is empty!';
            $html = '<h2>If you have just completed checkout process then <a href="account/dashboard">check your account</a> for active orders.</h2>';
            $html = '<h2>You can start adding new auction lots to you cart.</h2>';
        } else {
            $title = 'Auction <b>'.$cart['auction'].'</b> order cart: <a href="checkout" class="btn btn-primary">Proceed to checkout</a>';

            $table_name = $table_prefix.'order_item'; 
            $table = new Cart($this->container->mysql,$this->container,$table_name);

            $param = [];
            $param['order_id'] = $cart['order_id'];
            $param['auction_id'] = $cart['auction_id'];
            $param['table_prefix'] = $table_prefix;
            $table->setup($param);
            $html = $table->processTable();
        
            //display cart order totals
            
            if(strpos('list',$table->getMode()) !== false) {
                $html .= '<p><strong>Bid total: </strong>'.$cart['total'].'</p>';
                /*
                $template_auction = new Template(BASE_TEMPLATE.'auction/');
                $template_auction->data = Helpers::getCartItemTotals($db,$table_prefix,$cart['order_id']);
                $template_auction->messages = 'Preliminary bid total estimate assuming all bids successful.';
                 
                $html .= $template_auction->render('totals.php');
                */
            }
            
        }        
            

        $template['html'] = $html;
        $template['title'] = $title;
        //$template['javascript'] = $dashboard->getJavascript();
        
        return $this->container->view->render($response,'public.php',$template);
    }
}