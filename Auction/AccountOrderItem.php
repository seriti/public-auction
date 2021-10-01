<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\Secure;
use Seriti\Tools\Form;

use App\Auction\Helpers;

class AccountOrderItem extends Table 
{
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    protected $lot_id_row;

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
            $access['delete'] = false; 
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

        //sort lots by auction specific lot no. 
        $this->addSql('JOIN','LEFT JOIN '.$this->table_prefix.'lot AS L ON(T.lot_id = L.lot_id)');
        $this->addSortOrder('L.lot_no','Lot Number','DEFAULT');

        if($active_order) {
            $this->addAction(['type'=>'check_box','text'=>'','checked'=>true]);
            //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit','pos'=>'L'));
            //$this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));    
        }
        

        $this->addSearch(array('lot_id','price'),array('rows'=>1));

        $this->addMessage('You may only increase your bids. You cannot decrease or delete them.');
    } 

    protected function modifyEditValue($col_id,$value,$edit_type,$param) 
    {
        if($col_id === 'price') {
            $value = Secure::clean('float',$value);
            $name = $param['name']; 

            $input_param = [];
            $input_param['class'] = $this->classes['edit_small'];
            $html = Form::textInput($name,$value,$input_param);

            $info = '';
            $bids = Helpers::getBestBid($this->db,$this->table_prefix,$this->lot_id_row);
            if($bids['active_bids']) {
                if($bids['best_bid']['user_id'] != $this->user_id) $info .= '<span class="'.$this->classes['message'].'">Not&nbsp;best&nbsp;bid!</span>';
            }

            if($info !== '') $html .= $info;
            
            return $html;
        }

    }

    protected function modifyRowValue($col_id,$data,&$value)
    {
        if($col_id === 'lot_id') {
            $lot_id = $value;
            $s3 = $this->getContainer('s3');

            //set for use by modifyEditValue()
            $this->lot_id_row = $lot_id;

            $value = Helpers::getLotSummary($this->db,$this->table_prefix,$s3,$lot_id);
        }
    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        $order_id = $this->master['key_val'];
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$order_id,$error);
        //only allowed increase of bids
        if($error === '') {
            $data_before = $this->get($id);
            if($data['price'] < $data_before['price']) $error .= 'You can only increase a confirmed bid!';
        }
    }

    protected function beforeDelete($id,&$error) 
    {
        $error .= 'You cannot delete confirmed bids!';

        /*
        $order_id = $this->master['key_val'];
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$order_id,$error);
        */
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
            $param = [];
            //$param['notify_higher_bid'] = true;
            Helpers::sendOrderMessage($this->db,$this->table_prefix,$this->container,$order_id,$subject,$message,$param,$error);    
        }
    }
}

?>
