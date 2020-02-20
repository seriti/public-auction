<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class OrderItem extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot','col_label'=>'lot_id','pop_up'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX.'order','key'=>'order_id','child_col'=>'order_id', 
                                 'show_sql'=>'SELECT CONCAT("Order ID[",order_id,"] created-",date_create) FROM '.TABLE_PREFIX.'order WHERE order_id = "{KEY_VAL}" '));  

        
        //$access['read_only'] = true;                         
        //$this->modifyAccess($access);

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID'));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Bid Price'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $sql_status = '(SELECT "BID") UNION (SELECT "OUT_BID") UNION (SELECT "SUCCESS")';
        $this->addSelect('status',$sql_status);

        $this->addSearch(array('lot_id','status','price'),array('rows'=>2));
    } 

    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'lot_id') {
            $lot_id = $value;
            $s3 = $this->getContainer('s3');

            $value = Helpers::getLotSummary($this->db,TABLE_PREFIX,$s3,$lot_id);
        }
    } 

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        //check lot exists and assigned to correct auction as well as check pricing
        $sql = 'SELECT lot_id,auction_id,name,price_reserve FROM '.TABLE_PREFIX.'lot '.
               'WHERE lot_id = "'.$this->db->escapeSql($data['lot_id']).'" ';
        $lot = $this->db->readSqlRecord($sql);
        if($lot === 0) {
            $this->addError('Order Lot ID['.$data['lot_id'].'] does not exist anymore!');
        } else {
            if($lot['auction_id'] !== AUCTION_ID) $this->addError('Lot is not part this auction['.AUCTION_NAME.']');
            if($lot['price_reserve'] > $data['price']) $this->addError('Lot reserve price['.$lot['price_reserve'].'] is GREATER that entered price['.$data['price'].']');
        }  

        //check that lot not already part of this order
        if(!$this->errors_found) {
            $sql = 'SELECT COUNT(*) FROM '.$this->table.' '.
                   'WHERE order_id = "'.$this->db->escapeSql($this->master['key_val']).'" AND '.
                         'lot_id = "'.$this->db->escapeSql($data['lot_id']).'" AND item_id <> "'.$this->db->escapeSql($id).'" ';
            $exist = $this->db->readSqlValue($sql);
            if($exist) $this->addError('Lot is already part of order. Please update that record.');
        }
    }  
}

?>
