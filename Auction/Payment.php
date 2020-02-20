<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class Payment extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Payment','col_label'=>'amount'];
        parent::setup($param);        
               
        $this->addTableCol(array('id'=>'payment_id','type'=>'INTEGER','title'=>'Payment ID','key'=>true,'key_auto'=>true));
        $this->addTableCol(array('id'=>'invoice_id','type'=>'INTEGER','title'=>'Invoice ID'));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Date paid'));
        $this->addTableCol(array('id'=>'amount','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addSortOrder('T.order_id DESC','Most recent first','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $status_list = ['NEW','CONFIRMED'];
        $this->addSelect('status',['list'=>$status_list,'list_assoc'=>false]);

        $this->addSearch(array('payment_id','invoice_id','date_create','amount','status'),array('rows'=>2));
        //$this->addSelect('order_id','SELECT order_id, CONCAT(order_id,":",date_create) FROM '.TABLE_PREFIX.'order WHERE status = "NEW" ORDER BY date_create DESC');
    }    
}

?>
