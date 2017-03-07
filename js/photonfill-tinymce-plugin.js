(function() {
	tinymce.create('tinymce.plugins.PhotonfillAdmin', {
		init : function( editor ) {
			editor.on( 'ExecCommand', function( event ) {
				var node,
					cmd = event.command,
					dom = editor.dom;
				if ( cmd === 'mceInsertContent' ) {
					var img,
						images;
					images = dom.select( 'img.lazyload' );
					for ( var i = 0, len = images.length; i < len; i++ ) {
						img = editor.selection.select( images[i] );
						lazySizes.loader.unveil( img );
					}
					editor.nodeChanged();

				}
			});
		},
	});

	tinymce.PluginManager.add( 'photonfill', tinymce.plugins.PhotonfillAdmin );

})();
