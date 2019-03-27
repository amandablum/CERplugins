<tr class="shipping paczkomaty-shipping">
    <td colspan="2">
	    <h4><?php _e( 'UPS Access Point', 'flexible-shipping-ups' ); ?></h4>
        <?php

        $field_args = array(
            'type'          => 'select',
            'options'       => $select_options,
            'description'   => __( 'UPS Access Points closest for your shipping address', 'flexible-shipping-ups' ),
        );
        woocommerce_form_field( 'ups_access_point', $field_args, $selected_access_point );
        ?>
        <script type="text/javascript">
            var ups_access_point_value;
                jQuery(document).ready(function() {
                if ( jQuery().select2 ) {
                    jQuery('#ups_access_point').select2();
                };
                ups_access_point_value = jQuery('#ups_access_point').val();
            });
            jQuery(document).on( 'change', '#ups_access_point', function() {
                if ( ups_access_point_value != jQuery('#ups_access_point').val() ) {
                    ups_access_point_value = jQuery('#ups_access_point').val();
                    jQuery('#ups_access_point').select2( 'destroy' );
                    jQuery(document.body).trigger( 'update_checkout' );
                }
            });
        </script>
    </td>
</tr>
