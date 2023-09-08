<?php
namespace App\Auction;

use Seriti\Tools\Form;
use Seriti\Tools\Task as SeritiTask;

class Task extends SeritiTask
{
    protected $labels = MODULE_AUCTION['labels'];

    //configure
    public function setup($param = []) 
    {
        $this->col_count = 2;  

        $login_user = $this->getContainer('user'); 

        $this->addBlock('AUCTION',1,1,'Manage auctions');
        $this->addTask('AUCTION','CHANGE_AUCTION','Change active auction');
        $this->addTask('AUCTION','EDIT_AUCTION','Edit <strong>'.AUCTION_NAME.'</strong> details');
        $this->addTask('AUCTION','ALL_AUCTIONS','Manage ALL auctions');
        $this->addTask('AUCTION','ADD_AUCTION','Add a new auction');
        $this->addTask('AUCTION','AUCTION_SETUP','All Auctions defaults');

        $this->addBlock('LOT',2,1,'Manage lot setup');
        $this->addTask('LOT','LOT_ARCHIVE','View all archived lots'); 
        $this->addTask('LOT','LOT_SELLER','Manage lot sellers'); 
        $this->addTask('LOT','LOT_CONDITION','Manage lot conditions options');    
        $this->addTask('LOT','LOT_INDEX','Manage lot index terms'); 
        $this->addTask('LOT','LOT_CATEGORY','Manage lot '.$this->labels['category']); 
        $this->addTask('LOT','LOT_TYPE','Manage lot '.$this->labels['type']); 

        if($login_user->getAccessLevel() === 'GOD') {
            $this->addBlock('SHIP',1,2,'Setup shipping');
            $this->addTask('SHIP','SHIP_OPTIONS','Shipping options');
            $this->addTask('SHIP','SHIP_LOCATIONS','Shipping locations');
            $this->addTask('SHIP','SHIP_COSTS','Shipping costs');

            $this->addBlock('PAYMENT',2,2,'Setup payment');
            $this->addTask('PAYMENT','PAY_OPTIONS','Payment options');

            $this->addBlock('USER',1,3,'User setup');
            $this->addTask('USER','USER_CLEAR','Remove orphaned user settings');

            //$this->addBlock('IMPORT',1,2,'Import products');
            //$this->addTask('IMPORT','IMPORT_PRODUCT','Import product data');
        }    
        
    }

    public function processTask($id,$param = []) {
        $error = '';
        $error_tmp = '';
        $message = '';
        $n = 0;
        
        if($id === 'EDIT_AUCTION') {
            $location = 'auction?mode=edit&id='.AUCTION_ID;
            header('location: '.$location);
            exit;
        }
        
        if($id === 'ADD_AUCTION') {
            $location = 'auction?mode=add';
            header('location: '.$location);
            exit;
        }

        if($id === 'ALL_AUCTIONS') {
            $location = 'auction';
            header('location: '.$location);
            exit;
        }

        if($id === 'AUCTION_SETUP') {
            $location = 'setup';
            header('location: '.$location);
            exit;
        }

        if($id === 'LOT_ARCHIVE') {
            $location = 'lot_archive';
            header('location: '.$location);
            exit;
        }

        if($id === 'LOT_SELLER') {
            $location = 'seller';
            header('location: '.$location);
            exit;
        }


        if($id === 'LOT_CONDITION') {
            $location = 'condition';
            header('location: '.$location);
            exit;
        }

        if($id === 'LOT_CATEGORY') {
            $location = 'category';
            header('location: '.$location);
            exit;
        }

        if($id === 'LOT_TYPE') {
            $location = 'type';
            header('location: '.$location);
            exit;
        }

        if($id === 'LOT_INDEX') {
            $location = 'index_term';
            header('location: '.$location);
            exit;
        }
        
        if($id === 'SHIP_OPTIONS') {
            $location = 'ship_option';
            header('location: '.$location);
            exit;
        }

        if($id === 'SHIP_LOCATIONS') {
            $location = 'ship_location';
            header('location: '.$location);
            exit;
        }

        if($id === 'SHIP_COSTS') {
            $location = 'ship_cost';
            header('location: '.$location);
            exit;
        }
        
        if($id === 'PAY_OPTIONS') {
            $location = 'pay_option';
            header('location: '.$location);
            exit;
        }

        if($id === 'USER_CLEAR') {
            if(!isset($param['process'])) $param['process'] = false;  
                    
            if($param['process'] === 'clear') {
                $recs = Helpers::cleanUserData($this->db,$error_tmp);
                if($error_tmp === '') {
                    $this->addMessage('SUCCESSFULY removed '.$recs.' orphaned user setting records!');
                } else {
                    $error = 'Could not remove orphaned user data';
                    if($this->debug) $error .= ': '.$error_tmp;
                    $this->addError($error);   
                }     
            } else {
                $html = '';
                $class = 'form-control input-small';
                $html .= 'Please confirm that you want to remove all user settings where no valid user exists.<br/>'.
                         '<form method="post" action="?mode=task&id='.$id.'" enctype="multipart/form-data">'.
                         '<input type="hidden" name="process" value="clear"><br/>'.
                         '<input type="submit" name="submit" value="CLEAR ORPHANED RECORDS" class="'.$this->classes['button'].'">'.
                         '</form>';

                //display form in message box       
                $this->addMessage($html);      
            }
        }

        if($id === 'CHANGE_AUCTION') {
            if(!isset($param['process'])) $param['process'] = false;  
            if(!isset($param['company_id'])) $param['company_id'] = '';
        
            if($param['process'] === 'change') {
                $cache = $this->getContainer('cache');  
                $auction_id = $param['auction_id']; 
                $cache->store('auction_id',$auction_id);      
        
                $location = 'lot';
                header('location: '.$location);
                exit;             
            } else {
                $sql = 'SELECT auction_id,name FROM '.TABLE_PREFIX.'auction ORDER BY auction_id DESC';
                $list_param = array();
                $list_param['class'] = 'form-control input-large';
            
                $html = '';
                $class = 'form-control input-small';
                $html .= 'Please select Auction that you wish to work on.<br/>'.
                         '<form method="post" action="?mode=task&id='.$id.'" enctype="multipart/form-data">'.
                         '<input type="hidden" name="process" value="change"><br/>'.
                         'Select Company: '.Form::sqlList($sql,$this->db,'auction_id',$param['auction_id'],$list_param).
                         '<input type="submit" name="submit" value="CHANGE ACTIVE" class="'.$this->classes['button'].'">'.
                         '</form>'; 
                //display form in message box       
                $this->addMessage($html);      
            }  
        } 
           
    }

}

?>