<?php 
namespace App\Auction;

use Seriti\Tools\Table;

use App\Auction\Helpers;

class OrderMessage extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Order Message','col_label'=>'subject','pop_up'=>true];
        parent::setup($param);        
                       
        //NB: specify master table relationship
        $this->setupMaster(array('table'=>TABLE_PREFIX.'order','key'=>'order_id','child_col'=>'order_id', 
                                 'show_sql'=>'SELECT CONCAT("Order ID[",order_id,"] created-",date_create) FROM '.TABLE_PREFIX.'order WHERE order_id = "{KEY_VAL}" '));  

        $access['edit'] = false;
        $access['delete'] = false;
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'message_id','type'=>'INTEGER','title'=>'Message ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'subject','type'=>'STRING','title'=>'Subject'));
        $this->addTableCol(array('id'=>'message','type'=>'TEXT','title'=>'Body'));
        //$this->addTableCol(array('id'=>'options','type'=>'TEXT','title'=>'Options'));
        $this->addTableCol(array('id'=>'date_sent','type'=>'DATETIME','title'=>'Date sent','edit'=>false));
       
        $this->addSearch(array('subject','message','date_sent'),array('rows'=>1));
    } 

    protected function beforeUpdate($id,$edit_type,&$form,&$error) 
    {
        $error_tmp = '';

        if($edit_type === 'INSERT') {
            $order_id = $this->master['key_val']; 
            $subject = $form['subject'];
            $message = $form['message'];
            $param=[];
            Helpers::sendOrderMessage($this->db,TABLE_PREFIX,$this->container,$order_id,$subject,$message,$param,$error_tmp);
            if($error_tmp !== '') $error .= 'Could not send message: '.$error_tmp;
        }
    }

    protected function afterUpdate($id,$edit_type,$form) {
        if($edit_type === 'INSERT') {
            $sql='UPDATE '.$this->table.' SET date_sent = NOW() '.
                 'WHERE message_id = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error);
            if($error !== '') die($error);


        }
    } 

    
}

?>
