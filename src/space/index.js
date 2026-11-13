import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import icon from './icon';
import Edit from './edit';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
} );
