<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\STORAGE;

use App\Auction\Helpers;

class AccountOrder extends Table 
{
    protected $table_prefix = TABLE_PREFIX_AUCTION;
    protected $user_id = 0;

    //configure
    public function setup($param = []) 
    {
        $table_param = ['row_name'=>'Order','col_label'=>'date_create'];
        parent::setup($table_param);
       
        if(isset($param['table_prefix'])) $this->table_prefix = $param['table_prefix'];
        if(isset($param['user_id'])) $this->user_id = $param['user_id'];

        $access['read_only'] = true;                         
        $this->modifyAccess($access);

        $this->addTableCol(array('id'=>'order_id','type'=>'INTEGER','title'=>'Order ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'auction_id','type'=>'INTEGER','title'=>'Auction','join'=>'name FROM '.$this->table_prefix.'auction WHERE auction_id'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        $this->addTableCol(array('id'=>'date_create','type'=>'DATETIME','title'=>'Date created'));
        $this->addTableCol(array('id'=>'date_update','type'=>'DATETIME','title'=>'Date updated'));
        $this->addTableCol(array('id'=>'no_items','type'=>'INTEGER','title'=>'No. of lots'));
        $this->addTableCol(array('id'=>'total_bid','type'=>'DECIMAL','title'=>'Bid total'));
        //$this->addTableCol(array('id'=>'total_success','type'=>'DECIMAL','title'=>'Success total'));
        $this->addTableCol(array('id'=>'ship_address','type'=>'TEXT','title'=>'Shipping address'));
        $this->addTableCol(array('id'=>'ship_location_id','type'=>'INTEGER','title'=>'Shipping location','join'=>'name FROM '.$this->table_prefix.'ship_location WHERE location_id'));
        $this->addTableCol(array('id'=>'ship_option_id','type'=>'INTEGER','title'=>'Shipping option','join'=>'name FROM '.$this->table_prefix.'ship_option WHERE option_id'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addSql('WHERE','T.user_id = "'.$this->db->escapeSql($this->user_id).'" ');

        $this->addSortOrder('T.order_id DESC','Most recent first','DEFAULT');
        
        //$this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        //$this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));
        $this->addAction(array('type'=>'popup','text'=>'Lots','url'=>'order_item','mode'=>'view','width'=>600,'height'=>600)); 
    }

    

    //protected function beforeUpdate($id,$context,&$data,&$error) {}
    //protected function beforeDelete($id,&$error) {}
    //protected function afterDelete($id) {} 
}
?>
