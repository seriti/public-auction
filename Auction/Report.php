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

        //$param = ['input'=>['select_admin_user','select_dates','format']];
        //$this->addReport('LOTS_CAPTURED','Auction lots created by admin user',$param); 
        
        $param = ['input'=>['select_auction','select_seller','format']];
        $this->addReport('SELLER_IOU','Auction Seller IOU',$param); 

        $param = ['input'=>['select_auction','format']];
        $this->addReport('AUCTION_STATS','View Auction Statistics',$param); 
        $this->addReport('AUCTION_PDF','Create Auction Lots listing PDF',$param); 
        $this->addReport('AUCTION_REALISED_PDF','Create REALISED Auction lots listing PDF',$param); 
        $this->addReport('AUCTION_REALISED_SMALL','Create REALISED COMPRESSED Auction lots listing PDF',$param);
        $this->addReport('AUCTION_MASTER_PDF','Create MASTER Auction lots listing PDF',$param);

        
        $this->addReport('AUCTION_SELLER','Create Auction Sellers Lots listing',$param);  

        $this->addReport('AUCTION_BUYER_INVOICE','Create Auction Buyers Invoice Lots listing',$param);  
        
        //$param = ['input'=>['select_user','select_month_period']];
        //$this->addReport('ORDERS_NEW','Monthly performance over period',$param);
    
        $this->addInput('select_auction','');
        $this->addInput('select_user','');
        $this->addInput('select_admin_user','');
        $this->addInput('select_seller','');
        $this->addInput('select_dates','Select date period');
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

        if($id === 'select_admin_user') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $sql = 'SELECT user_id,name FROM '.TABLE_USER.' WHERE zone = "ADMIN" AND status <> "HIDE" ORDER BY name'; 
            if(isset($form['admin_user_id'])) $admin_user_id = $form['admin_user_id']; else $admin_user_id = '';
            $html .= Form::sqlList($sql,$this->db,'admin_user_id',$admin_user_id,$param);
        }

        if($id === 'select_seller') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $sql = 'SELECT seller_id,name FROM '.TABLE_PREFIX.'seller WHERE status <> "HIDE" ORDER BY name'; 
            if(isset($form['seller_id'])) $seller_id = $form['seller_id']; else $seller_id = '';
            $html .= Form::sqlList($sql,$this->db,'seller_id',$seller_id,$param);
        }

        if($id === 'select_dates') {
            $param = [];
            $param['class'] = 'form-control bootstrap_date input-small';

            $date = getdate();

            if(isset($form['from_date'])) {
                $from_date = $form['from_date'];
            } else {
                $from_date = date('Y-m-d',mktime(0,0,0,$date['mon']-1,$date['mday'],$date['year']));;
            }

            if(isset($form['to_date'])) {
                $to_date = $form['to_date'];
            } else {
                $to_date = date('Y-m-d');;
            }     
            
            $html .= '<table>
                        <tr>
                          <td align="right" valign="top" width="20%"><b>From date : </b></td>
                          <td>'.Form::textInput('from_date',$from_date,$param).'</td>
                        </tr>
                        <tr>
                          <td align="right" valign="top" width="20%"><b>To date : </b></td>
                          <td>'.Form::textInput('to_date',$to_date,$param).'</td>
                        </tr>
                     </table>';
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
        
        if($id === 'AUCTION_STATS') {
            $html .= HelpersReport::AuctionStatistics($this->db,$form['auction_id'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'INVOICES_ISSUED') {
            $html .= HelpersReport::invoiceReport($this->db,'ALL',$form['user_id'],$form['auction_id'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'ORDERS_CREATED') {
            $html .= HelpersReport::orderReport($this->db,'ALL',$form['user_id'],$form['auction_id'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'LOTS_CAPTURED') {
            $html .= HelpersReport::lotCaptureReport($this->db,$form['admin_user_id'],$form['from_date'],$form['to_date'],$options,$error);
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

        if($id === 'AUCTION_REALISED_SMALL') {   
            $system = $this->container['system'];
            $pdf_name = '';

            $options['layout'] = 'COMPRESSED';
            if($id === 'AUCTION_REALISED_SMALL') $options['layout'] = 'COMPRESSED';
            
            $index = HelpersReport::createAuctionSummary($this->db,$system,$form['auction_id'],$options,$pdf_name,$error);
            if($error !== '') {
                $this->addError($error);
            } else {
                $this->addMessage('Created PDF successfully: '.$pdf_name) ;              
            }
        }

        if($id === 'AUCTION_SELLER') {
            $html .= HelpersReport::auctionSellerReport($this->db,$form['auction_id'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'AUCTION_BUYER_INVOICE') {
            $pdf_name = '';
            $html .= HelpersReport::buyerInvoiceLotsReport($this->db,$form['auction_id'],$options,$pdf_name,$error);
            if($error !== '') $this->addError($error);
        }

        
        return $html;
    }

}

?>