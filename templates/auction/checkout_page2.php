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
    $price_msg = false;
    echo '<table class="table  table-striped table-bordered table-hover table-condensed">'.
         '<tr><th>Lot No.</th><th>Lot name</th><th>Bid Price</th><th>Reserve price</th></tr>';
    foreach($data['items'] as $item) {
        echo '<tr><td>'.$item['lot_no'].'</td><td>'.$item['name'].'</td>'.
                 '<td>'.CURRENCY_SYMBOL.number_format($item['price'],2).'</td>'.
                 '<td>'.CURRENCY_SYMBOL.number_format($item['price_reserve'],2).'</td></tr>';

        if($item['price'] == $item['price_reserve']) $price_msg = true;        
    }
    echo '<tr><td><strong>Bid total:</strong></td><td><strong>'.CURRENCY_SYMBOL.number_format($data['total'],2).'</strong></td></tr>';
    echo '</table>';
    
    if($price_msg) echo '<p><strong>We note that some of your bids are at reserve. If this was intentional, that’s fine with us, 
                         as long as you know you can update the bid amount when <a href="/public/cart">viewing your bid form cart</a>.</strong></p>';

    ?>
    </div>
  </div>
  
  <div class="row">
    <div class="col-sm-6"><input type="submit" name="Submit" value="Proceed" class="btn btn-primary"></div>
  </div>  

</div>