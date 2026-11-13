import metadata from '../../block.json';

/**
 * The block.json default of each named attribute.
 *
 * @param {string[]} names Attribute names.
 * @return {Object} Attribute defaults keyed by name.
 */
export function getDefaults( names ) {
	return Object.fromEntries(
		names.map( ( name ) => [ name, metadata.attributes[ name ].default ] )
	);
}
