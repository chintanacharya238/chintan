<?php
namespace CRH;

if ( ! defined( 'ABSPATH' ) ) exit;

class Areas {
	/* ---------------- Boot ---------------- */
    public static function init() : void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'assets' ] );

		// Product page modal + attach data on add-to-cart
		add_action( 'woocommerce_after_add_to_cart_button', [ __CLASS__, 'product_modal' ] );
		add_filter( 'woocommerce_add_cart_item_data', [ __CLASS__, 'add_cart_item_data' ], 10, 3 );
        add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate_add_to_cart' ], 10, 5 );

		// CART: inject the editor (classic template)
		add_action( 'woocommerce_after_cart_item_name', [ __CLASS__, 'cart_editor_action' ], 10, 2 );
        // Elementor Cart widget appends to title
        add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'append_cart_editor_to_name' ], 20, 3 );

		// CART: hide default qty UI when allocations exist
		add_filter( 'woocommerce_cart_item_quantity', [ __CLASS__, 'override_cart_qty' ], 20, 3 );

		// CART & CHECKOUT (not mini-cart): read-only display
		add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'show_allocated_in_cart_and_checkout' ], 10, 2 );

		// Persist to order + validate on checkout
		add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'to_order_item' ], 10, 4 );
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate_checkout' ], 10, 2 );

		// Admin & emails
		add_action( 'woocommerce_before_order_itemmeta', [ __CLASS__, 'display_allocated_areas_admin' ], 10, 3 );
		add_filter( 'woocommerce_display_item_meta', [ __CLASS__, 'format_allocated_label' ], 10, 3 );

		// AJAX: save allocations from Cart editor
		add_action( 'wp_ajax_crh_save_areas', [ __CLASS__, 'ajax_save_areas' ] );
		add_action( 'wp_ajax_nopriv_crh_save_areas', [ __CLASS__, 'ajax_save_areas' ] );

		// REST API expose allocations on order line items
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_fields' ] );

        // Restore allocations from session on cart load
        add_filter( 'woocommerce_get_cart_item_from_session', [ __CLASS__, 'hydrate_from_session' ], 10, 3 );
	}

	/* ---------------- Assets ---------------- */
    public static function assets() : void {
        $deps = [ 'jquery', 'underscore' ];

		wp_enqueue_style( 'crh-areas', CRH_AREAS_URL . 'assets/css/areas.css', [], CRH_AREAS_VER );
		wp_enqueue_script( 'crh-areas', CRH_AREAS_URL . 'assets/js/areas.js', $deps, CRH_AREAS_VER, true );

		if ( is_cart() ) {
			wp_enqueue_script( 'crh-cart', CRH_AREAS_URL . 'assets/js/crh-cart.js', $deps, CRH_AREAS_VER, true );
		}

		// Autocomplete names from current cart
		$names = [];
		if ( function_exists( 'WC' ) && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $line ) {
				if ( empty( $line['crh_areas'] ) || ! is_array( $line['crh_areas'] ) ) continue;
				foreach ( $line['crh_areas'] as $row ) {
					if ( ! empty( $row['name'] ) ) {
						$names[] = sanitize_text_field( $row['name'] );
					}
				}
			}
		}
		$names = array_values( array_unique( $names ) );

		$data = [
			'ajax'          => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'crh_areas' ),
			'cartAreaNames' => $names,
			'accordionOpen' => (int) Admin::get()['accordion_default_open'],
		];

		wp_localize_script( 'crh-areas', 'CRH_AREAS_DATA', $data );
		if ( is_cart() ) {
			wp_localize_script( 'crh-cart', 'CRH_AREAS_DATA', $data );
		}
	}

	/* ---------------- Product page ---------------- */
	public static function product_modal() : void {
		$o = Admin::get();
		if ( empty( $o['enabled'] ) ) return;
		if ( ! is_product() ) return; ?>
		<div id="crh-areas-modal" class="crh-modal" aria-hidden="true">
		  <div class="crh-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="crhModalTitle">
			<div class="crh-modal__header">
			  <h3 id="crhModalTitle"><?php esc_html_e('Assign Areas', 'crh-areas'); ?></h3>
			  <button type="button" class="crh-modal__close" aria-label="<?php esc_attr_e('Close', 'crh-areas'); ?>">&times;</button>
			</div>
			<div class="crh-modal__body">
			  <div class="crh-summary">
				<div class="crh-summary__left">
				  <div class="crh-product-mini"><?php echo wp_kses_post( wc_get_product( get_the_ID() )->get_image( 'thumbnail' ) ); ?></div>
				  <div class="crh-product-meta">
					<div class="crh-product-title"><?php echo esc_html( get_the_title() ); ?></div>
					<div class="crh-sku"><?php echo esc_html( wc_get_product( get_the_ID() )->get_sku() ); ?></div>
				  </div>
				</div>
				<div class="crh-summary__right">
				  <div class="crh-total"><span><?php esc_html_e('Total Qty', 'crh-areas'); ?></span><strong id="crh-total-qty">0</strong></div>
				</div>
			  </div>

			  <div class="crh-rows" id="crh-area-rows"></div>
			  <div class="crh-row-add"><button type="button" class="button crh-add-row"><?php esc_html_e('Add New', 'crh-areas'); ?></button></div>
			  <div class="crh-error" role="alert" aria-live="polite" style="display:none;"></div>
			</div>
			<div class="crh-modal__footer">
			  <button type="button" class="button button-secondary crh-no-areas"><?php esc_html_e('No Areas Required', 'crh-areas'); ?></button>
			  <button type="button" class="button button-primary crh-allocate"><?php esc_html_e('Add to Cart', 'crh-areas'); ?></button>
			</div>
		  </div>
		</div>
		<input type="hidden" name="crh_areas_json" id="crh_areas_json" value=""/>
		<?php
	}

	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) : array {
		if ( empty( $_POST['crh_areas_json'] ) ) return $cart_item_data;

		$areas = json_decode( wp_unslash( $_POST['crh_areas_json'] ), true );
		if ( ! is_array( $areas ) ) return $cart_item_data;

		$total = 0; $clean = [];
		foreach ( $areas as $row ) {
			$name = sanitize_text_field( $row['name'] ?? '' );
			$qty  = max( 0, (int) ( $row['qty'] ?? 0 ) );
			if ( $name && $qty > 0 ) {
				$clean[] = [ 'name' => $name, 'qty' => $qty ];
				$total  += $qty;
			}
		}
		if ( $clean ) {
			$cart_item_data['crh_areas']       = $clean;
			$cart_item_data['crh_areas_total'] = $total;
		}
		return $cart_item_data;
	}

    /**
     * Server-side validation when adding to cart from product page.
     * Ensures area allocations (if provided) sum to requested quantity.
     */
    public static function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {
        if ( empty( $_POST['crh_areas_json'] ) ) {
            return $passed; // No allocations provided => allow
        }

        $areas = json_decode( wp_unslash( $_POST['crh_areas_json'] ), true );
        if ( ! is_array( $areas ) ) {
            return $passed; // malformed or "No Areas Required" flow => ignore
        }

        $sum = 0;
        foreach ( $areas as $row ) {
            $q = max( 0, (int) ( $row['qty'] ?? 0 ) );
            $name = sanitize_text_field( $row['name'] ?? '' );
            if ( $name && $q > 0 ) {
                $sum += $q;
            }
        }

        if ( $sum > 0 && $sum !== (int) $quantity ) {
            wc_add_notice( __( 'Total allocated quantity does not match product quantity.', 'crh-areas' ), 'error' );
            return false;
        }

        return $passed;
    }

	/* ---------------- Cart render ---------------- */
	/** Classic cart template: print editor below product title */
	public static function cart_editor_action( $cart_item, $cart_item_key ) : void {
		if ( ! is_cart() ) return;
		if ( empty( $cart_item['crh_areas'] ) || ! is_array( $cart_item['crh_areas'] ) ) return;

		echo self::get_cart_editor_markup( $cart_item, $cart_item_key );
	}

	/** Elementor cart widget: append editor to the product title HTML */
	public static function append_cart_editor_to_name( $product_name, $cart_item, $cart_item_key ) {
		if ( ! is_cart() ) return $product_name;
		if ( did_action( 'woocommerce_after_cart_item_name' ) ) return $product_name; // avoid duplicate
		if ( empty( $cart_item['crh_areas'] ) || ! is_array( $cart_item['crh_areas'] ) ) return $product_name;
		return $product_name . self::get_cart_editor_markup( $cart_item, $cart_item_key );
	}

	/** One source of truth for the cart editor HTML (names + qty editable) */
	private static function get_cart_editor_markup( $cart_item, $cart_item_key ) : string {
		ob_start(); ?>
		<div class="crh-acc crh-card" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>">
		  <button class="crh-acc__toggle" type="button" aria-expanded="<?php echo Admin::get()['accordion_default_open'] ? 'true' : 'false'; ?>">
			<span class="crh-card__title"><?php esc_html_e('Allocated Areas', 'crh-areas'); ?></span>
			<span class="crh-chevron" aria-hidden="true">▾</span>
		  </button>

		  <div class="crh-acc__panel" <?php echo Admin::get()['accordion_default_open'] ? 'style="display:block"' : ''; ?>>
			<div class="crh-list crh-panel-rows">
			  <?php foreach ( $cart_item['crh_areas'] as $row ) : ?>
				<div class="crh-panel-row">
				  <input class="crh-area-name" type="text" value="<?php echo esc_attr( $row['name'] ); ?>" placeholder="<?php esc_attr_e('Area Name','crh-areas'); ?>" list="crh-cart-datalist"/>
				  <input class="crh-area-qty"  type="number" min="1" value="<?php echo esc_attr( (int) $row['qty'] ); ?>"/>
				  <button type="button" class="button crh-del-row" aria-label="<?php esc_attr_e('Remove','crh-areas'); ?>">×</button>
				</div>
			  <?php endforeach; ?>
			</div>

			<div class="crh-panel-actions">
			  <button type="button" class="button crh-add-row"><?php esc_html_e('Add New','crh-areas'); ?></button>
			  <button type="button" class="button button-primary crh-save"><?php esc_html_e('Save Allocation','crh-areas'); ?></button>
			  <span class="crh-panel-msg" role="alert" aria-live="polite"></span>
			</div>
		  </div>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Hide default +/- qty box on Cart when allocations exist */
	public static function override_cart_qty( $product_quantity, $cart_item_key, $cart_item ) {
		if ( ! is_cart() ) return $product_quantity;
		if ( empty( $cart_item['crh_areas'] ) || ! is_array( $cart_item['crh_areas'] ) ) return $product_quantity;

		$total = (int) $cart_item['quantity'];
		return '<span class="crh-managed-qty" aria-label="' . esc_attr__( 'Quantity managed by Allocated Areas', 'crh-areas' ) . '">'
		     . esc_html__( 'Managed in “Allocated Areas”', 'crh-areas' )
		     . ' <small>(' . esc_html( $total ) . ')</small></span>';
	}

	/** Read-only list in Cart & Checkout (hidden in mini-cart) */
	public static function show_allocated_in_cart_and_checkout( $item_data, $cart_item ) {
		if ( ! ( is_cart() || is_checkout() ) ) return $item_data;
		if ( empty( $cart_item['crh_areas'] ) || ! is_array( $cart_item['crh_areas'] ) ) return $item_data;

		$html = '<ul style="margin:0;padding-left:14px;">';
		foreach ( $cart_item['crh_areas'] as $r ) {
			$html .= '<li>' . esc_html( $r['name'] ) . ' — ' . (int) $r['qty'] . '</li>';
		}
		$html .= '</ul>';

		$item_data[] = [
			'key'     => __( 'Allocated Areas', 'crh-areas' ),
			'value'   => wp_strip_all_tags( $html ),
			'display' => $html,
		];
		return $item_data;
	}

	/* ---------------- Persist to order & validation ---------------- */
	public static function to_order_item( $item, $cart_item_key, $values, $order ) : void {
		if ( empty( $values['crh_areas'] ) || ! is_array( $values['crh_areas'] ) ) return;

		$item->add_meta_data( '_crh_areas', $values['crh_areas'] ); // structured
		$flat = [];
		foreach ( $values['crh_areas'] as $a ) $flat[] = sanitize_text_field( $a['name'] ) . ' — ' . (int) $a['qty'];
		if ( $flat ) $item->add_meta_data( 'Allocated Areas', implode( ', ', $flat ) ); // visible one-liner
	}

	public static function validate_checkout( $data, $errors ) : void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
		foreach ( WC()->cart->get_cart() as $line ) {
			if ( empty( $line['crh_areas'] ) ) continue;
			$sum = 0;
			foreach ( $line['crh_areas'] as $r ) $sum += (int) ( $r['qty'] ?? 0 );
			$line_qty = (int) $line['quantity'];
			if ( $sum !== $line_qty ) {
				$errors->add( 'crh_areas_mismatch', sprintf(
					esc_html__( 'Allocated areas must sum to %1$d (currently %2$d).', 'crh-areas' ),
					$line_qty, $sum
				) );
			}
		}
	}

	/* ---------------- Admin & emails ---------------- */
	public static function display_allocated_areas_admin( $item_id, $item, $product ) : void {
		$areas = $item->get_meta( '_crh_areas', true );
		if ( empty( $areas ) || ! is_array( $areas ) ) return;

		echo '<div class="crh-order-areas"><strong>' . esc_html__( 'Allocated Areas:', 'crh-areas' ) . '</strong><ul style="margin:4px 0 0 12px; list-style:disc;">';
		foreach ( $areas as $area ) {
			echo '<li>' . esc_html( $area['name'] ) . ' — ' . (int) $area['qty'] . '</li>';
		}
		echo '</ul></div>';
	}

	public static function format_allocated_label( $html, $item, $args ) : string {
		$areas = $item->get_meta( '_crh_areas', true );
		if ( empty( $areas ) || ! is_array( $areas ) ) return $html;

		$out = '<strong>' . esc_html__( 'Allocated Areas:', 'crh-areas' ) . '</strong><br><ul style="margin:0;padding-left:15px;">';
		foreach ( $areas as $r ) $out .= '<li>' . esc_html( $r['name'] ) . ' — ' . (int) $r['qty'] . '</li>';
		$out .= '</ul>';
		return $html . $out;
	}

	/* ---------------- AJAX ---------------- */
    public static function ajax_save_areas() : void {
		check_ajax_referer( 'crh_areas', 'nonce' );
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( [ 'msg' => 'Cart not available.' ] );
		}

		$key   = sanitize_text_field( $_POST['cart_item_key'] ?? '' );
		$areas = json_decode( wp_unslash( $_POST['areas'] ?? '[]' ), true );

		if ( ! $key || ! isset( WC()->cart->cart_contents[ $key ] ) ) {
			wp_send_json_error( [ 'msg' => 'Invalid cart item.' ] );
		}

		$line =& WC()->cart->cart_contents[ $key ];
		$qty   = (int) $line['quantity'];

		$sum = 0; $clean = [];
		foreach ( (array) $areas as $r ) {
			$name = sanitize_text_field( $r['name'] ?? '' );
			$q    = max( 0, (int) ( $r['qty'] ?? 0 ) );
			if ( $name && $q > 0 ) {
				$clean[] = [ 'name' => $name, 'qty' => $q ];
				$sum    += $q;
			}
		}

		if ( $sum !== $qty ) {
			wp_send_json_error( [ 'msg' => sprintf( __( 'Allocated (%1$d) must equal item qty (%2$d).', 'crh-areas' ), $sum, $qty ) ] );
		}

		$line['crh_areas']       = $clean;
		$line['crh_areas_total'] = $sum;
		WC()->cart->set_session();

		wp_send_json_success( [ 'msg' => __( 'Saved.', 'crh-areas' ) ] );
	}

    /**
     * Ensure crh_areas survives cart session (e.g., page refresh).
     */
    public static function hydrate_from_session( $cart_item, $values, $key ) {
        if ( isset( $values['crh_areas'] ) && is_array( $values['crh_areas'] ) ) {
            $cart_item['crh_areas'] = $values['crh_areas'];
        }
        if ( isset( $values['crh_areas_total'] ) ) {
            $cart_item['crh_areas_total'] = (int) $values['crh_areas_total'];
        }
        return $cart_item;
    }

	/* ---------------- REST API ---------------- */
	public static function register_rest_fields() : void {
		// Expose allocations on WC order line items (v3 schema)
		register_rest_field( 'shop_order', 'crh_allocations', [
			'get_callback' => function( $object ) {
				$order = wc_get_order( $object['id'] );
				if ( ! $order ) return [];
				$result = [];
				foreach ( $order->get_items() as $item_id => $item ) {
					$areas = $item->get_meta( '_crh_areas', true );
					if ( ! empty( $areas ) && is_array( $areas ) ) {
						$result[] = [
							'item_id' => $item_id,
							'product_id' => $item->get_product_id(),
							'variation_id' => $item->get_variation_id(),
							'areas' => array_map( function( $r ) {
								return [ 'name' => (string) ( $r['name'] ?? '' ), 'qty' => (int) ( $r['qty'] ?? 0 ) ];
							}, $areas ),
						];
					}
				}
				return $result;
			},
			'schema' => [
				'description' => __( 'Allocated areas per order line', 'crh-areas' ),
				'type' => 'array',
			],
		] );
	}
}
