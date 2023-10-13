<?php
use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Html;

use App\Auction\Helpers;

$list_param['class'] = 'form-control edit_input';

$invoice = $data['invoice']['invoice'];
$payments = $data['invoice']['payments'];
$no_lots = $data['invoice']['no_lots'];
$payment_total = $data['invoice']['payment_total'];
$payment_due = $data['pay_amount'];//$invoice['total'] - $payment_total;

//NB: template for payments outside checkout wizard
?>

<div id="checkout_div">

  <p>
  <?php
  echo '<h2>Hi there <strong>'.$data['user_name'].'</strong>. please proceed with payment process for invoice.</h2>';
  ?>
  <br/>
  </p>
  
  <div class="row">
    <div class="col-sm-3">
    <?php 
    $html = '<h2>Invoice details:</h2>';
    $html .= '<p>'.
             'Invoice No: <strong>'.$invoice['invoice_no'].'</strong><br/>'.
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
    <div class="col-sm-3">
    <?php 
    $html = '<h2>Lots included:</h2>';
    $html .= $data['lots'];
    echo $html;
    ?>
    </div>
    <div class="col-sm-3">
    <?php 
    $button_txt = 'Proceed with '.CURRENCY_SYMBOL.number_format($payment_due,2).' payment'; 
    echo '<h2>Payment options:</h2>';
    $table = MODULE_AUCTION['table_prefix'].'pay_option';
    $sql = 'SELECT option_id, name FROM '.$table.' WHERE provider_code <> "NONE" AND status = "OK" ORDER BY sort';
    echo Form::sqlList($sql,$db,'pay_option_id',$form['pay_option_id'],$list_param);
    echo '<input type="submit" name="Submit" value="'.$button_txt.'" class="btn btn-primary">';
    ?>
    </div>  
  </div>
  
</div>