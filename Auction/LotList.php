<?php 
namespace App\Auction;

use Seriti\Tools\Listing;

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
use Seriti\Tools\CURRENCY_SYMBOL;

use Seriti\Tools\STORAGE;
//use Seriti\Tools\BASE_UPLOAD_WWW;

use Psr\Container\ContainerInterface;

class LotList extends Listing
{
    protected $auction_id;
    protected $table_prefix = MODULE_AUCTION['table_prefix'];
    //for storing linked auction details
    protected $auction;
    protected $access_custom;
    protected $user_id = 0;
    
    //configure
    public function setup($param = []) 
    {
        if(!isset($param['auction_id'])) {
           $this->addError('NO auction specified'); 
        } else {
            $this->auction_id = Secure::clean('integer',$param['auction_id']);
            //only list auction lots with status NOT = HIDE 
            $this->addSql('WHERE','T.`auction_id` = "'.$this->db->escapeSql($this->auction_id).'" AND T.`status` <> "HIDE"');
        }

        $this->setupAuction();


        if($this->auction['status'] === 'CLOSED') {
            $this->addMessage('The auction has been CLOSED');
            $this->addMessage('You can still view lots but bidding is disabled');
        }    

        $labels = MODULE_AUCTION['labels'];
        $images = MODULE_AUCTION['images'];
        $this->access_custom = MODULE_AUCTION['access'];

        $lot_id_display = false;
        $lot_no_display = true;

        $user = $this->getContainer('user');
        $this->user_id = $user->getId();
        
        $currency = 'R';

        $image_popup = ['show'=>true,'width'=>$images['width'],'height'=>$images['height']];

        //javascript to process add to bid form button
        //cart_icon.innerHTML = "WTF";
        $action_callback = 'var cart_icon = document.getElementById("menu_cart");
                            cart_icon.style.display="inline";
                           ';

        $param = ['row_name'=>'Lot','col_label'=>'name','show_header'=>false,'order_by'=>'name',
                  'image_pos'=>'LEFT','image_width'=>200,'no_image_src'=>BASE_URL.'images/no_image.png',
                  'image_popup'=>$image_popup,'format'=>'MERGE_COLS', //'format'=>'MERGE_COLS' or 'STANDARD'
                  'action_route'=>BASE_URL.'public/ajax?mode=list_add',
                  'action_callback'=>$action_callback,
                  'action_button_text'=>'Add to '.$labels['order']]; 
        parent::setup($param);

        $this->addListCol(array('id'=>'lot_id','type'=>'INTEGER','title'=>'Lot ID','key'=>true,'key_auto'=>true,'list'=>$lot_id_display));
        $this->addListCol(array('id'=>'lot_no','type'=>'INTEGER','title'=>'Lot No.','list'=>$lot_no_display));
        $this->addListCol(array('id'=>'category_id','type'=>'INTEGER','title'=>$labels['category'],'class'=>'list_item_title','list'=>true,'tree'=>'CT',
                                'join'=>'`title` FROM `'.$this->table_prefix.'category` WHERE `id`'));
        $this->addListCol(array('id'=>'name','type'=>'STRING','title'=>'Name','class'=>'list_item_title'));

        $this->addListCol(array('id'=>'type_id','type'=>'INTEGER','title'=>$labels['type'],
                                'join'=>'`name` FROM `'.$this->table_prefix.'type` WHERE `type_id`'));
        $this->addListCol(array('id'=>'type_txt1','type'=>'STRING','title'=>$labels['type_txt1']));
        //$this->addListCol(array('id'=>'type_txt2','type'=>'STRING','title'=>$labels['type_txt2']));
        $this->addListCol(array('id'=>'condition_id','type'=>'INTEGER','title'=>'Condition',
                                'join'=>'`name` FROM `'.$this->table_prefix.'condition` WHERE `condition_id`'));

        $this->addListCol(array('id'=>'description','type'=>'TEXT','title'=>'Description','class'=>'list_item_text'));
        $this->addListCol(array('id'=>'price_reserve','type'=>'DECIMAL','title'=>'Reserve Price','prefix'=>$currency));
        $this->addListCol(array('id'=>'price_estimate','type'=>'DECIMAL','title'=>'Estimate Price','prefix'=>$currency));
        $this->addListCol(array('id'=>'index_terms','type'=>'STRING','title'=>'Index terms','list'=>false));
        $this->addListCol(array('id'=>'status','type'=>'STRING','title'=>'Status','list'=>false));
        $this->addListCol(array('id'=>'bid_final','type'=>'DECIMAL','title'=>'Bid final','list'=>false));

        //NB: must have to be able to search on products below category_id in tree
        $this->addSql('JOIN','JOIN `'.$this->table_prefix.'category` AS CT ON(T.`category_id` = CT.`'.$this->tree_cols['node'].'`)');
        $this->addSql('JOIN','JOIN `'.$this->table_prefix.'condition` AS CN ON(T.`condition_id` = CN.`condition_id`)');

        //$this->addSql('JOIN','JOIN '.$this->table_prefix.'type AS L ON(T.type_id = L.type_id)');
        
        //NB: Lots will default to order on Lot No. unless alternative specified below
        $this->addSortOrder('T.`lot_no`','Lot Number','DEFAULT');

        //sort by primary category, then name, tyen description
        //$this->addSortOrder('CT.rank,T.name,T.description ',$labels['category'].', then Name then Description','DEFAULT');
        
        //if not using this sort order then JOIN to condition table above not necessary for CN.sort
        //$this->addSortOrder('CT.rank,T.type_txt1,T.type_txt2,CN.sort',$labels['category'].', then '.$labels['type_txt1'].', then '.$labels['type_txt2'].', then Condition','DEFAULT');

        //NB: Action column allows adding to cart with or without being logged in unless customListAction() return content.
        if($this->auction['status'] === 'CLOSED' and !$this->access_custom['show_sold_price']) {
            $show_action = false;  
        } else {
            $show_action = true; 
        }

        if($this->auction['status'] !== 'CLOSED' and $this->access_custom['login_before_bid'] and $this->user_id == 0) {
            //$show_action = false;
            $this->addMessage('You need to <a href="/login">login</a> before you can add Lots to your bid form.');
        }

        if($show_action) {
            $this->addListAction('submit',['type'=>'text','text'=>'','pos'=>'R']);      
        } 
        
        
        
        $sql_cat = 'SELECT `id`,CONCAT(IF(`level` > 1,REPEAT("--",`level` - 1),""),`title`) FROM `'.$this->table_prefix.'category`  ORDER BY `rank`';
        $this->addSelect('category_id',$sql_cat);

        $this->addSelect('type_id','SELECT `type_id`,`name` FROM `'.$this->table_prefix.'type` WHERE `status` <> "HIDE" ORDER BY `sort`');
        $this->addSelect('index_terms','SELECT `term_code`,`name` FROM `'.$this->table_prefix.'index_term` WHERE `status` <> "HIDE" ORDER BY `name`');
        $this->addSelect('condition_id','SELECT `condition_id`,`name` FROM `'.$this->table_prefix.'condition` WHERE `status` <> "HIDE" ORDER BY `sort`');
        
        //left out index_terms for now  removed('type_txt2')
        $this->addSearch(array('category_id','name','type_id','type_txt1','condition_id','description','index_terms'),array('rows'=>3));

        $this->setupListImages(array('table'=>$this->table_prefix.'file','location'=>'LOT','max_no'=>100,'manage'=>false,
                                     'list'=>true,'list_no'=>1,'storage'=>STORAGE,'title'=>'Lot','access'=>$images['access'],
                                     'link_url'=>'lot_image','link_data'=>'SIMPLE','width'=>$images['width'],'height'=>$images['height']));

        
       
    }

    //NB: If this returns any html then default actions NOT displayed
    protected function customListAction($data,$row_no,$pos) 
    {
        $html = '';

        //regardless of auction status
        $lot_sold = false;
        if($data['status'] === 'SOLD' or $data['bid_final'] > 0 ) {
            $lot_sold = true;
            if($this->access_custom['show_sold_price']) {
                $html .= '<strong>SOLD @'.CURRENCY_SYMBOL.$data['bid_final'].'</strong><br/>';
            } else {
                $html .= '<strong>SOLD</strong><br/>';
            }
            
        }        

        if($this->auction['status'] === 'CLOSED') {
            if(!$lot_sold) $html .= 'Lot not sold.';
            //just in case any error in logic above    
            if($html === '') $html .= 'Auction closed.';
        } else {
            if($html === '' and $this->user_id == 0 and $this->access_custom['login_before_bid']) {
                $html .= '<a href="/login">Login to Bid</a>';
            }    
        }

        return $html;   
    }

    protected function modifyRowFormatted($row_no,&$actions_left,&$actions_right,&$images,&$files,&$items)
    {
       $lot_id = $items[$this->key['id']]['value'];

       $gallery_link = '<a href="javascript:open_popup(\'image_popup?id='.$lot_id.'\','.$this->image_popup['width'].','.$this->image_popup['height'].')">'.
                        $this->icons['gallery'].'</a>';

       $items['name']['formatted'] .= '&nbsp;'.$gallery_link;
        
    }

    protected function addCustomNavigation($context)
    {
        $html = '';

        $page_count = ceil($this->row_count/$this->max_rows);

        $html.='<div class="well">All Pages: ';
        for($p = 1; $p <= $page_count; $p++) {
            if($p == $this->page_no) {
                $html .= '<h3>'.$p.'&nbsp;&nbsp;</h3>';
            } else {
                $html .= '<a class="nav_next" href="?mode=list&page='.$p.'">'.$p.'</a>&nbsp;&nbsp;'; 
            }
            
        }
        
        return $html;
    }

    protected function setupAuction() 
    {
        $sql = 'SELECT `name`,`summary`,`description`,`date_start_postal`,`date_start_live`,`status` '.
               'FROM `'.$this->table_prefix.'auction` '.
               'WHERE `auction_id` = "'.$this->db->escapeSql($this->auction_id).'" ';
        $this->auction = $this->db->readSqlRecord($sql);
    }

    public function getAuction() 
    {
        return $this->auction;
    }
}  

?>
