<?php 
namespace App\Auction;

use Seriti\Tools\Table;

use App\Auction\Helpers;

class AccountOrderItem extends Table 
{
    protected $table_prefix = MODULE_AUCTION['table_prefix'];

    //configure
    public function setup($param = []) 
    {
        $error = '';
        if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix']; 
        //if any error then order is NOT active and returns false .
        $active_order = Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$param['order_id'],$error);

        $table_param = ['row_name'=>'Lot','col_label'=>'lot_id','pop_up'=>true,
                        'table_edit_all'=>$active_order,'update_calling_page'=>$active_order];
        parent::setup($table_param);    
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>$this->table_prefix.'order','key'=>'order_id','child_col'=>'order_id', 
                                 'show_sql'=>'SELECT CONCAT("'.MODULE_AUCTION['labels']['order'].' ID[",order_id,"] created-",date_create) FROM '.$this->table_prefix.'order WHERE order_id = "{KEY_VAL}" '));  

        $access = [];
        if($active_order) {
            $access['add'] = false;
            $access['edit'] = true;                                                  
            $access['delete'] = true; 
            $access['email'] = false;                        
            $access['move'] = false;
        } else {
            $access['read_only'] = true;
        }
        $this->modifyAccess($access);

        $this->changeText('btn_action','Update Lots');

        $this->addTableCol(array('id'=>'item_id','type'=>'INTEGER','title'=>'Item ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Auction lot','edit'=>false));
        $this->addTableCol(array('id'=>'price','type'=>'DECIMAL','title'=>'Bid Price','edit'=>true));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','edit'=>false));

        if($active_order) {
            $this->addAction(['type'=>'check_box','text'=>'','checked'=>true]);
            //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit','pos'=>'L'));
            $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));    
        }
        

        $this->addSearch(array('lot_id','price'),array('rows'=>1));
    } 

    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'lot_id') {
            $lot_id = $value;
            $s3 = $this->getContainer('s3');

            $value = Helpers::getLotSummary($this->db,$this->table_prefix,$s3,$lot_id);
        }
    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        $order_id = $this->master['key_val'];
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$order_id,$error);
    }

    protected function beforeDelete($id,&$error) 
    {
        $order_id = $this->master['key_val'];
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$order_id,$error);
    }

    protected function afterUpdate($id,$context,$data) 
    {
        $order_id = $this->master['key_val'];
        Helpers::updateOrderTotals($this->db,$this->table_prefix,$order_id,$error);
    }

    protected function afterDelete($id) 
    {
        $order_id = $this->master['key_val'];
        Helpers::updateOrderTotals($this->db,$this->table_prefix,$order_id,$error);
    }

    protected function afterUpdateTable($action) 
    {
        if($action === 'UPDATE' or $action === 'DELETE') {
            $order_id = $this->master['key_val'];
            $error = '';
            $subject = 'UPDATED';
            $message = 'You updated your '.MODULE_AUCTION['labels']['order'].'. Please view revised details below.';
            $param=[];
            Helpers::sendOrderMessage($this->db,$this->table_prefix,$this->container,$order_id,$subject,$message,$param,$error);    
        }
    }
}

?>
