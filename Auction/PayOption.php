<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class PayOption extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Payment option','col_label'=>'name'];
        parent::setup($param);
                
        $this->addTableCol(array('id'=>'option_id','type'=>'INTEGER','title'=>'Option ID','key'=>true,'key_auto'=>true,'list'=>true));
        
        $this->addTableCol(array('id'=>'type_id','type'=>'STRING','title'=>'Payment type'));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'sort','type'=>'INTEGER','title'=>'Sort Order','hint'=>'Option display order in dropdowns'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        
        //$this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $pay_type = ['EFT_TOKEN'=>'Manual payment with emailed token',
                     'GATEWAY_FORM'=>'Payment gateway'];
        $this->addSelect('type_id',['list'=>$pay_type,'list_assoc'=>true]);

        $status = ['OK','HIDE'];
        $this->addSelect('status',['list'=>$status,'list_assoc'=>fals]);
   }
}
?>
