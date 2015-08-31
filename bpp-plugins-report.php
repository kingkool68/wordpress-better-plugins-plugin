<?php

class BPP_Plugin_Report {
	/**
	 * Holds plugin stats so they are only generated once per page load.
	 * @var (boolean/array)
	 */
	private $the_plugin_stats = false;

	public function _construct() {}

	/**
	 * Hook in to actions to get things going...
	 */
	public function setup() {
		add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
	}

	/**
	 * Add a network admin menu to view the plugin report. It lives under the Plugins admin menu item.
	 */
	public function network_admin_menu() {
		add_plugins_page( 'Plugins Report', 'Plugins Report', 'activate_plugins', 'bpp-plugins-report', array( $this, 'do_plugin_report' ) );
	}

	/**
	 * The body of the plugin report page consisting of Inactive Plugins, Active Plugins, and Network Activated Plugins.
	 */
	public function do_plugin_report() {
		$args = array(
			'title' => 'Inactive Plugins (Maybe safe to delete?)',
			'headers' => array( '', 'Plugin Name', 'Path' ),
			'callback' => array( $this, 'inactive_callback' ),
		);
		$this->plugin_table( $args );

		$args = array(
			'title' => 'Active Plugins',
			'headers' => array( '', 'Plugin Name', 'Site(s)', 'Count' ),
			'callback' => array( $this, 'active_callback' ),
		);
		$this->plugin_table( $args );

		$args = array(
			'title' => 'Network Activated Plugins',
			'headers' => array( '', 'Plugin'),
			'callback' => array( $this, 'network_activated_callback' ),
		);
		$this->plugin_table( $args );
	}

	/**
	 * Renders an HTML table of plugin details.
	 * @param  array $args The table title, table headers, and callback method for rendering the table body.
	 */
	public function plugin_table( $args ) {
		$all_plugins = $this->get_plugin_stats();

		extract( $args );
		?>
		<h2><?php echo $args['title']; ?></h2>
		<table class="wp-list-table widefat">
			<thead>
				<tr>
					<?php foreach( $headers as $header ): ?>
						<th><?php echo $header; ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				// Use a callback to render the table body.
				if ( is_callable( $callback ) ) {
					call_user_func( $callback, $all_plugins );
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get all of the plugins from all of the sites and store it as an array so we only need to do the work once per page load.
	 * @return array All plugin details for each site.
	 */
	public function get_plugin_stats() {
		if( $this->the_plugin_stats ) {
			return $this->the_plugin_stats;
		}

		$all_plugins = get_plugins();
		$sites = wp_get_sites();
		$network_actived_plugins = array_keys( get_site_option( 'active_sitewide_plugins' ) );

		foreach( $all_plugins as $slug => $plugin ) {
			// Make sure the 'active_sites' key is set to prevent undefined index errors.
			$all_plugins[ $slug ]['active_sites'] = array();

			// Note if the plugin is network activated.
			if( in_array( $slug, $network_actived_plugins ) ) {
				$all_plugins[ $slug ]['Network'] = true;
			}
		}

		foreach( $sites as $index => $site ) {
			$blog_id = intval( $site['blog_id'] );
			switch_to_blog( $blog_id );
			$active_plugins = get_option( 'active_plugins', array() );
			foreach( $active_plugins as $slug ) {
				if( !in_array( $slug, $network_actived_plugins ) ) {
					$all_plugins[ $slug ]['active_sites'][] = $blog_id;
				}
			}
		}

		$this->the_plugin_stats = $all_plugins;

		return $all_plugins;
	}

	/**
	 * Callback function for inactive plugins table body.
	 * @param  array $all_plugins List of plugin stats from get_plugin_stats() method.
	 */
	public function inactive_callback( $all_plugins ) {
		$count = 1;
		foreach( $all_plugins as $path => $plugin ):
			if( !$plugin['Network'] && count( $plugin['active_sites'] ) < 1 ) {
				$class = 'alternate';
				if( $count % 2 ) {
					$class = '';
				}
				?>
				<tr class="<?php echo $class; ?>">
					<td><?php echo $count++; ?>.</td>
					<td><?php echo $plugin['Name']; ?></td>
					<td><?php echo $path; ?></td>
				</tr>
				<?php
			}
		endforeach;
	}

	/**
	 * Callback function for active plugins table body.
	 * @param  array $all_plugins List of plugin stats from get_plugin_stats() method.
	 */
	public function active_callback( $all_plugins ) {
		$count = 1;
		foreach( $all_plugins as $path => $plugin ):
			if( !$plugin['Network'] && count( $plugin['active_sites'] ) >= 1 ) {
				$class = 'alternate';
				if( $count % 2 ) {
					$class = '';
				}
				if( empty( $plugin['Name'] ) ) {
					$plugin['Name'] = $path;
				}
				?>
				<tr class="<?php echo $class; ?>">
					<td><?php echo $count++; ?>.</td>
					<td><?php echo $plugin['Name']; ?></td>
					<td>
					<?php
					$output = array();
					foreach( $plugin['active_sites'] as $blog_id ) {
						$site_url = get_site_url( $blog_id, '/wp-admin/plugins.php' );
						$site = get_blog_details( array( 'blog_id' => $blog_id ) );

						$output[] = '<a href="' . esc_url( $site_url ) . '" target="_blank">' . $site->blogname . '</a>';
					}

					echo implode( ', ', $output );
					?>
					</td>
					<td><?php echo count( $plugin['active_sites'] ); ?></td>
				</tr>
				<?php
			}
		endforeach;
	}

	/**
	 * Callback function for network activated plugins table body.
	 * @param  array $all_plugins List of plugin stats from get_plugin_stats() method.
	 */
	public function network_activated_callback( $all_plugins ) {
		$count = 1;
		foreach( $all_plugins as $path => $plugin ):
			if( $plugin['Network'] ) {
				$class = 'alternate';
				if( $count % 2 ) {
					$class = '';
				}
				?>
				<tr class="<?php echo $class; ?>">
					<td><?php echo $count++; ?>.</td>
					<td><?php echo $plugin['Name']; ?></td>
				</tr>
				<?php
			}
		endforeach;
	}

}

// Create the class and kick things off...
$bpp_plugin_report = new bpp_Plugin_Report();
$bpp_plugin_report->setup();
