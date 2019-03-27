<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
include_once(WOO_DISCOUNT_DIR . '/helper/general-helper.php');
include_once(WOO_DISCOUNT_DIR . '/includes/discount-base.php');

/**
 * Class FlycartWooDiscountRulesCartRules
 */
if (!class_exists('FlycartWooDiscountRulesCartRules')) {
    class FlycartWooDiscountRulesCartRules
    {
        /**
         * @var string
         */
        private $option_name = 'woo_discount_cart_option';

        /**
         * @var string
         */
        public $post_type = 'woo_discount_cart';

        /**
         * @var bool
         */
        public $discount_applied = false;

        /**
         * @var
         */
        private $rules;

        /**
         * @var
         */
        public $rule_sets;

        /**
         * @var array
         */
        public $cart_items;

        /**
         * @var
         */
        public $sub_total;

        /**
         * @var int
         */
        public $discount_total = 0;
        public $product_discount_total = 0;

        public $has_category_in_rule = 0;

        /**
         * @var array
         */
        public $coupon_list;

        /**
         * @var string
         */
        public $coupon_code;

        /**
         * @var
         */
        public $matched_sets;

        public $matched_discounts;

        public $postData;

        public static $rules_loaded = 0;
        public static $cartRules;

        public $has_free_shipping = 0;
        public $bogo_coupon_codes = array();

        /**
         * FlycartWooDiscountRulesCartRules constructor.
         */
        public function __construct()
        {
            global $woocommerce;

            $this->postData = \FlycartInput\FInput::getInstance();
            $this->cart_items = (isset($woocommerce->cart->cart_contents) ? $woocommerce->cart->cart_contents : array());
            $this->calculateCartSubtotal();
            $this->coupon_list = (isset($woocommerce->cart->applied_coupons) ? $woocommerce->cart->applied_coupons : array());

            // Check for Remove Coupon Request.
            if (!is_null($this->postData->get('remove_coupon', null))) $this->removeWoocommerceCoupon($this->postData->get('remove_coupon'));

            // Update Coupon Code
            $this->coupon_code = strtolower($this->getCouponCode());
        }

        /**
         * Save Cart Configs.
         *
         * @param array $request bulk request data.
         * @return bool
         */
        public function save($request)
        {
            foreach ($request as $index => $value) {
                if ($index !== 'discount_rule') {
                    $request[$index] = FlycartWooDiscountRulesGeneralHelper::makeString($value);
                }
            }

            $id = (isset($request['rule_id']) ? $request['rule_id'] : false);

            $id = intval($id);
            if (!$id && $id != 0) return false;
            $title = $request['rule_name'] = (isset($request['rule_name']) ? str_replace('\'', '', $request['rule_name']) : 'New');
            $slug = str_replace(' ', '-', strtolower($title));

            // To Lowercase.
            $slug = strtolower($slug);

            // Encoding String with Space.
            $slug = str_replace(' ', '-', $slug);

            $request['rule_descr'] = (isset($request['rule_descr']) ? str_replace('\'', '', $request['rule_descr']) : '');
            $request['cart_discounted_products'] = (isset($request['cart_discounted_products'])) ? json_encode($request['cart_discounted_products']) : '{}';
            $form = array(
                'rule_name',
                'rule_descr',
                'date_from',
                'date_to',
                'apply_to',
                'discount_type',
                'cart_discounted_products',
                'product_discount_quantity',
                'to_discount',
                'discount_rule',
                'rule_order',
                'status',
                'wpml_language',
            );

            if ($id) {
                $post = array(
                    'ID' => $id,
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => 'New Rule',
                    'post_type' => $this->post_type,
                    'post_status' => 'publish'
                );
                wp_update_post($post);
            } else {
                $post = array(
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => 'New Rule',
                    'post_type' => $this->post_type,
                    'post_status' => 'publish'
                );
                $id = wp_insert_post($post);
                $request['status'] = 'publish';
            }
            $request['rule_order'] = FlycartWooDiscountRulesGeneralHelper::reOrderRuleIfExists($id, $request['rule_order'], $this->post_type);
            $coupons_used = array();
            $coupon_keys = array('coupon_applied_any_one','coupon_applied_all_selected');
            foreach ($request['discount_rule'] as $index => $value) {
                foreach ($coupon_keys as $coupon_key){
                    if(isset($value[$coupon_key]) && !empty($value[$coupon_key])){
                        if(is_array($value[$coupon_key])){
                            $coupons_used = array_merge($coupons_used, $value[$coupon_key]);
                        }
                    }
                }
                $request['discount_rule'][$index] = FlycartWooDiscountRulesGeneralHelper::makeString($value);
            }

            if (isset($request['discount_rule'])) $request['discount_rule'] = json_encode($request['discount_rule']);

            if (is_null($id) || !isset($id)) return false;
            FlycartWooDiscountRulesGeneralHelper::resetUsedCoupons($id, $coupons_used);
            $request['wpml_language'] = FlycartWooDiscountRulesGeneralHelper::getWPMLLanguage();

            foreach ($request as $index => $value) {
                if (in_array($index, $form)) {
                    if (get_post_meta($id, $index)) {
                        update_post_meta($id, $index, $value);
                    } else {
                        add_post_meta($id, $index, $value);
                    }
                }
            }
        }

        /**
         * Load View Data.
         *
         * @param $option
         * @param integer $id to load post.
         * @return string mixed response.
         */
        public function view($option, $id)
        {
            $id = intval($id);
            if (!$id) return false;

            $post = get_post($id, 'OBJECT');
            if (isset($post)) {
                if (isset($post->ID)) {
                    $post->meta = get_post_meta($post->ID);
                }
            }
            return $post;
        }

        /**
         * List of Checklist.
         */
        public function checkPoint()
        {
            // Apply rules with products.
            // NOT YET USED.
            if ($this->discount_applied) return true;
        }

        /**
         * Load List of Rules.
         *
         * @return mixed
         */
        public function getRules($onlyCount = false)
        {
            if(self::$rules_loaded) return $this->rules = self::$cartRules;

            $post_args = array('post_type' => $this->post_type, 'numberposts' => '-1');
            $postData = \FlycartInput\FInput::getInstance();
            $request = $postData->getArray();
            if(is_admin() && isset($request['page']) && $request['page'] == 'woo_discount_rules'){
                $post_args['meta_key'] = 'rule_order';
                $post_args['orderby'] = 'meta_value_num';
                $post_args['order'] = 'DESC';
                if(isset($request['order']) && in_array($request['order'], array('asc', 'desc'))){
                    if($request['order'] == 'asc') $post_args['order'] = 'ASC';
                }
            }
            $posts = get_posts($post_args);

            if ($onlyCount) return count($posts);
            if (isset($posts) && count($posts) > 0) {
                $wpml_language = FlycartWooDiscountRulesGeneralHelper::getWPMLLanguage();
                foreach ($posts as $index => $item) {
                    $posts[$index]->meta = get_post_meta($posts[$index]->ID);
                    if(!empty($wpml_language) && $wpml_language != 'all'){
                        if(isset($posts[$index]->meta['wpml_language'])){
                            if(isset($posts[$index]->meta['wpml_language']['0'])){
                                if($posts[$index]->meta['wpml_language']['0'] != $wpml_language && $posts[$index]->meta['wpml_language']['0'] != '' && $posts[$index]->meta['wpml_language']['0'] != 'all') unset($posts[$index]);
                            }
                        }
                    }
                }

                $this->rules = $posts;
            }

            self::$rules_loaded = 1;
            self::$cartRules = $posts;

            return $posts;
        }

        /**
         * To Analyzing the Pricing Rules to Apply the Discount in terms of price.
         */
        public function analyse($woocommerce, $free_shipping_check = 0)
        {
            global $woocommerce;
            // Re-arranging the Rules.
            $this->organizeRules();
            // Apply Group of Rules.
            $this->applyRules();
            // Get Overall Discounts.
            $this->getDiscountAmount();

            //run an event
            do_action('woo_discount_rules_after_fetching_discount', $this);
            global $flycart_woo_discount_rules;
            $flycart_woo_discount_rules->cart_rules = $this;
            // Add a Coupon Virtually (Temporary access).
            if(!$free_shipping_check)
                if ($this->discount_total != 0) {
                    if(!FlycartWooDiscountRulesGeneralHelper::haveToApplyTheRules()) return false;
                    add_filter('woocommerce_get_shop_coupon_data', array($this, 'addVirtualCoupon'), 10, 2);
                    add_action('woocommerce_after_calculate_totals', array($this, 'applyFakeCoupons'));
                }
            if($this->product_discount_total) {
                add_action('woocommerce_after_calculate_totals', array($this, 'applyFakeCouponsForBOGO'));
                add_filter('woocommerce_get_shop_coupon_data', array($this, 'addVirtualCouponForBOGO'), 10, 2);
            }
        }

        /**
         * To Make record of discount changes.
         *
         * @return bool
         */
        public function makeLog()
        {
            if (is_null($this->coupon_code) || empty($this->coupon_code)) return false;

            $discount_log = array(
                'coupon_name' => $this->coupon_code,
                'discount' => $this->discount_total,
            );
            WC()->session->set('woo_cart_discount', json_encode($discount_log));
        }

        /**
         * Virtually add Coupon to apply the Discount.
         *
         * @param array $unknown_param
         * @param string $old_coupon_code Existing Coupon
         * @return array|bool
         */
        public function addVirtualCoupon($response, $old_coupon_code)
        {
            $coupon_code = $this->coupon_code;
            // Getting Coupon Remove status from Session.
            $is_removed = WC()->session->get('woo_coupon_removed');
            // If Both are same, then it won't added.
            if ($coupon_code == $is_removed) return false;
            if ($old_coupon_code == $coupon_code || wc_strtolower($old_coupon_code) == wc_strtolower($coupon_code)) {

                if ($this->postData->get('remove_coupon', false) == $coupon_code) return false;
                $this->makeLog();
                $discount_type = 'fixed_cart';
                $amount = $this->discount_total;
                if(FlycartWoocommerceVersion::wcVersion('3.2')){
                    if(!$this->has_category_in_rule){
                        $discount_type = 'percent';
                        //To calculate the percent from total
                        if($this->sub_total > 0) {
                            $amount = ((100 * $this->discount_total) / $this->sub_total);
                        }
                    }
                }

                $coupon = array(
                    'id' => 321123 . rand(2, 9),
                    'amount' => $amount,
                    'individual_use' => false,
                    'product_ids' => array(),
                    'exclude_product_ids' => array(),
                    'usage_limit' => '',
                    'usage_limit_per_user' => '',
                    'limit_usage_to_x_items' => '',
                    'usage_count' => '',
                    'expiry_date' => '',
                    'apply_before_tax' => 'yes',
                    'free_shipping' => false,
                    'product_categories' => array(),
                    'exclude_product_categories' => array(),
                    'exclude_sale_items' => false,
                    'minimum_amount' => '',
                    'maximum_amount' => '',
                    'customer_email' => '',
                );
                if(FlycartWoocommerceVersion::wcVersion('3.2')) {
                    $coupon['discount_type'] = $discount_type;
                } else {
                    $coupon['type'] = $discount_type;
                }
                return $coupon;
            }

            return $response;
        }

        public function addVirtualCouponForBOGO($response, $old_coupon_code)
        {
            $bogo_coupon_codes = $this->bogo_coupon_codes;
            $coupon_codes = array_keys($bogo_coupon_codes);
            // Getting Coupon Remove status from Session.
            $is_removed = WC()->session->get('woo_coupon_removed');
            // If Both are same, then it won't added.
            if (in_array($is_removed, $coupon_codes)) return false;
            if (in_array($old_coupon_code, $coupon_codes) || in_array(wc_strtolower($old_coupon_code), $coupon_codes)) {
                if (in_array($this->postData->get('remove_coupon', false), $coupon_codes)) return false;
                $this->makeLog();
                $discount_type = 'fixed_cart';
                $amount = $bogo_coupon_codes[wc_strtolower($old_coupon_code)]['amount'];

                $coupon = array(
                    'id' => 321123 . rand(2, 9),
                    'amount' => $amount,
                    'individual_use' => false,
                    'product_ids' => array($bogo_coupon_codes[wc_strtolower($old_coupon_code)]['product_id']),
                    'exclude_product_ids' => array(),
                    'usage_limit' => '',
                    'usage_limit_per_user' => '',
                    'limit_usage_to_x_items' => '',
                    'usage_count' => '',
                    'expiry_date' => '',
                    'apply_before_tax' => 'yes',
                    'free_shipping' => false,
                    'product_categories' => array(),
                    'exclude_product_categories' => array(),
                    'exclude_sale_items' => false,
                    'minimum_amount' => '',
                    'maximum_amount' => '',
                    'customer_email' => '',
                );
                if(FlycartWoocommerceVersion::wcVersion('3.2')) {
                    $coupon['discount_type'] = $discount_type;
                } else {
                    $coupon['type'] = $discount_type;
                }
                return $coupon;
            }

            return $response;
        }

        /**
         * To Get the Coupon code that already specified.
         *
         * @return string
         */
        public function getCouponCode()
        {
            $config = new FlycartWooDiscountBase();
            $config = $config->getBaseConfig();

            if (is_string($config)) $config = json_decode($config, true);

            // Pre-Defined alternative Coupon Code.
            $coupon = 'Discount';

            // Verify and overwrite the Coupon Code.
            if (isset($config['coupon_name']) && $config['coupon_name'] != '') $coupon = $config['coupon_name'];
            return $coupon;
        }

        /**
         * Apply fake coupon to cart
         *
         * @access public
         * @return void
         */
        public function applyFakeCoupons()
        {
            global $woocommerce;

            // 'newyear' is a temporary coupon for validation.
            $coupon_code = apply_filters('woocommerce_coupon_code', $this->coupon_code);
            // Getting New Instance with the Coupon Code.
            $the_coupon = FlycartWoocommerceCoupon::wc_get_coupon($coupon_code);
            // Validating the Coupon as Valid and discount status.
            if ($the_coupon->is_valid() && !$woocommerce->cart->has_discount($coupon_code)) {

                // Do not apply coupon with individual use coupon already applied
                if ($woocommerce->cart->applied_coupons) {
                    foreach ($woocommerce->cart->applied_coupons as $code) {
                        $coupon = FlycartWoocommerceCoupon::wc_get_coupon($code);
                        if (FlycartWoocommerceCoupon::get_individual_use($coupon) == true) {
                            return false;
                        }
                    }
                }

                // Add coupon
                $woocommerce->cart->applied_coupons[] = $coupon_code;
                $trigger_applied_coupon_before_load_cart = apply_filters('woo_discount_rules_trigger_applied_coupon_before_load_cart', false);
                if($trigger_applied_coupon_before_load_cart){
                    add_action('woocommerce_before_cart', array($this, 'trigger_event_woocommerce_applied_coupon'));
                    add_action('woocommerce_review_order_before_cart_contents', array($this, 'trigger_event_woocommerce_applied_coupon'));
                } else {
                    remove_action('woocommerce_after_calculate_totals', array($this, 'applyFakeCoupons'));
                    do_action('woocommerce_applied_coupon', $coupon_code);
                    add_action('woocommerce_after_calculate_totals', array($this, 'applyFakeCoupons'));
                }

                return true;
            }
        }

        public function trigger_event_woocommerce_applied_coupon(){
            global $woocommerce;
            $coupon_code = apply_filters('woocommerce_coupon_code', $this->coupon_code);
            if(in_array($coupon_code, $woocommerce->cart->applied_coupons)){
                do_action('woocommerce_applied_coupon', $coupon_code);
            }
            if(!empty($this->bogo_coupon_codes)) {
                foreach ($this->bogo_coupon_codes as $coupon_code => $coupon_data) {
                    $coupon_code = apply_filters('woocommerce_coupon_code', $coupon_code);
                    if(in_array($coupon_code, $woocommerce->cart->applied_coupons)){
                        do_action('woocommerce_applied_coupon', $coupon_code);
                    }
                }
            }
        }

        public function applyFakeCouponsForBOGO()
        {
            global $woocommerce;
            if(!empty($this->bogo_coupon_codes)){
                foreach ($this->bogo_coupon_codes as $coupon_code => $coupon_data){
                    // 'newyear' is a temporary coupon for validation.
                    $coupon_code = apply_filters('woocommerce_coupon_code', $coupon_code);
                    // Getting New Instance with the Coupon Code.
                    $the_coupon = FlycartWoocommerceCoupon::wc_get_coupon($coupon_code);
                    // Validating the Coupon as Valid and discount status.
                    if ($the_coupon->is_valid() && !$woocommerce->cart->has_discount($coupon_code)) {

                        // Do not apply coupon with individual use coupon already applied
                        if ($woocommerce->cart->applied_coupons) {
                            foreach ($woocommerce->cart->applied_coupons as $code) {
                                $coupon = FlycartWoocommerceCoupon::wc_get_coupon($code);
                                if (FlycartWoocommerceCoupon::get_individual_use($coupon) == true) {
                                    return false;
                                }
                            }
                        }

                        // Add coupon
                        $woocommerce->cart->applied_coupons[] = $coupon_code;
                        $trigger_applied_coupon_before_load_cart = apply_filters('woo_discount_rules_trigger_applied_coupon_before_load_cart', false);
                        if($trigger_applied_coupon_before_load_cart){
                            add_action('woocommerce_before_cart', array($this, 'trigger_event_woocommerce_applied_coupon'));
                            add_action('woocommerce_review_order_before_cart_contents', array($this, 'trigger_event_woocommerce_applied_coupon'));
                        } else {
                            remove_action('woocommerce_after_calculate_totals', array($this, 'applyFakeCouponsForBOGO'));
                            do_action('woocommerce_applied_coupon', $coupon_code);
                            add_action('woocommerce_after_calculate_totals', array($this, 'applyFakeCouponsForBOGO'));
                        }
                    }
                }
            }
            return true;
        }

        /**
         * Simply remove or reset the virtual coupon by set "empty" as value
         * to "Woo's" session "woo_coupon_removed".
         *
         * @param $coupon
         */
        public function removeWoocommerceCoupon($coupon)
        {
            WC()->session->set('woo_coupon_removed', $coupon);
        }

        /**
         * @return string
         */
        public function woocommerceEnableCoupons()
        {
            return 'true';
        }

        /**
         *
         */
        public function organizeRules()
        {
            // Loads the Rules to Global.
            $this->getRules();
            // Validate and Re-Assign the Rules.
            $this->filterRules();
        }

        /**
         * @return bool
         */
        public function applyRules()
        {
            global $woocommerce;
            // If there is no rules, then return false.
            if (!isset($this->rules)) return false;

            // Check point having list of checklist to apply.
            if ($this->checkPoint()) return false;

            // To Generate Valid Rule sets.
            $this->generateRuleSets();
        }

        /**
         *
         */
        public function filterRules()
        {
            $rules = $this->rules;

            if (is_null($rules) || !isset($rules)) return false;
            // Start with empty set.
            $rule_set = array();
            foreach ($rules as $index => $rule) {
                $status = (isset($rule->status) ? $rule->status : false);

                // To Check as Plugin Active - InActive.
                if ($status == 'publish') {
                    $date_from = (isset($rule->date_from) ? $rule->date_from : '');
                    $date_to = (isset($rule->date_to) ? $rule->date_to : '');
                    $validateDate = FlycartWooDiscountRulesGeneralHelper::validateDateAndTime($date_from, $date_to);
                    // Validating Rule with Date of Expiry.
                    if ($validateDate) {
                        // Validating the Rule with its Order ID.
                        if (isset($rule->rule_order)) {
                            // If Order ID is '-', then this rule not going to implement.
                            if ($rule->rule_order !== '-') {
                                $rule_set[] = $rule;
                            }
                        }
                    }
                }
            }
            $this->rules = $rule_set;

            // To Order the Rules, based on its order ID.
            $this->orderRules();
        }

        /**
         * @return bool
         */
        public function orderRules()
        {
            if (empty($this->rules)) return false;

            $ordered_rules = array();

            // Make associative array with Order ID.
            foreach ($this->rules as $index => $rule) {
                if (isset($rule->rule_order)) {
                    if ($rule->rule_order != '') {
                        $ordered_rules[$rule->rule_order] = $rule;
                    }
                }
            }
            // Order the Rules with it's priority.
            ksort($ordered_rules);

            $this->rules = $ordered_rules;
        }

        /**
         * @return bool
         */
        public function generateRuleSets()
        {
            global $woocommerce;
            $rule_sets = array();

            if (!isset($this->rules)) return false;

            // Loop the Rules set to collect matched rules.
            foreach ($this->rules as $index => $rule) {
                // General Rule Info.
                $rule_sets[$index]['discount_type'] = 'price_discount';
                $rule_sets[$index]['name'] = (isset($rule->rule_name) ? $rule->rule_name : 'Rule_' . $index);
                $rule_sets[$index]['descr'] = (isset($rule->rule_descr) ? $rule->rule_descr : '');
                $rule_sets[$index]['method'] = (isset($rule->rule_method) ? $rule->rule_method : 'qty_based');
                $rule_sets[$index]['qty_based_on'] = (isset($rule->qty_based_on) ? $rule->qty_based_on : 'each_product');
                $rule_sets[$index]['date_from'] = (isset($rule->date_from) ? $rule->date_from : false);
                $rule_sets[$index]['date_to'] = (isset($rule->date_to) ? $rule->date_to : false);
                $rule_sets[$index]['discount_rule'] = (isset($rule->discount_rule) ? $rule->discount_rule : false);
                $rule_sets[$index]['discount_type'] = (isset($rule->discount_type) ? $rule->discount_type : false);
                $rule_sets[$index]['to_discount'] = (isset($rule->to_discount) ? $rule->to_discount : false);
                $rule_sets[$index]['cart_discounted_products'] = isset($rule->cart_discounted_products) ? json_decode($rule->cart_discounted_products) : array();
                $rule_sets[$index]['product_discount_quantity'] = isset($rule->product_discount_quantity) ? $rule->product_discount_quantity : 1;
                if (in_array($rule->discount_type, array('product_discount'))) {
                    $rule_sets[$index]['enabled'] = $this->validateBOGOCart($rule_sets[$index]['discount_rule'],$rule);
                }else{
                    $rule_sets[$index]['enabled'] = $this->validateCart($rule_sets[$index]['discount_rule']);
                }
            }
            $rule_sets = apply_filters('woo_discount_rules_cart_rule_sets_to_apply', $rule_sets);
            $this->rule_sets = $rule_sets;
        }

        /**
         * Get Overall discount amount across allover the rules that available.
         *
         * @return integer Total Discount Amount.
         */
        public function getDiscountAmount()
        {
            $discount = 0;
            $discounts = array();
            if (!isset($this->rule_sets)) return false;

            // Get settings
            $config = new FlycartWooDiscountBase();
            $config = $config->getBaseConfig();
            if (is_string($config)) $config = json_decode($config, true);
            if(isset($config['cart_setup'])){
                $cart_setup = $config['cart_setup'];
            } else {
                $cart_setup = 'all';
            }

            if(count($this->rule_sets)){
                if(in_array($cart_setup, array('first', 'all'))){
                    if($cart_setup == 'first'){
                        // Processing the Totals.
                        foreach ($this->rule_sets as $index => $rule) {
                            if ($rule['enabled'] == true) {
                                $discounts['name'][$index] = $rule['name'];
                                $discounts['type'][$index] = $rule['discount_type'];
                                if ($rule['discount_type'] == 'shipping_price') {
                                    $this->has_free_shipping = 1;
                                } else if ($rule['discount_type'] == 'price_discount') {
                                    // Getting the Flat Rate of Discount.
                                    $discounts['to_discount'][$index] = $this->calculateDiscount($this->sub_total, array('type' => 'price', 'value' => $rule['to_discount']));
                                } else if($rule['discount_type'] == 'product_discount'){
                                    // Calculate product discount
                                    $this->calculateProductDiscount($rule['cart_discounted_products'],$rule['product_discount_quantity']);
                                } else {
                                    //we will have to re-calculate the sub-total if it has category selected
                                    if($this->is_category_specific($rule)) {
                                        if(!empty($this->cart_items)){
                                            if(!did_action('woocommerce_before_calculate_totals')){
                                                do_action('woocommerce_before_calculate_totals', FlycartWoocommerceCart::get_cart_object());
                                            }
                                        }
                                        $this->has_category_in_rule = 1;
                                        //re-calculate the sub-total
                                        $subtotal = $this->calculate_conditional_subtotal($this->get_discounted_categories_from_json($rule));
                                    } else {
                                        $subtotal = $this->sub_total;
                                    }
                                    // Getting the Percentage level of Discount.
                                    $discounts['to_discount'][$index] = $this->calculateDiscount($subtotal, array('type' => 'percentage', 'value' => $rule['to_discount']));
                                }
                                if(isset($discounts['to_discount']) && isset($discounts['to_discount'][$index])) {
                                    // Sum of Available discount list.
                                    $discount += $discounts['to_discount'][$index];
                                }
                                // Update the status of the status of the discount rule.
                                $discounts['is_enabled'][$index] = $rule['enabled'];
                                break;
                            }
                        }
                    } else {
                        // Processing the Totals.
                        foreach ($this->rule_sets as $index => $rule) {
                            if ($rule['enabled'] == true) {
                                $discounts['name'][$index] = $rule['name'];
                                $discounts['type'][$index] = $rule['discount_type'];
                                if ($rule['discount_type'] == 'shipping_price') {
                                    $this->has_free_shipping = 1;
                                } else if ($rule['discount_type'] == 'price_discount') {
                                    // Getting the Flat Rate of Discount.
                                    $discounts['to_discount'][$index] = $this->calculateDiscount($this->sub_total, array('type' => 'price', 'value' => $rule['to_discount']));
                                } else if($rule['discount_type'] == 'product_discount'){
                                    // Calculate product discount
                                    $this->calculateProductDiscount($rule['cart_discounted_products'],$rule['product_discount_quantity']);
                                } else {
                                    //we will have to re-calculate the sub-total if it has category selected
                                    if($this->is_category_specific($rule)) {
                                        $this->has_category_in_rule = 1;
                                        //re-calculate the sub-total
                                        $subtotal = $this->calculate_conditional_subtotal($this->get_discounted_categories_from_json($rule));
                                    }else {
                                        $subtotal = $this->sub_total;
                                    }
                                    // Getting the Percentage level of Discount.
                                    $discounts['to_discount'][$index] = $this->calculateDiscount($subtotal, array('type' => 'percentage', 'value' => $rule['to_discount']));
                                }
                                if(isset($discounts['to_discount']) && isset($discounts['to_discount'][$index])){
                                    // Sum of Available discount list.
                                    $discount += $discounts['to_discount'][$index];
                                }

                                // Update the status of the status of the discount rule.
                                $discounts['is_enabled'][$index] = $rule['enabled'];
                            }
                        }
                    }
                } else if($cart_setup == 'biggest'){
                    $biggestDiscount = 0;
                    // Processing the Totals.
                    foreach ($this->rule_sets as $index => $rule) {
                        if ($rule['enabled'] == true) {
                            if ($rule['discount_type'] == 'shipping_price') {
                                $this->has_free_shipping = 1;
                                $newDiscount = 0;
                            } else if ($rule['discount_type'] == 'price_discount') {
                                // Getting the Flat Rate of Discount.
                                $newDiscount = $this->calculateDiscount($this->sub_total, array('type' => 'price', 'value' => $rule['to_discount']));
                            } else if($rule['discount_type'] == 'product_discount'){
                                // Calculate product discount
                                $this->calculateProductDiscount($rule['cart_discounted_products'],$rule['product_discount_quantity']);
                            } else {
                                //we will have to re-calculate the sub-total if it has category selected
                                if($this->is_category_specific($rule)) {
                                    $this->has_category_in_rule = 1;
                                    //re-calculate the sub-total
                                    $subtotal = $this->calculate_conditional_subtotal($this->get_discounted_categories_from_json($rule));
                                }else {
                                    $subtotal = $this->sub_total;
                                }
                                // Getting the Percentage level of Discount.
                                $newDiscount = $this->calculateDiscount($subtotal, array('type' => 'percentage', 'value' => $rule['to_discount']));
                            }

                            if($newDiscount > $biggestDiscount){
                                $biggestDiscount = $newDiscount;
                                $discounts['name'][1] = $rule['name'];
                                $discounts['type'][1] = $rule['discount_type'];
                                $discounts['to_discount'][1] = $newDiscount;
                                $discount = $newDiscount;
                                // Update the status of the status of the discount rule.
                                $discounts['is_enabled'][1] = $rule['enabled'];
                            }
                        }
                    }
                }
            }

            $this->discount_total = $discount;
            $this->matched_discounts = $discounts;
            return $discounts;
        }

        /**
         * Check is specific to category
         * */
        public function is_category_specific($rule) {
            if(count($this->get_discounted_categories_from_json($rule))) {
                return true;
            }
            return false;
        }

        /**
         * get discount categories from rule
         * */
        public function get_discounted_categories_from_json($rule)
        {
            $categories = array();
            if ( ! empty( $rule['discount_rule'] ) )
            {
                if(!is_object($rule['discount_rule'])) {
                    //assume it is a json string and parse
                    $rules = json_decode($rule['discount_rule'], true);
                }

                if(count($rules)) {
                    foreach($rules as $rule) {
                        if(array_key_exists('categories_in', $rule)) {
                            $categories = $rule['categories_in'];
                            break;
                        }
                        if(array_key_exists('in_each_category', $rule)) {
                            $categories = $rule['in_each_category'];
                            break;
                        }
                        if(array_key_exists('atleast_one_including_sub_categories', $rule)) {
                            $categories = FlycartWooDiscountRulesGeneralHelper::getAllSubCategories($rule['atleast_one_including_sub_categories']);
                            break;
                        }
                    }
                }
            }
            return $categories;
        }

        /**
         * Comparing the Rules with the each line item to check
         * and return as, matched or not.
         *
         * @param array $rules
         * @return bool true|false
         */
        public function validateCart($rules)
        {
            $this->calculateCartSubtotal();
            $rules = (is_string($rules) ? json_decode($rules, true) : array());
            // Simple array helper to re-arrange the structure.
            FlycartWooDiscountRulesGeneralHelper::reArrangeArray($rules);
            if(is_array($rules) && count($rules)){
                foreach ($rules as $index => $rule) {
                    // Validating the Rules one by one.
                    if ($this->applyRule($index, $rule, $rules) == false) {
                        return false;
                    }
                }
            }
            return true;
        }

        /**
         * Applying bunch amount of rules with the line item.
         *
         * @param string $index Index of the Rule
         * @param array $rule array of rule info.
         * @return bool true|false as matched or not.
         */
        public function applyRule($index, $rule, $rules)
        {
            $skipRuleType = array('categories_in', 'in_each_category', 'atleast_one_including_sub_categories');
            $availableRuleToSkip = array_intersect($skipRuleType, array_keys($rules));
            switch ($index) {

                // Cart Subtotal.
                case 'subtotal_least':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($this->sub_total < $rule) {
                        return false;
                    }
                    return true;
                    break;
                case 'subtotal_less':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($this->sub_total >= $rule) {
                        return false;
                    }
                    return true;
                    break;

                // Cart Item Count.
                case 'item_count_least':
                    if(!empty($availableRuleToSkip)){
                    } elseif (count($this->cart_items) < $rule) {
                        return false;
                    }
                    return true;
                    break;
                case 'item_count_less':
                    if(!empty($availableRuleToSkip)){
                    } elseif (count($this->cart_items) >= $rule) {
                        return false;
                    }
                    return true;
                    break;

                // Quantity Count.
                case 'quantity_least':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($this->cartItemQtyTotal() < $rule) {
                        return false;
                    }
                    return true;
                    break;
                case 'quantity_less':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($this->cartItemQtyTotal() >= $rule) {
                        return false;
                    }
                    return true;
                    break;

                // Logged In Users.
                case 'users_in':
                    $rule = FlycartWoocommerceVersion::backwardCompatibilityStringToArray($rule);
                    if (get_current_user_id() == 0 || !in_array(get_current_user_id(), $rule)) {
                        return false;
                    }
                    return true;
                    break;
                case 'shipping_countries_in':
//                    $user_meta = get_user_meta(get_current_user_id());
                    $shippingCountry = WC()->customer->get_shipping_country();
//                    if (!$user_meta || !isset($user_meta['shipping_country']) || empty($user_meta['shipping_country']) || !in_array($user_meta['shipping_country'][0], $rule)) {
                    if (empty($shippingCountry) || !in_array($shippingCountry, $rule)) {
                        return false;
                    }
                    return true;
                    break;
                case 'roles_in':
                    if (count(array_intersect(FlycartWooDiscountRulesGeneralHelper::getCurrentUserRoles(), $rule)) == 0) {
                        return false;
                    }
                    return true;
                    break;
                case ($index == 'customer_email_tld' || $index == 'customer_email_domain'):
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = trim($r);
                            $rule[$key] = trim($rule[$key], '.');
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    $postBillingEmail = $this->postData->get('billing_email', '', 'raw');
                    if($postBillingEmail != ''){
                        $postDataArray['billing_email'] = $postBillingEmail;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['billing_email'] = FlycartWoocommerceOrder::get_billing_email($order);
                        }
                    }
                    if(isset($postDataArray['billing_email']) && $postDataArray['billing_email'] != ''){
                        $user_email = $postDataArray['billing_email'];
                        if(get_current_user_id()){
                            update_user_meta(get_current_user_id(), 'billing_email', $user_email);
                        }
                        if($index == 'customer_email_tld')
                            $tld = $this->getTLDFromEmail($user_email);
                        else
                            $tld = $this->getDomainFromEmail($user_email);
                        if(in_array($tld, $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $user_email = get_user_meta( get_current_user_id(), 'billing_email', true );
                        if($user_email != '' && !empty($user_email)){
                            if($index == 'customer_email_tld')
                                $tld = $this->getTLDFromEmail($user_email);
                            else
                                $tld = $this->getDomainFromEmail($user_email);
                            if(in_array($tld, $rule)){
                                return true;
                            }
                        } else {
                            $user_details = get_userdata( get_current_user_id() );
                            if(isset($user_details->data->user_email) && $user_details->data->user_email != ''){
                                $user_email = $user_details->data->user_email;
                                if($index == 'customer_email_tld')
                                    $tld = $this->getTLDFromEmail($user_email);
                                else
                                    $tld = $this->getDomainFromEmail($user_email);
                                if(in_array($tld, $rule)){
                                    return true;
                                }
                            }
                        }
                    }
                    return false;
                    break;

                case 'customer_billing_city':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    $postBillingEmail = $this->postData->get('billing_city', '', 'raw');
                    if($postBillingEmail != ''){
                        $postDataArray['billing_city'] = $postBillingEmail;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['billing_city'] = FlycartWoocommerceOrder::get_billing_city($order);
                        }
                    }
                    if(isset($postDataArray['billing_city']) && $postDataArray['billing_city'] != ''){
                        $billingCity = $postDataArray['billing_city'];
                        if(in_array(strtolower($billingCity), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $billingCity = get_user_meta( get_current_user_id(), 'billing_city', true );
                        if($billingCity != '' && !empty($billingCity)){
                            if(in_array(strtolower($billingCity), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'customer_shipping_state':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    if(isset($postDataArray['ship_to_different_address']) && $postDataArray['ship_to_different_address']){
                        $shippingFieldName = 'shipping_state';
                    } else {
                        $shippingFieldName = 'billing_state';
                    }
                    $postShippingState = $this->postData->get($shippingFieldName, '', 'raw');
                    if($postShippingState != ''){
                        $postDataArray[$shippingFieldName] = $postShippingState;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['shipping_state'] = FlycartWoocommerceOrder::get_shipping_state($order);
                        }
                    }
                    if(isset($postDataArray[$shippingFieldName]) && $postDataArray[$shippingFieldName] != ''){
                        $shippingState = $postDataArray[$shippingFieldName];
                        if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $shippingState = get_user_meta( get_current_user_id(), 'shipping_state', true );
                        if($shippingState != '' && !empty($shippingState)){
                            if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'customer_shipping_city':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    if(isset($postDataArray['ship_to_different_address']) && $postDataArray['ship_to_different_address']){
                        $shippingFieldName = 'shipping_city';
                    } else {
                        $shippingFieldName = 'billing_city';
                    }
                    $postShippingState = $this->postData->get($shippingFieldName, '', 'raw');
                    if($postShippingState != ''){
                        $postDataArray[$shippingFieldName] = $postShippingState;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['shipping_city'] = FlycartWoocommerceOrder::get_shipping_city($order);
                        }
                    }
                    if(isset($postDataArray[$shippingFieldName]) && $postDataArray[$shippingFieldName] != ''){
                        $shippingState = $postDataArray[$shippingFieldName];
                        if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $shippingState = get_user_meta( get_current_user_id(), 'shipping_city', true );
                        if($shippingState != '' && !empty($shippingState)){
                            if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'customer_shipping_zip_code':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    if(isset($postDataArray['ship_to_different_address']) && $postDataArray['ship_to_different_address']){
                        $shippingFieldName = 'shipping_postcode';
                    } else {
                        $shippingFieldName = 'billing_postcode';
                    }
                    $postShippingState = $this->postData->get($shippingFieldName, '', 'raw');
                    if($postShippingState != ''){
                        $postDataArray[$shippingFieldName] = $postShippingState;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['shipping_postcode'] = FlycartWoocommerceOrder::get_shipping_city($order);
                        }
                    }
                    if(isset($postDataArray[$shippingFieldName]) && $postDataArray[$shippingFieldName] != ''){
                        $shippingState = $postDataArray[$shippingFieldName];
                        if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $shippingState = get_user_meta( get_current_user_id(), 'shipping_postcode', true );
                        if($shippingState != '' && !empty($shippingState)){
                            if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'categories_in':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsInSelectedCategory($index, $rule, $rules);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'atleast_one_including_sub_categories':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsInSelectedCategory($index, $rule, $rules, 1);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'in_each_category':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsInEachSelectedCategory($index, $rule, $rules);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'customer_based_on_purchase_history':
                case 'customer_based_on_purchase_history_order_count':
                case 'customer_based_on_purchase_history_product_order_count':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsBasedOnPurchaseHistory($index, $rule, $rules);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'coupon_applied_any_one':
                    if(!empty($rule)){
                        $ruleSuccess = $this->validateCartCouponAppliedAnyOne($index, $rule, $rules);
                        if($ruleSuccess){
                            if(is_string($rule)){
                            $coupons = explode(',', $rule);
                            } elseif (is_array($rule)){
                                $coupons = $rule;
                            } else {
                                return false;
                            }

                            FlycartWooDiscountRulesGeneralHelper::removeCouponPriceInCart($coupons);
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'coupon_applied_all_selected':
                    if(!empty($rule)){
                        $ruleSuccess = $this->validateCartCouponAppliedAllSelected($index, $rule, $rules);
                        if($ruleSuccess){
                            if(is_string($rule)){
                            $coupons = explode(',', $rule);
                            } elseif (is_array($rule)){
                                $coupons = $rule;
                            } else {
                                return false;
                            }
                            FlycartWooDiscountRulesGeneralHelper::removeCouponPriceInCart($coupons);
                            return true;
                        }
                    }
                    return false;
                    break;
            }

        }

        /**
         * check the any one of the selected coupon applied
         * */
        protected function validateCartCouponAppliedAnyOne($index, $rule, $rules){
            global $woocommerce;
            $allowed = 0;
            if(is_string($rule)){
            $coupons = explode(',', $rule);
            } elseif (is_array($rule)){
                $coupons = $rule;
            } else {
                return 0;
            }
            if(is_array($coupons) && count($coupons)){
                foreach ($coupons as $coupon){
                    if($woocommerce->cart->has_discount($coupon)){
                        $allowed = 1;
                        break;
                    }
                }
            }

            return $allowed;
        }

        /**
         * check the all the selected coupon applied
         * */
        protected function validateCartCouponAppliedAllSelected($index, $rule, $rules){
            global $woocommerce;
            $allowed = 0;
            if(is_string($rule)){
            $coupons = explode(',', $rule);
            } elseif (is_array($rule)){
                $coupons = $rule;
            } else {
                return 0;
            }
            if(is_array($coupons) && count($coupons)){
                foreach ($coupons as $coupon){
                    if(!$woocommerce->cart->has_discount($coupon)){
                        $allowed = 0;
                        break;
                    } else {
                        $allowed = 1;
                    }
                }
            }

            return $allowed;
        }

        /**
         * check the cart items satisfies purchase history rule
         * */
        protected function validateCartItemsBasedOnPurchaseHistory($index, $rule, $rules){
            $allowed = 0;
            $user = get_current_user_id();
            if($user){
                $purchase_history_status_list = isset($rule['purchase_history_order_status'])? $rule['purchase_history_order_status']: array('wc-completed');
                if(isset($rule['purchased_history_amount'])){
                    if($rule['purchased_history_amount'] >= 0){
                        $customerOrders = get_posts( array(
                            'numberposts' => -1,
                            'meta_key'    => '_customer_user',
                            'meta_value'  => $user,
                            'post_type'   => wc_get_order_types(),
                            'post_status' => $purchase_history_status_list,
                        ) );
                        $totalPurchasedAmount = $totalOrder = 0;
                        if(!empty($customerOrders)){
                            foreach ($customerOrders as $customerOrder) {
                                $order = FlycartWoocommerceOrder::wc_get_order($customerOrder->ID);
                                $total = FlycartWoocommerceOrder::get_total($order);
                                if($index == 'customer_based_on_purchase_history_product_order_count' && isset($rule['purchase_history_products'])){
                                    $products = $this->getProductsFromRule($rule['purchase_history_products']);
                                    $product_ids = FlycartWoocommerceOrder::get_product_ids($order);
                                    if(!empty($products)){
                                        if (!count(array_intersect($products, $product_ids)) > 0) {
                                            continue;
                                        }
                                    }
                                }
                                $totalPurchasedAmount += $total;
                                $totalOrder++;
                            }
                        }

                        $totalAmount = $totalPurchasedAmount;
                        if($index == 'customer_based_on_purchase_history_order_count' || $index == 'customer_based_on_purchase_history_product_order_count'){
                            $totalAmount = $totalOrder;
                        }
                        $purchased_history_type = isset($rule['purchased_history_type'])? $rule['purchased_history_type']: 'atleast';
                        if($purchased_history_type == 'less_than_or_equal'){
                            if($totalAmount <= $rule['purchased_history_amount']){
                                $allowed = 1;
                            }
                        } else {
                            if($totalAmount >= $rule['purchased_history_amount']){
                                $allowed = 1;
                            }
                        }
                    }
                }
            }

            return $allowed;
        }

        /**
         * get product from rule
         * */
        public function getProductsFromRule($product){
            $productInArray = array();
            if(empty($product)) return $productInArray;
            if(is_array($product)) $productInArray = $product;
            else if(is_string($product)){
                $productInArray = json_decode($product);
                $productInArray = FlycartWoocommerceVersion::backwardCompatibilityStringToArray($productInArray);
            }
            if(!is_array($productInArray)){
                $productInArray = array();
            }
            return $productInArray;
        }

        /**
         * verify the cart items are from selected category
         * */
        protected function validateCartItemsInSelectedCategory($index, $rule, $rules, $check_child_category = 0){
            if($check_child_category){
                $rule = FlycartWooDiscountRulesGeneralHelper::getAllSubCategories($rule);
            }
            $ruleSuccess = 0;
            global $woocommerce;
            $categoryFound = $sub_total = $quantity = $item_count = 0;
            if(count($woocommerce->cart->cart_contents)){
                foreach ($woocommerce->cart->cart_contents as $key => $cartItem) {
                    $terms = get_the_terms( $cartItem['product_id'], 'product_cat' );
                    $categoryMatches = 0;
                    if($terms){
                        foreach ($terms as $term) {
                            if(in_array($term->term_id, $rule)){
                                $categoryMatches = 1;
                                $categoryFound = 1;
                                break;
                            }
                        }
                    }
                    if($categoryMatches){
                        $sub_total += $cartItem['line_subtotal'];//+$cartItem['line_subtotal_tax'];
                        $quantity += $cartItem['quantity'];
                        $item_count++;
                    }
                }
            }
            if($categoryFound){
                $ruleSuccess = 1;
                if(is_array($rules) && count($rules)){
                    foreach ($rules as $rule_type => $rule_values){
                        $checkRuleTypes = array('quantity_least', 'quantity_less', 'subtotal_least', 'subtotal_less', 'item_count_least', 'item_count_less');
                        if(in_array($rule_type, $checkRuleTypes)){
                            if($rule_type == 'subtotal_least'){
                                if ($sub_total < $rule_values) {
                                    $ruleSuccess = 0;
                                }
                            } elseif ($rule_type == 'subtotal_less'){

                                if ($sub_total >= $rule_values) {
                                    $ruleSuccess = 0;
                                }
                            } elseif ($rule_type == 'item_count_least'){
                                if ($item_count < $rule_values) {
                                    $ruleSuccess = 0;
                                }
                            } elseif ($rule_type == 'item_count_less'){
                                if ($item_count >= $rule_values) {
                                    $ruleSuccess = 0;
                                }
                            } elseif ($rule_type == 'quantity_least'){
                                if ($quantity < $rule_values) {
                                    $ruleSuccess = 0;
                                }
                            } elseif ($rule_type == 'quantity_less'){
                                if ($quantity >= $rule_values) {
                                    $ruleSuccess = 0;
                                }
                            }
                        }
                    }
                }
            }

            return $ruleSuccess;
        }

        /**
         * verify the cart items are from each selected category
         * */
        protected function validateCartItemsInEachSelectedCategory($index, $rule, $rules){
            $ruleSuccess = 0;
            if(is_array($rule)){
                foreach ($rule as $category){
                    $category_matched = $this->validateCartItemsInSelectedCategory($index, array($category), $rules);
                    if($category_matched) $ruleSuccess = 1;
                    else {
                        $ruleSuccess = 0;
                        break;
                    }
                }
            }

            return $ruleSuccess;
        }

        /**
         * Get tld from email
         * */
        protected function getTLDFromEmail($email){
            $emailArray = explode('@', $email);
            if(isset($emailArray[1])){
                $emailDomainArray = explode('.', $emailArray[1]);
                if(count($emailDomainArray)>1){
                    unset($emailDomainArray[0]);
                }
                return implode('.', $emailDomainArray);
            }
            return $emailArray[0];
        }

        /**
         * Get tld from email
         * */
        protected function getDomainFromEmail($email){
            $emailArray = explode('@', $email);
            if(isset($emailArray[1])){
                return $emailArray[1];
            }
            return $emailArray[0];
        }

        /**
         * Get cart total amount
         *
         * @access public
         * @return float
         */
        public function calculateCartSubtotal()
        {
            if(!empty($this->cart_items)){
                if(!did_action('woocommerce_before_calculate_totals')){
                    do_action('woocommerce_before_calculate_totals', FlycartWoocommerceCart::get_cart_object());
                }
            }

            $cart_subtotal = 0;
            // Iterate over all cart items and
            if(is_array($this->cart_items) && count($this->cart_items)){
                foreach ($this->cart_items as $cart_item_key => $cart_item) {
                    $quantity = (isset($cart_item['quantity']) && $cart_item['quantity']) ? $cart_item['quantity'] : 1;
                    $cart_subtotal += ((float)FlycartWoocommerceProduct::get_price($cart_item['data'], true)) * $quantity;
                }
            }

            $this->sub_total = (float)$cart_subtotal;

        }

        public function calculate_conditional_subtotal($conditions) {

            $cart_subtotal = 0;
            // Iterate over all cart items and
            if(is_array($this->cart_items) && count($this->cart_items)){
                foreach ($this->cart_items as $cart_item_key => $cart_item) {

                    if($this->does_item_belong_to_category($conditions, $cart_item['data'])) {

                        //total should be specific to the products from certan categories
                        $quantity = (isset($cart_item['quantity']) && $cart_item['quantity']) ? $cart_item['quantity'] : 1;
                        $cart_subtotal += FlycartWoocommerceProduct::get_price($cart_item['data'], true) * $quantity;
                    }

                }
            }

            return (float)$cart_subtotal;

        }

        public function does_item_belong_to_category($categories, $product) {
            $cat_id = FlycartWoocommerceProduct::get_category_ids($product);
            $result = array_intersect($categories, $cat_id);
            if(is_array($result) && count($result) > 0) {
                return true;
            }
            return false;
        }

        /**
         * To Sum the Cart Item's Qty.
         *
         * @return int Total Qty of Cart.
         */
        public function cartItemQtyTotal()
        {
            global $woocommerce;
            $cart_items = $woocommerce->cart->cart_contents;
            $total_quantity = 0;
            if(is_array($cart_items) && count($cart_items)){
                foreach ($cart_items as $cart_item) {
                    $current_quantity = (isset($cart_item['quantity']) && $cart_item['quantity']) ? $cart_item['quantity'] : 1;
                    $total_quantity += $current_quantity;
                }
            }
            return $total_quantity;
        }

        /**
         * Overall Discount Calculation based on Percentage or Flat.
         *
         * @param integer $sub_total Subtotal of the Cart.
         * @param integer $adjustment percentage or discount of adjustment.
         * @return integer Final Discount Amount.
         */
        public function calculateDiscount($sub_total, $adjustment)
        {
            $sub_total = ($sub_total < 0) ? 0 : $sub_total;

            $discount = 0;

            if ($adjustment['type'] == 'percentage') {
                $discount = $sub_total * ($adjustment['value'] / 100);
            } else if ($adjustment['type'] == 'price') {
                $discount = $adjustment['value'];
            }

            return ($discount <= 0) ? 0 : $discount;
        }

        /**
         * @param array $product_ids - list of discount products from admin settings
         * @param int $discount_quantity - quantity of products to be discount
         * @param string $rule_text - Text to be shown for coupon code
         * @return int
         */
        public function calculateProductDiscount(array $product_ids = array(), $discount_quantity = 1,$rule_text ="")
        {
            if (empty($product_ids))
                return true;
            if(empty($rule_text))
                $rule_text = '{{product_name}} X {{quantity}}';
            $carts = FlycartWoocommerceCart::get_cart();
            if(empty($carts))
                return true;
            $added_products = array();
            foreach ($carts as $cart_item_key => $cart_item) {
                if (empty($cart_item['data'])) {
                    continue;
                }
                $product_id = FlycartWoocommerceProduct::get_id($cart_item['data']);
                $added_products[$product_id] = array('item_name'=> FlycartWoocommerceProduct::get_name($cart_item['data']), 'item_quantity' => $cart_item['quantity'], 'item' => $cart_item_key, 'item_price' => FlycartWoocommerceProduct::get_price($cart_item['data'], true));
            }
            if(is_array($product_ids) && count($product_ids)){
                foreach ($product_ids as $discounted_product_id) {
                    $discounted_price=0;
                    //Check the discounted product already found in cart
                    if (array_key_exists($discounted_product_id, $added_products)) {
                        $old_quantity = isset($added_products[$discounted_product_id]['item_quantity']) ? $added_products[$discounted_product_id]['item_quantity'] : 0;
                        if ($old_quantity < $discount_quantity) {
                            if (isset($added_products[$discounted_product_id]['item']) && !empty($added_products[$discounted_product_id]['item'])) {
                                FlycartWoocommerceCart::set_quantity($added_products[$discounted_product_id]['item'], $discount_quantity);
                            }
                        }
                        $discounted_price = ($discount_quantity * $added_products[$discounted_product_id]['item_price']);
                        $coupon_msg = str_replace(array('{{product_name}}','{{quantity}}'),array($added_products[$discounted_product_id]['item_name'],$discount_quantity),$rule_text);
                        $this->bogo_coupon_codes[wc_strtolower($coupon_msg)] = array('product_id'=>$discounted_product_id,'amount'=>$discounted_price);
                    } else {
                        //If product not in cart,then add to cart
                        $product = FlycartWoocommerceProduct::wc_get_product($discounted_product_id);
                        if($product) {
                            FlycartWoocommerceCart::add_to_cart($discounted_product_id, $discount_quantity);
                            do_action('woo_discount_rules_cart_rules_after_adding_free_product_to_cart');
                            $discounted_price = ($discount_quantity * FlycartWoocommerceProduct::get_price($product, true));
                            $coupon_msg = str_replace(array('{{product_name}}', '{{quantity}}'), array(FlycartWoocommerceProduct::get_name($product, true), $discount_quantity), $rule_text);
                            $this->bogo_coupon_codes[wc_strtolower($coupon_msg)] = array('product_id'=>$discounted_product_id,'amount'=>$discounted_price);
                        }
                    }
                    $this->product_discount_total += $discounted_price;
                }
            }
            return true;
        }

        /**
         * Validate for product discount rules
         * @param $conditions
         * @param $rule_set
         * @return bool
         */
        function validateBOGOCart($conditions,$rule_set){
            $this->calculateCartSubtotal();
            $rules = (is_string($conditions) ? json_decode($conditions, true) : array());
            // Simple array helper to re-arrange the structure.
            FlycartWooDiscountRulesGeneralHelper::reArrangeArray($rules);
            if(is_array($rules) && count($rules)){
                foreach ($rules as $index => $rule) {
                    // Validating the Rules one by one.
                    if ($this->applyCartBOGORule($index, $rule, $rules,$rule_set) == false) {
                        return false;
                    }
                }
            }
            return true;
        }

        /**
         * Rules for only BOGO products
         * @param $index
         * @param $rule
         * @param $rules
         * @param $rule_set
         * @return bool
         */
        function applyCartBOGORule($index, $rule, $rules,$rule_set){
            //Calculating subtotal, quantity for BOGO Products
            $cart = array();
            $free_line_item = 0;
            $free_quantity = 0;
            $free_item_price = 0;
            if(is_array($this->cart_items) && count($this->cart_items)){
                foreach ($this->cart_items as $cart_items){
                    $product_id = FlycartWoocommerceProduct::get_id($cart_items['data']);
                    $cart[$product_id]['quantity'] = $cart_items['quantity'];
                    $cart[$product_id]['price'] = FlycartWoocommerceProduct::get_price($cart_items['data'], true);
                    $cart[$product_id]['subtotal'] = $cart[$product_id]['price'] * $cart[$product_id]['quantity'];
                }
            }
            $discounted_products = (isset($rule_set->cart_discounted_products)) ? $rule_set->cart_discounted_products : '[]';
            $products = json_decode($discounted_products);
            $rule_discount_quantity = (isset($rule_set->product_discount_quantity)) ? $rule_set->product_discount_quantity : 0;
            if(FlycartWooDiscountRulesGeneralHelper::is_countable($products)){
                foreach ($products as $discounted_product_id) {
                    if(array_key_exists($discounted_product_id,$cart))
                    {
                        $free_line_item += 1;
                        $free_quantity += $rule_discount_quantity;
                        $product = FlycartWoocommerceProduct::wc_get_product($discounted_product_id);
                        $product_price = FlycartWoocommerceProduct::get_price($product, true);
                        $free_item_price += $product_price;
                    }
                }
            }
            $cart_quantity_except_free = array_sum(array_column($cart,'quantity')) - $free_quantity;
            $cart_subtotal_except_free = array_sum(array_column($cart,'subtotal')) - $free_item_price;
            $cart_line_item_except_free = (count($cart)-$free_line_item);

            $skipRuleType = array('categories_in', 'in_each_category', 'atleast_one_including_sub_categories');
            $availableRuleToSkip = array_intersect($skipRuleType, array_keys($rules));
            switch ($index) {
                // Cart Subtotal.
                case 'subtotal_least':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($cart_subtotal_except_free < $rule) {
                        return false;
                    }
                    return true;
                    break;
                case 'subtotal_less':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($cart_subtotal_except_free >= $rule) {
                        return false;
                    }
                    return true;
                    break;

                // Cart Item Count.
                case 'item_count_least':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($cart_line_item_except_free < $rule) {
                        return false;
                    }
                    return true;
                    break;
                case 'item_count_less':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($cart_line_item_except_free >= $rule) {
                        return false;
                    }
                    return true;
                    break;

                // Quantity Count.
                case 'quantity_least':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($cart_quantity_except_free < $rule) {
                        return false;
                    }
                    return true;
                    break;
                case 'quantity_less':
                    if(!empty($availableRuleToSkip)){
                    } elseif ($cart_quantity_except_free >= $rule) {
                        return false;
                    }
                    return true;
                    break;

                // Logged In Users.
                case 'users_in':
                    $rule = FlycartWoocommerceVersion::backwardCompatibilityStringToArray($rule);
                    if (get_current_user_id() == 0 || !in_array(get_current_user_id(), $rule)) {
                        return false;
                    }
                    return true;
                    break;
                case 'shipping_countries_in':
//                    $user_meta = get_user_meta(get_current_user_id());
                    $shippingCountry = WC()->customer->get_shipping_country();
//                    if (!$user_meta || !isset($user_meta['shipping_country']) || empty($user_meta['shipping_country']) || !in_array($user_meta['shipping_country'][0], $rule)) {
                    if (empty($shippingCountry) || !in_array($shippingCountry, $rule)) {
                        return false;
                    }
                    return true;
                    break;
                case 'roles_in':
                    if (count(array_intersect(FlycartWooDiscountRulesGeneralHelper::getCurrentUserRoles(), $rule)) == 0) {
                        return false;
                    }
                    return true;
                    break;
                case ($index == 'customer_email_tld' || $index == 'customer_email_domain'):
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = trim($r);
                            $rule[$key] = trim($rule[$key], '.');
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    $postBillingEmail = $this->postData->get('billing_email', '', 'raw');
                    if($postBillingEmail != ''){
                        $postDataArray['billing_email'] = $postBillingEmail;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['billing_email'] = FlycartWoocommerceOrder::get_billing_email($order);
                        }
                    }
                    if(isset($postDataArray['billing_email']) && $postDataArray['billing_email'] != ''){
                        $user_email = $postDataArray['billing_email'];
                        if(get_current_user_id()){
                            update_user_meta(get_current_user_id(), 'billing_email', $user_email);
                        }
                        if($index == 'customer_email_tld')
                            $tld = $this->getTLDFromEmail($user_email);
                        else
                            $tld = $this->getDomainFromEmail($user_email);
                        if(in_array($tld, $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $user_email = get_user_meta( get_current_user_id(), 'billing_email', true );
                        if($user_email != '' && !empty($user_email)){
                            if($index == 'customer_email_tld')
                                $tld = $this->getTLDFromEmail($user_email);
                            else
                                $tld = $this->getDomainFromEmail($user_email);
                            if(in_array($tld, $rule)){
                                return true;
                            }
                        } else {
                            $user_details = get_userdata( get_current_user_id() );
                            if(isset($user_details->data->user_email) && $user_details->data->user_email != ''){
                                $user_email = $user_details->data->user_email;
                                if($index == 'customer_email_tld')
                                    $tld = $this->getTLDFromEmail($user_email);
                                else
                                    $tld = $this->getDomainFromEmail($user_email);
                                if(in_array($tld, $rule)){
                                    return true;
                                }
                            }
                        }
                    }
                    return false;
                    break;

                case 'customer_billing_city':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    $postBillingEmail = $this->postData->get('billing_city', '', 'raw');
                    if($postBillingEmail != ''){
                        $postDataArray['billing_city'] = $postBillingEmail;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['billing_city'] = FlycartWoocommerceOrder::get_billing_city($order);
                        }
                    }
                    if(isset($postDataArray['billing_city']) && $postDataArray['billing_city'] != ''){
                        $billingCity = $postDataArray['billing_city'];
                        if(in_array(strtolower($billingCity), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $billingCity = get_user_meta( get_current_user_id(), 'billing_city', true );
                        if($billingCity != '' && !empty($billingCity)){
                            if(in_array(strtolower($billingCity), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'customer_shipping_state':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    if(isset($postDataArray['ship_to_different_address']) && $postDataArray['ship_to_different_address']){
                        $shippingFieldName = 'shipping_state';
                    } else {
                        $shippingFieldName = 'billing_state';
                    }
                    $postShippingState = $this->postData->get($shippingFieldName, '', 'raw');
                    if($postShippingState != ''){
                        $postDataArray[$shippingFieldName] = $postShippingState;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['shipping_state'] = FlycartWoocommerceOrder::get_shipping_state($order);
                        }
                    }
                    if(isset($postDataArray[$shippingFieldName]) && $postDataArray[$shippingFieldName] != ''){
                        $shippingState = $postDataArray[$shippingFieldName];
                        if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $shippingState = get_user_meta( get_current_user_id(), 'shipping_state', true );
                        if($shippingState != '' && !empty($shippingState)){
                            if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'customer_shipping_city':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    if(isset($postDataArray['ship_to_different_address']) && $postDataArray['ship_to_different_address']){
                        $shippingFieldName = 'shipping_city';
                    } else {
                        $shippingFieldName = 'billing_city';
                    }
                    $postShippingState = $this->postData->get($shippingFieldName, '', 'raw');
                    if($postShippingState != ''){
                        $postDataArray[$shippingFieldName] = $postShippingState;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['shipping_city'] = FlycartWoocommerceOrder::get_shipping_city($order);
                        }
                    }
                    if(isset($postDataArray[$shippingFieldName]) && $postDataArray[$shippingFieldName] != ''){
                        $shippingState = $postDataArray[$shippingFieldName];
                        if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $shippingState = get_user_meta( get_current_user_id(), 'shipping_city', true );
                        if($shippingState != '' && !empty($shippingState)){
                            if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'customer_shipping_zip_code':
                    $rule = explode(',', $rule);
                    if(is_array($rule) && count($rule)){
                        foreach($rule as $key => $r){
                            $rule[$key] = strtolower(trim($r));
                        }
                    }
                    $postData = $this->postData->get('post_data', '', 'raw');
                    $postDataArray = array();
                    if($postData != ''){
                        parse_str($postData, $postDataArray);
                    }
                    if(isset($postDataArray['ship_to_different_address']) && $postDataArray['ship_to_different_address']){
                        $shippingFieldName = 'shipping_postcode';
                    } else {
                        $shippingFieldName = 'billing_postcode';
                    }
                    $postShippingState = $this->postData->get($shippingFieldName, '', 'raw');
                    if($postShippingState != ''){
                        $postDataArray[$shippingFieldName] = $postShippingState;
                    }
                    if(!get_current_user_id()){
                        $order_id = $this->postData->get('order-received', 0);
                        if($order_id){
                            $order = FlycartWoocommerceOrder::wc_get_order($order_id);
                            $postDataArray['shipping_postcode'] = FlycartWoocommerceOrder::get_shipping_city($order);
                        }
                    }
                    if(isset($postDataArray[$shippingFieldName]) && $postDataArray[$shippingFieldName] != ''){
                        $shippingState = $postDataArray[$shippingFieldName];
                        if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                            return true;
                        }
                    } else if(get_current_user_id()){
                        $shippingState = get_user_meta( get_current_user_id(), 'shipping_postcode', true );
                        if($shippingState != '' && !empty($shippingState)){
                            if(in_array(strtolower($shippingState), $rule) || in_array(strtoupper($shippingState), $rule)){
                                return true;
                            }
                        }
                    }
                    return false;
                    break;
                case 'categories_in':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsInSelectedCategory($index, $rule, $rules);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'atleast_one_including_sub_categories':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsInSelectedCategory($index, $rule, $rules, 1);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'in_each_category':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsInEachSelectedCategory($index, $rule, $rules);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'customer_based_on_purchase_history':
                case 'customer_based_on_purchase_history_order_count':
                case 'customer_based_on_purchase_history_product_order_count':
                    if(count($rule)){
                        $ruleSuccess = $this->validateCartItemsBasedOnPurchaseHistory($index, $rule, $rules);
                        if($ruleSuccess){
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'coupon_applied_any_one':
                    if(!empty($rule)){
                        $ruleSuccess = $this->validateCartCouponAppliedAnyOne($index, $rule, $rules);
                        if($ruleSuccess){
                            if(is_string($rule)){
                                $coupons = explode(',', $rule);
                            } elseif (is_array($rule)){
                                $coupons = $rule;
                            } else {
                                return false;
                            }

                            FlycartWooDiscountRulesGeneralHelper::removeCouponPriceInCart($coupons);
                            return true;
                        }
                    }
                    return false;
                    break;
                case 'coupon_applied_all_selected':
                    if(!empty($rule)){
                        $ruleSuccess = $this->validateCartCouponAppliedAllSelected($index, $rule, $rules);
                        if($ruleSuccess){
                            if(is_string($rule)){
                                $coupons = explode(',', $rule);
                            } elseif (is_array($rule)){
                                $coupons = $rule;
                            } else {
                                return false;
                            }
                            FlycartWooDiscountRulesGeneralHelper::removeCouponPriceInCart($coupons);
                            return true;
                        }
                    }
                    return false;
                    break;
            }
        }
    }
}