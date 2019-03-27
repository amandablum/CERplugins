<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Flexible_Shipping_UPS_Access_Points_Helper' ) ) {

	class Flexible_Shipping_UPS_Access_Points_Helper {

		const TRANSIENT_NAME_FOR_ACCESS_POINT = 'ups_ap';
		const TRANSIENT_NAME_FOR_NEAREST_ACCESS_POINT = 'ups_nearest_ap';
		const TRANSIENT_NAME_FOR_NEAREST_ACCESS_POINTS = 'ups_nearest_aps';

		/**
		 * @var string
		 */
		private $access_key;

		/**
		 * @var string
		 */
		private $user_id;

		/**
		 * @var string
		 */
		private $password;

		/**
		 * Flexible_Shipping_UPS_Access_Points constructor.
		 *
		 * @param $access_key
		 * @param $user_id
		 * @param $password
		 */
		public function __construct( $access_key, $user_id, $password ) {
			$this->access_key = $access_key;
			$this->user_id = $user_id;
			$this->password = $password;
		}

		/**
		 * Get access points for given country and postcode.
		 *
		 * @param string $country
		 * @param string $postcode
		 * @param int $max_number_of_points
		 *
		 * @return mixed
		 * @throws Exception
		 */
		public function get_access_points_for_postcode( $country, $postcode, $max_number_of_points = 0 ) {

			$transient_name = self::TRANSIENT_NAME_FOR_NEAREST_ACCESS_POINTS . '_' . sanitize_title( $country ) . '_' . sanitize_title( $postcode ) . '_' . sanitize_title( $max_number_of_points );
			$locations = get_transient( $transient_name );

			if ( !$locations ) {
				$locator_request = new \Ups\Entity\LocatorRequest;

				$origin_address = new \Ups\Entity\OriginAddress;
				$address        = new \Ups\Entity\AddressKeyFormat;
				$address->setCountryCode( $country );
				$address->setPostcodePrimaryLow( $postcode );

				$origin_address->setAddressKeyFormat( $address );
				$locator_request->setOriginAddress( $origin_address );

				$acccess_point_search = new \Ups\Entity\AccessPointSearch;
				$acccess_point_search->setAccessPointStatus( \Ups\Entity\AccessPointSearch::STATUS_ACTIVE_AVAILABLE );

				$location_search = new \Ups\Entity\LocationSearchCriteria;
				$location_search->setAccessPointSearch( $acccess_point_search );

				if ( $max_number_of_points == 0 || $max_number_of_points > 50 ) {
					$location_search->setMaximumListSize( 50 );
				} else {
					$location_search->setMaximumListSize( $max_number_of_points );
				}

				$locator_request->setLocationSearchCriteria( $location_search );

				$unit_of_measurement = new \Ups\Entity\UnitOfMeasurement;
				$unit_of_measurement->setCode( \Ups\Entity\UnitOfMeasurement::UOM_KM );
				$locator_request->setUnitOfMeasurement( $unit_of_measurement );

				$locator = new Ups\Locator( $this->access_key, $this->user_id, $this->password );

				$locations = $locator->getLocations( $locator_request, \Ups\Locator::OPTION_UPS_ACCESS_POINT_LOCATIONS );

				set_transient( $transient_name, $locations, DAY_IN_SECONDS );

			}

			return $locations->SearchResults->DropLocation;
		}

		/**
		 * Get public access point ID from given location (access point).
		 *
		 * @param stdClass $location
		 *
		 * @return mixed
		 */
		public function get_public_access_point_id_from_location( $location ) {
			return $location->AccessPointInformation->PublicAccessPointID;
		}

		/**
		 * Prepare one line string with access point location address from given location.
		 *
		 * @param stdClass $location
		 *
		 * @return string
		 */
		public function prepare_access_point_address_as_label( $location ) {
			$label = $location->AddressKeyFormat->ConsigneeName;
			$label .= ', ' . $location->AddressKeyFormat->AddressLine;
			$label .= ', ' . $location->AddressKeyFormat->PostcodePrimaryLow;
			$label .= ' ' . $location->AddressKeyFormat->PoliticalDivision2;
			return $label;
		}

		/**
		 * Prepare options array for select item from given locations.
		 *
		 * @param array $locations
		 *
		 * @return array
		 */
		public function prepare_items_for_select_field( $locations ) {
			$select_options = array();
			foreach ( $locations as $location ) {
				$select_options[$this->get_public_access_point_id_from_location( $location )] = $this->prepare_access_point_address_as_label( $location );
			}
			return $select_options;
		}

		/**
		 * Get nearest access point for given country and post code.
		 *
		 * @param string $country
		 * @param string $postcode
		 *
		 * @return mixed
		 * @throws Exception
		 */
		public function get_nearest_access_point_for_postcode( $country, $postcode ) {
			$locations = $this->get_access_points_for_postcode( $country, $postcode, 1 );
			return $locations;
		}


		/**
		 * Get access point for given ID
		 *
		 * @param string $access_point_id Access point ID.
		 * @param string $country_code Country code.
		 *
		 * @return mixed|stdClass
		 * @throws Exception Exception.
		 */
		public function get_access_point_by_id( $access_point_id, $country_code ) {
			$transient_name = self::TRANSIENT_NAME_FOR_ACCESS_POINT . '_' . $access_point_id;
			$locations      = get_transient( $transient_name );

			if ( false === $locations ) {
				$locator_request = new \Ups\Entity\LocatorRequest();

				$origin_address = new \Ups\Entity\OriginAddress();
				$address        = new \Ups\Entity\AddressKeyFormat();
				$address->setCountryCode( $country_code );

				$origin_address->setAddressKeyFormat( $address );
				$locator_request->setOriginAddress( $origin_address );

				$access_point_search = new \Ups\Entity\AccessPointSearch();
				$access_point_search->setAccessPointStatus( \Ups\Entity\AccessPointSearch::STATUS_ACTIVE_AVAILABLE );
				$access_point_search->setPublicAccessPointId( $access_point_id );

				$location_search = new \Ups\Entity\LocationSearchCriteria();
				$location_search->setAccessPointSearch( $access_point_search );
				$location_search->setMaximumListSize( 1 );

				$locator_request->setLocationSearchCriteria( $location_search );

				$locator = new Ups\Locator( $this->access_key, $this->user_id, $this->password );

				try {
					$locations = $locator->getLocations(
						$locator_request,
						\Ups\Locator::OPTION_UPS_ACCESS_POINT_LOCATIONS
					);
				} catch ( Exception $e ) {
					return null;
				}

				set_transient( $transient_name, $locations, DAY_IN_SECONDS );

			}

			return $locations->SearchResults->DropLocation;
		}

	}

}
