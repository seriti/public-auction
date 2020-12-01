<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\Date;
use Seriti\Tools\STORAGE;

use App\Auction\Helpers;

class AccountOrder extends Table 
{
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    protected $user_id = 0;

    //configure
    public function setup($param = []) 
    {
        if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix'];
        if(isset($param['user_id'])) $this->user_id = $param['user_id'];
        $order_name = $param['labels']['order'];

        $table_param = ['row_name'=>$order_name,'col_label'=>'order_id'];
        parent::setup($table_param);
        
        $access = [];
        $access['add'] = false;
        $access['edit'] = true;                                                  
        $access['delete'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'order_id','type'=>'INTEGER','title'=>$order_name.' ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'auction_id','type'=>'INTEGER','title'=>'Auction','join'=>'CONCAT(name," @",DATE_FORMAT(date_start_live,"%d %M %Y")) FROM '.$this->table_prefix.'auction WHERE auction_id','edit'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','edit'=>false));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Date created','edit'=>false));
        $this->addTableCol(array('id'=>'date_update','type'=>'DATETIME','title'=>'Date updated','edit'=>false));
        $this->addTableCol(array('id'=>'no_items','type'=>'INTEGER','title'=>'No. of lots','edit'=>false));
        $this->addTableCol(array('id'=>'total_bid','type'=>'DECIMAL','title'=>'Bid total','edit'=>false));
        //$this->addTableCol(array('id'=>'total_success','type'=>'DECIMAL','title'=>'Success total'));
        $this->addTableCol(array('id'=>'ship_address','type'=>'TEXT','title'=>'Shipping address'));
        $this->addTableCol(array('id'=>'ship_location_id','type'=>'INTEGER','title'=>'Shipping location','join'=>'name FROM '.$this->table_prefix.'ship_location WHERE location_id'));
        $this->addTableCol(array('id'=>'ship_option_id','type'=>'INTEGER','title'=>'Shipping option','join'=>'name FROM '.$this->table_prefix.'ship_option WHERE option_id'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','edit'=>false));

        $this->addSql('WHERE','T.user_id = "'.$this->db->escapeSql($this->user_id).'" AND T.temp_token = "" AND T.status <> "NEW" ');

        $this->addSortOrder('T.order_id DESC','Most recent first','DEFAULT');
        
        $this->addAction(array('type'=>'edit','text'=>'edit shipping','icon_text'=>'edit','pos'=>'R','verify'=>true));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R','verify'=>true));
        $this->addAction(array('type'=>'popup','text'=>'Bid Lots','url'=>'order_item','mode'=>'view','width'=>600,'height'=>600)); 

        $this->addSelect('ship_location_id','SELECT location_id,name FROM '.$this->table_prefix.'ship_location WHERE status <> "HIDE" ORDER By sort');
        $this->addSelect('ship_option_id','SELECT option_id,name FROM '.$this->table_prefix.'ship_option WHERE status <> "HIDE" ORDER By sort');

    }

    

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$id,$error);
    }

    protected function beforeDelete($id,&$error) 
    {
        Helpers::checkOrderUpdateOk($this->db,$this->table_prefix,$id,$error);
    }

    protected function afterDelete($id) 
    {
        $error = '';

        $sql = 'DELETE FROM '.$this->table_prefix.'order_item WHERE order_id = "'.$this->db->escapeSql($id).'" ';
        $this->db->executeSql($sql,$error); 
    } 

    protected function verifyRowAction($action,$data) 
    {
        $valid = true;

        if($data['status'] !== 'ACTIVE') $valid = false;

        return $valid;
    }
}
?>
