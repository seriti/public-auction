<?php
namespace App\Auction;

use Seriti\Tools\Table;
//use Seriti\Tools\Crypt;
//use Seriti\Tools\Form;
//use Seriti\Tools\Secure;
//use Seriti\Tools\Audit;

class Type extends Table
{
     

    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>TYPE_NAME,'col_label'=>'name'];
        parent::setup($param);
                
        $this->addTableCol(array('id'=>'type_id','type'=>'INTEGER','title'=>'Type ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
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