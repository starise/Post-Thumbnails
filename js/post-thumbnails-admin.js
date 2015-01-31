window.PostThumbnails = {

    setThumbnailHTML: function(html, id){
	    jQuery('.inside', '#' + id).html(html);
    },

    setThumbnailID: function(thumb_id, id){
	    var field = jQuery('input[value=_' + id + '_thumbnail_id]', '#list-table');
	    if ( field.size() > 0 ) {
		    jQuery('#meta\\[' + field.attr('id').match(/[0-9]+/) + '\\]\\[value\\]').text(thumb_id);
	    }
    },

    removeThumbnail: function(id, nonce){
	    jQuery.post(ajaxurl, {
		    action:'set-' + id + '-thumbnail', post_id: jQuery('#post_ID').val(), thumbnail_id: -1, _ajax_nonce: nonce, cookie: encodeURIComponent(document.cookie)
	    }, function(str){
		    if ( str == '0' ) {
			    alert( setPostThumbnailL10n.error );
		    } else {
			    PostThumbnails.setThumbnailHTML(str, id);
		    }
	    }
	    );
    },


    setAsThumbnail: function(thumb_id, id, nonce){
	    var $link = jQuery('a#' + id + '-thumbnail-' + thumb_id);
		$link.data('thumbnail_id', thumb_id);
	    $link.text( setPostThumbnailL10n.saving );
	    jQuery.post(ajaxurl, {
		    action:'set-' + id + '-thumbnail', post_id: post_id, thumbnail_id: thumb_id, _ajax_nonce: nonce, cookie: encodeURIComponent(document.cookie)
	    }, function(str){
		    var win = window.dialogArguments || opener || parent || top;
		    $link.text( setPostThumbnailL10n.setThumbnail );
		    if ( str == '0' ) {
			    alert( setPostThumbnailL10n.error );
		    } else {
			    $link.show();
			    $link.text( setPostThumbnailL10n.done );
			    $link.fadeOut( 2000, function() {
				    jQuery('tr.' + id + '-thumbnail').hide();
			    });
			    win.PostThumbnails.setThumbnailID(thumb_id, id);
			    win.PostThumbnails.setThumbnailHTML(str, id);
		    }
	    }
	    );
    }
}
