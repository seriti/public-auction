<?php 
namespace App\Auction;

use Seriti\Tools\Upload;

class OrderFile extends Upload 
{
  //configure
    public function setup($param = []) 
    {
        $id_prefix = 'ORD'; 

        $param = ['row_name'=>'Order document',
                  'pop_up'=>true,
                  'update_calling_page'=>true,
                  'prefix'=>$id_prefix,//will prefix file_name if used, but file_id.ext is unique 
                  'upload_location'=>$id_prefix]; 
        parent::setup($param);

        $param=[];
        $param['table']     = TABLE_PREFIX.'order';
        $param['key']       = 'order_id';
        $param['label']     = 'order_id';
        $param['child_col'] = 'location_id';
        $param['child_prefix'] = $id_prefix;
        $param['show_sql'] = 'SELECT CONCAT("Order[",order_id,"] created: ",date_create) FROM '.TABLE_PREFIX.'order WHERE order_id = "{KEY_VAL}"';
        $this->setupMaster($param);

        $this->addAction(array('type'=>'edit','text'=>'edit details of','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R','icon_text'=>'delete'));
    }
}
?>
