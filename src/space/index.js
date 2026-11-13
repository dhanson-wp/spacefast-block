import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import icon from './icon';
import Edit from './edit';
import './editor.scss';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
} );
