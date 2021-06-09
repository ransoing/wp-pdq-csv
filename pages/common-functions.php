<?php

namespace pdqcsv\pages;

// ensure this file isn't directly loaded
defined( 'ABSPATH' ) or die();

function outputHeader( $pageTitle = false ) {
	?>
	<h1 class="pdqcsv-title">
		<span class="pdqcsv-main-title">WP PDQ CSV</span><span class="pdqcsv-subtitle"> &mdash; Pretty darn quick CSV exporter</span><br>
		<span class="pdqcsv-page-name"><?php if ( $pageTitle ) echo $pageTitle; ?></span>
	</h1>
	<?php
}

function enqueueCommonCss() {
	$pluginRootUrl = plugin_dir_url( realpath(__DIR__) );
	wp_enqueue_style( 'pdqcsv-jquery-ui-theme',     $pluginRootUrl . 'vendor/jquery-ui/jquery-ui.theme.min.css' );
	wp_enqueue_style( 'pdqcsv-jquery-ui',           $pluginRootUrl . 'vendor/jquery-ui/jquery-ui.structure.min.css', ['pdqcsv-jquery-ui-theme'] );
	wp_enqueue_style( 'pdqcsv-page-common', 		$pluginRootUrl . 'pages/common.css' );
}
