const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: async () => ( {
		...( await defaultConfig.entry() ),
		attribution: './src/attribution.js',
		'admin-bar-attribution': './src/admin-bar-attribution.js',
		'log-viewer': './src/log-viewer.js',
	} ),
};
