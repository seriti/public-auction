<?php 
namespace App\Auction;

use Seriti\Tools\Upload;

class AuctionFile extends Upload 
{
  //configure
    public function setup($param = []) 
    {
        //need different prefix for Documents 
        $id_prefix = 'AUCD'; 

        $param = ['row_name'=>'Document',
                  'pop_up'=>true,
                  'col_label'=>'file_name_orig',
                  'update_calling_page'=>true,
                  'prefix'=>$file_prefix];
        parent::setup($param);

        $this->allow_ext = array('Documents'=>array('doc','xls','ppt','pdf','rtf','docx','xlsx','pptx','ods','odt','txt','csv','zip','gz','msg','eml')); 

        $param = [];
        $param['table']     = TABLE_PREFIX.'auction';
        $param['key']       = 'auction_id';
        $param['label']     = 'name';
        $param['child_col'] = 'location_id';
        $param['child_prefix'] = $id_prefix ;
        $param['show_sql'] = 'SELECT CONCAT("Documents for Auction: ",name) FROM '.TABLE_PREFIX.'auction WHERE auction_id = "{KEY_VAL}"';
        $this->setupMaster($param);

        $this->addAction(array('type'=>'edit','text'=>'edit details of','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R','icon_text'=>'delete'));

        $this->info['ADD'] = 'If you have Mozilla Firefox or Google Chrome you should be able to drag and drop files directly from your file explorer.'.
                             'Alternatively you can click [Add Documents] button to select multiple documents for upload using [Shift] or [Ctrl] keys. '.
                             'Finally you need to click [Upload selected Documents] button to upload documents to server.';

        //$access['read_only'] = true;                         
        //$this->modifyAccess($access); p
    }
}
?>
