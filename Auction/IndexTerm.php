<?php 
namespace App\Auction;

use Seriti\Tools\Table;

class IndexTerm extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Term','col_label'=>'name'];
        parent::setup($param);
                
        $this->addTableCol(array('id'=>'term_id','type'=>'INTEGER','title'=>'Term ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'term_code','type'=>'STRING','title'=>'Term code','hint'=>'(Unique code which you can use in lot index terms field)'));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
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
