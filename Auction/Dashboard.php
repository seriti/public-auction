<?php
namespace App\Auction;

use Seriti\Tools\Dashboard AS DashboardTool;

class Dashboard extends DashboardTool
{
     

    //configure
    public function setup($param = []) 
    {
        $this->col_count = 2;  

        $login_user = $this->getContainer('user'); 
        $user_access = $login_user->getAccessLevel();

        $access = MODULE_AUCTION['access'];

        //(block_id,col,row,title)
        $this->addBlock('ADD',1,1,'Capture data');
        $this->addItem('ADD','Add a new Lot',['link'=>"lot?mode=add"]);
        $this->addItem('ADD','Add a new Payment',['link'=>"payment?mode=add"]);
        
        $this->addBlock('AUCTION',2,1,'Auction processes');
        $this->addItem('AUCTION','View all bids',['link'=>'lot_bid']);
        $this->addItem('AUCTION','Live auction- All bids PDF',['link'=>'lot_bid_pdf']);
        $this->addItem('AUCTION','Notify users with losing bids',['link'=>'lot_notify_outbid','link_confirm'=>'Are you sure? This will send multiple emails to users.']);
        $this->addItem('AUCTION','Capture multiple auction results',['link'=>'lot_auction']);
        $this->addItem('AUCTION','Invoice a user/order',['link'=>'invoice_wizard']);
        
        $this->addItem('AUCTION','Assign Lot numbers',['link'=>'lot_no','link_confirm'=>'Are you sure? Once orders linked to assigned lot numbers you cannot change.']);
        $this->addItem('AUCTION','Assign Online bid results to lots & clear any unprocessed carts',['link'=>'lot_result','link_confirm'=>'Are you sure? Any active shopping carts will be erased without option to restore.']);

        $this->addBlock('USER',1,2,'System Users');
        $this->addItem('USER','User settings',['link'=>'user_extend']);
        $this->addItem('USER','Reset user buyer nos',['link'=>'user_reset','link_confirm'=>'Are you sure you want to remove all buyer nos?']);
        if($access['login_before_bid']) {
            $title = 'Confirm UN-checked out user '.MODULE_AUCTION['labels']['order'].'s';
        } else {
            $title = 'Link users to UN-checked out '.MODULE_AUCTION['labels']['order'].'s';
        }
        $this->addItem('USER',$title,['link'=>'order_orphan']);

        if($user_access === 'GOD') {
            $this->addBlock('CONFIG',1,3,'Module Configuration');
            $this->addItem('CONFIG','Setup Database',['link'=>'setup_data','icon'=>'setup']);
            $this->addItem('CONFIG','Setup Defaults',['link'=>'setup','icon'=>'setup']);
        }    
        
    }

}

?>