<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;
use Seriti\Tools\TABLE_USER;

$html = '';

$html .= '<div class = "row">'.
         '<table class="table table-hover">';

$input_param['class'] = 'form-control edit_input';
$input_param_small['class'] = 'form-control edit_input input-small';

/*
$sql = 'SELECT O.order_id, CONCAT("ID[",O.order_id,"] status[",O.status,"] user: ",U.name) '.
       'FROM '.TABLE_PREFIX.'order AS O JOIN '.TABLE_USER.' AS U ON(O.user_id = U.user_id) '.
       'WHERE O.auction_id = "'.AUCTION_ID.'" ORDER BY O.date_update DESC ';
*/

$html .= '<tr><td>Auction:</td><td><strong>'.AUCTION_NAME.'</strong></td><td></td></tr>'.
         '<tr><td>Initialise with:</td><td>'.Form::arrayList($data['source'],'source_type',$form['source_type'],true,$input_param).'</td><td><i>(Select how to identify user)</i></td></tr>'.
         '<tr><td>Initialise with value:</td><td>'.Form::textInput('source_id',$source_id,$input_param_small).'</td><td><i>(Enter identification value depending on above selection)</i></td></tr>'.
         '<tr><td>Xtra invoice items:</td><td>'.Form::textInput('xtra_item_no',$form['xtra_item_no'],$input_param_small).'</td><td><i>(Indicates number of additional invoice items you can manually specify)</i></td></tr>'.
         '<tr><td>Proceed: </td><td><input class="btn btn-primary" type="submit" value="review invoice data"></td><td><i>(Click to review user lots and capture any additional invoice items)</i></td></tr>'.
         '</table>
         </div>';
  
echo $html;          

//print_r($form);
//print_r($data);
?>
         
