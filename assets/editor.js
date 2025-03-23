jQuery(document).ready(function($) {
    $('#mi-plugin-seo-ia-generate').on('click', function() {
        var post_id = $('#post_ID').val();
        $.ajax({
            url: miPluginSEOIA.ajax_url,
            method: 'POST',
            data: {
                action: 'mi_plugin_seo_ia_generate_content',
                nonce: miPluginSEOIA.nonce,
                post_id: post_id
            },
            success: function(response) {
                if (response.success) {
                    var editor = tinymce.get('content');
                    if (editor) {
                        editor.insertContent(response.data);
                    } else {
                        $('#content').val($('#content').val() + '\n' + response.data);
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error al conectar con el servidor.');
            }
        });
    });
});