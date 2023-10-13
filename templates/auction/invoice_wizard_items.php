<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$html = '';

//get wizard variables and daata
$user = $data['user'];
$items = $data['items'];
$item_total = $data['item_total'];
$item_weight = $data['item_weight'];
$item_volume = $data['item_volume'];
$item_vat = $data['item_vat'];

if($form['email'] !== '') $email = $form['email']; else $email = $user['email'];
if($form['email_xtra'] !== '') $email_xtra = $form['email_xtra']; else $email_xtra = $user['email_alt'];
//dont want duplicate emails being sent
if($email_xtra === $email) $email_xtra = '';

if($form['vat'] !== '') $vat = $form['vat']; else $vat = $item_vat;
if($form['invoice_comment'] !== '') $comment = $form['invoice_comment']; else $comment = '';

//identify user and invoice dates
$html_items = '';
$html_items .= '<h1>'.$user['name'].' Auction: '.AUCTION_NAME.'</h1>';

$param['class'] = 'form-control';
  
$html_items .= '<table>'.
               '<tr><td>FOR: </td><td>'.Form::textAreaInput('invoice_for',$user['name_invoice'],40,'',$param).'</td></tr>'.
               '<tr><td>COMMENT: </td><td>'.Form::textAreaInput('invoice_comment',$comment,40,'',$param).'</td></tr>'.
               '</table>';

$item_no = count($items[0])-1; //first line contains headers for pdf
$item_total = 0;
$html_items .= '<table><tr><th>Quantity</th><th width="300">Description</th><th>Unit price</th><th>Total</th></tr>';

for($i = 1; $i <= $item_no; $i++) {
  //to stop display of zeros
  if($items[0][$i] == 0) $items[0][$i] = ''; 

  //preserve description for linked lots as lot_id used to update final bid price
  $lot_id = $items[4][$i];
  if($lot_id != 0) {
    $value = str_replace('"','&quot;',$items[1][$i]);
    $desc_input = '<input type="hidden" name="desc_'.$i.'" value="'.$value.'">'.$value;
  } else {
    $desc_input = Form::textInput('desc_'.$i,$items[1][$i],$param);
  }  

  $html_items.='<tr><td>'.Form::textInput('quant_'.$i,$items[0][$i],$param).'</td>'.
               '<td>'.$desc_input.'</td>'.
               '<td>'.Form::textInput('price_'.$i,$items[2][$i],$param).'</td>'.
               '<td>'.Form::textInput('total_'.$i,$items[3][$i],$param).'</td>'.
               '</tr>';
  $item_total += (float)$items[3][$i]; 
}  
 

//display subtotals/vat/totals
$html_items .= '<tr><td colspan="2"></td><td>Subtotal</td><td><strong>'.number_format($item_total,2).'</strong></td></tr>'.
               '<tr><td colspan="2"></td><td>VAT</td><td>'.Form::textInput('vat',$vat,$param).'</td></tr>';
        
$html_items .= '</table>';
 
$html .= $html_items.'<br/><br/>'.
         '<div style="width:300px">'.
         'Email invoice to'.Form::textInput('email',$email,$param).'<br/>'.
         'Email COPY to'.Form::textInput('email_xtra',$email_xtra,$param).'<br/>'.
         '<input type="submit" value="Check totals and xtra items before processing" class="btn btn-primary"><br/>'.
         '</div>';
  
echo $html;          
?>
