<?php 
namespace App\Auction;

use Seriti\Tools\Upload;

//use Seriti\Tools\Form;
use Seriti\Tools\Secure;
//use Seriti\Tools\Template;
//use Seriti\Tools\Image;
//use Seriti\Tools\Calc;
//use Seriti\Tools\Menu;

//use Seriti\Tools\DbInterface;
//use Seriti\Tools\IconsClassesLinks;
//use Seriti\Tools\MessageHelpers;
//use Seriti\Tools\ContainerHelpers;
//use Seriti\Tools\STORAGE;
//use Seriti\Tools\UPLOAD_DOCS;
//use Seriti\Tools\BASE_PATH;
//use Seriti\Tools\BASE_TEMPLATE;
use Seriti\Tools\BASE_URL;

use Seriti\Tools\STORAGE;
//use Seriti\Tools\BASE_UPLOAD_WWW;

use Psr\Container\ContainerInterface;

class AuctionImageList extends Upload
{
    protected $auction_id;
    protected $id_prefix;
    
    //configure
    public function setup($param = []) 
    {
        $this->id_prefix = 'AUC';

        if(!isset($param['auction_id'])) {
           $this->addError('NO auction specified'); 
        } else {
            $this->auction_id = Secure::clean('integer',$param['auction_id']);
            $location_id = $this->id_prefix.$this->auction_id;

            $this->addSql('WHERE','T.location_id = "'.$this->db->escapeSql($location_id).'" ');
        }
             

        $param = ['row_name'=>'Image',
                  'show_info'=>false,
                  'prefix'=>$this->id_prefix];
        parent::setup($param);
     
        //NB: only want to list and search images, NOTHING ELSE
        $access = ['read_only' => true];
        $this->modifyAccess($access);    

        //thumbnail display parameters           
        $thumbnail = ['list_view'=>true,'edit_view'=>true,
                      'list_width'=>120,'list_height'=>0,'edit_width'=>0,'edit_height'=>0];

        parent::setupImages(['resize'=>$resize,'thumbnail'=>$thumbnail]);

        //limit to web viewable images
        $this->allow_ext = array('Images'=>array('jpg','jpeg','gif','png')); 

        //standard upload cols. just to disable listing of these values
        $this->addFileCol(['id'=>$this->file_cols['file_date'],'title'=>'File date','type'=>'DATE','list'=>false]);
        $this->addFileCol(['id'=>$this->file_cols['file_size'],'title'=>'File size','type'=>'INTEGER','list'=>false]);
        //NB: only need to add non-standard file cols here, or if you need to modify standard file col setup
        $this->addFileCol(array('id'=>'caption','type'=>'STRING','title'=>'Caption','upload'=>true,'required'=>false));

        $this->addSearch(array('file_name_orig','caption'),array('rows'=>1));
        
    }
}  

?>
