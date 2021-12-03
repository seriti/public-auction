<?php 
namespace App\Auction;

use Seriti\Tools\Upload;
use Seriti\Tools\Secure;
use Seriti\Tools\Calc;

use Psr\Container\ContainerInterface;

class AuctionDocumentList extends Upload
{
    protected $auction_id;
    protected $id_prefix;
    
    //configure
    public function setup($param = []) 
    {
        $this->id_prefix = 'AUCD';

        if(!isset($param['auction_id'])) {
           $this->addError('NO auction specified'); 
        } else {
            $this->auction_id = Secure::clean('integer',$param['auction_id']);
            $location_id = $this->id_prefix.$this->auction_id;

            $this->addSql('WHERE','T.`location_id` = "'.$this->db->escapeSql($location_id).'" ');
        }
        
        $param = ['row_name'=>'Document',
                  'show_info'=>false,
                  'nav_show'=>'NONE',
                  'prefix'=>$this->id_prefix,
                  'excel_csv'=>false];
        parent::setup($param);
     
        //NB: only want to list and search images, NOTHING ELSE
        $access = ['read_only' => true];
        $this->modifyAccess($access);    
        
        //limit to web viewable images
        $this->allow_ext = array('Documents'=>array('doc','xls','ppt','pdf','rtf','docx','xlsx','pptx','ods','odt','txt','csv','zip','gz','msg','eml')); 


        //standard upload cols. just to disable listing of these values
        $this->addFileCol(['id'=>$this->file_cols['file_date'],'title'=>'File date','type'=>'DATE','list'=>false]);
        //$this->addFileCol(['id'=>$this->file_cols['file_size'],'title'=>'File size','type'=>'INTEGER','list'=>false]);
        //NB: only need to add non-standard file cols here, or if you need to modify standard file col setup
        //$this->addFileCol(array('id'=>'caption','type'=>'STRING','title'=>'Caption','upload'=>true,'required'=>false));

        //$this->addSearch(array('file_name_orig','caption'),array('rows'=>1));
        
    }

    protected function modifyRowValue($col_id,$data,&$value) {
        if($col_id === 'file_size') {
            $value = Calc::displayBytes($value);           
        }   
    } 

}  

?>
