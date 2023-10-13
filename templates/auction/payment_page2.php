<?php
use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Html;

use App\Auction\Helpers;

$list_param['class'] = 'form-control edit_input';
$text_param['class'] = 'form-control edit_input';
$textarea_param['class'] = 'form-control edit_input';


$invoice = $data['invoice']['invoice'];
$payments = $data['invoice']['payments'];
$no_lots = $data['invoice']['no_lots'];
$payment_total = $data['invoice']['payment_total'];
$payment_due = $data['pay_amount'];

//not used currently
$pay_type = $data['pay']['type_id'];
if($pay_type === 'EFT_TOKEN')  $button_text = 'Email me payment instructions.';
if($pay_type === 'GATEWAY_FORM')  $button_text = 'Proceed to '.$data['pay']['name'];

//NB: template for payments outside checkout wizard
?>

<div id="checkout_div">
  
  <div class="row">
    <div class="col-sm-6">
      <?php 
      $html = '';
      $html .= '<p>'.
               'Invoice Ref: <strong>'.$invoice['invoice_no'].'</strong><br/>'.
               'For Auction: <strong>'.$invoice['auction'].'</strong><br/>'.
               'Created on: <strong>'.Date::formatDate($invoice['date']).'</strong><br/> '.
               'Status: <strong>'.Helpers::getInvoiceStatusText($invoice['status']).'</strong><br/>'.
               'No. Lots: '.$no_lots.'<br/>'.
               'Sub Total: '.$invoice['sub_total'].'<br/>'.
               'Tax: '.$invoice['tax'].'<br/>'.
               'Total: '.CURRENCY_SYMBOL.$invoice['total'].'<br/>'.
               'Payments received: '.CURRENCY_SYMBOL.number_format($payment_total,2).'<br/>'.
               '<strong>Payment required: '.CURRENCY_SYMBOL.number_format($payment_due,2).'</strong><br/>'.
               '</p>';  
      echo $html;    
      ?>
    </div>

    <div class="col-sm-6">
      
      <?php 
     
      if($pay_type === 'GATEWAY_FORM') {
         echo '<h2>Payment gateway ready, click to proceed</h2>'; 
         echo $data['gateway_form']; 
      }
      
      if($pay_type === 'EFT_TOKEN') {
          echo '<h2>You have been emailed payment instructions.</h2>'; 
      } 
      
      ?>
    </div>

  </div>     

</div>