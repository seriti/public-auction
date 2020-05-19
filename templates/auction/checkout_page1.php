<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

$list_param['class'] = 'form-control edit_input';
?>

<div id="checkout_div">

  <p>
  <?php
  if(isset($data['user_id'])) {
      echo '<h2>Hi there <strong>'.$data['user_name'].'</strong>. you are logged in and ready to proceed with checkout process.</h2>';
  } else {
      echo '<h2>If you are aready a user <a href="/login">please login</a> before you proceed.</h2>';
      echo '<h2>If you are not a user then you can proceed and you will be registered automatically.</h2>';
  }
  ?>
  <h2>NB: The final amount and shipping charges are dependant on which bids are successful. 
  The below options are for indication purposes only. You will be contacted to confirm all details after auction finalised.</h2>
  <br/>
  </p>
  
  <div class="row">
    <div class="col-sm-3">Ship to location:</div>
    <div class="col-sm-3">
    <?php 
    $sql = 'SELECT location_id, name FROM '.MODULE_AUCTION['table_prefix'].'ship_location WHERE status = "OK" ORDER BY sort';
    echo Form::sqlList($sql,$db,'ship_location_id',$form['ship_location_id'],$list_param) 
    ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-3">Shipping option:</div>
    <div class="col-sm-3">
    <?php 
    $sql = 'SELECT option_id, name FROM '.MODULE_AUCTION['table_prefix'].'ship_option WHERE status = "OK" ORDER BY sort';
    echo Form::sqlList($sql,$db,'ship_option_id',$form['ship_option_id'],$list_param) 
    ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-3">Payment option:</div>
    <div class="col-sm-3">
    <?php 
    $sql = 'SELECT option_id, name FROM '.MODULE_AUCTION['table_prefix'].'pay_option WHERE status = "OK" ORDER BY sort';
    echo Form::sqlList($sql,$db,'pay_option_id',$form['pay_option_id'],$list_param) 
    ?>
    </div>
  </div>

  <div class="row">
    <div class="col-sm-6"><input type="submit" name="Submit" value="Proceed" class="btn btn-primary"></div>
  </div>  

    
  
</div>