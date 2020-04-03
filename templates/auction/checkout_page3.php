<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$list_param['class'] = 'form-control edit_input';
$text_param['class'] = 'form-control edit_input';
$textarea_param['class'] = 'form-control edit_input';
?>

<div id="checkout_div">

  <h2>The final amount including packaging and shipping charges are dependant on which bids are successful. 
  You will be contacted to confirm all details after auction finalised.</h2>
  
  <div class="row">
    <div class="col-sm-3">Ship to location:</div>
    <div class="col-sm-3"><?php echo $data['ship_location']; ?></div>
  </div>
  <div class="row">
    <div class="col-sm-3">Shipping option:</div>
    <div class="col-sm-3"><?php echo $data['ship_option']; ?></div>
  </div>
  <div class="row">
    <div class="col-sm-3">Payment option:</div>
    <div class="col-sm-3"><?php echo $data['pay_option']; ?></div>
  </div>
  <div class="row">
    <div class="col-sm-3"><strong>Total bid amount:</strong></div>
    <div class="col-sm-3"><strong><?php echo  CURRENCY_SYMBOL.number_format($data['total'],2); ?></strong></div>
  </div>  

  <div class="row">
    <div class="col-sm-3">Your email address:</div>
    <div class="col-sm-3">
      <?php 
      if(isset($data['user_id'])) {
          echo $data['user_email'];
          if(isset($data['user_created']) and $data['user_created']) {
             echo '</div><div class="col-sm-3"><i>You are now registered with us and logged in. You have been emailed your password.</i>';
          }
      } else {
          echo Form::textInput('user_email',$form['user_email'],$text_param); 
      }    
      ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-3">Your name:</div>
    <div class="col-sm-3">
      <?php 
      if(isset($data['user_id'])) {
          echo $data['user_name'];
      } else {
          echo Form::textInput('user_name',$form['user_name'],$text_param); 
      }    
      ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-3">Your Cell:</div>
    <div class="col-sm-3">
      <?php echo Form::textInput('user_cell',$form['user_cell'],$text_param); ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-3">Ship to address:</div>
    <div class="col-sm-3">
    <?php echo Form::textAreaInput('user_ship_address',$form['user_ship_address'],50,5,$textarea_param); ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-3">Bill to address:</div>
    <div class="col-sm-3">
    <a href="javascript:copyAddress();"><i>copy shipping address</i></a>
    <?php echo Form::textAreaInput('user_bill_address',$form['user_bill_address'],50,5,$textarea_param); ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-6"><input type="submit" name="Submit" value="Confirm <?php echo AUCTION_ORDER_NAME;?>" class="btn btn-primary"></div>
  </div>  

</div>
<script>
function copyAddress() {
  var from = document.getElementById('user_ship_address');
  var to = document.getElementById('user_bill_address');
      
  to.value = from.value;
}
</script>  
