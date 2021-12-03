<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class InvoiceItem extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Invoice Item','col_label'=>'item','pop_up'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX.'invoice','key'=>'invoice_id','child_col'=>'invoice_id', 
                                 'show_sql'=>'SELECT CONCAT("Invoice: ",`invoice_no`) FROM `'.TABLE_PREFIX.'invoice` WHERE `invoice_id` = "{KEY_VAL}" '));                        

        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'quantity','type'=>'INTEGER','title'=>'Quantity'));
        $this->addTableCol(array('id'=>'item','type'=>'STRING','title'=>'Invoice item'));
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID'));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Price'));
        $this->addTableCol(array('id'=>'total','type'=>'DECIMAL','title'=>'Total'));

        $this->addSortOrder('T.item_id','Order of capture','DEFAULT');
    }    
}

?>
