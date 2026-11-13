import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import icon from './icon';
import Edit from './edit';
import variations from './variations';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	variations,
} );
