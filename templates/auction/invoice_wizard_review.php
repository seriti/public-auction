<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$html = '';

//get wizard variables and daata
$client = $data['client'];
$items = $data['items'];
$item_total = $data['item_total'];

$email = $form['email'];
$email_xtra = $form['email_xtra'];
$from = $form['from_date'];
$to = $form['to_date'];
$vat = $form['vat'];

//identify client and invoice dates
$html .= '<h1>'.$client['name'].'</h1>';

if($form['show_period'] === 'YES') {
  $html .= '<h1>For period: from '.$from.' to '.$to.'</h1>'; 
}  
  
$html .= '<table>'.
         '<tr><td>FOR: </td><td><strong>'.$form['invoice_for'].'</strong></td></tr>'.
         '<tr><td>COMMENT: </td><td><strong>'.nl2br($form['invoice_comment']).'</strong></td></tr>'.
         '<tr><td colspan="2">'.$data['upload_div'].'</td></tr>'.
         '</table>';

$item_no = count($items[0])-1; //first line contains headers for pdf
$html .= '<table class="table"><tr><th>Quantity</th><th width="300">Description</th><th>Unit price</th><th>Total</th></tr>';
for($i = 1; $i <= $item_no; $i++) {
  if($items[0][$i] != 0) {
    $html .= '<tr><td>'.$items[0][$i].'</td>'.
             '<td>'.$items[1][$i].'</td>'.
             '<td>'.number_format($items[2][$i],2).'</td>'.
             '<td>'.number_format($items[3][$i],2).'</td>'.
             '</tr>';
  }               
}  

//display subtotals/vat/totals
$total = $item_total+$vat;
$html .= '<tr><td colspan="2"></td><td>Subtotal</td><td><strong>'.number_format($item_total,2).'</strong></td></tr>'.
         '<tr><td colspan="2"></td><td>VAT</td><td><strong>'.number_format($vat,2).'</td></tr>'.
         '<tr><td colspan="2"></td><td>TOTAL</td><td><strong>'.number_format($total,2).'</td></tr>';
        
$html  .= '</table>';
 
$html .= '<br/>'.
         'Email invoice to:<strong>'.$form['email'].'</strong><br/>'.
         'Email COPY to:<strong>'.$form['email_xtra'].'</strong><br/>'.
         '<input type="submit" id="submit_button" value="Create Invoice PDF and email to above addresses with any supporting documents" 
           class="btn btn-primary" onclick="link_download(\'submit_button\');"><br/>';


$html .= $javascript;

echo $html;          

//print_r($data);
?>

