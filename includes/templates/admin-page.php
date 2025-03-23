<div class="wrap">
    <h1>SEO con IA</h1>
    <?php echo $message; ?>
	<div class="ad-banner" style="margin-top: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
		<p>Potencia tu SEO con <a href="https://openrouter.ai/?ref=mi-plugin-seo-ia" target="_blank">OpenRouter</a> - ¡Obtén tu clave API ahora!</p>
	</div>
    <form method="post" class="mi-plugin-seo-ia-form">
        <?php wp_nonce_field('mi_plugin_seo_ia_action', 'mi_plugin_seo_ia_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="page_id">Selecciona una página base:</label></th>
                <td>
                    <select name="page_id" id="page_id">
                        <?php
                        $pages = get_pages();
                        foreach ($pages as $page) {
                            echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="content_length">Longitud del contenido:</label></th>
                <td>
                    <select name="content_length" id="content_length">
                        <option value="300-500">300-500 palabras</option>
                        <option value="500-800">500-800 palabras</option>
                        <option value="800+">800+ palabras</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="content_type">Tipo de contenido:</label></th>
                <td>
                    <select name="content_type" id="content_type">
                        <option value="post">Artículo</option>
                        <option value="page">Página</option>
                    </select>
                </td>
            </tr>
            <tr id="category_row" style="display: none;">
                <th><label for="category_id">Categoría:</label></th>
                <td>
                    <select name="category_id" id="category_id">
                        <option value="">Sin categoría</option>
                        <?php
                        $categories = get_categories(['hide_empty' => false]);
                        foreach ($categories as $category) {
                            echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="custom_links">Enlaces Internos Personalizados:<br><small>(URL|título, uno por línea)</small></label></th>
                <td>
                    <textarea name="custom_links" id="custom_links" rows="3" placeholder="https://tudominio.com/pagina|Texto del enlace" class="regular-text"></textarea>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="action" value="analyze_seo" class="button button-primary">Analizar SEO</button>
            <button type="submit" name="action" value="generate_article" class="button">Generar Contenido Relacionado</button>
        </p>
    </form>

    <?php if (!empty($keywords_display) || !empty($meta_description_display)): ?>
        <h2>Resultados SEO</h2>
        <div class="seo-results" style="background: #f0f0f0; padding: 10px; border: 1px solid #ccc;">
            <?php if (!empty($keywords_display)): ?>
                <p><?php echo $keywords_display; ?></p>
            <?php endif; ?>
            <?php if (!empty($meta_description_display)): ?>
                <p><?php echo $meta_description_display; ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Registro de Depuración</h2>
    <div class="log-output" style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto;">
        <?php echo $log_content ? $log_content : 'No hay mensajes recientes en el log.'; ?>
    </div>
</div>

<style>
    .mi-plugin-seo-ia-form .form-table th {
        width: 200px;
        vertical-align: top;
    }
    .mi-plugin-seo-ia-form .form-table td {
        padding-bottom: 15px;
    }
    .mi-plugin-seo-ia-form select, 
    .mi-plugin-seo-ia-form textarea {
        width: 100%;
        max-width: 400px;
    }
    .mi-plugin-seo-ia-form .submit {
        margin-top: 20px;
    }
    .mi-plugin-seo-ia-form .button {
        margin-right: 10px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var contentType = document.getElementById('content_type');
        var categoryRow = document.getElementById('category_row');

        function toggleCategory() {
            if (contentType.value === 'post') {
                categoryRow.style.display = 'table-row';
            } else {
                categoryRow.style.display = 'none';
            }
        }

        toggleCategory();
        contentType.addEventListener('change', toggleCategory);
    });
</script>