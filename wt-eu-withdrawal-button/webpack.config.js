const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin-dashboard': path.resolve( __dirname, 'react/src/index.js' ),
	},
	output: {
		path: path.resolve( __dirname, 'react/build' ),
		filename: '[name].js',
	},
};
