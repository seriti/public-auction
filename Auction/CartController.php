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
        $order_name = $module['labels']['order'];
        $order_name_plural = $order_name.'s';
        
        //NB: Cart contents same as order but temp_token identifies 
        $temp_token = $user->getTempToken();
        $cart = Helpers::getCart($db,$table_prefix,$temp_token);

        if($cart === 0) {
            $title = 'Your cart is empty!';
            $html = '<h2>If you have just completed checkout process then <a href="account/dashboard">check your account</a> for active '.$order_name_plural.'.</h2>';
            $html = '<h2>You can start adding new auction lots to your cart.</h2>';
        } else {
            $title = '<b>'.$cart['auction'].'</b> '.$order_name.' cart: <a href="checkout" class="btn btn-primary">Proceed to checkout</a>';

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
                $totals = Helpers::getCartItemTotals($db,$table_prefix,$cart['order_id']);
                $html .= '<p><strong>Bid total: </strong>'.CURRENCY_SYMBOL.number_format($totals['total'],2).' for '.$totals['no_items'].' lots : '.
                         '<a href="checkout" class="btn btn-primary">Proceed to checkout</a></p>';

                //$html .= '<p></p>';

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