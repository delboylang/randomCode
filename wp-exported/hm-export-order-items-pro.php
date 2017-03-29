<?php

/**
 * DO NOT UPDATE ! 
 * 
 * Plugin Name: Export Order Items Pro for WooCommerce
 * Description: Export order items (products ordered) in CSV (Comma Separated Values) format, with product, line item, order, and customer data.
 * Version: 2.0.3
 * Author: Potent Plugins
 * Author URI: http://potentplugins.com/?utm_source=export-order-items-pro&utm_medium=link&utm_campaign=wp-plugin-author-uri
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 * 
 * History 
 * 
 * 1/11/2016 Erild added negative numbers and credit notes.
 * 
 * 23/11/2016 Del Langrish 
 * 
 * Disabled core functionality and locked down field names
 * Also added in additional functionality to detect and and force static posting of values to an agreed 
 * specification.  
 * 
 */
// Add Export Order Items to the WordPress admin
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);


add_action ( 'admin_menu', 'hm_xoiwcp_admin_menu' );
function hm_xoiwcp_admin_menu() {
	add_submenu_page ( 'woocommerce', 'Export Order Items', 'Export Order Items', 'view_woocommerce_reports', 'hm_xoiwcp', 'hm_xoiwcp_page' );
}
function hm_xoiwcp_default_report_settings() {
	return array (
			'report_time' => '30d',
			'report_start' => date ( 'Y-m-d', current_time ( 'timestamp' ) - (86400 * 31) ),
			'report_start_time' => '12:00:00 AM',
			'report_end' => date ( 'Y-m-d', current_time ( 'timestamp' ) - 86400 ),
			'report_end_time' => '12:00:00 AM',
			'order_statuses' => array (
					'wc-processing',
					'wc-on-hold',
					'wc-completed' 
			),
			'order_meta_filter_on' => 0,
			'order_meta_filter_key' => '',
			'order_meta_filter_value' => '',
			'order_meta_filter_value_2' => '',
			'order_meta_filter_op' => '=',
			'customer_role' => 0,
			'orderby' => 'order_id',
			'orderdir' => 'asc',
			'products' => 'all',
			'product_cats' => array (),
			'product_ids' => '',
			'product_tag_filter_on' => 0,
			'product_tag_filter' => '',
			'product_meta_filter_on' => 0,
			'product_meta_filter_key' => '',
			'product_meta_filter_value' => '',
			'product_meta_filter_value_2' => '',
			'product_meta_filter_op' => '=',
			'exclude_free' => 0,
			'exclude_free_after_discount' => 0,
			'include_shipping' => 0,
			'fields' => array (
					'product_id',
					'product_name',
					'quantity',
					'line_total',
					'order_date',
					'billing_name',
					'billing_email' 
			),
			'total_fields' => array (
					'quantity',
					'line_subtotal',
					'line_total',
					'line_tax',
					'line_total_with_tax' 
			),
			'order_shipping_total_once' => 0,
			'field_names' => array (),
			'include_header' => 1,
			'include_totals' => 0,
			'format' => 'CSV' 
	);
}

// This function generates the Product Sales Report page HTML
function hm_xoiwcp_page() {
	$savedReportSettings = get_option ( 'hm_xoiwcp_report_settings' );
	if (empty ( $savedReportSettings )) {
		$savedReportSettings = array (
				hm_xoiwcp_default_report_settings () 
		);
	}
	
	if (isset ( $_POST ['op'] ) && $_POST ['op'] == 'preset-del' && ! empty ( $_POST ['r'] ) && isset ( $savedReportSettings [$_POST ['r']] )) {
		unset ( $savedReportSettings [$_POST ['r']] );
		update_option ( 'hm_xoiwcp_report_settings', $savedReportSettings );
		$_POST ['r'] = 0;
		echo ('<script type="text/javascript">location.href = location.href;</script>');
	}
	
	$reportSettings = array_merge ( hm_xoiwcp_default_report_settings (), $savedReportSettings [isset ( $_POST ['r'] ) && isset ( $savedReportSettings [$_POST ['r']] ) ? $_POST ['r'] : 0] );
	
	$fieldOptions = array (
			'product_id' => 'Product ID',
			'product_sku' => 'Product SKU',
			'product_name' => 'Product Name',
			'variation_id' => 'Variation ID',
			'variation_sku' => 'Variation SKU',
			'variation_attributes' => 'Variation Attributes',
			'item_sku' => 'Item SKU',
			'product_categories' => 'Product Categories',
			'order_id' => 'Order ID',
			'order_status' => 'Order Status',
			'order_date' => 'Order Date/Time',
			'quantity' => 'Line Item Quantity',
			'line_subtotal' => 'Line Item Gross',
			'line_total' => 'Line Item Gross After Discounts',
			'line_tax' => 'Line Item Tax',
			'line_total_with_tax' => 'Line Item Total With Tax',
			'billing_name' => 'Billing Name',
			'billing_phone' => 'Billing Phone',
			'billing_email' => 'Billing Email',
			'billing_address' => 'Billing Address',
			'shipping_name' => 'Shipping Name',
			'shipping_phone' => 'Shipping Phone',
			'shipping_email' => 'Shipping Email',
			'shipping_address' => 'Shipping Address',
			'customer_order_note' => 'Customer Order Note',
			'order_note_most_recent' => 'Order Note - Most Recent',
			'order_shipping_methods' => 'Order Shipping Methods',
			'order_shipping_cost' => 'Order Shipping Cost',
			'order_shipping_tax' => 'Order Shipping Tax',
			'order_shipping_cost_with_tax' => 'Order Shipping Cost With Tax' 
	);
	
	include (dirname ( __FILE__ ) . '/admin/admin.php');
}

// Hook into WordPress init; this function performs report generation when
// the admin form is submitted
add_action ( 'init', 'hm_xoiwcp_on_init', 9999 );
function hm_xoiwcp_on_init() {
	global $pagenow;
	
	// Check if we are in admin and on the report page
	if (! is_admin ())
		return;
	if ($pagenow == 'admin.php' && isset ( $_GET ['page'] ) && $_GET ['page'] == 'hm_xoiwcp' && ! empty ( $_POST ['hm_xoiwcp_do_export'] )) {
		
		if(!staxo_setRequest()){
			die();
		}

		// Verify the nonce
		check_admin_referer ( 'hm_xoiwcp_do_export' );
		
		$newSettings = array_intersect_key ( $_POST, hm_xoiwcp_default_report_settings () );
		/*
		 * foreach ($newSettings as $key => $value)
		 * if (!is_array($value))
		 * $newSettings[$key] = htmlspecialchars($value);
		 */
		
		if (empty ( $newSettings ['include_header'] ))
			$newSettings ['include_header'] = 0;
			
			// Update the saved report settings
		$savedReportSettings = get_option ( 'hm_xoiwcp_report_settings' );
		$savedReportSettings [0] = array_merge ( hm_xoiwcp_default_report_settings (), $newSettings );
		
		if (! empty ( $_POST ['save_preset'] ))
			$savedReportSettings [] = array_merge ( $savedReportSettings [0], array (
					'preset_name' => strip_tags ( $_POST ['save_preset'] ) 
			) );
		
		update_option ( 'hm_xoiwcp_report_settings', $savedReportSettings );
		
		// Check if no fields are selected
		if (empty ( $_POST ['fields'] ))
			return;
			
			// Assemble the filename for the report download
		if ($_POST ['format'] != 'html' && $_POST ['format'] != 'html-enhanced') {
			$filename = 'Order Items Export - ';
			$filename .= date ( 'Y-m-d', current_time ( 'timestamp' ) );
		}
		
		// Send headers
		if ($_POST ['format'] == 'xlsx') {
			header ( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			$filename .= '.xlsx';
		} else if ($_POST ['format'] == 'xls') {
			header ( 'Content-Type: application/vnd.ms-excel' );
			$filename .= '.xls';
		} else if ($_POST ['format'] == 'html' || $_POST ['format'] == 'html-enhanced') {
			header ( 'Content-Type: text/html; charset=utf-8' );
		} else if ($_POST ['format'] == 'csv-ascii') {
		//	header ( 'Content-Type: text/csv; charset=iso-8859-1' );
			$filename .= '.csv';
		} else {
	//		header ( 'Content-Type: text/csv; charset=utf-8' );
			$filename .= '.csv';
		}
		if ($_POST ['format'] != 'html' && $_POST ['format'] != 'html-enhanced') {
			header ( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		}
		
		// Output the report header row (if applicable) and body
		$stdout = fopen ( 'php://output', 'w' );
		if ($_POST ['format'] == 'xlsx' || $_POST ['format'] == 'xls') {
			include_once (__DIR__ . '/HM_XLS_Export.php');
			$dest = new HM_XLS_Export ();
		} else if ($_POST ['format'] == 'html') {
			include_once (__DIR__ . '/HM_HTML_Export.php');
			$dest = new HM_HTML_Export ( $stdout );
		} else if ($_POST ['format'] == 'html-enhanced') {
			include_once (__DIR__ . '/HM_HTML_Enhanced_Export.php');
			$dest = new HM_HTML_Enhanced_Export ( $stdout );
		} else if ($_POST ['format'] == 'csv-ascii') {
			include_once (__DIR__ . '/HM_CSV_ASCII_Export.php');
			$dest = new HM_CSV_ASCII_Export ( $stdout );
		} else {
			include_once (__DIR__ . '/HM_CSV_Export.php');
			$dest = new HM_CSV_Export ( $stdout );
		}
		if (! empty ( $_POST ['include_header'] ))
		
		hm_xoiwcp_export_header ( $dest );
		hm_xoiwcp_export_body ( $dest );
	
		if ($_POST ['format'] == 'xlsx')
			$dest->outputXLSX ( 'php://output' );
		else if ($_POST ['format'] == 'xls')
			$dest->outputXLS ( 'php://output' );
		else {
			// Call destructor, if any
			$dest = null;
			
			fclose ( $stdout );
		}
		
		exit ();
	}
}

// This function outputs the report header row
function hm_xoiwcp_export_header($dest) {
	$header = array ();

	
	if (in_array ( $_REQUEST ['reportType'], array("invoice","customer") )) {
		$header []	=	"Sales Invoice/Credit Note ID";
	}
	
	foreach ( $_POST ['fields'] as $field ) {
		$header [] = $_POST ['field_names'] [$field];
	}
	if($_REQUEST ['reportType'] == "summary"){
		

		
		$header	=	array();
		$header[] 	=	'Product SKU';
		$header[] 	=	'Product Name';
		$header[]	=	'Quantity';	
	}

	$dest->putRow ( $header, true );
}

// This function generates and outputs the report body rows
function hm_xoiwcp_export_body($dest) {	
	global $woocommerce, $wpdb;	
	
	$totalProductArray	=	array();
	
	$refundFlag = false;
	if (in_array ( $_REQUEST ['reportType'], array("invoice","customer", "summary") )) {
		$refundFlag = true;
	}
	
	// Calculate report start and end dates (timestamps)
	switch ($_POST ['report_time']) {
		case '0d' :
			$end_date = strtotime ( 'midnight', current_time ( 'timestamp' ) );
			$start_date = $end_date;
			break;
		case '1d' :
			$end_date = strtotime ( 'midnight', current_time ( 'timestamp' ) ) - 86400;
			$start_date = $end_date;
			break;
		case '7d' :
			$end_date = strtotime ( 'midnight', current_time ( 'timestamp' ) ) - 86400;
			$start_date = $end_date - (86400 * 7);
			break;
		case '+7d' :
			$start_date = strtotime ( 'midnight', current_time ( 'timestamp' ) ) + 86400;
			$end_date = $start_date + (86400 * 7);
			break;
		case '1cm' :
			$start_date = strtotime ( date ( 'Y-m', current_time ( 'timestamp' ) ) . '-01 midnight -1month' );
			$end_date = strtotime ( '+1month', $start_date ) - 86400;
			break;
		case '0cm' :
			$start_date = strtotime ( date ( 'Y-m', current_time ( 'timestamp' ) ) . '-01 midnight' );
			$end_date = strtotime ( '+1month', $start_date ) - 86400;
			break;
		case '+1cm' :
			$start_date = strtotime ( date ( 'Y-m', current_time ( 'timestamp' ) ) . '-01 midnight +1month' );
			$end_date = strtotime ( '+1month', $start_date ) - 86400;
			break;
		case '+30d' :
			$start_date = strtotime ( 'midnight', current_time ( 'timestamp' ) ) + 86400;
			$end_date = $start_date + (86400 * 30);
			break;
		case 'custom' :
			$end_date = strtotime ( $_POST ['report_end_time'], strtotime ( $_POST ['report_end'] ) );
			$start_date = strtotime ( $_POST ['report_start_time'], strtotime ( $_POST ['report_start'] ) );
			break;
		default : // 30 days is the default
			$end_date = strtotime ( 'midnight', current_time ( 'timestamp' ) ) - 86400;
			$start_date = $end_date - (86400 * 30);
	}
	
	// Assemble order by string
	$orderby = (in_array ( $_POST ['orderby'], array (
			'order_id' 
	) ) ? $_POST ['orderby'] : 'product_id');
	$orderby .= ' ' . ($_POST ['orderdir'] == 'asc' ? 'ASC' : 'DESC');
	
	// Create a new WC_Admin_Report object
	include_once ($woocommerce->plugin_path () . '/includes/admin/reports/class-wc-admin-report.php');
	$wc_report = new WC_Admin_Report ();
	$wc_report->start_date = $start_date;
	$wc_report->end_date = $end_date;
	
	// Get report data
	
	$reportData = array (
			'_product_id' => array (
					'type' => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function' => '',
					'name' => 'product_id',
					'join_type' => (empty ( $_POST ['include_shipping'] ) ? 'INNER' : 'LEFT') 
			),
			'_variation_id' => array (
					'type' => 'order_item_meta',
					'order_item_type' => 'line_item',
					'function' => '',
					'name' => 'variation_id',
					'join_type' => 'LEFT' 
			),
			'order_id' => array (
					'type' => 'order_item',
					'function' => '',
					'name' => 'order_id' 
			)
			// 'join_type' => 'LEFT'
			,
			'order_item_id' => array (
					'type' => 'order_item',
					'function' => '',
					'name' => 'order_item_id' 
			)
			// 'join_type' => 'LEFT'
			 
	);
	
	// Fix refund quantity issues in WC < 2.6.0
	$needsRefundQtyFix = version_compare ( get_option ( 'woocommerce_db_version', '1.0' ), '2.6.0', '<' );
	if ($needsRefundQtyFix) {
		$reportData ['post_type'] = array (
				'type' => 'post_data',
				'function' => '',
				'name' => 'order_type' 
		);
	}
	
	if (in_array ( 'quantity', $_POST ['fields'] )) {
		$reportData ['_qty'] = array (
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => '',
				'name' => 'quantity',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'line_subtotal', $_POST ['fields'] )) {
		$reportData ['_line_subtotal'] = array (
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => '',
				'name' => 'line_subtotal',
				'join_type' => 'LEFT' 
		);
	}
	
	if (in_array ( 'line_total', $_POST ['fields'] )) {
		$reportData ['_line_total'] = array (
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => '',
				'name' => 'line_total',
				'join_type' => 'LEFT' 
		);
	}
	
	if (in_array ( 'order_status', $_POST ['fields'] )) {
		$reportData ['post_status'] = array (
				'type' => 'post_data',
				'function' => '',
				'name' => 'order_status' 
		);
	}
	if (in_array ( 'order_date', $_POST ['fields'] )) {
		$reportData ['post_date'] = array (
				'type' => 'post_data',
				'function' => '',
				'name' => 'order_date' 
		);
	}
	if (in_array ( 'billing_name', $_POST ['fields'] )) {
		$reportData ['_billing_first_name'] = array (
				'type' => 'meta',
				'name' => 'billing_first_name',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_billing_last_name'] = array (
				'type' => 'meta',
				'name' => 'billing_last_name',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'billing_phone', $_POST ['fields'] )) {
		$reportData ['_billing_phone'] = array (
				'type' => 'meta',
				'name' => 'billing_phone',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'billing_email', $_POST ['fields'] )) {
		$reportData ['_billing_email'] = array (
				'type' => 'meta',
				'name' => 'billing_email',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'billing_address', $_POST ['fields'] )) {
		$reportData ['_billing_address_1'] = array (
				'type' => 'meta',
				'name' => 'billing_address_1',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_billing_address_2'] = array (
				'type' => 'meta',
				'name' => 'billing_address_2',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_billing_city'] = array (
				'type' => 'meta',
				'name' => 'billing_city',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_billing_state'] = array (
				'type' => 'meta',
				'name' => 'billing_state',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_billing_postcode'] = array (
				'type' => 'meta',
				'name' => 'billing_postcode',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_billing_country'] = array (
				'type' => 'meta',
				'name' => 'billing_country',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'shipping_name', $_POST ['fields'] )) {
		$reportData ['_shipping_first_name'] = array (
				'type' => 'meta',
				'name' => 'shipping_first_name',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_shipping_last_name'] = array (
				'type' => 'meta',
				'name' => 'shipping_last_name',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'shipping_phone', $_POST ['fields'] )) {
		$reportData ['_shipping_phone'] = array (
				'type' => 'meta',
				'name' => 'shipping_phone',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'shipping_email', $_POST ['fields'] )) {
		$reportData ['_shipping_email'] = array (
				'type' => 'meta',
				'name' => 'shipping_email',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'shipping_address', $_POST ['fields'] )) {
		$reportData ['_shipping_address_1'] = array (
				'type' => 'meta',
				'name' => 'shipping_address_1',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_shipping_address_2'] = array (
				'type' => 'meta',
				'name' => 'shipping_address_2',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_shipping_city'] = array (
				'type' => 'meta',
				'name' => 'shipping_city',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_shipping_state'] = array (
				'type' => 'meta',
				'name' => 'shipping_state',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_shipping_postcode'] = array (
				'type' => 'meta',
				'name' => 'shipping_postcode',
				'function' => '',
				'join_type' => 'LEFT' 
		);
		$reportData ['_shipping_country'] = array (
				'type' => 'meta',
				'name' => 'shipping_country',
				'function' => '',
				'join_type' => 'LEFT' 
		);
	}
	if (in_array ( 'customer_order_note', $_POST ['fields'] )) {
		$reportData ['post_excerpt'] = array (
				'type' => 'post_data',
				'function' => '',
				'name' => 'customer_order_note' 
		);
	}
	if (in_array ( 'line_total_with_tax', $_POST ['fields'] ) || in_array ( 'line_tax', $_POST ['fields'] )) {
		$reportData ['_line_tax'] = array (
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => '',
				'name' => 'line_tax',
				'join_type' => 'LEFT' 
		);
	}
	
	// Shipping line item fields
	if (! empty ( $_POST ['include_shipping'] )) {
		$reportData ['order_item_type'] = array (
				'type' => 'order_item',
				'function' => '',
				'name' => 'order_item_type' 
		);
		if (in_array ( 'product_id', $_POST ['fields'] ) || in_array ( 'product_name', $_POST ['fields'] )) {
			$reportData ['method_id'] = array (
					'type' => 'order_item_meta',
					// 'order_item_type' => 'shipping',
					'function' => '',
					'name' => 'shipping_method_id',
					'join_type' => 'LEFT' 
			);
			
			if (in_array ( 'product_name', $_POST ['fields'] )) {
				// We need this to resolve the shipping method names later on
				$woocommerce->shipping->load_shipping_methods ();
				$shippingMethods = $woocommerce->shipping->get_shipping_methods ();
			}
		}
		if (in_array ( 'line_subtotal', $_POST ['fields'] ) || in_array ( 'line_total', $_POST ['fields'] ) || in_array ( 'line_total_with_tax', $_POST ['fields'] )) {
			$reportData ['cost'] = array (
					'type' => 'order_item_meta',
					// 'order_item_type' => 'shipping',
					'function' => '',
					'name' => 'shipping_cost',
					'join_type' => 'LEFT' 
			);
		}
		if (in_array ( 'line_total_with_tax', $_POST ['fields'] ) || in_array ( 'line_tax', $_POST ['fields'] )) {
			$reportData ['taxes'] = array (
					'type' => 'order_item_meta',
					// 'order_item_type' => 'shipping',
					'function' => '',
					'name' => 'shipping_taxes',
					'join_type' => 'LEFT' 
			);
		}
		
		$orderItemTypes = array (
				'line_item',
				'shipping' 
		);
	}
	
	$customFields = hm_xoiwcp_get_custom_fields ();
	foreach ( array_intersect ( $_POST ['fields'], $customFields ) as $customField ) {
		$fieldType = substr ( $customField, 2, strpos ( $customField, '__', 2 ) - 2 );
		if ($fieldType == 'shop_order' || $fieldType == 'order_item') {
			$fieldName = substr ( $customField, strpos ( $customField, '__', 2 ) + 2 );
			if (strpos ( $fieldName, ' ' ) === false && strpos ( $fieldName, '-' ) === false) {
				$reportData [$fieldName] = array (
						'type' => ($fieldType == 'shop_order' ? 'meta' : 'order_item_meta'),
						'name' => $customField,
						'function' => '',
						'join_type' => 'LEFT' 
				);
			}
		}
	}
	
	if ($_POST ['products'] == 'ids') {
		$product_ids = array ();
		foreach ( explode ( ',', $_POST ['product_ids'] ) as $productId ) {
			$productId = trim ( $productId );
			if (is_numeric ( $productId ))
				$product_ids [] = $productId;
		}
	}
	if ($_POST ['products'] == 'cats' || ! empty ( $_POST ['product_tag_filter_on'] ) || ! empty ( $_POST ['product_meta_filter_on'] )) {
		$params = array (
				'post_type' => 'product',
				'nopaging' => true,
				'fields' => 'ids',
				'ignore_sticky_posts' => true,
				'tax_query' => array () 
		);
		
		if (isset ( $product_ids )) {
			$params ['post__in'] = $product_ids;
		}
		if ($_POST ['products'] == 'cats') {
			$cats = array ();
			foreach ( $_POST ['product_cats'] as $cat )
				if (is_numeric ( $cat ))
					$cats [] = $cat;
			$params ['tax_query'] [] = array (
					'taxonomy' => 'product_cat',
					'terms' => $cats 
			);
		}
		if (! empty ( $_POST ['product_tag_filter_on'] )) {
			$tags = array ();
			foreach ( explode ( ',', $_POST ['product_tag_filter'] ) as $tag ) {
				$tag = trim ( $tag );
				if (! empty ( $tag ))
					$tags [] = $tag;
			}
			$params ['tax_query'] [] = array (
					'taxonomy' => 'product_tag',
					'field' => 'name',
					'terms' => $tags 
			);
		}
		
		if (count ( $params ['tax_query'] ) > 1) {
			$params ['tax_query'] ['relation'] = 'AND';
		}
		
		// Product meta field filtering
		if (! empty ( $_POST ['product_meta_filter_on'] )) {
			$customFieldsByType = hm_xoiwcp_get_custom_fields ( true );
			if (in_array ( $_POST ['product_meta_filter_key'], $customFieldsByType ['Product'] ) && in_array ( $_POST ['product_meta_filter_op'], array (
					'=',
					'!=',
					'<',
					'<=',
					'>',
					'>=',
					'BETWEEN' 
			) )) {
				$params ['meta_query'] = array (
						array (
								'key' => $_POST ['product_meta_filter_key'],
								'compare' => $_POST ['product_meta_filter_op'],
								'value' => ($_POST ['product_meta_filter_op'] == 'BETWEEN' ? array (
										$_POST ['product_meta_filter_value'],
										$_POST ['product_meta_filter_value_2'] 
								) : $_POST ['product_meta_filter_value']) 
						) 
				);
				if (is_numeric ( $_POST ['product_meta_filter_value'] ) && ($_POST ['product_meta_filter_op'] != 'BETWEEN' || is_numeric ( $_POST ['product_meta_filter_value_2'] ))) {
					$params ['meta_query'] [0] ['type'] = 'NUMERIC';
				}
			}
		}
		
		$product_ids = get_posts ( $params );
	}
	
	// Customer filtering
	if ((! empty ( $_POST ['customer_role'] ) && $_POST ['customer_role'] != - 1) || (! empty ( $_POST ['order_meta_filter_on'] ) && ! empty ( $_POST ['order_meta_filter_key'] ) && $_POST ['order_meta_filter_key'] [0] == 'C')) {
		$getUsersArgs = array (
				'fields' => 'ID' 
		);
		
		// Customer User order field filter
		if (! empty ( $_POST ['order_meta_filter_on'] ) && ! empty ( $_POST ['order_meta_filter_key'] ) && $_POST ['order_meta_filter_key'] [0] == 'C' && array_key_exists ( $_POST ['order_meta_filter_key'], hm_xoiwcp_get_order_filter_fields () ) && in_array ( $_POST ['order_meta_filter_op'], array (
				'=',
				'!=',
				'<',
				'<=',
				'>',
				'>=',
				'BETWEEN' 
		) )) {
			
			// If the customer role filter is set to Guest Customers AND a customer user meta field filter is enabled, $customerIds is empty
			if (! empty ( $_POST ['customer_role'] ) && $_POST ['customer_role'] == - 1) {
				$customerIds = array ();
			} else {
				$getUsersArgs ['meta_query'] = array (
						array (
								'key' => esc_sql ( substr ( $_POST ['order_meta_filter_key'], 1 ) ),
								'compare' => $_POST ['order_meta_filter_op'],
								'value' => ($_POST ['order_meta_filter_op'] == 'BETWEEN' ? array (
										$_POST ['order_meta_filter_value'],
										$_POST ['order_meta_filter_value_2'] 
								) : $_POST ['order_meta_filter_value']) 
						) 
				);
				if (is_numeric ( $_POST ['order_meta_filter_value'] ) && ($_POST ['order_meta_filter_op'] != 'BETWEEN' || is_numeric ( $_POST ['order_meta_filter_value_2'] ))) {
					$getUsersArgs ['meta_query'] [0] ['type'] = 'NUMERIC';
				}
			}
		}
		
		if (! isset ( $customerIds )) {
			// Customer role
			if (! empty ( $_POST ['customer_role'] )) {
				$getUsersArgs ['role'] = esc_sql ( $_POST ['customer_role'] );
			}
			
			$customerIds = get_users ( $getUsersArgs );
		}
	}
	
	if ((! isset ( $product_ids ) || ! empty ( $product_ids )) && (! isset ( $customerIds ) || ! empty ( $customerIds ))) { // Do not run the report if product_ids or customerIds is set and empty
	                                                     
		// Get WHERE conditions
		$where_meta = array ();
		if (isset ( $product_ids )) {
			$where_meta [] = array (
					'type' => 'order_item_meta',
					'meta_key' => '_product_id',
					'operator' => 'in',
					'meta_value' => $product_ids 
			);
		}
		if (! empty ( $_POST ['customer_role'] ) && $_POST ['customer_role'] == - 1) {
			$where_meta [] = array (
					'type' => 'order_meta',
					'meta_key' => '_customer_user',
					'meta_value' => 0 
			);
		} else if (isset ( $customerIds )) {
			if (count ( $customerIds ) <= 1000) {
				$where_meta [] = array (
						'type' => 'order_meta',
						'meta_key' => '_customer_user',
						'operator' => 'IN',
						'meta_value' => $customerIds 
				);
			} else {
				// We need to filter customer IDs *after* the report has run
				$customerIdPostFilter = true;
			}
		}
		
		if (! empty ( $_POST ['order_meta_filter_on'] ) && ! empty ( $_POST ['order_meta_filter_key'] ) && $_POST ['order_meta_filter_key'] [0] == 'O' && array_key_exists ( $_POST ['order_meta_filter_key'], hm_xoiwcp_get_order_filter_fields () ) && in_array ( $_POST ['order_meta_filter_op'], array (
				'=',
				'!=',
				'<',
				'<=',
				'>',
				'>=',
				'BETWEEN' 
		) )) {
			
			// Escape meta value(s) and force typecast of numeric value(s)
			$metaValue = esc_sql ( $_POST ['order_meta_filter_value'] );
			if (is_numeric ( $_POST ['order_meta_filter_value'] )) {
				$metaValue .= '\'*\'1';
			}
			if ($_POST ['order_meta_filter_op'] == 'BETWEEN') {
				$metaValue .= '\' AND \'' . esc_sql ( $_POST ['order_meta_filter_value_2'] );
				if (is_numeric ( $_POST ['order_meta_filter_value_2'] )) {
					$metaValue .= '\'*\'1';
				}
			}
			
			$where_meta [] = array (
					'type' => 'order_meta',
					'meta_key' => esc_sql ( substr ( $_POST ['order_meta_filter_key'], 1 ) ),
					'operator' => $_POST ['order_meta_filter_op'],
					'meta_value' => $metaValue 
			);
		}
		
		// Zero-amount item filtering
		if (! empty ( $_POST ['exclude_free'] )) {
			$where_meta [] = array (
					'type' => 'order_item_meta',
					'meta_key' => (empty ( $_POST ['exclude_free_after_discount'] ) ? '_line_subtotal' : '_line_total'),
					'operator' => '!=',
					'meta_value' => 0 
			);
		}
		
		// Add the customer user field, if necessary
		if (empty ( $customerIdPostFilter )) {
			foreach ( $_POST ['fields'] as $field ) {
				if (substr ( $field, 0, 17 ) == '__customer_user__') {
					$hasCustomerUserField = true;
					break;
				}
			}
		}
		if (! empty ( $customerIdPostFilter ) || ! empty ( $hasCustomerUserField )) {
			if (isset ( $reportData ['_customer_user'] )) {
				$customerIdField = $reportData ['_customer_user'] ['name'];
			} else {
				$reportData ['_customer_user'] = array (
						'type' => 'meta',
						'function' => '',
						'name' => '_customer_user' 
				);
				$customerIdField = '_customer_user';
			}
		}
		
		// Custom date range time fields
		$where = array ();
		if ($_POST ['report_time'] == 'custom') {
			$where [] = array (
					'key' => 'post_date',
					'operator' => '>=',
					'value' => date ( 'Y-m-d H:i:s', $start_date ) 
			);
			$where [] = array (
					'key' => 'post_date',
					'operator' => '<',
					'value' => date ( 'Y-m-d H:i:s', $end_date ) 
			);
		}
		
		// Filter order statuses
		add_filter ( 'woocommerce_reports_order_statuses', 'hm_xoiwcp_report_order_statuses', 9999 );
		
		// Avoid max join size error
		$wpdb->query ( 'SET SQL_BIG_SELECTS=1' );
		
		// Based on woocoommerce/includes/admin/reports/class-wc-report-sales-by-product.php
		$sold_products = $wc_report->get_order_report_data ( array (
				'data' => $reportData,
				'query_type' => 'get_results',
				'order_by' => $orderby,
				'filter_range' => ($_POST ['report_time'] != 'all' && $_POST ['report_time'] != 'custom'),
				'order_types' => wc_get_order_types ( 'order_count' ),
				// 'order_status' => $orderStatuses,
				'where' => $where,
				'where_meta' => $where_meta 
		) );
		
		// Remove report order statuses filter
		remove_filter ( 'woocommerce_reports_order_statuses', 'hm_xoiwcp_report_order_statuses', 9999 );
		
		$addonFields = hm_xoiwcp_get_addon_fields ();
		
		if (! empty ( $_POST ['include_totals'] )) {
			$totals = array_combine ( $_POST ['total_fields'], array_fill ( 0, count ( $_POST ['total_fields'] ), 0 ) );
		}
		
		$orderShippingCache = array ();
		if (! empty ( $_POST ['order_shipping_total_once'] )) {
			$orderShippingTotalSkipIds = array ();
			$orderShippingTotalSkipFields = array (
					'order_shipping_cost',
					'order_shipping_cost_with_tax',
					'order_shipping_tax' 
			);
		}
		$count	=	0;
		
		$sold_products = staxo_getRefunds($sold_products, $start_date , $end_date);
		
		$max	=	count( $sold_products);
		
		/*
		 * We need to get the onest that have been refunded during this period. 
		 * 
		 * So firstly go 
		 * 
		 * 
		 */
		
		foreach ( $sold_products as $product ) {
			
			
			$count++;
			

		
			
			if($product->line_subtotal == 0) { // eXCLUDE THEM ALL FOR NOW 
				continue;
			}
			// Check order item type
			if (isset ( $orderItemTypes )) {
				if (! in_array ( $product->order_item_type, $orderItemTypes )) {
					continue;
				}
				$isShipping = ($product->order_item_type == 'shipping');
			} else {
				// If $orderItemTypes is not set, then we're not including shipping rows in this report
				$isShipping = false;
			}
			
			// Apply customer ID filter, if necessary
			if (! empty ( $customerIdPostFilter ) && ! in_array ( $product->$customerIdField, $customerIds )) {
				continue;
			}
			
			// Calculate shipping line tax
			if ($isShipping && isset ( $product->shipping_taxes )) {
				$product->line_tax = 0;
				$taxArray = @unserialize ( $product->shipping_taxes );
				if (! empty ( $taxArray )) {
					foreach ( $taxArray as $taxItem ) {
						$product->line_tax += $taxItem;
					}
				}
			}

			/*
			 * MOD BY ERILD!!! TALK TO ME ABOUT IT I WILL REMEMBER FOR LIFE
			 * 
			 * Modification to grab the post_parent. 
			 * 
			 * This is used to reference the original order.
			 * 
			 */
			$row = array ();
			$hasRefundRef	=	false;
			
			
			if ($refundFlag) {	
					
					$sql	=	<<<heredoc
SELECT ID, post_parent
FROM wp_posts
WHERE ID = '{$product->order_id}'
heredoc;
					$refundid = $wpdb->get_row ($sql);
					$orderID	=	get_post_meta ( $product->order_id, "_order_number", true ); // grab the order number from the sequential numberign that is in place
					if(!$orderID){
						$orderID = $product->order_id;
					}
				
					if($refundid->post_parent){ 
						$orderID	=	$product->order_id; // Assign  it back. 
						$product->order_id	=	$refundid->post_parent; 	
						$postparentID	=	$refundid->post_parent;
						$hasRefundRef	=	true;
					}
					$render	=true;
					if($orderID == $masterID){
						$render	=	false;
					}else{
						$masterID = $orderID;
						if(isset($_REQUEST ['reportType']) && $_REQUEST ['reportType']	==	"invoice"){
							$dest->putRow ( $storedArray ); // Render the stored array. 
							unset($storedArray);// Clear the stored ARRAY
						}
					}
					$row [] =	  $orderID;
					$storedArray['creditNote'] = 	$orderID;
			}
			
			foreach ( $_POST ['fields'] as $field ) {
				if (isset ( $addonFields [$field] ['cb'] )) {
					$row [] = call_user_func ( $addonFields [$field] ['cb'], $product, null );
				} else {
					switch ($field) {
						case 'product_id' :
							$row [] = ($isShipping ? $product->shipping_method_id : $product->product_id);
							$storedArray[$field] =($isShipping ? $product->shipping_method_id : $product->product_id);
							break;
						case 'order_id' :
							$row [] = $product->order_id;
							$storedArray[$field] = $product->order_id;
 							
							break;
						case 'order_status' :
							$row [] = wc_get_order_status_name ( $product->order_status );
							$storedArray[$field] = wc_get_order_status_name ( $product->order_status );
							break;
						case 'order_date' :
							$row [] = $product->order_date;
							$storedArray[$field] =$product->order_date;
							break;
						case 'product_sku' :
							$row [] = get_post_meta ( $product->product_id, '_sku', true );
							$storedArray[$field] =get_post_meta ( $product->product_id, '_sku', true );
							break;
						case 'product_name' :
							if (! $isShipping) {
								$row [] = html_entity_decode ( get_the_title ( $product->product_id ), ENT_QUOTES | ENT_HTML401 );
								$storedArray[$field] =	html_entity_decode ( get_the_title ( $product->product_id ), ENT_QUOTES | ENT_HTML401 );
							} else if (! empty ( $shippingMethods [$product->shipping_method_id]->method_title )) {
								$row [] = 'Shipping - ' . $shippingMethods [$product->shipping_method_id]->method_title;
								$storedArray[$field] ='Shipping - ' . $shippingMethods [$product->shipping_method_id]->method_title;
							} else if (empty ( $product->shipping_method_id )) {
								$row [] = 'Shipping';
								$storedArray[$field] =$product->shipping_method_id;
							} else {
								$row [] = 'Shipping - ' . $product->shipping_method_id;
								$storedArray[$field] ='Shipping - ' . $product->shipping_method_id;
							}
							
							break;
						case 'product_categories' :
							$terms = get_the_terms ( $product->product_id, 'product_cat' );
							if (empty ( $terms )) {
								$row [] = '';
							} else {
								$categories = array ();
								foreach ( $terms as $term )
									$categories [] = $term->name;
								$row [] = implode ( ', ', $categories );
							}
							break;
						case 'billing_name' :
							$row [] = $product->billing_first_name . ' ' . $product->billing_last_name;
							$storedArray[$field]	= $product->billing_first_name . ' ' . $product->billing_last_name;
							break;
						case 'billing_phone' :
							$row [] = $product->billing_phone;
							break;
						case 'billing_email' :
							$row [] = $product->billing_email;
							break;
						case 'billing_address' :
							$addressComponents = array ();
							if (! empty ( $product->billing_address_1 ))
								$addressComponents [] = $product->billing_address_1;
							if (! empty ( $product->billing_address_2 ))
								$addressComponents [] = $product->billing_address_2;
							if (! empty ( $product->billing_city ))
								$addressComponents [] = $product->billing_city;
							if (! empty ( $product->billing_state ))
								$addressComponents [] = $product->billing_state;
							if (! empty ( $product->billing_postcode ))
								$addressComponents [] = $product->billing_postcode;
							if (! empty ( $product->billing_country ))
								$addressComponents [] = $product->billing_country;
							$row [] = implode ( ', ', $addressComponents );
							break;
						case 'shipping_name' :
							$row [] = $product->shipping_first_name . ' ' . $product->shipping_last_name;
							
							break;
						case 'shipping_phone' :
							$row [] = $product->shipping_phone;
							break;
						case 'shipping_email' :
							$row [] = $product->shipping_email;
							break;
						case 'shipping_address' :
							$addressComponents = array ();
							if (! empty ( $product->shipping_address_1 ))
								$addressComponents [] = $product->shipping_address_1;
							if (! empty ( $product->shipping_address_2 ))
								$addressComponents [] = $product->shipping_address_2;
							if (! empty ( $product->shipping_city ))
								$addressComponents [] = $product->shipping_city;
							if (! empty ( $product->shipping_state ))
								$addressComponents [] = $product->shipping_state;
							if (! empty ( $product->shipping_postcode ))
								$addressComponents [] = $product->shipping_postcode;
							if (! empty ( $product->shipping_country ))
								$addressComponents [] = $product->shipping_country;
							$row [] = implode ( ', ', $addressComponents );
							break;
					
						case 'quantity' :
							if ($product->order_status == 'wc-refunded'  ) {
								$row [] = $product->quantity * - 1;
								
								$test	=	 $product->quantity * - 1;
								$storedArray[$field] =	$test + $storedArray[$field]; // Amended by Del
								$product->quantity =	$product->quantity * - 1;
								
								
							} else {
								if(!$product->quantity ){ // Catch for random 0 quantity should always be 1
									$product->quantity	=	1; 
									if($product->line_subtotal < 0){ // and if it a refund then it will be negative. 
										$product->quantity	=	-1;
									}
								}
								if( $hasRefundRef &&  $product->quantity > 0){//should be negative so set it to negative 
									$product->quantity = $product->quantity * -1;
								}
								
								$storedArray[$field] =	  $storedArray[$field]  + $product->quantity; // Amended by Del
								$row [] = $product->quantity;
							}

							break;
							
						case 'line_subtotal' :
							$fieldPrice = get_post_meta ( $product->product_id, "_price", true );
							if($fieldPrice){
								$amount	=	$product->quantity * $fieldPrice;
							
								if($amount != $product->line_subtotal){
									$product->line_subtotal = $amount;
								}
							}
							if ($product->order_status == 'wc-refunded' || $hasRefundRef) {
								if($product->line_subtotal > 0){
									$row [] 			 = $product->line_subtotal * - 1;
									$test				 = $product->line_subtotal * - 1;
								}else{
									$row [] 			 = $product->line_subtotal;
									$test				 = $product->line_subtotal;
								}								
						
								$storedArray[$field] = $storedArray[$field]  + $test; // Amended by Del
							}else if ($product->quantity < 0 && $product->line_subtotal >0) {
								$row [] 			=	$product->line_subtotal * - 1;
								$test				=	$product->line_subtotal * - 1;
								$storedArray[$field] = $storedArray[$field]  + $test; // Amended by Del
							}else {
								$storedArray[$field]	=	$storedArray[$field]  + $product->line_subtotal; // Amended by Del  
								$row [] = $product->line_subtotal;
							}
							break;
		
						case 'line_tax' :
							$row [] = $product->line_tax;
							$storedArray[$field]= $product->line_tax;
							break;
						case 'line_total_with_tax' :
							$row [] = ($isShipping ? $product->shipping_cost : $product->line_total) + $product->line_tax;
							$storedArray[$field]= ($isShipping ? $product->shipping_cost : $product->line_total) + $product->line_tax;
							break;
						case 'variation_id' :
							$row [] = (empty ( $product->variation_id ) ? '' : $product->variation_id);
	
							break;
						case 'variation_sku' :
							$row [] = (empty ( $product->variation_id ) ? '' : get_post_meta ( $product->variation_id, '_sku', true ));
							break;
						case 'variation_attributes' :
							if (empty ( $product->variation_id )) {
								$row [] = '';
							} else {
								$attr = wc_get_product_variation_attributes ( $product->variation_id );
								foreach ( $attr as $i => $v ) {
									if ($v === '')
										unset ( $attr [$i] );
								}
								$row [] = urldecode ( implode ( ', ', $attr ) );
							}
							break;
						case 'item_sku' :
							$row [] = (empty ( $product->variation_id ) ? get_post_meta ( $product->product_id, '_sku', true ) : get_post_meta ( $product->variation_id, '_sku', true ));
							break;
						case 'customer_order_note' :
							$row [] = $product->customer_order_note;
							break;
						case 'order_note_most_recent' :
							// Copied from woocommerce/includes/admin/meta-boxes/class-wc-meta-box-order-notes.php and modified
							remove_filter ( 'comments_clauses', array (
									'WC_Comments',
									'exclude_order_comments' 
							), 10, 1 );
							$note = get_comments ( array (
									'post_id' => $product->order_id,
									'orderby' => 'comment_date',
									'order' => 'DESC',
									'approve' => 'approve',
									'type' => 'order_note',
									'number' => 1 
							) );
							add_filter ( 'comments_clauses', array (
									'WC_Comments',
									'exclude_order_comments' 
							), 10, 1 );
							$row [] = (empty ( $note [0]->comment_content ) ? '' : $note [0]->comment_content);
							break;
						case 'order_shipping_methods' :
						case 'order_shipping_cost' :
						case 'order_shipping_cost_with_tax' :
						case 'order_shipping_tax' :
							if ($isShipping) {
								$row [] = '';
							} else {
								if (! isset ( $orderShippingCache [$product->order_id] )) {
									$orderShippingCache [$product->order_id] = hm_xoiwcp_get_order_shipping_fields_values ( $product->order_id, $_POST ['fields'] );
								}
								
								$value	=	($orderShippingCache [$product->order_id] ? $orderShippingCache [$product->order_id] [$field] : 'Error');
								if('order_shipping_cost'== $field	&&  $product->order_status == 'wc-refunded'){
									$value	=	$value * -1;
								}
								$storedArray[$field] = $value;
								$row [] = $value;
							}
							break;
						default :
							if (in_array ( $field, $customFields )) {
								$fieldType = substr ( $field, 2, strpos ( $field, '__', 2 ) - 2 );
								$fieldName = substr ( $field, strpos ( $field, '__', 2 ) + 2 );
								switch ($fieldType) {
									case 'shop_order' :
										$fieldValue = (isset ( $product->$field ) ? $product->$field : get_post_meta ( $product->order_id, $fieldName, true ));
										if($fieldName	== "_order_number" && !$fieldValue){
											$fieldValue= $product->order_id;
										}
										if($fieldName	== "_order_total" && $product->order_status == 'wc-refunded'){
											$fieldValue	=	-1 *	$fieldValue;
										}
										if($fieldName	== "_cart_discount"){
											$fieldValue	=	get_post_meta ( $product->order_id, "_cart_discount", true );
											if($hasRefundRef &&  $product->quantity < 0){
												$fieldValue = -1 * $fieldValue; 
											}
										}		
										$storedArray[$field] =  (is_array ( $fieldValue ) ? hm_xoiwcp_array_string ( $fieldValue ) : $fieldValue);
										break;
									case 'order_item' :
										$fieldValue = (isset ( $product->$field ) ? $product->$field : wc_get_order_item_meta ( $product->order_item_id, $fieldName, true ));
										$storedArray[$field] =  (is_array ( $fieldValue ) ? hm_xoiwcp_array_string ( $fieldValue ) : $fieldValue);
										break;
									case 'product' :
										if($fieldName	== '_price'){
											$fieldValue = get_post_meta ( $product->product_id, $fieldName, true );
											$subTotal		=	$product->line_subtotal;
											$priceOfProduct	=	$product->line_subtotal / $product->quantity; 	 
											if($fieldValue != $priceOfProduct){
												$fieldValue 	=	 $priceOfProduct;
											}
										}
										if($fieldName	== '_price' &&  $_REQUEST ['reportType'] == "invoice"){
											$fieldValue = $product->quantity * $fieldValue; 
											$storedArray[$field]	=	$storedArray[$field]  + 	$fieldValue ;
										}
										break;
										
									case 'product_variation' :
										$fieldValue = get_post_meta ( $product->variation_id, $fieldName, true );
										$storedArray[$field] =  (is_array ( $fieldValue ) ? hm_xoiwcp_array_string ( $fieldValue ) : $fieldValue);
										break;
									case 'customer_user' :
										$fieldValue = get_user_meta ( $product->$customerIdField, $fieldName, true );
										$storedArray[$field] =  (is_array ( $fieldValue ) ? hm_xoiwcp_array_string ( $fieldValue ) : $fieldValue);
										break;
									default :
										$fieldValue = ''; // No field type match
								}
								
								$fieldValue = maybe_unserialize ( $fieldValue );
								
									//$storedArray[$field] =  (is_array ( $fieldValue ) ? hm_xoiwcp_array_string ( $fieldValue ) : $fieldValue);
								
								$row [] = (is_array ( $fieldValue ) ? hm_xoiwcp_array_string ( $fieldValue ) : $fieldValue);
							}
					}
					
				}
				if (isset ( $totals [$field] ) && (! isset ( $orderShippingTotalSkipIds ) || ! in_array ( $field, $orderShippingTotalSkipFields ) || ! isset ( $orderShippingTotalSkipIds [$product->order_id] ))) {
					$totals [$field] += end ( $row );
				}

			}
			
			$totalProductArray[$product->product_id][0] 	=  	get_post_meta ( $product->product_id, '_sku', true );
			$totalProductArray[$product->product_id][1]		= 	html_entity_decode ( get_the_title ( $product->product_id ), ENT_QUOTES | ENT_HTML401 );
			$totalProductArray[$product->product_id][2]		=  	$totalProductArray[$product->product_id][2] + $product->quantity;
			
			if (isset ( $orderShippingTotalSkipIds )) {
				// Skip future lines in this order for order shipping totals
				$orderShippingTotalSkipIds [$product->order_id] = true;
			}
			
			if(isset($_REQUEST['reportType']) && $_REQUEST ['reportType'] == "customer" ){
				$dest->putRow ( $row );
			}
		}
		
		if(isset($_REQUEST['reportType']) && $_REQUEST ['reportType'] == "summary" ){
			foreach($totalProductArray as $item ){
				$dest->putRow($item);
			}
		}

		
		// Output the totals row, if applicable
		if (! empty ( $_POST ['include_totals'] )) {
			$row = array ();
			foreach ( $_POST ['fields'] as $fieldId ) {
				if ($fieldId == 'product_name') {
					$row [] = 'TOTAL';
				} else {
					$row [] = (isset ( $totals [$fieldId] ) ? $totals [$fieldId] : '');
				}
			}
			$dest->putRow ( $row, false, true );
		}
		
		if($count==$max){ // Last item 
			if(isset($_REQUEST ['reportType']) && $_REQUEST ['reportType']	==	"invoice"){
			$dest->putRow ( $storedArray ); // Render the stored array.
			}
		}
	}
}
function hm_xoiwcp_array_string($arr) {
	// Determine whether the array is indexed or associative
	$isIndexedArray = true;
	for($i = 0; $i < count ( $arr ); ++ $i) {
		if (! isset ( $arr [$i] )) {
			$isIndexedArray = false;
			break;
		}
	}
	
	// Process associative array
	if (! $isIndexedArray) {
		foreach ( $arr as $key => $value ) {
			$arr [$key] = $key . ': ' . (is_array ( $value ) ? '(' . hm_xoiwcp_array_string ( $value ) . ')' : $value);
		}
	}
	
	return implode ( ', ', $arr );
}
function hm_xoiwcp_get_order_shipping_fields_values($orderId, $fields) {
	$order = wc_get_order ( $orderId );
	if (empty ( $order )) {
		return false;
	}
	$shippingItems = $order->get_shipping_methods ();
	if ($shippingItems === false) {
		return false;
	}
	
	// die(print_r($order));
	
	$shippingFieldsValues = array ();
	if (in_array ( 'order_shipping_methods', $fields )) {
		$shippingFieldsValues ['order_shipping_methods'] = '';
		foreach ( $shippingItems as $shippingItem ) {
			$shippingFieldsValues ['order_shipping_methods'] = (empty ( $shippingFieldsValues ['order_shipping_methods'] ) ? '' : ', ') . $shippingItem ['name'];
		}
	}
	if (in_array ( 'order_shipping_cost', $fields ) || in_array ( 'order_shipping_cost_with_tax', $fields )) {
		$shippingFieldsValues ['order_shipping_cost'] = 0;
		foreach ( $shippingItems as $shippingItem ) {
			$shippingFieldsValues ['order_shipping_cost'] += (empty ( $shippingItem ['item_meta'] ['cost'] [0] ) ? 0 : $shippingItem ['item_meta'] ['cost'] [0]);
		}
	}
	
	if (in_array ( 'order_shipping_tax', $fields ) || in_array ( 'order_shipping_cost_with_tax', $fields )) {
		$shippingFieldsValues ['order_shipping_tax'] = 0;
		foreach ( $shippingItems as $shippingItem ) {
			if (isset ( $shippingItem ['item_meta'] ['taxes'] [0] )) {
				$taxArray = @unserialize ( $shippingItem ['item_meta'] ['taxes'] [0] );
				if (! empty ( $taxArray )) {
					foreach ( $taxArray as $taxItem ) {
						$shippingFieldsValues ['order_shipping_tax'] += $taxItem;
					}
				}
			}
		}
		$shippingFieldsValues ['order_shipping_cost_with_tax'] = $shippingFieldsValues ['order_shipping_cost'] + $shippingFieldsValues ['order_shipping_tax'];
	}
	
	return $shippingFieldsValues;
}
function hm_xoiwcp_get_custom_fields($byType = false) {
	global $hm_xoiwcp_custom_fields, $hm_xoiwcp_custom_fields_by_type, $wpdb;
	
	$typeNames = array (
			'shop_order' => 'Order',
			'order_item' => 'Order Line Item',
			'customer_user' => 'Customer User',
			'product' => 'Product',
			'product_variation' => 'Product Variation' 
	);
	if (! isset ( $hm_xoiwcp_custom_fields )) {
		$hm_xoiwcp_custom_fields = array ();
		$hm_xoiwcp_custom_fields_by_type = array ();
		
		foreach ( $typeNames as $typeName )
			$hm_xoiwcp_custom_fields_by_type [$typeName] = array ();
		
		$fields = $wpdb->get_results ( 'SELECT DISTINCT post_type, meta_key FROM ' . $wpdb->prefix . 'postmeta JOIN ' . $wpdb->prefix . 'posts ON (post_id=ID) WHERE post_type IN("product", "product_variation", "shop_order")
										UNION SELECT DISTINCT "order_item" AS post_type, meta_key FROM ' . $wpdb->prefix . 'woocommerce_order_itemmeta
										UNION SELECT DISTINCT "customer_user" AS post_type, meta_key FROM ' . $wpdb->prefix . 'usermeta
										ORDER BY meta_key ASC' );
		foreach ( $fields as $field ) {
			$fieldId = '__' . $field->post_type . '__' . $field->meta_key;
			$hm_xoiwcp_custom_fields [] = $fieldId;
			$hm_xoiwcp_custom_fields_by_type [$typeNames [$field->post_type]] [$fieldId] = $field->meta_key;
		}
	}
	
	return $byType ? $hm_xoiwcp_custom_fields_by_type : $hm_xoiwcp_custom_fields;
}

/*
 * Get fields added by other plugins.
 * Plugins hooked to "hm_xoiwcp_addon_fields" must add their fields to the array in the following format:
 * my_addon_field_id => array(
 * 'label' => 'My Addon Field',
 * 'cb' => my_callback_function
 * );
 * where "my_callback_function" takes the following arguments:
 * $product: the product object returned by $wc_report->get_order_report_data()
 * $type: null for regular products (currently not used)
 * and returns the field value to include in the report for the given product.
 */
function hm_xoiwcp_get_addon_fields() {
	global $hm_xoiwcp_addon_fields;
	if (! isset ( $hm_xoiwcp_addon_fields )) {
		$hm_xoiwcp_addon_fields = apply_filters ( 'hm_xoiwcp_addon_fields', array () );
	}
	return $hm_xoiwcp_addon_fields;
}

add_action ( 'admin_enqueue_scripts', 'hm_xoiwcp_admin_enqueue_scripts' );
function hm_xoiwcp_admin_enqueue_scripts() {
	wp_enqueue_style ( 'hm_xoiwcp_admin_style', plugins_url ( 'css/export-order-items.css', __FILE__ ) );
	wp_enqueue_style ( 'pikaday', plugins_url ( 'css/pikaday.css', __FILE__ ) );
	wp_enqueue_script ( 'moment', plugins_url ( 'js/moment.min.js', __FILE__ ) );
	wp_enqueue_script ( 'pikaday', plugins_url ( 'js/pikaday.js', __FILE__ ) );
	
	wp_enqueue_script ( 'jquery-ui-sortable' );
}

// Schedulable email report hook
add_filter ( 'pp_wc_get_schedulable_email_reports', 'hm_xoiwcp_add_schedulable_email_reports' );
function hm_xoiwcp_add_schedulable_email_reports($reports) {
	$myReports = array (
			'last' => 'Last used settings' 
	);
	$savedReportSettings = get_option ( 'hm_xoiwcp_report_settings' );
	$updated = false;
	foreach ( $savedReportSettings as $i => $settings ) {
		if ($i == 0)
			continue;
		if (empty ( $settings ['key'] )) {
			$chars = 'abcdefghijklmnopqrstuvwxyz123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$numChars = strlen ( $chars );
			while ( true ) {
				$key = '';
				for($j = 0; $j < 32; ++ $j)
					$key .= $chars [rand ( 0, $numChars - 1 )];
				$unique = true;
				foreach ( $savedReportSettings as $settings2 )
					if (isset ( $settings2 ['key'] ) && $settings2 ['key'] == $key)
						$unique = false;
				if ($unique)
					break;
			}
			$savedReportSettings [$i] ['key'] = $key;
			$updated = true;
		}
		$myReports [$savedReportSettings [$i] ['key']] = $settings ['preset_name'];
	}
	
	if ($updated)
		update_option ( 'hm_xoiwcp_report_settings', $savedReportSettings );
	
	$reports ['hm_xoiwcp'] = array (
			'name' => 'Export Order Items Pro',
			'callback' => 'hm_xoiwcp_run_scheduled_report',
			'reports' => $myReports 
	);
	return $reports;
}
function hm_xoiwcp_run_scheduled_report($reportId, $start, $end, $args = array(), $output = false) {
	$_POST ['format']	=	'csv';
	/*
	$savedReportSettings = get_option ( 'hm_xoiwcp_report_settings' );
	if (! isset ( $savedReportSettings [0] ))
	//	return false;
	
	if ($reportId == 'last') {
		$presetIndex = 0;
	} else {
		foreach ( $savedReportSettings as $i => $settings ) {
			if (isset ( $settings ['key'] ) && $settings ['key'] == $reportId) {
				$presetIndex = $i;
				break;
			}
		}
	}
	if (! isset ( $presetIndex ))
		return false;
	*/	
		// Add one day to end since we're setting the time to midnight
	$end += 86400;

	$prevPost = $_POST;
	//$_POST = $savedReportSettings [$presetIndex];
	$_POST ['report_time'] = 'custom';
	$_POST ['report_start'] = date ( 'Y-m-d', $start );
	$_POST ['report_start_time'] = '12:00:00 AM';
	$_POST ['report_end'] 	= date ( 'Y-m-d', $end );
	$_POST ['report_end_time'] = '12:00:00 AM';

	staxo_setRequest();
	$filepath =  '../../../uploads/data/'.$_REQUEST['reportType'] .'-Order-Items-Export' . ($presetIndex == 0 ? '' : ' - ' . $_POST ['preset_name']) . ' - ' . date ( 'Y-m-d', current_time ( 'timestamp' ) ) . '.' . ($_POST ['format'] == 'html-enhanced' ? 'html' : (in_array ( $_POST ['format'], array (
			'xlsx',
			'xls',
			'html' 
	) ) ? $_POST ['format'] : 'csv'));
	
	if ($_POST ['format'] == 'xlsx' || $_POST ['format'] == 'xls') {
		include_once (__DIR__ . '/HM_XLS_Export.php');
		$dest = new HM_XLS_Export ();
	} else if ($_POST ['format'] == 'html') {
		include_once (__DIR__ . '/HM_HTML_Export.php');
		$out = fopen ( $output ? 'php://output' : $filepath, 'w' );
		$dest = new HM_HTML_Export ( $out );
	} else if ($_POST ['format'] == 'html-enhanced') {
		include_once (__DIR__ . '/HM_HTML_Enhanced_Export.php');
		$out = fopen ( $output ? 'php://output' : $filepath, 'w' );
		$dest = new HM_HTML_Enhanced_Export ( $out );
	} else {
		include_once (__DIR__ . '/HM_CSV_Export.php');
		$out = fopen ( $output ? 'php://output' : $filepath, 'w' );
		$dest = new HM_CSV_Export ( $out );
		
	}
	 
	hm_xoiwcp_export_header ( $dest );
	hm_xoiwcp_export_body ( $dest, $start, $end );
		
		
	
	if ($_POST ['format'] == 'xlsx') {
		$dest->outputXLSX ( $filepath );
		if ($output) {
			readfile ( $filepath );
			unlink ( $filepath );
		}
	} else if ($_POST ['format'] == 'xls') {
		$dest->outputXLS ( $filepath );
		if ($output) {
			readfile ( $filepath );
			unlink ( $filepath );
		}
	} else {
		// Call destructor, if any
		$dest = null;
		fclose ( $out );
	}
	
	$_POST = $prevPost;
	echo $filepath;
	return $filepath;
}
function hm_xoiwcp_report_order_statuses() {
	$wcOrderStatuses = wc_get_order_statuses ();
	$orderStatuses = array ();
	if (! empty ( $_POST ['order_statuses'] )) {
		foreach ( $_POST ['order_statuses'] as $orderStatus ) {
			if (isset ( $wcOrderStatuses [$orderStatus] ))
				$orderStatuses [] = substr ( $orderStatus, 3 );
		}
	}
	return $orderStatuses;
}
function hm_xoiwcp_get_order_filter_fields() {
	global $wpdb, $hm_xoiwcp_order_filter_fields;
	if (! isset ( $hm_xoiwcp_order_filter_fields )) {
		$hm_xoiwcp_order_filter_fields = array ();
		
		$orderFields = $wpdb->get_col ( 'SELECT DISTINCT meta_key FROM ' . $wpdb->prefix . 'postmeta JOIN ' . $wpdb->prefix . 'posts ON (post_id=ID) WHERE post_type="shop_order"' );
		if ($orderFields === false) {
			return false;
		}
		foreach ( $orderFields as $orderField ) {
			$hm_xoiwcp_order_filter_fields ['O' . $orderField] = $orderField;
		}
		
		$customerFields = $wpdb->get_col ( 'SELECT DISTINCT meta_key FROM ' . $wpdb->prefix . 'usermeta' );
		if ($customerFields === false) {
			return false;
		}
		foreach ( $customerFields as $customerField ) {
			$hm_xoiwcp_order_filter_fields ['C' . $customerField] = $customerField;
		}
	}
	
	// Returned array must be grouped by field type (e.g. order fields together and customer fields together)
	return $hm_xoiwcp_order_filter_fields;
}

/**
 * Licensing *
 */
function hm_xoiwcp_license_check() {
	return true;
	if (isset ( $_POST ['hm_xoiwcp_license_deactivate'] )) {
		hm_xoiwcp_deactivate_license ();
	}
	
	if (get_option ( 'hm_xoiwcp_license_status', 'invalid' ) == 'valid') {
		return true;
	} else {
		if (isset ( $_POST ['hm_xoiwcp_license_activate'] ) && ! empty ( $_POST ['hm_xoiwcp_license_key'] ) && ctype_alnum ( $_POST ['hm_xoiwcp_license_key'] )) {
			update_option ( 'hm_xoiwcp_license_key', trim ( $_POST ['hm_xoiwcp_license_key'] ) );
			hm_xoiwcp_activate_license ();
			if (get_option ( 'hm_xoiwcp_license_status', 'invalid' ) == 'valid')
				return true;
		}
		
		echo ('
		<div style="background-color: #fff; border: 1px solid #ccc; padding: 20px; display: inline-block;">
			<form action="" method="post">
		');
		wp_nonce_field ( 'hm_xoiwcp_license_activate_nonce', 'hm_xoiwcp_license_activate_nonce' );
		echo ('
				<label for="hm_xoiwcp_license_activate" style="display: block; margin-bottom: 10px;">Please enter the license key provided when you purchased the plugin:</label>
				<input type="text" id="hm_xoiwcp_license_key" name="hm_xoiwcp_license_key" />
				<button type="submit" name="hm_xoiwcp_license_activate" value="1" class="button-primary">Activate</button>
			</form>
		</div>
		');
		return false;
	}
}
function hm_xoiwcp_activate_license() {
	
	// run a quick security check
	if (! check_admin_referer ( 'hm_xoiwcp_license_activate_nonce', 'hm_xoiwcp_license_activate_nonce' ))
		return; // get out if we didn't click the Activate button
			        
	// retrieve the license
	$license = trim ( get_option ( 'hm_xoiwcp_license_key' ) );
	
	// data to send in our API request
	$api_params = array (
			'edd_action' => 'activate_license',
			'license' => $license,
			'item_name' => urlencode ( HM_XOIWCP_ITEM_NAME ), // the name of our product in EDD
			'url' => home_url () 
	);
	
	// Call the custom API.
	$response = wp_remote_post ( HM_XOIWCP_STORE_URL, array (
			'timeout' => 15,
			'sslverify' => false,
			'body' => $api_params 
	) );
	
	// make sure the response came back okay
	if (is_wp_error ( $response ))
		return false;
		
		// decode the license data
	$license_data = json_decode ( wp_remote_retrieve_body ( $response ) );
	
	// $license_data->license will be either "valid" or "invalid"
	
	update_option ( 'hm_xoiwcp_license_status', $license_data->license );
}
function hm_xoiwcp_deactivate_license() {
	
	// run a quick security check
	if (! check_admin_referer ( 'hm_xoiwcp_license_deactivate_nonce', 'hm_xoiwcp_license_deactivate_nonce' ))
		return; // get out if we didn't click the dectivate button
			        
	// retrieve the license from the database
	$license = trim ( get_option ( 'hm_xoiwcp_license_key' ) );
	
	// data to send in our API request
	$api_params = array (
			'edd_action' => 'deactivate_license',
			'license' => $license,
			'item_name' => urlencode ( HM_XOIWCP_ITEM_NAME ), // the name of our product in EDD
			'url' => home_url () 
	);
	
	// Call the custom API.
	$response = wp_remote_post ( HM_XOIWCP_STORE_URL, array (
			'timeout' => 15,
			'sslverify' => false,
			'body' => $api_params 
	) );
	
	// make sure the response came back okay
	if (is_wp_error ( $response ))
		return false;
		
		// decode the license data
	$license_data = json_decode ( wp_remote_retrieve_body ( $response ) );
	
	// $license_data->license will be either "deactivated" or "failed"
	if ($license_data->license == 'deactivated')
		delete_option ( 'hm_xoiwcp_license_status' );
}

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define ( 'HM_XOIWCP_STORE_URL', 'http://store.hearkenmedia.com' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file
                                                                  
// the name of your product. This should match the download name in EDD exactly
define ( 'HM_XOIWCP_ITEM_NAME', 'Export Order Items Pro WordPress Plugin' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

if (! class_exists ( 'HM_XOIWCP_EDD_SL_Plugin_Updater' )) {
	// load our custom updater
	include (dirname ( __FILE__ ) . '/EDD_SL_Plugin_Updater.php');
}
function hm_xoiwcp_register_option() {
	// creates our settings in the options table
	register_setting ( 'hm_xoiwcp_license', 'hm_xoiwcp_license_key', 'hm_xoiwcp_sanitize_license' );
}
add_action ( 'admin_init', 'hm_xoiwcp_register_option' );
function hm_xoiwcp_plugin_updater() {
	
	// retrieve our license key from the DB
	$license_key = trim ( get_option ( 'hm_xoiwcp_license_key' ) );
	
	// setup the updater
	$edd_updater = new HM_XOIWCP_EDD_SL_Plugin_Updater ( HM_XOIWCP_STORE_URL, __FILE__, array (
			'version' => '2.0.3', // current version number
			'license' => $license_key, // license key (used get_option above to retrieve from DB)
			'item_name' => HM_XOIWCP_ITEM_NAME, // name of this plugin
			'author' => 'Hearken Media' 
	) // author of this plugin
 );
}
add_action ( 'admin_init', 'hm_xoiwcp_plugin_updater', 0 );
function hm_xoiwcp_sanitize_license($new) {
	$old = get_option ( 'hm_xoiwcp_license_key' );
	if ($old && $old != $new) {
		delete_option ( 'hm_xoiwcp_license_status' ); // new license has been entered, so must reactivate
	}
	return $new;
}
/**
 *  Method to set global Post method for the new report in module
 */
function staxo_setRequest(){
	
	
	
	$state 					=	false;
	$_POST['report_time']	=	"custom";
	$_POST['customer_role']		=	"Tutor";
	
	$which =	"Price";
	if(isset($_REQUEST['reportType']) && $_REQUEST['reportType'] == "invoice"){
		$which	=	'Total Product Cost';
	}
	
	$_POST['field_names'] 	=	array (
										'user_id'=> 'User ID' ,
										'order_date' => 'Order Date/Time',
										'order_id' => 'Order ID',
										'quantity' => 'Line Item Quantity',
										'__product___price' => $which,
										'line_subtotal' => 'Total Product Cost',
										'order_shipping_cost' => 'Shipping Cost',
										'__shop_order___order_total' => 'Order Total',
										'billing_name' => 'Billing Name',
										'__shop_order___billing_address_1' => 'Billing address 1',
										'__shop_order___billing_postcode' => 'Billing Postcode',
										'order_note_most_recent' => 'Order Note - Most Recent',
										'__shop_order___transaction_id' => 'Transaction ID',
										'order_status' => 'Order Status',
										'product_id' => 'Product ID',
										'product_sku' => 'Product SKU',
										'product_name' => 'Product Name',
										'variation_id' => 'Variation ID',
										'variation_sku' => 'Variation SKU',
										'variation_attributes' => 'Variation Attributes',
										'item_sku' => 'Item SKU',
										'product_categories' => 'Product Categories',
										'line_total' => 'Line Item Gross After Discounts',
										'line_tax' => 'Line Item Tax',
										'line_total_with_tax' => 'Line Item Total With Tax',
										'billing_phone' => 'Billing Phone',
										'billing_email' => 'Billing Email',
										'billing_address' => 'Billing Address',
										'shipping_name' => 'Billing Name',
										'shipping_phone' => 'Shipping Phone',
										'shipping_email' => 'Shipping Email',
										'shipping_address' => 'Shipping Address',
										'customer_order_note' => 'Customer Order Note',
										'order_shipping_methods' => 'Order Shipping Methods',
										'order_shipping_tax' => 'Order Shipping Tax',
										'order_shipping_cost_with_tax' => 'Order Shipping Cost With Tax',
										'__shop_order___order_number'=>"Order ID",
										'__shop_order___cart_discount'=>"_cart_discount"
								);
	
	
	$storeRequest	=	array(
							"invoice"=>array(
												'order_date',
												'__shop_order___order_number',
												 'quantity',	
												'__product___price',
												'__shop_order___cart_discount',
												 'order_shipping_cost',
												 '__shop_order___order_total',
												 'billing_name',
												 '__shop_order___billing_email',
												 '__shop_order___billing_address_1',
												 '__shop_order___billing_postcode',
												'order_status',
									 			'line_subtotal',
							),
							"customer"=>array(	
												'order_date',
												'__shop_order___order_number',
												'quantity',
												'product_sku',
												'product_name',
												 '__product___price',
												'billing_name',
												 '__shop_order___billing_address_1',
												 '__shop_order___billing_postcode',
												'order_status',
												'line_subtotal'
									),
							"summary"=>array(	
													'order_date',
													'__shop_order___order_number',
													'quantity',
													'product_sku',
													'product_name',
													'__product___price',
													'billing_name',
													'__shop_order___billing_address_1',
													'__shop_order___billing_postcode',
													'order_status',
													'line_subtotal'
												)
									);
	$status	=	array(
							"invoice"=>	array (
									0 => 'wc-completed',
									1 => 'wc-refunded',
							),
							"customer"=>	array (
									0 => 'wc-completed',
									1 => 'wc-refunded',
							),
							"summary"=>	array (
									0 => 'wc-completed',
									1 => 'wc-refunded',
							)
				);

	if(isset($_REQUEST['reportType']) && is_array($storeRequest[$_REQUEST['reportType']])){ // 
		$_POST['fields']			=	$storeRequest[$_REQUEST['reportType']];
		$_POST['order_statuses']	=	$status[$_REQUEST['reportType']];
		$state	=	true;
	}	
	
	return $state;
}

function staxo_getStoredArray(){
	return $storedArray;	 
}


function staxo_getRefunds($storedData,  $from , $to){
	global $wpdb;
		$from 	=	date ( 'Y-m-d H:i:s', $from );
		$to 	=	date( 'Y-m-d H:i:s', $to );
	
	$sql  =	<<<heredoc
select id as order_id , post_parent as post_parent, post_date as date from wp_posts where post_parent != 0	
AND post_date >= '{$from}' AND post_date < '$to'
AND post_type IN('shop_order', 'shop_order_refund')
heredoc;
	
	$refunds	= 	$wpdb->get_results($sql);
	$sorted		=	$storedData;
	
	
	if($refunds){
		foreach($refunds AS   $key=> $refund ){
			foreach($storedData as $product){
				if($product->order_id == $refund->order_id){
					unset($refunds[$key]);
				}
			}
		}
	
		foreach($refunds as $key=> $refund ){
			$products	=	staxo_getOrigOrder($refund->post_parent);
			$max =	count($products);
			$currentCount	=	0;
			$sqlGetOrderTotal 	=	<<<heredoc
select meta_value from wp_postmeta where meta_key="_refund_amount" and post_id={$refund->order_id}
heredoc;

			$value = $wpdb->get_row ($sqlGetOrderTotal);

			foreach($products as $product){
				$currentCount++;
				if($product->order_status	!= "wc-cancelled"){
					if($product->__shop_order___order_total != 	$value->meta_value){
						if($value->meta_value == $product->line_total){
							/*
							 * Hack to deal with partial orders. 
							 */
							$product->order_id		=	$refund->order_id;
							$product->order_status	=	'wc-refunded';
							$product->order_date	= $refund->date;
							$product->__shop_order___order_total = $value->meta_value;
							$refundOrder[] = $product;
						}else{
							if($currentCount==$max ){// Deal with the order not having any products and just assign ONE value to it as a small amount 
								$product->order_id		=	$refund->order_id;
								$product->order_status	=	'wc-refunded';
								$product->order_date	= $refund->date;
								$product->quantity		=	0.000001;
								$product->__shop_order___order_total = $value->meta_value;
								$refundOrder[] = $product;
							}
						}
					}else{
						$product->order_id		=	$refund->order_id;
						$product->order_status	=	'wc-refunded';
						$product->order_date	= $refund->date;
						$refundOrder[] = $product;
					}
				}
			}
		}

		if(is_array( 			$refundOrder)){
			$testRes =	array_merge($refundOrder	,$storedData);
			$col  = 'order_id';
			$sort = array();
			foreach ($testRes as $i => $obj) {
				$sort[$i] = $obj->{$col};
			}
			$sorted_test = array_multisort($sort, SORT_ASC, $testRes);			
			return $testRes;
		}
	}
	return $sorted;
}

function staxo_getOrigOrder($id){
	global $wpdb;
	$sqlTwo = <<<heredoc
SELECT
  order_item_meta__product_id.meta_value AS product_id,
  order_item_meta__variation_id.meta_value AS variation_id,
  order_items.order_id AS order_id,
  order_items.order_item_id AS order_item_id,
  order_item_meta__qty.meta_value AS quantity,
  order_item_meta__line_subtotal.meta_value AS line_subtotal,
  order_item_meta__line_total.meta_value AS line_total,
  posts.post_status AS order_status,
  posts.post_date AS order_date,
  meta__billing_first_name.meta_value AS billing_first_name,
  meta__billing_last_name.meta_value AS billing_last_name,
  meta__order_number.meta_value AS __shop_order___order_number,
  meta__cart_discount.meta_value AS __shop_order___cart_discount,
  meta__order_total.meta_value AS __shop_order___order_total,
  meta__billing_address_1.meta_value AS __shop_order___billing_address_1,
  meta__billing_postcode.meta_value AS __shop_order___billing_postcode
FROM
  wp_posts AS posts
LEFT JOIN
  wp_woocommerce_order_items AS order_items
ON
  (
    posts.ID = order_items.order_id
  ) AND(
    order_items.order_item_type = 'line_item'
  )
INNER JOIN
  wp_woocommerce_order_itemmeta AS order_item_meta__product_id
ON
  (
    order_items.order_item_id = order_item_meta__product_id.order_item_id
  ) AND(
    order_item_meta__product_id.meta_key = '_product_id'
  )
LEFT JOIN
  wp_woocommerce_order_itemmeta AS order_item_meta__variation_id
ON
  (
    order_items.order_item_id = order_item_meta__variation_id.order_item_id
  ) AND(
    order_item_meta__variation_id.meta_key = '_variation_id'
  )
LEFT JOIN
  wp_woocommerce_order_itemmeta AS order_item_meta__qty
ON
  (
    order_items.order_item_id = order_item_meta__qty.order_item_id
  ) AND(
    order_item_meta__qty.meta_key = '_qty'
  )
LEFT JOIN
  wp_woocommerce_order_itemmeta AS order_item_meta__line_total
ON
  (
    order_items.order_item_id = order_item_meta__line_total.order_item_id
  ) AND(
    order_item_meta__line_total.meta_key = '_line_total'
  )
LEFT JOIN
  wp_woocommerce_order_itemmeta AS order_item_meta__line_subtotal
ON
  (
    order_items.order_item_id = order_item_meta__line_subtotal.order_item_id
  ) AND(
    order_item_meta__line_subtotal.meta_key = '_line_subtotal'
  )
LEFT JOIN
  wp_postmeta AS meta__billing_first_name
ON
  (
    posts.ID = meta__billing_first_name.post_id AND meta__billing_first_name.meta_key = '_billing_first_name'
  )
LEFT JOIN
  wp_postmeta AS meta__billing_last_name
ON
  (
    posts.ID = meta__billing_last_name.post_id AND meta__billing_last_name.meta_key = '_billing_last_name'
  )
LEFT JOIN
  wp_postmeta AS meta__order_number
ON
  (
    posts.ID = meta__order_number.post_id AND meta__order_number.meta_key = '_order_number'
  )
LEFT JOIN
  wp_postmeta AS meta__cart_discount
ON
  (
    posts.ID = meta__cart_discount.post_id AND meta__cart_discount.meta_key = '_cart_discount'
  )
LEFT JOIN
  wp_postmeta AS meta__order_total
ON
  (
    posts.ID = meta__order_total.post_id AND meta__order_total.meta_key = '_order_total'
  )
LEFT JOIN
  wp_postmeta AS meta__billing_address_1
ON
  (
    posts.ID = meta__billing_address_1.post_id AND meta__billing_address_1.meta_key = '_billing_address_1'
  )
LEFT JOIN
  wp_postmeta AS meta__billing_postcode
ON
  (
    posts.ID = meta__billing_postcode.post_id AND meta__billing_postcode.meta_key = '_billing_postcode'
  )
WHERE
		posts.post_type IN('shop_order', 'shop_order_refund') AND
  		ID='{$id}'
heredoc;
	$orders 	= 	$wpdb->get_results($sqlTwo);

	
	
	return $orders;
}

function stax_cmp($a, $b){
	$t1 = strtotime($a->order_date);
	$t2 = strtotime($b->order_date);
	return $t1 - $t2;
	
}
?>