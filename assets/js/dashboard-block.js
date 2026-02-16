( function( blocks, element, i18n, blockEditor ) {
	const el = element.createElement;
	const __ = i18n.__;
	const useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'wbcom/wbfsm-dashboard', {
		title: __( 'WB Shop Manager Dashboard', 'wb-frontend-shop-manager-for-woocommerce' ),
		description: __( 'Embed the frontend shop manager dashboard for partner users.', 'wb-frontend-shop-manager-for-woocommerce' ),
		icon: 'store',
		category: 'widgets',
		supports: {
			html: false,
		},
		edit: function() {
			const props = useBlockProps( {
				className: 'wbfsm-dashboard-block-placeholder',
			} );

			return el(
				'div',
				props,
				el( 'strong', null, __( 'WB Shop Manager Dashboard', 'wb-frontend-shop-manager-for-woocommerce' ) ),
				el( 'p', null, __( 'Frontend dashboard will render here on the live page for allowed users.', 'wb-frontend-shop-manager-for-woocommerce' ) )
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor );
