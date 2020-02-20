<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class AccountOrderItem extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Item','col_label'=>'name','pop_up'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX_AUCTION.'order','key'=>'order_id','child_col'=>'order_id', 
                                 'show_sql'=>'SELECT CONCAT("Order ID[",order_id,"] created-",date_create) FROM '.TABLE_PREFIX_AUCTION.'order WHERE order_id = "{KEY_VAL}" '));  

        
        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Auction lot'));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Bid Price'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));


        //$this->addSearch(array('notes','date'),array('rows'=>1));
    } 

    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'lot_id') {
            $lot_id = $value;
            $s3 = $this->getContainer('s3');

            $value = Helpers::getLotSummary($this->db,TABLE_PREFIX_AUCTION,$s3,$lot_id);
        }
    }   
}

?>
