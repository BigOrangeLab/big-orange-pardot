const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry,
		attribution: './src/attribution.js',
		'admin-bar-attribution': './src/admin-bar-attribution.js',
	},
};
