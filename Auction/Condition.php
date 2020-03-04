<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class Condition extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Lot condition','col_label'=>'name'];
        parent::setup($param);
                
        $this->addTableCol(array('id'=>'condition_id','type'=>'INTEGER','title'=>'Condition ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Abbreviation'));
        $this->addTableCol(array('id'=>'description','type'=>'STRING','title'=>'Description'));
        $this->addTableCol(array('id'=>'sort','type'=>'INTEGER','title'=>'Sort Order','hint'=>'Option display order in dropdowns'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));
        
        $this->addSortOrder('T.sort','Sort Order','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $sql_status = '(SELECT "OK") UNION (SELECT "HIDE")';
        $this->addSelect('status',$sql_status);

        $this->addSearch(array('name','status'),array('rows'=>1));
    }
}
?>
