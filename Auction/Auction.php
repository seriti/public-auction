<?php 
namespace App\Auction;

use Seriti\Tools\Table;
use Seriti\Tools\STORAGE;

use App\Auction\Helpers;

class Auction extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Auction','col_label'=>'name'];
        parent::setup($param);
                
        $this->addForeignKey(array('table'=>TABLE_PREFIX.'order','col_id'=>'auction_id','message'=>'Auction Order'));

        $this->addTableCol(array('id'=>'auction_id','type'=>'INTEGER','title'=>'Auction ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'summary','type'=>'TEXT','title'=>'Summary'));
        $this->addTableCol(array('id'=>'description','type'=>'TEXT','title'=>'Description'));
        $this->addTableCol(array('id'=>'date_start_postal','type'=>'DATETIME','title'=>'Date start postal'));
        $this->addTableCol(array('id'=>'date_start_live','type'=>'DATETIME','title'=>'Date start_live'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status','new'=>'NEW'));
        
        //$this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));
        
        $sql_status = '(SELECT "NEW") UNION (SELECT "ACTIVE") UNION (SELECT "CLOSED") UNION (SELECT "HIDE")';
        $this->addSelect('status',$sql_status);
        
        $this->addSearch(array('name','summary','description','date_start_postal','date_start_live','status'),array('rows'=>2));

        $this->setupFiles(array('table'=>TABLE_PREFIX.'file','location'=>'AUCD','max_no'=>10,
                                  'icon'=>'<span class="glyphicon glyphicon-file" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>1,'storage'=>STORAGE,
                                  'link_url'=>'auction_file','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));

        $this->setupImages(array('table'=>TABLE_PREFIX.'file','location'=>'AUC','max_no'=>10,
                                  'icon'=>'<span class="glyphicon glyphicon-picture" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>1,'storage'=>STORAGE,
                                  'link_url'=>'auction_file','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));
    }

    protected function beforeUpdate($id,$context,&$data,&$error) 
    {
        //NB: THIS ALSO HAPPENS AT INVOICE CREATION TIME AND WILL OVERWRITE ANYTHING SET HERE. INTENDED AS A LIVE UPDATE TO ONLINE USERS 
        Helpers::updateAuctionStatus($this->db,$id,$data['status'],$error);

    }
}
?>
