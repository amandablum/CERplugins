<?php

class WC_Wishlists_User {

	private static $key = '';
	private static $product_id_cache = false;
	private static $list_cache = false;

	public static function init() {
		if ( is_user_logged_in() ) {
			self::$key = get_current_user_id();

			return;
		}

		$temp_key  = uniqid();
		$store_key = 'wp-wc_wishlists_user';

		if ( isset( $_COOKIE[ $store_key ] ) ) {
			$temp_key = $_COOKIE[ $store_key ];
		}

		self::$key = $temp_key;
	}

	public static function set_cookie() {
		$store_key = 'wp-wc_wishlists_user';
		if ( ! isset( $_COOKIE[ $store_key ] ) || empty( $_COOKIE[ $store_key ] ) ) {
			$temp_key = is_user_logged_in() ? get_current_user_id() : uniqid( md5( date( 'F j, Y @ h:i A' ) ) );
			setcookie( $store_key, $temp_key, time() + apply_filters( 'wc_wishlists_cookie_duration', 3600 * 24 * 30 ), '/' );
			self::$key = $temp_key;
		} else {
			if ( is_user_logged_in() ) {
				$temp_key = get_current_user_id();
				setcookie( $store_key, $temp_key, time() + apply_filters( 'wc_wishlists_cookie_duration', 3600 * 24 * 30 ), '/' );
				self::$key = $temp_key;
			}
		}
	}

	public static function get_wishlist_key() {
		if ( empty( self::$key ) || is_user_logged_in() && self::$key != get_current_user_id() ) {
			self::init();
		}

		return self::$key;
	}

	/**
	 * @param bool $by_type
	 * @param bool $key
	 *
	 * @return WC_Wishlists_Wishlist[]
	 */
	public static function get_wishlists( $by_type = false, $key = false ) {
		if ( $key == false ) {
			$key = self::get_wishlist_key();
		}

		$transient_timeout = apply_filters('wc_wishlists_transient_timeout', DAY_IN_SECONDS);

		$stored_lists = get_transient( 'wc_wishlists_users_lists_' . $key );
		if ( $stored_lists ) {
			self::$list_cache = $stored_lists;
		}


		if ( self::$list_cache === false ) {
			self::$list_cache = array();
			$args             = array(
				'post_type'   => 'wishlist',
				'post_status' => 'publish',
				// 'orderby' => 'title post_date',
				'orderby'     => 'date',
				'nopaging'    => true,
				'meta_query'  => array(
					array(
						'key'   => '_wishlist_owner',
						'value' => $key,
					)
				)
			);

			$posts = get_posts( $args );
			if ( $posts ) {
				foreach ( $posts as $post ) {
					$list                          = new WC_Wishlists_Wishlist( $post->ID );
					self::$list_cache[ $post->ID ] = $list;
				}
				set_transient( 'wc_wishlists_users_lists_' . $key, self::$list_cache, $transient_timeout );
			} else {
				set_transient( 'wc_wishlists_users_lists_' . $key, array(), $transient_timeout );
			}
		}


		if ( $by_type ) {
			$lists = array();
			foreach ( self::$list_cache as $list ) {
				if ( $list->get_wishlist_sharing() == $by_type ) {
					$lists[] = $list;
				}
			}

			return $lists;
		} else {
			return self::$list_cache;
		}

	}

	public static function get_wishlist_product_ids() {
		$lists       = self::get_wishlists();
		$key         = self::get_wishlist_key() . '_wishlist_products';
		$product_ids = array();
		if ( self::$product_id_cache ) {
			$product_ids = self::$product_id_cache;
		} else {
			$temp = array();

			foreach ( $lists as $list ) {
				$items = WC_Wishlists_Wishlist_Item_Collection::get_items( $list->id );
				if ( $items ) {
					foreach ( $items as $item ) {
						$temp[ $item['product_id'] ][] = $list->id;
					}
				}
			}

			foreach ( $temp as $product_id => $lists ) {
				$product_ids[ $product_id ] = array_unique( $lists );
			}

			self::$product_id_cache = $product_ids;
		}

		return $product_ids;
	}

}
