<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\Form;

use App\Auction\Helpers;

class OrderItem extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot','col_label'=>'lot_id','pop_up'=>true,'update_calling_page'=>true,'add_repeat'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX.'order','key'=>'order_id','child_col'=>'order_id', 
                                 'show_sql'=>'SELECT CONCAT("Order ID[",order_id,"] created-",date_create) FROM '.TABLE_PREFIX.'order WHERE order_id = "{KEY_VAL}" '));  
        
        //$access['read_only'] = true;                         
        //$this->modifyAccess($access);

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        //NB: set as STRING so can either capture Lot No(using "N" prefix) or ID 
        $this->addTableCol(array('id'=>'lot_no','type'=>'INTEGER','title'=>'Lot No.','linked'=>'L.lot_no'));
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot description','edit_title'=>'Lot ID','new'=>'0','autofocus'=>true));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Bid Price'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addSql('JOIN','JOIN '.TABLE_PREFIX.'lot AS L ON(T.lot_id = L.lot_id)');
        $this->addSortOrder('L.lot_no ','Catalog Lot No','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $sql_status = '(SELECT "BID") UNION (SELECT "OUT_BID") UNION (SELECT "SUCCESS")';
        $this->addSelect('status',$sql_status);

        $this->addSearch(array('lot_id','status','price'),array('rows'=>2));
        $this->addSearchXtra('L.lot_no','Lot no');
    } 

    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'lot_id') {
            $lot_id = $value;
            $s3 = $this->getContainer('s3');

            $value = Helpers::getLotSummary($this->db,TABLE_PREFIX,$s3,$lot_id);
        }
    } 


    protected function viewEditXtra($id,$form,$context) 
    {
        $html = '';

        if(isset($form['lot_no'])) {
            $lot_no = $form['lot_no'];
        } else {
            $lot_no = '';   
        }    
        
        $param = ['class'=>$this->classes['edit_small']];
        $html .= '<strong>Enter Catalog Lot No.(This will override Lot ID):</strong><br/>'.
                 Form::textInput('lot_no',$lot_no,$param); 

        return $html;
    }
    

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        //$order_id = $this->master['key_val'];
        //Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$order_id,$error);

        if(isset($_POST['lot_no']) and $_POST['lot_no'] !== '') {
            $lot_no = $_POST['lot_no'];
            $lot = Helpers::getLot($this->db,TABLE_PREFIX,'LOT_NO',$lot_no,AUCTION_ID);
            if($lot == 0) {
                $this->addError('INVALID auction Lot No['.$lot_no.'] entered!');
            } else {
                $data['lot_id'] = $lot['lot_id'];
            }
        } else {
            $lot = Helpers::getLot($this->db,TABLE_PREFIX,'LOT_ID',$data['lot_id'],AUCTION_ID);
            if($lot == 0) {
                $this->addError('INVALID auction Lot ID['.$data['lot_id'].'] entered!');
            }
        }

        if(!$this->errors_found) {
            if($lot['auction_id'] !== AUCTION_ID) $this->addError('Lot is not part this auction['.AUCTION_NAME.']');
            if($lot['price_reserve'] > $data['price']) $this->addError('Lot reserve price['.$lot['price_reserve'].'] is GREATER that entered price['.$data['price'].']');
        }

        //check that lot not already part of this order
        if(!$this->errors_found) {
            $sql = 'SELECT COUNT(*) FROM '.$this->table.' '.
                   'WHERE order_id = "'.$this->db->escapeSql($this->master['key_val']).'" AND '.
                         'lot_id = "'.$this->db->escapeSql($lot['lot_id']).'" AND item_id <> "'.$this->db->escapeSql($id).'" ';
            $exist = $this->db->readSqlValue($sql);
            if($exist) $this->addError('Lot is already part of order. Please update that record.');
        }
    } 

    protected function afterUpdate($id,$context,$data) 
    {
        $order_id = $this->master['key_val'];
        Helpers::updateOrderTotals($this->db,TABLE_PREFIX,$order_id,$error);
    } 

    /* ASSUME ADMIN PEOPLE WILL KNOW WHAT THEY ARE DOING???    
    protected function beforeDelete($id,&$error) 
    {
        $order_id = $this->master['key_val'];
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$order_id,$error);
    }
    */

    protected function afterDelete($id) 
    {
        $order_id = $this->master['key_val'];
        Helpers::updateOrderTotals($this->db,TABLE_PREFIX,$order_id,$error);
    }
}

?>
