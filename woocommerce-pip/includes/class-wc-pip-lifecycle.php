<?php
/**
 * WooCommerce Print Invoices/Packing Lists
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Print
 * Invoices/Packing Lists to newer versions in the future. If you wish to
 * customize WooCommerce Print Invoices/Packing Lists for your needs please refer
 * to http://docs.woocommerce.com/document/woocommerce-print-invoice-packing-list/
 *
 * @package   WC-Print-Invoices-Packing-Lists/Emails
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\PIP;

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_3_0 as Framework;

/**
 * Plugin lifecycle handler.
 *
 * @since 3.6.0
 *
 * @method \WC_PIP get_plugin()
 */
class Lifecycle extends Framework\Plugin\Lifecycle {


	/**
	 * Runs the plugin install.
	 *
	 * @since 3.6.0
	 */
	protected function install() {

		// install default settings
		if ( $settings_handler = $this->get_plugin()->get_settings_instance() ) {

			$settings_sections = array_merge( array( 'general' ), array_keys( $settings_handler->get_sections() ) );

			foreach ( $settings_sections as $section ) {

				$this->install_default_settings( $settings_handler->get_settings( $section ) );
			}
		}

		// PIP versions prior to 2.7.0 did not set a version option, so the upgrade method needs to be called manually.
		// We do this by checking first if an old option exists, but a new one doesn't.
		if ( get_option( 'woocommerce_pip_invoice_start' ) && ! get_option( 'wc_pip_invoice_number_start' ) ) {

			$this->upgrade( '2.7.1' );
		}
	}


	/**
	 * Performs any version-related changes.
	 *
	 * @since 3.6.0
	 *
	 * @param string $installed_version the currently installed version of the plugin
	 */
	protected function upgrade( $installed_version ) {

		// TODO when adding more upgrade scripts to higher versions beyond 3.0.0, consider splitting each script in its own private method as we did in other plugins {FN 2018-07-05}

		if ( version_compare( $installed_version, '3.0.0', '<' ) ) {

			// old option name => new option name
			$options = array(
				'woocommerce_pip_logo'           => 'wc_pip_company_logo',
				'woocommerce_pip_company_name'   => 'wc_pip_company_name',
				'woocommerce_pip_company_extra'  => 'wc_pip_company_extra',
				'woocommerce_pip_return_policy'  => 'wc_pip_return_policy',
				'woocommerce_pip_header'         => 'wc_pip_header',
				'woocommerce_pip_footer'         => 'wc_pip_footer',
				'woocommerce_pip_invoice_start'  => 'wc_pip_invoice_number_start',
				'woocommerce_pip_invoice_prefix' => 'wc_pip_invoice_number_prefix',
				'woocommerce_pip_invoice_suffix' => 'wc_pip_invoice_number_suffix',
			);

			foreach ( $options as $old_option => $new_option ) {

				if ( $old_setting = get_option( $old_option ) ) {

					update_option( $new_option, $old_setting );
					delete_option( $old_option );
				}
			}

			// emails option needs a different handling
			$emails_enabled  = 'enabled' === get_option( 'woocommerce_pip_send_email' ) ? 'yes' : 'no';
			$default_setting = array( 'enabled' => $emails_enabled );
			$emails_settings = array(
				'woocommerce_pip_email_invoice_settings'      => get_option( 'woocommerce_pip_email_invoice_settings', $default_setting ),
				'woocommerce_pip_email_packing_list_settings' => get_option( 'woocommerce_pip_email_packing_list_settings', $default_setting ),
			);

			// update from a legacy setting to send HTML emails with an array compatible with WC Emails settings
			foreach ( $emails_settings as $emails_setting_key => $emails_setting_options ) {

				if ( $emails_setting_options && is_array( $emails_setting_options ) ) {

					$emails_setting_options['enabled'] = $emails_enabled;

					update_option( $emails_setting_key, $emails_setting_options );
				}
			}

			// delete legacy email option
			delete_option( 'woocommerce_pip_send_email' );

			// prevent changing default behaviour for old installations
			update_option( 'wc_pip_use_order_number', 'no' );

			// print preview option is no longer used
			delete_option( 'woocommerce_pip_preview' );

			// redundant option before WC Settings Page was used
			delete_option( 'pip_fields_submitted' );

			// now update the print status of past orders if PIP was previously installed
			$posts_per_page = 500;
			$offset         = (int) get_option( 'wc_pip_upgrade_install_offset', 0 );
			$documents      = array( 'invoice', 'packing-list' );

			do {

				// grab order ids
				$order_ids = get_posts( array(
					'post_type'      => 'shop_order',
					'fields'         => 'ids',
					'posts_per_page' => $posts_per_page,
					'offset'         => $offset,
					'post_status'    => 'any'
				) );

				// sanity check
				if ( is_wp_error( $order_ids ) ) {
					break;
				}

				if ( ! empty( $order_ids ) && is_array( $order_ids ) ) {

					$is_wc_3_0 = Framework\SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0();

					foreach( $order_ids as $order_id ) {

						$invoice_number = null;

						// previously, PIP would generate an invoice number when a print window was open for the first time,
						// therefore we can check this meta to see if a document has been printed before
						if ( $is_wc_3_0 ) {

							$order = wc_get_order( $order_id );

							if ( $order ) {
								$invoice_number = $order->get_meta( '_pip_invoice_number', true, 'view' );
							}

						} else {

							$invoice_number = get_post_meta( $order_id, '_pip_invoice_number', true );
						}

						if ( ! empty( $invoice_number ) ) {

							foreach ( $documents as $document_type ) {

								$document = $this->get_plugin()->get_document( $document_type, array( 'order_id' => $order_id ) );

								if ( $document ) {
									$document->update_print_count();
								}
							}
						}
					}
				}

				// increment offset
				$offset += $posts_per_page;

				// keep track of how far we made it in case we hit a script timeout
				update_option( 'wc_pip_upgrade_install_offset', $offset );

			} while ( count( $order_ids ) === $posts_per_page );  // while full set of results returned (meaning there may be more results still to retrieve)

			// upgrade flag
			update_option( 'woocommerce_pip_upgraded_to_3_0_0', 'yes' );

			$this->get_plugin()->log( 'Updated to version 3.0.0' );
		}
	}


}
