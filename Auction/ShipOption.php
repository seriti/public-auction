<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class ShipOption extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Shipping option','col_label'=>'name'];
        parent::setup($param);
                
        $this->addForeignKey(array('table'=>TABLE_PREFIX.'order','col_id'=>'ship_option_id','message'=>'Auction Order'));

        $this->addTableCol(array('id'=>'option_id','type'=>'INTEGER','title'=>'Option ID','key'=>true,'key_auto'=>true,'list'=>true));
        
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'sort','type'=>'INTEGER','title'=>'Sort Order','hint'=>'Option display order in dropdowns'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        
        //$this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $sql_status = '(SELECT "OK") UNION (SELECT "HIDE")';
        $this->addSelect('status',$sql_status);

        //$this->addSearch(array('name','status'),array('rows'=>1));
    }
}
?>
