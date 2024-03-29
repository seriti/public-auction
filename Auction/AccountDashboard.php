<?php
namespace App\Auction;

use Seriti\Tools\Date;
use Seriti\Tools\CURRENCY_SYMBOL;
use Seriti\Tools\Dashboard AS DashboardTool;

class AccountDashboard extends DashboardTool
{
     

    //configure
    public function setup($param = []) 
    {
        $this->col_count = 2;  

        $user = $this->getContainer('user'); 

        $temp_token = $user->getTempToken();
        $user_id = $user->getId();

        //Class accessed outside /App/Auction so cannot use TABLE_PREFIX constant
        $module = $this->container->config->get('module','auction');
        $table_prefix = $module['table_prefix'];
        $order_name = $module['labels']['order'];
        $order_name_plural = $order_name.'s';


        $sql = 'SELECT * FROM `'.$table_prefix.'user_extend` WHERE `user_id` = "'.$user_id.'" ';
        $user_extend = $this->db->readSqlRecord($sql);

        $cart = Helpers::getCart($this->db,$table_prefix,$temp_token);
        if($cart === 0) {
            $cart_html = 'Your cart is empty';
        } else {    
            $cart_html = '<p>'.$order_name.' cart created on '.Date::formatDate($cart['date_create']).' <a href="/public/cart">'.$cart['item_count'].' items</a></p>'; 
        }  

        $sql = 'SELECT O.`order_id`,O.`auction_id`,O.`date_create`,O.`no_items`,O.`total_bid`,A.`name` AS `auction` '.
               'FROM `'.$table_prefix.'order` AS O JOIN `'.$table_prefix.'auction` AS A ON(O.`auction_id` = A.`auction_id`) '.
               'WHERE O.`user_id` = "'.$user_id.'" AND O.`status` = "ACTIVE" '.
               'ORDER BY O.`date_create` DESC ';
        $new_orders = $this->db->readSqlArray($sql);
        if($new_orders === 0) {
            $order_html = 'NO outstanding active '.$order_name_plural;
        } else {
            $order_html .= '<ul>';
            foreach($new_orders as $order_id => $order) {
               $item_href = "javascript:open_popup('order_item?id=".$order_id."',600,600)";
               $order_html .= '<li>'.$order_name.' ID['.$order_id.'] for auction <b>'.$order['auction'].'</b><br/>'.
                              'Created on '.Date::formatDate($order['date_create']).' <a href="'.$item_href.'">'.$order['no_items'].' items</a> '.
                              'total bid value:'.CURRENCY_SYMBOL.$order['total_bid'].'</li>'; 
            }
            $order_html .= '</ul>';
        } 


        $invoices = Helpers::getUnpaidInvoices($this->db,$table_prefix,$user_id);   

        //(block_id,col,row,title)
        $this->addBlock('USER',1,1,'User data: <a href="profile?mode=edit">edit</a>');
        $this->addItem('USER','<strong>Email:</strong> '.$user->getEmail());
        $this->addItem('USER','<strong>Invoice name:</strong> '.$user_extend['name_invoice']);
        $this->addItem('USER','<strong>Cellphone:</strong> '.$user_extend['cell']);
        $this->addItem('USER','<strong>Landline:</strong> '.$user_extend['tel']);
        $this->addItem('USER','<strong>Shipping Address:</strong><br/>'.nl2br($user_extend['ship_address']));
        $this->addItem('USER','<strong>Billing Address:</strong><br/>'.nl2br($user_extend['bill_address']));

        $this->addBlock('INVOICE',1,2,'Invoices outstanding');
        $this->addItem('INVOICE',$invoices['html']);
        
        $this->addBlock('CART',2,1,'UNconfirmed '.$order_name.' Cart contents');
        $this->addItem('CART',$cart_html);  

        $this->addBlock('ORDERS',2,2,'Active confirmed '.$order_name_plural);
        $this->addItem('ORDERS',$order_html);  
        
    }

}

?>