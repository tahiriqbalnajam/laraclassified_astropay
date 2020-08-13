<div class="row payment-plugin" id="astropay" style="display: none;">
	<div class="col-md-10 col-sm-12 box-center center mt-4 mb-0">
		<div class="row">
			
			<div class="col-xl-12 text-center">
				<img class="img-fluid" src="{{ url('images/offlinepayment/payment.png') }}" title="{{ trans('astropay::messages.Offline Payment') }}">
			</div>
			
			<div class="col-xl-12 mt-3">
				<div id="astropayDescription">
					<div class="card card-default">
						
						<div class="card-header">
							<h3 class="panel-title">
								{{ trans('astropay::messages.Payment Details') }}
							</h3>
						</div>									
						<div class="card-body">
                            <div class="form-row align-items-center">
                                <div class="col-sm-5">
                                  <label class="sr-only" for="card_number">Name</label>
                                  <input type="text" name="card_number" id="card_number" class="form-control"  placeholder="Credit Card #">
                                </div>
                                <div class="col-auto">
                                  <label class="sr-only" for="expirymonth">Month</label>
                                  <div class="input-group mb-2">
                                    <select name="month" class="form-control input-md" id="expirymonth">
                                        <option value="01">01</option>
                                        <option value="02">ٖ02</option>
                                        <option value="03">03</option>
                                        <option value="04">04</option>
                                        <option value="05">05</option>
                                        <option value="06">06</option>
                                        <option value="07">07</option>
                                        <option value="08">08</option>
                                        <option value="09">09</option>
                                        <option value="10">10</option>
                                        <option value="11">11</option>
                                        <option value="12">12</option>
                                    </select>
                                  </div>
                                </div>
                                <div class="col-auto">
                                    <label class="sr-only" for="expiryyear">Year</label>
                                    <div class="input-group mb-2">
                                        <select name="year" id="expiryyear" class="form-control input-md">
                                            <option value="2020">2020</option>
                                            <option value="2021">2021</option>
                                            <option value="2022">2022</option>
                                            <option value="2023">2023</option>
                                            <option value="2024">2024</option>
                                            <option value="2025">2025</option>
                                            <option value="2026">2026</option>
                                            <option value="2027">2027</option>
                                            <option value="2028">2028</option>
                                            <option value="2029">2029</option>
                                            <option value="2030">2030</option>
                                        </select>
                                    </div>
                                  </div>
                                  <div class="col-auto">
                                    <label class="sr-only" for="expiryyear">Year</label>
                                    <div class="input-group mb-2">
                                        <input type="text" max="3" name="cvv" class="form-control input-md" placeholder="CVV">
                                    </div>
                                  </div>
                              </div>
						</div>
					</div>
				</div>
			</div>
			
		</div>
    </div>
</div>
<style>

</style>
@section('after_scripts')
    @parent
    <script>
        $(document).ready(function ()
        {
            var selectedPackage = $('input[name=package_id]:checked').val();
			var packageName = $('input[name=package_id]:checked').data('name');
            var packagePrice = getPackagePrice(selectedPackage);
            var paymentMethod = $('#paymentMethodId').find('option:selected').data('name');
    
            /* Check Payment Method */
            checkPaymentMethodForAstropay(paymentMethod, packageName, packagePrice);
            
            $('#paymentMethodId').on('change', function () {
                paymentMethod = $(this).find('option:selected').data('name');
                checkPaymentMethodForAstropay(paymentMethod, packageName, packagePrice);
            });
            $('.package-selection').on('click', function () {
                selectedPackage = $(this).val();
				packageName = $(this).data('name');
                packagePrice = getPackagePrice(selectedPackage);
                paymentMethod = $('#paymentMethodId').find('option:selected').data('name');
                checkPaymentMethodForAstropay(paymentMethod, packageName, packagePrice);
            });
    
            /* Send Payment Request */
            $('#submitPostForm').on('click', function (e)
            {
                e.preventDefault();
        
                paymentMethod = $('#paymentMethodId').find('option:selected').data('name');
                
                if (paymentMethod != 'astropay' || packagePrice <= 0) {
                    return false;
                }
    
                $('#postForm').submit();
        
                /* Prevent form from submitting */
                return false;
            });
        });

        function checkPaymentMethodForAstropay(paymentMethod, packageName, packagePrice)
        {
            if (paymentMethod == 'astropay' && packagePrice > 0) {
            	$('#astropayDescription').find('.package-name').html(packageName);
                $('#astropay').show();
            } else {
                $('#astropay').hide();
            }
        }
    </script>
@endsection
