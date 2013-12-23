<?php

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class VKWP__List_Table extends WP_List_Table {
	var $data=array();
	
	function __construct($class) {
		parent::__construct( array(
			'singular'=> 'wp_list_text_link', //Singular label
			//'plural' => $class, 
			'ajax'	=> false //We won't support Ajax for this table
		) );
	}
	
	function extra_tablenav( $which ) {
		echo '
			<ul class="subsubsub"> 
				<li class="all">
									
				</li>
			</ul>
		';		
	}

	function get_columns() {
		$columns = array(
			'id' 		=> __('id'),
			'title'		=> __('Title'),
			'album'		=> __('Album'),
			'export'	=> __('No export').'<input type="checkbox" name="cb_exportall" id="cb_exportall" value="true"  />'
		);
		return $columns;
	}	
	
	function prepare_items($items) {
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->total_items = count($items);
		$this->items = $items;
	}
	
	function column_default( $item, $column_name ) {
		switch( $column_name ) { 
			case 'title':
			case 'count':
			case 'id':
				return $item[ $column_name ];
			case 'album':
				return $item[ $column_name ];
			break;
			case 'export':
				return  '
						<input type="hidden" name="cb_export['.$item['id'].']"  value="false" /> 
						<input type="checkbox" class="exportitem" name="cb_export['.$item['id'].']" value="true" '.(($item[ $column_name ]==0)? "checked":"").' />
						';
			break;	
			default:
				return $item[ $column_name];
		}
	}
	
	function no_items() {
		_e( 'No items.' );
	}

}

?>