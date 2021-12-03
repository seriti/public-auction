<?php
namespace App\Auction;

use Psr\Container\ContainerInterface;

use Seriti\Tools\DbInterface;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\TABLE_USER;

class LotInfo
{
    use IconsClassesLinks;

    protected $container;
    protected $db;
    protected $lot_id;

    public function __construct(DbInterface $db,ContainerInterface $container,$lot_id)
    {
        $this->db = $db;
        $this->container = $container;
        $this->lot_id = $lot_id;
    }

    public function show()
    {

        $html = '<div class="container">';

        $sql = 'SELECT O.`order_id`,O.`date_create`,O.`user_id`,U.`name`,U.`email`,I.`price`,I.`status` '.
               'FROM `'.TABLE_PREFIX.'order` AS O JOIN `'.TABLE_PREFIX.'order_item` AS I ON(O.`auction_id` = "'.AUCTION_ID.'" AND O.`order_id` = I.`order_id`) '.
               'JOIN `'.TABLE_USER.'` AS U ON(O.`user_id` = U.`user_id`) '.
               'WHERE I.`lot_id` = "'.$this->db->escapeSql($this->lot_id).'" '.
               'ORDER BY I.`price` DESC, O.`date_create` ';
        $orders = $this->db->readSqlArray($sql);
        if($orders == 0) {
            $html .= '<h2>NO online orders exist for this lot.</h2>';
        } else {
            $html .= '<h2>Following online orders exist for this lot:</h2>';

            $html .= '<table class="'.$this->classes['table'].'">'.
                     '<tr><th>Order ID</th><th>Created on</th><th>User ID:name</th><th>Bid price</th></tr>';
            foreach($orders as $order_id => $order) {

                $html .= '<tr><td>'.$order_id.'</td><td>'.$order['date_create'].'</td><td>'.$order['user_id'].':'.$order['name'].'</td><td>'.number_format($order['price']).'</td></tr>';

            }
            $html .= '</table>';
        }

        $sql = 'SELECT INV.`invoice_id`,INV.`date`,INV.`user_id`,U.`name`,U.`email`,I.`price`,INV.`status` '.
               'FROM `'.TABLE_PREFIX.'invoice` AS INV '.
               'JOIN `'.TABLE_PREFIX.'invoice_item` AS I ON(INV.`auction_id` = "'.AUCTION_ID.'" AND INV.`invoice_id` = I.`invoice_id`) '.
               'JOIN `'.TABLE_USER.'` AS U ON(INV.`user_id` = U.`user_id`) '.
               'WHERE I.`lot_id` = "'.$this->db->escapeSql($this->lot_id).'" '.
               'ORDER BY I.`price` DESC ';
        $invoices = $this->db->readSqlArray($sql);
        if($invoices == 0) {
            $html .= '<h2>NO invoice exists for this lot.</h2>';
        } else {
            $html .= '<h2>Following invoice issued for this lot:</h2>';

            $html .= '<table class="'.$this->classes['table'].'">'.
                     '<tr><th>Invoice ID</th><th>Created on</th><th>User ID:name</th><th>Price</th><th>Status</th></tr>';
            foreach($invoices as $invoice_id => $invoice) {

                $html .= '<tr><td>'.$invoice_id.'</td><td>'.$invoice['date'].'</td><td>'.$invoice['user_id'].':'.$invoice['name'].'</td>'.
                             '<td>'.number_format($invoice['price']).'</td><td>'.$invoice['status'].'</td></tr>';

            }
            $html .= '</table>';
        }
        
        

        $html .= '</div>';

        return $html;
    }
    
}