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
 * @author    SkyVerge
 * @copyright Copyright (c) 2011-2018, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace SkyVerge\WooCommerce\PIP\Integration;

defined( 'ABSPATH' ) or exit;

use SkyVerge\WooCommerce\PluginFramework\v5_3_0 as Framework;

/**
 * Integration class for WooCommerce Product Add-Ons.
 *
 * @since 3.6.1
 */
class Product_Add_Ons {


	/** @var string meta key used by Product Add Ons to attach add ons on a line item */
	private $attached_add_ons_meta_key = '_wc_pao_attached_addons';


	/**
	 * Sets up the Product Add-Ons integration.
	 *
	 * @since 3.6.1
	 */
	public function __construct() {

		// new features in Product Add Ons 3.0 are only enabled in WooCommerce 3.0+
		if ( Framework\SV_WC_Plugin_Compatibility::is_wc_version_gte_3_0() ) {

			// hide a product add on from showing in a dedicated table row...
			add_filter( 'wc_pip_order_item_visible',        array( $this, 'hide_product_add_on_row' ), 10, 2 );
			// ...instead, append the product add on data to its parent item (similar to 2.x behavior)
			add_filter( 'wc_pip_order_item_meta_data_list', array( $this, 'add_order_item_product_add_on_meta' ), 1, 5 );
		}
	}


	/**
	 * Checks whether an order item is a product add on.
	 *
	 * @since 3.6.1
	 *
	 * @param int|array|\WC_Order_Item $item order item object, array data or ID
	 * @return bool
	 */
	private function is_product_add_on( $item ) {

		$is_add_on = false;

		if ( is_numeric( $item ) ) {
			$item = new \WC_Order_Item( $item );
		}

		/* @type \WC_Meta_Data $meta */
		foreach ( $item->get_meta_data() as $meta ) {

			$meta_data = $meta->get_data();

			if (    isset( $meta_data['key'] )
			     && $this->attached_add_ons_meta_key !== $meta_data['key']
			     && false !== strpos( $meta_data['key'], '_wc_pao_' ) ) {

				$is_add_on = true;
				break;
			}
		}

		return $is_add_on;
	}


	/**
	 * Checks whether an order item has product add ons attached.
	 *
	 * @since 3.6.1
	 *
	 * @param array|\WC_Order_Item $item order item object
	 * @return bool
	 */
	private function has_order_item_add_ons( $item ) {

		$has_add_ons = $item->get_meta( $this->attached_add_ons_meta_key );

		return ! empty( $has_add_ons );
	}


	/**
	 * Gets order item add ons for a regular order item.
	 *
	 * @since 3.6.1
	 *
	 * @param array|\WC_Order_Item $item order item object
	 * @return array|\WC_Order_Item[] product add on items
	 */
	private function get_order_item_add_ons( $item ) {

		$attached_add_ons = $item->get_meta( $this->attached_add_ons_meta_key );

		return is_array( $attached_add_ons ) ? $attached_add_ons : array();
	}


	/**
	 * Hides product add ons from showing on an individual row in the document table.
	 *
	 * @internal
	 *
	 * @since 3.6.1
	 *
	 * @param bool $visible by default all items are visible, we hide order items that are in fact add ons
	 * @param array|\WC_Order_Item $item item object
	 * @return bool
	 */
	public function hide_product_add_on_row( $visible, $item ) {

		return false !== $visible ? ! $this->is_product_add_on( $item ) : $visible;
	}


	/**
	 * Adds product add on data in the parent order item row.
	 *
	 * @see \WC_PIP_Document::get_order_item_meta() for a similar output
	 *
	 * @internal
	 *
	 * @since 3.6.1
	 *
	 * @param string $item_meta HTML meta
	 * @param int $item_id order item ID
	 * @param array|\WC_Order_Item $item order item object
	 * @param \WC_Product $product related product object
	 * @param \WC_PIP_Document $document document object
	 * @return string HTML
	 */
	public function add_order_item_product_add_on_meta( $item_meta, $item_id, $item, $product, $document ) {

		if ( $this->has_order_item_add_ons( $item ) ) {

			/* this filter is documented in includes/abstract-wc-pip-document.php */
			$flat = (bool) apply_filters( 'wc_pip_document_table_row_item_meta_flat', false, $product, $item_id, $item, $document->type, $document->order );

			foreach ( $this->get_order_item_add_ons( $item ) as $add_on ) {

				if ( ! isset( $add_on['name'], $add_on['value'], $add_on['field_name'] ) ) {
					continue;
				}

				$price = ! empty( $add_on['price'] ) ? ' (' . wc_price( $add_on['price'] ) . ')' : '';

				if ( $flat ) {
					$meta_list[] = wp_kses_post( $add_on['name'] . $price . ': ' . $add_on['value'] );
				} else {
					$meta_list[] = '
						<dt class="variation-' . sanitize_html_class( $add_on['field_name'] ) . '">' . wp_kses_post( $add_on['name'] ) . $price . ':</dt>
						<dd class="variation-' . sanitize_html_class( $add_on['field_name'] ) . '">' . wp_kses_post( make_clickable( $add_on['value'] ) ) . '</dd>
					';
				}
			}

			if ( ! empty( $meta_list ) ) {

				if ( $flat ) {
					$item_meta .= implode( ", \n", $meta_list );
				} else {
					$item_meta .= '<dl class="variation">' . implode( '', $meta_list ) . '</dl>';
				}
			}
		}

		return $item_meta;
	}


}
