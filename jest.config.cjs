module.exports = {
	preset: '@wordpress/jest-preset-default',
	testMatch: [ '**/test/**/*.[jt]s?(x)' ],
	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
};
