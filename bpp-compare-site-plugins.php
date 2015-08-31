<?php

class BPP_Compare_Site_Plugins {

	/**
	 * Holds the list of plugin details for the current site.
	 * @var array
	 */
	public $sites_plugins = array();

	public function _construct() {}

	/**
	 * Hook in to actions to get things going.
	 */
	public function setup() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Register the CSS file for the plugin comparison admin page.
	 */
	public function register_admin_styles() {
		wp_register_style( 'bpp-compare-site-plugins-styles', plugin_dir_url( __FILE__ ) . 'css/bpp-compare-site-plugins.css', array(), '1', 'all' );
	}

	/**
	 * Add a 'Compare' sub-menu item under the Plugins admin menu.
	 */
	public function admin_menu() {
		add_plugins_page( 'Compare Site Plugins', 'Compare', 'activate_plugins', 'bpp-compare-site-plugins', array( $this, 'compare_plugins_admin_page' ) );
	}

	/**
	 * The 'Compare' admin menu page content.
	 * @return [type] [description]
	 */
	public function compare_plugins_admin_page() {
		wp_enqueue_style('bpp-compare-site-plugins-styles');

		if(
			( isset( $_GET['bulk-activate'] ) && $_GET['bulk-activate'] == true ) ||
			( isset( $_GET['bulk-network-activate'] ) && $_GET['bulk-network-activate'] == true )
		) {
			pew_compare_site_plugins_bulk_activate();
		}

		// Get the list of plugin details for the current site and store it for use later.
		$this->sites_plugins = array(
			'name' => get_bloginfo( 'name' ),
			'all' => get_plugins(),
			// 'all' => array_keys( get_plugins() ),
			'active' => get_option( 'active_plugins' ),
		);

		/*
		echo '<pre>';
		var_dump( get_plugins() );
		echo '</pre>';
		*/

		if( is_multisite() ) {
			$this->sites_plugins['network_active'] = array_keys( 		get_site_option('active_sitewide_plugins') );
		}

		$posted_site_id = 0;
		if( isset( $_POST['site_id'] ) ) {
			$posted_site_id = $_POST['site_id'];
		}

		if(
			( $posted_site_id > 0 || isset( $_POST['plugins'] ) ) &&
			isset( $_POST['nonce'] ) &&
			wp_verify_nonce( $_POST['nonce'], get_current_user_id() )
		) {
			$this->admin_page_step_2();
		} else {
			$this->admin_page_step_1();
		}
	}

	/**
	 * The default view of the 'Compare' admin menu page.
	 */
	public function admin_page_step_1() {
		$user_id = get_current_user_id();
		$user_sites = get_blogs_of_user( $user_id );
		?>

		<h1>Compare Site Plugins</h1>
		<form action="plugins.php?page=bpp-compare-site-plugins" method="post">
			<label for="plugins">Paste the <strong>Plugin Code</strong> from another site running this plugin</label>
			<textarea name="plugins" id="plugins" rows="5" cols="70"></textarea>

		<?php if( is_multisite() && $user_sites ): global $blog_id; ?>

			<p class="or">Or</p>
			<label>Select a Site</label>
			<select name="site_id">
				<option value=""></option>
			<?php
			foreach( $user_sites as $site ):
				if( $site->userblog_id != $blog_id ):
			?>
				<option value="<?php echo intval( $site->userblog_id ); ?>"><?php echo $site->blogname; ?></option>
			<?php endif; endforeach; ?>
			</select>

		<?php endif; ?>

			<?php wp_nonce_field($user_id, 'nonce'); ?>
			<input type="submit" class="button button-primary" value="Compare plugins">

			<label>Copy the following <strong>Plugin Code</strong> to compare the plugins on this site to another site</label>
			<textarea onclick="this.select()" rows="2" cols="70"><?php echo base64_encode( serialize( $this->sites_plugins ) ); ?></textarea>

		</form>
		<?php
	}

	/**
	 * The view for actually comparing plugins in the 'Compare' admin menu page.
	 */
	public function admin_page_step_2() {
		$site_id = intval( $_POST['site_id'] );
		$other_sites_plugins = unserialize( base64_decode( $_POST['plugins'] ) );

		if( !$other_sites_plugins[0] && $site_id > 0 ) {
			switch_to_blog( $site_id );
			$blog_deets = get_blog_details($site_id);

			$other_sites_plugins = array(
				'name' => $blog_deets->blogname,
				'active' => get_option('active_plugins')
			);

			restore_current_blog();
		}

		$this_sites_plugins = $this->array_check( $this->sites_plugins );
		$other_sites_plugins = $this->array_check( $other_sites_plugins );
		?>

		<h2 class="nav-tab-wrapper">
			<a href="#this-site" class="nav-tab nav-tab-active">This Site</a>
			<a href="#other-site" class="nav-tab"><?php echo $other_sites_plugins['name']; ?></a>
		</h2>

		<div id="this-site">

			<?php
			$other_sites_plugin_keys = array_keys( $other_sites_plugins['all'] );
			$this_sites_plugin_keys = array_keys( $this_sites_plugins['all'] );

			$missing_plugins = false;
			if( !$site_id ) {
				$missing_plugins = array_diff( $other_sites_plugin_keys, $this_sites_plugin_keys );
			}

			if( $missing_plugins ): ?>
				<h2>Missing Plugins</h2>
				<p>The following plugins need to be downloaded for this site.</p>

				<?php $this->render_missing_plugin_table( $missing_plugins, $other_sites_plugins ); ?>

			<?php endif; ?>

			<?php
			$active_plugins = array_diff( $other_sites_plugins['active'], $this_sites_plugins['active'] );

			if( $missing_plugins && $active_plugins ) {
				$active_plugins = array_diff( $active_plugins, $missing_plugins );
			}

			if( $active_plugins ):
			?>
				<h2>Active Plugins</h2>
				<p>The following plugins need to be activated for this site.</p>

				<form action="plugins.php?page=bpp-compare-site-plugins&bulk-activate=true" method="post">
					<ol>
						<?php $this->render_plugin_list_items( $active_plugins, false, true ); ?>
					</ol>
					<input type="submit" class="button button-secondary" value="Activate Selected Plugins">
				</form>
			<?php endif; ?>

			<?php
			$network_plugins = array_diff( $other_sites_plugins['network_active'], $this_sites_plugins['network_active'] );
			if( $missing_plugins && $network_plugins ) {
				$network_plugins = array_diff( $network_plugins, $missing_plugins );
			}

			if( $network_plugins ): ?>
				<h2>Network Plugins</h2>
				<p>The following plugins need to be network activated for <?php echo $other_sites_plugins['name']; ?>.</p>

				<form action="plugins.php?page=bpp-compare-site-plugins&bulk-network-activate=true" method="post">
					<ol>
						<?php $this->render_plugin_list_items( $network_plugins, false, true ); ?>
					</ol>
					<input type="submit" class="button button-secondary" value="Network Activate Selected Plugins">
				</form>
			<?php endif; ?>

		</div>

		<div id="other-site" class="hide">

			<?php
			$other_sites_plugin_keys = array_keys( $other_sites_plugins['all'] );
			$this_sites_plugin_keys = array_keys( $this_sites_plugins['all'] );

			$missing_plugins = false;
			if( !$site_id ) {
				$missing_plugins = array_diff( $this_sites_plugin_keys, $other_sites_plugin_keys );
			}

			if( $missing_plugins ): ?>
				<h2>Missing Plugins</h2>
				<p>The following plugins need to be downloaded for <?php echo $other_sites_plugins['name']; ?>.</p>

				<?php $this->render_missing_plugin_table( $missing_plugins, $this_sites_plugins ); ?>
			<?php endif; ?>

			<?php
			$active_plugins = array_diff( $this_sites_plugins['active'], $other_sites_plugins['active'] );
			if( $missing_plugins && $active_plugins ) {
				$active_plugins = array_diff( $active_plugins, $missing_plugins );
			}

			if( $active_plugins ):
			?>
				<h2>Active Plugins</h2>
				<p>The following plugins need to be activated for <?php echo $other_sites_plugins['name']; ?>.</p>

				<ol>
					<?php $this->render_plugin_list_items( $active_plugins ); ?>
				</ol>
			<?php endif; ?>

			<?php
			$network_plugins = array_diff( $this_sites_plugins['network_active'], $other_sites_plugins['network_active'] );
			if( $missing_plugins && $network_plugins ) {
				$network_plugins = array_diff( $network_plugins, $missing_plugins );
			}

			if( $network_plugins && is_multisite() && !$site_id ): ?>
				<h2>Network Plugins</h2>
				<p>The following plugins need to be activated network-wide for <?php echo $other_sites_plugins['name']?>.</p>

				<ol>
					<?php $this->render_plugin_list_items( $network_plugins ); ?>
				</ol>
			<?php endif; ?>

		</div>

		<script>
		jQuery(document).ready(function($) {
			var $containers = $('#this-site, #other-site');
			$('.nav-tab-wrapper a').click(function(e) {
				e.preventDefault();
				$this = $(this);
				$this.parent().find('.nav-tab-active').removeClass('nav-tab-active');
				$this.addClass('nav-tab-active');

				$containers.addClass('hide');

				var id = this.href.split('#')[1];
				$('#' + id).removeClass('hide');
			});

			$containers.each(function(index, element) {
				$this = $(this);
				if( $this.children().length == 0 ) {
					$this.append('<p>Everything looks good here!</p>');
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Handles activating multiple plugins all at once.
	 */
	public function bulk_activate_plugins() {
		$network_wide = false;
		if( $_GET['bulk-network-activate'] == true ) {
			$network_wide = true;
		}

		$checkboxes = $_POST['checkbox'];
		if( !$checkboxes || !is_array($checkboxes) ) {
			wp_die('Nothing selected.');
		}

		$result = activate_plugins( $checkboxes, $redirect = '', $network_wide );

		if( is_wp_error( $result ) ) {
	  		echo '<div id="message" class="error"><p>' . $result->get_error_message() . '</p></div>';
		} else {
			global $updated_message;
			$label = 'plugins';
			if( count($checkboxes) == 1 ) {
				$label = 'plugin';
			}

			$network_label = '';
			if( $network_wide ) {
				$network_label = 'Network ';
			}

			$updated_message = $network_label . 'Activated ' . count( $checkboxes ) . ' ' . $label;
			?>
			<div class="updated" id="message"><p><?php echo $updated_message; ?></p></div>
			<?php
		}
	}

	/**
	 * Given a list of $paths render <li> items containing a checkbox, a link to the plugin URI if available, and the plugin name.
	 * @param  array $paths            An array of plugin paths to get details and render as <li>s
	 * @param  boolean $plain          Whether to show a non-linked plain <li> item with no additional details. Defaults to false.
	 * @param  boolean $show_checkbox  Whether to show a checkbox in the list items or not.
	 */
	public function render_plugin_list_items( $paths, $plain = false, $show_checkbox = false ) {
		if( !is_array($paths) ) {
			return false;
		}

		foreach( $paths as $path ):

			$checkbox = '';
			if( $show_checkbox ) {
				$checkbox = "<input type='checkbox' value='$path' name='checkbox[]'> ";
			}

			if( $plain ) {
			?>
				<li><?php echo $checkbox . $path; ?></li>
			<?php
			} else {
				$deets = get_plugin_data( trailingslashit(WP_PLUGIN_DIR) . $path );
				$plugin_url = $deets['PluginURI'];
				$plugin_name = $deets['Name'];
				// $plugin_description = $deets['Description'];

				if( $plugin_url ) {
					$plugin_name = '<a href="' . esc_url( $plugin_url ) . '" target="_blank" title="' . esc_attr( $path ) . '">' . $plugin_name . '</a>';
				}
			?>
				<li><?php echo $checkbox; echo $plugin_name; ?></li>
			<?php }

		endforeach;
	}

	/**
	 * Render an HTML table of details about plugins that are missing from a site.
	 * @param  array $missing_plugins     An array of plugin paths that are misisng from the current site.
	 * @param  array $other_sites_plugins Array of all plugin details to compare against.
	 */
	public function render_missing_plugin_table( $missing_plugins, $other_sites_plugins ) {
		?>
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<th></th>
					<th>Plugin Name</th>
					<th>Path</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$count = 1;
				foreach( $missing_plugins as $plugin_path ):
					$plugin = $other_sites_plugins['all'][ $plugin_path ];
					$plugin_name = $plugin['Name'];
					if( $plugin['PluginURI'] ) {
						$plugin_name = '<a href="' . $plugin['PluginURI'] . '" target="_blank">' . $plugin_name . '</a>';
					}

					$class = 'alternate';
					if( $count % 2 ) {
						$class = '';
					}
				?>
				<tr class="<?php echo $class; ?>">
					<td><?php echo $count . '.'; ?></td>
					<td><?php echo $plugin_name; ?></td>
					<td><?php echo $plugin_path; ?></td>
				</tr>
				<?php
				$count++;
				endforeach;
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Helper function to make sure the proper array keys are set to avoid PHP notices and errors.
	 * @param  array $arr The array to check.
	 */
	public function array_check( $arr ) {
		$keys = array( 'all', 'network_active', 'active' );
		foreach( $keys as $key ) {
			if( !is_array( $arr[$key] ) ) {
				$arr[ $key ] = array();
			}
		}

		return $arr;
	}
}

// Create the class and kick things off...
$bpp_compare_site_plugins = new BPP_Compare_Site_Plugins();
$bpp_compare_site_plugins->setup();
