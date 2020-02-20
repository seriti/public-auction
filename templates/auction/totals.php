<div class="shop-totals">
  
  <div class="row">
    <div class="col-sm-12">
       <?php echo $messages; ?>     
    </div>
  </div> 
 
  <div class="row">
    <div class="col-sm-3">Bid Subtotal:</div>
    <div class="col-sm-3"><?php echo CURRENCY_SYMBOL.number_format($data['subtotal'],2); ?></div>
  </div>

  <div class="row">
    <div class="col-sm-3">Subtotal includes discount:</div>
    <div class="col-sm-3"><?php echo  CURRENCY_SYMBOL.number_format($data['discount'],2); ?></div>
  </div>  

  <div class="row">
    <div class="col-sm-3">Tax:</div>
    <div class="col-sm-3"><?php echo  CURRENCY_SYMBOL.number_format($data['tax'],2); ?></div>
  </div>  

  <div class="row">
    <div class="col-sm-6"><hr/></div>
  </div>  

  <div class="row">
    <div class="col-sm-3"><strong>Bid Total:</strong></div>
    <div class="col-sm-3"><strong><?php echo  CURRENCY_SYMBOL.number_format($data['total'],2); ?></strong></div>
  </div>    
  
  
</div>