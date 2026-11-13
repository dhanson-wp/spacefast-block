import { useViewportMatch } from '@wordpress/compose';

/**
 * Places a tools panel's options menu beside the sidebar on larger screens,
 * as core blocks do.
 *
 * @return {Object} Props for `ToolsPanel`'s `dropdownMenuProps`.
 */
export default function useToolsPanelDropdownMenuProps() {
	const isMobile = useViewportMatch( 'medium', '<' );
	return ! isMobile
		? {
				popoverProps: {
					placement: 'left-start',
					// Inner sidebar width (248px) - button width (24px) - border
					// (1px) + padding (16px) + spacing (20px).
					offset: 259,
				},
			}
		: {};
}
