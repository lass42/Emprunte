function hb_formatted_date( date ) {
	if ( ! date || ( date == '0000-00-00' ) ) {
		return '';
	} else {
		date = date.split( '-' );
		date = new Date( date[0], date[1] - 1, date[2] );
		return jQuery.datepick.formatDate( hb_date_format, date );
	}
}

function hb_db_formatted_date( date ) {
	try {
		date = jQuery.datepick.parseDate( hb_date_format, date );
	} catch( e ) {
		date = false;
	}
	if ( date ) {
		return  jQuery.datepick.formatDate( 'yyyy-mm-dd', date );
	} else {
		return '0000-00-00';
	}
}