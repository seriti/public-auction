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

        //(block_id,col,row,title)
        $this->addBlock('ADD',1,1,'Capture data');
        $this->addItem('ADD','Add a new Lot',['link'=>"lot?mode=add"]);
        $this->addItem('ADD','Add a new Payment',['link'=>"payment?mode=add"]);
        
        $this->addBlock('AUCTION',2,1,'Auction processes');
        $this->addItem('AUCTION','View all bids',['link'=>'lot_bid']);
        $this->addItem('AUCTION','Capture multiple auction results',['link'=>'lot_auction']);
        $this->addItem('AUCTION','Invoice a user/order',['link'=>'invoice_wizard']);
        $this->addItem('AUCTION','Assign Lot numbers',['link'=>'lot_no']);
        $this->addItem('AUCTION','Assign Online bid results to lots & clear any unprocessed carts',['link'=>'lot_result']);

        $this->addBlock('USER',1,2,'System Users');
        $this->addItem('USER','User settings',['link'=>'user_extend']);
        $this->addItem('USER','Link users to UNlinked '.MODULE_AUCTION['labels']['order'].'s',['link'=>'order_orphan']);

        if($login_user->getAccessLevel() === 'GOD') {
            $this->addBlock('CONFIG',1,3,'Module Configuration');
            $this->addItem('CONFIG','Setup Database',['link'=>'setup_data','icon'=>'setup']);
            $this->addItem('CONFIG','Setup Defaults',['link'=>'setup','icon'=>'setup']);
        }    
        
    }

}

?>