<?php
namespace App\Auction;

use Seriti\Tools\Tree;
//use Seriti\Tools\Crypt;
//use Seriti\Tools\Form;
//use Seriti\Tools\Secure;
//use Seriti\Tools\Audit;

class Category extends Tree
{
    protected $row_name = MODULE_AUCTION['labels']['category'];

    //configure
    public function setup($param = []) 
    {
        $param=['row_name'=>'Auction '.$this->row_name,'col_label'=>'title'];

        parent::setup($param); 

        $this->addMessage('NB:Order of '.$this->row_name.' is used in auction catalogues and online listings.');       

    }
}

?>