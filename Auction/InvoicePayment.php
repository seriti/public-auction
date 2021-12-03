<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class InvoicePayment extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Payment','col_label'=>'amount','pop_up'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX.'invoice','key'=>'invoice_id','child_col'=>'invoice_id', 
                                 'show_sql'=>'SELECT CONCAT("Invoice ID[",invoice_id,"] created-",`date`) FROM `'.TABLE_PREFIX.'invoice` WHERE `invoice_id` = "{KEY_VAL}" '));  
                
        $this->addTableCol(array('id'=>'payment_id','type'=>'INTEGER','title'=>'Payment ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Date paid'));
        $this->addTableCol(array('id'=>'amount','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $status_list = ['NEW','CONFIRMED'];
        $this->addSelect('status',['list'=>$status_list,'list_assoc'=>false]);

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));
    }    
}

?>
