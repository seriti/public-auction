<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class AccountInvoiceItem extends Table 
{
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    protected $user_id = 0;    

    //configure
    public function setup($param = []) 
    {
        $parent_param = ['row_name'=>'Invoice Item','col_label'=>'item','pop_up'=>true];
        parent::setup($parent_param); 

        if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix'];
        if(isset($param['user_id'])) $this->user_id = $param['user_id'];       
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>$this->table_prefix.'invoice','key'=>'invoice_id','child_col'=>'invoice_id', 
                                 'show_sql'=>'SELECT CONCAT("Items for Invoice: ",`invoice_no`) '.
                                             'FROM `'.$this->table_prefix.'invoice` WHERE `invoice_id` = "{KEY_VAL}" '));                        

        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'quantity','type'=>'INTEGER','title'=>'Quantity'));
        $this->addTableCol(array('id'=>'item','type'=>'STRING','title'=>'Invoice item'));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Price'));
        $this->addTableCol(array('id'=>'total','type'=>'DECIMAL','title'=>'Total'));

        $this->addSortOrder('T.`item_id`','Order of capture','DEFAULT');
    }    
}

?>
