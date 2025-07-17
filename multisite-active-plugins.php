<?php
/*
Plugin Name: Multisite Active Plugins
Description: A simple plugin for WordPress multisites that lists all installed plugins, shows a summary of activation counts with anchor links, and details which sites have each plugin active. Visible only in network admin for super admins.
Version: 1.0.0
Author: Joel Lisenby
*/

// Ensure this only runs in multisite
if (!is_multisite()) {
    return;
}

// Add network admin menu page
add_action('network_admin_menu', 'ms_plugin_lister_add_menu');

function ms_plugin_lister_add_menu() {
    add_menu_page(
        'Multisite Plugin List',
        'Active Plugins',
        'manage_network',
        'ms-plugin-list',
        'ms_plugin_lister_display_page',
        'dashicons-plugins-checked',
        99
    );
}

// Display the page content
function ms_plugin_lister_display_page() {
    if (!is_super_admin() || !is_network_admin()) {
        wp_die('Access denied.');
    }

    echo '<div class="wrap">';
    echo '<h1>Multisite Plugin List</h1>';

    // Get all installed plugins
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    $all_plugins = get_plugins();

    // Get network-activated plugins
    $network_active = array_keys(get_site_option('active_sitewide_plugins', array()));

    // Get all sites
    $sites = get_sites(array('number' => false, 'orderby' => 'id'));

    // Collect active plugins per site
    $site_active_plugins = array();
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        $site_active_plugins[$site->blog_id] = get_option('active_plugins', array());
        $site_names[$site->blog_id] = get_bloginfo('name');
        restore_current_blog();
    }

    // Prepare data for each plugin
    $plugin_data = array();
    foreach ($all_plugins as $basename => $plugin_info) {
        $name = $plugin_info['Name'];
        $slug = sanitize_title($name); // For anchor

        if (in_array($basename, $network_active)) {
            // Network active: active on all sites
            $active_count = count($sites);
            $active_sites = array_column($sites, 'blog_id');
        } else {
            // Per-site activations
            $active_sites = array();
            foreach ($site_active_plugins as $site_id => $actives) {
                if (in_array($basename, $actives)) {
                    $active_sites[] = $site_id;
                }
            }
            $active_count = count($active_sites);
        }

        $plugin_data[$basename] = array(
            'name' => $name,
            'slug' => $slug,
            'count' => $active_count,
            'sites' => $active_sites,
        );
    }

    // Sort plugins alphabetically by name
    uasort($plugin_data, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    // Display summary
    echo '<h2>Summary</h2>';
    echo '<ul>';
    echo '<li style="border-bottom: 1px solid #c7c7c7;"><span style="display: grid; grid-template-columns: 3fr 1fr; max-width: 600px;padding: 0.5em 0;"><span class="name">Plugin Name</span><span class="count" style="text-align: right;">Active Sites</span></a></li>';
    foreach ($plugin_data as $basename => $data) {
        echo '<li style="border-bottom: 1px solid #c7c7c7;"><a style="display: grid; grid-template-columns: 3fr 1fr; max-width: 600px;padding: 0.5em 0;" href="#' . esc_attr($data['slug']) . '"><span class="name">' . esc_html($data['name']) . '</span><span class="count" style="text-align: right;">' . $data['count'] . '</span></a></li>';
    }
    echo '</ul>';

    // Display details
    echo '<h2>Details</h2>';
    foreach ($plugin_data as $basename => $data) {
        echo '<h3 id="' . esc_attr($data['slug']) . '">' . esc_html($data['name']) . '</h3>';
        if ($data['count'] > 0) {
            echo '<p>Active on ' . $data['count'] . ' site(s):</p>';
            echo '<ul>';
            foreach ($data['sites'] as $site_id) {
                $site_name = isset($site_names[$site_id]) ? $site_names[$site_id] : 'Site ID ' . $site_id;
                $site_url = get_home_url($site_id);
                echo '<li><a href="' . esc_url($site_url) . '" target="_blank">' . esc_html($site_name) . ' (ID: ' . $site_id . ')</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Not active on any sites.</p>';
        }
    }

    echo '</div>';
}
