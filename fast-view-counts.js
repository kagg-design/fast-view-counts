function unique( array ) {
	return array.filter( function( el, index, arr ) {
		return index === arr.indexOf( el );
	} );
}

jQuery( document ).ready(
	function( $ ) {
		var countId     = 'data-view-count-id';
		var countUpdate = 'data-view-count-update';

		var countElements = $( '[' + countId + ']' );
		var views         = [];
		$( countElements ).each(
			function( i, countView ) {
				views[i] = {
					id: $( countView ).attr( countId ),
					update: $( countView ).attr( countUpdate ),
				};
			}
		);
		if ( 0 === views.length ) {
			return;
		}

		var data = {
			action: 'update_view_counts',
			nonce: update_view_counts.nonce,
			views: views,
		};

		$.post(
			update_view_counts.url,
			data,
			function( response ) {
				if ( response.success ) {
					countElements.each(
						function( i, countView ) {
							$( countView ).html( response.data.counts[i] );
						}
					);
				}
			}
		);
	}
);
