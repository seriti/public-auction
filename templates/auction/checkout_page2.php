<?php
use Seriti\Tools\Form;
use Seriti\Tools\Html;

?>

<div id="checkout_div">

  <h2>The final amount including packaging and shipping charges are dependant on which bids are successful. 
  You will be contacted to confirm all details after auction finalised.</h2>
  
  <div class="row">
    <div class="col-sm-3">Ship to location:</div>
    <div class="col-sm-3"><strong><?php echo $data['ship_location']; ?></strong></div>
  </div>
  <div class="row">
    <div class="col-sm-3">Shipping option:</div>
    <div class="col-sm-3"><strong><?php echo $data['ship_option']; ?></strong></div>
  </div>
  <div class="row">
    <div class="col-sm-3">Payment option:</div>
    <div class="col-sm-3"><strong><?php echo $data['pay_option']; ?></strong></div>
  </div>


  <br/>
  <div class="row">
    <div class="col-sm-6">
    <?php 
    echo '<table class="table  table-striped table-bordered table-hover table-condensed">'.
         '<tr><th>Auction lot</th><th>Bid Price</th></tr>';
    foreach($data['items'] as $item) {
        echo '<tr><td>'.$item['name'].'</td><td>'.CURRENCY_SYMBOL.number_format($item['price'],2).'</td></tr>';
    }
    echo '<tr><td><strong>Bid total:</strong></td><td><strong>'.CURRENCY_SYMBOL.number_format($data['total'],2).'</strong></td></tr>';
    echo '</table>';
    
    ?>
    </div>
  </div>
  
  <div class="row">
    <div class="col-sm-6"><input type="submit" name="Submit" value="Proceed" class="btn btn-primary"></div>
  </div>  

</div>