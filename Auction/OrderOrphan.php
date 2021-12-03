<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\STORAGE;
use Seriti\Tools\TABLE_USER;

use App\Auction\Helpers;

class OrderOrphan extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'UN-checked out '.MODULE_AUCTION['labels']['order'],'col_label'=>'order_id'];
        parent::setup($param);

        $access = MODULE_AUCTION['access'];

        $this->modifyAccess(['add'=>false,'delete'=>false]);
         
        //$this->addForeignKey(array('table'=>TABLE_PREFIX.'order_item','col_id'=>'order_id','message'=>MODULE_AUCTION['labels']['order'].' lots'));
        $this->addForeignKey(array('table'=>TABLE_PREFIX.'order_message','col_id'=>'order_id','message'=>MODULE_AUCTION['labels']['order'].' messages'));
        $this->addForeignKey(array('table'=>TABLE_PREFIX.'invoice','col_id'=>'order_id','message'=>MODULE_AUCTION['labels']['order'].' Invoice'));

        $this->addTableCol(array('id'=>'order_id','type'=>'INTEGER','title'=>MODULE_AUCTION['labels']['order'].' ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'user_id','type'=>'INTEGER','title'=>'Linked User',
                                 'join'=>'`name` FROM `'.TABLE_USER.'` WHERE `user_id`'));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Date created','edit'=>false));
        $this->addTableCol(array('id'=>'date_update','type'=>'DATETIME','title'=>'Date updated','edit'=>false));
        $this->addTableCol(array('id'=>'no_items','type'=>'INTEGER','title'=>'Number of lots','edit'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'ACTIVE','edit'=>false));
        
        //order table also store cart contents before converted to an order in checkout wizard
        $this->addSql('WHERE','T.`auction_id` = "'.AUCTION_ID.'" AND T.`temp_token` <> "" AND T.`status` = "NEW" ');

        $this->addSortOrder('T.order_id DESC','Most recent first','DEFAULT');

        //$this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));
        $this->addAction(array('type'=>'popup','text'=>'Lots','url'=>'order_item','mode'=>'view','width'=>700,'height'=>600));
        
        $this->addSelect('user_id','SELECT `user_id`,`name` FROM `'.TABLE_USER.'` WHERE `status` <> "HIDE" ORDER BY `name`');
        
        $this->addSearch(array('date_create','date_update'),array('rows'=>1));

        if($access['login_before_bid']) {
            $this->addMessage('Select <b>edit</b> action link to complete checkout for '.MODULE_AUCTION['labels']['order'].'. <b>NB: This will move record to '.MODULE_AUCTION['labels']['order'].'s tab.</b>');
        } else {
            $this->addMessage('Select <b>edit</b> action link to assign a user to '.MODULE_AUCTION['labels']['order'].' and complete checkout. <b>NB: This will move record to '.MODULE_AUCTION['labels']['order'].'s tab.</b>');
        }

        
    }

    protected function afterUpdate($id,$edit_type,$form) {
        $error = '';
        //should always be UPDATE as INSERT not allowed
        if($edit_type === 'UPDATE' and $form['user_id'] != 0) { 
            

            $sql = 'UPDATE `'.$this->table.'` SET `date_update` = NOW(), `user_id` = "'.$this->db->escapeSql($form['user_id']).'", '.
                          '`status` = "ACTIVE", `temp_token` = "" '.
                   'WHERE `order_id` = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error);

            if($error === '') {
               $order_id = $id;
               $subject = 'Manually LINKED by support'; 
               $message = 'Our staff have manually linked your orphaned '.MODULE_AUCTION['labels']['order'].' to your account. '.
                          'Please check lot details and contact us if you have any concerns.';
               $param=[];
               Helpers::sendOrderMessage($this->db,TABLE_PREFIX,$this->container,$order_id,$subject,$message,$param,$error);
               //make sure no_items and total bid correct as no checkout process followed
               Helpers::updateOrderTotals($this->db,TABLE_PREFIX,$order_id,$error);
            }
        }    
    }

    protected function modifyRowValue($col_id,$data,&$value) 
    {
        //NB: this must be calculated as not necessarily assigned yet
        if($col_id === 'no_items') {
            $sql = 'SELECT COUNT(*) FROM `'.TABLE_PREFIX.'order_item` WHERE `order_id` = "'.$this->db->escapeSql($data['order_id']).'" ';
            $value = $this->db->readSqlValue($sql,0);    
        }
        
    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        if($context === 'UPDATE') {
            Helpers::checkOrderUpdateOk($this->db,TABLE_PREFIX,$id,$error);    
        }
    }
    
    protected function beforeDelete($id,&$error) 
    {
        Helpers::checkOrderUpdateOk($this->db,TABLE_PREFIX,$id,$error);
    }

    protected function afterDelete($id) {
        $error = '';

        $sql = 'DELETE FROM `'.TABLE_PREFIX.'order_item` WHERE `order_id` = "'.$this->db->escapeSql($id).'" ';
        $this->db->executeSql($sql,$error); 
    } 
}
?>
