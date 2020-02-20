<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class ShipCost extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Cost','col_label'=>'name'];
        parent::setup($param);
                
        $this->addTableCol(array('id'=>'cost_id','type'=>'INTEGER','title'=>'Cost ID','key'=>true,'key_auto'=>true,'list'=>true));
        
        $this->addTableCol(array('id'=>'location_id','type'=>'INTEGER','title'=>'Shipping location','join'=>'name FROM '.TABLE_PREFIX.'ship_location WHERE location_id'));
        $this->addTableCol(array('id'=>'option_id','type'=>'INTEGER','title'=>'Shipping option','join'=>'name FROM '.TABLE_PREFIX.'ship_option WHERE option_id'));
        $this->addTableCol(array('id'=>'cost_free','type'=>'DECIMAL','title'=>'Cost free','hint'=>'Above this cost shipping is free, make 0 to ignore'));
        $this->addTableCol(array('id'=>'cost_base','type'=>'DECIMAL','title'=>'Cost base','hint'=>'Basic charge per order'));
        $this->addTableCol(array('id'=>'cost_weight','type'=>'DECIMAL','title'=>'Cost per Kg','hint'=>'Additional cost per Kg of Order items'));
        $this->addTableCol(array('id'=>'cost_volume','type'=>'DECIMAL','title'=>'Cost per Litre','hint'=>'Additional cost per litre of Order items'));
        $this->addTableCol(array('id'=>'cost_max','type'=>'DECIMAL','title'=>'Cost maximum','hint'=>'Maximum cost if base plus weighting exceeds, make 0 to ignore'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        
        $this->addSortOrder('T.location_id,T.option_id','Location then Option','DEFAULT');
        
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $sql_status = '(SELECT "OK") UNION (SELECT "HIDE")';
        $this->addSelect('status',$sql_status);

        //$this->addSearch(array('name','status'),array('rows'=>1));
    }
}
?>
