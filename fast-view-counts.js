function unique( array ) {
	return array.filter( function( el, index, arr ) {
		return index === arr.indexOf( el );
	} );
}

jQuery( document ).ready(
	function( $ ) {
		var countId     = 'data-view-count-id';
		var countUpdate = 'data-view-count-update';
		var dateTimeStamp      = 'data-view-date-timestamp';

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

		var dateElements = $( '[' + dateTimeStamp + ']' );
		var dates        = [];
		$( dateElements ).each(
			function( i, dateView ) {
				dates[i] = {
					timestamp: $( dateView ).attr( dateTimeStamp ),
				};
			}
		);

		if ( 0 === views.length && 0 === dates.length ) {
			return;
		}

		var data = {
			action: 'update_view_counts',
			nonce: update_view_counts.nonce,
			views: views,
			dates: dates,
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
					dateElements.each(
						function( i, dateView ) {
							$( dateView ).html( response.data.dates[i] );
						}
					);
				}
			}
		);
	}
);
