<?php
/*
Plugin Name: Better Plugins Plugin
Description: Makes dealing with plugins easier by providing tools for comparing, reporting, and filtering plugins.
Version: 1.0.1
Author: Russell Heimlich
Author URI: http://www.russellheimlich.com
GitHub Plugin URI: kingkool68/wordpress-better-plugins-plugin
*/


// Make the lists of plugins filterable as you type thanks to this handy snippit of JavaScript.
function bpp_plugins_footer() {
?>
<script>
	// via https://github.com/charliepark/faq-patrol
	// extend :contains to be case-insensitive; via http://stackoverflow.com/questions/187537/
	jQuery.expr[':'].contains = function(a,i,m){return (a.textContent || a.innerText || "").toUpperCase().indexOf(m[3].toUpperCase())>=0;};

	jQuery(document).ready(function($) {
		$('#plugin-search-input').keyup( function() {
			$val = $(this).val();
			if( $val.length < 2 ) {
				$("#the-list > tr").show();
			} else {
				$("#the-list > tr").hide();
				$("#the-list .plugin-title strong:contains("+ $val +")").parent().parent().show();
			}
		}).focus();

	});
</script>
<?php
}
add_action( 'admin_footer-plugins.php', 'bpp_plugins_footer' );


// Network Plugins Report - Find which plugins aren't used on any site in your network from a single page.
if( is_network_admin() ) {
	include 'bpp-plugins-report.php';
}

// Compare Site Plugins with an external site or another site in a network to see which ones need to be activated.
if( is_admin() ) {
	include 'bpp-compare-site-plugins.php';
}
