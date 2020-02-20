<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class AccountInvoicePayment extends Table 
{
    
    protected $table_prefix = TABLE_PREFIX_AUCTION;
    protected $user_id = 0;
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Payment','col_label'=>'amount','pop_up'=>true];
        parent::setup($param);   

        if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix'];
        if(isset($param['user_id'])) $this->user_id = $param['user_id'];      
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>$this->table_prefix.'invoice','key'=>'invoice_id','child_col'=>'invoice_id', 
                                 'show_sql'=>'SELECT CONCAT("Payments for Invoice: ",invoice_no) FROM '.$this->table_prefix.'invoice WHERE invoice_id = "{KEY_VAL}" '));      

        
        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'payment_id','type'=>'INTEGER','title'=>'Payment ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Date paid'));
        $this->addTableCol(array('id'=>'amount','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        //$this->addSearch(array('notes','date'),array('rows'=>1));
    }    
}

?>
