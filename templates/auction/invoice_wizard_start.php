<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;
use Seriti\Tools\TABLE_USER;

$html = '';

$html .= '<div>'.
         //'<form method="post" action="?mode=review" name="create_invoice" id="create_invoice">'.
         '<table>';

$list_param['class'] = 'form-control edit_input';
$date_param['class'] = 'form-control edit_input bootstrap_date';

/*
$sql = 'SELECT O.order_id, CONCAT("ID[",O.order_id,"] status[",O.status,"] user: ",U.name) '.
       'FROM '.TABLE_PREFIX.'order AS O JOIN '.TABLE_USER.' AS U ON(O.user_id = U.user_id) '.
       'WHERE O.auction_id = "'.AUCTION_ID.'" ORDER BY O.date_update DESC ';
*/

$html .= '<tr><td>Auction:</td><td><strong>'.AUCTION_NAME.'</strong></td></tr>'.
         '<tr><td>Initialise with:</td><td>'.Form::arrayList($data['source'],'source_type',$form['source_type'],true,$list_param).'</td></tr>'.
         '<tr><td>Initial value:</td><td>'.Form::textInput('source_id',$source_id,$list_param).'</td></tr>'.
         '<tr><td>Proceed: </td><td><input class="btn btn-primary" type="submit" value="review invoice data"></td></tr>'.
         '</table></div>';
  
echo $html;          

//print_r($form);
//print_r($data);
?>
