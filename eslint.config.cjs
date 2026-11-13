const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );

module.exports = [
	{
		ignores: [
			'**/build/**',
			'**/node_modules/**',
			'**/vendor/**',
			'.superpowers/**',
		],
	},
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		rules: {
			// The experimental components core's own blocks use for the same
			// controls: tools panels, toggle groups, unit inputs, and Cover's
			// full-height toolbar button.
			'@wordpress/no-unsafe-wp-apis': [
				'error',
				{
					'@wordpress/block-editor': [
						'__experimentalBlockFullHeightAligmentControl',
					],
					'@wordpress/components': [
						'__experimentalToggleGroupControl',
						'__experimentalToggleGroupControlOption',
						'__experimentalToolsPanel',
						'__experimentalToolsPanelItem',
						'__experimentalUnitControl',
						'__experimentalUseCustomUnits',
					],
				},
			],
		},
	},
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [
			'**/@(test|__tests__)/**/*.{js,jsx,ts,tsx,mjs,cjs}',
			'**/*.@(test|spec).{js,jsx,ts,tsx,mjs,cjs}',
		],
	},
];
