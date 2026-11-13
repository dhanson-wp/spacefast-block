import * as blockEditor from '@wordpress/block-editor';

// WordPress 6.7 has only the experimental name; later versions only the stable
// one. Looked up by variable names so the bundle imports neither by name.
const STABLE_TYPOGRAPHY_EXPORT = 'getTypographyClassesAndStyles';
const EXPERIMENTAL_TYPOGRAPHY_EXPORT =
	'__experimentalGetTypographyClassesAndStyles';
const getTypographyClassesAndStyles =
	blockEditor[ STABLE_TYPOGRAPHY_EXPORT ] ??
	blockEditor[ EXPERIMENTAL_TYPOGRAPHY_EXPORT ];
const { __experimentalUseColorProps: useColorProps, useSettings } = blockEditor;

/**
 * The classes and styles for the block's color and font size supports. The
 * block doesn't let WordPress print them on its wrapper, so the link mode can
 * put them on its button instead, as the core Button block does.
 * Mirrors spacefast_block_color_and_font_size() on the server.
 *
 * @param {Object} attributes Block attributes.
 * @return {{className: string, style: Object}} Class names and inline styles.
 */
export default function useColorAndFontSizeProps( attributes ) {
	const colorProps = useColorProps( attributes );
	const [ fluidTypographySettings, layout ] = useSettings(
		'typography.fluid',
		'layout'
	);
	const typographyProps = getTypographyClassesAndStyles( attributes, {
		typography: { fluid: fluidTypographySettings },
		layout: { wideSize: layout?.wideSize },
	} );

	return {
		className: [ colorProps.className, typographyProps.className ]
			.filter( Boolean )
			.join( ' ' ),
		style: { ...colorProps.style, ...typographyProps.style },
	};
}
