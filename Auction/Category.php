<?php
namespace App\Auction;

use Seriti\Tools\Tree;
//use Seriti\Tools\Crypt;
//use Seriti\Tools\Form;
//use Seriti\Tools\Secure;
//use Seriti\Tools\Audit;

class Category extends Tree
{
     

    //configure
    public function setup($param = []) 
    {
        $param=['row_name'=>'Auction '.CATEGORY_NAME,'col_label'=>'title'];

        parent::setup($param); 

        $this->addMessage('NB:Order of '.CATEGORY_NAME.' is used in auction catalogues and online listings.');       

    }
}

?>