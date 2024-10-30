jQuery( function ( $ ) {
  init_kineticpay_meta();
  $(".kineticpay_customize_kineticpay_donations_field input:radio").on("change", function() {
    init_kineticpay_meta();
  });

  function init_kineticpay_meta(){
    if ("enabled" === $(".kineticpay_customize_kineticpay_donations_field input:radio:checked").val()){
      $(".kineticpay_api_key_field").show();
      $(".kineticpay_description_field").show();
      $(".kineticpay_collect_billing").show();
    } else {
      $(".kineticpay_api_key_field").hide();
      $(".kineticpay_description_field").hide();
      $(".kineticpay_collect_billing").hide();
    }
  }
});