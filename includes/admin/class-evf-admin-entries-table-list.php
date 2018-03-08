<?php
/**
 * EverestForms Entries Table List
 *
 * @package EverestForms\Admin
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Entries table list class.
 */
class EVF_Admin_Entries_Table_List extends WP_List_Table {

	/**
	 * Form ID.
	 *
	 * @var int
	 */
	public $form_id;

	/**
	 * Forms object.
	 *
	 * @var EVF_Form_Handler
	 */
	public $form;

	/**
	 * Forms object.
	 *
	 * @var EVF_Form_Handler[]
	 */
	public $forms;

	/**
	 * Form data as an array.
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Initialize the log table list.
	 */
	public function __construct() {
		// Fetch all forms.
		$this->forms = EVF()->form->get();

		// Check that the user has created at least one form.
		if ( ! empty( $this->forms ) ) {
			$this->form_id = ! empty( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : apply_filters( 'everest_forms_entry_list_default_form_id', absint( $this->forms[0]->ID ) );
			$this->form    = EVF()->form->get( $this->form_id );
			$this->form_data = ! empty( $this->form->post_content ) ? evf_decode( $this->form->post_content ) : '';
		}

		parent::__construct( array(
			'singular' => 'entry',
			'plural'   => 'entries',
			'ajax'     => false,
		) );
	}

	/**
	 * No items found text.
	 */
	public function no_items() {
		esc_html_e( 'Whoops, it appears you do not have any form entries yet.', 'everest-forms' );
	}

	/**
	 * Get list columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns               = array();
		$columns['cb']         = '<input type="checkbox" />';
		$columns               = $this->get_columns_form_fields( $columns );
		$columns['date']       = esc_html__( 'Date', 'everest-forms' );
		$columns['actions']    = esc_html__( 'Actions', 'everest-forms' );

		return apply_filters( 'everest_forms_entries_table_columns', $columns, $this->form_data );
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'id'   => array( 'title', false ),
			'date' => array( 'date', false ),
		);
	}

	/**
	 * Get the list of fields, that are disallowed to be displayed as column in a table.
	 *
	 * @return array
	 */
	public static function get_columns_form_disallowed_fields() {
		return (array) apply_filters( 'everest_forms_entries_table_fields_disallow', array( 'divider', 'html', 'pagebreak', 'captcha' ) );
	}

	/**
	 * Logic to determine which fields are displayed in the table columns.
	 *
	 * @param array $columns
	 * @param int   $display
	 *
	 * @return array
	 */
	public function get_columns_form_fields( $columns = array(), $display = 3 ) {
		$entry_columns = EVF()->form->get_meta( $this->form_id, 'entry_columns' );

		if ( ! $entry_columns && ! empty( $this->form_data['form_fields'] ) ) {
			$x = 0;
			foreach ( $this->form_data['form_fields'] as $id => $field ) {
				if ( ! in_array( $field['type'], self::get_columns_form_disallowed_fields(), true ) && $x < $display ) {
					$columns[ 'evf_field_' . $id ] = ! empty( $field['label'] ) ? wp_strip_all_tags( $field['label'] ) : esc_html__( 'Field', 'everest-forms' );
					$x++;
				}
			}
		} elseif ( ! empty( $entry_columns ) ) {
			foreach ( $entry_columns as $id ) {
				// Check to make sure the field as not been removed.
				if ( empty( $this->form_data['form_fields'][ $id ] ) ) {
					continue;
				}

				$columns[ 'evf_field_' . $id ] = ! empty( $this->form_data['form_fields'][ $id ]['label'] ) ? wp_strip_all_tags( $this->form_data['form_fields'][ $id ]['label'] ) : esc_html__( 'Field', 'everest-forms' );
			}
		}

		return $columns;
	}

	/**
	 * Column cb.
	 *
	 * @param  object $entry Entry object.
	 * @return string
	 */
	public function column_cb( $entry ) {
		return sprintf( '<input type="checkbox" name="%1$s[]" value="%2$s" />', $this->_args['singular'], $entry->entry_id );
	}

	/**
	 * Show specific form fields.
	 *
	 * @param  object $entry
	 * @param  string $column_name
	 * @return string
	 */
	public function column_form_field( $entry, $column_name ) {
		$field_id = str_replace( 'evf_field_', '', $column_name );

		if ( ! empty( $entry->meta[ $field_id ] ) ) {
			$value = $entry->meta[ $field_id ];

			// Limit to 5 lines.
			$lines = explode( "\n", $value );
			$value = array_slice( $lines, 0, 4 );
			$value = implode( "\n", $value );

			if ( count( $lines ) > 5 ) {
				$value .= '&hellip;';
			} elseif ( strlen( $value ) > 75 ) {
				$value = substr( $value, 0, 75 ) . '&hellip;';
			}

			$value = nl2br( wp_strip_all_tags( trim( $value ) ) );

			return apply_filters( 'everest_forms_html_field_value', $value, $entry->meta[ $field_id ], $this->form_data, 'entry-table' );
		} else {
			return '<span class="na">&mdash;</span>';
		}
	}

	/**
	 * Renders the columns.
	 *
	 * @param  object $entry
	 * @param  string $column_name
	 * @return string
	 */
	public function column_default( $entry, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				$value = absint( $entry->entry_id );
				break;

			case 'date':
				$value = date_i18n( get_option( 'date_format' ), strtotime( $entry->date_created ) + ( get_option( 'gmt_offset' ) * 3600 ) );
				break;

			default:
				if ( false !== strpos( $column_name, 'evf_field_' ) ) {
					$value = $this->column_form_field( $entry, $column_name );
				} else {
					$value = '';
				}
				break;
		}

		return apply_filters( 'everest_forms_entry_table_column_value', $value, $entry, $column_name );
	}

	/**
	 * Render the actions column.
	 *
	 * @param  object $entry
	 * @return string
	 */
	public function column_actions( $entry ) {
		if ( 'trash' !== $entry->status ) {
			$actions = array(
				'view'  => '<a href="' . esc_url( admin_url( 'admin.php?page=evf-entries&amp;form_id=' . $entry->form_id . '&amp;view-entry=' . $entry->entry_id ) ) . '">' . esc_html__( 'View', 'everest-forms' ) . '</a>',
				/* translators: %s: entry name */
				'trash' => '<a class="submitdelete" aria-label="' . esc_attr__( 'Trash form entry', 'everest-forms' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array(
					'trash'   => $entry->entry_id,
					'form_id' => $this->form_id,
				), admin_url( 'admin.php?page=evf-entries' ) ), 'trash-entry' ) ) . '">' . esc_html__( 'Trash', 'everest-forms' ) . '</a>',
			);
		} else {
			$actions = array(
				'untrash' => '<a aria-label="' . esc_attr__( 'Restore form entry from trash', 'everest-forms' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array(
					'untrash' => $entry->entry_id,
					'form_id' => $this->form_id,
				), admin_url( 'admin.php?page=evf-entries' ) ), 'untrash-entry' ) ) . '">' . esc_html__( 'Restore', 'everest-forms' ) . '</a>',
				'delete'  => '<a class="submitdelete" aria-label="' . esc_attr__( 'Delete form entry permanently', 'everest-forms' ) . '" href="' . esc_url( wp_nonce_url( add_query_arg( array(
					'delete'  => $entry->entry_id,
					'form_id' => $this->form_id,
				), admin_url( 'admin.php?page=evf-entries' ) ), 'delete-entry' ) ) . '">' . esc_html__( 'Delete Permanently', 'everest-forms' ) . '</a>',
			);
		}

		return implode( ' <span class="sep">|</span> ', apply_filters( 'everest_forms_entry_table_actions', $actions, $entry ) );
	}

	/**
	 * Get the status label for entries.
	 *
	 * @param string $status_name Status name.
	 * @param int    $amount      Amount of entries.
	 * @return array
	 */
	private function get_status_label( $status_name, $amount ) {
		$statuses = evf_get_entry_statuses();

		if ( isset( $statuses[ $status_name ] ) ) {
			return array(
				'singular' => sprintf( '%s <span class="count">(%s)</span>', esc_html( $statuses[ $status_name ] ), $amount ),
				'plural'   => sprintf( '%s <span class="count">(%s)</span>', esc_html( $statuses[ $status_name ] ), $amount ),
				'context'  => '',
				'domain'   => 'everest-forms',
			);
		}

		return array(
			'singular' => sprintf( '%s <span class="count">(%s)</span>', esc_html( $status_name ), $amount ),
			'plural'   => sprintf( '%s <span class="count">(%s)</span>', esc_html( $status_name ), $amount ),
			'context'  => '',
			'domain'   => 'everest-forms',
		);
	}

	/**
	 * Table list views.
	 *
	 * @return array
	 */
	protected function get_views() {
		$status_links  = array();
		$num_entries   = evf_get_count_entries_by_status( $this->form_id );
		$total_entries = array_sum( (array) $num_entries ) - $num_entries['trash'];
		$statuses      = array_keys( evf_get_entry_statuses() );
		$class         = empty( $_REQUEST['status'] ) ? ' class="current"' : ''; // WPCS: input var okay. CSRF ok.

		/* translators: %s: count */
		$status_links['all'] = "<a href='admin.php?page=evf-entries&amp;form_id=$this->form_id'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_entries, 'entries', 'everest-forms' ), number_format_i18n( $total_entries ) ) . '</a>';

		foreach ( $statuses as $status_name ) {
			$class = '';

			if ( empty( $num_entries[ $status_name ] ) || 'publish' === $status_name ) {
				continue;
			}

			if ( isset( $_REQUEST['status'] ) && sanitize_key( wp_unslash( $_REQUEST['status'] ) ) === $status_name ) { // WPCS: input var okay, CSRF ok.
				$class = ' class="current"';
			}

			$label = $this->get_status_label( $status_name, $num_entries[ $status_name ] );

			$status_links[ $status_name ] = "<a href='admin.php?page=evf-entries&amp;form_id=$this->form_id&amp;status=$status_name'$class>" . sprintf( translate_nooped_plural( $label, $num_entries[ $status_name ] ), number_format_i18n( $num_entries[ $status_name ] ) ) . '</a>';
		}

		return $status_links;
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		if ( isset( $_GET['status'] ) && 'trash' == $_GET['status'] ) {
			return array(
				'untrash' => __( 'Restore', 'everest-forms' ),
				'delete'  => __( 'Delete Permanently', 'everest-forms' )
			);
		}

		return array(
			'trash' => __( 'Move to Trash', 'everest-forms' )
		);
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( ! empty( $this->forms ) && 'top' == $which ) {
			$all_forms     = evf_get_all_forms();
			$selected_form = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : $this->form_id;
			?>
			<select id="filter-by-form-id" name="form_id">
				<?php foreach( $all_forms as $key => $form ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_form, $key ); ?>><?php esc_html_e( $form ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php echo esc_attr( 'Filter', 'everest-forms' ); ?>">
			<?php
		}

		if ( 'top' == $which && isset( $_GET['status'] ) && 'trash' == $_GET['status'] && current_user_can( 'delete_posts' ) ) {
			echo '<div class="alignleft actions"><a id="delete_all" class="button apply" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=evf-entries&form_id=' . $this->form_id . '&status=trash&empty_trash=1' ), 'empty_trash' ) ) . '">' . __( 'Empty Trash', 'everest-forms' ) . '</a></div>';
		}
	}

	/**
	 * Prepare table list items.
	 *
	 * @global wpdb $wpdb
	 */
	public function prepare_items( $args = array() ) {
		global $wpdb;

		$per_page     = $this->get_items_per_page( 'evf_forms_per_page' );
		$current_page = $this->get_pagenum();

		// Query args.
		$args = array(
			'status'  => 'publish',
			'form_id' => $this->form_id,
			'limit'   => $per_page,
			'offset'  => $per_page * ( $current_page - 1 ),
		);

		// Handle the status query.
		if ( ! empty( $_REQUEST['status'] ) ) { // WPCS: input var okay, CSRF ok.
			$args['status'] = sanitize_key( wp_unslash( $_REQUEST['status'] ) ); // WPCS: input var okay, CSRF ok.
		}

		if ( ! empty( $_REQUEST['s'] ) ) { // WPCS: input var okay, CSRF ok.
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ); // WPCS: input var okay, CSRF ok.
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) { // WPCS: input var okay, CSRF ok.
			$args['orderby'] = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ); // WPCS: input var okay, CSRF ok.
		}

		if ( ! empty( $_REQUEST['order'] ) ) { // WPCS: input var okay, CSRF ok.
			$args['order'] = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ); // WPCS: input var okay, CSRF ok.
		}

		// Get the entries.
		$entries     = evf_search_entries( $args );
		$this->items = array_map( 'evf_get_entry', $entries );

		// Get total items.
		$args['limit']  = -1;
		$args['offset'] = 0;
		$total_items    = count( evf_search_entries( $args ) );

		// Set the pagination.
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}
}
