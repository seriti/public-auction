<?php
namespace App\Auction;

use Seriti\Tools\CURRENCY_ID;
use Seriti\Tools\Form;
use Seriti\Tools\Report AS ReportTool;

use App\Auction\Helpers;
use App\Auction\HelpersReport;

class Report extends ReportTool
{
     

    //configure
    public function setup() 
    {
        //$this->report_header = 'WTF';
        $param = [];
        $this->report_select_title = 'Select report';
        $this->always_list_reports = true;

        $param = ['input'=>['select_auction','select_user','format']];
        $this->addReport('INVOICES_ISSUED','Auction invoices issued',$param); 
        $this->addReport('ORDERS_CREATED','Auction orders created',$param); 

        $param = ['input'=>['select_auction','select_seller','format']];
        $this->addReport('SELLER_IOU','Auction Seller IOU',$param); 

        $param = ['input'=>['select_auction','format']];
        $this->addReport('AUCTION_PDF','Create Auction lots listing PDF',$param); 
        $this->addReport('AUCTION_REALISED_PDF','Create REALISED Auction lots listing PDF',$param); 
        $this->addReport('AUCTION_MASTER_PDF','Create MASTER Auction lots listing PDF',$param); 
        
        //$param = ['input'=>['select_user','select_month_period']];
        //$this->addReport('ORDERS_NEW','Monthly performance over period',$param);
    
        $this->addInput('select_auction','');
        $this->addInput('select_user','');
        $this->addInput('select_seller','');
        $this->addInput('select_date_create','');
        $this->addInput('select_format','');
    }

    protected function viewInput($id,$form = []) 
    {
        $html = '';
        
        if($id === 'select_auction') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $param['xtra'] = ['ALL'=>'All auctions'];
            $sql = 'SELECT auction_id,name FROM '.TABLE_PREFIX.'auction WHERE status <> "HIDE" ORDER BY name'; 
            if(isset($form['auction_id'])) $auction_id = $form['auction_id']; else $auction_id = 'ALL';
            $html .= Form::sqlList($sql,$this->db,'auction_id',$auction_id,$param);
        }

        if($id === 'select_user') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $param['xtra'] = ['ALL'=>'All users'];
            $sql = 'SELECT user_id,CONCAT(name,":",email) FROM '.TABLE_USER.' WHERE zone = "PUBLIC" AND status <> "HIDE" ORDER BY name'; 
            if(isset($form['user_id'])) $user_id = $form['user_id']; else $user_id = 'ALL';
            $html .= Form::sqlList($sql,$this->db,'user_id',$user_id,$param);
        }

        if($id === 'select_seller') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $sql = 'SELECT seller_id,name FROM '.TABLE_PREFIX.'seller WHERE status <> "HIDE" ORDER BY name'; 
            if(isset($form['seller_id'])) $seller_id = $form['seller_id']; else $seller_id = '';
            $html .= Form::sqlList($sql,$this->db,'seller_id',$seller_id,$param);
        }

        if($id === 'select_date_create') {
            $param = [];
            $param['class'] = $this->classes['date'];
            if(isset($form['date_create'])) $date_create = $form['date_create']; else $date_create = date('Y-m-d',mktime(0,0,0,date('m')-12,date('j'),date('Y')));
            $html .= Form::textInput('date_create',$date_create,$param);
        }

       
        if($id === 'select_format') {
            if(isset($form['format'])) $format = $form['format']; else $format = 'HTML';
            $html.= Form::radiobutton('format','PDF',$format).'&nbsp;<img src="/images/pdf_icon.gif">&nbsp;PDF document<br/>';
            $html.= Form::radiobutton('format','CSV',$format).'&nbsp;<img src="/images/excel_icon.gif">&nbsp;CSV/Excel document<br/>';
            $html.= Form::radiobutton('format','HTML',$format).'&nbsp;Show on page<br/>';
        }

        return $html;       
    }

    protected function processReport($id,$form = []) 
    {
        $html = '';
        $error = '';
        $options = [];
        $pdf_name = '';
        $options['format'] = $form['format'];
        
        if($id === 'INVOICES_ISSUED') {
            $html .= HelpersReport::invoiceReport($this->db,'ALL',$form['user_id'],$form['auction_id'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'ORDERS_CREATED') {
            $html .= HelpersReport::orderReport($this->db,'ALL',$form['user_id'],$form['auction_id'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'SELLER_IOU') {
            $system = $this->container['system'];
            $pdf_name = '';

            $index = HelpersReport::createAuctionSellerPdf($this->db,$system,$form['auction_id'],$form['seller_id'],$options,$pdf_name,$error) ;
            if($error !== '') {
                $this->addError($error);
            } else {
                $this->addMessage('Created PDF successfully: '.$pdf_name) ;              
            }
        }

        if($id === 'AUCTION_PDF' or $id === 'AUCTION_REALISED_PDF' or $id === 'AUCTION_MASTER_PDF') {   
            $system = $this->container['system'];
            $pdf_name = '';

            $options['layout'] = 'STANDARD';
            if($id === 'AUCTION_REALISED_PDF') $options['layout'] = 'REALISED';
            if($id === 'AUCTION_MASTER_PDF') $options['layout'] = 'MASTER';

            $index = HelpersReport::createAuctionCatalogPdf($this->db,$system,$form['auction_id'],$options,$pdf_name,$error);
            if($error !== '') {
                $this->addError($error);
            } else {
                $this->addMessage('Created PDF successfully: '.$pdf_name) ;              
            }
            
            /*
            $index = HelpersReport::buildAuctionIndex($this->db,$form['auction_id']); 
            foreach($index as $term=>$lots) {
                $html.="Term: $term (".implode(',',$lots).')<br/>';
            }
            */

                
        }

        
        return $html;
    }

}

?>